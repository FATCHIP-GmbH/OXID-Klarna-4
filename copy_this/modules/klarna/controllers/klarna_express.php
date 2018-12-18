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

class klarna_express extends oxUBase
{
    /**
     * @var string
     */
    protected $_sThisTemplate = 'kl_klarna_checkout.tpl';

    /**
     * @var KlarnaOrder
     */
    protected $_oKlarnaOrder;

    /**
     * @var klarna_oxuser
     */
    protected $_oUser;

    /**
     * @var bool
     */
    protected $blockIframeRender;

    /**
     * @var array
     */
    protected $_aOrderData;

    /** @var string country selected by the user in the popup */
    protected $selectedCountryISO;

    /** @var bool show select country popup to the user */
    protected $blShowPopup;

    /**
     *
     * @throws oxSystemComponentException
     * @throws Exception
     */
    public function init()
    {

        $oSession = oxRegistry::getSession();
        $oBasket  = $oSession->getBasket();
        $oConfig  = oxRegistry::getConfig();
        $oUtils   = oxRegistry::getUtils();


        /**
         * KCO is not enabled. redirect to legacy oxid checkout
         */
        if (KlarnaUtils::getShopConfVar('sKlarnaActiveMode') !== 'KCO') {
            $oUtils->redirect(oxRegistry::getConfig()->getShopSecureHomeUrl() . 'cl=order', false, 302);
            return;
        }

        /**
         * Reset Klarna session if flag set by changing user address data in the User Controller earlier.
         */
        $this->checkForSessionResetFlag();

        $this->determineUserControllerAccess();

        /**
         * Returning from legacy checkout for guest user.
         * Request parameter reset_klarna_country is checked and $this->blockIframeRender is set.
         */
        if ($oConfig->getRequestParameter('reset_klarna_country') == 1) {
            $this->blockIframeRender = true;
        }

        $oBasket->setPayment('klarna_checkout');
        $oSession->setVariable('paymentid', 'klarna_checkout');

        parent::init();
    }

    /**
     * @return string
     * @throws oxSystemComponentException
     * @throws oxConnectionException
     * @throws oxException
     */
    public function render()
    {
        $oSession = oxRegistry::getSession();
        $oBasket  = $oSession->getBasket();
        $this->rebuildFakeUser($oBasket);

        $result   = parent::render();

        /**
         * Reload page with ssl if not secure already.
         */
        $this->checkSsl();

        /**
         * Check if we have a logged in user.
         * If not create a fake one.
         */
        if(!$this->_oUser){
            $this->_oUser = $this->resolveUser();
        }

        $oBasket->setBasketUser($this->_oUser);

        $this->blShowPopup = $this->showCountryPopup();
        $this->addTplParam("blShowPopUp", $this->blShowPopup);

        if ($this->blockIframeRender) {
            return $this->_sThisTemplate;
        }

        $this->addTplParam('blShowCountryReset', KlarnaUtils::isNonKlarnaCountryActive());

        try {
            $oKlarnaOrder = oxNew('KlarnaOrder', $oBasket, $this->_oUser);
        } catch (KlarnaConfigException $e) {
            oxRegistry::get("oxUtilsView")->addErrorToDisplay($e);
            KlarnaUtils::fullyResetKlarnaSession();

            return $this->_sThisTemplate;

        } catch (KlarnaBasketTooLargeException $e) {
            oxRegistry::get("oxUtilsView")->addErrorToDisplay($e);

            $this->redirectForNonKlarnaCountry(oxRegistry::getSession()->getVariable('sCountryISO'), false);

            return $this->_sThisTemplate;
        }

        if ($oSession->getVariable('wrong_merchant_urls')) {
            $oSession->deleteVariable('wrong_merchant_urls');
            oxRegistry::get("oxUtilsView")->addErrorToDisplay('KLARNA_WRONG_URLS_CONFIG', false, true);
            $this->addTplParam('confError', true);

            return $this->_sThisTemplate;
        }
        $orderData = $oKlarnaOrder->getOrderData();

        if (!KlarnaUtils::isCountryActiveInKlarnaCheckout(strtoupper($orderData['purchase_country']))) {

            $sUrl = oxRegistry::getConfig()->getShopHomeURL() . 'cl=user';
            oxRegistry::getUtils()->redirect($sUrl, false, 302);

            return;
        }

        try {
            $this->getKlarnaClient(oxRegistry::getSession()->getVariable('sCountryISO'))
                ->initOrder($oKlarnaOrder)
                ->createOrUpdateOrder();

        } catch (KlarnaWrongCredentialsException $oEx) {
            KlarnaUtils::fullyResetKlarnaSession();
            oxRegistry::get("oxUtilsView")->addErrorToDisplay(
                oxRegistry::getLang()->translateString('KLARNA_UNAUTHORIZED_REQUEST', null, true));
            oxRegistry::getUtils()->redirect(oxRegistry::getConfig()->getShopSecureHomeURL() . 'cl=start', true, 301);

            return $this->_sThisTemplate;
        } catch (oxException $oEx) {
            $oEx->debugOut();
            KlarnaUtils::fullyResetKlarnaSession();
            oxRegistry::getUtils()->redirect(oxRegistry::getConfig()->getShopSecureHomeURL() . 'cl=klarna_express', false, 302);

            return $this->_sThisTemplate;
        }

        $this->addTemplateParameters();

        return $result;
    }

