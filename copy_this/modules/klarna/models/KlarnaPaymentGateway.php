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
 * Class KlarnaPaymentGateway
 *
 * @property string _sLastError
 */
class KlarnaPaymentGateway extends KlarnaPaymentGateway_parent
{
    protected $paymentHandlerMap = array(
        klarna_oxpayment::KLARNA_PAYMENT_SLICE_IT_ID  => 'KlarnaPaymentHandler',
        klarna_oxpayment::KLARNA_PAYMENT_PAY_LATER_ID => 'KlarnaPaymentHandler',
        klarna_oxpayment::KLARNA_PAYMENT_PAY_NOW      => 'KlarnaPaymentHandler',
        klarna_oxpayment::KLARNA_DIRECTDEBIT          => 'KlarnaPaymentHandler',
        klarna_oxpayment::KLARNA_SOFORT               => 'KlarnaPaymentHandler',
    );

    public function executePayment($dAmount, & $oOrder)
    {
        $result = parent::executePayment($dAmount, $oOrder);
        $paymentHandler = $this->createPaymentHandler($oOrder);
        if ($paymentHandler) {
            $result = $paymentHandler->execute($oOrder);
            $this->_sLastError = $paymentHandler->getError();
        }
        return $result;
    }

    /**
     * Payment Handler Factory Function
     * @param oxOrder $oOrder
     * @return bool|KlarnaPaymentHandlerInterface
     */
    protected function createPaymentHandler(oxOrder $oOrder)
    {
        $paymentId = $oOrder->getFieldData('OXPAYMENTTYPE');
        $handlerClass = $this->paymentHandlerMap[$paymentId];
        if ($handlerClass) {
            return oxNew($handlerClass);
        }
        return false;
    }
}