<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $hasDuplicates = DB::table('users')
            ->selectRaw('LOWER(email) AS normalized_email')
            ->groupByRaw('LOWER(email)')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new RuntimeException('Resolve case-insensitive duplicate user emails before running this migration. No accounts have been changed.');
        }

        // Uses the configured PostgreSQL search path (laravel) or the isolated SQLite test database.
        DB::statement('CREATE UNIQUE INDEX users_email_lower_unique ON users (LOWER(email))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX users_email_lower_unique');
    }
};
