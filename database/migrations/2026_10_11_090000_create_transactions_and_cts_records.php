<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Phase 9 will populate the agreed payment terms; do not assume the listed price is final.
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('agreed_price', 15, 2)->nullable();
            $table->string('payment_type', 20)->nullable();
            $table->unsignedInteger('term_months')->nullable();
            $table->string('status', 30)->default('cts_processing');
            $table->timestamps();
        });

        Schema::create('cts_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('initiated_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('property_verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('draft_path')->nullable();
            $table->timestamp('draft_uploaded_at')->nullable();
            $table->unsignedInteger('draft_version')->default(0);
            $table->string('status', 32)->default('requested');
            $table->timestamp('buyer_reviewed_at')->nullable();
            $table->text('buyer_feedback')->nullable();
            $table->string('executed_path')->nullable();
            $table->foreignId('executed_uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('executed_uploaded_at')->nullable();
            $table->date('notarized_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cts_records');
        Schema::dropIfExists('transactions');
    }
};
