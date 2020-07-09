<?php

use OxidEsales\Eshop\Core\Registry;

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

class klarna_oxbasket extends klarna_oxbasket_parent
{

    /**
     * Array of articles that have long delivery term so Klarna cannot be used to pay for them
     *
     * @var array
     */
    protected $_aPreorderArticles = array();

    /**
     * @var string
     */
    protected $_orderHash = '';

    /**
     * Checkout configuration
     * @var array
     */
    protected $_aCheckoutConfig;

    /**
     * Klarna Order Lines
     * @var array
     */
    protected $klarnaOrderLines;

    /**
     * @var int
     */
    protected $klarnaOrderLang;

    /**
     * Format products for Klarna checkout
     *
     * @param bool $orderMgmtId
     * @return array
     * @throws oxArticleException
     * @throws oxArticleInputException
     * @throws oxNoArticleException
     * @throws oxSystemComponentException
     * @internal param $orderData
     * @throws oxConnectionException
     * @throws KlarnaBasketTooLargeException
     */
    public function getKlarnaOrderLines($orderMgmtId = null)
    {
        $this->calculateBasket(true);
        $this->klarnaOrderLines = array();
        $this->_calcItemsPrice();

        $iOrderLang = $this->getOrderLang($orderMgmtId);

        $aItems = $this->getContents();
        usort($aItems, array($this, 'sortOrderLines'));

        $counter = 0;
        /* @var oxBasketItem $oItem */
        foreach ($aItems as $oItem) {
            $counter++;

            list($quantity,
                $regular_unit_price,
                $total_amount,
                $total_discount_amount,
                $tax_rate,
                $total_tax_amount,
                $quantity_unit) = KlarnaUtils::calculateOrderAmountsPricesAndTaxes($oItem, $orderMgmtId);

            /** @var oxArticle | oxBasketItem | klarna_oxarticle $oArt */
            $oArt = $oItem->getArticle();
            if (!$oArt instanceof oxArticle) {
                $oArt = $oArt->getArticle();
            }

            if ($iOrderLang) {
                $oArt->loadInLang($iOrderLang, $oArt->getId());
            }

            $aProcessedItem = array(
                "type"             => "physical",
                'reference'        => $this->getArtNum($oArt),
                'quantity'         => $quantity,
                'unit_price'       => $regular_unit_price,
                'tax_rate'         => $tax_rate,
                "total_amount"     => $total_amount,
                "total_tax_amount" => $total_tax_amount,
            );

            if ($quantity_unit !== '') {
                $aProcessedItem["quantity_unit"] = $quantity_unit;
            }

            if ($total_discount_amount !== 0) {
                $aProcessedItem["total_discount_amount"] = $total_discount_amount;
            }

            $aProcessedItem['name'] = $oArt->kl_getOrderArticleName($counter, $iOrderLang);
            if (KlarnaUtils::getShopConfVar('blKlarnaEnableAnonymization') === false) {
                $aProcessedItem['product_url']         = $oArt->kl_getArticleUrl();
                $aProcessedItem['image_url']           = $oArt->kl_getArticleImageUrl();
                $aProcessedItem['product_identifiers'] = array(
                    'category_path'            => $oArt->kl_getArticleCategoryPath(),
                    'global_trade_item_number' => $oArt->kl_getArticleEAN(),
                    'manufacturer_part_number' => $oArt->kl_getArticleMPN(),
                    'brand'                    => $oArt->kl_getArticleManufacturer(),
                );
            }

            $this->klarnaOrderLines[] = $aProcessedItem;
        }

        $this->_addServicesAsProducts($orderMgmtId);
        $this->_orderHash = md5(json_encode($this->klarnaOrderLines));

        $totals = $this->calculateTotals($this->klarnaOrderLines);

        $aOrderLines      = array(
            'order_lines'      => $this->klarnaOrderLines,
            'order_amount'     => $totals['total_order_amount'],
            'order_tax_amount' => $totals['total_order_tax_amount'],
        );

        $this->_orderHash = md5(json_encode($aOrderLines));

        return $aOrderLines;
    }

