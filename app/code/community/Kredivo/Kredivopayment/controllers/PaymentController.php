<?php
/**
 * NOTICE OF LICENSE
 *
 * @category    Kredivo
 * @package     Kredivo_Kredivopayment
 * @author      Kredivo.
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
require_once Mage::getBaseDir('lib') . '/Kredivo/autoload.php';

class Kredivo_Kredivopayment_PaymentController extends Mage_Core_Controller_Front_Action
{

    /**
     * @return Mage_Checkout_Model_Session
     */
    protected function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * The redirect action is triggered when someone places an order
     */
    public function redirectAction()
    {
        $orderId = $this->_getCheckout()->getLastRealOrderId();
        $order   = Mage::getModel('sales/order')->loadByIncrementId($orderId);

        // send an order email when redirecting to payment page
        $order->setState(Mage::getStoreConfig('payment/kredivopayment/order_status'), true, 'New order, waiting for payment.');
        $order->sendNewOrderEmail();
        $order->setEmailSent(true);

        $items               = $order->getAllItems();
        $discount_amount     = $order->getDiscountAmount();
        $shipping_amount     = $order->getShippingAmount();
        $shipping_tax_amount = $order->getShippingTaxAmount();
        $tax_amount          = $order->getTaxAmount();

        $item_details = array();
        foreach ($items as $each) {
            $item = array(
                'product_name'  => $each->getName(),
                'product_sku'   => $each->getSku(),
                'product_price' => $this->is_string($each->getPrice()),
                'quantity'      => $this->is_string($each->getQtyToInvoice()),
            );

            if ($item['quantity'] == 0) {
                continue;
            }

            $item_details[] = $item;
        }
        unset($each);

        if ($discount_amount != 0) {
            $couponItem = array(
                'product_name'  => 'DISCOUNT',
                'product_sku'   => 'DISCOUNT',
                'product_price' => $discount_amount,
                'quantity'      => 1,
            );
            $item_details[] = $couponItem;
        }

        if ($shipping_amount > 0) {
            $shipping_item = array(
                'product_name'  => 'Shipping Cost',
                'product_sku'   => 'SHIPPING',
                'product_price' => $shipping_amount,
                'quantity'      => 1,
            );
            $item_details[] = $shipping_item;
        }

        if ($shipping_tax_amount > 0) {
            $shipping_tax_item = array(
                'product_name'  => 'Shipping Tax',
                'product_sku'   => 'SHIPPING_TAX',
                'product_price' => $shipping_tax_amount,
                'quantity'      => 1,
            );
            $item_details[] = $shipping_tax_item;
        }

        if ($tax_amount > 0) {
            $tax_item = array(
                'product_name'  => 'Tax',
                'product_sku'   => 'TAX',
                'product_price' => $tax_amount,
                'quantity'      => 1,
            );
            $item_details[] = $tax_item;
        }

        $current_currency = Mage::app()->getStore()->getCurrentCurrencyCode();
        if ($current_currency != 'IDR') {
            $conversion_func = function ($non_idr_price) {
                return $non_idr_price * Mage::helper('kredivopayment')->_getConversionRate();
            };
            foreach ($item_details as &$item) {
                $item['product_price'] = intval(round(call_user_func($conversion_func, $item['product_price'])));
            }
            unset($item);
        } else {
            foreach ($item_details as &$each) {
                $each['product_price'] = (int) $each['product_price'];
            }
            unset($each);
        }

        Kredivo_Config::$is_production = Mage::helper('kredivopayment')->_isProduction();
        Kredivo_Config::$server_key    = Mage::helper('kredivopayment')->_getServerKey();

        $totalPrice = 0;
        foreach ($item_details as $item) {
            $totalPrice += $item['product_price'] * $item['quantity'];
        }

        $order_billing_address           = $order->getBillingAddress();
        $billing_address                 = array();
        $billing_address['address']      = $order_billing_address->getStreet(1);
        $billing_address['city']         = $order_billing_address->getCity();
        $billing_address['postal_code']  = $order_billing_address->getPostcode();
        $billing_address['country_code'] = $this->convert_country_code($order_billing_address->getCountry());
        $billing_address['phone']        = $order_billing_address->getTelephone();

        $order_shipping_address           = $order->getShippingAddress();
        $shipping_address                 = array();
        $shipping_address['address']      = $order_shipping_address->getStreet(1);
        $shipping_address['city']         = $order_shipping_address->getCity();
        $shipping_address['postal_code']  = $order_shipping_address->getPostcode();
        $shipping_address['country_code'] = $this->convert_country_code($order_shipping_address->getCountry());
        $shipping_address['phone']        = $order_shipping_address->getTelephone();

        $payloads = array(
            "server_key"        => Kredivo_Config::$server_key, //optional
            "order_id"          => $orderId,
            "user_email"        => $order_billing_address->getEmail(),
            "user_phone"        => $order_billing_address->getTelephone(),
            "amount"            => $this->is_string($totalPrice),
            "items"             => $item_details,
            "push_uri"          => Mage::helper('kredivopayment')->_getNotificationUri(),
            "back_to_store_uri" => Mage::helper('kredivopayment')->_getCallbackUri(),
            "billing_address"   => implode(", ", $billing_address),
            "shipping_address"  => implode(", ", $shipping_address),
        );

        // echo "<pre>", print_r($payloads);exit();

        try {
            $redirUrl = Kredivo_Api::get_redirection_url($payloads);

            $this->log(array_merge([
                'production' => Kredivo_Config::$is_production,
                'endpoint'   => Kredivo_Config::get_api_endpoint(),
            ], $payloads));

            $this->_redirectUrl($redirUrl);
        } catch (Exception $e) {
            error_log($e->getMessage());
            Mage::log('error:' . print_r($e->getMessage(), true), null, 'kredivo.log', true);
        }
    }

    /**
     * The response action is triggered when your gateway sends back a response
     * after processing the customer's payment
     */
    public function responseAction()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {

            $this->log($_GET);

            if (isset($_GET['order_id']) && isset($_GET['tr_status'])) {
                $trans_status = strtolower($_GET['tr_status']);
                //if capture or pending or settlement, redirect to order received page
                if (in_array($trans_status, ['settlement', 'pending'])) {
                    Mage::getSingleton('checkout/session')->unsQuoteId();
                    Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/success', array('_secure' => false));
                }
                //if deny, redirect to order checkout page again
                elseif ($trans_status == 'deny') {
                    $this->cancelAction();
                    Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/failure', array('_secure' => true));
                }
            }
        } else {
            Mage_Core_Controller_Varien_Action::_redirect('');
        }
    }

    /**
     * Get notification from kredivo
     */
    public function notificationAction()
    {
        // This Additional Code Written By Bayu
        // Code for send data to vendor dashboard
        $site_name = "http://vendor.tinkerlust.com";

        $name = $order->getBillingAddress()->getName();
        $email = $order_billing_address->getEmail();
        $order_no = $orderId;
        $total = $this->is_string($totalPrice);
        $transfer_no = $notification->transaction_id;
        $transfer_bank = "KREDIVO";

        $name = urlencode($name);
        $email = urlencode($email);
        $order_no = urlencode($order_no);
        $total = urlencode($total);
        $transfer_no = urlencode($transfer_no);
        $transfer_bank = urlencode($transfer_bank);

        $url_go = "$site_name/api_paymentconfirm.php?payment_sd=yes&name=$name&email=$email&order_no=$order_no&total=$total&transfer_no=$transfer_no&transfer_bank=$transfer_bank";
        $content = Mage::helper('kredivopayment')->bacaHTML($url_go);
        echo $content;
        // Code for send data to vendor dashboard ends

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            Kredivo_Config::$is_production = Mage::helper('kredivopayment')->_isProduction();
            Kredivo_Config::$server_key    = Mage::helper('kredivopayment')->_getServerKey();

            $notification = new Kredivo_Notification();
            $this->log($notification->get_json());

            $confirmation = Kredivo_Api::confirm_order_status(array(
                'tr_id'         => $notification->transaction_id,
                'signature_key' => $notification->signature_key,
            ));
            $this->log($confirmation);

            if (strtolower($confirmation->status) == 'ok') {

                $order = Mage::getModel('sales/order');
                $order->loadByIncrementId($confirmation->order_id);

                $fraud_status = strtolower($confirmation->fraud_status);
                if ($fraud_status == 'accept') {

                    $transaction_status = strtolower($confirmation->transaction_status);
                    switch ($transaction_status) {
                        case 'settlement':
                            $invoice = $order->prepareInvoice()
                                ->setTransactionId($order->getId())
                                ->addComment('Payment successfully processed by Kredivo.')
                                ->register()
                                ->pay();

                            $transaction_save = Mage::getModel('core/resource_transaction')
                                ->addObject($invoice)
                                ->addObject($invoice->getOrder());

                            $transaction_save->save();

                            $order->setStatus(Mage_Sales_Model_Order::STATE_PROCESSING);
                            $order->sendOrderUpdateEmail(true, 'Thank you, your payment is successfully processed.');
                            break;
                        case 'pending':
                            $order->setStatus(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
                            $order->sendOrderUpdateEmail(true, 'Thank you, your payment is successfully processed.');
                            break;
                        case 'deny':
                            $order->setStatus(Mage_Sales_Model_Order::STATE_CANCELED);
                            break;
                        case 'cancel':
                            $order->setStatus(Mage_Sales_Model_Order::STATE_CANCELED);
                            break;
                    }

                } else {
                    $order->setStatus(Mage_Sales_Model_Order::STATUS_FRAUD);
                }

                $order->save();
            }

            echo Kredivo_Api::response_notification();
        }
        exit();
    }

    /**
     * The cancel action is triggered when an order is to be cancelled
     */
    public function cancelAction()
    {
        if (Mage::getSingleton('checkout/session')->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId(Mage::getSingleton('checkout/session')->getLastRealOrderId());
            if ($order->getId()) {
                // Flag the order as 'cancelled' and save it
                $order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'Kredivo has declined the payment.')->save();
            }
        }
    }

    /**
     * Log errors / messages
     */
    private function log($message)
    {
        if (is_object($message) || is_array($message)) {
            $message = json_encode($message);
        }

        // Add to library logger
        Kredivo_Log::debug($message);

        Mage::log('DEBUG: ' . $message, null, 'kredivo.log', true);
    }

    private function is_string($str)
    {
        try {
            return is_string($str) ? floatval($str) : $str;
        } catch (Exception $e) {}

        return $str;
    }

    /**
     * Convert 2 digits coundry code to 3 digit country code
     */
    public function convert_country_code($country_code)
    {
        // 3 digits country codes
        $cc_three = array(
            'AF' => 'AFG',
            'AX' => 'ALA',
            'AL' => 'ALB',
            'DZ' => 'DZA',
            'AD' => 'AND',
            'AO' => 'AGO',
            'AI' => 'AIA',
            'AQ' => 'ATA',
            'AG' => 'ATG',
            'AR' => 'ARG',
            'AM' => 'ARM',
            'AW' => 'ABW',
            'AU' => 'AUS',
            'AT' => 'AUT',
            'AZ' => 'AZE',
            'BS' => 'BHS',
            'BH' => 'BHR',
            'BD' => 'BGD',
            'BB' => 'BRB',
            'BY' => 'BLR',
            'BE' => 'BEL',
            'PW' => 'PLW',
            'BZ' => 'BLZ',
            'BJ' => 'BEN',
            'BM' => 'BMU',
            'BT' => 'BTN',
            'BO' => 'BOL',
            'BQ' => 'BES',
            'BA' => 'BIH',
            'BW' => 'BWA',
            'BV' => 'BVT',
            'BR' => 'BRA',
            'IO' => 'IOT',
            'VG' => 'VGB',
            'BN' => 'BRN',
            'BG' => 'BGR',
            'BF' => 'BFA',
            'BI' => 'BDI',
            'KH' => 'KHM',
            'CM' => 'CMR',
            'CA' => 'CAN',
            'CV' => 'CPV',
            'KY' => 'CYM',
            'CF' => 'CAF',
            'TD' => 'TCD',
            'CL' => 'CHL',
            'CN' => 'CHN',
            'CX' => 'CXR',
            'CC' => 'CCK',
            'CO' => 'COL',
            'KM' => 'COM',
            'CG' => 'COG',
            'CD' => 'COD',
            'CK' => 'COK',
            'CR' => 'CRI',
            'HR' => 'HRV',
            'CU' => 'CUB',
            'CW' => 'CUW',
            'CY' => 'CYP',
            'CZ' => 'CZE',
            'DK' => 'DNK',
            'DJ' => 'DJI',
            'DM' => 'DMA',
            'DO' => 'DOM',
            'EC' => 'ECU',
            'EG' => 'EGY',
            'SV' => 'SLV',
            'GQ' => 'GNQ',
            'ER' => 'ERI',
            'EE' => 'EST',
            'ET' => 'ETH',
            'FK' => 'FLK',
            'FO' => 'FRO',
            'FJ' => 'FJI',
            'FI' => 'FIN',
            'FR' => 'FRA',
            'GF' => 'GUF',
            'PF' => 'PYF',
            'TF' => 'ATF',
            'GA' => 'GAB',
            'GM' => 'GMB',
            'GE' => 'GEO',
            'DE' => 'DEU',
            'GH' => 'GHA',
            'GI' => 'GIB',
            'GR' => 'GRC',
            'GL' => 'GRL',
            'GD' => 'GRD',
            'GP' => 'GLP',
            'GT' => 'GTM',
            'GG' => 'GGY',
            'GN' => 'GIN',
            'GW' => 'GNB',
            'GY' => 'GUY',
            'HT' => 'HTI',
            'HM' => 'HMD',
            'HN' => 'HND',
            'HK' => 'HKG',
            'HU' => 'HUN',
            'IS' => 'ISL',
            'IN' => 'IND',
            'ID' => 'IDN',
            'IR' => 'RIN',
            'IQ' => 'IRQ',
            'IE' => 'IRL',
            'IM' => 'IMN',
            'IL' => 'ISR',
            'IT' => 'ITA',
            'CI' => 'CIV',
            'JM' => 'JAM',
            'JP' => 'JPN',
            'JE' => 'JEY',
            'JO' => 'JOR',
            'KZ' => 'KAZ',
            'KE' => 'KEN',
            'KI' => 'KIR',
            'KW' => 'KWT',
            'KG' => 'KGZ',
            'LA' => 'LAO',
            'LV' => 'LVA',
            'LB' => 'LBN',
            'LS' => 'LSO',
            'LR' => 'LBR',
            'LY' => 'LBY',
            'LI' => 'LIE',
            'LT' => 'LTU',
            'LU' => 'LUX',
            'MO' => 'MAC',
            'MK' => 'MKD',
            'MG' => 'MDG',
            'MW' => 'MWI',
            'MY' => 'MYS',
            'MV' => 'MDV',
            'ML' => 'MLI',
            'MT' => 'MLT',
            'MH' => 'MHL',
            'MQ' => 'MTQ',
            'MR' => 'MRT',
            'MU' => 'MUS',
            'YT' => 'MYT',
            'MX' => 'MEX',
            'FM' => 'FSM',
            'MD' => 'MDA',
            'MC' => 'MCO',
            'MN' => 'MNG',
            'ME' => 'MNE',
            'MS' => 'MSR',
            'MA' => 'MAR',
            'MZ' => 'MOZ',
            'MM' => 'MMR',
            'NA' => 'NAM',
            'NR' => 'NRU',
            'NP' => 'NPL',
            'NL' => 'NLD',
            'AN' => 'ANT',
            'NC' => 'NCL',
            'NZ' => 'NZL',
            'NI' => 'NIC',
            'NE' => 'NER',
            'NG' => 'NGA',
            'NU' => 'NIU',
            'NF' => 'NFK',
            'KP' => 'MNP',
            'NO' => 'NOR',
            'OM' => 'OMN',
            'PK' => 'PAK',
            'PS' => 'PSE',
            'PA' => 'PAN',
            'PG' => 'PNG',
            'PY' => 'PRY',
            'PE' => 'PER',
            'PH' => 'PHL',
            'PN' => 'PCN',
            'PL' => 'POL',
            'PT' => 'PRT',
            'QA' => 'QAT',
            'RE' => 'REU',
            'RO' => 'SHN',
            'RU' => 'RUS',
            'RW' => 'EWA',
            'BL' => 'BLM',
            'SH' => 'SHN',
            'KN' => 'KNA',
            'LC' => 'LCA',
            'MF' => 'MAF',
            'SX' => 'SXM',
            'PM' => 'SPM',
            'VC' => 'VCT',
            'SM' => 'SMR',
            'ST' => 'STP',
            'SA' => 'SAU',
            'SN' => 'SEN',
            'RS' => 'SRB',
            'SC' => 'SYC',
            'SL' => 'SLE',
            'SG' => 'SGP',
            'SK' => 'SVK',
            'SI' => 'SVN',
            'SB' => 'SLB',
            'SO' => 'SOM',
            'ZA' => 'ZAF',
            'GS' => 'SGS',
            'KR' => 'KOR',
            'SS' => 'SSD',
            'ES' => 'ESP',
            'LK' => 'LKA',
            'SD' => 'SDN',
            'SR' => 'SUR',
            'SJ' => 'SJM',
            'SZ' => 'SWZ',
            'SE' => 'SWE',
            'CH' => 'CHE',
            'SY' => 'SYR',
            'TW' => 'TWN',
            'TJ' => 'TJK',
            'TZ' => 'TZA',
            'TH' => 'THA',
            'TL' => 'TLS',
            'TG' => 'TGO',
            'TK' => 'TKL',
            'TO' => 'TON',
            'TT' => 'TTO',
            'TN' => 'TUN',
            'TR' => 'TUR',
            'TM' => 'TKM',
            'TC' => 'TCA',
            'TV' => 'TUV',
            'UG' => 'UGA',
            'UA' => 'UKR',
            'AE' => 'ARE',
            'GB' => 'GBR',
            'US' => 'USA',
            'UY' => 'URY',
            'UZ' => 'UZB',
            'VU' => 'VUT',
            'VA' => 'VAT',
            'VE' => 'VEN',
            'VN' => 'VNM',
            'WF' => 'WLF',
            'EH' => 'ESH',
            'WS' => 'WSM',
            'YE' => 'YEM',
            'ZM' => 'ZMB',
            'ZW' => 'ZWE',
        );
        // Check if country code exists
        if (isset($cc_three[$country_code]) && $cc_three[$country_code] != '') {
            $country_code = $cc_three[$country_code];
        }
        return $country_code;
    }

}
