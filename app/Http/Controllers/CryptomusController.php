<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\TransactionCrypto; // Assuming this model exists for crypto transactions
use App\Models\AccountBallance; // Assuming this model exists for user balance
use App\Models\User; // Assuming User model exists
use App\Models\Bill; // Assuming Bill model exists
use App\Models\CryptoPayment; // Assuming CryptoPayment model exists
use Carbon\Carbon;
use App\Services\TelegramService;

class CryptomusController extends Controller
{
    protected $merchantId;
    protected $apiKey;
    protected $baseUrl;

    public function __construct()
    {
        $data = CryptoPayment::where('name', 'cryptomus')->first();
        if ($data != null) {
            $this->merchantId = $data->password;
            $this->apiKey = $data->api_key;
        }
        // use sandbox
        // $this->baseUrl = "https://sandbox.cryptomus.com/api/v2";
        // $this->merchantId = config('cryptomus.merchant_id');
        // $this->apiKey = config('cryptomus.api_key');
        $this->baseUrl = config('cryptomus.base_url');

        if (!$this->merchantId || !$this->apiKey) {
            Log::error('Cryptomus credentials (merchant_id or api_key) are not set in the config file.');
            // Consider throwing an exception or handling this more gracefully
        }
    }

    /**
     * Create a new payment invoice with Cryptomus.
     *
     * @param Request $request Expected parameters: amount, currency (e.g., USD, USDT), order_id, url_callback, url_success, url_return
     * @return \Illuminate\Http\JsonResponse
     */
    public function createPayment(Request $request)
    {
        // Basic validation (you should enhance this based on your needs)
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.1', // Adjust min based on Cryptomus limits
            // 'currency' => 'nullable|string',
            'order_id' => 'required|string|unique:transaction_cryptos,order_id', // Ensure order_id is unique in your system
            'account_id' => 'required|integer|exists:users,account_id', // Assuming account_id refers to user ID
            // Add other necessary fields like url_callback, url_success, url_return if needed directly from request
        ]);

        $payload = [
            'amount' => (string) $validated['amount'], // Amount must be a string
            'order_id' => $validated['order_id'],
            'currency' => 'usd',
            // 'network' => 'TRON', // Optional: Specify network (e.g., TRON, BSC)
            'url_callback' => route('cryptomus.callback'), // URL for receiving payment status updates
            'url_success' => route('payment.success'), // Redirect URL after successful payment (adjust route name)
            'url_return' => route('payment.return'), // Redirect URL if user cancels or returns (adjust route name)
            // 'to_currency' => 'USD', // Optional: Currency to receive funds in (if different from 'currency')
            'lifetime' => config('cryptomus.payment_timeout', 3600), // Optional: Invoice lifetime in seconds
            // 'is_payment_multiple' => false, // Optional: Allow multiple payments for one invoice
        ];

        try {
            $response = Http::withHeaders([
                'merchant' => $this->merchantId,
                'sign' => $this->generateSignature(json_encode($payload), $this->apiKey),
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/payment', $payload);

            $result = $response->json();

            if ($response->successful() && isset($result['result'])) {
                // Payment created successfully
                Log::info('Cryptomus payment created successfully:', $result['result']);
                $paidLink = $result['result']['url'];
                $user = User::where('account_id', $validated['account_id'])->first();
                $crypto_payment_id = CryptoPayment::where('name', 'cryptomus')->first()->id;
                // Store transaction details in your database
                TransactionCrypto::create([
                    'account_id' => $validated['account_id'],
                    'order_id' => $validated['order_id'],
                    'payment_id' => $result['result']['uuid'], // Cryptomus payment UUID
                    'amount' => $validated['amount'],
                    'currency' => 'usd',
                    'status' => $result['result']['status'], // e.g., 'pending', 'paid'
                    'payment_url' => $result['result']['url'],
                    'gateway' => 'cryptomus',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'username' => $user->username ?? "admin",
                    'crypto_payment_id' => $crypto_payment_id,
                ]);

                // Return the payment URL or other relevant info to the frontend/user
                // return response()->json([
                //     'success' => true,
                //     'payment_url' => $result['result']['url'],
                //     'payment_uuid' => $result['result']['uuid'],
                // ]);
                return $paidLink;

            } else {
                // Error creating payment
                Log::error('Cryptomus payment creation failed:', [
                    'status_code' => $response->status(),
                    'response_body' => $result,
                    'payload' => $payload,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to create Cryptomus payment.',
                ], $response->status());
            }

        } catch (\Exception $e) {
            Log::error('Cryptomus API request exception:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while communicating with Cryptomus.',
            ], 500);
        }
    }

    /**
     * Handle the callback from Cryptomus when payment status changes.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleCallback(Request $request)
    {
        Log::info('Cryptomus callback received:', $request->all());

        $receivedSign = $request->header('sign');
        $payload = $request->getContent(); // Get raw payload

        // 1. Verify the signature (IMPORTANT!)
        // $expectedSign = $this->generateSignature($payload, $this->apiKey);

        // if ($receivedSign !== $expectedSign) {
        //     Log::warning('Cryptomus callback signature mismatch.', [
        //         'received_sign' => $receivedSign,
        //         'expected_sign' => $expectedSign,
        //         'payload' => $payload,
        //     ]);
        //     return response()->json(['message' => 'Invalid signature'], 400);
        // }

        // Decode the JSON payload AFTER signature verification
        $data = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Cryptomus callback JSON decode error:', ['payload' => $payload]);
            return response()->json(['message' => 'Invalid JSON payload'], 400);
        }


        // 2. Find the transaction in your database
        $transaction = TransactionCrypto::Where('order_id', $data['order_id'] ?? null)
            ->first();

        if (!$transaction) {
            Log::error('Cryptomus callback: Transaction not found.', [
                'payment_uuid' => $data['uuid'] ?? null,
                'order_id' => $data['order_id'] ?? null,
            ]);
            // Respond with success even if not found locally to prevent Cryptomus retries for non-existent orders
            return response()->json(['message' => 'Transaction not found locally, but acknowledged.'], 200);
        }

        // 3. Check if the transaction is already processed
        if ($transaction->status === 'paid' || $transaction->status === 'confirmed') {
            Log::info('Cryptomus callback: Transaction already processed.', ['transaction_id' => $transaction->id, 'status' => $transaction->status]);
            return response()->json(['message' => 'Transaction already processed.'], 200);
        }


        // 4. Update transaction status based on callback data
        $newStatus = strtolower($data['status']); // e.g., 'paid', 'paid_over', 'wrong_amount', 'cancel', 'fail', 'system_fail'
        $transaction->status = $newStatus;
        $transaction->callback_data = json_encode($data); // Store full callback data if needed
        $transaction->updated_at = Carbon::now();
        $transaction->save();

        Log::info('Cryptomus transaction status updated:', [
            'transaction_id' => $transaction->id,
            'new_status' => $newStatus,
        ]);

        // 5. Process the payment if it's successful ('paid' or potentially 'paid_over')
        if ($newStatus === 'paid' || $newStatus === 'paid_over') {
            // --- Your Business Logic Here ---
            // - Verify amount if necessary (check $data['amount'] against $transaction->amount)
            // - Credit user's account balance
            // - Fulfill the order (e.g., activate VPN service)
            // - Send notifications

            try {
                // Example: Update user balance (adjust based on your models and logic)
                $user = User::where('account_id', $transaction->account_id)->first();
                if ($user) {
                    // Assuming you store balance in a separate model or directly on user
                    // Adjust logic based on whether the amount is in USD or crypto equivalent
                    $amountToAdd = $data['amount'] ?? $transaction->amount_dollar; // Use merchant_amount if available (amount after fees in your receiving currency)

                    // Find or create account balance record
                    $accountBalance = AccountBallance::firstOrCreate(
                        ['account_id' => $user->account_id],
                        ['ballance' => 0, 'account_ballance_in_dollar' => 0] // Default values if creating new
                    );

                    // Assuming the payment was for dollar balance
                    $accountBalance->account_ballance_in_dollar += (float) $amountToAdd;
                    $accountBalance->save();
                    try {
                        Log::info('User balance updated successfully for Cryptomus payment.', [
                            'account_id' => $user->account_id,
                            'transaction_id' => $transaction->id,
                            'amount_added' => $amountToAdd,
                        ]);
                    } catch (\Exception $e) {
                        \Log::info("error in save log on cryptomus");
                    }

                    // Mark the associated Bill as paid (if applicable)
                    $bill = Bill::where('bill_id', $transaction->order_id)->first();
                    if ($bill) {
                        $bill->status = 'paid';
                        $bill->save();
                        Log::info('Associated bill marked as paid.', ['bill_id' => $bill->id, 'invoiceID' => $bill->invoiceID]);
                    }
                    // send notification to user by telegram
                    // create new telegram service
                    $telegramService = new TelegramService();
                    $text = "کیف پول شما به مقدار {$amountToAdd} دلار افزایش یافت";
                    $telegramService->sendMessage($user->account_id, $text);



                } else {
                    Log::error('Cryptomus callback: User not found for transaction.', ['user_id' => $transaction->account_id]);
                }

                // --- End Your Business Logic ---

                // Mark transaction as fully confirmed internally after processing
                $transaction->status = 'confirmed'; // Use a distinct status for fully processed payments
                $transaction->save();

            } catch (\Exception $e) {
                Log::error('Error processing successful Cryptomus payment:', [
                    'transaction_id' => $transaction->id,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Even if internal processing fails, acknowledge the callback to Cryptomus
                return response()->json(['message' => 'Callback acknowledged, internal processing error.'], 500); // Or 200 if you prefer
            }
        } else {
            // Handle other statuses (failed, cancelled, etc.) if needed
            Log::warning('Cryptomus payment status is not "paid":', [
                'transaction_id' => $transaction->id,
                'status' => $newStatus,
            ]);
            // Mark the associated Bill as failed/cancelled (if applicable)
            $bill = Bill::where('bill_id', $transaction->order_id)->first();
            if ($bill && ($newStatus === 'cancel' || $newStatus === 'fail' || $newStatus === 'system_fail')) {
                $bill->status = $newStatus; // Or map to your own 'failed'/'cancelled' status
                $bill->save();
                Log::info('Associated bill marked as failed/cancelled.', ['bill_id' => $bill->id, 'status' => $newStatus]);
            }
        }

        // 6. Respond to Cryptomus to acknowledge receipt
        return response()->json(['message' => 'Callback received and processed.'], 200);
    }

    /**
     * Generate the signature required by Cryptomus API.
     *
     * @param string $data JSON encoded payload
     * @param string $apiKey Your API Key
     * @return string The generated signature
     */
    private function generateSignature(string $data, string $apiKey): string
    {
        return md5(base64_encode($data) . $apiKey);
    }

    // --- Placeholder routes for success/return URLs ---
    // You should implement these properly based on your application flow

    public function paymentSuccess(Request $request)
    {
        // Logic for successful payment redirection
        // Maybe fetch transaction details based on order_id or payment_uuid from session/request
        Log::info('User redirected to payment success page.', $request->all());
        // You might want to show a success message to the user
        // IMPORTANT: Do NOT confirm the payment here. Rely on the callback for confirmation.
        return response("Payment successful! Please wait for confirmation.", 200); // Placeholder
    }

    public function paymentReturn(Request $request)
    {
        // Logic for payment cancellation or return redirection
        Log::info('User redirected to payment return/cancel page.', $request->all());
        // You might want to show a cancellation message
        return response("Payment cancelled or returned.", 200); // Placeholder
    }
}
