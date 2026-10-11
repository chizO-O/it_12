<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Notifications\PaymentReminder;
use App\Support\PesoAmount;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendPaymentReminders extends Command
{
    protected $signature = 'grovemap:payment-reminders';

    protected $description = 'Send one in-app upcoming and one overdue reminder per unpaid schedule entry';

    public function handle(): int
    {
        $today = CarbonImmutable::today('Asia/Manila');
        $horizon = $today->addDays(3);
        $sent = 0;

        Transaction::query()->where('status', 'sold')
            ->where('payment_type', 'installment')
            ->whereHas('paymentSchedules', fn ($q) => $q->whereDate('due_date', '<=', $horizon->toDateString()))
            ->with(['paymentSchedules', 'reservation.buyer'])
            ->chunkById(100, function ($transactions) use ($today, $horizon, &$sent) {
                foreach ($transactions as $transaction) {
                    $buyer = $transaction->reservation?->buyer;
                    if (! $buyer) {
                        continue;
                    }
                    $unallocatedPaid = PesoAmount::cents($transaction->payments()->sum('amount'));
                    foreach ($transaction->paymentSchedules->sortBy('installment_no') as $schedule) {
                        $scheduledCents = PesoAmount::cents($schedule->amount_due);
                        $allocatedCents = min($scheduledCents, $unallocatedPaid);
                        $unallocatedPaid -= $allocatedCents;
                        $remaining = $scheduledCents - $allocatedCents;
                        if ($remaining <= 0) {
                            continue;
                        }
                        $due = CarbonImmutable::parse($schedule->due_date)->startOfDay();
                        if ($due->isBefore($today)) {
                            $kind = 'overdue';
                            $message = 'An installment is overdue. Please review your payment schedule and coordinate with the Cashier.';
                        } elseif ($due->lessThanOrEqualTo($horizon)) {
                            $kind = 'upcoming';
                            $message = 'An installment is due on '.$due->format('M j, Y').'. Please review your payment schedule.';
                        } else {
                            continue;
                        }
                        // Database uniqueness prevents duplicate alerts on repeated scheduled runs.
                        $inserted = DB::table('payment_reminder_deliveries')->insertOrIgnore([
                            'payment_schedule_id' => $schedule->id,
                            'kind' => $kind,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        if ($inserted !== 1) {
                            continue;
                        }
                        try {
                            $buyer->notify(new PaymentReminder($transaction->id, $message));
                            $sent++;
                        } catch (Throwable $exception) {
                            // Retry next scheduled run rather than permanently skipping a failed alert.
                            DB::table('payment_reminder_deliveries')
                                ->where('payment_schedule_id', $schedule->id)->where('kind', $kind)->delete();
                            Log::warning('Payment reminder delivery failed', [
                                'payment_schedule_id' => $schedule->id,
                                'exception' => $exception::class,
                            ]);
                        }
                    }
                }
            });

        $this->info("Created {$sent} payment reminder notification(s).");
        return self::SUCCESS;
    }
}
