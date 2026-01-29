<?php

namespace App\Http\Controllers\Traits;

use Carbon\Carbon;
use Illuminate\Http\Request;

trait ResolvesReportPeriod
{
    protected function resolveDates(Request $request): array
    {
        $period = $request->query('period', 'custom');
        $now = Carbon::now();

        switch ($period) {
            case 'this_month':
                return [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()];
            case 'last_month':
                return [$now->copy()->subMonth()->startOfMonth()->toDateString(), $now->copy()->subMonth()->endOfMonth()->toDateString()];
            case 'this_quarter':
                return [$now->copy()->firstOfQuarter()->toDateString(), $now->copy()->lastOfQuarter()->toDateString()];
            case 'last_quarter':
                return [$now->copy()->subQuarter()->firstOfQuarter()->toDateString(), $now->copy()->subQuarter()->lastOfQuarter()->toDateString()];
            case 'this_year':
                return [$now->copy()->startOfYear()->toDateString(), $now->copy()->endOfYear()->toDateString()];
            case 'last_year':
                return [$now->copy()->subYear()->startOfYear()->toDateString(), $now->copy()->subYear()->endOfYear()->toDateString()];
            case 'last_financial_year':
                // Financial year: July 1 - June 30
                $fyStart = $now->month >= 7
                    ? Carbon::create($now->year - 1, 7, 1)
                    : Carbon::create($now->year - 2, 7, 1);
                $fyEnd = $fyStart->copy()->addYear()->subDay();
                return [$fyStart->toDateString(), $fyEnd->toDateString()];
            default: // custom
                return [$request->query('from'), $request->query('to')];
        }
    }
}
