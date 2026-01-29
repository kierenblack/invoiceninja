<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use App\Http\Controllers\Traits\ResolvesReportPeriod;
use Illuminate\Support\Facades\DB;

class TrialBalanceController extends Controller
{
    use ResolvesReportPeriod;

    public function index(Request $request)
    {
        $companyId = auth()->user()->companyId();
        [$from, $to] = $this->resolveDates($request);

        $breakdown = $request->query('breakdown') === 'monthly';

        if ($breakdown && $from && $to) {
            return $this->monthlyBreakdown($companyId, $from, $to);
        }

        return $this->buildQuery($companyId, $from, $to)->get();
    }

    public function csv(Request $request)
    {
        $companyId = auth()->user()->companyId();
        [$from, $to] = $this->resolveDates($request);

        $rows = $this->buildQuery($companyId, $from, $to)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Code', 'Name', 'Type', 'Debit', 'Credit']);
            foreach ($rows as $row) {
                fputcsv($out, [$row->code, $row->name, $row->type, $row->total_debit, $row->total_credit]);
            }
            fclose($out);
        }, 'trial_balance.csv', ['Content-Type' => 'text/csv']);
    }

    private function buildQuery(int $companyId, ?string $from, ?string $to)
    {
        return ChartOfAccount::where('chart_of_accounts.company_id', $companyId)
            ->leftJoin('journal_entries', 'chart_of_accounts.id', '=', 'journal_entries.chart_of_account_id')
            ->when($from, fn($q) => $q->whereDate('journal_entries.entry_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('journal_entries.entry_date', '<=', $to))
            ->select(
                'chart_of_accounts.id',
                'chart_of_accounts.code',
                'chart_of_accounts.name',
                'chart_of_accounts.type',
                DB::raw('COALESCE(SUM(journal_entries.debit),0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_entries.credit),0) as total_credit')
            )
            ->groupBy('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', 'chart_of_accounts.type')
            ->orderBy('chart_of_accounts.code');
    }

    private function monthlyBreakdown(int $companyId, string $from, string $to)
    {
        $rows = ChartOfAccount::where('chart_of_accounts.company_id', $companyId)
            ->leftJoin('journal_entries', 'chart_of_accounts.id', '=', 'journal_entries.chart_of_account_id')
            ->whereDate('journal_entries.entry_date', '>=', $from)
            ->whereDate('journal_entries.entry_date', '<=', $to)
            ->select(
                'chart_of_accounts.id',
                'chart_of_accounts.code',
                'chart_of_accounts.name',
                'chart_of_accounts.type',
                DB::raw("DATE_FORMAT(journal_entries.entry_date, '%Y-%m') as month"),
                DB::raw('COALESCE(SUM(journal_entries.debit),0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_entries.credit),0) as total_credit')
            )
            ->groupBy('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', 'chart_of_accounts.type', DB::raw("DATE_FORMAT(journal_entries.entry_date, '%Y-%m')"))
            ->orderBy('month')
            ->orderBy('chart_of_accounts.code')
            ->get();

        return $rows->groupBy('month')->map(fn($items, $month) => [
            'month' => $month,
            'data' => $items->values(),
        ])->values();
    }
}
