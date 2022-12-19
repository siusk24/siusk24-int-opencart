<?php

use Mijora\S24IntOpencart\Helper;
use Mijora\S24IntOpencart\Model\Offer;
use Mijora\S24IntOpencart\Params;

require_once(DIR_SYSTEM . 'library/s24_int_m/vendor/autoload.php');

class ControllerExtensionModuleS24IntM extends Controller
{
    public function ajax()
    {
        if (!isset($this->request->get['action'])) {
            exit();
        }

        switch ($this->request->get['action']) {
            case 'getFrontCurrentData':
                header('Content-Type: application/json');
                echo json_encode([
                    'data' => $this->getFrontCurrentData()
                ]);
                exit();
            case 'selectTerminal':
                header('Content-Type: application/json');
                echo json_encode([
                    'data' => $this->selectTerminal()
                ]);
                exit();
            default:
                exit();
        }
    }

    private function getFrontCurrentData()
    {
        $cart_offers = $this->session->data['s24_int_m_cart_offers'] ?? [];
        $shipping_method = $this->session->data['shipping_method']['code'] ?? null;

        $shipping_method = str_replace('s24_int_m.', '', $shipping_method);

        $address = $this->session->data['shipping_address'] ?? null;

        $cart_offers = array_map(function ($offer) {
            return Helper::base64Decode($offer, false);
        }, $cart_offers);

        return [
            'offers' => $cart_offers,
            'shipping_method' => $shipping_method,
            'address' => $address,
            'selection' => $this->session->data['s24_int_m_cart_selected_terminal'] ?? []
        ];
    }

    private function selectTerminal()
    {
        if (!isset($this->request->post['terminal_id']) || !isset($this->request->post['s24_int_m_option'])) {
            return [
                'error' => 'Could not save selection. Please try again'
            ];
        }

        // make sure its terminal option and also not too long
        if (
            strpos($this->request->post['s24_int_m_option'], 's24_int_m.terminal_') !== 0 ||
            strlen($this->request->post['s24_int_m_option']) > 100
        ) {
            return [
                'error' => 'Invalid shipping option'
            ];
        }

        $terminal_id = $this->db->escape(strip_tags($this->request->post['terminal_id']));
        $option = $this->db->escape(strip_tags($this->request->post['s24_int_m_option']));
        $option = explode('.', $option)[1];

        if (!isset($this->session->data['s24_int_m_cart_selected_terminal'])) {
            $this->session->data['s24_int_m_cart_selected_terminal'] = [];
        }

        $this->session->data['s24_int_m_cart_selected_terminal'][$option] = [
            'terminal_id' => $terminal_id,
            'address' => isset($this->request->post['address']) ? strip_tags($this->request->post['address']) : null,
            'city' => isset($this->request->post['city']) ? strip_tags($this->request->post['city']) : null,
            'comment' => isset($this->request->post['comment']) ? strip_tags($this->request->post['comment']) : null,
            'country_code' => isset($this->request->post['country_code']) ? strip_tags($this->request->post['country_code']) : null,
            'identifier' => isset($this->request->post['identifier']) ? strip_tags($this->request->post['identifier']) : null,
            'name' => isset($this->request->post['name']) ? strip_tags($this->request->post['name']) : null,
            'zip' => isset($this->request->post['zip']) ? strip_tags($this->request->post['zip']) : null
        ];

        return [
            'result' => 'OK'
        ];
    }
}
