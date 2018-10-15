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
 *
 *
 * Class KlarnaOxCmp_User user component
 *
 * @package Klarna
 * @extend OxCmp_User
 */
class Klarna_OxCmp_User extends Klarna_OxCmp_User_parent
{
    /**
     * Redirect to klarna express page from this classes
     *
     * @var array
     */
    protected $_aClasses = array(
        'user',
        'klarna_express',
    );

    /**
     * Login user without redirection
     * @throws oxSystemComponentException
     */
    public function login_noredirect()
    {
        parent::login_noredirect();

        oxRegistry::getSession()->setVariable("iShowSteps", 1);
        $oViewConfig = oxNew('oxviewconfig');
        if ($oViewConfig->isKlarnaCheckoutEnabled()) {
            KlarnaUtils::fullyResetKlarnaSession();
            oxRegistry::getSession()->deleteVariable('sFakeUserId');
            if ($this->klarnaRedirect()) {
                oxRegistry::getUtils()->redirect(
                    $this->getConfig()->getShopSecureHomeUrl() . 'cl=klarna_express',
                    false,
                    302
                );
            }
        }
        if ($oViewConfig->isKlarnaPaymentsEnabled()) {
            KlarnaPayment::cleanUpSession();
        }
    }

    /**
     * Redirect to klarna checkout
     * @return bool
     */
    public function klarnaRedirect()
    {
        $sClass = oxRegistry::getConfig()->getRequestParameter('cl');

        return in_array($sClass, $this->_aClasses);
    }


    protected function _getLogoutLink()
    {

        $oViewConfig = oxNew('oxviewconfig');
        if ($oViewConfig->isKlarnaCheckoutEnabled() && $this->klarnaRedirect()) {

            $oConfig     = $this->getConfig();
            $sLogoutLink = $oConfig->isSsl() ? $oConfig->getShopSecureHomeUrl() : $oConfig->getShopHomeUrl();
            $sLogoutLink .= 'cl=' . 'basket' . $this->getParent()->getDynUrlParams();

            return $sLogoutLink . '&amp;fnc=logout';
        } else {
            return parent::_getLogoutLink();
        }
    }

    /**
     * @return string
     */
    public function changeuser_testvalues()
    {
        $result = parent::changeuser_testvalues();
        if (KlarnaUtils::isKlarnaCheckoutEnabled() && $result === 'account_user') {

            oxRegistry::getSession()->setVariable('resetKlarnaSession', 1);

            if (oxRegistry::getConfig()->getRequestParameter('blshowshipaddress')) {
                oxRegistry::getSession()->setVariable('blshowshipaddress', 1);
                oxRegistry::getSession()->setVariable('deladrid', oxRegistry::getConfig()->getRequestParameter('oxaddressid'));
            } else {
                oxRegistry::getSession()->deleteVariable('deladrid');
            }
        }

        return $result;
    }
}
