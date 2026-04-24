<?php

namespace App\Http\Controllers;

use App\Support\Dashboard\DashboardCsvExporter;
use App\Support\Dashboard\DashboardStatusCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class DashboardExportController extends Controller
{
    public function __invoke(Request $request, DashboardCsvExporter $exporter): Response
    {
        $filters = $request->validate([
            'export_type' => ['required', 'string', Rule::in(DashboardStatusCatalog::exportTypes())],
            'selected_month' => ['required', 'date_format:Y-m'],
            'search' => ['nullable', 'string', 'max:255'],
            'quick_filter' => ['required', 'string', Rule::in(array_keys(DashboardStatusCatalog::quickFilterLabels()))],
            'sort_by' => ['required', 'string', Rule::in(['due_date_asc', 'amount_desc'])],
        ]);

        return $exporter->download($filters, $request->user());
    }
}
