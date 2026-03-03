<?php

namespace App\Services\Payment;

use App\Exceptions\Payment\PaymentConfigurationException;
use App\Exceptions\Payment\TranzakApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

/**
 * Tranzak API Client
 * 
 * Handles communication with the Tranzak API for payment processing.
 * Implements retry logic with exponential backoff for resilient API calls.
 * 
 * Validates: Requirements 4.1, 4.2, 13.3
 */
class TranzakClient
{
    /**
     * @var string Tranzak API key for authentication
     */
    private string $apiKey;
    
    /**
     * @var string Tranzak App ID
     */
    private string $appId;
    
    /**
     * @var string Base URL for Tranzak API
     */
    private string $baseUrl;
    
    /**
     * @var int Request timeout in seconds
     */
    private int $timeout;
    
    /**
     * @var int Maximum number of retry attempts
     */
    private int $retryAttempts;
    
    /**
     * @var array Delay in seconds for each retry attempt
     */
    private array $retryDelays;
    
    /**
     * Initialize the Tranzak client
     * 
     * @param string $apiKey Tranzak API key
     * @param string $appId Tranzak App ID
     * @param string $baseUrl Base URL for Tranzak API (default: production URL)
     * @param int $timeout Request timeout in seconds
     * @param int $retryAttempts Maximum number of retry attempts
     * @param array $retryDelays Delay in seconds for each retry attempt
     * 
     * @throws PaymentConfigurationException If credentials are missing
     */
    public function __construct(
        string $apiKey,
        string $appId,
        string $baseUrl = 'https://dsapi.tranzak.me',
        int $timeout = 60,
        int $retryAttempts = 3,
        array $retryDelays = [1, 2, 4]
    ) {
        if (empty($apiKey) || empty($appId)) {
            throw new PaymentConfigurationException(
                'Tranzak credentials are missing. API key and App ID are required.'
            );
        }
        
        $this->apiKey = $apiKey;
        $this->appId = $appId;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
        $this->retryAttempts = $retryAttempts;
        $this->retryDelays = $retryDelays;
        
        Log::debug('TranzakClient initialized', [
            'base_url' => $this->baseUrl,
            'timeout' => $this->timeout,
            'retry_attempts' => $this->retryAttempts,
        ]);
    }
    
