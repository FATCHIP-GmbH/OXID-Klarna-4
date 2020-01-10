<?php


class KlarnaOxShopControl extends KlarnaOxShopControl_parent
{
    protected function _initializeViewObject($sClass, $sFunction, $aParams = null, $aViewsChain = null)
    {
        // detect paypal button clicks
        $searchTerm = 'paypalExpressCheckoutButton';
        $found = array_filter(array_keys($_REQUEST), function ($paramName) use($searchTerm) {
            return strpos($paramName, $searchTerm) !== false;
        });
        // remove KCO id from session
        if ((bool)$found) {
            oxRegistry::getSession()->deleteVariable('klarna_checkout_order_id');
//            oxRegistry::getUtils()->writeToLog('Paypal button usage detected: ' . json_encode($found, 128), 'my.log');
        }

        return parent::_initializeViewObject($sClass, $sFunction, $aParams, $aViewsChain);
    }
}