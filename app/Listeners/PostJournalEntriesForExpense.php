<?php

namespace App\Listeners;

use App\Models\Expense;
use App\Models\JournalEntry;
use App\Listeners\Traits\ResolvesAccountMappings;
use Carbon\Carbon;

class PostJournalEntriesForExpense
{
    use ResolvesAccountMappings;

    public function handle(Expense $expense)
    {
        if (JournalEntry::where('source_type', Expense::class)
            ->where('source_id', $expense->id)
            ->exists()
        ) {
            return;
        }

        $companyId = $expense->company_id;
        $amount    = $expense->amount;
        $date      = Carbon::parse($expense->date ?? now());

        $accounts = $this->resolveAccounts($companyId, 'expense', '5000', '1000');

        if (! $accounts) {
            return;
        }

        // Debit Expense account
        JournalEntry::create([
            'company_id'            => $companyId,
            'chart_of_account_id'   => $accounts['debit']->id,
            'source_type'           => Expense::class,
            'source_id'             => $expense->id,
            'debit'                 => $amount,
            'credit'                => 0,
            'description'           => 'Expense recorded',
            'entry_date'            => $date,
        ]);

        // Credit Cash account
        JournalEntry::create([
            'company_id'            => $companyId,
            'chart_of_account_id'   => $accounts['credit']->id,
            'source_type'           => Expense::class,
            'source_id'             => $expense->id,
            'debit'                 => 0,
            'credit'                => $amount,
            'description'           => 'Expense recorded',
            'entry_date'            => $date,
        ]);
    }
}