    /**
     * Create a new payment request with Tranzak
     * 
     * Initiates a payment request and returns the payment URL where
     * the user should be redirected to complete the payment.
     * 
     * @param array $data Payment data containing:
     *   - amount: Payment amount
     *   - currency: Currency code (e.g., "XAF")
     *   - description: Payment description
     *   - return_url: URL to redirect user after payment
     *   - cancel_url: URL to redirect user if payment is cancelled
     *   - callback_url: URL for webhook notifications
     *   - app_id: Tranzak App ID
     *   - mchTransactionRef: Merchant transaction reference (optional)
     * 
     * @return array Payment response containing:
     *   - links.payment_url: URL to redirect user for payment
     *   - request_id: Tranzak's payment request identifier
     * 
     * @throws TranzakApiException If payment creation fails
     */
    public function createPayment(array $data, array $options = []): array
    {
        Log::debug('TranzakClient: Creating payment', [
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? null,
        ]);
        
        try {
            $operation = function () use ($data) {
                $headers = [
                    'Authorization' => 'Bearer ' . $this->getToken(),
                    'Content-Type' => 'application/json',
                ];
                
                Log::debug('TranzakClient: Request headers', [
                    'headers' => array_keys($headers),
                    'url' => "{$this->baseUrl}/xp021/v1/request/create",
                ]);
                
                $requestBody = [
                    'amount' => $data['amount'],
                    'currencyCode' => $data['currency'] ?? 'XAF',
                    'description' => $data['description'] ?? 'Payment',
                    'mchTransactionRef' => $data['mchTransactionRef'] ?? 'TXN_' . uniqid(),
                    'returnUrl' => $data['return_url'],
                ];

                if (!empty($data['cancel_url'])) {
                    $requestBody['cancelUrl'] = $data['cancel_url'];
                }
                if (!empty($data['callback_url'])) {
                    $requestBody['callbackUrl'] = $data['callback_url'];
                }

                $response = Http::withHeaders($headers)
                    ->timeout($this->timeout)
                    ->post("{$this->baseUrl}/xp021/v1/request/create", $requestBody);
                
                Log::debug('TranzakClient: Payment creation response', [
                    'status' => $response->status(),
                    'success' => $response->successful(),
                    'body' => $response->json(),
                ]);
                
                if (!$response->successful()) {
                    $errorBody = $response->json();
                    throw new TranzakApiException(
                        "Failed to create payment: " . ($errorBody['errorMsg'] ?? $errorBody['message'] ?? $response->body()),
                        $response->status()
                    );
                }
                
                $body = $response->json();

                // Tranzak may return HTTP 200 with success=false; always check success flag per documentation
                if (isset($body['success']) && $body['success'] === false) {
                    throw new TranzakApiException(
                        "Failed to create payment: " . ($body['errorMsg'] ?? 'Unknown error'),
                        $body['errorCode'] ?? 500
                    );
                }
                
                Log::debug('TranzakClient: Raw response body', [
                    'body' => $body
                ]);
                
                // Tranzak API often wraps the response in a 'data' key, but sometimes it doesn't
                // or the structure might vary between sandbox and production.
                $responseData = $body;
                if (isset($body['data']) && is_array($body['data'])) {
                    $responseData = $body['data'];
                }
                
                // Flexible extraction of key fields
                $paymentUrl = $responseData['links']['paymentAuthUrl'] ?? $responseData['paymentAuthUrl'] ?? $responseData['links']['payment_url'] ?? null;
                $requestId = $responseData['requestId'] ?? $responseData['request_id'] ?? null;

                Log::debug('TranzakClient: Extracted fields', [
                    'payment_url' => $paymentUrl,
                    'request_id' => $requestId
                ]);
                
                // Validate response structure
                if (!$paymentUrl || !$requestId) {
                    Log::error('TranzakClient: Invalid response structure', [
                        'body' => $body,
                    ]);
                    throw new TranzakApiException(
                        "Invalid response structure from Tranzak API: missing paymentAuthUrl or requestId",
                        500
                    );
                }
                
                return [
                    'links' => ['paymentAuthUrl' => $paymentUrl],
                    'requestId' => $requestId,
                    'data' => $responseData
                ];
            };

            // Check if we should use retry logic
            $retries = $options['retries'] ?? $this->retryAttempts;
            if ($retries > 0) {
                return $this->retryWithBackoff($operation);
            }
            
            return $operation();
        } catch (TranzakApiException $e) {
            // Re-throw TranzakApiException as-is
            throw $e;
        } catch (Exception $e) {
            Log::error('TranzakClient: Failed to create payment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw new TranzakApiException(
                'Failed to create payment with Tranzak: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    /**
     * Get the status of a payment request
     * 
     * Queries the Tranzak API to retrieve the current status of a payment.
     * 
     * @param string $requestId Tranzak payment request ID
     * 
     * @return array Payment status response containing:
     *   - status: Payment status (e.g., "SUCCESSFUL", "PENDING", "FAILED")
     *   - requestId: Payment request identifier
     *   - amount: Payment amount
     *   - currencyCode: Currency code
     * 
     * @throws TranzakApiException If status check fails
     */
    public function getPaymentStatus(string $requestId): array
    {
        Log::debug('TranzakClient: Getting payment status', [
            'requestId' => $requestId,
        ]);
        
        try {
            return $this->retryWithBackoff(function () use ($requestId) {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->getToken(),
                    'Content-Type' => 'application/json',
                ])
                ->timeout($this->timeout)
                ->get("{$this->baseUrl}/xp021/v1/request/details", [
                    'requestId' => $requestId
                ]);
                
                Log::debug('TranzakClient: Payment status response', [
                    'status' => $response->status(),
                    'success' => $response->successful(),
                    'body' => $response->json(),
                ]);
                
                if (!$response->successful()) {
                    $errorBody = $response->json();
                    throw new TranzakApiException(
                        "Failed to get payment status: " . ($errorBody['errorMsg'] ?? $errorBody['message'] ?? $response->body()),
                        $response->status()
                    );
                }
                
                $body = $response->json();

                // Tranzak may return HTTP 200 with success=false; always check success flag per documentation
                if (isset($body['success']) && $body['success'] === false) {
                    throw new TranzakApiException(
                        "Failed to get payment status: " . ($body['errorMsg'] ?? 'Unknown error'),
                        $body['errorCode'] ?? 500
                    );
                }
                
                // Tranzak API wraps the response in a 'data' key
                $responseData = isset($body['data']) && is_array($body['data']) ? $body['data'] : $body;
                
                // Validate response structure
                if (!isset($responseData['status'])) {
                    Log::error('TranzakClient: Invalid status response structure', [
                        'body' => $body,
                    ]);
                    throw new TranzakApiException(
                        "Invalid response structure from Tranzak API",
                        500
                    );
                }
                
                return $responseData;
            });
        } catch (TranzakApiException $e) {
            // Re-throw TranzakApiException as-is
            throw $e;
        } catch (Exception $e) {
            Log::error('TranzakClient: Failed to get payment status', [
                'error' => $e->getMessage(),
                'request_id' => $requestId,
            ]);
            
            throw new TranzakApiException(
                'Failed to get payment status from Tranzak: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    /**
     * Retry an operation with exponential backoff
     * 
     * Implements retry logic with configurable delays between attempts.
     * This provides resilience against transient network failures and
     * API rate limiting.
     * 
     * @param callable $operation The operation to retry
     * 
     * @return mixed The result of the operation
     * 
     * @throws Exception If all retry attempts fail
     */
    private function retryWithBackoff(callable $operation): mixed
    {
        $attempt = 0;
        $lastException = null;
        
        while ($attempt < $this->retryAttempts) {
            try {
                return $operation();
            } catch (Exception $e) {
                $lastException = $e;
                $attempt++;
                
                if ($attempt >= $this->retryAttempts) {
                    Log::error('TranzakClient: All retry attempts exhausted', [
                        'attempts' => $attempt,
                        'error' => $e->getMessage(),
                    ]);
                    break;
                }
                
                // Get delay for this attempt (use last delay if we exceed array bounds)
                $delay = $this->retryDelays[$attempt - 1] ?? $this->retryDelays[count($this->retryDelays) - 1];
                
                Log::warning('Tranzak operation failed, retrying', [
                    'gateway' => 'tranzak',
                    'attempt' => $attempt,
                    'max_attempts' => $this->retryAttempts,
                    'delay_seconds' => $delay,
                    'error' => $e->getMessage(),
                ]);
                
                sleep($delay);
            }
        }
        
        throw $lastException;
    }

    /**
     * Get Tranzak API authentication token
     * 
     * @return string Authentication token
     * @throws TranzakApiException If token request fails
     */
    private function getToken(): string
    {
        // Cache token for 50 minutes (tokens usually expire in 60 minutes)
        // Ensure cache key is safe and robust
        $cacheKey = 'tranzak_token_' . md5($this->appId);
        
        return Cache::remember($cacheKey, 3000, function () {
            Log::debug('TranzakClient: Requesting new authentication token');
            
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/auth/token", [
                    'appId' => $this->appId,
                    'appKey' => $this->apiKey,
                ]);
                
            if (!$response->successful()) {
                Log::error('TranzakClient: Failed to get token', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                
                throw new TranzakApiException(
                    "Failed to authenticate with Tranzak API",
                    $response->status()
                );
            }
            
            $data = $response->json();
            
            if (empty($data['data']['token'])) {
                Log::error('TranzakClient: Invalid token response structure', [
                    'response' => $data,
                ]);
                throw new TranzakApiException(
                    "Invalid token format from Tranzak API",
                    500
                );
            }
            
            return $data['data']['token'];
        });
    }
}
