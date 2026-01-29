<?php

namespace App\Listeners;

use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Listeners\Traits\ResolvesAccountMappings;
use Carbon\Carbon;

class PostJournalEntriesForInvoice
{
    use ResolvesAccountMappings;

    public function handle(Invoice $invoice)
    {
        if ($invoice->status_id != Invoice::STATUS_SENT) {
            return;
        }

        $companyId = $invoice->company_id;
        $amount    = $invoice->amount;
        $date      = Carbon::parse($invoice->date ?? now());

        $accounts = $this->resolveAccounts($companyId, 'invoice', '1100', '4000');

        if (! $accounts) {
            return;
        }

        JournalEntry::create([
            'company_id'          => $companyId,
            'chart_of_account_id' => $accounts['debit']->id,
            'source_type'         => Invoice::class,
            'source_id'           => $invoice->id,
            'debit'               => $amount,
            'credit'              => 0,
            'description'         => 'Invoice issued',
            'entry_date'          => $date,
        ]);

        JournalEntry::create([
            'company_id'          => $companyId,
            'chart_of_account_id' => $accounts['credit']->id,
            'source_type'         => Invoice::class,
            'source_id'           => $invoice->id,
            'debit'               => 0,
            'credit'              => $amount,
            'description'         => 'Invoice issued',
            'entry_date'          => $date,
        ]);
    }
}
