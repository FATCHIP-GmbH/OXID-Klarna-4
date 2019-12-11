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

class KlarnaUtils
{
    /**
     * @param null $email
     * @return oxUser
     * @throws oxConnectionException
     * @throws oxSystemComponentException
     */
    public static function getFakeUser($email = null)
    {
        $oUser = oxNew('oxuser');
        $oUser->loadByEmail($email);

        $sCountryISO = oxRegistry::getSession()->getVariable('sCountryISO');
        if ($sCountryISO) {
            $oCountry   = oxNew('oxCountry');
            $sCountryId = $oCountry->getIdByCode($sCountryISO);
            $oCountry->load($sCountryId);
            $oUser->oxuser__oxcountryid = new oxField($sCountryId);
            $oUser->oxuser__oxcountry   = new oxField($oCountry->oxcountry__oxtitle->value);
        }
        oxRegistry::getConfig()->setUser($oUser);

        if ($email) {
            oxRegistry::getSession()->setVariable('klarna_checkout_user_email', $email);
        }

        return $oUser;
    }

    /**
     * @param $name
     * @return mixed
     */
    public static function getShopConfVar($name)
    {
        $config = oxRegistry::getConfig();
        $shopId = $config->getShopId();

        return $config->getShopConfVar($name, $shopId, 'klarna');
    }

    /**
     * @param $sCountryId
     * @return mixed
     */
    public static function getCountryISO($sCountryId)
    {
        $oCountry = oxNew('oxCountry');
        $oCountry->load($sCountryId);

        return $oCountry->getFieldData('oxisoalpha2');
    }

    /**
     * @return mixed
     */
    public static function getKlarnaModuleMode()
    {
        return self::getShopConfVar('sKlarnaActiveMode');
    }

    /**
     * @return bool
     */
    public static function isKlarnaPaymentsEnabled()
    {
        return self::getKlarnaModuleMode() === KlarnaConsts::MODULE_MODE_KP;
    }

    /**
     *
     */
    public static function isKlarnaCheckoutEnabled()
    {
        /** @var oxPayment $oPayment */
        $oPayment = oxNew('oxpayment');
        $oPayment->load('klarna_checkout');
        $klarnaActiveInOxid = $oPayment->oxpayments__oxactive->value == 1;
        $ssl                = oxRegistry::getConfig()->getConfigParam('sSSLShopURL');

        return KlarnaUtils::getKlarnaModuleMode() === KlarnaConsts::MODULE_MODE_KCO && $klarnaActiveInOxid && isset($ssl);
    }

    /**
     * @param null $iLang
     * @return oxCountryList
     */
    public static function getActiveShopCountries($iLang = null)
    {
        /** @var oxCountryList $oCountryList */
        $oCountryList = oxNew('oxcountrylist');
        $oCountryList->loadActiveCountries($iLang);

        return $oCountryList;
    }

    /**
     * @param null $sCountryISO
     * @return array|mixed
     */
    public static function getAPICredentials($sCountryISO = null)
    {
        if (!$sCountryISO) {
            $sCountryISO = oxRegistry::getSession()->getVariable('sCountryISO');
        }

        if (!$aCredentials = KlarnaUtils::getShopConfVar('aKlarnaCreds_' . $sCountryISO)) {
            $aCredentials = array(
                'mid'      => KlarnaUtils::getShopConfVar('sKlarnaMerchantId'),
                'password' => KlarnaUtils::getShopConfVar('sKlarnaPassword'),
            );
        }

        return $aCredentials;
    }

