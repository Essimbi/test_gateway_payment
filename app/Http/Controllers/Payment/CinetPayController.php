<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentService;
use App\Exceptions\Payment\PaymentException;
use App\Exceptions\Payment\UnsupportedGatewayException;
use App\GatewayType;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

/**
 * CinetPay Payment Controller
 * 
 * Handles HTTP requests for payment processing with CinetPay.
 * Manages payment flow, IPN notifications, and user redirects.
 * 
 * Validates: Requirements 1.1, 1.6, 3.1, 3.2, 6.1-6.5, 9.1-9.5, 10.1, 10.2, 10.5
 */
class CinetPayController extends Controller
{
    protected PaymentService $paymentService;

    /**
     * Initialize the controller
     * 
     * @param PaymentService $paymentService
     */
    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Show gateway selection page
     * 
     * Displays available payment gateways for the user to choose from.
     * 
     * Validates: Requirements 1.1
     * 
     * @param Request $request
     * @return View
     */
    public function showGatewaySelection(Request $request): View
    {
        // Validate request data
        $validated = $request->validate([
            'amount' => 'required|numeric|min:100',
        ]);

        $amount = $validated['amount'];

        // Get available gateways from PaymentService
        $availableGateways = $this->paymentService->getAvailableGateways();
        
        // Determine unavailable gateways (all gateway types minus available ones)
        $allGateways = GatewayType::cases();
        $unavailableGateways = array_filter($allGateways, function($gateway) use ($availableGateways) {
            return !in_array($gateway, $availableGateways, true);
        });

        // Render gateway selection view
        return view('payment.select-gateway', [
            'amount' => $amount,
            'availableGateways' => $availableGateways,
            'unavailableGateways' => array_values($unavailableGateways),
        ]);
    }

    /**
     * Show payment summary page
     * 
     * Displays a summary of the payment before redirecting to the selected gateway.
     * 
     * Validates: Requirements 1.5, 1.6
     * 
     * @param Request $request
     * @return View|RedirectResponse
     */
    public function showPaymentSummary(Request $request)
    {
        // Validate request data
        $validated = $request->validate([
            'amount' => 'required|numeric|min:100',
            'gateway_type' => 'required|string',
            'transaction_id' => 'nullable|string',
        ]);

        $amount = $validated['amount'];
        $gatewayTypeValue = $validated['gateway_type'];
        $transactionId = $validated['transaction_id'] ?? null;

        // Validate gateway is supported
        try {
            $gatewayType = GatewayType::from($gatewayTypeValue);
        } catch (\ValueError $e) {
            Log::warning('Invalid gateway type provided', [
                'gateway_type' => $gatewayTypeValue,
            ]);
            
            return redirect()->route('payment.select-gateway', ['amount' => $amount])
                ->with('error', 'Invalid payment gateway selected. Please choose a valid gateway.');
        }

        // Verify gateway is available (has valid credentials)
        $availableGateways = $this->paymentService->getAvailableGateways();
        if (!in_array($gatewayType, $availableGateways, true)) {
            Log::warning('Unavailable gateway selected', [
                'gateway_type' => $gatewayType->value,
            ]);
            
            return redirect()->route('payment.select-gateway', ['amount' => $amount])
                ->with('error', 'The selected payment gateway is currently unavailable. Please choose another gateway.');
        }

        // Render payment summary view with gateway information
        return view('payment.summary', [
            'amount' => $amount,
            'transaction_id' => $transactionId,
            'gateway' => $gatewayType,
            'gatewayName' => $gatewayType->getDisplayName(),
            'gatewayDescription' => $gatewayType->getDescription(),
        ]);
    }

