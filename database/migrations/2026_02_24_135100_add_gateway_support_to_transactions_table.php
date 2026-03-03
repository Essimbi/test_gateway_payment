<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds gateway support to transactions table:
     * - Adds gateway_type column with default 'cinetpay' for backward compatibility
     * - Renames cinetpay_payment_id to gateway_payment_id
     * - Adds index on gateway_type
     * - Sets gateway_type to CINETPAY for all existing transactions
     * 
     * Validates: Requirements 5.1, 9.1, 9.2
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Add gateway_type column with default 'cinetpay' for backward compatibility
            $table->string('gateway_type')->default('cinetpay')->after('status');
            
            // Add index on gateway_type for performance
            $table->index('gateway_type');
        });
        
        // Set gateway_type to CINETPAY for all existing transactions
        DB::table('transactions')->update(['gateway_type' => 'cinetpay']);
        
        Schema::table('transactions', function (Blueprint $table) {
            // Rename cinetpay_payment_id to gateway_payment_id
            $table->renameColumn('cinetpay_payment_id', 'gateway_payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Rename gateway_payment_id back to cinetpay_payment_id
            $table->renameColumn('gateway_payment_id', 'cinetpay_payment_id');
        });
        
        Schema::table('transactions', function (Blueprint $table) {
            // Drop index and column
            $table->dropIndex(['gateway_type']);
            $table->dropColumn('gateway_type');
        });
    }
};
