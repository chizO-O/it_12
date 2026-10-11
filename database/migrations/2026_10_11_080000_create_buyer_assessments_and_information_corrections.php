<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('last_updated_by')->constrained('users')->restrictOnDelete();
            $table->string('employment_status', 120)->nullable();
            $table->string('source_of_income')->nullable();
            $table->string('monthly_income_range', 120)->nullable();
            $table->string('preferred_payment_method', 20)->nullable();
            $table->integer('requested_installment_months')->nullable();
            $table->decimal('proposed_down_payment', 15, 2)->nullable();
            $table->text('additional_clarifications')->nullable();
            $table->text('agent_remarks')->nullable();
            $table->timestamp('privacy_notice_given_at');
            $table->timestamps();
        });

        Schema::create('buyer_information_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('section', 20);
            $table->string('status', 20)->default('pending');
            $table->json('original_values');
            $table->json('proposed_values');
            $table->text('reason');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['reservation_id', 'created_at'], 'buyer_corrections_reservation_date');
        });

        DB::statement("CREATE UNIQUE INDEX buyer_corrections_pending_unique ON buyer_information_corrections (reservation_id, section) WHERE status = 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_information_corrections');
        Schema::dropIfExists('buyer_assessments');
    }
};
