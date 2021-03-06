<?php
require_once(DIR_SYSTEM . 'lib/common.php');

class ModelPaymentKyash extends Model {
    use KyashModel;

    function __construct($params) {
        parent::__construct($params);
        $this->load->model('checkout/order');
        $this->model_order = $this->model_checkout_order;
        $this->init();
    }

    public function getMethod($address, $total) {
        $this->language->load('payment/kyash');

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->lookup($this->settings, 'kyash_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

        if ($this->lookup($this->settings, 'kyash_total') > 0 && $this->lookup($this->settings, 'kyash_total') > $total) {
            $status = false;
        } elseif (!$this->lookup($this->settings, 'kyash_geo_zone_id')) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        } else {
            $status = false;
        }

        $method_data = array();

        if ($status) {
            $additional = '';
            if ($_REQUEST['route'] === 'checkout/payment_method') {
                $additional = $this->getShopsLink($address['postcode']);
            }

            $method_data = array(
                'code' => 'kyash',
                'title' => ($this->lookup($this->settings, 'kyash_pg_text')?: 'Kyash - Pay at a nearby Shop') . $additional,
                'terms' => '',
                'sort_order' => $this->lookup($this->settings, 'kyash_sort_order')
            );
        }

        return $method_data;
    }

    public function getShopsLink($postcode) {
        $this->language->load('payment/kyash');

        $css = '<link href="catalog/view/theme/default/stylesheet/kyash.css" rel="stylesheet">';

        $html = '
		<script type="text/javascript" src="//secure.kyash.com/outlets.js"></script>
		<div id="see_nearby_shops_container" style="display: none">
		    <kyash:code merchant_id="'.$this->settings["kyash_public_api_id"].'" postal_code="'.$postcode.'"></kyash:code>
		</div>
		<p id="kyash_payment_instructions">Product will be sent to the shipping address only after payment. If order is cancelled or not delivered, you can avail refund as per our policies.</p>';

        $js = '<script src="catalog/view/javascript/kyash.js" type="text/javascript"></script>';

        return $css . $html . $js;
    }

    public function getOrderParams($order_info) {
        $address1 = $order_info['payment_address_1'];
        if ($order_info['payment_address_2']) {
            $address1 .= ',' . $order_info['payment_address_2'];
        }

        $address2 = $order_info['shipping_address_1'];
        if ($order_info['shipping_address_2']) {
            $address2 .= ',' . $order_info['shipping_address_2'];
        }

        $params = array(
            'order_id' => $order_info['order_id'],
            'amount' => $order_info['total'],
            'billing_contact.first_name' => $order_info['payment_firstname'],
            'billing_contact.last_name' => $order_info['payment_lastname'],
            'billing_contact.email' => $order_info['email'],
            'billing_contact.address' => $address1,
            'billing_contact.city' => $order_info['payment_city'],
            'billing_contact.state' => $order_info['payment_zone'],
            'billing_contact.pincode' => $order_info['payment_postcode'],
            'billing_contact.phone' => $order_info['telephone'],
            'shipping_contact.first_name' => $order_info['shipping_firstname'],
            'shipping_contact.last_name' => $order_info['shipping_lastname'],
            'shipping_contact.address' => $address2,
            'shipping_contact.city' => $order_info['shipping_city'],
            'shipping_contact.state' => $order_info['shipping_zone'],
            'shipping_contact.pincode' => $order_info['shipping_postcode'],
            'shipping_contact.phone' => $order_info['telephone']
        );

        return http_build_query($params);
    }

    public function updateKyashCode($order_id, $code, $status, $kyash_expires, $additional) {
        $this->language->load('payment/kyash');
        $method = ($this->lookup($this->settings, 'kyash_pg_text')?: 'Kyash - Pay at a nearby Shop') . $additional;
        $this->db->query("UPDATE `" . DB_PREFIX . "order` SET kyash_code = '" . $code . "', kyash_status = '" . $status ."', kyash_expires = '" . $kyash_expires . "', payment_method = '" . $method . "' WHERE order_id = '" . (int)$order_id . "'");
    }

    public function getSuccessContent($order_id) {
        if ($order_id > 0) {
            list($kyash_code, ,$expires_on) = $this->getOrderInfo($order_id);
            if (empty($kyash_code))
                return '';

            $order_info = $this->model_checkout_order->getOrder($order_id);
            $postcode = $order_info['payment_postcode'];

            $dateTime = new DateTime("@".$expires_on);
            $dateTime->setTimeZone(new DateTimeZone('Asia/Kolkata'));
            $kc_expires_on = $dateTime->format("j M Y, g:i A");
            if (empty($postcode)) {
                $postcode = 'Enter Pincode';
            }

            $url = $this->url->link('payment/kyash/getPaymentPoints2');
            $css = '<link href="catalog/view/theme/default/stylesheet/kyash.css" rel="stylesheet">';
            $html = '
			<script type="text/javascript" src="//secure.kyash.com/outlets.js"></script>
			<div id="see_nearby_shops_container" style="display: none">
			    <kyash:code merchant_id="'.$this->settings["kyash_public_api_id"].'" postal_code="'.$postcode.'" kyash_code="'.$kyash_code.'"></kyash:code>
			</div>';

            return $css . $html;
        }

        return '';
    }
}
?>