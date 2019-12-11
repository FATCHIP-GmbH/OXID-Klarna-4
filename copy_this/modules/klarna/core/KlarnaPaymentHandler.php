<?php
/**
 * Copyright 2019 Klarna AB
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
 * Class KlarnaPaymentHandler
 */

class KlarnaPaymentHandler implements KlarnaPaymentHandlerInterface
{
    protected $error = null;

    /** @var KlarnaPaymentsClient  */
    protected $httpClient;

    /** @var KlarnaPayment  */
    protected $context;

    public function __construct()
    {
        $this->httpClient = KlarnaPaymentsClient::getInstance();
        $this->context = $this->getContext();
    }

    public function execute(oxOrder $oOrder)
    {
        $this->context->validateOrder();
        $errors = $this->context->getError();
        if (count($errors) > 0) {
            $this->error = reset($errors);
            return false;
        }
        // returns success response or false
        // errors are added automatically to the view by httpClient
        $response = $this->httpClient->initOrder($this->context)->createNewOrder();
        $result = $this->checkFraudStatus($response);
        if ($result) {
            $this->updateOrder($oOrder, $response);
        }

        return $result;
    }

    /** @codeCoverageIgnore */
    public function getError()
    {
        return $this->error;
    }

    protected function checkFraudStatus(array $createResponse)
    {
        if($createResponse['fraud_status'] !== 'ACCEPTED') {
            $this->error = 'fraud_status=' . $createResponse['fraud_status'];
            return false;
        }

        return true;
    }

    protected function updateOrder(oxOrder $oOrder, $response)
    {
        $oOrder->oxorder__klorderid = new oxField($response['order_id'], oxField::T_RAW);
        $oOrder->saveMerchantIdAndServerMode();
        $oOrder->save();
    }

    /**
     * KlarnaPayment factory function
     */
    protected function getContext()
    {
        $oSession = oxRegistry::getSession();
        /** @var  KlarnaPayment $oKlarnaPayment */
        $oKlarnaPayment = oxNew('KlarnaPayment',
            $oSession->getBasket(),
            $oSession->getUser()
        );

        return $oKlarnaPayment;
    }
}