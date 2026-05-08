<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportController extends Controller
{
    public function salesOrder(Request $request)
    {
        $tableName = 'acm_sales_orders';
        $hasData = Schema::hasTable($tableName);
        
        $query = $hasData ? DB::table($tableName) : null;
        
        // Allowed filters (Default to current month if not specified in request)
        $dateFrom = $request->has('date_from') ? $request->input('date_from') : \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d');
        $dateTo = $request->has('date_to') ? $request->input('date_to') : \Carbon\Carbon::now()->endOfMonth()->format('Y-m-d');
        $customerId = $request->input('customer_id');

        if ($hasData) {
            if ($dateFrom) {
                // Assuming OrderDate is stored in format YYYY-MM-DD
                $query->where('OrderDate', '>=', $dateFrom . 'T00:00:00');
            }
            if ($dateTo) {
                $query->where('OrderDate', '<=', $dateTo . 'T23:59:59');
            }
            if ($customerId) {
                $query->where('CustomerID', $customerId);
            }
            
            // Default sorting
            $query->orderBy('OrderDate', 'desc');
        }

        $records = $query ? $query->paginate(50)->withQueryString() : [];

        // Fetch distinct values for filter dropdowns
        $customerIds = [];
        if ($hasData) {
            $customerIds = DB::table($tableName)->distinct()->pluck('CustomerID')->filter()->sort();
        }

        return view('reports.sales_order', [
            'records'       => $records,
            'hasData'       => $hasData,
            'customerIds'   => $customerIds,
            'filters'       => [
                'date_from'     => $dateFrom,
                'date_to'       => $dateTo,
                'customer_id'   => $customerId,
            ],
            'activeTab'     => 'report.sales-order'
        ]);
    }
}