    /**
     * @return bool
     */
    protected function showCountryPopup()
    {
        $sCountryISO        = $this->getSession()->getVariable('sCountryISO');
        $resetKlarnaCountry = oxRegistry::getConfig()->getRequestParameter('reset_klarna_country');

        if ($resetKlarnaCountry) {
            return true;
        }

        if (!KlarnaUtils::isNonKlarnaCountryActive()) {
            return false;
        }

        if ($this->isKLUserLoggedIn()) {
            return false;
        }

        if ($sCountryISO) {
            return false;
        }

        return true;
    }

    /**
     *
     * @return bool
     * @throws \oxSystemComponentException
     */
    protected function isKLUserLoggedIn()
    {
        $oUser = $this->getUser();

        if ($oUser && $oUser->kl_getType() === klarna_oxuser::LOGGED_IN) {
            return true;
        }

        return false;
    }

    /**
     * @return KlarnaCheckoutClient|KlarnaClientBase
     */
    public function getKlarnaClient($sCountryISO)
    {
        return KlarnaCheckoutClient::getInstance($sCountryISO);
    }

    /**
     * Get addresses saved by the user if any exist.
     * @throws oxConnectionException
     */
    public function getFormattedUserAddresses()
    {
        if ($this->_oUser->isFake()) {
            return false;
        }

        return KlarnaFormatter::getFormattedUserAddresses($this->_oUser);
    }

    /**
     *
     * @throws oxSystemComponentException
     */
    public function getKlarnaModalFlagCountries()
    {
        $flagCountries = KlarnaConsts::getKlarnaPopUpFlagCountries();

        $result = array();
        foreach ($flagCountries as $isoCode) {
            $country = oxNew('oxcountry');
            $id      = $country->getIdByCode($isoCode);
            $country->load($id);
            if ($country->oxcountry__oxactive->value == 1) {
                $result[] = $country;
            }
        }

        return $result;
    }

