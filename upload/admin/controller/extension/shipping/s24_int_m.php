<?php


require_once(DIR_SYSTEM . 'library/s24_int_m/vendor/autoload.php');

use Mijora\S24IntOpencart\Controller\ManifestCtrl;
use Mijora\S24IntOpencart\Controller\ParcelCtrl;
use Mijora\S24IntOpencart\Helper;
use Mijora\S24IntOpencart\Model\Country;
use Mijora\S24IntOpencart\Model\Offer;
use Mijora\S24IntOpencart\Model\ParcelDefault;
use Mijora\S24IntOpencart\Model\Service;
use Mijora\S24IntOpencart\Model\ShippingOption;
use Mijora\S24IntOpencart\Model\ShippingOptionCountry;
use Mijora\S24IntOpencart\Params;
use Mijora\S24IntApiLib\API;
use Mijora\S24IntApiLib\Order;

class ControllerExtensionShippingS24IntM extends Controller
{
    private $error = array();

    private $tabs = [
        'general', 'api', 'sender-info', 'price', 'cod', 'terminals',
        'tracking-email', 'advanced'
    ];

    public function install()
    {
        $sql_array = [
            "
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "s24_int_m_country` (
                `id` int(11) unsigned NOT NULL,
                `code` varchar(4) NOT NULL DEFAULT '',
                `name` varchar(255) NOT NULL DEFAULT '',
                `en_name` varchar(255) DEFAULT '',
                PRIMARY KEY (`id`,`code`)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",
            "
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "s24_int_m_option` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `enabled` tinyint(1) NOT NULL DEFAULT '0',
                `type` tinyint(1) NOT NULL DEFAULT '1',
                `allowed_services` text,
                `offer_priority` tinyint(1) NOT NULL DEFAULT '1',
                `sort_order` int(11) NOT NULL DEFAULT '0',
                `title` varchar(255) DEFAULT NULL,
                `price_type` tinyint(1) NOT NULL DEFAULT '1',
                `price` decimal(15,4) DEFAULT NULL,
                `free_shipping` decimal(15,4) DEFAULT NULL,
                PRIMARY KEY (`id`)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",
            "
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "s24_int_m_option_country` (
                `option_id` int(11) unsigned NOT NULL,
                `country_code` varchar(4) NOT NULL DEFAULT '',
                `offer_priority` tinyint(1) DEFAULT NULL,
                `price_type` tinyint(1) DEFAULT NULL,
                `price` decimal(15,4) DEFAULT NULL,
                `free_shipping` decimal(15,4) DEFAULT NULL,
                PRIMARY KEY (`option_id`,`country_code`)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",
            "
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "s24_int_m_order` (
                `order_id` int(11) unsigned NOT NULL,
                `selected_service` varchar(50) DEFAULT NULL,
                `offer_data` text,
                `terminal_id` varchar(200) DEFAULT NULL,
                `terminal_data` text,
                `added_at` datetime DEFAULT NULL,
                `updated_at` datetime DEFAULT NULL,
                PRIMARY KEY (`order_id`)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",
            "
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "s24_int_m_order_api` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `order_id` int(11) unsigned NOT NULL,
                `api_cart_id` varchar(255) DEFAULT NULL,
                `api_shipment_id` varchar(255) DEFAULT NULL,
                `created_at` datetime DEFAULT NULL,
                `canceled` tinyint(1) NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`),
                KEY `order_id` (`order_id`),
                KEY `api_cart_id` (`api_cart_id`),
                KEY `api_shipment_id` (`api_shipment_id`)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",
            "
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "s24_int_m_parcel_default` (
                `category_id` int(11) unsigned NOT NULL,
                `weight` decimal(15,8) NOT NULL DEFAULT '1.00000000',
                `length` decimal(15,8) NOT NULL DEFAULT '10.00000000',
                `width` decimal(15,8) NOT NULL DEFAULT '10.00000000',
                `height` decimal(15,8) NOT NULL DEFAULT '10.00000000',
                `hs_code` varchar(255) DEFAULT NULL,
                PRIMARY KEY (`category_id`),
                UNIQUE KEY `category_id` (`category_id`)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",
            "
            INSERT INTO " . DB_PREFIX . "s24_int_m_parcel_default (category_id) VALUES (0)
            "
        ];

        foreach ($sql_array as $sql) {
            $this->db->query($sql);
        }
    }

    public function uninstall()
    {
        $this->load->model('setting/setting');

        $this->model_setting_setting->deleteSetting('s24_int_m');

        $sql_array = [
            "DROP TABLE IF EXISTS `" . DB_PREFIX . "s24_int_m_country`",
            "DROP TABLE IF EXISTS `" . DB_PREFIX . "s24_int_m_option`",
            "DROP TABLE IF EXISTS `" . DB_PREFIX . "s24_int_m_option_country`",
            "DROP TABLE IF EXISTS `" . DB_PREFIX . "s24_int_m_order`",
            "DROP TABLE IF EXISTS `" . DB_PREFIX . "s24_int_m_order_api`",
            "DROP TABLE IF EXISTS `" . DB_PREFIX . "s24_int_m_parcel_default`"
        ];

        foreach ($sql_array as $sql) {
            $this->db->query($sql);
        }

        // remove modification file
        Helper::removeModificationXml();
    }

    private function installCountries()
    {
        $config_key = Params::PREFIX . 'country_last_update';
        $last_country_update = $this->config->get($config_key);

        if (!$last_country_update) {
            $last_country_update = 0;
        }

        $last_country_update = (int) $last_country_update;

        $countries_installed = $this->db->query("
            SELECT COUNT(id) as total FROM `" . DB_PREFIX . "s24_int_m_country` 
        ");

        $has_countries = true;
        if (!$countries_installed->rows || (int) $countries_installed->row['total'] === 0) {
            $has_countries = false;
        }

        // check if need to update
        if ($has_countries && time() < $last_country_update) {
            return;
        }

        if (!Helper::$token) {
            Helper::setApiStaticToken($this->config);
        }

        $all_countries = Helper::getCountries(true);

        if (empty($all_countries)) {
            Helper::saveSettings($this->db, [
                $config_key => time() + Params::COUNTRY_CHECK_TIME_RETRY
            ]);
            return;
        }

        $this->db->query("
            TRUNCATE TABLE " . DB_PREFIX . "s24_int_m_country
        ");

        $offset = 0;
        while ($slice = array_slice($all_countries, $offset, 50)) {
            $offset += 50;

            $data = array_map(function ($item) {
                return "('" . $item->id . "', '" . $item->code . "', '" . $this->db->escape($item->name) . "', '" . $this->db->escape($item->en_name) . "')";
            }, $slice);

            $sql = "INSERT INTO `" . DB_PREFIX . "s24_int_m_country` (`id`, `code`, `name`, `en_name`)
                VALUES " . implode(', ', $data);
            $this->db->query($sql);
        }

        Helper::saveSettings($this->db, [
            $config_key => time() + Params::COUNTRY_CHECK_TIME
        ]);

        $this->session->data['success'] = 'API Countries updated';
    }

    public function index()
    {
        $this->load->language('extension/shipping/s24_int_m');

        $this->document->setTitle($this->language->get('heading_title'));

        $extension_home = 'extension';
        if (version_compare(VERSION, '3.0.0', '>=')) {
            $extension_home = 'marketplace';
        }

        if (isset($this->request->get['fixdb']) && $this->validate()) {
            $this->fixDb();
            $this->response->redirect($this->url->link('extension/shipping/s24_int_m', $this->getUserToken(), true));
        }

        if (isset($this->request->get['fixxml']) && $this->validate()) {
            Helper::copyModificationXml();
            $this->session->data['success'] = $this->language->get(Params::PREFIX . 'xml_updated');
            $this->response->redirect($this->url->link($extension_home . '/modification', $this->getUserToken(), true));
        }

        $current_tab = 'tab-general';

        if (($this->request->server['REQUEST_METHOD'] == 'POST')) {
            $this->prepPostData();

            if (isset($this->request->post['api_settings_update'])) {
                unset($this->request->post['api_settings_update']);
                $this->saveSettings($this->request->post);
                $this->session->data['success'] = $this->language->get('s24_int_m_msg_setting_saved');
                $current_tab = 'api';
            }

            if (isset($this->request->post['module_settings_update'])) {
                unset($this->request->post['module_settings_update']);
                $this->saveSettings($this->request->post);
                $this->session->data['success'] = $this->language->get('s24_int_m_msg_setting_saved');
                $current_tab = 'general';
            }

            if (isset($this->request->post['sender_settings_update'])) {
                unset($this->request->post['sender_settings_update']);
                $this->saveSettings($this->request->post);
                $this->session->data['success'] = $this->language->get('s24_int_m_msg_setting_saved');
                $current_tab = 'sender-info';
            }

            $this->response->redirect($this->url->link('extension/shipping/s24_int_m', $this->getUserToken() . '&tab=' . $current_tab, true));
        }

        $data[Params::PREFIX . 'version'] = Params::VERSION;

        // set static tokens
        Helper::setApiStaticToken($this->config);

        // update API coutry list if needed
        $this->installCountries();

        $data['success'] = '';
        $data['error_warning'] = [];

        if (isset($this->error['warning'])) {
            $data['error_warning'][] = $this->error['warning'];
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', $this->getUserToken(), true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get(Params::PREFIX . 'text_extension'),
            'href' => $this->url->link($extension_home . '/extension', $this->getUserToken() . '&type=shipping', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/shipping/s24_int_m', $this->getUserToken(), true)
        );

        $data['action'] = $this->url->link('extension/shipping/s24_int_m', $this->getUserToken(), true);

        $data['cancel'] = $this->url->link($extension_home . '/extension', $this->getUserToken() . '&type=shipping', true);

        $this->load->model('localisation/tax_class');

        $data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();

        $this->load->model('localisation/geo_zone');

        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        $data['ajax_url'] = 'index.php?route=extension/shipping/s24_int_m/ajax&' . $this->getUserToken();

        // opencart 3 expects status and sort_order begin with shipping_ 
        $setting_prefix = '';
        if (version_compare(VERSION, '3.0.0', '>=')) {
            $setting_prefix = 'shipping_';
        }

        $oc_settings = [
            'status', 'sort_order'
        ];

        foreach ($oc_settings as $value) {
            if (isset($this->request->post[$setting_prefix . Params::PREFIX . $value])) {
                $data[Params::PREFIX . $value] = $this->request->post[$setting_prefix . Params::PREFIX . $value];
                continue;
            }

            $data[Params::PREFIX . $value] = $this->config->get($setting_prefix . Params::PREFIX . $value);
        }

        // Load saved settings or values from post request
        $module_settings = [
            // general tab
            'tax_class_id', 'geo_zone_id',
            // api tab
            'api_token', 'api_test_mode', 'api_consolidate', 'api_use_vat_price',
            // sender-info tab
            'sender_name', 'sender_street', 'sender_postcode',
            'sender_city', 'sender_country', 'sender_phone', 'sender_email',
        ];

        foreach ($module_settings as $key) {
            if (isset($this->request->post[Params::PREFIX . $key])) {
                $data[Params::PREFIX . $key] = $this->request->post[Params::PREFIX . $key];
                continue;
            }

            $data[Params::PREFIX . $key] = $this->config->get(Params::PREFIX . $key);
        }

        $version_check = @json_decode($this->config->get(Params::PREFIX . 'version_check_data'), true);
        if (empty($version_check) || Helper::isTimeToCheckVersion($version_check['timestamp'])) {
            $git_version = Helper::hasGitUpdate();
            $version_check = [
                'timestamp' => time(),
                'git_version' => $git_version
            ];
            $this->saveSettings([
                Params::PREFIX . 'version_check_data' => json_encode($version_check)
            ]);
        }

        $data[Params::PREFIX . 'git_version'] = $version_check['git_version'];

        //check if we still need to show notification
        if ($version_check['git_version'] !== false && !Helper::isModuleVersionNewer($version_check['git_version']['version'])) {
            $data[Params::PREFIX . 'git_version'] = false;
        }

        $data[Params::PREFIX . 'db_check'] = Helper::checkDbTables($this->db);
        $data[Params::PREFIX . 'db_fix_url'] = $this->url->link('extension/shipping/s24_int_m', $this->getUserToken() . '&fixdb', true);

        $pd_categories_data = $this->getPdCategories();

        $data = array_merge($data, $pd_categories_data);

        $data[Params::PREFIX . 'xml_check'] = Helper::isModificationNewer();
        $data[Params::PREFIX . 'xml_fix_url'] = $this->url->link('extension/shipping/s24_int_m', $this->getUserToken() . '&fixxml', true);

        // Load dynamic strings
        $data['dynamic_strings'] = $this->getDynamicStrings();

        $partial_data = [
            's24_int_options' => ShippingOption::getShippingOptions($this->db),
            'dynamic_strings' => $data['dynamic_strings']
        ];
        $data['s24_int_shipping_options'] = $this->load->view('extension/shipping/s24_int_m/shipping_options_partial', $partial_data);

        $data['services'] = Helper::getServices($this->session);

        $data['countries'] = Country::getAllCountries($this->db);

        $data['sender_tab_partial'] = $this->load->view('extension/shipping/s24_int_m/sender_tab_partial', $data);

        $data['price_types'] = [
            '0' => $this->language->get(Params::PREFIX . 'offer_price_0')
        ];
        foreach (Offer::OFFER_PRICE_AVAILABLE as $type) {
            $data['price_types'][$type] = $this->language->get(Offer::getOfferPriceTranslationString($type));
        }

        $data['price_type_addons'] = Offer::OFFER_PRICE_ADDONS;

        $data['priority_types'] = [
            '0' => $this->language->get(Params::PREFIX . 'offer_priority_0')
        ];
        foreach (Offer::OFFER_PRIORITY_AVAILABLE as $type) {
            $data['priority_types'][$type] = $this->language->get(Offer::getOfferPriorityTranslationString($type));
        }

        $data['shipping_types'] = [];
        foreach (Service::TYPE_AVAILABLE as $type) {
            $data['shipping_types'][$type] = $this->language->get(Service::getTypeTranslationString($type));
        }

        $data['services_list'] = [];

        if (!is_array($data['services'])) {
            $data['error_warning'][] = 'Problems with API';
            $data['services'] = [];
        }

        foreach ($data['services'] as $service) {
            $is_courier = (bool) $service->get(Service::DELIVERY_TO_ADDRESS);
            $data['services_list'][$service->get(Service::SERVICE_CODE)] = [
                'name' => $service->get(Service::NAME),
                'shippingType' => $is_courier ? Service::TYPE_COURIER : Service::TYPE_TERMINAL
            ];
        }

        // load JS strings
        $data['js_strings'] = $this->getJsStrings();

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/shipping/s24_int_m/settings', $data));
    }

    protected function getUserToken()
    {
        if (version_compare(VERSION, '3.0.0', '>=')) {
            return 'user_token=' . $this->session->data['user_token'];
        }

        return 'token=' . $this->session->data['token'];
    }


    protected function fixDb()
    {
        $db_check = Helper::checkDbTables($this->db);
        if (!$db_check) {
            return; // nothing to fix
        }

        foreach ($db_check as $table => $fix) {
            $this->db->query($fix);
        }
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/shipping/s24_int_m')) {
            $this->error['warning'] = $this->language->get(Params::PREFIX . 'error_permission');
            return false; // skip the rest
        }

        return !$this->error;
    }

    protected function saveSettings($data)
    {
        Helper::saveSettings($this->db, $data);
    }

    /**
     * Converts certain settings that comes as array into string
     */
    protected function prepPostData()
    {
        // handle checkbox in certain tabs, if off it will be missing from request
        if (isset($this->request->post['module_settings_update'])) {
            $this->request->post[Params::PREFIX . 'status'] = isset($this->request->post[Params::PREFIX . 'status']) ? 1 : 0;
            $this->request->post[Params::PREFIX . 'api_test_mode'] = isset($this->request->post[Params::PREFIX . 'api_test_mode']) ? 1 : 0;
            $this->request->post[Params::PREFIX . 'api_consolidate'] = isset($this->request->post[Params::PREFIX . 'api_consolidate']) ? 1 : 0;
            $this->request->post[Params::PREFIX . 'api_use_vat_price'] = isset($this->request->post[Params::PREFIX . 'api_use_vat_price']) ? 1 : 0;
        }

        // Opencart 3 expects status to be shipping_s24_int_m_status
        if (version_compare(VERSION, '3.0.0', '>=') && isset($this->request->post[Params::PREFIX . 'status'])) {
            $this->request->post['shipping_' . Params::PREFIX . 'status'] = $this->request->post[Params::PREFIX . 'status'];
            unset($this->request->post[Params::PREFIX . 'status']);
        }

        // Opencart 3 expects sort_order to be shipping_s24_int_m_sort_order
        if (version_compare(VERSION, '3.0.0', '>=') && isset($this->request->post[Params::PREFIX . 'sort_order'])) {
            $this->request->post['shipping_' . Params::PREFIX . 'sort_order'] = $this->request->post[Params::PREFIX . 'sort_order'];
            unset($this->request->post[Params::PREFIX . 'sort_order']);
        }
    }

    private function getDynamicStrings()
    {
        $strings = [];

        foreach (Service::TYPE_AVAILABLE as $service_type) {
            $service_type_key = Service::getTypeTranslationString($service_type);
            $strings[$service_type_key] = $this->language->get($service_type_key);
        }

        foreach (Offer::OFFER_PRIORITY_AVAILABLE as $priority_type) {
            $priority_type_key = Offer::getOfferPriorityTranslationString($priority_type);
            $strings[$priority_type_key] = $this->language->get($priority_type_key);
        }

        foreach (Offer::OFFER_PRICE_AVAILABLE as $price_type) {
            $price_type_key = Offer::getOfferPriceTranslationString($price_type);
            $strings[$price_type_key] = $this->language->get($price_type_key);
        }

        return $strings;
    }

    private function getJsStrings()
    {
        $js_string_keys = [
            'default', 'edit', 'delete', 'save', 'cancel', 'type_in',
            'price_edit_unsaved_alert', 'price_country_delete_alert'
        ];

        $strings = [];


        foreach ($js_string_keys as $key) {
            $strings[$key] = $this->language->get(Params::PREFIX . 'js_' . $key);
        }

        return $strings;
    }

    public function ajax()
    {
        $this->load->language('extension/shipping/s24_int_m');
        if (!$this->validate()) {
            echo json_encode($this->error);
            exit();
        }
        $restricted = json_encode(['warning' => 'Restricted']);
        switch ($_GET['action']) {
            case 'getPdCategories':
                $page = 1;
                if (isset($this->request->get['page'])) {
                    $page = (int) $this->request->get['page'];
                }

                if ($page < 1) {
                    $page = 1;
                }

                echo json_encode(['data' => $this->getPdCategories($page)]);
                exit();
            case 'savePdCategory':
                echo json_encode(['data' => $this->savePdCategory()]);
                exit();
            case 'resetPdCategory':
                echo json_encode(['data' => $this->resetPdCategory()]);
                exit();
            case 'getShippingOptionList':
                echo json_encode(['data' => $this->getShippingOptionList()]);
                exit();
            case 'getShippingOption':
                echo json_encode(['data' => $this->getShippingOption()]);
                exit();
            case 'saveShippingOption':
                echo json_encode(['data' => $this->saveShippingOption()]);
                exit();
            case 'deleteShippingOption':
                echo json_encode(['data' => $this->deleteShippingOption()]);
                exit();
            case 'saveOptionCountry':
                echo json_encode(['data' => $this->saveOptionCountry()]);
                exit();
            case 'deleteOptionCountry':
                echo json_encode(['data' => $this->deleteOptionCountry()]);
                exit();
            case 'getOrderPanel':
                echo json_encode(['data' => $this->getOrderPanel()]);
                exit();
            case 'registerShipment':
                echo json_encode(['data' => $this->registerShipment()]);
                exit();
            case 'cancelShipment':
                echo json_encode(['data' => $this->cancelShipment()]);
                exit();
            case 'getLabel':
                echo json_encode(['data' => $this->getLabel()]);
                exit();
            case 'getManifest':
                echo json_encode(['data' => $this->getManifest()]);
                exit();
            case 'loadManifestPage':
                echo json_encode(['data' => $this->loadManifestPage()]);
                exit();
            case 'updateSelectedTerminal':
                echo json_encode(['data' => $this->updateSelectedTerminal()]);
                exit();

            default:
                die($restricted);
                break;
        }
    }

    private function saveShippingOption()
    {
        $result = [
            'shipping_method' => null,
            'update_result' => false
        ];

        $option_id = isset($this->request->post['option_id']) ? (int) $this->request->post['option_id'] : null;

        // must be a valid integer id, otherwise use null for creation
        if ($option_id !== null && $option_id < 1) {
            $option_id = null;
        }

        $title = isset($this->request->post['title']) ? $this->request->post['title'] : '';
        $enabled = isset($this->request->post['enabled']) ? (bool) $this->request->post['enabled'] : false;
        $type = isset($this->request->post['type']) ? (int) $this->request->post['type'] : 0;
        $sort_order = isset($this->request->post['sort_order']) ? (int) $this->request->post['sort_order'] : 0;
        $allowed_services = isset($this->request->post['allowed_services']) ? $this->request->post['allowed_services'] : '';
        $offer_priority = isset($this->request->post['offer_priority']) ? (int) $this->request->post['offer_priority'] : null;
        $price_type = isset($this->request->post['price_type']) ? (int) $this->request->post['price_type'] : null;
        $price = isset($this->request->post['price']) ? $this->request->post['price'] : null;
        $free_shipping = isset($this->request->post['free_shipping']) ? $this->request->post['free_shipping'] : null;

        if ($offer_priority === 0) {
            $offer_priority = null;
        }

        if ($price_type === 0) {
            $price_type = null;
        }

        if ($type !== null && !in_array($type, Service::TYPE_AVAILABLE)) {
            $errors[] = 'Invalid shipping method type';
        }

        if ($offer_priority !== null && !in_array($offer_priority, Offer::OFFER_PRIORITY_AVAILABLE)) {
            $errors[] = 'Invalid Offer priority';
        }

        if ($price_type !== null && !in_array($price_type, Offer::OFFER_PRICE_AVAILABLE)) {
            $errors[] = 'Invalid price type';
        }

        if (!empty($errors)) {
            $result['error'] = implode(", \n", $errors);
            return $result;
        }

        if ($price === 'null' || $price === '') {
            $price = null;
        }

        if ($free_shipping === 'null' || $free_shipping === '') {
            $free_shipping = null;
        }

        if (!empty($errors)) {
            $result['error'] = implode(", \n", $errors);
            return $result;
        }

        $shipping_option = $option_id ? ShippingOption::getShippingOption($option_id, $this->db, false) : new ShippingOption();

        if ($shipping_option === null) {
            $result['error'] = 'Bad shipping method ID';
            return $result;
        }

        $shipping_option->title = $title;
        $shipping_option->type = $type;
        $shipping_option->enabled = $enabled ? 1 : 0;
        $shipping_option->sort_order = $sort_order;
        $shipping_option->allowed_services = $allowed_services;
        $shipping_option->offer_priority = $offer_priority;
        $shipping_option->price_type = $price_type;
        $shipping_option->price = $price === null ? null : (float) $price;
        $shipping_option->free_shipping = $free_shipping === null ? null : (float) $free_shipping;

        $sql_result = $shipping_option->save($this->db);

        $result['shipping_method'] = $shipping_option;
        $result['update_result'] = $sql_result;

        if ($sql_result) {
            $result = array_merge($result, $this->getShippingOptionList());
        }

        return $result;
    }

    private function deleteShippingOption()
    {
        $result = [
            'update_result' => false
        ];

        $option_id = isset($this->request->post['option_id']) ? (int) $this->request->post['option_id'] : 0;

        if ($option_id < 1) {
            $errors[] = 'option_id must be > 0';
        }

        if (!empty($errors)) {
            $result['error'] = implode(", \n", $errors);
            return $result;
        }

        ShippingOption::deleteById($option_id, $this->db);

        $result['update_result'] = true;

        return $result;
    }

    private function getShippingOptionList()
    {
        return [
            'shipping_options' => $this->load->view(
                'extension/shipping/s24_int_m/shipping_options_partial',
                [
                    'dynamic_strings' => $this->getDynamicStrings(),
                    's24_int_options' => ShippingOption::getShippingOptions($this->db)
                ]
            )
        ];
    }

    private function getShippingOption()
    {
        $result = [
            'shipping_method' => null
        ];

        if (!isset($this->request->post['shipping_option_id'])) {
            $result['error'] = 'No shipping option ID given';
            return $result;
        }

        $result['shipping_method'] = ShippingOption::getShippingOption((int) $this->request->post['shipping_option_id'], $this->db);

        if (!empty($result['shipping_method']->countries)) {
            $result['countries_html'] = ShippingOptionCountry::getShippingOptionCountry(
                (int) $this->request->post['shipping_option_id'],
                $result['shipping_method']->countries[array_keys($result['shipping_method']->countries)[0]]->country_code,
                $this->db
            );
        }

        return $result;
    }

    private function deleteOptionCountry()
    {
        $result = [
            'update_result' => false
        ];

        $option_id = isset($this->request->post['option_id']) ? (int) $this->request->post['option_id'] : 0;
        $country_code = isset($this->request->post['country_code']) ? $this->request->post['country_code'] : null;

        if ($option_id < 1) {
            $errors[] = 'option_id must be > 0';
        }

        if (empty($country_code) || strlen($country_code) > 2) {
            $errors[] = 'country_code must be valid ISO_2 country code';
        }

        if (!empty($errors)) {
            $result['error'] = implode(", \n", $errors);
            return $result;
        }

        $result['update_result'] = ShippingOptionCountry::deleteShippingOptionCountry($option_id, $country_code, $this->db);

        return $result;
    }

    private function saveOptionCountry()
    {
        $result = [
            'update_result' => false
        ];
        $errors = [];
        $option_country = new ShippingOptionCountry();

        $option_id = isset($this->request->post['option_id']) ? (int) $this->request->post['option_id'] : 0;
        $country_code = isset($this->request->post['country_code']) ? $this->request->post['country_code'] : null;
        $offer_priority = isset($this->request->post['offer_priority']) ? (int) $this->request->post['offer_priority'] : null;

        $price_type = isset($this->request->post['price_type']) ? (int) $this->request->post['price_type'] : null;
        $price = isset($this->request->post['price']) ? $this->request->post['price'] : null;
        $free_shipping = isset($this->request->post['free_shipping']) ? $this->request->post['free_shipping'] : null;

        if ($offer_priority === 0) {
            $offer_priority = null;
        }

        if ($price_type === 0) {
            $price_type = null;
        }

        if ($option_id < 1) {
            $errors[] = 'option_id must be > 0';
        }

        if (empty($country_code) || strlen($country_code) > 2) {
            $errors[] = 'country_code must be valid ISO_2 country code';
        }

        if ($offer_priority !== null && !in_array($offer_priority, Offer::OFFER_PRIORITY_AVAILABLE)) {
            $errors[] = 'Invalid Offer priority';
        }

        if ($price_type !== null && !in_array($price_type, Offer::OFFER_PRICE_AVAILABLE)) {
            $errors[] = 'Invalid price type';
        }

        if (!empty($errors)) {
            $result['error'] = implode(", \n", $errors);
            return $result;
        }

        if ($price === 'null') {
            $price = null;
        }

        if ($free_shipping === 'null') {
            $free_shipping = null;
        }


        $option_country->option_id = $option_id;
        $option_country->country_code = $country_code;
        $option_country->offer_priority = $offer_priority;
        $option_country->price_type = $price_type;
        $option_country->price = empty($price) ? null : (float) $price;
        $option_country->free_shipping = empty($free_shipping) ? null : (float) $free_shipping;

        $result['update_result'] = $option_country->save($this->db);
        $result['fields'] = $option_country;

        return $result;
    }

    private function resetPdCategory()
    {
        $result = [
            'post' => $this->request->post,
            'update_result' => false
        ];

        if (!isset($this->request->post['category_id'])) {
            $result['error'] = 'No category ID given';
            return $result;
        }

        $category_id = (int) $this->request->post['category_id'];

        if ($category_id <= 0) {
            $result['error'] = 'Category ID must be > 0';
            return $result;
        }

        $result['update_result'] = ParcelDefault::remove($category_id, $this->db);

        return $result;
    }

    private function savePdCategory()
    {
        $result = [
            'post' => $this->request->post,
            'update_result' => false
        ];

        ParcelDefault::$db = $this->db;
        $parcel_default = new ParcelDefault();

        $parcel_default->category_id = (int) $this->request->post['category_id'];
        $parcel_default->weight = (float) $this->request->post['weight'];
        $parcel_default->length = (float) $this->request->post['length'];
        $parcel_default->width = (float) $this->request->post['width'];
        $parcel_default->height = (float) $this->request->post['height'];
        $parcel_default->hs_code = $this->request->post['hs_code'];

        $result['validation'] = $parcel_default->fieldValidation();
        $result['parcel_default'] = $parcel_default;

        $is_valid = true;
        foreach ($result['validation'] as $validation_result) {
            if (!$validation_result) {
                $is_valid = false;
                break;
            }
        }

        if ($is_valid) {
            $result['update_result'] = $parcel_default->save();
        }

        return $result;
    }

    private function getPdCategories($page = 1)
    {
        $data = [];

        $page_limit = (int) $this->config->get('config_limit_admin');
        $this->load->model('catalog/category');
        $filter_data = array(
            'sort'  => 'name',
            'order' => 'ASC',
            'start' => ($page - 1) * $page_limit,
            'limit' => $page_limit
        );

        $partial_data = [];
        $partial_data['s24_int_m_categories'] = $this->model_catalog_category->getCategories($filter_data);

        $this->associateWithParcelDefault($partial_data['s24_int_m_categories']);

        $data['global_parcel_default'] = ParcelDefault::getGlobalDefault($this->db);
        $data['pd_categories_partial'] = $this->load->view(
            'extension/shipping/s24_int_m/pd_categories_partial',
            $partial_data
        );

        $category_total = $this->model_catalog_category->getTotalCategories();
        $data['pd_categories_paginator'] = $this->getPagination($page, ceil($category_total / $page_limit));

        return $data;
    }

    private function associateWithParcelDefault(&$categories)
    {
        $categories_id = array_map(function ($item) {
            return $item['category_id'];
        }, $categories);

        $parcel_defaults = ParcelDefault::getMultipleParcelDefault($categories_id, $this->db);

        $categories = array_map(function ($item) use ($parcel_defaults) {
            $item['default_data'] = [];
            if (isset($parcel_defaults[$item['category_id']])) {
                $item['default_data'] = $parcel_defaults[$item['category_id']];
            }
            return $item;
        }, $categories);
    }

    private function getPagination($current_page, $total_pages)
    {
        return $this->load->view('extension/shipping/s24_int_m/pagination', [
            'current_page' => (int) $current_page,
            'total_pages' => (int) $total_pages
        ]);
    }

    private function getOrderPanel()
    {
        $result = [];
        if (!isset($this->request->post['order_id'])) {
            $result['error'] = 'Missing Order ID';
            return $result;
        }

        $order_id =  (int) $this->request->post['order_id'];

        $data = [];

        $data['order_id'] = $order_id;

        $order_data = Offer::getOrderOffer($order_id, $this->db);

        if (empty($order_data)) {
            return [
                'error' => 'S24 International: Order has no offers associated'
            ];
        }

        $data['offer'] = Helper::base64Decode($order_data['offer_data'], false);

        // siusk24 api does not have this param, could be usefull to move it into Offer class
        $data['is_terminal'] = isset($data['offer']['parcel_terminal_type']) && $data['offer']['parcel_terminal_type'];

        $data['terminal_data'] = Helper::base64Decode($order_data['terminal_data'], false);
        $data['terminal_id'] = $order_data['terminal_id'];

        $data['api_url'] = (bool) $this->config->get(Params::PREFIX . 'api_test_mode') ? Params::API_URL_TEST : Params::API_URL;

        $this->load->model('sale/order');
        $order_products =  $this->model_sale_order->getOrderProducts($order_id);
        $products_data = ParcelCtrl::getProductsDataByOrder($order_id, $this->db);

        foreach ($order_products as $key => $product) {
            $product = array_merge($product, $products_data[$product['product_id']]);
            $order_products[$key] = $product;
        }

        $data['products'] = $order_products;
        $data['order'] = $this->model_sale_order->getOrder($order_id);

        $data['parcels'] = ParcelCtrl::makeParcelsFromCart($order_products, $this->db, $this->weight, $this->length, $this->config);
        $country = new Country($data['order']['shipping_iso_code_2'], $this->db);
        // $data['items'] = ParcelCtrl::makeItemsFromProducts($order_products, $country, $this->db);

        $data['shipment_status'] = 'Shipment has yet to be registered';

        $sql = $this->db->query("
            SELECT api_cart_id, api_shipment_id FROM " . DB_PREFIX . "s24_int_m_order_api 
            WHERE order_id = " . (int) $order_id . " AND canceled = 0
            ORDER BY order_id DESC 
            LIMIT 1
        ");

        $data['api_data'] = false;
        if ($sql->rows) {
            $data['api_data'] = [
                'manifest_id' => $sql->row['api_cart_id'],
                'shipment_id' => $sql->row['api_shipment_id']
            ];

            $data['shipment_status'] = 'Registered. Generating label';
        }

        try {

            Helper::setApiStaticToken($this->config);
            $api = Helper::getApiInstance(); //new API($token, $test_mode);

            $this->load->model('sale/order');

            $order_products =  $this->model_sale_order->getOrderProducts($order_id);
            $products_data = ParcelCtrl::getProductsDataByOrder($order_id, $this->db);

            foreach ($order_products as $key => $product) {
                $product = array_merge($product, $products_data[$product['product_id']]);
                $order_products[$key] = $product;
            }

            $data['parcels'] = ParcelCtrl::makeParcelsFromCart($order_products, $this->db, $this->weight, $this->length, $this->config);

            if ($data['api_data']) {
                try {
                    $result['label_response'] = $api->getLabel($data['api_data']['shipment_id']);

                    $data['label_status'] = $result['label_response'];
                    if (isset($result['label_response']->base64pdf) && $result['label_response']->base64pdf) {
                        $data['shipment_status'] = 'Registered. Label generated';
                    }
                } catch (\Exception $e) {
                    $result['label_response'] = $e->getMessage();
                    $data['label_status'] = null;
                }
                try {
                    $result['track_response'] = $api->trackOrder($data['api_data']['shipment_id']);
                } catch (\Exception $e) {
                    $result['track_response'] = $e->getMessage();
                }
            }
        } catch (\Throwable $th) {
            return [
                'error' => 'An error occured while trying to generate S24 International panel: ' . $th->getMessage() . $th->getTraceAsString()
            ];
        } catch (\Exception $th) {
            return [
                'error' => 'An error occured while trying to generate S24 International panel: ' . $th->getMessage()
            ];
        }

        $result['panelHtml'] = $this->load->view('extension/shipping/s24_int_m/order_panel', $data);
        return $result;
    }

    private function cancelShipment()
    {
        $result = [];
        if (!isset($this->request->post['order_id'])) {
            $result['error'] = 'Missing Order ID';
            return $result;
        }
        if (!isset($this->request->post['shipment_id'])) {
            $result['error'] = 'Missing Order ID';
            return $result;
        }

        $shipment_id = $this->request->post['shipment_id'];
        $order_id = (int) $this->request->post['order_id'];

        $token = $this->config->get(Params::PREFIX . 'api_token');
        $test_mode = $this->config->get(Params::PREFIX . 'api_test_mode');

        try {
            Helper::setApiStaticToken($this->config);
            $api = Helper::getApiInstance();
            // $api = new API($token, $test_mode);
            $result = $api->cancelOrder($shipment_id);
        } catch (\Exception $e) {
            // TODO: better handling?
            // return [
            //     'error' => $e->getMessage()
            // ];
        }

        $this->db->query("
            UPDATE " . DB_PREFIX . "s24_int_m_order_api
            SET canceled = 1
            WHERE order_id = " . $order_id . " AND api_shipment_id = '" . $this->db->escape($shipment_id) . "'
        ");

        return [
            'canceled_result' => $result
        ];
    }

    private function registerShipment()
    {
        $result = [];
        if (!isset($this->request->post['order_id'])) {
            $result['error'] = 'Missing Order ID';
            return $result;
        }

        $order_id =  (int) $this->request->post['order_id'];

        $order_offer = Offer::getOrderOffer($order_id, $this->db);

        if (empty($order_offer)) {
            return [
                'error' => 'Failed to load order offer information from database'
            ];
        }

        try {
            $service_code = $order_offer['selected_service'];
            $offer_data = Helper::base64Decode($order_offer['offer_data'], false);
            $terminal_data = null;
            // TODO: better terminal identification?
            if (isset($offer_data['parcel_terminal_type']) && $offer_data['parcel_terminal_type']) {
                $terminal_data = Helper::base64Decode($order_offer['terminal_data'], false);
            }

            $this->load->model('sale/order');
            $order =  $this->model_sale_order->getOrder($order_id);
            $order_products =  $this->model_sale_order->getOrderProducts($order_id);

            $country = new Country($order['shipping_iso_code_2'], $this->db);

            $products_data = ParcelCtrl::getProductsDataByOrder($order_id, $this->db);

            foreach ($order_products as $key => $product) {
                $product = array_merge($product, $products_data[$product['product_id']]);
                $order_products[$key] = $product;
            }

            // HS Code for now using global default
            $parcel_default = ParcelDefault::getGlobalDefault($this->db);
            $order[Params::PREFIX . 'hs_code'] = $parcel_default->hs_code;


            $parcels = ParcelCtrl::makeParcelsFromCart($order_products, $this->db, $this->weight, $this->length, $this->config);
            $items = ParcelCtrl::makeItemsFromProducts($order_products, $country, $this->db);

            $option_id = str_replace('s24_int_m.', '', $order['shipping_code']);
            $option_id = explode('_', $option_id)[1];

            $shipping_option = ShippingOption::getShippingOption($option_id, $this->db, false);

            if (!$shipping_option) {
                throw new \Exception("S24 International shipping option ID $option_id no longer available", 1);
            }

            $api_order = new Order();
            $api_order
                ->setServiceCode($service_code)
                ->setSender(Helper::getSender($this->config, $this->db))
                ->setReceiver(Helper::getReceiver($order, $country, $shipping_option->type, $terminal_data))
                ->setReference($order_id)
                ->setParcels($parcels)
                ->setItems($items);

            Helper::setApiStaticToken($this->config);
            $api = Helper::getApiInstance();
            // $api = new API($token, $test_mode);
            $response = $api->generateOrder($api_order);

            if (!$response || !isset($response->cart_id) || !isset($response->shipment_id)) {
                throw new Exception("Failed to receive response from API", 1);
            }

            $created_at = DateTime::createFromFormat("Y-m-d\TH:i:s.uP", $response->created_at);

            $this->db->query("
                INSERT INTO " . DB_PREFIX . "s24_int_m_order_api
                (order_id, api_cart_id, api_shipment_id, created_at)
                VALUES (" . (int) $order_id . ", '" . $response->cart_id . "', '" . $response->shipment_id . "', '" . $created_at->format('Y-m-d H:i:s') . "')
            ");
        } catch (\Exception $th) {
            return [
                'error' => $th->getMessage()
            ];
        }

        $data['parcels'] = $parcels;
        $data['api_data'] = [
            'shipment_id' => $response->shipment_id,
            'manifest_id' => $response->cart_id
        ];
        $data['shipment_status'] = 'Registered. Generating label';

        return [
            'api_order' => json_decode($api_order->returnJson()),
            'response' => $response
        ];
    }

    private function getLabel()
    {
        $result = [];
        if (!isset($this->request->post['order_id'])) {
            $result['error'] = 'Missing Order ID';
            return $result;
        }

        $order_id =  (int) $this->request->post['order_id'];

        try {
            $sql = $this->db->query("
                SELECT api_cart_id, api_shipment_id FROM " . DB_PREFIX . "s24_int_m_order_api 
                WHERE order_id = " . (int) $order_id . " AND canceled = 0
                ORDER BY order_id DESC 
                LIMIT 1
            ");

            if (!$sql->rows) {
                return [
                    'error' => 'Order hasnt been registered yet'
                ];
            }

            $shipment_id = $sql->row['api_shipment_id'];
            $cart_id = $sql->row['api_cart_id'];

            Helper::setApiStaticToken($this->config);
            $api = Helper::getApiInstance();
            $response = $api->getLabel($shipment_id);
        } catch (\Exception $th) {
            return [
                'error' => $th->getMessage()
            ];
        }

        return [
            'manifest_id' => $cart_id,
            'shipment_id' => $shipment_id,
            'response' => $response
        ];
    }

    /**
     * MANIFEST PAGE
     */
    public function manifest()
    {
        $this->load->language('extension/shipping/s24_int_m');

        $this->document->setTitle($this->language->get(Params::PREFIX . 'manifest_page_title'));

        $extension_home = 'extension';
        if (version_compare(VERSION, '3.0.0', '>=')) {
            $extension_home = 'marketplace';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', $this->getUserToken(), true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get(Params::PREFIX . 'text_extension'),
            'href' => $this->url->link($extension_home . '/extension', $this->getUserToken() . '&type=shipping', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get(Params::PREFIX . 'manifest_page_title'),
            'href' => $this->url->link('extension/shipping/s24_int_m/manifest', $this->getUserToken(), true)
        );

        $data['ajax_url'] = 'index.php?route=extension/shipping/s24_int_m/ajax&' . $this->getUserToken();

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $data['manifests_partial'] = $this->getManifestsPartial();

        $this->response->setOutput($this->load->view('extension/shipping/s24_int_m/manifest', $data));
    }

    private function getManifestsPartial($page = 1)
    {
        $page = (int) $page;
        $page_limit = (int) $this->config->get('config_limit_admin');
        if ($page_limit <= 0) {
            $page_limit = 30;
        }

        $total = ManifestCtrl::getTotal($this->db);
        $total_pages = ceil($total / $page_limit);

        if ($total_pages < 1) {
            $total_pages = 1;
        }

        if ($page < 1) {
            $page = 1;
        }

        if ($page > $total_pages) {
            $page = $total_pages;
        }

        $offset = ($page - 1) * $page_limit;
        $data = [
            'manifests' => ManifestCtrl::list($this->db, $offset, $page_limit)
        ];


        $data['paginator'] = '';
        if ($total_pages > 1) {
            $data['paginator'] = $this->getPagination($page, $total_pages);
        }

        return $this->load->view('extension/shipping/s24_int_m/manifests_partial', $data);
    }

    private function loadManifestPage()
    {
        $page = 1;
        if (isset($this->request->post['page'])) {
            $page = (int) $this->request->post['page'];
        }

        return [
            'html' => $this->getManifestsPartial($page)
        ];
    }

    private function getManifest()
    {
        $result = [];
        if (!isset($this->request->post['order_id']) && !isset($this->request->post['manifest_id'])) {
            $result['error'] = 'Missing Order or Manifest ID';
            return $result;
        }

        $manifest_id = null;

        if (isset($this->request->post['order_id'])) {
            $order_id = (int) $this->request->post['order_id'];

            $sql = $this->db->query("
                SELECT api_cart_id, api_shipment_id FROM " . DB_PREFIX . "s24_int_m_order_api 
                WHERE order_id = " . (int) $order_id . "  AND canceled = 0
                ORDER BY order_id DESC 
                LIMIT 1
            ");

            if (!$sql->rows) {
                return [
                    'error' => 'Order hasnt been registered yet'
                ];
            }

            $shipment_id = $sql->row['api_shipment_id'];
            $manifest_id = $sql->row['api_cart_id'];
        }

        if (isset($this->request->post['manifest_id'])) {
            $manifest_id = strip_tags($this->request->post['manifest_id']);
        }

        if ($manifest_id === null) {
            return [
                'error' => 'Missing manifest ID'
            ];
        }

        try {

            Helper::setApiStaticToken($this->config);
            $api = Helper::getApiInstance();

            $response = $api->generateManifest($manifest_id);
        } catch (\Exception $th) {
            return [
                'error' => $th->getMessage(),
                'config' => [
                    'token' => Helper::$token,
                    'mode' => Helper::$test_mode,
                    'url' => Helper::getApiUrl(Helper::$test_mode)
                ]
            ];
        }

        return [
            'manifest_id' => $manifest_id,
            'shipment_id' => isset($shipment_id) ? $shipment_id : null,
            'response' => $response
        ];
    }

    private function updateSelectedTerminal()
    {
        $terminal_id = isset($this->request->post['terminal_id']) ? $this->request->post['terminal_id'] : null;
        $order_id = isset($this->request->post['order_id']) ? $this->request->post['order_id'] : null;

        if (!$terminal_id || !$order_id) {
            return [
                'error' => 'Terminal ID and/or Order ID missing'
            ];
        }

        $terminal_id = $this->db->escape(strip_tags($terminal_id));
        $order_id = $this->db->escape(strip_tags($order_id));

        $terminal_data = [
            'terminal_id' => $terminal_id,
            'address' => isset($this->request->post['address']) ? strip_tags($this->request->post['address']) : null,
            'city' => isset($this->request->post['city']) ? strip_tags($this->request->post['city']) : null,
            'comment' => isset($this->request->post['comment']) ? strip_tags($this->request->post['comment']) : null,
            'country_code' => isset($this->request->post['country_code']) ? strip_tags($this->request->post['country_code']) : null,
            'identifier' => isset($this->request->post['identifier']) ? strip_tags($this->request->post['identifier']) : null,
            'name' => isset($this->request->post['name']) ? strip_tags($this->request->post['name']) : null,
            'zip' => isset($this->request->post['zip']) ? strip_tags($this->request->post['zip']) : null
        ];

        try {
            $result = $this->db->query("
                UPDATE " . DB_PREFIX . "s24_int_m_order
                SET
                    terminal_id = '" . $terminal_id . "',
                    terminal_data = '" . Helper::base64Encode($terminal_data, true) . "'
                WHERE order_id = '" . (int) $order_id . "'
            ");
            return [
                'result' => $result
            ];
        } catch (\Exception $th) {
            return [
                'error' => $th->getMessage()
            ];
        }
    }
}