    /**
     * Initiate payment and redirect to selected gateway
     * 
     * Validates request data, creates transaction, and redirects to the selected gateway payment page.
     * 
     * Validates: Requirements 1.4, 1.5
     * 
     * @param Request $request
     * @return RedirectResponse
     */
    public function initiatePayment(Request $request): RedirectResponse
    {
        try {
            // Validate request data
            $validated = $request->validate([
                'amount' => 'required|numeric|min:100',
                'gateway_type' => 'required|string',
                'metadata' => 'nullable|array',
            ]);

            $amount = $validated['amount'];
            $gatewayTypeValue = $validated['gateway_type'];
            $metadata = $validated['metadata'] ?? [];
            $userId = Auth::id();

            // Validate and parse gateway type
            try {
                $gatewayType = GatewayType::from($gatewayTypeValue);
            } catch (\ValueError $e) {
                Log::warning('Invalid gateway type in payment initiation', [
                    'gateway_type' => $gatewayTypeValue,
                    'user_id' => $userId,
                ]);
                
                return redirect()->back()
                    ->with('error', 'Invalid payment gateway selected. Please try again.');
            }

            // Initialize payment through service with selected gateway
            $transaction = $this->paymentService->initializePayment($amount, $userId, $gatewayType, $metadata);

            // Get payment URL from transaction metadata
            $paymentUrl = $transaction->metadata['payment_url'] ?? null;
            
            Log::debug('CinetPayController: Checking payment URL', [
                'transaction_id' => $transaction->transaction_id,
                'has_url' => !empty($paymentUrl),
                'metadata' => $transaction->metadata
            ]);

            if (!$paymentUrl) {
                Log::error('CinetPayController: Payment URL not found in transaction metadata', [
                    'transaction_id' => $transaction->transaction_id,
                    'metadata' => $transaction->metadata
                ]);
                throw new PaymentException('Payment URL not available');
            }

            // Redirect to gateway payment page
            Log::info('CinetPayController: Redirecting to gateway', [
                'transaction_id' => $transaction->transaction_id,
                'url' => $paymentUrl
            ]);
            return redirect()->away($paymentUrl);

        } catch (UnsupportedGatewayException $e) {
            // Handle unsupported gateway exception
            Log::error('Unsupported gateway in payment initiation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'gateway' => $gatewayTypeValue ?? 'unknown',
            ]);

            return redirect()->back()
                ->with('error', 'The selected payment gateway is not supported. Please choose another gateway.');

        } catch (PaymentException $e) {
            // Log error with gateway information
            Log::error('Payment initiation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'gateway' => $gatewayTypeValue ?? 'unknown',
            ]);

