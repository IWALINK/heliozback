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
        Schema::create('bank_information', function (Blueprint $table) {
            $table->id();
            $table->string('account_holder_name');
            $table->string('bank_name');
            $table->string('iban');
            $table->string('bic')->nullable();
            $table->string('account_number')->nullable();
            $table->string('routing_number')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_information');
    }
};
