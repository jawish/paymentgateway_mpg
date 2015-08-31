<?php
/**
 * Maldives Payment Gateway (MPG) processor.
 *
 * @author Jawish Hameed <jaa@jawish.org>
 * @version 1.0
 * @license MIT
 */

/**
 * Maldives Payment Gateway (MPG) processor.
 *
 */
class PaymentGatewayMpg
{
    public $gatewayUrl = 'https://egateway.bankofmaldives.com.mv/SENTRY/PaymentGateway/Application/RedirectLink.aspx';
    public $purchaseCurrency = '462';   // MVR: 462, USD: 840
    public $purchaseCurrencyExponent = '2';
    public $acquirerId;
    public $merchantId;
    public $transactionPassword;
    public $version = '1.0.0';
    public $signatureMethod = 'SHA1';
    public $returnUrl;
    public $orderId;
    public $amount = 0;


    /**
     * Initialize class
     * 
     * @param array $data       Array of configs to initialize object.
     *                          Config attributes: 
     *                          - gatewayUrl
     *                          - purchaseCurrency
     *                          - purchaseCurrencyExponent
     *                          - aquirerId
     *                          - merchantId
     *                          - transactionPassword
     *                          - version
     *                          - signatureMethod
     *                          - returnUrl
     *                          - orderId
     *                          - amount
     */
    public function __construct($data=array())
    {
        if (is_array($data) && count($data) > 0) {
            foreach ($data as $key => $value) {
                if (property_exists(get_class($this), $key)) {
                    $this->$key = $value;
                }
            }
        }
    }

    /**
     * Generate the form data and return as array
     *
     * @return array            Array form data to be POST'ed to MPG.
     *                          Attributes:
     *                          - Version
     *                          - MerID
     *                          - AcqID
     *                          - MerRespURL
     *                          - PurchaseCurrency
     *                          - PurchaseCurrencyExponent
     *                          - OrderID
     *                          - SignatureMethod
     *                          - PurchaseAmt
     *                          - Url
     *                          - Signature
     */
    public function generateFormData()
    {
        // Format the amount
        $amountFormatted = str_pad(number_format($this->amount, 2, '', ''), 12, '0', STR_PAD_LEFT);

        // Create form fields for MPG
        $mpgData = array(
            'Version'                   => $this->version,
            'MerID'                     => $this->merchantId,
            'AcqID'                     => $this->acquirerId,
            'MerRespURL'                => $this->returnUrl,
            'PurchaseCurrency'          => $this->purchaseCurrency,
            'PurchaseCurrencyExponent'  => $this->purchaseCurrencyExponent,
            'OrderID'                   => $this->orderId,
            'SignatureMethod'           => $this->signatureMethod,
            'PurchaseAmt'               => $amountFormatted,
            'Url'                       => $this->gatewayUrl,
            'Signature'                 => $this->generateSignature()
        );
        
        // Return final data
        return $mpgData;
    }


    /**
     * Process response
     *
     * @param $response array       Response from server, usually the $_POST variable.
     * @return array                Parsed response
     */
    public function processResponse($response)
    {
        $requiredFields = array(
            'ResponseCode',
            'OrderID',
            'ReasonCode',
            'ReasonCodeDesc'
        );

        // Check is response is an array and contains the required fields
        if (is_array($response) && 
            count(array_intersect_key(array_flip($requiredFields), $response)) != count($requiredFields)) {
            throw new Exception('Invalid response');
        }

        // Return formatted response data
        return array(
            'responseCode'          => $response['ResponseCode'],
            'responseDescription'   => $this->getErrorDescription($response['ResponseCode']),
            'orderId'               => $response['OrderID'],
            'reasonDescription'     => $response['ReasonCodeDesc'],
            'reasonCode'            => $response['ReasonCode'],
            'referenceNo'           => isset($response['ReferenceNo']) ?: '',
            'authCode'              => isset($response['AuthCode']) ?: '',
            'cardNo'                => isset($response['PaddedCardNo']) ?: '',
            'signature'             => isset($response['Signature']) ?: ''
        );
    }

    /**
     * Check if response signature matches expected
     * 
     * @param string $signature     Signature to compare
     * @return boolean              Boolean true, if match, false otherwise.
     */
    public function validateSignature($signature)
    {
        return $this->generateSignature() == $signature;
    }


    /**
     * Generate signature
     *
     * @return string               Signature string
     */
    public function generateSignature()
    {
        // Signature method can only be SHA1
        if ($this->signatureMethod != 'SHA1') {
            throw new Exception('Unsupported signature method');
        }

        // Format the amount
        $amountFormatted = str_pad(number_format($this->amount, 2, '', ''), 12, '0', STR_PAD_LEFT);

        $signatureText = $this->transactionPassword .
                         $this->merchantId .
                         $this->acquirerId .
                         $this->orderId .
                         $amountFormatted .
                         $this->purchaseCurrency;

        return base64_encode(sha1($signatureText, true));
    }


    /**
     * Get textual error message for given code
     */
    public function getErrorDescription($code)
    {
        switch ($code) {
            case '1':
                $error = 'Transaction successful!';
                break;

            case '2':
            case '3':
            case '4':
            case '11':
                $error = 'Transaction was rejected. Please contact your bank.';
                break;

            default:
                $error = 'Something went wrong. Please try again...';
        }

        return $error;
    }

}