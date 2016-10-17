<?php
/**
 * NOTICE OF LICENSE
 *
 * @category    Kredivo
 * @package     Kredivo_Kredivopayment
 * @author      Kredivo.
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Kredivo_Kredivopayment_Helper_Data extends Mage_Core_Helper_Abstract
{

    public function _getTitle()
    {
        return Mage::getStoreConfig('payment/kredivopayment/title');
    }

    public function _getServerKey()
    {
        return Mage::getStoreConfig('payment/kredivopayment/server_key');
    }

    public function _getConversionRate()
    {
        return Mage::getStoreConfig('payment/kredivopayment/conversion_rate');
    }

    public function _isProduction()
    {
        return Mage::getStoreConfig('payment/kredivopayment/environment') == 'production';
    }

    public function _getPaymentUri()
    {
        return Mage::getUrl('kredivopayment/payment/redirect', array('_secure' => true));
    }

    public function _getCallbackUri()
    {
        return Mage::getUrl('kredivopayment/payment/response', array('_secure' => true));
    }

    public function _getNotificationUri()
    {
        return Mage::getUrl('kredivopayment/payment/notification', array('_secure' => true));
    }

    public function bacaHTML($url)
    {
        $data = curl_init();
        curl_setopt($data, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($data, CURLOPT_URL, $url);
        $hasil = curl_exec($data);
        curl_close($data);
        return $hasil;
    }
}
