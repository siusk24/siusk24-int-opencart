<?php

/**
{
        "service_code": "S66",
        "name": "FEDEX PRIORITY",
        "additional": "Nuo durų iki durų",
        "delivery_time": "2-4d.d.",
        "price": "81.78",
        "remote_area_charge": 0,
        "total_price_excl_vat": "81.78",
        "pickup_from_address": true,
        "delivery_to_address": true,
        "parcel_terminal_type": null,
        "additional_services": {
            "cod": false,
            "insurance": false,
            "carry_service": false,
            "doc_return": false,
            "own_login": false,
            "fragile": false
        }
    }
 */

namespace Mijora\S24IntOpencart\Model;

use Mijora\S24IntOpencart\Params;
use Mijora\S24IntApiLib\API;
use Mijora\S24IntApiLib\Receiver;

class Offer implements \JsonSerializable
{
    const NAME = 'name';
    const SERVICE_CODE = 'service_code';
    const ADDITIONAL = 'additional';
    const DELIVERY_TIME = 'delivery_time';
    const PRICE = 'price';
    const PRICE_EXCL_VAT = 'total_price_excl_vat';
    const PRICE_INCL_VAT = 'total_price_with_vat';
    const REMOTE_AREA_CHARGE = 'remote_area_charge';
    const PICKUP_FROM_ADDRESS = 'pickup_from_address';
    const DELIVERY_TO_ADDRESS = 'delivery_to_address';
    const PARCEL_TERMINAL_TYPE = 'parcel_terminal_type';
    const ADDITIONAL_SERVICES = 'additional_services';

    const SEPARATOR_ALLOWED_SERVICES = ',';

    const OFFER_PRIORITY_CHEAPEST = 1;
    const OFFER_PRIORITY_FASTEST = 2;

    const OFFER_PRIORITY_AVAILABLE = [
        self::OFFER_PRIORITY_CHEAPEST,
        self::OFFER_PRIORITY_FASTEST
    ];

    const OFFER_PRICE_FIXED = 1;
    const OFFER_PRICE_SURCHARGE_PERC = 2;
    const OFFER_PRICE_SURCHARGE_FIXED = 3;

    const OFFER_PRICE_AVAILABLE = [
        self::OFFER_PRICE_FIXED,
        self::OFFER_PRICE_SURCHARGE_PERC,
        self::OFFER_PRICE_SURCHARGE_FIXED
    ];

    const OFFER_PRICE_ADDONS = [
        self::OFFER_PRICE_FIXED => '€',
        self::OFFER_PRICE_SURCHARGE_PERC => '%',
        self::OFFER_PRICE_SURCHARGE_FIXED => '€',
    ];

    private $data = null;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function get($key)
    {
        $params = explode('.', $key);
        $obj = $this->data;
        foreach ($params as $param) {
            $obj = $obj->{$param} ?? null;
            if ($obj === null) {
                return null;
            }
        }
        return $obj;
    }

    public function getPrice($add_to_price = 0.0, $addition_type = null, $use_vat_price = false)
    {
        if ($use_vat_price && !isset($this->data->{self::PRICE_INCL_VAT})) {
            return null;
        }

        if (!$use_vat_price && !isset($this->data->{self::PRICE_EXCL_VAT})) {
            return null;
        }

        $add_to_price = (float) $add_to_price;
        $offer_price = (float) ($use_vat_price ? $this->data->{self::PRICE_INCL_VAT} : $this->data->{self::PRICE_EXCL_VAT});
        $addition_type = (int) $addition_type;

        // nothing to add return as is
        if (!in_array($addition_type, self::OFFER_PRICE_AVAILABLE) || $add_to_price < 0.0) {
            return $offer_price;
        }

        // price is fixed so add_to_price is used as new price
        if ($addition_type === self::OFFER_PRICE_FIXED) {
            return $add_to_price;
        }

        if ($addition_type === self::OFFER_PRICE_SURCHARGE_FIXED) {
            return $offer_price + $add_to_price;
        }

        if ($addition_type === self::OFFER_PRICE_SURCHARGE_PERC) {
            $add = $offer_price * ($add_to_price / 100);
            return $offer_price + $add;
        }

        return $offer_price;
    }

    public function getAdditionalServices()
    {
        return get_object_vars($this->data->{self::ADDITIONAL_SERVICES});
    }

    public function jsonSerialize()
    {
        return $this->data;
    }

    public static function getOfferPriorityTranslationString($offer_priority)
    {
        if (!in_array($offer_priority, self::OFFER_PRIORITY_AVAILABLE)) {
            return null;
        }

        return Params::PREFIX . 'offer_priority_' . (int) $offer_priority;
    }

    public static function getOfferPriceTranslationString($offer_price)
    {
        if (!in_array($offer_price, self::OFFER_PRICE_AVAILABLE)) {
            return null;
        }

        return Params::PREFIX . 'offer_price_' . (int) $offer_price;
    }

    public static function sortByPriority(&$offers_array, $sort_by = Offer::OFFER_PRIORITY_CHEAPEST)
    {
        $sort_by = (int) $sort_by;

        // do nothing if priority key is invalid
        if (!in_array($sort_by, self::OFFER_PRIORITY_AVAILABLE)) {
            return;
        }

        usort($offers_array, function ($offer_a, $offer_b) use ($sort_by) {
            $price = (float) $offer_a->get(Offer::PRICE_EXCL_VAT) - (float) $offer_b->get(Offer::PRICE_EXCL_VAT);

            preg_match('/[0-9]+-[0-9]+/', $offer_a->get(Offer::DELIVERY_TIME), $offer_a_delivery);
            preg_match('/[0-9]+-[0-9]+/', $offer_b->get(Offer::DELIVERY_TIME), $offer_b_delivery);

            $delivery_time = strcmp($offer_a_delivery[0], $offer_b_delivery[0]);

            // sort by cheapest first
            if ($sort_by === Offer::OFFER_PRIORITY_CHEAPEST) {
                if ($price !== 0.0) {
                    return $price;
                }

                return $delivery_time;
            }

            // sort by fastest first
            return $delivery_time === 0 ? $price : $delivery_time;
        });
    }

    public static function getOrderOffer($order_id, $db)
    {
        $sql = $db->query("
            SELECT * FROM " . DB_PREFIX . "s24_int_m_order
            WHERE order_id = " . $order_id . "
            LIMIT 1
        ");

        if (!$sql->rows) {
            return [];
        }

        return $sql->row;
    }
}
