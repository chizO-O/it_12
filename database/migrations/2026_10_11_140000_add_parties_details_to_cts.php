<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cts_records', function (Blueprint $table) {
            // CTS-specific declared details belong to this lot's contract, not the login profile.
            $table->text('buyer_permanent_address')->nullable();
            $table->string('buyer_marital_status', 50)->nullable();
            $table->unsignedSmallInteger('buyer_age')->nullable();
            $table->string('buyer_nationality', 100)->nullable();
            $table->timestamp('buyer_details_submitted_at')->nullable();
            $table->text('landowner_permanent_address')->nullable();
            $table->string('landowner_marital_status', 50)->nullable();
            $table->unsignedSmallInteger('landowner_age')->nullable();
            $table->string('landowner_nationality', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cts_records', function (Blueprint $table) {
            $table->dropColumn([
                'buyer_permanent_address', 'buyer_marital_status', 'buyer_age',
                'buyer_nationality', 'buyer_details_submitted_at',
                'landowner_permanent_address', 'landowner_marital_status',
                'landowner_age', 'landowner_nationality',
            ]);
        });
    }
};
