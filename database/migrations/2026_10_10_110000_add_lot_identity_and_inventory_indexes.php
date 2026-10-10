<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('lots')
            ->selectRaw('property_id')
            ->groupByRaw("property_id, COALESCE(LOWER(TRIM(block_no)), ''), LOWER(TRIM(lot_no))")
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicates) {
            throw new RuntimeException('Duplicate lot numbers exist in the same property and block. Review those records before migrating; no lots have been changed.');
        }

        DB::statement("CREATE UNIQUE INDEX lots_property_block_number_unique ON lots (property_id, (COALESCE(LOWER(TRIM(block_no)), '')), LOWER(TRIM(lot_no)))");

        Schema::table('properties', fn (Blueprint $table) => $table->index('location'));
        Schema::table('lots', fn (Blueprint $table) => $table->index(['property_id', 'status']));
    }

    public function down(): void
    {
        Schema::table('lots', fn (Blueprint $table) => $table->dropIndex(['property_id', 'status']));
        Schema::table('properties', fn (Blueprint $table) => $table->dropIndex(['location']));
        DB::statement('DROP INDEX lots_property_block_number_unique');
    }
};