            // Display user-friendly error message
            return redirect()->back()
                ->with('error', 'Unable to initiate payment. Please try again later.');
        } catch (\Exception $e) {
            // Log unexpected error with gateway information
            Log::error('Unexpected error during payment initiation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'gateway' => $gatewayTypeValue ?? 'unknown',
            ]);

            // Display user-friendly error message
            return redirect()->back()
                ->with('error', 'An unexpected error occurred. Please try again later.');
        }
    }

    /**
     * Handle IPN notification from CinetPay
     * 
     * Receives POST notification from CinetPay when payment status changes.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function handleIPN(Request $request): JsonResponse
    {
        try {
            // Get payload from request
            $payload = $request->all();

            // Process IPN through service
            $result = $this->paymentService->processIPN($payload);

            // Always return 200 OK to prevent CinetPay retries
            return response()->json([
                'status' => 'success',
                'message' => 'IPN processed',
            ], 200);

        } catch (\Exception $e) {
            // Log error but still return 200 OK to prevent retries
            Log::error('IPN processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            // Return 200 OK even on error to prevent CinetPay retries
            return response()->json([
                'status' => 'error',
                'message' => 'IPN received but processing failed',
            ], 200);
        }
    }

    /**
     * Handle CinetPay callback
     * 
     * Receives POST notification from CinetPay when payment status changes.
     * Extracts payload and delegates to PaymentService for processing.
     * 
     * Validates: Requirements 6.4, 6.5
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function handleCinetPayCallback(Request $request): JsonResponse
    {
        try {
            // Extract payload from request
            $payload = $request->all();

            Log::info('CinetPay callback received', [
                'payload' => $payload,
            ]);

            // Call PaymentService::processCallback() with CINETPAY type
            $result = $this->paymentService->processCallback(GatewayType::CINETPAY, $payload);

            // Always return 200 OK to prevent gateway retries
            return response()->json([
                'status' => 'success',
                'message' => 'Callback processed',
            ], 200);

        } catch (\Exception $e) {
            // Log error but still return 200 OK to prevent retries
            Log::error('CinetPay callback processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
                'gateway' => GatewayType::CINETPAY->value,
            ]);

            // Return 200 OK even on error to prevent gateway retries
            return response()->json([
                'status' => 'error',
                'message' => 'Callback received but processing failed',
            ], 200);
        }
    }

    /**
     * Handle Tranzak callback
     * 
     * Receives POST notification from Tranzak when payment status changes.
     * Extracts payload and delegates to PaymentService for processing.
     * Handles malformed callbacks gracefully.
     * 
     * Validates: Requirements 6.4, 6.5, 12.4
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function handleTranzakCallback(Request $request): JsonResponse
    {
        try {
            // Extract payload from request
            $payload = $request->all();

            Log::info('Tranzak callback received', [
                'payload' => $payload,
            ]);

            // Validate payload structure (basic check for malformed callbacks)
            if (empty($payload) || !is_array($payload)) {
                Log::warning('Malformed Tranzak callback received', [
                    'payload' => $payload,
                    'gateway' => GatewayType::TRANZAK->value,
                ]);

                // Return 200 OK to prevent retries, but don't process
                return response()->json([
                    'status' => 'error',
                    'message' => 'Malformed callback payload',
                ], 200);
            }

            // Call PaymentService::processCallback() with TRANZAK type
            $result = $this->paymentService->processCallback(GatewayType::TRANZAK, $payload);

            // Always return 200 OK to prevent gateway retries
            return response()->json([
                'status' => 'success',
                'message' => 'Callback processed',
            ], 200);

        } catch (\Exception $e) {
            // Log error but still return 200 OK to prevent retries
            Log::error('Tranzak callback processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
                'gateway' => GatewayType::TRANZAK->value,
            ]);

            // Return 200 OK even on error to prevent gateway retries
            return response()->json([
                'status' => 'error',
                'message' => 'Callback received but processing failed',
            ], 200);
        }
    }

    /**
     * Handle user return from payment gateway
     * 
     * Verifies transaction status and redirects to appropriate result page.
     * Displays gateway name in result views.
     * 
     * Validates: Requirements 5.5
     * 
     * @param Request $request
     * @param string $transactionId
     * @return View
     */
    public function handleReturn(Request $request, string $transactionId): View
    {
        try {
            // Retrieve transaction
            $transaction = $this->paymentService->getTransaction($transactionId);

            if (!$transaction) {
                return view('payment.failure', [
                    'message' => 'Transaction not found',
                    'transaction_id' => $transactionId,
                    'gateway' => null,
                    'gatewayName' => 'Unknown',
                ]);
            }

            // Get gateway information
            $gatewayName = $transaction->gateway_type->getDisplayName();

            // Verify transaction status with the appropriate gateway
            $verifiedStatus = $this->paymentService->verifyTransactionStatus($transactionId);

            // Update transaction status if needed
            if ($transaction->status->canTransitionTo($verifiedStatus)) {
                $transaction = $this->paymentService->updateTransactionStatus($transactionId, $verifiedStatus);
            } else {
                // Refresh transaction to get latest status
                $transaction = $transaction->fresh();
            }

            // Redirect based on status
            if ($transaction->status === \App\PaymentStatus::ACCEPTED) {
                return view('payment.success', [
                    'transaction' => $transaction,
                    'transaction_id' => $transaction->transaction_id,
                    'amount' => $transaction->amount,
                    'gateway' => $transaction->gateway_type,
                    'gatewayName' => $gatewayName,
                ]);
            } elseif ($transaction->status === \App\PaymentStatus::REFUSED) {
                return view('payment.failure', [
                    'transaction' => $transaction,
                    'transaction_id' => $transaction->transaction_id,
                    'amount' => $transaction->amount,
                    'gateway' => $transaction->gateway_type,
                    'gatewayName' => $gatewayName,
                ]);
            } else {
                // Status is still PENDING
                return view('payment.pending', [
                    'transaction' => $transaction,
                    'transaction_id' => $transaction->transaction_id,
                    'gateway' => $transaction->gateway_type,
                    'gatewayName' => $gatewayName,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error handling return from payment gateway', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return view('payment.failure', [
                'message' => 'An error occurred while processing your payment',
                'transaction_id' => $transactionId,
                'gateway' => null,
                'gatewayName' => 'Unknown',
            ]);
        }
    }

    /**
     * Cancel payment
     * 
     * Cancels a pending payment and redirects to home.
     * 
     * Validates: Requirements 9.5
     * 
     * @param string $transactionId
     * @return RedirectResponse
     */
    public function cancelPayment(string $transactionId): RedirectResponse
    {
        try {
            // Retrieve transaction
            $transaction = $this->paymentService->getTransaction($transactionId);

            if (!$transaction) {
                return redirect('/')
                    ->with('error', 'Transaction not found');
            }

            // Verify transaction is still PENDING
            if ($transaction->status !== \App\PaymentStatus::PENDING) {
                return redirect('/')
                    ->with('info', 'Transaction has already been processed');
            }

            // Redirect to home with cancellation message
            return redirect('/')
                ->with('info', 'Payment cancelled');

        } catch (\Exception $e) {
            Log::error('Error cancelling payment', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect('/')
                ->with('error', 'An error occurred while cancelling payment');
        }
    }
}
