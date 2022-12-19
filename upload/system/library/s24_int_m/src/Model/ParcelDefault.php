<?php

namespace Mijora\S24IntOpencart\Model;

class ParcelDefault implements \JsonSerializable
{
    public static $db; // for opencart db object

    public $category_id = 0;
    public $weight = 1.0;
    public $length = 1.0;
    public $width = 1.0;
    public $height = 1.0;
    public $hs_code;

    public function __construct()
    {
        //
    }

    public function jsonSerialize()
    {
        return $this->getAllValues();
    }

    public function getAllValues()
    {
        return [
            'category_id' => $this->category_id,
            'weight' => $this->weight,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'hs_code' => $this->hs_code,
        ];
    }

    /**
     * @param object $db
     * 
     * @return ParcelDefault
     */
    public static function getGlobalDefault($db)
    {
        $parcel_default = new ParcelDefault();

        $result = $db->query("
            SELECT `category_id`, `weight`, `length`, `width`, `height`, `hs_code`
            FROM `" . DB_PREFIX . "s24_int_m_parcel_default`
            WHERE `category_id` = 0 LIMIT 1
        ");

        if (empty($result->rows)) {
            return $parcel_default;
        }

        $parcel_default->category_id = (int) $result->row['category_id'];
        $parcel_default->weight = (float) $result->row['weight'];
        $parcel_default->length = (float) $result->row['length'];
        $parcel_default->width = (float) $result->row['width'];
        $parcel_default->height = (float) $result->row['height'];
        $parcel_default->hs_code = $result->row['hs_code'];

        return $parcel_default;
    }

    /**
     * @param mixed $category_ids
     * @param mixed $db
     * 
     * @return ParcelDefault[]
     */
    public static function getMultipleParcelDefault($category_ids, $db)
    {
        $sql_result = $db->query("
            SELECT `category_id`, `weight`, `length`, `width`, `height`, `hs_code`
            FROM `" . DB_PREFIX . "s24_int_m_parcel_default`
            WHERE `category_id` IN ('" . implode("', '", $category_ids) . "')
        ");

        if (empty($sql_result->rows)) {
            return [];
        }

        $result = [];
        foreach ($sql_result->rows as $row) {
            $parcel_default = new ParcelDefault();
            $parcel_default->category_id = (int) $row['category_id'];
            $parcel_default->weight = (float) $row['weight'];
            $parcel_default->length = (float) $row['length'];
            $parcel_default->width = (float) $row['width'];
            $parcel_default->height = (float) $row['height'];
            $parcel_default->hs_code = $row['hs_code'];

            $result[(int) $row['category_id']] = $parcel_default;
        }

        return $result;
    }

    public function fieldValidation()
    {
        $result = [
            'category_id' => true,
            'weight' => true,
            'length' => true,
            'width' => true,
            'height' => true,
        ];

        if (empty($this->weight) || (float) $this->weight <= 0) {
            $result['weight'] = false;
        }
        if (empty($this->length) || (float) $this->length <= 0) {
            $result['length'] = false;
        }
        if (empty($this->width) || (float) $this->width <= 0) {
            $result['width'] = false;
        }
        if (empty($this->height) || (float) $this->height <= 0) {
            $result['height'] = false;
        }

        return $result;
    }

    public static function remove($category_id, $db)
    {
        return $db->query("
            DELETE FROM `" . DB_PREFIX . "s24_int_m_parcel_default` WHERE `category_id` = '" . (int) $category_id . "'
        ");
    }

    public function save()
    {
        self::remove($this->category_id, self::$db);

        return self::$db->query("
            INSERT INTO `" . DB_PREFIX . "s24_int_m_parcel_default` 
            (`category_id`, `weight`, `length`, `width`, `height`, `hs_code`)
            VALUES ('" . (int) $this->category_id . "', '" . (float) $this->weight . "', '" . (float) $this->length . "',
             '" . (float) $this->width . "', '" . (float) $this->height . "', '" . self::$db->escape($this->hs_code) . "')
        ");
    }
}
