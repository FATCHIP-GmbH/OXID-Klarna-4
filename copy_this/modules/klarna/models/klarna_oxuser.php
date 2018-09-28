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
 * Class Klarna_oxUser extends default OXID oxUser class to add
 * Klarna payment related additional parameters and logic
 */
class klarna_oxuser extends klarna_oxuser_parent
{
    const NOT_EXISTING   = 0;    // email not exists in user table
    const REGISTERED     = 1;    // registered but not logged in
    const NOT_REGISTERED = 2;    // not registered returning customer
    const LOGGED_IN      = 3;    // logged in user

    /**
     *
     * @var int  user type
     */
    protected $_type;

    /**
     * @var string user country ISO
     */
    protected $_countryISO;

    /**
     * @return array
     * @throws oxSystemComponentException
     */
    public function getKlarnaData()
    {
        $shippingAddress = null;
        $result          = array();

        if ((bool)KlarnaUtils::getShopConfVar('blKlarnaEnablePreFilling')) {
            $this->preFillAddress($result);
        }


        if ($sCountryISO = $this->getConfig()->getRequestParameter('selected-country')) {
            if (oxRegistry::getSession()->hasVariable('invadr')) {
                oxRegistry::getSession()->deleteVariable('invadr');
            }
            $result['billing_address']['country'] = $sCountryISO;
            oxRegistry::getSession()->setVariable('sCountryISO', $sCountryISO);
        }

        return $result;
    }

    /**
     * @param $result
     * @throws \OxidEsales\EshopCommunity\Core\Exception\SystemComponentException
     */
    protected function preFillAddress(&$result)
    {
        $customer = array(
            'type' => 'person',
        );

        $userBirthDate = $this->getFieldData('oxbirthdate');
        if ($userBirthDate && $userBirthDate != '0000-00-00') {
            $customer['date_of_birth'] = $userBirthDate;
        }

        $result = array(
            'customer' => $customer,
        );

        $blShowShippingAddress = (bool)oxRegistry::getSession()->getVariable('blshowshipaddress');

        $billingAddress            = KlarnaFormatter::oxidToKlarnaAddress($this);
        $result['billing_address'] = isset($billingAddress) ? $billingAddress : null;

        if (oxRegistry::getSession()->hasVariable('deladrid') && $blShowShippingAddress) {
            $delAddressOxid = oxRegistry::getSession()->getVariable('deladrid');
            $oAddress       = oxNew('oxAddress');
            $oAddress->load($delAddressOxid);
            $shippingAddress            = KlarnaFormatter::oxidToKlarnaAddress($oAddress);
            $result['shipping_address'] = isset($shippingAddress) ? $shippingAddress : null;
        }
    }

    /**
     * Applicable in KP mode
     * @param bool $isB2BAvailable
     * @return array
     * @throws oxSystemComponentException
     */
    public function getKlarnaPaymentData($isB2BAvailable = false)
    {
        $customer = array(
            'date_of_birth' => null,
        );
        if (isset($this->oxuser__oxbirthdate->value) && $this->oxuser__oxbirthdate->value !== '0000-00-00') {
            $customer['date_of_birth'] = $this->oxuser__oxbirthdate->value;
        }

        $billingAddress = KlarnaFormatter::oxidToKlarnaAddress($this);

        if (oxRegistry::getSession()->hasVariable('deladrid')) {
            $oAddress = oxNew('oxAddress');
            $oAddress->load(oxRegistry::getSession()->getVariable('deladrid'));
            $shippingAddress = KlarnaFormatter::oxidToKlarnaAddress($oAddress);
        }

        $aUserData = array(
            'billing_address'  => $billingAddress,
            'shipping_address' => isset($shippingAddress) ? $shippingAddress : $billingAddress,
            'customer'         => $customer,
            'attachment'       => $this->addAttachmentsData(),
        );

        if($isB2BAvailable && !empty($billingAddress['organization_name'])){
            $aUserData['customer']['type'] = 'organization';
        }

        return $aUserData;
    }

