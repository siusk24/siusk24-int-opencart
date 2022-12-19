<?php

namespace Mijora\S24IntOpencart\Controller;

class ManifestCtrl
{
    public static function list($db, $offset = 0, $limit = 30)
    {
        $sql = $db->query(
            "
            SELECT api_cart_id, COUNT(DISTINCT api_shipment_id) as total_orders FROM " . DB_PREFIX . "s24_int_m_order_api
            WHERE `canceled` = 0
            GROUP BY api_cart_id
            ORDER BY api_cart_id DESC
            LIMIT " . $offset . ", " . $limit
        );

        if (!$sql->rows) {
            return [];
        }

        $result = [];
        foreach ($sql->rows as $row) {
            $result[] = [
                'manifest_id' => $row['api_cart_id'],
                'total_orders' => $row['total_orders'],
            ];
        }

        return $result;
    }

    public static function getTotal($db)
    {
        $sql = $db->query("
            SELECT COUNT(DISTINCT api_cart_id) as `total` FROM " . DB_PREFIX . "s24_int_m_order_api
            WHERE canceled = 0
        ");

        return isset($sql->row['total']) ?  (int) $sql->row['total'] : 0;
    }
}
