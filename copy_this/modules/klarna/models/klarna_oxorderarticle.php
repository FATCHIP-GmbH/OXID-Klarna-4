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

class klarna_oxorderarticle extends klarna_oxorderarticle_parent
{
    public function getAmount()
    {
        return $this->oxorderarticles__oxamount->value;
    }

    /**
     * @codeCoverageIgnore
     * @return mixed
     */
    public function getRegularUnitPrice()
    {
        return $this->getBasePrice();
    }

    /**
     * @codeCoverageIgnore
     * @return mixed
     */
    public function getUnitPrice()
    {
        return $this->getPrice();
    }

    /**
     * @param $index
     * @param int|string $iOrderLang
     */
    public function kl_setTitle($index, $iOrderLang = '')
    {
        $name = KlarnaUtils::getShopConfVar('sKlarnaAnonymizedProductTitle_' . $this->getLangTag($iOrderLang));
        $this->oxorderarticles__kltitle = new oxField(html_entity_decode( "$name $index", ENT_QUOTES));
    }

    public function kl_setArtNum()
    {
        $this->oxorderarticles__klartnum = new oxField( md5($this->oxorderarticles__oxartnum->value));
    }

    protected function getLangTag($iOrderLang)
    {
        return strtoupper(oxRegistry::getLang()->getLanguageAbbr($iOrderLang));
    }
}