    /**
     * @param $sCountryISO
     * @param bool $filterKcoList
     * @return bool
     * @throws oxSystemComponentException
     */
    public static function isCountryActiveInKlarnaCheckout($sCountryISO, $filterKcoList = true)
    {
        if ($sCountryISO === null) {
            return true;
        }

        /** @var oxCountryList $activeKlarnaCountries */
        $activeKlarnaCountries = oxNew('oxcountrylist');
        $activeKlarnaCountries->loadActiveKlarnaCheckoutCountries($filterKcoList);
        if (!count($activeKlarnaCountries)) {
            return false;
        }
        foreach ($activeKlarnaCountries as $country) {
            if (strtoupper($sCountryISO) == $country->oxcountry__oxisoalpha2->value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     * @throws oxSystemComponentException
     */
    public static function isNonKlarnaCountryActive()
    {
        $activeNonKlarnaCountries = oxNew('oxcountrylist');
        $activeNonKlarnaCountries->loadActiveNonKlarnaCheckoutCountries();
        if (count($activeNonKlarnaCountries) > 0) {
            return true;
        }

        return false;
    }

    /**
     * @param int|null $iLang
     * @return oxCountryList
     * @throws oxSystemComponentException
     */
    public static function getKlarnaGlobalActiveShopCountries($iLang = null)
    {
        $oCountryList = oxNew('oxcountrylist');
        $oCountryList->loadActiveKlarnaCheckoutCountries($iLang);

        return $oCountryList;

    }

    /**
     * @return array
     *
     */
    public static function getKlarnaGlobalActiveShopCountryISOs($iLang = null)
    {
        $oCountryList = oxNew('oxcountrylist');
        $oCountryList->loadActiveKlarnaCheckoutCountries($iLang);

        $result = array();
        foreach ($oCountryList as $country) {
            $result[] = $country->oxcountry__oxisoalpha2->value;
        }

        return $result;
    }

    /**
     * @param null $iLang
     * @return klarna_oxcountrylist|object|oxCountryList
     * @throws oxSystemComponentException
     */
    public static function getAllActiveKCOGlobalCountryList($iLang = null)
    {
        $oCountryList = oxNew('oxcountrylist');
        $oCountryList->loadActiveKCOGlobalCountries($iLang);

        return $oCountryList;
    }

    /**
     * @param BasketItem $oItem
     * @param $isOrderMgmt
     * @return array
     */
    public static function calculateOrderAmountsPricesAndTaxes($oItem, $isOrderMgmt)
    {
        $quantity           = self::parseFloatAsInt($oItem->getAmount());
        $regular_unit_price = 0;
        $basket_unit_price  = 0;

        if (!$oItem->isBundle()) {
            $regUnitPrice = $oItem->getRegularUnitPrice();
            if ($isOrderMgmt) {
                $unitPrice = $oItem->getArticle()->getUnitPrice();
            } else {
                $unitPrice = $oItem->getUnitPrice();
            }

            $regular_unit_price = self::parseFloatAsInt($regUnitPrice->getBruttoPrice() * 100);
            $basket_unit_price  = self::parseFloatAsInt($unitPrice->getBruttoPrice() * 100);
        }

        $total_discount_amount = ($regular_unit_price - $basket_unit_price) * $quantity;
        $total_amount          = $basket_unit_price * $quantity;

        if ($oItem->isBundle()) {
            $tax_rate = self::parseFloatAsInt($oItem->getUnitPrice()->getVat() * 100);
        } else {
            $tax_rate = self::parseFloatAsInt($oItem->getUnitPrice()->getVat() * 100);
        }
//        $total_tax_amount = self::parseFloatAsInt($oItem->getPrice()->getVatValue() * 100);
        $total_tax_amount = self::parseFloatAsInt(
            $total_amount - round($total_amount / ($tax_rate / 10000 + 1), 0)
        );

        $quantity_unit = 'pcs';

        return array($quantity, $regular_unit_price, $total_amount, $total_discount_amount, $tax_rate, $total_tax_amount, $quantity_unit);
    }

    /**
     * @param $number
     *
     * @return int
     */
    public static function parseFloatAsInt($number)
    {
        return (int)(oxRegistry::getUtils()->fRound($number));
    }

    /**
     * @param oxCategory $oCat
     * @param array $aCategories
     * @return array
     */
    public static function getSubCategoriesArray(oxCategory $oCat, $aCategories = array())
    {
        $aCategories[] = $oCat->getTitle();

        if ($oParentCat = $oCat->getParentCategory()) {
            return self::getSubCategoriesArray($oParentCat, $aCategories);
        }

        return $aCategories;
    }

    /**
     * @param $sCountryISO
     * @return string
     */
    public static function resolveLocale($sCountryISO)
    {
        $lang = oxRegistry::getLang()->getLanguageAbbr();
        oxRegistry::getSession()->setVariable('klarna_iframe_lang', $lang);

        return strtolower($lang) . '-' . strtoupper($sCountryISO);
    }

    /**
     * @return bool
     */
    public static function is_ajax()
    {
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && (strtolower(getenv('HTTP_X_REQUESTED_WITH')) === 'xmlhttprequest'));
    }

    /**
     *
     */
    public static function fullyResetKlarnaSession()
    {
        oxRegistry::getSession()->deleteVariable('paymentid');
        oxRegistry::getSession()->deleteVariable('klarna_checkout_order_id');
        oxRegistry::getSession()->deleteVariable('amazonOrderReferenceId');
        oxRegistry::getSession()->deleteVariable('klarna_checkout_user_email');
        oxRegistry::getSession()->deleteVariable('externalCheckout');
        oxRegistry::getSession()->deleteVariable('sAuthToken');
        oxRegistry::getSession()->deleteVariable('klarna_session_data');
        oxRegistry::getSession()->deleteVariable('finalizeRequired');
        oxRegistry::getSession()->deleteVariable('sCountryISO');
//        oxRegistry::getSession()->deleteVariable('oFakeKlarnaUser');
        oxRegistry::getSession()->deleteVariable('sFakeUserId');
    }

    /**
     * @param $text
     * @return string|null
     */
    public static function stripHtmlTags($text)
    {
        $result = preg_replace('/<(\/)?[a-z]+[^<]*>/', '', $text);

        return $result ?: null;
    }

    /**
     * @param $iso3
     * @return false|string
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     */
    public static function getCountryIso2fromIso3($iso3)
    {
        $sql = 'SELECT oxisoalpha2 FROM oxcountry WHERE oxisoalpha3 = ?';

        return oxDb::getDb(oxDb::FETCH_MODE_ASSOC)->getOne($sql, array($iso3));
    }
}