    protected function getOrderLang($orderMgmtId)
    {
        $iOrderLang = null;
        if ($orderMgmtId) {
            $oOrder = oxNew('oxOrder');
            $oOrder->load($orderMgmtId);
            $iOrderLang = $oOrder->getFieldData('oxlang');
        }

        return $iOrderLang;
    }

    /**
     * @param $aProcessedItems
     * @return array
     * @throws KlarnaBasketTooLargeException
     */
    protected function calculateTotals($aProcessedItems)
    {
        $total_order_amount = $total_order_tax_amount = 0;
        foreach ($aProcessedItems as $item) {
            $total_order_amount     += $item['total_amount'];
            $total_order_tax_amount += $item['total_tax_amount'];
        }

        if ($total_order_amount > 100000000) {
            throw new KlarnaBasketTooLargeException('KL_ORDER_AMOUNT_TOO_HIGH');
        }

        return array(
            'total_order_amount'     => $total_order_amount,
            'total_order_tax_amount' => $total_order_tax_amount,
        );
    }


    /**
     * Add OXID additional payable services as products to array
     * @param bool $orderMgmtId
     * @return void
     * @throws oxSystemComponentException
     */
    protected function _addServicesAsProducts($orderMgmtId = false)
    {
        $iLang  = null;
        $oOrder = null;
        if ($orderMgmtId) {
            $oOrder = oxNew('oxorder');
            $oOrder->load($orderMgmtId);
            $iLang = $oOrder->getFieldData('oxlang');
        }

        if (KlarnaUtils::isKlarnaPaymentsEnabled() || $oOrder) {
            $oDelivery = parent::getCosts('oxdelivery');
//            if ($this->_isServicePriceSet($oDelivery)) {
            $oDeliverySet = oxNew('oxDeliverySet');
            if ($iLang) {
                $oDeliverySet->loadInLang($iLang, $this->getShippingId());
            } else {
                $oDeliverySet->load($this->getShippingId());
            }

            $this->klarnaOrderLines[] = $this->getKlarnaPaymentDelivery($oDelivery, $oOrder, $oDeliverySet);
//            }
        }
        $this->_addDiscountsAsProducts($oOrder, $iLang);
        $this->_addGiftWrappingCost($iLang);
        $this->_addGiftCardProducts($iLang);
//      $this->_addServicePaymentCost();
//      $this->_addTrustedShopsExcellenceFee();

    }

    protected function _addGiftWrappingCost($iLang = null)
    {
        $oWrappingCost = $this->getCosts('oxwrapping');
        if (($oWrappingCost && $oWrappingCost->getPrice())) {
            $unit_price = KlarnaUtils::parseFloatAsInt($oWrappingCost->getBruttoPrice() * 100);

            if (!$this->is_fraction($this->getOrderVatAverage())) {
                $tax_rate = KlarnaUtils::parseFloatAsInt($this->getOrderVatAverage() * 100);
            } else {
                $tax_rate = KlarnaUtils::parseFloatAsInt($oWrappingCost->getVat() * 100);
            }

            $this->klarnaOrderLines[] = array(
                'type'                  => 'physical',
                'reference'             => 'SRV_WRAPPING',
                'name'                  => html_entity_decode(oxRegistry::getLang()->translateString('KL_GIFT_WRAPPING_TITLE', $iLang), ENT_QUOTES),
                'quantity'              => 1,
                'total_amount'          => $unit_price,
                'total_discount_amount' => 0,
                'total_tax_amount'      => KlarnaUtils::parseFloatAsInt(round($oWrappingCost->getVatValue() * 100, 0)),
                'unit_price'            => $unit_price,
                'tax_rate'              => $tax_rate,
            );
        }
    }

