<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cts_records', function (Blueprint $table) {
            $table->string('landowner_name', 200)->nullable();
            $table->string('landowner_contact', 40)->nullable();
            $table->text('landowner_address')->nullable();
            $table->string('landowner_relationship', 150)->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('cts_records', function (Blueprint $table) {
            $table->dropColumn(['landowner_name', 'landowner_contact', 'landowner_address', 'landowner_relationship']);
        });
    }
};
