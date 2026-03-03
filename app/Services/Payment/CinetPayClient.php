<?php

namespace App\Services\Payment;

use App\Exceptions\Payment\CinetPayApiException;
use App\Exceptions\Payment\PaymentConfigurationException;
use CinetPay\CinetPay;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * CinetPay API Client
 * 
 * Handles communication with the CinetPay API for payment processing.
 * Uses the official CinetPay SDK with fallback to Laravel HTTP Client.
 * 
 * Validates: Requirements 8.1, 8.2, 8.3, 4.1, 4.3, 4.5, 10.3
 */
class CinetPayClient
{
    protected ?CinetPay $sdk = null;
    protected bool $useSdk = true;
    protected string $apiKey;
    protected string $siteId;
    protected string $secretKey;
    protected int $timeout;
    protected int $retryAttempts;
    protected array $retryDelays;

    /**
     * Initialize the CinetPay client
     * 
     * @throws PaymentConfigurationException
     */
    public function __construct()
    {
        $this->validateConfiguration();
        $this->loadConfiguration();
        $this->initializeSdk();
    }

    /**
     * Validate that all required configuration values are present
     * 
     * @throws PaymentConfigurationException
     */
    protected function validateConfiguration(): void
    {
        $apiKey = config('cinetpay.api_key');
        $siteId = config('cinetpay.site_id');
        $secretKey = config('cinetpay.secret_key');

        if (empty($apiKey) || empty($siteId) || empty($secretKey)) {
            throw new PaymentConfigurationException(
                'CinetPay credentials are missing. Please configure CINETPAY_API_KEY, CINETPAY_SITE_ID, and CINETPAY_SECRET_KEY in your .env file.'
            );
        }
    }

    /**
     * Load configuration values
     */
    protected function loadConfiguration(): void
    {
        $this->apiKey = config('cinetpay.api_key');
        $this->siteId = config('cinetpay.site_id');
        $this->secretKey = config('cinetpay.secret_key');
        
        // Load retry configuration from gateway-specific config
        $this->timeout = config('payment.gateways.cinetpay.timeout', 30);
        $this->retryAttempts = config('payment.gateways.cinetpay.retry_attempts', 3);
        $this->retryDelays = config('payment.gateways.cinetpay.retry_delays', [1, 2, 4]);
    }

