<?php

class klarna_validate extends oxUBase
{
    /** @var string */
    protected $order_id;

    /** @var string */
    protected $requestBody;

    /**
     * Klarna order validation callback
     * @throws oxSystemComponentException
     * @throws oxConnectionException
     */
    public function init()
    {
        parent::init();

        $redirectUrl       = null;
        $this->requestBody = $this->getRequestBody();
        $validator         = $this->getValidator();
        $validator->validateOrder();

        if ($validator->isValid()) {
            $responseStatus = 200;
            $this->logKlarnaData(
                'Validate Order',
                $this->order_id,
                'FROMKLARNA: ' . $this->requestBody,
                $_SERVER['REQUEST_URI'],
                $responseStatus,
                $validator->getResultErrors() ?: '',
                $redirectUrl ?: ''
            );

            $this->setValidResponseHeader($responseStatus);
            oxRegistry::getUtils()->showMessageAndExit('');
        } else {
            $sid         = oxRegistry::getConfig()->getRequestParameter('s');
            $redirectUrl = oxRegistry::getConfig()->getSslShopUrl() . "index.php?cl=basket&force_sid=$sid&klarnaInvalid=1&";
            $redirectUrl .= http_build_query($validator->getResultErrors());
            $responseStatus = 303;

            $this->logKlarnaData(
                'Validate Order',
                $this->order_id,
                'FROMKLARNA: ' . $this->requestBody,
                $_SERVER['REQUEST_URI'],
                $responseStatus,
                $validator->getResultErrors(),
                $redirectUrl
            );
            oxRegistry::getUtils()->redirect($redirectUrl, true, $responseStatus);
        }
    }

    /**
     * Logging push state message to database
     * @param $action
     * @param $order_id
     * @param string $requestBody
     * @param $url
     * @param $response
     * @param $errors
     * @param string $redirectUrl
     * @throws oxSystemComponentException
     */
    protected function logKlarnaData($action, $order_id, $requestBody, $url, $response, $errors, $redirectUrl = '')
    {
        $oKlarnaLog = oxNew('klarna_logs');
        $aData      = array(
            'kl_logs__klmethod'      => $action,
            'kl_logs__klurl'         => $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $url,
            'kl_logs__klorderid'     => $order_id,
            'kl_logs__klrequestraw'  => $requestBody,
            'kl_logs__klresponseraw' => "Code: " . $response .
                                        " \nHeader Location:" . $redirectUrl .
                                        " \nERRORS:" . var_export($errors, true),
            'kl_logs__kldate'        => date("Y-m-d H:i:s"),
        );
        $oKlarnaLog->assign($aData);
        $oKlarnaLog->save();
    }

    /**
     * @codeCoverageIgnore
     * @return bool|string
     */
    protected function getRequestBody()
    {
        return file_get_contents('php://input');
    }

    /**
     * @return KlarnaOrderValidator
     */
    protected function getValidator()
    {
        $aKlarnaOrderData = json_decode($this->requestBody, true);
        $this->order_id   = $aKlarnaOrderData['order_id'];

        return oxNew('KlarnaOrderValidator', $aKlarnaOrderData);
    }

    /**
     * @codeCoverageIgnore
     * @param $responseStatus
     * @return bool
     */
    protected function setValidResponseHeader($responseStatus)
    {
        header("", true, $responseStatus);

        return true;
    }
}