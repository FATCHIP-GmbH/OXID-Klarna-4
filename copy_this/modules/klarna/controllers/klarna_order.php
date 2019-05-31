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

/**
 * Extends default OXID order controller logic.
 */
class Klarna_Order extends Klarna_Order_parent
{
    const DUPLICATE_KEY_ERROR_CODE = 1062;

    protected $_aResultErrors;

    /** @var string  KlarnaExpressController url */
    protected $selfUrl;

    /**
     * @var oxUser|klarna_oxuserser
     */
    protected $_oUser;

    /**
     * @var array data fetched from KlarnaCheckout
     */
    protected $_aOrderData;

    /** @var bool create new order on country change */
    protected $forceReloadOnCountryChange = false;

    /** @var  bool */
    public $loadKlarnaPaymentWidget = false;

    /**
     * @var bool
     */
    protected $isExternalCheckout = false;

    /**
     * @var array data fetched from KlarnaCheckout
     * @return string
     * @throws Exception
     */
    public function init()
    {
        parent::init();

        if (KlarnaUtils::isKlarnaCheckoutEnabled()) {
            $oConfig = oxRegistry::getConfig();
            $shopParam = method_exists($oConfig, 'mustAddShopIdToRequest')
                         && $oConfig->mustAddShopIdToRequest()
                ? '&shp=' . $oConfig->getShopId()
                : '';
            $oBasket        = oxRegistry::getSession()->getBasket();
            $this->selfUrl  = $oConfig->getShopSecureHomeUrl() . 'cl=klarna_express';
            if (oxRegistry::getConfig()->getRequestParameter('externalCheckout') == 1) {
                oxRegistry::getSession()->setVariable('externalCheckout', true);
            }
            $this->isExternalCheckout = oxRegistry::getSession()->getVariable('externalCheckout');

            if ($this->isKlarnaCheckoutOrder($oBasket)) {
                if ($newCountry = $this->isCountryChanged()) {
                    $this->_aOrderData = array(
                        'merchant_urls'    => array(
                            'checkout' => $oConfig->getSslShopUrl() . "?cl=klarna_express" . $shopParam,
                        ),
                        'billing_address'  => array(
                            'country' => $newCountry,
                            'email'   => oxRegistry::getSession()->getVariable('klarna_checkout_user_email'),
                        ),
                        'shipping_address' => array(
                            'country' => $newCountry,
                            'email'   => oxRegistry::getSession()->getVariable('klarna_checkout_user_email'),
                        ),
                    );
                    oxRegistry::getSession()->setVariable('sCountryISO', $newCountry);
                } else {
                    $oClient = $this->getKlarnaCheckoutClient();
                    try {
                        $this->_aOrderData = $oClient->getOrder();
                    } catch (KlarnaClientException $oEx) {
                        $oEx->debugOut();
                    }

                    if (KlarnaUtils::is_ajax() && $this->_aOrderData['status'] === 'checkout_complete') {
                        $this->jsonResponse('ajax', 'read_only');
                    }
                }
                $this->_initUser();
                $this->updateUserObject();
            }
        }
    }

    /**
     * Logging push state message to database
     * @param $action
     * @param $requestBody
     * @param $url
     * @param $response
     * @param $errors
     * @param string $redirectUrl
     * @throws oxSystemComponentException
     * @internal param KlarnaOrderValidator $oValidator
     */
    protected function logKlarnaData($action, $requestBody, $url, $response, $errors, $redirectUrl = '')
    {
        $order_id = isset($requestBody['order_id']) ? $requestBody['order_id'] : '';

        $oKlarnaLog = oxNew('klarna_logs');
        $aData      = array(
            'kl_logs__klmethod'      => $action,
            'kl_logs__klurl'         => $url,
            'kl_logs__klorderid'     => $order_id,
            'kl_logs__klrequestraw'  => json_encode($requestBody) .
                                        " \nERRORS:" . var_export($errors, true) .
                                        " \nHeader Location:" . $redirectUrl,
            'kl_logs__klresponseraw' => $response,
            'kl_logs__kldate'        => date("Y-m-d H:i:s"),
        );
        $oKlarnaLog->assign($aData);
        $oKlarnaLog->save();
    }


    protected function getKlarnaAllowedExternalPayments()
    {
        return KlarnaPayment::getKlarnaAllowedExternalPayments();
    }

    protected function isKlarnaExternalPaymentMethod($paymentId, $sCountryISO)
    {
        if (!in_array($paymentId, $this->getKlarnaAllowedExternalPayments())) {
            return false;
        }
        if (!KlarnaUtils::isCountryActiveInKlarnaCheckout($sCountryISO)) {
            return false;
        }

        return true;
    }

