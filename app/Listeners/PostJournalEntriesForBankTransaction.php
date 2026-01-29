<?php

namespace App\Listeners;

use App\Models\BankTransaction;
use App\Models\JournalEntry;
use App\Listeners\Traits\ResolvesAccountMappings;
use Carbon\Carbon;

class PostJournalEntriesForBankTransaction
{
    use ResolvesAccountMappings;

    public function handle(BankTransaction $transaction)
    {
        if (JournalEntry::where('source_type', BankTransaction::class)
            ->where('source_id', $transaction->id)
            ->exists()
        ) {
            return;
        }

        $companyId = $transaction->company_id;
        $amount    = abs($transaction->amount);
        $date      = Carbon::parse($transaction->date ?? now());

        // base_type determines direction: DEBIT = money out, CREDIT = money in
        $isCredit = strtolower($transaction->base_type ?? '') === 'credit';

        if ($isCredit) {
            $accounts = $this->resolveAccounts($companyId, 'bank_transaction_credit', '1000', '4000');
        } else {
            $accounts = $this->resolveAccounts($companyId, 'bank_transaction_debit', '5000', '1000');
        }

        if (! $accounts) {
            return;
        }

        $description = $isCredit ? 'Bank deposit' : 'Bank withdrawal';

        JournalEntry::create([
            'company_id'            => $companyId,
            'chart_of_account_id'   => $accounts['debit']->id,
            'source_type'           => BankTransaction::class,
            'source_id'             => $transaction->id,
            'debit'                 => $amount,
            'credit'                => 0,
            'description'           => $description,
            'entry_date'            => $date,
        ]);

        JournalEntry::create([
            'company_id'            => $companyId,
            'chart_of_account_id'   => $accounts['credit']->id,
            'source_type'           => BankTransaction::class,
            'source_id'             => $transaction->id,
            'debit'                 => 0,
            'credit'                => $amount,
            'description'           => $description,
            'entry_date'            => $date,
        ]);
    }
}
