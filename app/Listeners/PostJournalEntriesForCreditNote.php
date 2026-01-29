<?php

namespace App\Listeners;

use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Listeners\Traits\ResolvesAccountMappings;
use Carbon\Carbon;

class PostJournalEntriesForCreditNote
{
    use ResolvesAccountMappings;

    public function handle(Invoice $invoice)
    {
        if (! property_exists($invoice, 'is_credit') || ! $invoice->is_credit) {
            return;
        }

        if (JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->where('description', 'LIKE', '%Credit note%')
            ->exists()) {
            return;
        }

        $companyId = $invoice->company_id;
        $amount    = $invoice->amount;
        $date      = Carbon::parse($invoice->date ?? now());

        // credit_note: debit=4000 (Sales), credit=1100 (AR)
        $accounts = $this->resolveAccounts($companyId, 'credit_note', '4000', '1100');

        if (! $accounts) {
            return;
        }

        // Credit AR (reduce receivable) - use the credit account
        JournalEntry::create([
            'company_id'          => $companyId,
            'chart_of_account_id' => $accounts['credit']->id,
            'source_type'         => Invoice::class,
            'source_id'           => $invoice->id,
            'debit'               => 0,
            'credit'              => $amount,
            'description'         => 'Credit note issued',
            'entry_date'          => $date,
        ]);

        // Debit Sales (reverse revenue) - use the debit account
        JournalEntry::create([
            'company_id'          => $companyId,
            'chart_of_account_id' => $accounts['debit']->id,
            'source_type'         => Invoice::class,
            'source_id'           => $invoice->id,
            'debit'               => $amount,
            'credit'              => 0,
            'description'         => 'Credit note issued',
            'entry_date'          => $date,
        ]);
    }
}
