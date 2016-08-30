<?php
/**
 * NOTICE OF LICENSE
 *
 * @category    Kredivo
 * @package     Kredivo_Kredivopayment
 * @author      Kredivo.
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Kredivo_Kredivopayment_Model_Observer
{
    public function disableMethod(Varien_Event_Observer $observer)
    {
        $moduleName = "Kredivo_Kredivopayment";
        if ('kredivopayment' == $observer->getMethodInstance()->getCode()) {
            if (!Mage::getStoreConfigFlag('advanced/modules_disable_output/' . $moduleName)) {
                //nothing here, as module is ENABLE
            } else {
                $observer->getResult()->isAvailable = false;
            }

        }
    }
}
