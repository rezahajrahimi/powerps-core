<?php

namespace App\Http\Controllers;

use App\Models\TransactionCrypto;
use Illuminate\Http\Request;
use App\Models\CryptoPayment;
use Illuminate\Support\Facades\Config; // Added Config facade
use Illuminate\Support\Facades\Log; // Added Log facade
use Illuminate\Support\Facades\DB; // Added DB facade for potential transactions
use Illuminate\Validation\ValidationException;
use PrevailExcel\Nowpayments\Facades\Nowpayments;
use App\Models\User; // Added User model
use App\Models\Bill; // Added Bill model
use App\Http\Controllers\BillController; // Added BillController
use App\Http\Controllers\CryptoPaymentController; // Added CryptoPaymentController
use App\Http\Controllers\SettingController; // Added SettingController
use App\Http\Controllers\NowPaymentsController; // Added NowPaymentsController
use App\Http\Controllers\AccountBallanceController; // Added AccountBallanceController
use App\Http\Controllers\CryptomusController; // Added CryptomusController


class TransactionCryptoController extends Controller
{
    // Removed public properties $amount_dollar, $account_id as they are passed to methods now

    // New method to handle gateway selection
    public function initiateCryptoPayment(Request $request)
    {
        try {
            $validated = $request->validate([
                'gateway' => 'required|string|in:nowpayments,cryptomus,swappay',
                'invoiceID' => 'required|exists:bills,bill_id',
                'account_id' => 'required|exists:users,account_id',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'اطلاعات پرداخت نامعتبر است.',
            ], 422);
        }

        $gateway = $validated['gateway'];
        $invoiceID = $validated['invoiceID'];
        $accountId = $validated['account_id'];

        // Get amount from bill
        $billController = new BillController();
        $amountDollar = $billController->getBillAmountDollarByBillId($invoiceID);

        if ($amountDollar === null || $amountDollar <= 0) {
            // Consider logging this issue
            Log::warning("Invalid amount or invoice not found for initiateCryptoPayment.", ['invoiceID' => $invoiceID, 'amount' => $amountDollar]);
            return response()->json(['success' => false, 'message' => 'این صورتحساب موجود نمی باشد یا مبلغ آن نامعتبر است.'], 404);
        }

        // --- Database Transaction Start (Optional but Recommended) ---
        // DB::beginTransaction();
        // try {

        if ($gateway === 'nowpayments') {
            // Call the refactored NowPayments logic
            $response = $this->createNowPaymentsInvoice($accountId, $invoiceID, $amountDollar);
        } elseif ($gateway === 'cryptomus') {
            // Prepare request for CryptomusController
            $cryptomusCurrency = $validated['currency'] ?? 'USDT'; // Default to USDT if not provided
            // convert $amountDollar to numberic
            $amountDollar = (float) $amountDollar;
            $cryptomusRequest = new Request([
                'amount' => $amountDollar,
                'currency' => $cryptomusCurrency,
                'order_id' => "$invoiceID", // Using the same invoiceID
                'account_id' => $accountId,
            ]);

            $cryptomusController = new CryptomusController();
            $response = $cryptomusController->createPayment($cryptomusRequest);
        } elseif ($gateway === 'swappay') {
            $amountDollar = (float) $amountDollar;
            $swapPayRequest = new Request([
                'amount' => $amountDollar,
                'order_id' => (string) $invoiceID,
                'account_id' => $accountId,
                'preferred_link' => $request->input('preferred_link', 'WEBSITE'),
            ]);
            $swapPayController = new SwapPayController();
            $response = $swapPayController->createPayment($swapPayRequest);
        } else {
            // DB::rollBack(); // Rollback if using DB transaction
            return response()->json(['success' => false, 'message' => 'درگاه پرداخت نامعتبر است.'], 400);
        }

        // Check if the gateway call was successful before committing
        // This depends on the structure of the response from createNowPaymentsInvoice and createPayment
        // Example check assuming JsonResponse with a 'success' key in data
        // if ($response instanceof \Illuminate\Http\JsonResponse) {
        //     $responseData = $response->getData();
        //     if (isset($responseData->success) && !$responseData->success) {
        //          DB::rollBack();
        //          return $response; // Return the error response from the gateway controller
        //     }
        // } elseif ($response->isRedirect()) {
        //     // Handle redirects if NowPayments returns a redirect
        // } else {
        //     // Handle unexpected response types
        //     DB::rollBack();
        //     Log::error('Unexpected response type from gateway controller.', ['gateway' => $gateway, 'invoiceID' => $invoiceID]);
        //     return response()->json(['success' => false, 'message' => 'خطای غیرمنتظره در پردازش پاسخ درگاه.'], 500);
        // }


        // DB::commit();
        return $response; // Return the success response (e.g., with payment URL or redirect)

        // } catch (\Exception $e) {
        //     DB::rollBack();
        //     Log::error("Error initiating crypto payment for invoice {$invoiceID}", [
        //         'gateway' => $gateway,
        //         'error' => $e->getMessage(),
        //         'trace' => $e->getTraceAsString() // Limit trace length if needed
        //     ]);
        //     return response()->json(['success' => false, 'message' => 'خطا در شروع فرآیند پرداخت.'], 500);
        // }
        // --- Database Transaction End ---
    }

