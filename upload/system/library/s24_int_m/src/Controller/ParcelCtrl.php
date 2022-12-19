<?php

namespace Mijora\S24IntOpencart\Controller;

use Mijora\S24IntOpencart\Helper;
use Mijora\S24IntOpencart\Model\Country;
use Mijora\S24IntOpencart\Model\ParcelDefault;
use Mijora\S24IntApiLib\Item;
use Mijora\S24IntApiLib\Parcel;
use Mijora\S24IntOpencart\Params;

class ParcelCtrl
{
    const PARCEL_DIMENSIONS = [
        'weight',
        'width',
        'length',
        'height'
    ];

    public static function makeParcelsFromCart($cart_products, $db, $weight_class = null, $length_class = null, $config = null)
    {
        $product_categories = self::getProductCategoriesParcelDefaults($cart_products, $db);

        $defaults = ParcelDefault::getGlobalDefault($db);

        $kg_weight_class_id = Helper::getWeightClassId($db);
        $cm_length_class_id = Helper::getLengthClassId($db);

        // must have kg setup
        if (!$kg_weight_class_id) {
            return [];
        }

        $consolidate = false;
        if ($config) {
            $consolidate = (bool) ($config->get(Params::PREFIX . 'api_consolidate') ?? false);
        }

        $total_weight = 0.0;
        $total_volume = 0.0;

        $parcels = [];
        foreach ($cart_products as $product) {
            if ((int) $product['shipping'] === 0) {
                continue;
            }

            $product_id = $product['product_id'];
            $weight = (float) $product['weight'];
            $width = (float) $product['width'];
            $length = (float) $product['length'];
            $height = (float) $product['height'];

            // make sure weight and legth are in correct units
            if ($weight_class) {
                $weight = (float) $weight_class->convert($weight, $product['weight_class_id'], $kg_weight_class_id);
            }

            if ($length_class) {
                $width = (float) $length_class->convert($width, $product['length_class_id'], $cm_length_class_id);
                $length = (float) $length_class->convert($length, $product['length_class_id'], $cm_length_class_id);
                $height = (float) $length_class->convert($height, $product['length_class_id'], $cm_length_class_id);
            }

            foreach (self::PARCEL_DIMENSIONS as $dimmension) {
                if ($$dimmension > 0) {
                    continue;
                }

                $$dimmension = 0;
                if (isset($product_categories[$product_id])) {
                    foreach ($product_categories[$product_id] as $category_id => $parcel_default) {
                        if ($$dimmension < $parcel_default->$dimmension) {
                            $$dimmension = $parcel_default->$dimmension;
                        }
                    }
                }

                $$dimmension = $$dimmension === 0 ? $defaults->$dimmension : $$dimmension;
            }

            $total_weight += $weight;
            $total_volume += ($width * $height * $length);

            if (!$consolidate) {
                $parcel = (new Parcel())
                    ->setAmount((int) $product['quantity'])
                    ->setUnitWeight($weight)
                    ->setWidth(ceil($width))
                    ->setLength(ceil($length))
                    ->setHeight(ceil($height));

                $parcels[] = $parcel->generateParcel();
                // TODO: check if api requires it duplicated
                // for ($i = 0; $i < (int) $product['quantity']; $i++) {
                //     $parcels[] = $parcel->generateParcel();
                // }
            }
        }

        if ($consolidate && $total_volume > 0) {
            $consolidated_part = pow($total_volume, 1 / 3);
            $parcel = (new Parcel())
                ->setAmount(1)
                ->setUnitWeight($total_weight)
                ->setWidth(ceil($consolidated_part))
                ->setLength(ceil($consolidated_part))
                ->setHeight(ceil($consolidated_part));

            $parcels = [
                $parcel->generateParcel()
            ];
        }

        return $parcels;
    }

    /**
     *
     * @param array $cart_products
     * @param object $db
     * 
     * @return array
     * 
     */
    public static function getProductCategoriesParcelDefaults($cart_products, $db)
    {
        $product_ids = array_map(function ($product) {
            return (int) $product['product_id'];
        }, $cart_products);

        $result = $db->query('
            SELECT product_id, category_id FROM ' . DB_PREFIX . 'product_to_category 
            WHERE product_id IN (' . implode(', ', $product_ids) . ')
        ');

        $product_categories = [];
        $categories = [];
        if ($result->rows) {
            foreach ($result->rows as $row) {
                $product_id = $row['product_id'];
                $category_id = $row['category_id'];
                if (!isset($product_categories[$product_id])) {
                    $product_categories[$product_id] = [];
                }

                if (!isset($categories[$category_id])) {
                    $categories[$category_id] = $category_id;
                }

                $product_categories[$product_id][] = $row['category_id'];
            }
        }

        foreach ($product_categories as $product_id => $category_ids) {
            $product_categories[$product_id] = ParcelDefault::getMultipleParcelDefault($category_ids, $db);
        }

        return $product_categories;
    }

    public static function getProductsDataByOrder($order_id, $db)
    {
        $product_ids_sql = $db->query(
            '
            SELECT product_id, quantity FROM ' . DB_PREFIX . 'order_product 
            WHERE order_id = ' . (int) $order_id
        );

        if (!$product_ids_sql->rows) {
            return [];
        }

        // product info from order
        $products = [];
        foreach ($product_ids_sql->rows as $row) {
            $product_id = (int) $row['product_id'];
            $products[$product_id] = [
                'product_id' => $product_id,
                'quantity' => $row['quantity']
            ];
        }

        $product_ids = array_keys($products);

        // add in product dimmensions information
        $product_table_cols = ['product_id', 'shipping', 'width', 'height', 'length', 'weight', 'weight_class_id', 'length_class_id'];
        $products_sql = $db->query('
            SELECT ' . implode(', ', $product_table_cols) . ' FROM ' . DB_PREFIX . 'product 
            WHERE product_id IN (' . implode(', ', $product_ids) . ')
        ');

        foreach ($products_sql->rows as $row) {
            $product_id = (int) $row['product_id'];

            if (!isset($products[$product_id])) {
                continue;
            }

            foreach ($product_table_cols as $col) {
                $products[$product_id][$col] = $row[$col];
            }
        }

        return $products;
    }

    public static function makeItemsFromProducts($products, $country, $db)
    {
        $items = [];
        $global_default = ParcelDefault::getGlobalDefault($db);

        $product_categories_defaults = self::getProductCategoriesParcelDefaults($products, $db);

        foreach ($products as $product) {
            $hs_code = $global_default->hs_code ?? null;

            // find custom hs_code - first non empty value is used
            if (isset($product_categories_defaults[$product['product_id']])) {
                foreach ($product_categories_defaults[$product['product_id']] as $category_id => $parcel_default) {
                    if ($parcel_default->hs_code) {
                        $hs_code = $parcel_default->hs_code;
                        break;
                    }
                }
            }

            $item = new Item();
            $item
                ->setDescription($product['name'])
                ->setItemPrice((float) $product['price'])
                ->setItemAmount((int) $product['quantity'])
                ->setHsCode($hs_code ? $hs_code : '')
                ->setCountryId($country->get(Country::ID));

            $items[] = $item->generateItem();
        }

        return $items;
    }
}
