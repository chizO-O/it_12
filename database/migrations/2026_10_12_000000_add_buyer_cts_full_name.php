<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cts_records', function (Blueprint $table) {
            // Separate signed-contract identity from the editable user profile.
            $table->string('buyer_full_name', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cts_records', function (Blueprint $table) {
            $table->dropColumn('buyer_full_name');
        });
    }
};
