<?php

namespace Mijora\S24IntOpencart;

use Mijora\S24IntOpencart\Controller\OfferApi;
use Mijora\S24IntOpencart\Model\Country;
use Mijora\S24IntOpencart\Model\Service;
use Mijora\S24IntApiLib\API;
use Mijora\S24IntApiLib\Receiver;
use Mijora\S24IntApiLib\Sender;

class Helper
{
    public static $token;
    public static $test_mode = false;

    public static function getApiUrl($test_mode = false)
    {
        return $test_mode ? Params::API_URL_TEST : Params::API_URL;
    }

    public static function getApiInstance()
    {
        $api = new API(self::$token, self::$test_mode);
        $api->setUrl(self::getApiUrl(self::$test_mode) . '/');

        return $api;
    }

    public static function setApiStaticToken($config)
    {
        Helper::$token = $config->get(Params::PREFIX . 'api_token');
        Helper::$test_mode = (bool) $config->get(Params::PREFIX . 'api_test_mode');
        OfferApi::$token = Helper::$token;
        OfferApi::$test_mode = Helper::$test_mode;
    }

    public static function saveSettings($db, $data)
    {
        foreach ($data as $key => $value) {
            $query = $db->query("SELECT setting_id FROM `" . DB_PREFIX . "setting` WHERE `code` = 's24_int_m' AND `key` = '" . $db->escape($key) . "'");
            if ($query->num_rows) {
                $id = $query->row['setting_id'];
                $db->query("UPDATE " . DB_PREFIX . "setting SET `value` = '" . $db->escape($value) . "', serialized = '0' WHERE `setting_id` = '$id'");
            } else {
                $db->query("INSERT INTO `" . DB_PREFIX . "setting` SET store_id = '0', `code` = 's24_int_m', `key` = '$key', `value` = '" . $db->escape($value) . "'");
            }
        }
    }

    public static function getServices($session = null)
    {
        $session_key = Params::PREFIX . 'services';
        try {
            if ($session && isset($session->data[$session_key]) && $session->data[$session_key]['valid_until'] > time()) {
                return Service::fromArray(self::base64Decode($session->data[$session_key]['services']));
            }

            $api = self::getApiInstance();

            $services = $api->listAllServices();
            $services_array = Service::fromArray($services);

            if ($session) {
                $session->data[$session_key] = [
                    'services' => self::base64Encode($services_array),
                    'valid_until' => time() + 300 // keep for 5min
                ];
            }

            return $services_array;
        } catch (\Throwable $th) {
            return $th->getMessage();
        }
    }

    public static function getCountries($all = true)
    {
        try {
            $api = self::getApiInstance();

            $countries = $api->listAllCountries();
            if ($all) {
                return $countries;
            }

            $countries_array = [];

            foreach ($countries as $country) {
                if (!in_array($country->code, ['LV', 'LT', 'EE', 'PL'])) {
                    continue;
                }
                $countries_array[] = $country;
            }
            return $countries_array;
        } catch (\Throwable $th) {
            return [];
        }
    }

    public static function getModificationXmlVersion($file)
    {
        if (!is_file($file)) {
            return null;
        }

        $xml = file_get_contents($file);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadXml($xml);

        $version = $dom->getElementsByTagName('version')->item(0)->nodeValue;

        return $version;
    }

    public static function getModificationSourceFilename()
    {
        return Params::BASE_MOD_XML_SOURCE_DIR . self::getModificationXmlDirByVersion() . Params::BASE_MOD_XML;
    }

    public static function isModificationNewer()
    {
        return version_compare(
            self::getModificationXmlVersion(self::getModificationSourceFilename()),
            self::getModificationXmlVersion(Params::BASE_MOD_XML_SYSTEM),
            '>'
        );
    }

    public static function getModificationXmlDirByVersion()
    {
        if (version_compare(VERSION, '3.0.0', '>=')) {
            return Params::MOD_SOURCE_DIR_OC_3_0;
        }

        if (version_compare(VERSION, '2.3.0', '>=')) {
            return Params::MOD_SOURCE_DIR_OC_2_3;
        }

        // by default return latest version modifications dir
        return Params::MOD_SOURCE_DIR_OC_3_0;
    }

    public static function copyModificationXml()
    {
        self::removeModificationXml();
        return copy(self::getModificationSourceFilename(), Params::BASE_MOD_XML_SYSTEM);
    }

    public static function removeModificationXml()
    {
        if (is_file(Params::BASE_MOD_XML_SYSTEM)) {
            @unlink(Params::BASE_MOD_XML_SYSTEM);
        }
    }

    /**
     * @param mixed $data data to be encoded
     * @param bool $convert_to_json should data first be JSON encoded
     * 
     * @return string
     */
    public static function base64Encode($data, $convert_to_json = true)
    {
        return base64_encode($convert_to_json ? json_encode($data) : $data);
    }

    /**
     * @param string $base64_string BASE64 encoded string
     * @param bool $convert_to_object should decoded string be then json_decoded as StdObject
     * 
     * @return mixed
     */
    public static function base64Decode($base64_string, $convert_to_object = true)
    {
        return json_decode(base64_decode($base64_string), !$convert_to_object);
    }

