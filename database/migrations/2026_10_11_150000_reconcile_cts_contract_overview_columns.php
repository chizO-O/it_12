<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Reconcile older installations where the Phase 10 CTS overview migration
     * was not included in the patch applied locally. Safe on an up-to-date DB.
     */
    public function up(): void
    {
        if (! Schema::hasTable('cts_records')) {
            return;
        }

        $columns = [
            'contract_price',
            'ownership_agreement',
            'payment_agreement',
            'contract_prepared_by_id',
            'contract_prepared_at',
        ];
        $missing = array_values(array_filter(
            $columns,
            fn (string $column): bool => ! Schema::hasColumn('cts_records', $column)
        ));

        if ($missing === []) {
            return;
        }

        Schema::table('cts_records', function (Blueprint $table) use ($missing): void {
            if (in_array('contract_price', $missing, true)) {
                $table->decimal('contract_price', 15, 2)->nullable();
            }
            if (in_array('ownership_agreement', $missing, true)) {
                $table->text('ownership_agreement')->nullable();
            }
            if (in_array('payment_agreement', $missing, true)) {
                $table->text('payment_agreement')->nullable();
            }
            if (in_array('contract_prepared_by_id', $missing, true)) {
                $table->foreignId('contract_prepared_by_id')
                    ->nullable()->constrained('users')->nullOnDelete();
            }
            if (in_array('contract_prepared_at', $missing, true)) {
                $table->timestamp('contract_prepared_at')->nullable();
            }
        });
    }

    /**
     * Do not drop these columns during rollback: some installations created
     * them via an earlier migration that owns their lifecycle.
     */
    public function down(): void
    {
    }
};