    /**
     * @param null $iLang
     */
    protected function _addGiftCardProducts($iLang = null)
    {
        /** @var oxPrice $oWrappingCost */
        $oGiftCardCost = $this->getCosts('oxgiftcard');
        if (($oGiftCardCost && $oGiftCardCost->getPrice())) {
            $unit_price = KlarnaUtils::parseFloatAsInt($oGiftCardCost->getBruttoPrice() * 100);
            $tax_rate   = KlarnaUtils::parseFloatAsInt($oGiftCardCost->getVat() * 100);

            $this->klarnaOrderLines[] = array(
                'type'                  => 'physical',
                'reference'             => 'SRV_GIFTCARD',
                'name'                  => html_entity_decode(oxRegistry::getLang()->translateString('KL_GIFT_CARD_TITLE', $iLang), ENT_QUOTES),
                'quantity'              => 1,
                'total_amount'          => $unit_price,
                'total_discount_amount' => 0,
                'total_tax_amount'      => KlarnaUtils::parseFloatAsInt($unit_price - round($unit_price / ($tax_rate / 10000 + 1), 0)),
                'unit_price'            => $unit_price,
                'tax_rate'              => $tax_rate,
            );
        }
    }

    /**
     * Add OXID additional discounts as products to array
     * @param null $oOrder
     * @param null $iLang
     * @return void
     * @throws oxSystemComponentException
     */
    protected function _addDiscountsAsProducts($oOrder = null, $iLang = null)
    {
        $oDiscount = $this->getVoucherDiscount();
        if ($this->_isServicePriceSet($oDiscount)) {
            $this->klarnaOrderLines[] = $this->_getKlarnaCheckoutVoucherDiscount($oDiscount, $iLang, $oOrder);
        }

        $oDiscount = $this->getOxDiscount();
        if ($oOrder) {
            $oDiscount = oxNew('oxPrice');
            $oDiscount->setBruttoPriceMode();

            $oDiscount->setPrice($oOrder->getFieldData('oxdiscount'));
        }
        if ($this->_isServicePriceSet($oDiscount)) {
            $this->klarnaOrderLines[] = $this->_getKlarnaCheckoutDiscount($oDiscount, $iLang, $oOrder);
        }
    }

    /**
     * Check if service is set and has brutto price
     * @param $oService
     *
     * @return bool
     */
    protected function _isServicePriceSet($oService)
    {
        return ($oService && $oService->getBruttoPrice() != 0);
    }

    /**
     * Returns delivery costs
     *
     * @return oxPrice
     * @throws oxSystemComponentException
     */
    protected function getOxDiscount()
    {
        $totalDiscount = oxNew('oxPrice');
        $totalDiscount->setBruttoPriceMode();
        $discounts = $this->getDiscounts();

        if (!is_array($discounts)) {
            return $totalDiscount;
        }

        foreach ($discounts as $discount) {
            if ($discount->sType == 'itm') {
                continue;
            }
            $totalDiscount->add($discount->dDiscount);
        }

        return $totalDiscount;
    }

    /**
     * Create klarna checkout product from delivery price
     *
     * @param oxPrice $oPrice
     *
     * @param bool $oOrder
     * @param oxDeliverySet|null $oDeliverySet
     * @return array
     */
    public function getKlarnaPaymentDelivery(oxPrice $oPrice, $oOrder = null, oxDeliverySet $oDeliverySet = null)
    {
        $unit_price = KlarnaUtils::parseFloatAsInt($oPrice->getBruttoPrice() * 100);
        $tax_rate   = KlarnaUtils::parseFloatAsInt($oPrice->getVat() * 100);

        $aItem = array(
            'type'                  => 'shipping_fee',
            'reference'             => 'SRV_DELIVERY',
            'name'                  => html_entity_decode($oDeliverySet->getFieldData('oxtitle'), ENT_QUOTES),
            'quantity'              => 1,
            'total_amount'          => $unit_price,
            'total_discount_amount' => 0,
            'total_tax_amount'      => KlarnaUtils::parseFloatAsInt($unit_price - round($unit_price / ($tax_rate / 10000 + 1), 0)),
            'unit_price'            => $unit_price,
            'tax_rate'              => $tax_rate,
        );

        if ($oOrder && $oOrder->isKCO()) {
            $aItem['reference'] = $oOrder->getFieldData('oxdeltype');
        }

        return $aItem;
    }

