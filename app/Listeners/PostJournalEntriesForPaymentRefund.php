<?php

namespace App\Listeners;

use App\Models\Payment;
use App\Models\JournalEntry;
use App\Listeners\Traits\ResolvesAccountMappings;
use Carbon\Carbon;

class PostJournalEntriesForPaymentRefund
{
    use ResolvesAccountMappings;

    public function handle(Payment $payment)
    {
        $refundAmount = (float) ($payment->refunded ?? 0);

        if ($refundAmount <= 0) {
            return;
        }

        if (JournalEntry::where('source_type', Payment::class)
            ->where('source_id', $payment->id)
            ->where('description', 'LIKE', '%Refund%')
            ->exists()) {
            return;
        }

        $companyId = $payment->company_id;
        $date      = Carbon::now();

        // payment_refund: debit=1100 (AR), credit=1000 (Cash)
        $accounts = $this->resolveAccounts($companyId, 'payment_refund', '1100', '1000');

        if (! $accounts) {
            return;
        }

        // Debit AR (customer owes again)
        JournalEntry::create([
            'company_id'          => $companyId,
            'chart_of_account_id' => $accounts['debit']->id,
            'source_type'         => Payment::class,
            'source_id'           => $payment->id,
            'debit'               => $refundAmount,
            'credit'              => 0,
            'description'         => 'Refund issued',
            'entry_date'          => $date,
        ]);

        // Credit Cash
        JournalEntry::create([
            'company_id'          => $companyId,
            'chart_of_account_id' => $accounts['credit']->id,
            'source_type'         => Payment::class,
            'source_id'           => $payment->id,
            'debit'               => 0,
            'credit'              => $refundAmount,
            'description'         => 'Refund issued',
            'entry_date'          => $date,
        ]);
    }
}