    /**
     * Get user country ISO2
     *
     * @return string|null
     * @throws oxSystemComponentException
     */
    public function getUserCountryISO2()
    {
        // always reset cache for tests
        if (defined('OXID_PHP_UNIT')) {
            $this->_sUserCountryISO2 = null;
        }

        if ($this->_sUserCountryISO2 === null) {
            $oCountry = oxNew('oxcountry');
            $oCountry->load($this->getFieldData('oxcountryid'));
            if ($oCountry->exists()) {
                $this->_sUserCountryISO2 = $oCountry->getFieldData('oxisoalpha2');
            }
        }

        return $this->_sUserCountryISO2;
    }

    /**
     * Set user countryId
     * @return string country ISO alfa2
     * @throws oxSystemComponentException
     */
    public function resolveCountry()
    {
        $oCountry    = $this->getKlarnaDeliveryCountry();
        $sCountryISO = $oCountry->getFieldData('oxisoalpha2');
        oxRegistry::getSession()->setVariable('sCountryISO', $sCountryISO);

        return strtoupper($sCountryISO);
    }

    /**
     * @return object|oxCountry
     * @throws oxSystemComponentException
     */
    public function getKlarnaDeliveryCountry()
    {
        $oCountry = oxNew('oxCountry');
        // KCO
        if (KlarnaUtils::isKlarnaCheckoutEnabled()) {
            if (!($sCountryISO = oxRegistry::getSession()->getVariable('sCountryISO'))) {

                if (!($sCountryId = $this->getFieldData('oxcountryid'))) {
                    $sCountryISO = KlarnaUtils::getShopConfVar('sKlarnaDefaultCountry');
                    $sCountryId  = $oCountry->getIdByCode($sCountryISO);
                }

            } else {
                $sCountryId = $oCountry->getIdByCode($sCountryISO);
            }
            $this->oxuser__oxcountryid = new oxField($sCountryId, oxfield::T_RAW);

        }


        // KP
        if (KlarnaUtils::isKlarnaPaymentsEnabled()) {
            if (!($sCountryId = $this->getFieldData('oxcountryid'))) {
                $sCountryISO = KlarnaUtils::getShopConfVar('sKlarnaDefaultCountry');
                $sCountryId  = $oCountry->getIdByCode($sCountryISO);
            }
        }
        $oCountry->load($sCountryId);

        return $oCountry;
    }

    /**
     * @return string
     * @throws oxSystemComponentException
     */
    public function getCountryISO()
    {
        if ($this->_countryISO)
            return $this->_countryISO;
        else
            return $this->_countryISO = $this->resolveCountry();
    }

    /**
     * @param $sCountryISO
     * @return string user locale
     */
    public function resolveLocale($sCountryISO)
    {
        return KlarnaUtils::resolveLocale($sCountryISO);
    }

    /**
     * Check if user exists by its email, allow users with passwords as well, skip admin
     * @param string $sEmail
     * @return Klarna_oxUser -1 cant use this email, 0 no user found, 1 user loaded
     * @throws oxConnectionException
     */
    public function loadByEmail($sEmail)
    {
        if ($this->_type == self::LOGGED_IN)
            return $this;

        $exists = false;
        if ($sEmail) {
            $oDb = oxDb::getDb();
            $sQ  = "SELECT `oxid` FROM `oxuser` WHERE `oxusername` = " . $oDb->quote($sEmail);
            if (!oxRegistry::getConfig()->getConfigParam('blMallUsers')) {
                $sQ .= " AND `oxshopid` = " . $oDb->quote(oxRegistry::getConfig()->getShopId());
            }
            $sId    = $oDb->getOne($sQ);
            $exists = $this->load($sId);
        }

        if ($exists) {
            if (empty($this->oxuser__oxpassword->value)) {
                $this->_type = self::NOT_REGISTERED;
            } else {
                $this->_type = self::REGISTERED;
            }
        } else {
            $this->_type = self::NOT_EXISTING;
            $this->setFakeUserId();
        }

        if(empty($this->oxuser__oxrights->value)){
            $this->oxuser__oxrights = new oxField('user');
        }

        return $this;
    }