    // Refactored NowPayments logic
    private function createNowPaymentsInvoice($accountId, $invoiceID, $amountDollar)
    {
        $this->changeNowPaymentDataIfNeeded(); // Ensure NowPayments config is set

        $cryptoPaymentCtrl = new CryptoPaymentController();
        // Fetch settings only if needed, maybe cache them?
        $nowpaymentSettings = $cryptoPaymentCtrl->getNovPaymentData();
        if (!$nowpaymentSettings) {
            Log::error('Failed to fetch NowPayments settings for invoice creation.', ['invoiceID' => $invoiceID]);
            return response()->json(['success' => false, 'message' => 'خطا در خواندن تنظیمات NowPayments.'], 500);
        }

        $settingCntrl = new SettingController();
        $mainUrl = $settingCntrl->getMainUrl(); // Ensure this returns the correct base URL

        // Assuming NowPaymentsController exists and works as intended
        // If NowPaymentsController doesn't exist, you might need to use the PrevailExcel\Nowpayments facade directly
        if (!class_exists(NowPaymentsController::class)) {
            Log::error('NowPaymentsController class not found.');
            // Fallback or error handling needed here
            // Maybe use the facade directly? Example:
            // $payment = Nowpayments::createInvoice([...]);
            return response()->json(['success' => false, 'message' => 'کنترلر NowPayments یافت نشد.'], 500);
        }
        $npwPaymentCntrl = new NowPaymentsController();


        $req = new Request();
        $req->amount = $amountDollar;
        $req->order_id = $invoiceID;
        $req->order_description = "invoice {$invoiceID}";
        // Use route() helper for URLs if defined, otherwise url()
        $req->ipn_callback_url = url('/payback'); // Using url() helper as route name wasn't defined
        $req->success_url = url('/payback');
        $req->cancel_url = url('/cancelpay');
        $req->is_fixed_rate = $nowpaymentSettings->is_fixed_rate ?? false;
        $req->is_fee_paid_by_user = $nowpaymentSettings->is_fee_paid_by_user ?? false;

        // Save/Update initial transaction record
        try {
            // Use updateOrCreate to handle potential duplicate submissions gracefully
            $transaction = TransactionCrypto::updateOrCreate(
                ['order_id' => $invoiceID, 'gateway' => 'nowpayments'],
                [
                    'account_id' => $accountId, // Use user_id consistently
                    'username' => User::find($accountId)->username ?? '', // Fetch username
                    'crypto_payment_id' => $cryptoPaymentCtrl->getNowPaymentID(), // What ID is this? NowPayments payment_id comes later. Store temporarily?
                    'amount_dollar' => $amountDollar, // Keep original column name for now
                    'currency' => 'USD', // Assuming NowPayments amount is in USD
                    'confirmed' => false, // Use boolean false
                    'status' => 'pending', // Initial status
                    'gateway' => 'nowpayments',
                    // Let Eloquent handle timestamps automatically by removing created_at/updated_at here
                ]
            );
            Log::info('Initial NowPayments transaction record saved/updated.', ['transaction_id' => $transaction->id, 'order_id' => $invoiceID]);
        } catch (\Exception $e) {
            Log::error('Failed to save initial NowPayments transaction', [
                'order_id' => $invoiceID,
                'error' => $e->getMessage()
            ]);
            // Don't proceed if saving fails
            return response()->json(['success' => false, 'message' => 'خطا در ذخیره تراکنش اولیه.'], 500);
        }

        // Call the NowPayments invoice creation
        try {
            // Assuming createCryptoInvoice returns the response from NowPayments API
            $nowPaymentsApiResponse = $npwPaymentCntrl->createCryptoInvoice($req);

            // Log the raw response for debugging
            // Log::info('NowPayments createCryptoInvoice API response:', ['response' => $nowPaymentsApiResponse]);

            // --- Process the response ---
            // Check if the response indicates success and contains necessary data
            // This depends heavily on the actual structure of $nowPaymentsApiResponse
            // Adjust keys based on actual response structure from PrevailExcel\laravel-nowpayments or your NowPaymentsController
            // Example: Check for common keys like 'payment_id', 'invoice_id', 'pay_address'
            $paymentIdKey = 'payment_id'; // Adjust if the key is different (e.g., 'id', 'invoice_id')
            $payAddressKey = 'pay_address'; // Adjust if the key is different


            // decode $nowPaymentsApiResponse
            // $nowPaymentsApiResponse = json_decode($nowPaymentsApiResponse, true);
            // get location from headers
            $generalCntrl = new GeneralController();
            $location = $generalCntrl->get_nowpayment_payment_link_from_html($nowPaymentsApiResponse);
            // get payment id from location
            $paymentId = explode('/', $location);
            $paymentId = $paymentId[count($paymentId) - 1];
            // remove iid= from paymentId
            $paymentId = str_replace('?iid=', '', $paymentId);


            if (isset($location)) {

                // Update the transaction record with the actual payment ID and potentially URL
                $transaction->payment_id = $paymentId; // NowPayments' unique ID for the payment
                // Construct a payment URL if not directly provided, or store relevant details
                // $transaction->payment_url = $nowPaymentsApiResponse['payment_url'] ?? null; // If URL is provided
                $transaction->save();


                // return pay url
                return $location;

                // Return a success response to your frontend, including the payment details/URL



                // return response()->json([
                //     'success' => true,
                //     'message' => 'NowPayments invoice created successfully.',
                //     'payment_id' => $nowPaymentsApiResponse[$paymentIdKey],
                //     'pay_address' => $nowPaymentsApiResponse[$payAddressKey], // Example
                //     'pay_currency' => $nowPaymentsApiResponse['pay_currency'] ?? null, // Example
                //     // Add other relevant details for the user
                // ]);

            } else {
                // Handle API error response from NowPayments
                Log::error('NowPayments API did not return expected data for invoice creation.', [
                    'order_id' => $invoiceID,
                    'response' => $nowPaymentsApiResponse
                ]);
                // Maybe attempt to update transaction status to 'failed' here
                $transaction->status = 'failed';
                $transaction->save();
                return response()->json([
                    'success' => false,
                    'message' => 'خطا در ایجاد صورتحساب NowPayments (پاسخ نامعتبر).',
                    'details' => $nowPaymentsApiResponse // Include details if helpful
                ], 500);
            }
            // --- End response processing ---

        } catch (\Exception $e) {
            Log::error('Exception during NowPayments invoice creation call', [
                'order_id' => $invoiceID,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString() // More detailed trace
            ]);
            // Update transaction status to 'failed'
            if (isset($transaction)) {
                $transaction->status = 'failed';
                $transaction->save();
            }
            return response()->json(['success' => false, 'message' => 'خطا در ارتباط با NowPayments.'], 500);
        }
    }