    /**
     * @param $oBasket oxBasket
     * @return bool
     */
    protected function isKlarnaCheckoutOrder($oBasket)
    {
        $paymentId   = $oBasket->getPaymentId();
        $sCountryISO = oxRegistry::getSession()->getVariable('sCountryISO');

        if (!($paymentId === 'klarna_checkout' || $this->isKlarnaExternalPaymentMethod($paymentId, $sCountryISO))) {
            return false;
        }

        if ($this->isExternalCheckout) {
            return false;
        }

        if ($this->isPayPalAmazon()) {
            return false;
        }

        return true;
    }

    /**
     *
     * @return KlarnaCheckoutClient|KlarnaClientBase
     */
    protected function getKlarnaCheckoutClient()
    {
        return KlarnaCheckoutClient::getInstance();
    }

    /**
     * @return KlarnaPaymentsClient|KlarnaClientBase
     */
    protected function getKlarnaPaymentsClient()
    {
        return KlarnaPaymentsClient::getInstance();
    }

    /**
     * Runs security checks. Returns true if all passes
     * @return bool
     */
    protected function klarnaCheckoutSecurityCheck()
    {
        $oConfig = $this->getConfig();
        $requestedKlarnaId = $oConfig->getRequestParameter('klarna_order_id');
        $sessionKlarnaId = oxRegistry::getSession()->getVariable('klarna_checkout_order_id');

        // compare klarna ids - request to session
        if(empty($requestedKlarnaId) || $requestedKlarnaId !== $sessionKlarnaId){
            return false;
        }
        // make sure if klarna order was validated
        if (!$this->_aOrderData || $this->_aOrderData['status'] !== 'checkout_complete') {
            return false;
        }

        return true;
    }

    /**
     * Klarna confirmation callback. Calls only parent execute (standard oxid order creation) if not klarna_checkout
     * @throws Exception
     * @throws oxException
     * @throws oxSystemComponentException
     */
    public function execute()
    {
        $oBasket = oxRegistry::getSession()->getBasket();
        $paymentId = $oBasket->getPaymentId();

        if(klarna_oxpayment::isKlarnaPayment($paymentId)){
            /**
             * sDelAddrMD5 value is up to date with klarna user data (we updated user object in the init method)
             *  It is required later to validate user data before order creation
             */
            if($this->_oUser || $this->getUser()){
                oxRegistry::getSession()->setVariable('sDelAddrMD5', $this->getDeliveryAddressMD5());
            }

            // Are we in the KCO context
            if ($paymentId === 'klarna_checkout') {
                if (!$this->getSession()->checkSessionChallenge()) {
                    return;
                }

                if (!$this->klarnaCheckoutSecurityCheck()) {
                    return 'KlarnaExpress';
                }


                $this->kcoBeforeExecute();
                $iSuccess = $this->kcoExecute($oBasket);

                return $this->_getNextStep($iSuccess);
            }

        }

        // if user is not logged in set the user
        if(!$this->getUser() && isset($this->_oUser)){
            $this->setUser($this->_oUser);
        }

        $result = parent::execute();

        return $result;
    }

    /**
     * Runs before oxid execute in KP mode
     * Saves authorization token
     * Runs final validation
     * Creates order on Klarna side
     *
     * @throws oxSystemComponentException
     */
    public function kpBeforeExecute()
    {

        // downloadable product validation for sofort
        if (!$termsValid = $this->_validateTermsAndConditions()) {
            oxRegistry::get('oxUtilsView')->addErrorToDisplay('KL_PLEASE_AGREE_TO_TERMS');
            oxRegistry::getUtils()->redirect(oxRegistry::getConfig()->getShopSecureHomeUrl() . 'cl=order', false, 302);
        }

        if ($sAuthToken = oxRegistry::getConfig()->getRequestParameter('sAuthToken')) {
            oxRegistry::getSession()->setVariable('sAuthToken', $sAuthToken);
            $dt = new DateTime();
            oxRegistry::getSession()->setVariable('sTokenTimeStamp', $dt->getTimestamp());
        }


        if ($sAuthToken || oxRegistry::getSession()->hasVariable('sAuthToken')) {

            $oBasket = oxRegistry::getSession()->getBasket();
            /** @var  $oKlarnaPayment KlarnaPayment */
            $oKlarnaPayment = oxNew('KlarnaPayment', $oBasket, $this->getUser());

            $created = false;
            $oKlarnaPayment->validateOrder();

            $valid = $this->validatePayment($created, $oKlarnaPayment, $termsValid);

            if (!$valid || !$created) {
                oxRegistry::getUtils()->redirect(Registry::getConfig()->getShopSecureHomeUrl() . 'cl=order', false, 302);
            }

            oxRegistry::getSession()->setVariable('klarna_last_KP_order_id', $created['order_id']);
            oxRegistry::getUtils()->redirect($created['redirect_url'], false, 302);
        }
    }

