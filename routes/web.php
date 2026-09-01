<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionCryptoController;
use App\Http\Controllers\CryptomusController;
use App\Http\Controllers\SwapPayController;
use App\Http\Controllers\WebViewController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', [WebViewController::class, 'welcome']);
Route::get('buy/{account_id}/{invoiceID}/{price}', [WebViewController::class, 'shop']);
Route::post('shop', [TransactionController::class, 'add_order'])->name('shop.submit'); // for zarinpal

Route::get('order', [WebViewController::class, 'orderRedirect']);

// Laravel 8 & 9
Route::get('/pay', [App\Http\Controllers\NowPaymentsController::class, 'createCryptoInvoice'])->name('pay');

Route::get('cryptopayment/{account_id}/{invoiceID}/{price}', [WebViewController::class, 'cryptoPayment']);

Route::post('cryptogateway', [TransactionCryptoController::class, 'initiateCryptoPayment'])->name('crypto.initiate');

Route::get('/payback', [WebViewController::class, 'payback']);
Route::get('/cancelpay', [WebViewController::class, 'cancelPay']);

// Cryptomus Routes
Route::post('/cryptomus/create', [CryptomusController::class, 'createPayment'])->name('cryptomus.create');
Route::post('/cryptomus/callback', [CryptomusController::class, 'handleCallback'])->name('cryptomus.callback'); // Needs CSRF exemption
Route::get('/payment/success', [CryptomusController::class, 'paymentSuccess'])->name('payment.success');
Route::get('/payment/return', [CryptomusController::class, 'paymentReturn'])->name('payment.return');

// SwapPay (SwapWallet)
Route::match(['get', 'post'], '/swappay/return', [SwapPayController::class, 'handleReturn'])->name('swappay.return');


// run command by url
// Route::get('/run-command/{name_of_command}', ExecuteArtisanCommandController::class);