    // Helper to set NowPayments config if needed
    private function changeNowPaymentDataIfNeeded()
    {
        // Check if config is already set or needs refreshing
        // Consider caching the settings for a short period to avoid repeated DB calls
        if (!Config::get('nowpayments.apiKey')) {
            $cryptoPaymentCtrl = new CryptoPaymentController();
            $nowpayment = $cryptoPaymentCtrl->getNovPaymentData();
            if ($nowpayment && $nowpayment->api_key) { // Check if api_key is actually retrieved
                Config::set('nowpayments.apiKey', $nowpayment->api_key);
                // Assuming 'env' holds the URL like 'https://api.nowpayments.io/v1'
                Config::set('nowpayments.liveUrl', $nowpayment->env ?? 'https://api.nowpayments.io/v1');
                Log::info('NowPayments config set.');
            } else {
                Log::error('Failed to fetch NowPayments settings or API key is missing.');
                // Potentially throw an exception or handle this critical failure
            }
        }
    }

    // This method seems redundant now, config is set in changeNowPaymentDataIfNeeded
    /*
    public function changeNovaPaymentData()
    {
        $cryptoPaymentCtrl = new CryptoPaymentController();
        $nowpayment = $cryptoPaymentCtrl->getNovPaymentData();

        config::set('nowpayments.apiKey', $nowpayment->api_key);
        config::set('nowpayments.liveUrl', $nowpayment->env);
        return;
    }
    */

