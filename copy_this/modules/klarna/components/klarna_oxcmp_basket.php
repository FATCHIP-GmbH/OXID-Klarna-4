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
 * Basket component
 *
 * @package Klarna
 * @extend OxCmp_basket
 */
class Klarna_OxCmp_basket extends Klarna_OxCmp_basket_parent
{
    /**
     * Redirect controller name
     *
     * @var string
     */
    protected $_sRedirectController = 'klarna_express';

    /**
     * Executing action from details page
     */
    public function actionKlarnaExpressCheckoutFromDetailsPage()
    {
        // trows exception if adding item to basket fails
        $this->tobasket();

        $oConfig = oxRegistry::getConfig();
        oxRegistry::getUtils()->redirect(
            $oConfig->getShopSecureHomeUrl() . 'cl=' . $this->_sRedirectController . '',
            false,
            302
        );
    }

    /**
     * @param null $sProductId
     * @param null $dAmount
     * @param null $aSel
     * @param null $aPersParam
     * @param bool $blOverride
     */
    public function changebasket($sProductId = null, $dAmount = null, $aSel = null, $aPersParam = null, $blOverride = true)
    {
        parent::changebasket($sProductId, $dAmount, $aSel, $aPersParam, $blOverride);

        if (KlarnaUtils::isKlarnaCheckoutEnabled() && oxRegistry::getSession()->hasVariable('klarna_checkout_order_id')) {
            try {
                $this->updateKlarnaOrder();
            } catch (oxException $e) {
                $e->debugOut();
                KlarnaUtils::fullyResetKlarnaSession();
            }
        }
    }

    /**
     * @param null $sProductId
     * @param null $dAmount
     * @param null $aSel
     * @param null $aPersParam
     * @param bool $blOverride
     */
    public function tobasket($sProductId = null, $dAmount = null, $aSel = null, $aPersParam = null, $blOverride = false)
    {
        parent::tobasket($sProductId, $dAmount, $aSel, $aPersParam, $blOverride);

        if (KlarnaUtils::isKlarnaCheckoutEnabled() && oxRegistry::getSession()->hasVariable('klarna_checkout_order_id')) {
            try {
                $this->updateKlarnaOrder();
            } catch (oxException $e) {
                $e->debugOut();
                KlarnaUtils::fullyResetKlarnaSession();

            }
        }
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
        $orderLines = oxRegistry::getSession()->getBasket()->getKlarnaOrderLines();
        $oClient    = $this->getKlarnaCheckoutClient();

        return $oClient->createOrUpdateOrder(json_encode($orderLines), $oClient->getOrderId());
    }

    /**
     * @return KlarnaCheckoutClient|KlarnaClientBase
     */
    protected function getKlarnaCheckoutClient()
    {
        return KlarnaCheckoutClient::getInstance();
    }

    /**
     * @return KlarnaOrderManagementClient|KlarnaClientBase
     */
    protected function getKlarnaOrderClient()
    {
        return KlarnaOrderManagementClient::getInstance();
    }

}