    /**
     * Create klarna checkout product from voucher discounts
     *
     * @param oxPrice $oPrice
     *
     * @param null $iLang
     * @return array
     */
    protected function _getKlarnaCheckoutVoucherDiscount(oxPrice $oPrice, $iLang = null)
    {
        $unit_price = -KlarnaUtils::parseFloatAsInt($oPrice->getBruttoPrice() * 100);
        $tax_rate   = KlarnaUtils::parseFloatAsInt($this->_oProductsPriceList->getProportionalVatPercent() * 100);

        $aItem = array(
            'type'             => 'discount',
            'reference'        => 'SRV_COUPON',
            'name'             => html_entity_decode(oxRegistry::getLang()->translateString('KL_VOUCHER_DISCOUNT', $iLang), ENT_QUOTES),
            'quantity'         => 1,
            'total_amount'     => $unit_price,
            'unit_price'       => $unit_price,
            'tax_rate'         => $tax_rate,
            'total_tax_amount' => KlarnaUtils::parseFloatAsInt($unit_price - round($unit_price / ($tax_rate / 10000 + 1), 0)),
        );

        return $aItem;
    }

    /**
     * Create klarna checkout product from non voucher discounts
     *
     * @param oxPrice $oPrice
     *
     * @param null $iLang
     * @return array
     */
    protected function _getKlarnaCheckoutDiscount(oxPrice $oPrice, $iLang = null)
    {
        $value = $oPrice->getBruttoPrice();
        $type = 'discount';
        $reference = 'SRV_DISCOUNT';
        $name = html_entity_decode(oxRegistry::getLang()->translateString('KL_DISCOUNT_TITLE', $iLang), ENT_QUOTES);
        if ($value < 0) {
            $type = 'surcharge';
            $reference = 'SRV_SURCHARGE';
            $name = html_entity_decode(oxRegistry::getLang()->translateString('TCKLARNA_SURCHARGE_TITLE', $iLang), ENT_QUOTES);
        }

        $unit_price = -KlarnaUtils::parseFloatAsInt( $value * 100);
        $tax_rate   = KlarnaUtils::parseFloatAsInt($this->getOrderVatAverage() * 100);

        $aItem = array(
            'type'             => $type,
            'reference'        => $reference,
            'name'             => $name,
            'quantity'         => 1,
            'total_amount'     => $unit_price,
            'unit_price'       => $unit_price,
            'tax_rate'         => $tax_rate,
            'total_tax_amount' => KlarnaUtils::parseFloatAsInt($unit_price - round($unit_price / ($tax_rate / 10000 + 1), 0)),
        );

        return $aItem;
    }


    /**
     * Original OXID method _calcDeliveryCost
     * @throws oxSystemComponentException
     */
    public function kl_calculateDeliveryCost()
    {
        if ($this->_oDeliveryPrice !== null) {
            return $this->_oDeliveryPrice;
        }
        $myConfig       = oxRegistry::getConfig();
        $oDeliveryPrice = oxNew('oxprice');

        if (oxRegistry::getConfig()->getConfigParam('blDeliveryVatOnTop')) {
            $oDeliveryPrice->setNettoPriceMode();
        } else {
            $oDeliveryPrice->setBruttoPriceMode();
        }

        // don't calculate if not logged in
        $oUser = $this->getBasketUser();

        if (!$oUser && !$myConfig->getConfigParam('blCalculateDelCostIfNotLoggedIn')) {
            return $oDeliveryPrice;
        }

        $fDelVATPercent = $this->getAdditionalServicesVatPercent();
        $oDeliveryPrice->setVat($fDelVATPercent);

        // list of active delivery costs
        $this->handleDeliveryCosts($myConfig,$oUser,$oDeliveryPrice,$fDelVATPercent);

        return $oDeliveryPrice;
    }