    /**
     *
     */
    protected function setFakeUserId()
    {
        if (oxRegistry::getSession()->hasVariable('sFakeUserId')) {
            $this->setId(oxRegistry::getSession()->getVariable('sFakeUserId'));
        } else {
            $this->setId();
            oxRegistry::getSession()->setVariable('sFakeUserId', $this->getId());
        }
    }

    /**
     * @return int
     */
    public function kl_getType()
    {
        return $this->_type;
    }

    /**
     * @param int $iType
     */
    public function kl_setType($iType)
    {
        $this->_type = $iType;
    }

    /**
     * @return bool
     */
    public function isFake()
    {

        return ($this->kl_getType() && $this->kl_getType() !== self::LOGGED_IN) || !$this->oxuser__oxpassword->value;
    }

    /**
     * Checks if we can save user data to the database
     * @return bool
     */
    public function isWritable()
    {
        return $this->_type !== self::REGISTERED;
    }

    /**
     * @return bool
     */
    public function isCreatable()
    {
        return $this->_type == self::NOT_EXISTING || $this->_type == self::NOT_REGISTERED;
    }

    /**
     * Saves delivery address in the database
     *
     * @param $aDelAddress array Klarna delivery_address
     * @return void
     * @throws oxSystemComponentException
     * @throws oxException
     */
    public function updateDeliveryAddress($aDelAddress)
    {
        $oAddress = $this->buildAddress($aDelAddress);

        if ($oAddress->isValid()) {
            if(!$sAddressOxid = $oAddress->klExists()){
                $sAddressOxid = $oAddress->save();
                if ($this->isFake()) {
                    $oAddress->oxaddress__kltemporary = new Field(1, Field::T_RAW);
                }
            }
            $this->updateSessionDeliveryAddressId($sAddressOxid);
        }
    }

    /**
     * @codeCoverageIgnore
     * @param $aDelAddress
     * @return object
     */
    protected function buildAddress($aDelAddress)
    {
        $oAddress = oxNew('oxAddress');
        $oAddress->setId();
        $oAddress->assign($aDelAddress);

        $oAddress->oxaddress__oxuserid  = new oxField($this->getId(), oxField::T_RAW);
        $oAddress->oxaddress__oxcountry = $this->getUserCountry($oAddress->oxaddress__oxcountryid->value);

        return $oAddress;
    }

    /**
     * For Fake user. Replace session oxAddress ID, remove old address from DB
     * @param $sAddressOxid
     * @throws oxSystemComponentException
     */
    public function updateSessionDeliveryAddressId($sAddressOxid = null)
    {
        $oSession = oxRegistry::getSession();
        // keep only one address record for fake user, remove old
        if ($this->isFake() && $oSession->hasVariable('deladrid')) {
            $this->clearDeliveryAddress();
        }
        if ($sAddressOxid) {
            $oSession->setVariable('deladrid', $sAddressOxid);
            oxRegistry::getSession()->setVariable('blshowshipaddress', 1);
        }
    }

    /**
     * Remove delivery address from session and database
     *
     * @return void
     * @throws oxSystemComponentException
     */
    public function clearDeliveryAddress()
    {
        $oAddress = oxNew('oxAddress');
        $oAddress->load(oxRegistry::getSession()->getVariable('deladrid'));
        oxRegistry::getSession()->setVariable('deladrid', null);
        oxRegistry::getSession()->setVariable('blshowshipaddress', 0);
        if ($oAddress->isTemporary()){
            $oAddress->delete();
        }
    }