    public static function hasGitUpdate()
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => Params::GIT_VERSION_CHECK,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_USERAGENT => 'S24_INT_M_VERSION_CHECK_v1.0',
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $version_data = @json_decode($response, true);

        if (empty($version_data)) {
            return false;
        }

        $git_version = isset($version_data['tag_name']) ? $version_data['tag_name'] : null;

        if ($git_version === null) {
            return false;
        }

        $git_version = str_ireplace('v', '', $git_version);

        if (!self::isModuleVersionNewer($git_version)) {
            return false;
        }

        return [
            'version' => $git_version,
            'download_url' => isset($version_data['assets'][0]['browser_download_url'])
                ? $version_data['assets'][0]['browser_download_url']
                : Params::GIT_URL
        ];
    }

    public static function isModuleVersionNewer($git_version)
    {
        return version_compare($git_version, Params::VERSION, '>');
    }

    public static function isTimeToCheckVersion($timestamp)
    {
        return time() > (int) $timestamp + (Params::GIT_CHECK_EVERY_HOURS * 60 * 60);
    }

    public static function getSender($config, $db)
    {
        /**
            'sender_name', 'sender_street', 'sender_postcode',
            'sender_city', 'sender_country', 'sender_phone', 'sender_email'
         */
        $country_sender = new Country($config->get(Params::PREFIX . 'sender_country'), $db);

        $sender = new Sender();
        $sender
            ->setCompanyName($config->get(Params::PREFIX . 'sender_name'))
            ->setContactName($config->get(Params::PREFIX . 'sender_name'))
            ->setStreetName($config->get(Params::PREFIX . 'sender_street'))
            ->setZipcode($config->get(Params::PREFIX . 'sender_postcode'))
            ->setCity($config->get(Params::PREFIX . 'sender_city'))
            ->setPhoneNumber($config->get(Params::PREFIX . 'sender_phone'))
            ->setCountryId($country_sender->get(Country::ID));

        return $sender;
    }

    public static function getReceiver($order_data, $country, $type, $terminal_data = null)
    {
        $receiver = new Receiver(Service::TYPE_API_CODE[$type]);
        $receiver
            ->setShippingType(Service::TYPE_API_CODE[$type])
            ->setContactName($order_data['shipping_firstname'] . ' ' . $order_data['shipping_lastname'])
            ->setCompanyName($order_data['shipping_firstname'] . ' ' . $order_data['shipping_lastname'])
            ->setPhoneNumber($order_data['telephone'])
            ->setCountryId($country->get(Country::ID));

        if ($type === Service::TYPE_TERMINAL && $terminal_data) {
            $receiver
                ->setZipcode($terminal_data['zip'])
                ->setStreetName($terminal_data['address'])
                ->setCity($terminal_data['city']);
        }

        if ($type === Service::TYPE_COURIER) {
            $receiver
                ->setZipcode($order_data['shipping_postcode'])
                ->setCompanyName($order_data['shipping_firstname'] . ' ' . $order_data['shipping_lastname'])
                ->setStreetName($order_data['shipping_address_1'])
                ->setCity($order_data['shipping_city']);

            if (!empty($order_data['shipping_zone_code'])) {
                $receiver->setStateCode($order_data['shipping_zone_code']);
            }
        }

        if (!empty($order_data['s24_int_m_hs_code'])) {
            $receiver->setHsCode($order_data['s24_int_m_hs_code']);
        }

        return $receiver;
    }

    public static function getWeightClassId($db)
    {
        $weight_sql = $db->query("
            SELECT weight_class_id FROM `" . DB_PREFIX . "weight_class_description` WHERE `unit` = 'kg' LIMIT 1
        ");

        if (!$weight_sql->rows) {
            return null;
        }

        return (int) $weight_sql->row['weight_class_id'];
    }

    public static function getLengthClassId($db)
    {
        $weight_sql = $db->query("
            SELECT length_class_id FROM `" . DB_PREFIX . "length_class_description` WHERE `unit` = 'cm' LIMIT 1
        ");

        if (!$weight_sql->rows) {
            return null;
        }

        return (int) $weight_sql->row['length_class_id'];
    }

    /**
     * @param Object $db Opencart DB object
     * 
     * @return array Array with tablenames as keys and queries to run as values
     */
    public static function checkDbTables($db)
    {
        $result = array();

        // OC3 has too small default type for session (terminals takes a lot of space)
        if (version_compare(VERSION, '3.0.0', '>=')) {
            $session_table = $db->query("DESCRIBE `" . DB_PREFIX . "session`")->rows;
            foreach ($session_table as $col) {
                if (strtolower($col['Field']) != 'data') {
                    continue;
                }
                if (strtolower($col['Type']) == 'text') {
                    // needs to be MEDIUMTEXT or LONGTEXT
                    $result['session'] = "
                        ALTER TABLE `" . DB_PREFIX . "session` 
                        MODIFY `data` MEDIUMTEXT;
                    ";
                }
                break;
            }
        }

        return $result;
    }
}
