<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('transaction_id')->unique();
            $table->string('checkout_url')->nullable();
            $table->string('provider')->default('xendit');
            $table->string('payment_type')->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('gross_amount', 15, 2);
            $table->enum('status', ['pending','success','failed','expired','cancelled','refund'])->default('pending');
            $table->json('raw_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
