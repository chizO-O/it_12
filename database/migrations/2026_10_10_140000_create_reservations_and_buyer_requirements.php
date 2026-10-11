<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained()->restrictOnDelete();
            $table->foreignId('lot_id')->constrained()->restrictOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('agent_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('agent_notes')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['buyer_id', 'created_at']);
            $table->index(['agent_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        // Supported by both PostgreSQL (Supabase) and SQLite test databases.
        // Prevents duplicate ACTIVE reservations despite concurrent application requests.
        DB::statement("CREATE UNIQUE INDEX reservations_active_lot_unique ON reservations (lot_id) WHERE status IN ('pending', 'approved')");
        DB::statement("CREATE UNIQUE INDEX reservations_active_inquiry_unique ON reservations (inquiry_id) WHERE status IN ('pending', 'approved')");

        Schema::create('buyer_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->string('requirement_type', 40)->default('buyer_form');
            $table->string('status', 30)->default('submitted');
            $table->string('file_path')->nullable();
            $table->unique(['reservation_id', 'requirement_type']);
            $table->string('contact_number', 30)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_requirements');
        Schema::dropIfExists('reservations');
    }
};
