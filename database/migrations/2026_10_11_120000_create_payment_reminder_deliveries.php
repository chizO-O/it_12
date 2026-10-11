<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_reminder_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_schedule_id')->constrained('payment_schedules')->cascadeOnDelete();
            $table->string('kind', 20); // upcoming or overdue; only one of each per due entry.
            $table->timestamps();
            $table->unique(['payment_schedule_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reminder_deliveries');
    }
};
