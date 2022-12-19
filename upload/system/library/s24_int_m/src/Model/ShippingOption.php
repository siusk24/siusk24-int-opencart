<?php

namespace Mijora\S24IntOpencart\Model;

use Mijora\S24IntOpencart\Params;

class ShippingOption implements \JsonSerializable
{
    public $id;
    public $title;
    public $enabled = 0;
    public $type = Service::TYPE_COURIER;
    public $allowed_services = '';
    public $offer_priority = Offer::OFFER_PRIORITY_CHEAPEST;
    public $sort_order = 0;
    public $price_type = Offer::OFFER_PRICE_FIXED;
    public $price;
    public $free_shipping;
    /** @var ShippingOptionCountry[] */
    public $countries = [];

    public function __construct()
    {
        //
    }

    public function getTypeTranslationString()
    {
        return Service::getTypeTranslationString((int) $this->type);
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
            'id' => $this->id,
            'title' => $this->title,
            'enabled' => $this->enabled,
            'type' => $this->type,
            'allowed_services' => $this->allowed_services,
            'offer_priority' => $this->offer_priority,
            'sort_order' => $this->sort_order,
            'price_type' => $this->price_type,
            'price' => $this->price,
            'free_shipping' => $this->free_shipping,
            'countries' => $this->countries
        ];
    }

    public function update($db)
    {
        if (!$this->id) {
            return $this->save($db);
        }

        return $db->query(
            "
            UPDATE `" . DB_PREFIX . "s24_int_m_option`
            SET 
                `enabled` = '" . (int) $this->enabled . "',  
                `allowed_services` = '" . $db->escape($this->allowed_services) . "', 
                `offer_priority` = '" . (int) $this->offer_priority . "', 
                `sort_order` = '" . (int) $this->sort_order . "', 
                `title` = '" . $db->escape($this->title) . "', 
                `price_type` = '" . (int) $this->price_type . "', 
                `price` = " . ($this->price === null ? "NULL" : "'" . (float) $this->price . "'") . ", 
                `free_shipping` = " . ($this->free_shipping === null ? "NULL" : "'" . (float) $this->free_shipping . "'") . "
            WHERE id = " . (int) $this->id
        );
    }

    public function save($db)
    {
        if ($this->id) {
            return $this->update($db);
        }

        return $db->query("
            INSERT INTO `" . DB_PREFIX . "s24_int_m_option`
            (`enabled`, `type`, `allowed_services`, `offer_priority`, `sort_order`, `title`, `price_type`, `price`, `free_shipping`)
            VALUES (
                '" . (int) $this->enabled . "', '" . (int) $this->type . "', '" . $db->escape($this->allowed_services) . "',
                '" . (int) $this->offer_priority . "', '" . (int) $this->sort_order . "', '" . $db->escape($this->title) . "',
                '" . (int) $this->price_type . "',
                " . ($this->price === null ? "NULL" : "'" . (float) $this->price . "'") . ",
                " . ($this->free_shipping === null ? "NULL" : "'" . (float) $this->free_shipping . "'") . "
            )
        ");
    }

    public function delete($db)
    {
        if ((int) $this->id < 1) {
            return;
        }

        self::deleteById($this->id, $db);
    }

    /**
     * @param object $db
     * @param bool $with_countries
     * @param string|null $country_code
     * 
     * @return ShippingOption[]
     */
    public static function getShippingOptions($db, $with_countries = false, $country_code = null)
    {
        $result = $db->query("
            SELECT `id`, `enabled`, `type`, `allowed_services`, `offer_priority`, `sort_order`, `title`, `price_type`, `price`, `free_shipping`
            FROM `" . DB_PREFIX . "s24_int_m_option`
            ORDER BY `sort_order`, `id` ASC
        ");

        if (empty($result->rows)) {
            return [];
        }

        $option_list = [];
        foreach ($result->rows as $row) {
            $option = new ShippingOption();

            $option->id = (int) $row['id'];
            $option->title = $row['title'];
            $option->enabled = (int) $row['enabled'];
            $option->type = (int) $row['type'];
            $option->allowed_services = $row['allowed_services'];
            $option->offer_priority = (int) $row['offer_priority'];
            $option->sort_order = (int) $row['sort_order'];
            $option->price_type = (int) $row['price_type'];
            $option->price = $row['price']; //empty($row['price']) && $row['price'] !== '0' ? null : (float) $row['price'];
            $option->free_shipping = $row['free_shipping'];

            if ($with_countries && $country_code === null) {
                $option->countries = ShippingOptionCountry::getShippingOptionCountries((int) $row['id'], $db);
            }

            if ($with_countries && $country_code !== null) {
                $option->countries[$country_code] = ShippingOptionCountry::getShippingOptionCountry((int) $row['id'], $country_code, $db);
            }

            $option_list[] = $option;
        }

        return $option_list;
    }

    /**
     * @param int $id Shipping option ID
     * @param object $db OpenCart DB object
     * 
     * @return ShippingOption
     */
    public static function getShippingOption($id, $db, $with_countries = true)
    {
        $result = $db->query("
            SELECT `id`, `enabled`, `type`, `allowed_services`, `offer_priority`, `sort_order`, `title`, `price_type`, `price`, `free_shipping`
            FROM `" . DB_PREFIX . "s24_int_m_option`
            WHERE `id` = " . (int) $id . " LIMIT 1
        ");

        if (empty($result->rows)) {
            return null;
        }

        $option = new ShippingOption();

        $option->id = (int) $result->row['id'];
        $option->title = $result->row['title'];
        $option->enabled = (int) $result->row['enabled'];
        $option->type = (int) $result->row['type'];
        $option->allowed_services = $result->row['allowed_services'];
        $option->offer_priority = (int) $result->row['offer_priority'];
        $option->sort_order = (int) $result->row['sort_order'];
        $option->price_type = (float) $result->row['price_type'];
        $option->price = $result->row['price']; //empty($result->row['price']) ? null : (float) $result->row['price'];
        $option->free_shipping = $result->row['free_shipping'];

        if ($with_countries) {
            $option->countries = ShippingOptionCountry::getShippingOptionCountries((int) $result->row['id'], $db);
        }

        return $option;
    }

    public static function deleteById($option_id, $db)
    {
        $db->query(
            "
            DELETE FROM `" . DB_PREFIX . "s24_int_m_option` WHERE `id` = " . (int) $option_id
        );

        $db->query(
            "
            DELETE FROM `" . DB_PREFIX . "s24_int_m_option_country` WHERE `option_id` = " . (int) $option_id
        );
    }
}
