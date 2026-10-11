<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SQLite may rebuild tables when adding/removing foreign keys. During that
     * rebuild, Laravel's index introspection can lose expression-based index
     * columns (e.g. LOWER(email)) and try to recreate an invalid index on ().
     * Keep the exact definitions of those indexes and restore them afterward.
     * PostgreSQL / Supabase is unaffected by this test-only workaround.
     */
    private function preservingSqliteExpressionIndexes(callable $change): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $change();

            return;
        }

        $names = [
            'users_email_lower_unique',
            'lots_property_block_number_unique',
            'lots_layout_position_unique',
        ];

        $indexes = DB::table('sqlite_master')
            ->select(['name', 'sql'])
            ->where('type', 'index')
            ->whereIn('name', $names)
            ->whereNotNull('sql')
            ->get();

        foreach ($indexes as $index) {
            // Each name is from the fixed allowlist above.
            DB::statement('DROP INDEX "'.$index->name.'"');
        }

        $change();

        foreach ($indexes as $index) {
            DB::statement($index->sql);
        }
    }

    public function up(): void
    {
        $this->preservingSqliteExpressionIndexes(function (): void {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('unit_manager_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            });

            Schema::table('lots', function (Blueprint $table) {
                $table->foreignId('assigned_agent_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('agent_assigned_at')->nullable();
            });
        });
    }

    public function down(): void
    {
        $this->preservingSqliteExpressionIndexes(function (): void {
            Schema::table('lots', function (Blueprint $table) {
                $table->dropConstrainedForeignId('assigned_agent_id');
                $table->dropConstrainedForeignId('assigned_by_user_id');
                $table->dropColumn('agent_assigned_at');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('unit_manager_id');
                $table->dropConstrainedForeignId('invited_by_user_id');
            });
        });
    }
};
