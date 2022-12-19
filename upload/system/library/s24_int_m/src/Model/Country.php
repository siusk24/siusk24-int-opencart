<?php

namespace Mijora\S24IntOpencart\Model;

class Country implements \JsonSerializable
{
    const ID = 'id';
    const CODE = 'code';
    const NAME = 'name';
    const NAME_EN = 'en_name';

    private $data;

    public function __construct($country_code = null, $db = null)
    {
        if ($country_code && $db) {
            $this->data = self::getCountryData($country_code, $db);
        }
    }

    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Read one or all country data
     * 
     * @param string|null $key data key to get from loaded data array, null to get full data array
     * 
     * @return mixed value by $key or full data array
     */
    public function get($key = null)
    {
        if (!$key) {
            return $this->data;
        }

        return isset($this->data[$key]) ? $this->data[$key] : null;
    }

    public function jsonSerialize()
    {
        return $this->data;
    }

    /**
     * Loads country data by ISO_CODE_2 format country code
     * 
     * @param string $country_code ISO_CODE_2 country code
     * @param object $db OpenCart DB object
     * 
     * @return array|null country data or null if not found
     */
    public static function getCountryData($country_code, $db)
    {

        $result = $db->query(
            "
            SELECT oimc.`id`, oimc.`code`, oimc.`name`, oimc.`en_name` FROM `" . DB_PREFIX . "s24_int_m_country` oimc
            WHERE oimc.code = '" . $db->escape($country_code) . "' LIMIT 1"
        );

        if (!$result->rows) {
            return null;
        }

        return $result->row;
    }

    /**
     * Returns all countries as Country object
     * 
     * @param object $db OpenCart DB object
     * 
     * @return Country[]
     */
    public static function getAllCountries($db)
    {
        $sql_result = $db->query(
            "
            SELECT oimc.`id`, oimc.`code`, oimc.`name`, oimc.`en_name` FROM `" . DB_PREFIX . "s24_int_m_country` oimc
            "
        );

        if (!$sql_result->rows) {
            return [];
        }

        $result = [];

        foreach ($sql_result->rows as $row) {
            $result[] = (new Country())->setData($row);
        }

        return $result;
    }
}