    protected function handleDeliveryCosts(oxConfig $myConfig, $oUser, oxPrice &$oDeliveryPrice, $fDelVATPercent)
    {
        // list of active delivery costs
        if ($myConfig->getConfigParam('bl_perfLoadDelivery')) {
            /** @var oxDeliveryList Create new oxDeliveryList to get proper content */
            $oDeliveryList = oxNew("oxDeliveryList");
            $aDeliveryList = $oDeliveryList->getDeliveryList(
                $this,
                $oUser,
                $this->_findDelivCountry(),
                $this->getShippingId()
            );

            if (count($aDeliveryList) > 0) {
                foreach ($aDeliveryList as $oDelivery) {
                    //debug trace
                    if ($myConfig->getConfigParam('iDebug') == 5) {
                        echo("Delivery Cost : " . $oDelivery->oxdelivery__oxtitle->value . "<br>");
                    }
                    $oDeliveryPrice->addPrice($oDelivery->getDeliveryPrice($fDelVATPercent));
                }
            }
        }
    }

    protected function _calcDeliveryCost()
    {
        if (KlarnaUtils::isKlarnaPaymentsEnabled()) {
            return $this->kl_calculateDeliveryCost();
        } else {
            return parent::_calcDeliveryCost();
        }
    }

    /**
     * Get average of order VAT
     * @return float
     */
    protected function getOrderVatAverage()
    {
        $vatAvg = ($this->getBruttoSum() / $this->getProductsNetPriceWithoutDiscounts() - 1) * 100;

        return number_format($vatAvg, 2);
    }

    /**
     * Returns sum of product netto prices
     * @return float
     */
    protected function getProductsNetPriceWithoutDiscounts()
    {
        $nettoSum = 0;

        if (!empty($this->_aBasketContents)) {
            foreach ($this->_aBasketContents as $oBasketItem) {
                $nettoSum += $oBasketItem->getPrice()->getNettoPrice();
            }
        }

        return $nettoSum;
    }

    /**
     * @param $oArt
     * @return bool|null|string
     * @throws oxConnectionException
     */
    protected function getArtNum($oArt)
    {
        $original = $oArt->oxarticles__oxartnum->value;
        if (KlarnaUtils::getShopConfVar('blKlarnaEnableAnonymization')) {
            $hash = md5($original);
            if (KlarnaUtils::getShopConfVar('iKlarnaValidation') != 0) {
                $this->addKlarnaAnonymousMapping($oArt->getId(), $hash);
            }

            return $hash;
        }

        return substr($original, 0, 64);
    }

    /**
     * @param $val
     * @return bool
     */
    protected function is_fraction($val)
    {
        return is_numeric($val) && fmod($val, 1);
    }

    /**
     * @codeIgnoreCoverage
     * @param $iLang
     */
    public function setKlarnaOrderLang($iLang)
    {
        $this->klarnaOrderLang = $iLang;
    }

    /**
     * @param oxBasketItem $a
     * @param oxBasketItem $b
     * @return int
     * @throws oxArticleException
     * @throws oxArticleInputException
     * @throws oxNoArticleException
     */
    protected function sortOrderLines(oxBasketItem $a, oxBasketItem $b)
    {
        $oArtA = $a->getArticle();
        if (!$oArtA instanceof oxArticle) {
            $oArtA = $oArtA->getArticle();
        }
        $oArtB = $b->getArticle();
        if (!$oArtB instanceof oxArticle) {
            $oArtB = $oArtB->getArticle();
        }
        if (round(hexdec($oArtA->getId()), 3) > round(hexdec($oArtB->getId()), 3)) {
            return 1;
        } else if (round(hexdec($oArtA->getId()), 3) < round(hexdec($oArtB->getId()), 3)) {
            return -1;
        }

        return 0;
    }

    /**
     * @param $artOxid
     * @param $anonArtNum
     * @throws oxConnectionException
     */
    protected function addKlarnaAnonymousMapping($artOxid, $anonArtNum)
    {
        $db = oxDb::getDb();

        $sql = "INSERT IGNORE INTO kl_anon_lookup(klartnum, oxartid) values(?,?)";
        $db->execute($sql, array($anonArtNum, $artOxid));
    }

    /**
     * Check if vouchers are still valid. Usually used in the ajax requests
     */
    public function klarnaValidateVouchers()
    {
        $this->_calcVoucherDiscount();
    }
}