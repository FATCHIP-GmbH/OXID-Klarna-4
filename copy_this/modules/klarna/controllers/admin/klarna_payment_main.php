<?php

class klarna_payment_main extends klarna_payment_main_parent
{
    public function render()
    {
        $isKlarnaPayment = klarna_oxpayment::isKlarnaPayment($this->getEditObjectid());
        $this->addTplParam('isKlarnaPayment', $isKlarnaPayment);

        return parent::render();
    }
}