    /**
     * @param $created
     * @param KlarnaPayment $oKlarnaPayment
     * @param $termsValid
     * @throws \oxSystemComponentException
     * @return bool
     */
    protected function validatePayment(&$created, KlarnaPayment $oKlarnaPayment, $termsValid)
    {
        $oClient = $this->getKlarnaPaymentsClient();
        $valid   = !$oKlarnaPayment->isError() && $termsValid;
        if ($valid) {
            $created = $oClient->initOrder($oKlarnaPayment)->createNewOrder();
        } else {
            $oKlarnaPayment->displayErrors();
        }

        return $valid;
    }

    /**
     * @throws oxException
     * @throws oxSystemComponentException
     */
    protected function kcoBeforeExecute()
    {
        try {
            $this->_validateUser($this->_aOrderData);
        } catch (oxException $exception) {
            $this->_aResultErrors[] = $exception->getMessage();
            $this->logKlarnaData(
                'Order Execute',
                $this->_aOrderData,
                '',
                '',
                $this->_aResultErrors,
                ''
            );
        }

        // send newsletter confirmation
        if ($this->isNewsletterSignupNeeded()) {
            if ($oUser = $this->getUser()) {
                $oUser->setNewsSubscription(true, true);  // args = [value, send_confirmation]
            } else {
                throw new oxException('no user object');
            }
        }
    }


    /**
     * Check if user is logged in, if not check if user is in oxid and log them in
     * or create a user
     * @return bool
     * @throws oxException
     */
    protected function _validateUser()
    {
        switch ($this->_oUser->kl_getType()) {

            case Klarna_oxUser::NOT_EXISTING:
            case Klarna_oxUser::NOT_REGISTERED:
                // create regular account with password or temp account - empty password
                $result = $this->_createUser();

                return $result;

            default:
                break;
        }
    }

    /**
     * Create a user in oxid from klarna checkout data
     * @return bool
     * @throws oxException
     */
    protected function _createUser()
    {
        $aBillingAddress  = KlarnaFormatter::klarnaToOxidAddress($this->_aOrderData, 'billing_address');

        $aDeliveryAddress = null;
        if($this->_aOrderData['billing_address'] !== $this->_aOrderData['shipping_address']){
            $aDeliveryAddress = KlarnaFormatter::klarnaToOxidAddress($this->_aOrderData, 'shipping_address');
        }

        $this->_oUser->oxuser__oxusername = new oxField($this->_aOrderData['billing_address']['email'], oxField::T_RAW);
        $this->_oUser->oxuser__oxactive   = new oxField(1, oxField::T_RAW);

        if (isset($this->_aOrderData['customer']['date_of_birth'])) {
            $this->_oUser->oxuser__oxbirthdate = new oxField($this->_aOrderData['customer']['date_of_birth']);
        }

        $this->_oUser->createUser();

        //NECESSARY to have all fields initialized.
        $this->_oUser->load($this->_oUser->getId());

        $password = $this->isRegisterNewUserNeeded() ? $this->getRandomPassword(8) : null;
        $this->_oUser->setPassword($password);

        $this->_oUser->changeUserData($this->_oUser->oxuser__oxusername->value, $password, $password, $aBillingAddress, $aDeliveryAddress);

        // login only if registered a new account with password
        if ($this->isRegisterNewUserNeeded()) {
            oxRegistry::getSession()->setVariable('usr', $this->_oUser->getId());
            oxRegistry::getSession()->setVariable('blNeedLogout', true);
        }

        $this->setUser($this->_oUser);

        if($aDeliveryAddress){
            $this->_oUser->updateDeliveryAddress($aDeliveryAddress);
        }

        return true;
    }