    /**
     * @return string currency ISO for user country
     * @throws oxSystemComponentException
     */
    public function getKlarnaPaymentCurrency()
    {
        $country2currency = KlarnaConsts::getCountry2CurrencyArray();
        $cur              = $this->resolveCountry();
        if (isset($country2currency[$cur])) {

            return $country2currency[$cur];
        }
    }

    /**
     * @return null|oxAddress
     * @throws oxSystemComponentException
     */
    public static function getDelAddressInfo()
    {
        $oDelAdress            = null;
        $blShowShippingAddress = (bool)oxRegistry::getSession()->getVariable('blshowshipaddress');
        if (!($soxAddressId = oxRegistry::getConfig()->getRequestParameter('deladrid'))) {
            $soxAddressId = oxRegistry::getSession()->getVariable('deladrid');
        }
        if ($soxAddressId && $blShowShippingAddress) {
            $oDelAdress = oxNew('oxaddress');
            $oDelAdress->load($soxAddressId);

            //get delivery country name from delivery country id
            if ($oDelAdress->oxaddress__oxcountryid->value && $oDelAdress->oxaddress__oxcountryid->value != -1) {
                $oCountry = oxNew('oxcountry');
                $oCountry->load($oDelAdress->oxaddress__oxcountryid->value);
                $oDelAdress->oxaddress__oxcountry = clone $oCountry->oxcountry__oxtitle;
            }
        }

        return $oDelAdress;
    }

    /**
     *
     * @throws oxSystemComponentException
     */
    public function changeUserData($sUser, $sPassword, $sPassword2, $aInvAddress, $aDelAddress)
    {
        parent::changeUserData($sUser, $sPassword, $sPassword2, $aInvAddress, $aDelAddress);
        if (KlarnaUtils::isKlarnaCheckoutEnabled()) {
            oxRegistry::getSession()->setVariable('sCountryISO', $this->getUserCountryISO2());
        }
    }

    /**
     * @return array
     * @throws oxSystemComponentException
     */
    public function addAttachmentsData()
    {
        if (!$this->isFake()) {
            $emd = $this->getEMD();
            if (!empty($emd)) {
                return array(
                    'content_type' => 'application/vnd.klarna.internal.emd-v2+json',
                    'body'         => json_encode($emd),
                );
            }
        }
    }

    /**
     * @codeCoverageIgnore
     */
    protected function getEMD()
    {
        $klarnaEmd = new Klarna_EMD();

        return $klarnaEmd->getAttachments($this);
    }

    /**
     * @return mixed
     * @throws oxSystemComponentException
     */
    public function save()
    {
        $result = parent::save();
        if ($result && KlarnaUtils::isKlarnaCheckoutEnabled()) {
            oxRegistry::getSession()->setVariable('sCountryISO', $this->getUserCountryISO2());
        }

        return $result;
    }

    /**
     * @return mixed
     */
    public function logout()
    {
        $result = parent::logout();
        if ($result && !$this->isAdmin()) {
            KlarnaUtils::fullyResetKlarnaSession();
        }

        return $result;
    }

    /**
     *
     * @param $sUser
     * @param $sPassword
     * @param bool $blCookie
     */
    public function login($sUser, $sPassword, $blCookie = false)
    {
        $result = parent::login($sUser, $sPassword, $blCookie);

        if (KlarnaUtils::getKlarnaModuleMode() == KlarnaConsts::MODULE_MODE_KCO) {
            oxRegistry::getSession()->setVariable(
                'sCountryISO',
                $this->getUserCountryISO2()
            );
            oxRegistry::getSession()->deleteVariable('klarna_checkout_user_email');
            $this->_type = self::LOGGED_IN;
        }

        return $result;
    }

    /**
     * @return int
     */
    public function kl_checkUserType()
    {
        if($this->getId() === oxRegistry::getSession()->getVariable('usr')){

            return $this->_type = self::LOGGED_IN;
        }

        return $this->_type = self::NOT_REGISTERED;
    }
}
