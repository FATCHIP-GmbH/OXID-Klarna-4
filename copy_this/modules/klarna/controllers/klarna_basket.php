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

class klarna_basket extends klarna_basket_parent
{
    /**
     * Rendering template
     *
     * @return mixed
     */
    public function render()
    {
        if(oxRegistry::getConfig()->getRequestParameter('openAmazonLogin')){
            $this->addTplParam('openAmazonLogin', true);
        }

        $oSession = oxRegistry::getSession();
        $oBasket = $oSession->getBasket();
        $klarnaInvalid = oxRegistry::getConfig()->getRequestParameter('klarnaInvalid');
        if($oBasket->getPaymentId() === 'klarna_checkout' && $klarnaInvalid){
            $this->displayKlarnaValidationErrors();
        }

        return parent::render();
    }

    /**
     *
     */
    protected function displayKlarnaValidationErrors()
    {
        parse_str($_SERVER['QUERY_STRING'], $query);

        $oLang          = oxRegistry::getLang();
        foreach($query as $errorId => $articleId){
            if(strstr($errorId, 'ERROR')){
                oxRegistry::get("oxUtilsView")->addErrorToDisplay(
                    sprintf($oLang->translateString($errorId), $articleId)
                );
            }
        }
    }
}