    /**
     * Save order to database, delete order_id from session and redirect to thank you page
     *
     * @param oxBasket $oBasket
     * @throws oxSystemComponentException
     */
    protected function kcoExecute(oxBasket $oBasket)
    {
        // reload blocker
        if (!oxRegistry::getSession()->getVariable('sess_challenge')) {
            $sGetChallenge = oxUtilsObject::getInstance()->generateUID();
            oxRegistry::getSession()->setVariable('sess_challenge', $sGetChallenge);
        }

        $oBasket->calculateBasket(true);

        $oOrder = oxNew('oxorder');
        try {
            $iSuccess = $oOrder->finalizeOrder($oBasket, $this->_oUser);
        } catch (oxException $e) {
            oxRegistry::getSession()->deleteVariable('klarna_checkout_order_id');

            oxRegistry::get("oxUtilsView")->addErrorToDisplay($e);

        }

        if ($iSuccess === 1) {
            if (
                ($this->_oUser->kl_getType() === klarna_oxuser::NOT_REGISTERED ||
                 $this->_oUser->kl_getType() === klarna_oxuser::NOT_EXISTING) &&
                $this->isRegisterNewUserNeeded()
            ) {
                $this->_oUser->save();
            }
            // remove address record for fake user
            if ($this->_oUser->isFake())
                $this->_oUser->clearDeliveryAddress();
            // performing special actions after user finishes order (assignment to special user groups)
            $this->_oUser->onOrderExecute($oBasket, $iSuccess);

            if ($this->isRegisterNewUserNeeded()) {

                /** @var oxEmail $oEmail */
                $oEmail = oxNew('oxEmail');
                $oEmail->sendForgotPwdEmail($this->_oUser->oxuser__oxusername->value);
            }

            oxRegistry::getSession()->setVariable('paymentid', 'klarna_checkout');
        }

        return $iSuccess;
    }


    /**
     * General Ajax entry point for this controller
     */
    public function updateKlarnaAjax()
    {
        $aPost = $this->getJsonRequest();

        $sessionData = oxRegistry::getSession()->getVariable('klarna_session_data');
        if (KlarnaUtils::isKlarnaPaymentsEnabled() && empty($sessionData)) {
            $this->resetKlarnaPaymentSession('basket');

            return;
        }

        switch ($aPost['action']) {
            case 'shipping_option_change':
                $this->shipping_option_change($aPost);
                break;

            case 'shipping_address_change':
                $this->shipping_address_change();
                break;

            case 'change':
                $this->updateSession($aPost);
                break;

            case 'checkOrderStatus':
                $this->checkOrderStatus($aPost);
                break;

            case 'addUserData':
                $this->addUserData($aPost);
                break;

            default:
                $this->jsonResponse('undefined action', 'error');
        }
    }


    /**
     * Ajax call for Klarna Payment. Tracks changes and controls frontend Widget by status message
     * @param $aPost
     * @return string
     * @throws KlarnaClientException
     * @throws oxException
     * @throws oxSystemComponentException
     * @throws ReflectionException
     */
    protected function checkOrderStatus($aPost)
    {
        if (!KlarnaUtils::isKlarnaPaymentsEnabled()) {
            return $this->jsonResponse(__FUNCTION__, 'submit');
        }

        $oSession = oxRegistry::getSession();
        $oBasket  = $oSession->getBasket();
        $oUser    = $this->getUser();

        if (KlarnaPayment::countryWasChanged($oUser)) {
            $this->resetKlarnaPaymentSession();
        }

        /** @var  $oKlarnaPayment KlarnaPayment */
        $oKlarnaPayment = oxNew('KlarnaPayment', $oBasket, $oUser, $aPost);

        if(!$oKlarnaPayment->isSessionValid()){
            $this->resetKlarnaPaymentSession();
        }

        if(!$oKlarnaPayment->validateClientToken($aPost['client_token'])){
            return $this->jsonResponse(
                __METHOD__,
                'refresh',
                array('refreshUrl' => $oKlarnaPayment->refreshUrl)
            );
        }

        $oKlarnaPayment->setStatus('submit');

        if ($oKlarnaPayment->isAuthorized()) {
            $this->handleAuthorizedPayment($oKlarnaPayment);
        } else {
            $oKlarnaPayment->setStatus('authorize');
        }

        if ($oKlarnaPayment->paymentChanged) {
            $oKlarnaPayment->setStatus('authorize');
            $oSession->deleteVariable('sAuthToken');
            $oSession->deleteVariable('finalizeRequired');
        }

        $this->getKlarnaPaymentsClient()
            ->initOrder($oKlarnaPayment)
            ->createOrUpdateSession();

        $responseData = array(
            'update'        => $aPost,
            'paymentMethod' => $oKlarnaPayment->getPaymentMethodCategory(),
            'refreshUrl'      => $oKlarnaPayment->refreshUrl,
        );

        return $this->jsonResponse(
            __METHOD__,
            $oKlarnaPayment->getStatus(),
            $responseData
        );
    }

