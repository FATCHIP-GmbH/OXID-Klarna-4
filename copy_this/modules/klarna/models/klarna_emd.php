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

class Klarna_EMD
{
    /**
     * Date format
     *
     * @var string
     */
    const EMD_FORMAT = 'Y-m-d\TH:i:s\Z';

    /**
     * Get attachments from basket
     *
     * @param oxUser $oUser
     * @return array
     * @throws oxSystemComponentException
     */
    public function getAttachments(oxUser $oUser)
    {
        $return = array();

        if (KlarnaUtils::getShopConfVar('blKlarnaEmdCustomerAccountInfo')) {
            $return = array_merge($return, $this->getCustomerAccountInfo($oUser));
        }
        if (KlarnaUtils::getShopConfVar('blKlarnaEmdPaymentHistoryFull')) {
            $return = array_merge($return, $this->getPaymentHistoryFull($oUser));
        }

        return $return;
    }

    /**
     * Get customer account info
     *
     * @param oxUser $oUser
     * @return array
     * @throws oxSystemComponentException
     */
    protected function getCustomerAccountInfo(oxUser $oUser)
    {
        /** @var Klarna_Customer_Account_Info $oKlarnaPayload */
        $oKlarnaPayload = oxNew('Klarna_Customer_Account_Info');

        return $oKlarnaPayload->getCustomerAccountInfo($oUser);
    }

    /**
     * Get payment history
     *
     * @param oxUser $oUser
     * @return array
     * @throws oxSystemComponentException
     */
    protected function getPaymentHistoryFull(oxUser $oUser)
    {
        /** @var Klarna_Payment_History_Full $oKlarnaPayload */
        $oKlarnaPayload = oxNew('Klarna_Payment_History_Full');

        return $oKlarnaPayload->getPaymentHistoryFull($oUser);
    }
}