    /**
     * Initialize the CinetPay SDK
     * Falls back to HTTP client if SDK is not available
     */
    protected function initializeSdk(): void
    {
        try {
            if (class_exists(CinetPay::class)) {
                $this->sdk = new CinetPay($this->siteId, $this->apiKey);
                $this->useSdk = true;
                Log::debug('CinetPay SDK initialized successfully');
            } else {
                $this->useSdk = false;
                Log::warning('CinetPay SDK not available, using HTTP client fallback');
            }
        } catch (Exception $e) {
            $this->useSdk = false;
            Log::warning('Failed to initialize CinetPay SDK, using HTTP client fallback', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Initialize a payment with CinetPay
     * 
     * @param array $data Payment data including amount, currency, transaction_id, etc.
     * @return array Payment response with payment URL
     * @throws CinetPayApiException
     */
    public function initializePayment(array $data): array
    {
        Log::debug('Initializing payment with CinetPay', [
            'transaction_id' => $data['transaction_id'] ?? null,
            'amount' => $data['amount'] ?? null
        ]);

        try {
            return $this->retryWithBackoff(function () use ($data) {
                if ($this->useSdk) {
                    return $this->initializePaymentWithSdk($data);
                } else {
                    return $this->initializePaymentWithHttp($data);
                }
            });
        } catch (Exception $e) {
            Log::error('Failed to initialize payment', [
                'error' => $e->getMessage(),
                'transaction_id' => $data['transaction_id'] ?? null
            ]);
            throw new CinetPayApiException(
                'Failed to initialize payment with CinetPay: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Initialize payment using the CinetPay SDK
     * 
     * @param array $data
     * @return array
     * @throws Exception
     */
    private function initializePaymentWithSdk(array $data): array
    {
        try {
            $this->sdk->setTransId($data['transaction_id']);
            $this->sdk->setAmount($data['amount']);
            $this->sdk->setCurrency($data['currency'] ?? 'XOF');
            if (isset($data['description'])) {
                $this->sdk->setDesignation($data['description']);
            }
            $this->sdk->setReturnUrl($data['return_url']);
            $this->sdk->setNotifyUrl($data['notify_url']);
            // Note: setChannels(), setCustomerName(), setCustomerSurname() are not available in this SDK version
            
            $response = $this->sdk->generatePaymentLink();

            Log::debug('CinetPay SDK payment initialization response', [
                'code' => $response['code'] ?? null,
                'message' => $response['message'] ?? null
            ]);

            if (isset($response['code']) && $response['code'] == '201') {
                return [
                    'payment_url' => $response['data']['payment_url'] ?? null,
                    'payment_token' => $response['data']['payment_token'] ?? null,
                    'cinetpay_payment_id' => $response['data']['payment_id'] ?? null,
                ];
            }

            throw new Exception($response['message'] ?? 'Unknown error from CinetPay SDK');
        } catch (Exception $e) {
            // Convert SDK exceptions to application exceptions
            throw new CinetPayApiException(
                'CinetPay SDK error: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Initialize payment using Laravel HTTP Client
     * 
     * @param array $data
     * @return array
     * @throws Exception
     */
    private function initializePaymentWithHttp(array $data): array
    {
        $response = Http::timeout($this->timeout)
            ->post('https://api-checkout.cinetpay.com/v2/payment', [
                'apikey' => $this->apiKey,
                'site_id' => $this->siteId,
                'transaction_id' => $data['transaction_id'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'XOF',
                'description' => $data['description'] ?? 'Payment',
                'return_url' => $data['return_url'],
                'notify_url' => $data['notify_url'],
                'channels' => $data['channels'] ?? 'ALL',
                'customer_name' => $data['customer_name'] ?? '',
                'customer_surname' => $data['customer_surname'] ?? '',
            ]);

        Log::debug('CinetPay HTTP payment initialization response', [
            'status' => $response->status(),
            'body' => $response->json()
        ]);

        if ($response->successful()) {
            $body = $response->json();
            if (isset($body['code']) && $body['code'] == '201') {
                return [
                    'payment_url' => $body['data']['payment_url'] ?? null,
                    'payment_token' => $body['data']['payment_token'] ?? null,
                    'cinetpay_payment_id' => $body['data']['payment_id'] ?? null,
                ];
            }
            throw new Exception($body['message'] ?? 'Unknown error from CinetPay API');
        }

        throw new Exception('HTTP request failed with status ' . $response->status());
    }

    /**
     * Check the status of a transaction with CinetPay
     * 
     * @param string $transactionId
     * @return array Transaction status information
     * @throws CinetPayApiException
     */
    public function checkTransactionStatus(string $transactionId): array
    {
        Log::debug('Checking transaction status with CinetPay', [
            'transaction_id' => $transactionId
        ]);

        try {
            return $this->retryWithBackoff(function () use ($transactionId) {
                if ($this->useSdk) {
                    return $this->checkStatusWithSdk($transactionId);
                } else {
                    return $this->checkStatusWithHttp($transactionId);
                }
            });
        } catch (Exception $e) {
            Log::error('Failed to check transaction status', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId
            ]);
            throw new CinetPayApiException(
                'Failed to check transaction status: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Check transaction status using the CinetPay SDK
     * 
     * @param string $transactionId
     * @return array
     * @throws Exception
     */
    private function checkStatusWithSdk(string $transactionId): array
    {
        try {
            $this->sdk->setTransId($transactionId);
            $response = $this->sdk->getPayStatus();

            Log::debug('CinetPay SDK status check response', [
                'code' => $response['code'] ?? null,
                'message' => $response['message'] ?? null
            ]);

            if (isset($response['code']) && $response['code'] == '00') {
                return [
                    'status' => $response['data']['status'] ?? null,
                    'payment_method' => $response['data']['payment_method'] ?? null,
                    'operator_id' => $response['data']['operator_id'] ?? null,
                    'amount' => $response['data']['amount'] ?? null,
                ];
            }

            throw new Exception($response['message'] ?? 'Unknown error from CinetPay SDK');
        } catch (Exception $e) {
            // Convert SDK exceptions to application exceptions
            throw new CinetPayApiException(
                'CinetPay SDK error: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Check transaction status using Laravel HTTP Client
     * 
     * @param string $transactionId
     * @return array
     * @throws Exception
     */
    private function checkStatusWithHttp(string $transactionId): array
    {
        $response = Http::timeout($this->timeout)
            ->post('https://api-checkout.cinetpay.com/v2/payment/check', [
                'apikey' => $this->apiKey,
                'site_id' => $this->siteId,
                'transaction_id' => $transactionId,
            ]);

        Log::debug('CinetPay HTTP status check response', [
            'status' => $response->status(),
            'body' => $response->json()
        ]);

        if ($response->successful()) {
            $body = $response->json();
            if (isset($body['code']) && $body['code'] == '00') {
                return [
                    'status' => $body['data']['status'] ?? null,
                    'payment_method' => $body['data']['payment_method'] ?? null,
                    'operator_id' => $body['data']['operator_id'] ?? null,
                    'amount' => $body['data']['amount'] ?? null,
                ];
            }
            throw new Exception($body['message'] ?? 'Unknown error from CinetPay API');
        }

        throw new Exception('HTTP request failed with status ' . $response->status());
    }

    /**
     * Validate the signature of an IPN notification
     * 
     * @param array $payload IPN notification payload
     * @return bool True if signature is valid
     */
    public function validateSignature(array $payload): bool
    {
        if (!isset($payload['cpm_trans_id']) || !isset($payload['signature'])) {
            Log::warning('IPN payload missing required fields for signature validation');
            return false;
        }

        // CinetPay signature validation logic
        // The signature is typically a hash of specific fields with the secret key
        $expectedSignature = hash('sha256', 
            $payload['cpm_trans_id'] . 
            $payload['cpm_amount'] . 
            $this->secretKey
        );

        $isValid = hash_equals($expectedSignature, $payload['signature']);

        Log::debug('IPN signature validation', [
            'transaction_id' => $payload['cpm_trans_id'],
            'is_valid' => $isValid
        ]);

        return $isValid;
    }

    /**
     * Retry an operation with exponential backoff
     * 
     * @param callable $operation The operation to retry
     * @param int $maxAttempts Maximum number of retry attempts
     * @return mixed The result of the operation
     * @throws Exception If all retry attempts fail
     */
    private function retryWithBackoff(callable $operation, int $maxAttempts = null): mixed
    {
        $maxAttempts = $maxAttempts ?? $this->retryAttempts;
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxAttempts) {
            try {
                return $operation();
            } catch (Exception $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt >= $maxAttempts) {
                    break;
                }

                $delay = $this->retryDelays[$attempt - 1] ?? $this->retryDelays[count($this->retryDelays) - 1];
                
                Log::warning('CinetPay operation failed, retrying', [
                    'gateway' => 'cinetpay',
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'delay' => $delay,
                    'error' => $e->getMessage()
                ]);

                sleep($delay);
            }
        }

        throw $lastException;
    }
}