    /**
     * @param KlarnaPayment $oKlarnaPayment
     */
    protected function handleAuthorizedPayment(KlarnaPayment &$oKlarnaPayment)
    {
        $reauthorizeRequired = oxRegistry::getSession()->getVariable('reauthorizeRequired');

        if ($reauthorizeRequired || $oKlarnaPayment->isOrderStateChanged() || !$oKlarnaPayment->isTokenValid()) {
            $oKlarnaPayment->setStatus('reauthorize');
            oxRegistry::getSession()->deleteVariable('reauthorizeRequired');

        } else if ($oKlarnaPayment->requiresFinalization()) {
            $oKlarnaPayment->setStatus('finalize');
            // front will ignore this status if it's payment page
        }
    }

    /**
     *
     * @param $aPost
     * @return string
     * @throws KlarnaClientException
     * @throws oxException
     * @throws oxSystemComponentException
     * @throws ReflectionException
     */
    protected function addUserData($aPost)
    {
        $oSession = oxRegistry::getSession();
        $oBasket  = $oSession->getBasket();
        $oUser    = $this->getUser();

        if (KlarnaPayment::countryWasChanged($oUser)) {
            $this->resetKlarnaPaymentSession();
        }

        /** @var  $oKlarnaPayment KlarnaPayment */
        $oKlarnaPayment         = oxNew('KlarnaPayment', $oBasket, $oUser, $aPost);

        if (!$oKlarnaPayment->isSessionValid()) {
            $this->resetKlarnaPaymentSession();
        }

        if (!$oKlarnaPayment->validateClientToken($aPost['client_token'])) {
            return $this->jsonResponse(
                __METHOD__,
                'refresh',
                array('refreshUrl' => $oKlarnaPayment->refreshUrl)
            );
        }

        $responseData           = array();
        $responseData['update'] = $oKlarnaPayment->getChangedData();
        $savedCheckSums         = $oKlarnaPayment->fetchCheckSums();
        if ($savedCheckSums['_aUserData'] === false) {
            $oKlarnaPayment->setCheckSum('_aUserData', true);
        }

        $result = $this->getKlarnaPaymentsClient()
            ->initOrder($oKlarnaPayment)
            ->createOrUpdateSession();


        $this->jsonResponse(__METHOD__, 'updateUser', $responseData);
    }

    /**
     * Ajax - updates country heading above iframe
     * @param $aPost
     * @return string
     * @throws oxSystemComponentException
     */
    protected function updateSession($aPost)
    {
        $responseData   = array();
        $responseStatus = 'success';

        if ($aPost['country']) {

            $oCountry = oxNew('oxCountry');
            $sSql     = $oCountry->buildSelectString(array('oxisoalpha3' => $aPost['country']));
            $oCountry->assignRecord($sSql);

            oxRegistry::getSession()->setVariable('sCountryISO', $oCountry->oxcountry__oxisoalpha2->value);
            $this->forceReloadOnCountryChange = true;

            try {
                $this->updateKlarnaOrder();
            } catch (oxException $e) {
                $e->debugOut();
            }

            $responseData['url'] = $this->_aOrderData['merchant_urls']['checkout'];
            $responseStatus      = 'redirect';
        }

        return oxRegistry::getUtils()->showMessageAndExit(
            $this->jsonResponse(__FUNCTION__, $responseStatus, $responseData)
        );
    }

    /**
     * Ajax shipping_option_change action
     * @param $aPost
     * @return null
     */
    protected function shipping_option_change($aPost)
    {
        if (isset($aPost['id'])) {

            // update basket
            $oSession = oxRegistry::getSession();
            $oBasket  = $oSession->getBasket();
            $oBasket->setShipping($aPost['id']);

            // update klarna order
            try {
                $this->updateKlarnaOrder();
            } catch (oxException $e) {
                $e->debugOut();
            }

            $responseData = array();
            $this->jsonResponse(__FUNCTION__, 'changed', $responseData);
        } else {
            $this->jsonResponse(__FUNCTION__, 'error');
        }
    }

