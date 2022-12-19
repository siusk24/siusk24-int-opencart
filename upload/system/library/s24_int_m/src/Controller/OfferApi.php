<?php

namespace Mijora\S24IntOpencart\Controller;

use Mijora\S24IntOpencart\Helper;
use Mijora\S24IntOpencart\Model\Offer;
use Mijora\S24IntApiLib\Receiver;
use Mijora\S24IntApiLib\Sender;

class OfferApi
{
    public static $token;
    public static $test_mode = false;

    /**
     * @return Offer[]
     */
    public static function getOffers(
        Sender $sender = null,
        Receiver $receiver = null,
        $parcels = [],
        $sort_by = Offer::OFFER_PRIORITY_CHEAPEST,
        $price_field = Offer::PRICE_EXCL_VAT
    ) {
        try {
            $api = Helper::getApiInstance(); //new API(self::$token, self::$test_mode);

            $offers = $api->getOffers($sender, $receiver, $parcels);
            $offers_array = [];

            foreach ($offers as $offer) {
                if (!isset($offer->{Offer::DELIVERY_TIME}) || empty($offer->{Offer::DELIVERY_TIME})) {
                    continue;
                }

                // make sure price field is set
                if (!isset($offer->{$price_field})) {
                    continue;
                }

                $offers_array[] = new Offer($offer);
            }

            Offer::sortByPriority($offers_array, $sort_by);

            return $offers_array;
        } catch (\Throwable $th) {
        } catch (\Exception $th) {
        }

        return [];
    }
}
