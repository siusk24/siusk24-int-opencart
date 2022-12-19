<?php

namespace Mijora\S24IntOpencart\Model;

use Mijora\S24IntOpencart\Params;

class ShippingOptionCountry implements \JsonSerializable
{
    public $option_id;
    public $country_code;
    public $offer_priority;
    public $price_type;
    public $price;
    public $free_shipping;

    public function __construct()
    {
        //
    }

    public function remove($db)
    {
        return self::deleteShippingOptionCountry($this->option_id, $this->country_code, $db);
    }

    public function save($db)
    {
        $this->remove($db);

        $values = "VALUES ( '" . (int) $this->option_id  . "', '" . $db->escape($this->country_code) . "', ";
        if (empty($this->offer_priority)) {
            $values .= "NULL, ";
        } else {
            $values .= "'" . $this->offer_priority . "', ";
        }
        if (empty($this->price_type)) {
            $values .= "NULL, ";
        } else {
            $values .= "'" . $this->price_type . "', ";
        }
        if (empty($this->price)) {
            $values .= "NULL, ";
        } else {
            $values .= "'" . $this->price . "', ";
        }
        if (empty($this->free_shipping)) {
            $values .= "NULL";
        } else {
            $values .= "'" . $this->free_shipping . "'";
        }
        $values .= ")";

        return $db->query(
            "
            INSERT INTO `" . DB_PREFIX . "s24_int_m_option_country` 
            (`option_id`, `country_code`, `offer_priority`, `price_type`, `price`, `free_shipping`)
            " . $values
        );
    }

    public function getOfferPriorityTranslationString()
    {
        return Offer::getOfferPriorityTranslationString((int) $this->offer_priority);
    }

    public function getOfferPriceTypeTranslationString()
    {
        return Offer::getOfferPriceTranslationString((int) $this->price_type);
    }

    public function jsonSerialize()
    {
        return [
            'option_id' => $this->option_id,
            'country_code' => $this->country_code,
            'offer_priority' => $this->offer_priority,
            'price_type' => $this->price_type,
            'price' => $this->price,
            'free_shipping' => $this->free_shipping,
        ];
    }

    /**
     * @param object $db
     * 
     * @return ShippingOptionCountry[]
     */
    public static function getShippingOptionCountries($option_id, $db)
    {
        $result = $db->query(
            "
            SELECT `option_id`, `country_code`, `offer_priority`, `price_type`, `price`, `free_shipping`
            FROM `" . DB_PREFIX . "s24_int_m_option_country`
            WHERE option_id = " . (int) $option_id
        );

        if (empty($result->rows)) {
            return [];
        }

        $option_list = [];
        foreach ($result->rows as $row) {
            $option = new ShippingOptionCountry();

            $option->option_id = (int) $row['option_id'];
            $option->country_code = $row['country_code'];
            $option->offer_priority = $row['offer_priority'];
            $option->price_type = $row['price_type'];
            $option->price = $row['price'];
            $option->free_shipping = $row['free_shipping'];

            $option_list[$row['country_code']] = $option;
        }

        return $option_list;
    }

    /**
     * @param int $id Shipping option ID
     * @param object $db OpenCart DB object
     * 
     * @return ShippingOption
     */
    public static function getShippingOptionCountry($option_id, $country_code, $db)
    {
        $result = $db->query("
            SELECT `option_id`, `country_code`, `offer_priority`, `price_type`, `price`, `free_shipping`
            FROM `" . DB_PREFIX . "s24_int_m_option_country`
            WHERE `option_id` = " . (int) $option_id . " AND country_code = '" . $db->escape($country_code) . "'
            LIMIT 1
        ");

        if (empty($result->rows)) {
            return null;
        }

        $option = new ShippingOptionCountry();

        $option->option_id = (int) $result->row['option_id'];
        $option->country_code = $result->row['country_code'];
        $option->offer_priority = $result->row['offer_priority'];
        $option->price_type = $result->row['price_type'];
        $option->price = $result->row['price'];
        $option->free_shipping = $result->row['free_shipping'];

        return $option;
    }

    public static function deleteShippingOptionCountry($option_id, $country_code, $db)
    {
        return $db->query("
            DELETE FROM `" . DB_PREFIX . "s24_int_m_option_country` 
            WHERE `option_id` = '" . (int) $option_id . "' AND country_code = '" . $db->escape($country_code) . "'
        ");
    }
}