    /**
     * Ajax shipping_address_change action
     * @throws oxException
     * @throws oxSystemComponentException
     */
    protected function shipping_address_change()
    {
        $this->updateUserObject();
        try {
            $oSession = oxRegistry::getSession();
            $oBasket  = $oSession->getBasket();
            if($vouchersCount = count($oBasket->getVouchers())){
                $oBasket->klarnaValidateVouchers();
                // update widget if there was some invalid vouchers
                if($vouchersCount !== count($oBasket->getVouchers())){
                    $status = 'update_voucher_widget';
                }
            }
            $this->updateKlarnaOrder();
            $status = isset($status) ? $status : 'changed';
        } catch (oxException $e) {
            $e->debugOut();
        }

        return $this->jsonResponse(__FUNCTION__, $status);

    }

    /**
     * Sends update request to checkout API
     * @return array order data
     * @throws KlarnaClientException
     * @throws oxException
     * @throws oxSystemComponentException
     * @internal param oxBasket $oBasket
     * @internal param oxUser $oUser
     */
    protected function updateKlarnaOrder()
    {
        if ($this->_oUser) {
            $oSession = $this->getSession();
            $oBasket  = $oSession->getBasket();

            $oKlarnaOrder = oxNew('KlarnaOrder', $oBasket, $this->_oUser);
            $oClient      = $this->getKlarnaCheckoutClient();
            $aOrderData   = $oKlarnaOrder->getOrderData();

            if ($this->forceReloadOnCountryChange && isset($this->_aOrderData['billing_address']) && isset($this->_aOrderData['shipping_address'])) {
                $aOrderData['billing_address']  = $this->_aOrderData['billing_address'];
                $aOrderData['shipping_address'] = $this->_aOrderData['shipping_address'];
            }

            return $oClient->createOrUpdateOrder(
                json_encode($aOrderData)
            );
        }

        return true;
    }

    /**
     * Initialize oxUser object and get order data from Klarna
     * @throws oxSystemComponentException
     * @throws oxConnectionException
     */
    protected function _initUser()
    {
        if ($this->_oUser = $this->getUser()) {
            $this->_oUser->kl_setType(Klarna_oxUser::NOT_REGISTERED);
            if ($this->getViewConfig()->isUserLoggedIn()) {
                $this->_oUser->kl_setType(Klarna_oxUser::LOGGED_IN);
            }
        } else {
            $this->_oUser = KlarnaUtils::getFakeUser($this->_aOrderData['billing_address']['email']);
        }
        $oCountry                          = oxNew('oxCountry');
        $this->_oUser->oxuser__oxcountryid = new oxField(
            $oCountry->getIdByCode(
                strtoupper($this->_aOrderData['billing_address']['country'])
            ),
            oxField::T_RAW
        );

        $oBasket = oxRegistry::getSession()->getBasket();
        $oBasket->setBasketUser($this->_oUser);
    }

    /**
     * Update oxUser object
     * @throws oxSystemComponentException
     */
    protected function updateUserObject()
    {
        if(!empty($this->_oUser)) {
            if ($this->_aOrderData['billing_address'] !== $this->_aOrderData['shipping_address']) {
                $this->_oUser->updateDeliveryAddress(KlarnaFormatter::klarnaToOxidAddress($this->_aOrderData, 'shipping_address'));
            } else {
                $this->_oUser->clearDeliveryAddress();
            }

            $this->_oUser->assign(KlarnaFormatter::klarnaToOxidAddress($this->_aOrderData, 'billing_address'));

            if (isset($this->_aOrderData['customer']['date_of_birth'])) {
                $this->_oUser->oxuser__oxbirthdate = new oxField($this->_aOrderData['customer']['date_of_birth']);
            }

            if ($this->_oUser->isWritable()) {
                try {
                    if($this->_oUser->kl_getType() == klarna_oxuser::NOT_EXISTING
                        && count($this->_oUser->getUserGroups()) == 0){
                        $this->_oUser->addToGroup('oxidnewcustomer');
                    }

                    $this->_oUser->save();
                } catch (\Exception $e){
                    if($e->getCode() === DUPLICATE_KEY_ERROR_CODE && $this->_oUser->kl_getType() == Klarna_oxUser::LOGGED_IN){
                        $this->_oUser->logout();
                    }
                }
            }
        }
    }

    /**
     * Clear KCO session.
     * Destroy client instance / force to use new credentials. This allow us to
     * create new order (using new merchant account) in this request
     *
     */
    protected function resetKlarnaCheckoutSession()
    {
        KlarnaCheckoutClient::resetInstance(); // we need new instance with new credentials
        oxRegistry::getSession()->deleteVariable('klarna_checkout_order_id');
    }

