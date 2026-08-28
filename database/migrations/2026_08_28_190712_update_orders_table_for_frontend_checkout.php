<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null')->after('id'); // Nullable for guest checkout
            $table->enum('payment_method', ['cod', 'advance'])->default('advance')->after('status');
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending')->after('payment_method');
            $table->decimal('subtotal', 10, 2)->default(0)->after('total_amount');
            $table->decimal('delivery_fee', 10, 2)->default(0)->after('subtotal');
            $table->decimal('tax_amount', 10, 2)->default(0)->after('delivery_fee');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('tax_amount');
            $table->text('shipping_address')->nullable()->after('customer_email');
            $table->string('customer_phone')->nullable()->after('shipping_address');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'user_id',
                'payment_method',
                'payment_status',
                'subtotal',
                'delivery_fee',
                'tax_amount',
                'discount_amount',
                'shipping_address',
                'customer_phone'
            ]);
        });
    }
};
