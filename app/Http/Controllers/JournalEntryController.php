<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    public function index(Request $request)
    {
        $companyId = auth()->user()->companyId();

        $query = JournalEntry::where('company_id', $companyId)
            ->with('account');

        if ($request->query('from')) {
            $query->whereDate('entry_date', '>=', $request->query('from'));
        }

        if ($request->query('to')) {
            $query->whereDate('entry_date', '<=', $request->query('to'));
        }

        if ($request->query('chart_of_account_id')) {
            $query->where('chart_of_account_id', $request->query('chart_of_account_id'));
        }

        if ($request->query('source_type')) {
            $sourceType = $request->query('source_type');
            // Allow short names like "Invoice" or full class names
            if (! str_contains($sourceType, '\\')) {
                $sourceType = 'App\\Models\\' . $sourceType;
            }
            $query->where('source_type', $sourceType);
        }

        $entries = $query->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($request->query('per_page', 50));

        // Transform source_type to short name
        $entries->getCollection()->transform(function ($entry) {
            $entry->source_type_short = class_basename($entry->source_type ?? '');
            return $entry;
        });

        return $entries;
    }
}