    /**
     * Handles external payment
     * @throws oxException
     */
    public function klarnaExternalPayment()
    {
        $oSession = oxRegistry::getSession();
        $orderId   = oxRegistry::getSession()->getVariable('klarna_checkout_order_id');
        $paymentId = oxRegistry::getConfig()->getRequestParameter('payment_id');
        if (!$orderId || !$paymentId || !$this->isActivePayment($paymentId)) {
            oxRegistry::get("oxUtilsView")->addErrorToDisplay('KLARNA_WENT_WRONG_TRY_AGAIN', false, true);
            oxRegistry::getUtils()->redirect($this->selfUrl, true, 302);
        }

        $oBasket  = $oSession->getBasket();

        $oSession->setVariable("paymentid", $paymentId);
        $oBasket->setPayment($paymentId);

        if ($this->isExternalCheckout) {
            $this->klarnaExternalCheckout($paymentId);
        }

        $oBasket->setPayment($paymentId);

        if ($this->_oUser->isCreatable()) {
            $this->_createUser();
        }

        // make sure we have the right shipping option
        $oBasket->setShipping($this->_aOrderData['selected_shipping_option']['id']);
        $oBasket->onUpdate();

        if ($paymentId === 'bestitamazon') {
            oxRegistry::getUtils()->redirect(oxRegistry::getConfig()->getShopSecureHomeUrl() . "cl=klarna_epm_dispatcher&fnc=amazonLogin", false);
        } else {
            oxRegistry::getConfig()->setConfigParam('blAmazonLoginActive', false);
        }


        if ($paymentId === 'oxidpaypal') {
            if ($this->_oUser->kl_getType() === Klarna_oxUser::LOGGED_IN) {

                return oxRegistry::get('oePayPalStandardDispatcher')->setExpressCheckout();
            }

            return oxRegistry::get('oePayPalExpressCheckoutDispatcher')->setExpressCheckout();
        }

        // if user is not logged in set the user to render order
        if(!$this->getUser() && isset($this->_oUser)){
            $this->setUser($this->_oUser);
        }
    }

    /**
     * @param $paymentId
     */
    public function klarnaExternalCheckout($paymentId)
    {
        if ($paymentId === 'bestitamazon') {
            oxRegistry::getUtils()->redirect(oxRegistry::getConfig()->getShopSecureHomeUrl() . "cl=klarna_epm_dispatcher&fnc=amazonLogin", false);
        } else if ($paymentId === 'oxidpaypal') {
            oxRegistry::get('oePayPalExpressCheckoutDispatcher')->setExpressCheckout();
        } else {
            KlarnaUtils::fullyResetKlarnaSession();
            oxRegistry::get("oxUtilsView")->addErrorToDisplay('KLARNA_WENT_WRONG_TRY_AGAIN', false, true);
            oxRegistry::getUtils()->redirect($this->selfUrl, true, 302);
        }
    }

    /**
     * Should we register a new user account with the order?
     * @return bool
     * @internal param $aOrderData
     */
    protected function isRegisterNewUserNeeded()
    {
        $checked          = $this->_aOrderData['merchant_requested']['additional_checkbox'] === true;
        $checkboxFunction = KlarnaUtils::getShopConfVar('iKlarnaActiveCheckbox');

        return $checkboxFunction > 0 && $checked;
    }

    /**
     * Should we sign the user up for the newsletter?
     * @return bool
     * @internal param $aOrderData
     */
    protected function isNewsletterSignupNeeded()
    {
        $checked          = $this->_aOrderData['merchant_requested']['additional_checkbox'] === true;
        $checkboxFunction = KlarnaUtils::getShopConfVar('iKlarnaActiveCheckbox');

        return $checkboxFunction > 1 && $checked;
    }

    /**
     * @param $len int
     * @return string
     */
    protected function getRandomPassword($len)
    {
        $alphabet    = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        $pass        = array();
        $alphaLength = strlen($alphabet) - 1;
        for ($i = 0; $i < $len; $i++) {
            $n      = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }

        return implode($pass);
    }

    /**
     * Formats Json response
     * @param $action
     * @param $status
     * @param $data
     * @return string
     */
    private function jsonResponse($action, $status = null, $data = null)
    {
        return oxRegistry::getUtils()->showMessageAndExit(json_encode(array(
            'action' => $action,
            'status' => $status,
            'data'   => $data,
        )));
    }

    /**
     * Gets data from request body
     * @return array
     * @codeCoverageIgnore
     */
    private function getJsonRequest()
    {
        $requestBody = file_get_contents('php://input');

        return json_decode($requestBody, true);
    }

