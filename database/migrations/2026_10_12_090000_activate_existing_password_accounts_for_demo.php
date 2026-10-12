<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Historical compatibility: email_verified_at is treated as an *access activation*
        // marker in this demo. This does not prove ownership of any email address.
        DB::table('users')->whereNull('email_verified_at')->whereNotNull('password')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Deliberately irreversible. Do not revoke pre-existing accounts on rollback.
    }
};
