<?php

class klarna_order_list extends klarna_order_list_parent
{
    /**
     *
     * @throws oxException
     * @throws oxSystemComponentException
     */
    public function deleteEntry()
    {
        $orderId = $this->getEditObjectId();
        $oOrder  = oxNew('oxorder');
        $oOrder->load($orderId);

        if ($oOrder->isLoaded() && $oOrder->isKlarnaOrder() && !$oOrder->getFieldData('oxstorno')) {
            $orderId     = $oOrder->getFieldData('klorderid');
            $sCountryISO = KlarnaUtils::getCountryISO($oOrder->getFieldData('oxbillcountryid'));

            try {
                $result                  = $oOrder->cancelKlarnaOrder($orderId, $sCountryISO);
                $oOrder->oxorder__klsync = new oxField(1);
                $oOrder->save();
            } catch (oxException $e) {
                if (strstr($e->getMessage(), 'is canceled.')) {
                    parent::deleteEntry();
                } else {
                    oxRegistry::get("oxUtilsView")->addErrorToDisplay($e);
                    $_POST['oxid'] = -1;
                    $this->resetContentCache();
                    $this->init();
                }

                return;
            }
        }
        parent::deleteEntry();
    }

    /**
     * @throws oxSystemComponentException
     */
    public function storno()
    {
        $orderId = $this->getEditObjectId();
        $oOrder  = oxNew('oxorder');
        $oOrder->load($orderId);

        if ($oOrder->isLoaded() && $oOrder->isKlarnaOrder() && !$oOrder->getFieldData('oxstorno')) {
            $orderId     = $oOrder->getFieldData('klorderid');
            $sCountryISO = KlarnaUtils::getCountryISO($oOrder->getFieldData('oxbillcountryid'));

            try {
                $oOrder->cancelKlarnaOrder($orderId, $sCountryISO);
                $oOrder->oxorder__klsync = new oxField(1);
                $oOrder->save();
            } catch (oxException $e) {
                if (strstr($e->getMessage(), 'is canceled.')) {
                    parent::storno();
                } else {
                    oxRegistry::get("oxUtilsView")->addErrorToDisplay($e);
                    $_POST['oxid'] = -1;
                    $this->resetContentCache();
                    $this->init();
                }

                return;
            }
        } else {
            parent::storno();
        }
    }
}