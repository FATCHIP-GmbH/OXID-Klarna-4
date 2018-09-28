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

class klarna_oxuserpayment extends klarna_oxuserpayment_parent
{

    /**
     * @return bool
     */
    public function isKlarnaPayment()
    {
        return klarna_oxpayment::isKlarnaPayment($this->oxuserpayments__oxpaymentsid->value);
    }

    /**
     * @return string
     * @throws oxSystemComponentException
     */
    public function getBadgeUrl()
    {
        $paymentId = $this->oxuserpayments__oxpaymentsid->value;
        if ($paymentId === 'klarna_checkout') {
            return '//cdn.klarna.com/1.0/shared/image/generic/logo/en_gb/basic/logo_black.png';
        }

        $oPayment = oxNew('oxPayment');
        $oPayment->load($paymentId);

        $from   = '/' . preg_quote('-', '/') . '/';
        $locale = preg_replace($from, '_', strtolower(KlarnaConsts::getLocale()), 1);

        $name = $oPayment->getPaymentCategoryName();
        if ($name === 'pay_over_time') {
            $name = 'slice_it';
        }

        return sprintf("//cdn.klarna.com/1.0/shared/image/generic/badge/%s/%s/standard/pink.png",
            $locale,
            $name
        );
    }
}