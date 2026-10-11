<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->unsignedSmallInteger('grid_row')->nullable();
            $table->unsignedTinyInteger('grid_column')->nullable();
        });

        // NULL cells are allowed: existing lots keep their automatically generated layout.
        // Explicit positions must be unique within the same property and normalized block.
        DB::statement("CREATE UNIQUE INDEX lots_layout_position_unique ON lots (property_id, (COALESCE(LOWER(TRIM(block_no)), '')), grid_row, grid_column) WHERE grid_row IS NOT NULL AND grid_column IS NOT NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX lots_layout_position_unique');
        Schema::table('lots', function (Blueprint $table) {
            $table->dropColumn(['grid_row', 'grid_column']);
        });
    }
};