    // Keep the original method signature if it's called directly from somewhere else,
    // but maybe mark it as deprecated or make it private if initiateCryptoPayment is the new entry point.
    /*
    public function add_order_crypto_by_nowpayment(Request $request)
    {
       // Consider redirecting to initiateCryptoPayment or throwing an error
       // For now, let's assume initiateCryptoPayment is the main entry point
       Log::warning('Direct call to add_order_crypto_by_nowpayment is deprecated. Use initiateCryptoPayment.');
       // You could potentially call the new logic here for backward compatibility if needed:
       // $billController = new BillController();
       // $amountDollar = $billController->getBillAmountDollarByBillId($request->invoiceID);
       // if ($amountDollar) {
       //     return $this->createNowPaymentsInvoice($request->account_id, $request->invoiceID, $amountDollar);
       // } else {
       //     return response()->json(['success' => false, 'message' => 'صورتحساب یافت نشد.'], 404);
       // }
       return response()->json(['success' => false, 'message' => 'Method deprecated'], 400);
    }
    */

    public function orderSuccess(Request $request)
    {
        // This method handles the callback/redirect from NowPayments (via /payback route)
        Log::info('NowPayments orderSuccess callback/redirect received:', $request->all());
        $paymentId = null; // Initialize paymentId
        try {
            // Assuming transaction_id is the NowPayments payment_id (NP_id from query)
            $paymentId = $request->query('transaction_id') ?? $request->input('NP_id'); // Get NP_id from query
            // $status = $request->status; // Status might not be reliable from redirect

            if (!$paymentId) {
                Log::warning('NowPayments callback: NP_id (paymentId) missing.');
                return 'شناسه تراکنش یافت نشد.';
            }

            // Find the local transaction using the NowPayments payment_id
            // Ensure user_id is loaded if needed later
            $transaction = TransactionCrypto::where('payment_id', $paymentId)
                ->where('gateway', 'nowpayments')
                ->first();

            if (!$transaction) {
                Log::error('NowPayments callback: Local transaction not found for payment_id.', ['payment_id' => $paymentId]);
                // Avoid showing error directly, maybe redirect to a generic status page
                return 'تراکنش یافت نشد.';
            }

            // Check if already processed
            if ($transaction->status === 'confirmed') { // Use 'confirmed' as the final success state
                Log::info('NowPayments callback: Transaction already processed.', ['transaction_id' => $transaction->id]);
                return $this->orderSuccessMessage(); // Show success page again
            }

            // Validate the payment status with NowPayments API (Crucial Step!)
            // Pass the amount stored in our transaction record for validation
            $validationResult = $this->validateNowPayment($paymentId, $transaction->order_id, $transaction->amount_dollar);

            if (!$validationResult['isValid']) {
                Log::warning('NowPayments callback: Payment validation failed.', [
                    'payment_id' => $paymentId,
                    'reason' => $validationResult['reason'],
                    'api_data' => $validationResult['api_data'] ?? null
                ]);
                // Update local status to reflect failure if possible
                $transaction->status = $validationResult['status'] ?? 'failed'; // e.g., 'failed', 'expired'
                $transaction->save();
                return 'تراکنش نامعتبر است یا تایید نشد.'; // Provide a user-friendly message
            }

            // --- Payment is Valid - Process it ---
            DB::beginTransaction(); // Start DB transaction for processing
            try {
                // Update local transaction status
                // Reload the transaction within the DB transaction to avoid race conditions
                $transaction = TransactionCrypto::lockForUpdate()->find($transaction->id);

                // Double-check status again inside transaction
                if ($transaction->status === 'confirmed') {
                    DB::rollBack(); // Already processed by another request
                    Log::info('NowPayments callback: Transaction already processed (race condition check).', ['transaction_id' => $transaction->id]);
                    return $this->orderSuccessMessage();
                }

                $transaction->status = 'paid'; // Mark as paid first
                $transaction->confirmed = true; // Assuming confirmed means API verified
                $transaction->callback_data = json_encode($validationResult['api_data']); // Store validation data
                // $transaction->recipe_number = $paymentId; // recipe_number might be different concept, keep payment_id
                // Let Eloquent handle updated_at
                $transaction->save();

                // Add to user account balance
                $accBlCtrl = new AccountBallanceController();
                $userID = $transaction->user_id; // Use user_id from transaction
                $amountToAdd = $transaction->amount_dollar; // Use the amount stored in our transaction

                Log::info("Attempting to increase balance for user {$userID} by {$amountToAdd} (NowPayments)");
                $balanceUpdated = $accBlCtrl->incUserAccuntBalanceInDollar($userID, $amountToAdd);

                if (!$balanceUpdated) {
                    // Handle balance update failure
                    throw new \Exception("Failed to update balance for user {$userID}");
                }

                // Mark the associated Bill as paid
                $bill = Bill::where('invoiceID', $transaction->order_id)->first();
                if ($bill) {
                    $bill->status = 'paid';
                    $bill->save();
                    Log::info('Associated bill marked as paid (NowPayments).', ['bill_id' => $bill->id]);
                } else {
                    Log::warning('Could not find associated bill for NowPayments transaction.', ['order_id' => $transaction->order_id]);
                }

                // Send message to user
                $text = '';
                $text .= "✅ شارژ حساب شما با موفقیت انجام شد ✅\n"; // Using \n for Markdown
                $text .= "مبلغ: {$amountToAdd} دلار\n";
                $text .= "درگاه: NowPayments\n";
                $text .= "شناسه پرداخت: `{$paymentId}`\n"; // Use backticks for code formatting

                try {
                    // Ensure telegram_bot service is available
                    if (app()->has('telegram_bot')) {
                        app('telegram_bot')->sendMessage($text, $userID, null, 'Markdown');
                        Log::info('Success message sent via Telegram.', ['user_id' => $userID]);
                    } else {
                        Log::warning('Telegram bot service not available.');
                    }
                } catch (\Exception $telegramError) {
                    Log::error('Failed to send Telegram success message.', [
                        'user_id' => $userID,
                        'error' => $telegramError->getMessage()
                    ]);
                    // Continue even if Telegram fails
                }

                // Mark transaction as fully confirmed internally
                $transaction->status = 'confirmed';
                $transaction->save();

                DB::commit(); // Commit DB transaction
                Log::info('NowPayments transaction processed successfully.', ['transaction_id' => $transaction->id]);
                return $this->orderSuccessMessage(); // Show success page

            } catch (\Exception $e) {
                DB::rollBack(); // Rollback DB transaction on error
                Log::error('Error processing successful NowPayments callback:', [
                    'payment_id' => $paymentId,
                    'transaction_id' => $transaction->id ?? null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Don't show detailed error to user
                return 'خطا در پردازش داخلی تراکنش.';
            }
            // --- End Processing ---

        } catch (\Exception $e) { // Catch broader exceptions
            Log::error('General error in NowPayments orderSuccess:', [
                'payment_id' => $paymentId ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 'خطا در انجام عملیات.';
        }
    }

    // Renamed from isvalidPayment to be more descriptive
    // Returns array: ['isValid' => bool, 'reason' => string, 'status' => string|null, 'api_data' => array|null]
    private function validateNowPayment($paymentId, $expectedOrderId, $expectedAmount)
    {
        $this->changeNowPaymentDataIfNeeded(); // Ensure config is set
        try {
            // Ensure Nowpayments facade is correctly configured and working
            if (!class_exists(Nowpayments::class)) {
                Log::error('Nowpayments facade/class not found.');
                return ['isValid' => false, 'reason' => 'Nowpayments library not configured correctly', 'status' => 'failed', 'api_data' => null];
            }

            $data = Nowpayments::getPaymentStatus($paymentId);
            Log::info('NowPayments getPaymentStatus API response:', ['payment_id' => $paymentId, 'response' => $data]);

            if (!$data || !isset($data['payment_status'])) {
                return ['isValid' => false, 'reason' => 'Invalid API response from NowPayments', 'status' => 'failed', 'api_data' => $data];
            }

            $status = $data['payment_status'];
            $receivedAmount = $data['pay_amount'] ?? null; // Amount user sent in crypto
            $orderId = $data['order_id'] ?? null;
            $priceAmount = $data['price_amount'] ?? null; // Amount in original currency (e.g., USD)

            // 1. Check Order ID match
            if ($orderId !== $expectedOrderId) {
                return ['isValid' => false, 'reason' => 'Order ID mismatch', 'status' => 'failed', 'api_data' => $data];
            }

            // 2. Check Status (consider 'finished', 'partially_paid' might need handling)
            // 'confirmed' and 'sending' might also be relevant depending on flow
            if ($status !== 'finished') {
                // Map other statuses if needed (e.g., 'expired', 'failed')
                $localStatus = match ($status) {
                    'waiting' => 'pending',
                    'confirming' => 'pending',
                    'confirmed' => 'pending', // Still waiting for 'finished'
                    'sending' => 'pending',
                    'partially_paid' => 'failed', // Or handle partial payments
                    'failed' => 'failed',
                    'refunded' => 'failed',
                    'expired' => 'expired',
                    default => 'failed', // Unknown status
                };
                return ['isValid' => false, 'reason' => "Payment status is not 'finished' (it's '{$status}')", 'status' => $localStatus, 'api_data' => $data];
            }

            // 3. Check Amount (Compare price_amount with expected amount in USD)
            // Use a small tolerance for floating point comparisons
            if (abs((float) $priceAmount - (float) $expectedAmount) > 0.001) {
                Log::warning('NowPayments amount mismatch.', [
                    'payment_id' => $paymentId,
                    'expected' => $expectedAmount,
                    'received_price_amount' => $priceAmount,
                    'received_pay_amount' => $receivedAmount,
                ]);
                // Decide how to handle amount mismatch (e.g., fail, partial credit)
                return ['isValid' => false, 'reason' => 'Amount mismatch', 'status' => 'failed', 'api_data' => $data];
            }

            // If all checks pass
            return ['isValid' => true, 'reason' => 'Payment validated successfully', 'status' => 'paid', 'api_data' => $data];

        } catch (\Exception $e) {
            Log::error('Exception during NowPayments validation API call', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            return ['isValid' => false, 'reason' => 'API validation exception', 'status' => 'failed', 'api_data' => null];
        }
    }


    // This method seems unused now as amount is stored in the transaction
    /*
    public function getAmountByOrderID($order_id)
    {
        // Prefer fetching from the transaction record directly if possible
        $data = TransactionCrypto::where('order_id', $order_id)->whereIn('gateway', ['nowpayments', 'cryptomus'])->first(); // Specify gateway
        \Log::info($data);

        if ($data != null) {
            return $data->amount_dollar; // Use 'amount_dollar' field
        } else {
            return 0;
        }
    }
    */

    // This method is replaced by logic within orderSuccess and validateNowPayment/handleCallback
    /*
    public function setConfirmedTransaction($recipe_number, $order_id)
    {
        // Find by payment_id (recipe_number) instead of order_id if possible
        $data = TransactionCrypto::where('payment_id', $recipe_number)
                                 // ->where('gateway', 'nowpayments') // Be careful if payment_ids can overlap
                                 ->first();
        if ($data != null) {
            // $data->recipe_number = $recipe_number; // Already have payment_id
            $data->confirmed = true;
            $data->status = 'confirmed'; // Update status as well
            // Let Eloquent handle updated_at
            $data->save(); // Use save() instead of update() on model instance
            return true;
        } else {
            Log::warning('setConfirmedTransaction: Transaction not found for payment_id', ['payment_id' => $recipe_number]);
            return false;
        }
    }
    */

    // This method seems unused now, user ID is fetched from the transaction in orderSuccess/handleCallback
    /*
    public function getUserAccountIDByRecipeId($recipe_number)
    {
        $data = TransactionCrypto::where('payment_id', $recipe_number)->first(); // Use payment_id
        if ($data != null) {
            return $data->user_id; // Use user_id field
        } else {
            return null;
        }
    }
    */

    // Replaced by validateNowPayment
    /*
    public function isvalidPayment($transactionID) { ... }
    */

    // This method seems unused now, order ID is fetched from the transaction in orderSuccess/handleCallback
    /*
    public function getOrderIdByRecipeNumber($recipe_number)
    {
        try {
            $data = TransactionCrypto::where('payment_id', $recipe_number)->first(); // Use payment_id
            return $data->order_id ?? null; // Return null if not found
        } catch (\Throwable $th) {
            \Log::error("Error in getOrderIdByRecipeNumber: " . $th->getMessage());
            return null;
        }
    }
    */

    public function orderSuccessMessage()
    {
        // retunr a html page with success purchess message
        return '<html>
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <link href="https://fonts.googleapis.com/css?family=Nunito+Sans:400,400i,700,900&display=swap" rel="stylesheet">
                    <title>Payment Success</title>
                </head>
                    <style>
                    body {
                        text-align: center;
                        padding: 40px 0;
                        background: #EBF0F5;
                        direction: rtl; /* Right-to-left for Persian */
                        font-family: "Nunito Sans", "Helvetica Neue", sans-serif;
                    }
                        h1 {
                        color: #88B04B;
                        font-weight: 900;
                        font-size: 40px;
                        margin-bottom: 10px;
                        }
                        p {
                        color: #404F5E;
                        font-size:20px;
                        margin: 0;
                        }
                    .checkmark {
                        color: #9ABC66;
                        font-size: 100px;
                        line-height: 200px;
                        margin-left:-15px; /* Adjust if needed for RTL */
                    }
                    .card {
                        background: white;
                        padding: 60px;
                        border-radius: 4px;
                        box-shadow: 0 2px 3px #C8D0D8;
                        display: inline-block;
                        margin: 0 auto;
                    }
                    .circle {
                        border-radius:200px;
                        height:200px;
                        width:200px;
                        background: #F8FAF5;
                        margin:0 auto;
                    }
                    </style>
                    <body>
                    <div class="card">
                    <div class="circle">
                        <span class="checkmark">✓</span>
                    </div>
                        <h1>موفق</h1>
                        <p>پرداخت شما با موفقیت انجام شد<br/>منتظر تایید و اعمال شارژ بمانید.</p>
                    </div>
                    </body>
             </html>';
    }

    // This method seems unused based on routes

    public function getCurrentUrl()
    {
        // Get the current URL
        $currentUrl = url()->current();

        // Log the current URL
        // Log::info('Current URL:', ['url' => $currentUrl]);

        // Return the current URL
        return $currentUrl;
    }

}
