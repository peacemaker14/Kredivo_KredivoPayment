<?php
/**
 * NOTICE OF LICENSE
 *
 * @category    Kredivo
 * @package     Kredivo_Kredivopayment
 * @author      Kredivo.
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Kredivo_Kredivopayment_Model_Standard extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'kredivopayment';

    protected $_isInitializeNeeded     = true;
    protected $_canUseInternal         = true;
    protected $_canUseForMultishipping = false;

    // call to redirectAction function at Kredivo_Kredivopayment_PaymentController
    public function getOrderPlaceRedirectUrl()
    {
        return Mage::helper('kredivopayment')->_getPaymentUri();
    }
}
