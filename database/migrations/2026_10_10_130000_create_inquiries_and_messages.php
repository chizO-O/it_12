<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('service_area', 30)->nullable();
            $table->index(['role', 'service_area']);
        });

        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('lot_id')->constrained('lots')->restrictOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['buyer_id', 'lot_id']);
            $table->index(['agent_id', 'updated_at']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained('inquiries')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['inquiry_id', 'id']);
            $table->index(['inquiry_id', 'read_at']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->morphs('notifiable');
            $table->string('type');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('inquiries');
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'service_area']);
            $table->dropColumn('service_area');
        });
    }
};
