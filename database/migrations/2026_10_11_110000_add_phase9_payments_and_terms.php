<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('down_payment', 15, 2)->nullable();
            $table->timestamp('terms_confirmed_at')->nullable();
            $table->foreignId('confirmed_by_id')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('installment_no'); // 0 = spot cash or down payment
            $table->date('due_date');
            $table->decimal('amount_due', 15, 2);
            $table->timestamps();
            $table->unique(['transaction_id', 'installment_no']);
            $table->index(['transaction_id', 'due_date']);
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('reference_number', 100);
            $table->string('method', 30);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at');
            $table->timestamps();
            $table->unique(['transaction_id', 'reference_number']);
            $table->index(['transaction_id', 'payment_date']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_schedules');
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['confirmed_by_id']);
            $table->dropColumn(['down_payment', 'terms_confirmed_at', 'confirmed_by_id']);
        });
    }
};
