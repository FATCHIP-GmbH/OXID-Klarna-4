<?php
/**
 * Copyright 2018 Klarna AB
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class klarna_user extends klarna_user_parent
{
    /**
     *
     * @throws oxSystemComponentException
     */
    public function init()
    {
        parent::init();

        if ($amazonOrderId = oxRegistry::getConfig()->getRequestParameter('amazonOrderReferenceId')) {
            oxRegistry::getSession()->setVariable('amazonOrderReferenceId', $amazonOrderId);
        }

        if (KlarnaUtils::isKlarnaCheckoutEnabled()) {
            $sCountryISO = oxRegistry::getSession()->getVariable('sCountryISO');

            if (KlarnaUtils::isCountryActiveInKlarnaCheckout($sCountryISO) &&
                !oxRegistry::getSession()->hasVariable('amazonOrderReferenceId')
            ) {
                oxRegistry::getUtils()->redirect(oxRegistry::getConfig()->getShopHomeURL() .
                                                 'cl=klarna_express', false, 302);
            }
        }

    }

    /**
     * @return mixed
     * @throws oxSystemComponentException
     */
    public function getInvoiceAddress()
    {
        $result   = parent::getInvoiceAddress();
        $viewConf = oxRegistry::get('oxViewConfig');

        if (!$result && $viewConf->isCheckoutNonKlarnaCountry()) {
            $oCountry                      = oxNew('oxcountry');
            $result['oxuser__oxcountryid'] = $oCountry->getIdByCode(oxRegistry::getSession()->getVariable('sCountryISO'));
        }

        return $result;
    }

    /**
     *
     */
    public function klarnaResetCountry()
    {
        $invadr = oxRegistry::getConfig()->getRequestParameter('invadr');
        oxRegistry::get('oxcmp_user')->changeuser();
        unset($invadr['oxuser__oxcountryid']);
        unset($invadr['oxuser__oxzip']);
        unset($invadr['oxuser__oxstreet']);
        unset($invadr['oxuser__oxstreetnr']);
        $invadr['oxuser__oxusername'] = oxRegistry::getConfig()->getRequestParameter('lgn_usr');
        oxRegistry::getSession()->setVariable('invadr', $invadr);
        KlarnaUtils::fullyResetKlarnaSession();

        $sUrl = oxRegistry::getConfig()->getShopSecureHomeURL() . 'cl=klarna_express&reset_klarna_country=1';
        oxRegistry::getUtils()->showMessageAndExit($sUrl);
    }
}