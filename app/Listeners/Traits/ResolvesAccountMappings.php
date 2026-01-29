<?php

namespace App\Listeners\Traits;

use App\Models\Company;
use App\Models\ChartOfAccount;

trait ResolvesAccountMappings
{
    protected function resolveAccounts(int $companyId, string $transactionType, string $defaultDebitCode, string $defaultCreditCode): ?array
    {
        $company = Company::find($companyId);

        $debitCode = $defaultDebitCode;
        $creditCode = $defaultCreditCode;

        if ($company && $company->settings) {
            $mappings = $company->settings->account_mappings ?? null;

            if ($mappings && is_object($mappings) && isset($mappings->{$transactionType})) {
                $mapping = $mappings->{$transactionType};
                $debitCode = $mapping->debit_code ?? $defaultDebitCode;
                $creditCode = $mapping->credit_code ?? $defaultCreditCode;
            }
        }

        $debit = ChartOfAccount::where('company_id', $companyId)->where('code', $debitCode)->first();
        $credit = ChartOfAccount::where('company_id', $companyId)->where('code', $creditCode)->first();

        return ($debit && $credit) ? ['debit' => $debit, 'credit' => $credit] : null;
    }
}
