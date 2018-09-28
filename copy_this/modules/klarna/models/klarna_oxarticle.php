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

class klarna_oxarticle extends klarna_oxarticle_parent
{
    /**
     * Array of Klarna_PClass objects
     * @var array
     */
    protected $_aPClassList = null;

    /**
     * Show monthly cost?
     * @var bool
     */
    protected $_blShowMonthlyCost = null;

    /**
     * Check if article stock is good for expire check
     *
     * @return bool
     */
    public function isGoodStockForExpireCheck()
    {
        return (
            $this->getFieldData('oxstock') == 0
            && ($this->getFieldData('oxstockflag') == 1 || $this->getFieldData('oxstockflag') == 4)
        );
    }


    /**
     * Returning stock items by article number
     *
     * @param $sArtNum
     * @return object oxarticle
     * @throws oxConnectionException
     */
    public function klarna_loadByArtNum($sArtNum)
    {
        $sArticleTable = $this->getViewName();
        if (strlen($sArtNum) === 64) {
            $sArtNum   .= '%';
            $sSQL      = "SELECT art.oxid FROM {$sArticleTable} art WHERE art.OXACTIVE=1 AND art.OXARTNUM LIKE \"{$sArtNum}\"";
            $articleId = oxDb::getDb(ADODB_FETCH_ASSOC)->getOne($sSQL);
        } else {
            if (KlarnaUtils::getShopConfVar('blKlarnaEnableAnonymization')) {
                $sSQL = "SELECT oxartid 
                    FROM kl_anon_lookup 
                    JOIN {$sArticleTable} art
                    ON art.OXID=oxartid
                    WHERE art.OXACTIVE=1 AND klartnum = ?";
            } else {
                $sSQL = "SELECT art.oxid FROM {$sArticleTable} art WHERE art.OXACTIVE=1 AND art.OXARTNUM = ?";
            }
            $articleId = oxDb::getDb(ADODB_FETCH_ASSOC)->getOne($sSQL, array($sArtNum));
        }

        return $this->load($articleId);
    }


    /**
     * Return anonymized or regular product title
     *
     * @param null $counter
     * @param null $iOrderLang
     * @return mixed
     * @throws oxSystemComponentException
     */
    public function kl_getOrderArticleName($counter = null, $iOrderLang = null)
    {

        if (KlarnaUtils::getShopConfVar('blKlarnaEnableAnonymization')) {
            if ($iOrderLang) {
                $lang = strtoupper(oxRegistry::getLang()->getLanguageAbbr($iOrderLang));
            } else {
                $lang = strtoupper(oxRegistry::getLang()->getLanguageAbbr());
            }

            $name = KlarnaUtils::getShopConfVar('sKlarnaAnonymizedProductTitle_' . $lang);

            return html_entity_decode("$name $counter", ENT_QUOTES);
        }

        $name = $this->getFieldData('oxtitle');

        if (!$name && $parent = $this->getParentArticle()) {
            if ($iOrderLang) {
                $this->loadInLang($iOrderLang, $parent->getId());
            } else {
                $this->load($parent->getId());
            }
            $name = $this->getFieldData('oxtitle');
        }

        return html_entity_decode($name, ENT_QUOTES) ?: '(no title)';
    }

    /**
     * @return array
     */
    public function kl_getArticleUrl()
    {
        if (KlarnaUtils::getShopConfVar('blKlarnaSendProductUrls') === true &&
            KlarnaUtils::getShopConfVar('blKlarnaEnableAnonymization') === false) {

            $link = $this->getLink(null, true);

            $link = preg_replace('/\?.+/', '', $link);

            return $link ?: null;
        }

        return null;
    }

    /**
     * @return mixed
     */
    public function kl_getArticleImageUrl()
    {
        if (KlarnaUtils::getShopConfVar('blKlarnaSendImageUrls') === true &&
            KlarnaUtils::getShopConfVar('blKlarnaEnableAnonymization') === false) {

            $link = $this->getPictureUrl();
        }

        return $link ?: null;
    }

    /**
     * @return null
     */
    public function kl_getArticleEAN()
    {
        if (KlarnaUtils::getShopConfVar('blKlarnaEnableAnonymization') === false) {
            $ean = $this->getFieldData('oxean');
        }

        return $ean ?: null;
    }

    /**
     * @return null
     */
    public function kl_getArticleMPN()
    {
        if (KlarnaUtils::getShopConfVar('blKlarnaEnableAnonymization') === false) {
            $mpn = $this->getFieldData('oxmpn');
        }

        return $mpn ?: null;
    }

    /**
     * @return string
     */
    public function kl_getArticleCategoryPath()
    {
        $sCategories = null;
        if (KlarnaUtils::getShopConfVar('blKlarnaEnableAnonymization') === false) {
            $oCat = $this->getCategory();

            if ($oCat) {
                $aCategories = KlarnaUtils::getSubCategoriesArray($oCat);
                $sCategories = html_entity_decode(implode(' > ', array_reverse($aCategories)), ENT_QUOTES);
            }

        }

        return $sCategories ?: null;
    }

    /**
     * @param oxArticle $oItem
     * @return string|null
     */
    public function kl_getArticleManufacturer()
    {
        if (KlarnaUtils::getShopConfVar('blKlarnaEnableAnonymization') === false) {
            if (!$oManufacturer = $this->getManufacturer())
                return null;
        }

        return html_entity_decode($oManufacturer->getTitle(), ENT_QUOTES) ?: null;
    }

}