    /**
     *
     * @throws oxSystemComponentException
     */
    public function getKlarnaModalOtherCountries()
    {
        $flagCountries               = KlarnaConsts::getKlarnaPopUpFlagCountries();
        $activeKlarnaGlobalCountries = KlarnaUtils::getKlarnaGlobalActiveShopCountries();

        $result = array();
        foreach ($activeKlarnaGlobalCountries as $country) {
            if (in_array($country->oxcountry__oxisoalpha2->value, $flagCountries)) {
                continue;
            }
            $result[] = $country;
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getBreadCrumb()
    {
        $aPaths        = array();
        $aPath         = array();
        $iBaseLanguage = oxRegistry::getLang()->getBaseLanguage();

        $aPath['title'] = oxRegistry::getLang()->translateString('KL_CHECKOUT', $iBaseLanguage, false);
        $aPath['link']  = $this->getLink();
        $aPaths[]       = $aPath;

        return $aPaths;
    }

    /**
     * @throws oxSystemComponentException
     */
    public function getActiveShopCountries()
    {
        $list = oxNew('oxCountryList');
        $list->loadActiveCountries();

        return $list;
    }

    /**
     * @throws oxSystemComponentException
     * @return klarna_oxcountrylist|object|oxCountryList
     */
    public function getNonKlarnaCountries()
    {
        $list = oxNew('oxCountryList');
        $list->loadActiveCountries();

        foreach ($list->getArray() as $id => $country)
        {
            if(array_key_exists($id,KlarnaUtils::getAllActiveKCOGlobalCountryList()->getArray())){
                unset($list[$id]);
            }
        }

        return $list;
    }


    /**
     * @param $sCountryISO
     */
    protected function redirectForNonKlarnaCountry($sCountryISO, $blShippingOptionsSet = true)
    {
        if ($blShippingOptionsSet === false) {
            $sUrl = oxRegistry::getConfig()->getShopSecureHomeUrl() . 'cl=basket';
        } else {
            $sUrl = oxRegistry::getConfig()->getShopSecureHomeUrl() . 'cl=user&non_kco_global_country=' . $sCountryISO;
        }
        oxRegistry::getUtils()->redirect($sUrl, false, 302);
    }

    /**
     *
     */
    public function setKlarnaDeliveryAddress()
    {
        $oxidAddress = oxRegistry::getConfig()->getRequestParameter('klarna_address_id');
        oxRegistry::getSession()->setVariable('deladrid', $oxidAddress);
        oxRegistry::getSession()->setVariable('blshowshipaddress', 1);
        oxRegistry::getSession()->deleteVariable('klarna_checkout_order_id');
    }



    /**
     *
     * @param $oBasket
     * @return KlarnaOrder
     */
    protected function getKlarnaOrder($oBasket)
    {
        return new KlarnaOrder($oBasket, $this->_oUser);
    }

    /**
     *
     */
    protected function checkForSessionResetFlag()
    {
        if (oxRegistry::getSession()->getVariable('resetKlarnaSession') == 1) {
            KlarnaUtils::fullyResetKlarnaSession();
        }
    }

    /**
     *
     */
    protected function changeUserCountry()
    {
        if ($this->getUser()) {
            $oCountry   = oxNew('oxCountry');
            $sCountryId = $oCountry->getIdByCode($this->selectedCountryISO);
            $oCountry->load($sCountryId);
            $this->getUser()->oxuser__oxcountryid = new oxField($sCountryId);
            $this->getUser()->oxuser__oxcountry   = new oxField($oCountry->oxcountry__oxtitle->value);
            $this->getUser()->save();
        }
    }

    /**
     * @param $oSession
     * @param $oUtils
     */
    protected function handleCountryChangeFromPopup()
    {
        $oUtils   = oxRegistry::getUtils();
        if (KlarnaUtils::isCountryActiveInKlarnaCheckout($this->selectedCountryISO)) {
            $sUrl = oxRegistry::getConfig()->getShopSecureHomeUrl() . 'cl=klarna_express';
            $oUtils->redirect($sUrl, false, 302);
            /**
             * Redirect to legacy oxid checkout if selected country is not a KCO country.
             */
        } else {
            $this->redirectForNonKlarnaCountry($this->selectedCountryISO);
        }
    }

    /**
     * @param $oSession
     * @throws \oxSystemComponentException
     */
    protected function handleLoggedInUserWithNonKlarnaCountry($oSession)
    {
        /**
         * User is coming back from legacy oxid checkout wanting to change the country to one of KCO ones
         */
        if ($this->getConfig()->getRequestParameter('reset_klarna_country') == 1) {
            $oSession->setVariable('sCountryISO', KlarnaUtils::getShopConfVar('sKlarnaDefaultCountry'));
            /**
             * User is trying to access the klarna checkout for the first time and has to be redirected to legacy oxid checkout
             */
        } else {
            $oSession->setVariable('sCountryISO', $this->getUser()->getUserCountryISO2());
            $this->redirectForNonKlarnaCountry($this->getUser()->getUserCountryISO2());
        }
    }

    /**
     * Handle country changes from within or outside the iframe.
     * Redirect to legacy oxid checkout if country not valid for Klarna Checkout.
     * Receive redirects from legacy oxid checkout when changing back to a country handled by KCO
     *
     * @throws \oxSystemComponentException
     */
    protected function determineUserControllerAccess()
    {
        $oSession = oxRegistry::getSession();
        /**
         * A country has been selected from the country popup.
         */
        $this->selectedCountryISO = $this->getConfig()->getRequestParameter('selected-country');
        if ($this->selectedCountryISO) {
            $oSession->setVariable('sCountryISO', $this->selectedCountryISO);

            /**
             * Remove delivery address on country change
             */
            oxRegistry::getSession()->setVariable('blshowshipaddress', 0);
            /**
             * If user logged in - save the new country choice.
             */
            $this->changeUserCountry();
            /**
             * Restart klarna session on country change and reload the page
             * or redirect to legacy oxid checkout if selected country is not a KCO country.
             */
            $this->handleCountryChangeFromPopup();

            return;
        }
        /**
         * Logged in user with a non KCO country attempting to render the klarna checkout.
         */
        if ($this->getUser() && !KlarnaUtils::isCountryActiveInKlarnaCheckout($this->getUser()->getUserCountryISO2())) {
            /**
             * User is coming back from legacy oxid checkout wanting to change the country to one of KCO ones
             * or user is trying to access the klarna checkout for the first time and has to be redirected to
             * legacy oxid checkout
             */
            $this->handleLoggedInUserWithNonKlarnaCountry($oSession);

            return;
        }

        /**
         * Default country is not KCO and we need the country popup without rendering the iframe.
         */
        if (!$oSession->getVariable('sCountryISO') &&
            !KlarnaUtils::isCountryActiveInKlarnaCheckout(KlarnaUtils::getShopConfVar('sKlarnaDefaultCountry')) &&
            $this->getConfig()->getRequestParameter('reset_klarna_country') != 1
        ) {
            $oSession->setVariable('sCountryISO', KlarnaUtils::getShopConfVar('sKlarnaDefaultCountry'));
            $this->redirectForNonKlarnaCountry(KlarnaUtils::getShopConfVar('sKlarnaDefaultCountry'));
        }
    }

    protected function addTemplateParameters()
    {
        $sCountryISO = oxRegistry::getSession()->getVariable('sCountryISO');

        if (!KlarnaUtils::is_ajax()) {
            $oCountry = oxNew('oxCountry');
            $oCountry->load($oCountry->getIdByCode($sCountryISO));
            $this->addTplParam("sCountryName", $oCountry->oxcountry__oxtitle->value);

            $this->addTplParam("sPurchaseCountry", $sCountryISO);
            $this->addTplParam("sKlarnaIframe", $this->getKlarnaClient($sCountryISO)->getHtmlSnippet());
            $this->addTplParam("sCurrentUrl", oxRegistry::get('oxutilsurl')->getCurrentUrl());
            $this->addTplParam("shippingAddressAllowed", KlarnaUtils::getShopConfVar('blKlarnaAllowSeparateDeliveryAddress'));
        }
    }

    /**
     * @throws \oxSystemComponentException
     */
    protected function resolveUser()
    {
        $oSession = $this->getSession();

        /** @var KlarnaUser|User $oUser */
        $oUser = $this->getUser();
        if ($oUser && !empty($oUser->oxuser__oxpassword->value)) {
            $oUser->kl_checkUserType();
        } else {
            $email = $oSession->getVariable('klarna_checkout_user_email');
            /** @var KlarnaUser|User $oUser */
            $oUser = KlarnaUtils::getFakeUser($email);
        }

        return $oUser;
    }

    /**
     */
    protected function checkSsl()
    {
        $oConfig = $this->getConfig();
        $blAlreadyRedirected = $oConfig->getRequestParameter('sslredirect') == 'forced';
        $oConfig             = $this->getConfig();
        $oUtils              = oxRegistry::getUtils();
        if ($oConfig->getCurrentShopURL() != $oConfig->getSSLShopURL() && !$blAlreadyRedirected) {
            $sUrl = $oConfig->getShopSecureHomeUrl() . 'sslredirect=forced&cl=klarna_express';

            $oUtils->redirect($sUrl, false, 302);
        }
    }

    /**
     * Checks if user is fake - not registered
     * Used in the ServiceMenu Controller
     *
     */
    public function isKlarnaFakeUser()
    {
        return $this->_oUser->isFake();
    }

    /**
     * @param $oBasket
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     */
    protected function rebuildFakeUser($oBasket)
    {
        /** @var KlarnaUser|User $user */
        $user = $this->getUser();

        if ($user && empty($user->oxuser__oxpassword->value)) {
            $oClient = KlarnaCheckoutClient::getInstance();
            try{
                $_aOrderData = $oClient->getOrder();
            } catch (KlarnaClientException $e){
                $user->logout();
                return;
            }


            $this->getSession()->setBasket($oBasket);

            if ($_aOrderData && isset($_aOrderData['billing_address']['email'])) {
                $user->loadByEmail($_aOrderData['billing_address']['email']);
                $this->_oUser = $user;
                $this->getSession()->setVariable('klarna_checkout_order_id', $_aOrderData['order_id']);
                $this->getSession()->setVariable(
                    'klarna_checkout_user_email',
                    $_aOrderData['billing_address']['email']
                );
            }
        }
    }
}