    /**
     * @param $paymentId
     * @return bool
     * @throws oxSystemComponentException
     */
    protected function isActivePayment($paymentId)
    {
        $oPayment = oxNew('oxpayment');
        $oPayment->load($paymentId);

        return (boolean)$oPayment->oxpayments__oxactive->value;
    }

    /**
     * @return null|string
     * @throws oxSystemComponentException
     */
    public function render()
    {
        if (oxRegistry::getSession()->getVariable('paymentid') === "klarna_checkout") {
            oxRegistry::getSession()->deleteVariable('paymentid');
            oxRegistry::getUtils()->redirect(
                oxRegistry::getConfig()->getShopSecureHomeUrl() . "cl=basket", false
            );
        }

        $template = parent::render();

        if (KlarnaUtils::isKlarnaPaymentsEnabled() && $this->isCountryHasKlarnaPaymentsAvailable($this->_oUser)) {
            $oSession              = oxRegistry::getSession();
            $oBasket               = $oSession->getBasket();
            $payment_id            = $oBasket->getPaymentId();
            $aKlarnaPaymentMethods = Klarna_oxPayment::getKlarnaPaymentsIds('KP');

            if (in_array($payment_id, $aKlarnaPaymentMethods)) {
                // add KP js to the page
                $aKPSessionData = $oSession->getVariable('klarna_session_data');
                if ($aKPSessionData) {
                    $this->loadKlarnaPaymentWidget = true;
                    $this->addTplParam("client_token", $aKPSessionData['client_token']);
                    $this->addTplParam("tcKlarnaIsB2B", 'false');
                }
            }
            $this->addTplParam("sLocale", strtolower(KlarnaConsts::getLocale()));
        }

        return $template;
    }

    /**
     * @param string $controller
     * @return void
     */
    protected function resetKlarnaPaymentSession($controller = 'payment')
    {
        KlarnaPayment::cleanUpSession();

        $sPaymentUrl = htmlspecialchars_decode(oxRegistry::getConfig()->getShopSecureHomeUrl() . "cl=$controller");
        if (KlarnaUtils::is_ajax()) {
            $this->jsonResponse(__FUNCTION__, 'redirect', array('url' => $sPaymentUrl));
        }

        oxRegistry::getUtils()->redirect($sPaymentUrl, false, 302);
    }

    /**
     * @param null $sCountryISO
     * @return KlarnaOrderManagementClient|KlarnaClientBase
     */
    protected function getKlarnaOrderClient($sCountryISO = null)
    {
        return KlarnaOrderManagementClient::getInstance($sCountryISO);
    }

    /**
     *
     * @param $oUser
     * @return bool
     * @throws oxSystemComponentException
     */
    public function isCountryHasKlarnaPaymentsAvailable($oUser = null)
    {
        if ($oUser === null) {
            $oUser = $this->getUser();
        }
        $sCountryISO = KlarnaUtils::getCountryISO($oUser->getFieldData('oxcountryid'));
        if (in_array($sCountryISO, KlarnaConsts::getKlarnaCoreCountries())) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function includeKPWidget()
    {
        $paymentId = oxRegistry::getSession()->getBasket()->getPaymentId();

        return in_array($paymentId, klarna_oxpayment::getKlarnaPaymentsIds('KP'));
    }

    /**
     * @return bool
     */
    protected function isPayPalAmazon()
    {
        return in_array(oxRegistry::getSession()->getBasket()->getPaymentId(), array('oxidpaypal', 'bestitamazon'));
    }

    /**
     * @return bool|false|string
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     */
    protected function isCountryChanged()
    {
        $requestData = $this->getJsonRequest();
        $newCountry  = KlarnaUtils::getCountryIso2fromIso3(strtoupper($requestData['country']));
        $oldCountry  = oxRegistry::getSession()->getVariable('sCountryISO');

        if (!$newCountry) {
            return false;
        }

        return $newCountry != $oldCountry ? $newCountry : false;
    }

    public function getDeliveryAddressMD5()
    {
        // bill address
        $oUser = $this->getUser()?$this->getUser():$this->_oUser;
        $sDelAddress = $oUser->getEncodedDeliveryAddress();
        $oSession = oxRegistry::getSession();
        // delivery address
        if ($oSession->getVariable('deladrid')) {
            $oDelAddress = oxNew('oxAddress');
            $oDelAddress->load($oSession->getVariable('deladrid'));

            $sDelAddress .= $oDelAddress->getEncodedDeliveryAddress();
        }

        return $sDelAddress;
    }
}
