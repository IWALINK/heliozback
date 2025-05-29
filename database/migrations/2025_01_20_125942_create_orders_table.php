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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('stripe_session_id')->unique();
            $table->string('status')->default('pending'); // pending, completed, failed, refunded
            $table->decimal('total_amount', 10, 2);
            $table->string('currency')->default('CHF');
            $table->json('items'); // Store cart items as JSON
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->nullable();
            $table->string('invoice_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
