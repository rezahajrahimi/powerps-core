<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transaction_cryptos', function (Blueprint $table) {
            // Add gateway column (e.g., 'nowpayments', 'cryptomus')
            $table->string('gateway')->after('crypto_payment_id')->nullable()->index()->comment('Payment gateway used');

            // Add status column (e.g., 'pending', 'paid', 'confirmed', 'failed', 'expired')
            $table->string('status')->after('gateway')->default('pending')->index()->comment('Payment status from gateway/internal');

            // Add payment_id column (unique ID from the gateway)
            // Make it nullable as it might be set after initial record creation
            $table->string('payment_id')->after('status')->nullable()->unique()->comment('Gateway\'s unique payment/invoice ID');

            // Add payment_url column (URL to gateway's payment page)
            $table->text('payment_url')->after('payment_id')->nullable();

            // Add callback_data column (to store raw callback response for debugging)
            $table->json('callback_data')->after('payment_url')->nullable();

            // Add currency column (e.g., 'USD', 'USDT') - Assuming amount_dollar is always USD equivalent
            $table->string('currency')->after('amount_dollar')->nullable()->default('USD')->comment('Currency used for the amount');

            // Modify existing columns if necessary based on controller logic
            // Make recipe_number nullable as it might not apply to Cryptomus or be set later
             $table->string('recipe_number')->nullable()->default(null)->change();
            // Remove default from confirmed if status handles it better
             $table->boolean('confirmed')->nullable()->default(null)->change();
             // Rename amount_dollar to amount for clarity (optional, but recommended)
             // $table->renameColumn('amount_dollar', 'amount');

            // Consider adding indexes to frequently queried columns
            // Check if order_id index exists from '2024_05_11_132700_add_order_id_to_transaction_cryptos.php' migration
            // $table->index('order_id');
            // Foreign key on account_id might create an index automatically, verify if needed
            // $table->index('account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_cryptos', function (Blueprint $table) {
            // Drop columns in reverse order of creation
            $table->dropColumn('callback_data');
            $table->dropColumn('payment_url');
            $table->dropColumn('payment_id');
            $table->dropColumn('status');
            $table->dropColumn('gateway');
            $table->dropColumn('currency');

            // Revert column modifications if any were made
             $table->string('recipe_number')->nullable(false)->default('0')->change();
             $table->boolean('confirmed')->nullable(false)->default(false)->change();
             // $table->renameColumn('amount', 'amount_dollar');
        });
    }
};
