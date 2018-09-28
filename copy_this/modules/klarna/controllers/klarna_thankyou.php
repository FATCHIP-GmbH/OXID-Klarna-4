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



class klarna_thankyou extends klarna_thankyou_parent
{
    /** @var KlarnaCheckoutClient */
    protected $client;
    /**
     * @return mixed
     * @throws oxSystemComponentException
     */
    public function render()
    {
        $render = parent::render();

        if (oxRegistry::getSession()->getVariable('paymentid') === 'klarna_checkout') {

            $sKlarnaId = oxRegistry::getSession()->getVariable('klarna_checkout_order_id');
            $oOrder = oxNew('oxorder');
            $query = $oOrder->buildSelectString(array('klorderid' => $sKlarnaId));
            $oOrder->assignRecord($query);
            $sCountryISO = KlarnaUtils::getCountryISO($oOrder->getFieldData('oxbillcountryid'));
            $this->addTplParam("klOrder", $oOrder);

            if(!$this->client){
                $this->client = KlarnaCheckoutClient::getInstance($sCountryISO);
            }

            try {
                $this->client->getOrder($sKlarnaId);

            } catch (KlarnaClientException $e) {
                $e->debugOut();
            }

            // add klarna confirmation snippet
            $this->addTplParam("sKlarnaIframe", $this->client->getHtmlSnippet());
        }
        $this->addTplParam("sPaymentId", oxRegistry::getSession()->getVariable('paymentid'));

        KlarnaUtils::fullyResetKlarnaSession();

        return $render;
    }
}