<?php

require_once(DIR_SYSTEM . 'library/s24_int_m/vendor/autoload.php');

use Mijora\S24IntOpencart\Controller\OfferApi;
use Mijora\S24IntOpencart\Controller\ParcelCtrl;
use Mijora\S24IntOpencart\Helper;
use Mijora\S24IntOpencart\Model\Country;
use Mijora\S24IntOpencart\Model\Offer;
use Mijora\S24IntOpencart\Model\Service;
use Mijora\S24IntOpencart\Model\ShippingOption;
use Mijora\S24IntOpencart\Params;
use Mijora\S24IntApiLib\Receiver;

class ModelExtensionShippingS24IntM extends Model
{
    public function getQuote($address)
    {
        // must have EUR currency setup
        if (!$this->currency->has('EUR')) {
            return [];
        }

        $this->load->language('extension/shipping/s24_int_m');

        $setting_prefix = '';
        if (version_compare(VERSION, '3.0.0', '>=')) {
            $setting_prefix = 'shipping_';
        }

        // general geozone
        if ($this->config->get(Params::PREFIX . 'geo_zone_id')) {
            $query = $this->db->query("
                SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone 
                WHERE geo_zone_id = '" . (int) $this->config->get(Params::PREFIX . 'geo_zone_id') . "' 
                    AND country_id = '" . (int) $address['country_id'] . "' 
                    AND (zone_id = '" . (int) $address['zone_id'] . "' OR zone_id = '0')
            ");

            if (!$query->num_rows) {
                return [];
            }
        }

        $receiver_country = $address['iso_code_2'];

        // get shipping options with just receiver country data
        $options = ShippingOption::getShippingOptions($this->db, true, $receiver_country);

        // filter options by enabled, having country, etc
        $options = array_filter($options, function (ShippingOption $option) use ($receiver_country) {
            if (!$option->enabled) {
                return false;
            }

            // make sure option has receiver country
            if (!isset($option->countries[$receiver_country])) {
                return false;
            }

            return true;
        });

        $options = array_values($options);

        Helper::setApiStaticToken($this->config);

        $sender = Helper::getSender($this->config, $this->db);

        $country_receiver = new Country($receiver_country, $this->db);
        $receiver = new Receiver(false);
        $receiver
            ->setCompanyName($address['company'])
            ->setStreetName($address['address_1'] . ', ' . $address['address_2'])
            ->setZipcode($address['postcode'])
            ->setCity($address['city'])
            ->setCountryId($country_receiver->get(Country::ID));

        if (!empty($address['zone_code'])) {
            $receiver->setStateCode($address['zone_code']);
        }

        $products = $this->cart->getProducts();
        $parcels = ParcelCtrl::makeParcelsFromCart($products, $this->db, $this->weight, $this->length, $this->config);
        // echo "<pre>Parcel: " . json_encode($parcels, JSON_PRETTY_PRINT) . "</pre>";

        $use_offer_price_with_tax = (bool) $this->config->get(Params::PREFIX . 'api_use_vat_price');

        // passing 0 as sorting param so its not sorted
        $offers = OfferApi::getOffers($sender, $receiver, $parcels, 0, ($use_offer_price_with_tax ? Offer::PRICE_INCL_VAT : Offer::PRICE_EXCL_VAT));
        $offer = $offers[0] ?? null;

        $method_data = array();

        // if disabled or wrong geo zone etc, return empty array (no options)
        if (empty($offers)) {
            return $method_data;
        }

        // cart subtotal to use with free_shipping setting
        $sub_total = $this->cart->getSubTotal();
        // make sure its in EUR
        $sub_total_eur = $this->currency->convert($sub_total,  $this->session->data['currency'], 'EUR');

        // Add shipping options
        $tax_class_id = $this->config->get(Params::PREFIX . 'tax_class_id');

        $this->session->data['s24_int_m_cart_offers'] = [];
        // echo "<pre>" . json_encode(['SubTotal EUR: ' . $sub_total_eur, $options], JSON_PRETTY_PRINT) . "</pre>";
        foreach ($options as $option) {
            // make sure we have valid type and it has receiver country
            if (!isset(Service::TYPE_API_CODE[$option->type]) || !isset($option->countries[$receiver_country])) {
                continue;
            }

            $option_country = $option->countries[$receiver_country];

            $type = Service::TYPE_API_CODE[$option->type]; // will be used as part of shipping code key
            $allowed_services = array_map('trim', explode(Offer::SEPARATOR_ALLOWED_SERVICES, $option->allowed_services));

            // if set on country use country otherwise use option value
            $priority = $option_country->offer_priority !== null ? $option_country->offer_priority : $option->offer_priority;
            $price_type = $option_country->price_type !== null ? $option_country->price_type : $option->price_type;
            $price = $option_country->price !== null ? $option_country->price : $option->price;
            $free_shipping = $option_country->free_shipping !== null ? $option_country->free_shipping : $option->free_shipping;
            // echo "<pre>" . json_encode($option_country, JSON_PRETTY_PRINT) . "</pre>";
            // price must be set
            if ($price === null) {
                continue;
            }

            // filter allowed services
            $option_offers = array_filter($offers, function ($offer) use ($allowed_services) {
                if (in_array($offer->get(Offer::SERVICE_CODE), $allowed_services)) {
                    return true;
                }

                return false;
            });
            $option_offers = array_values($option_offers);

            // echo "<pre>" . json_encode(['before sort', $priority, $option_offers], JSON_PRETTY_PRINT) . "</pre>";

            // sort options
            Offer::sortByPriority($option_offers, $priority);

            // echo "<pre>" . json_encode(['after sort', $priority, $option_offers], JSON_PRETTY_PRINT) . "</pre>";


            if (empty($option_offers)) {
                continue;
            }

            // we want only first one from list
            $offer = $option_offers[0];
            // echo "<pre>" . json_encode($offer, JSON_PRETTY_PRINT) . "</pre>";

            $cost = (float) $offer->getPrice($price, $price_type, $use_offer_price_with_tax);

            // set 0 cost if free shipping enabled and subtotal is higher
            if ($free_shipping !== null && (float) $free_shipping <= $sub_total_eur) {
                $cost = 0;
            }

            // if offer has invalid price
            if ($cost === null) {
                continue;
            }

            $key = $type . '_' . $option->id;
            $this->session->data['s24_int_m_cart_offers'][$key] = Helper::base64Encode($offer, true);
            $quote_data[$key] = array(
                'code'         => 's24_int_m.' . $key,
                'title'        => $option->title,
                'cost'         => $cost,
                'tax_class_id' => $tax_class_id,
                'text'         => $this->currency->format(
                    $this->tax->calculate(
                        $cost,
                        $tax_class_id,
                        $this->config->get('config_tax')
                    ),
                    $this->session->data['currency']
                )
            );
        }

        // if neither courier nor terminal options available return empty array
        if (empty($quote_data)) {
            return $method_data;
        }

        $method_data = array(
            'code'       => 's24_int_m',
            'title'      => $this->language->get('text_title'),
            'quote'      => $quote_data,
            'sort_order' => $this->config->get($setting_prefix . Params::PREFIX . 'sort_order'),
            'error'      => false
        );

        return $method_data;
    }

    public function saveOrderInformation()
    {
        if (!isset($this->session->data['s24_int_m_cart_offers'])) {
            return;
        }

        $order_id = $this->session->data['order_id'];
        $cart_offers = $this->session->data['s24_int_m_cart_offers'];
        $shipping_method = $this->session->data['shipping_method'];

        $shipping_method = str_replace('s24_int_m.', '', $shipping_method['code']);

        if (empty($cart_offers) || !isset($cart_offers[$shipping_method])) {
            return;
        }

        $terminal_data = [];
        $terminal_id = 'NULL'; // sql ready
        if (isset($this->session->data['s24_int_m_cart_selected_terminal'][$shipping_method])) {
            $terminal_data = $this->session->data['s24_int_m_cart_selected_terminal'][$shipping_method];
            $terminal_id = "'" . $terminal_data['terminal_id'] . "'"; // sql ready
            unset($this->session->data['s24_int_m_cart_selected_terminal']);
        }

        $offer_data_array = Helper::base64Decode($cart_offers[$shipping_method], false);
        $selected_service = $offer_data_array[Offer::SERVICE_CODE];
        $datetime = date('Y-m-d H:i:s');

        $this->db->query("
            INSERT INTO " . DB_PREFIX . "s24_int_m_order
            (order_id, selected_service, offer_data, terminal_id, terminal_data, added_at, updated_at)
            VALUES (
                " . (int) $order_id . ", '" . $this->db->escape($selected_service) . "',
                '" . $cart_offers[$shipping_method] . "', " . $terminal_id . ", '" . Helper::base64Encode($terminal_data, true) . "',
                '" . $datetime . "', '" . $datetime . "'
            )
        ");

        unset($this->session->data['s24_int_m_cart_offers']);
    }

    public function updateOrderInformation($order_id)
    {
        $order_id = (int) $order_id;

        if (!isset($this->session->data['s24_int_m_cart_offers']) || $order_id <= 0) {
            return;
        }

        // check if its an existing s24_int order
        $is_order = (bool) $this->db->query(
            "
            SELECT order_id FROM " . DB_PREFIX . "s24_int_m_order
            WHERE order_id = " . $order_id
        )->rows;

        $cart_offers = $this->session->data['s24_int_m_cart_offers'];
        $shipping_method = $this->session->data['shipping_method'];

        $shipping_method = str_replace('s24_int_m.', '', $shipping_method['code']);

        if (empty($cart_offers) || !isset($cart_offers[$shipping_method])) {
            return;
        }

        $terminal_data = [];
        $terminal_id = 'NULL'; // sql ready

        $offer_data_array = Helper::base64Decode($cart_offers[$shipping_method], false);
        $selected_service = $offer_data_array[Offer::SERVICE_CODE];
        $datetime = date('Y-m-d H:i:s');

        if ($is_order) {
            $this->db->query("
                UPDATE " . DB_PREFIX . "s24_int_m_order
                SET
                    selected_service = '" . $this->db->escape($selected_service) . "', 
                    offer_data = '" . $cart_offers[$shipping_method] . "', 
                    terminal_id = " . $terminal_id . ", 
                    terminal_data = '" . Helper::base64Encode($terminal_data, true) . "',
                    updated_at = '" . $datetime . "'
                WHERE order_id = '" . $order_id . "'
            ");

            // TODO: maybe call through api?
            // mark as canceled all previously registered shipments
            $this->db->query("
                UPDATE " . DB_PREFIX . "s24_int_m_order_api
                SET
                    canceled = '1', 
                WHERE order_id = '" . $order_id . "'
            ");
        } else {
            $this->db->query("
                INSERT INTO " . DB_PREFIX . "s24_int_m_order
                (order_id, selected_service, offer_data, terminal_id, terminal_data, added_at, updated_at)
                VALUES (
                    " . (int) $order_id . ", '" . $this->db->escape($selected_service) . "',
                    '" . $cart_offers[$shipping_method] . "', " . $terminal_id . ", '" . Helper::base64Encode($terminal_data, true) . "',
                    '" . $datetime . "', '" . $datetime . "'
                )
            ");
        }

        unset($this->session->data['s24_int_m_cart_offers']);
    }

    public function getFrontData()
    {
        $this->load->language('extension/shipping/s24_int_m');

        $js_ts_key = Params::PREFIX . 'js_ts';
        $data = [
            $js_ts_key => []
        ];
        $js_keys = [
            'modal_header',
            'terminal_list_header',
            'seach_header',
            'search_btn',
            'modal_open_btn',
            'geolocation_btn',
            'your_position',
            'nothing_found',
            'no_cities_found',
            'geolocation_not_supported',
            'search_placeholder',
            'workhours_header',
            'contacts_header',
            'select_pickup_point',
            'no_pickup_points',
            'select_btn',
            'back_to_list_btn',
            'no_information',
            'no_terminal_selected'
        ];

        foreach ($js_keys as $key) {
            $data[$js_ts_key][$key] = $this->language->get(Params::PREFIX . 'js_' . $key);
        }

        $data['s24_int_m_api_url'] = (bool) $this->config->get(Params::PREFIX . 'api_test_mode') ? Params::API_URL_TEST : Params::API_URL;
        $data['s24_int_m_api_images_url'] = (bool) $this->config->get(Params::PREFIX . 'api_test_mode') ? Params::API_IMAGES_URL_TEST : Params::API_IMAGES_URL;

        return $data;
    }
}
