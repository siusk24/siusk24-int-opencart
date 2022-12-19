<?php

namespace Mijora\S24IntApiLib;

use Mijora\S24IntApiLib\Person;

use Mijora\S24IntApiLib\Exception\S24ApiException;

class Receiver extends Person
{

    public function __construct($shipping_type)
    {
        parent::__construct($shipping_type);
    }

    public function generateReceiver()
    {
        // iskelti i Person klase kaip atskira metoda, pvz: validatePerson
        if (!$this->shipping_type) throw new S24ApiException('All the fields must be filled. shipping_type is missing.');
        if (!$this->contact_name) throw new S24ApiException('All the fields must be filled. contact_name is missing.');
        if (!$this->street_name && $this->shipping_type === self::SHIPPING_COURIER) throw new S24ApiException('All the fields must be filled. street_name is missing.');
        if (!$this->zipcode) throw new S24ApiException('All the fields must be filled. zipcode is missing.');
        if (!$this->city && $this->shipping_type === self::SHIPPING_COURIER) throw new S24ApiException('All the fields must be filled. city is missing.');
        if (!$this->phone_number) throw new S24ApiException('All the fields must be filled. phone_number is missing.');
        if (!$this->country_id) throw new S24ApiException('All the fields must be filled. country_id is missing.');


        // vietoje $this->shipping_type naudoti GetShippingType is Person klases
        $receiver = array(
            'shipping_type' => $this->shipping_type,
            'company_name' => $this->company_name,
            'contact_name' => $this->contact_name,
            $this->shipping_type === 'courier' ?
                'zipcode' :
                'terminal_zipcode' => $this->zipcode,
            'street' => $this->street_name,
            'city' => $this->city,
            'phone' => $this->phone_number,
            'country_id' => $this->country_id,
            'state_code' => $this->state_code,
            'eori' => $this->eori,
            'hs_code' => $this->hs_code
        );

        if ($this->shipping_type === self::SHIPPING_COURIER)
            $receiver += ['zipcode' => $this->zipcode];
        if ($this->shipping_type === self::SHIPPING_TERMINAL)
            $receiver += ['parcel_terminal_zipcode' => $this->zipcode];

        return $receiver;
    }


    public function generateReceiverOffers()
    {
        if (!$this->zipcode) throw new S24ApiException('All the fields must be filled. zipcode is missing.');
        if (!$this->country_id) throw new S24ApiException('All the fields must be filled. country_id is missing.');


        return array(
            'zipcode' => $this->zipcode,
            'country_id' => $this->country_id
        );
    }


    public function returnJson()
    {
        return json_encode($this->generateReceiver());
    }

    public function __toArray()
    {
        return $this->generateReceiver();
    }
}
