<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\PayinTransaction;
use App\Models\UserApiLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\Facades\DataTables;


class ApiLogController extends Controller
{
    public function apiLog()
    {
        return view('user.reports.api_logs');
    }

    public function getApiLogs(Request $request)
    {
        $query = UserApiLog // relation required
            ::select([
                'id',
                'user_id',
                'event',
                'api_url',
                'header',
                'request',
                'response',
                'http_code',
                'created_at',
            ])

            ->where('user_id', Auth::id())
            /* 🔍 FILTERS */

            ->when(
                $request->event,
                fn($q, $v) =>
                $q->where('event', 'like', "%{$v}%")
            )

            ->when(
                $request->api_url,
                fn($q, $v) =>
                $q->where('api_url', 'like', "%{$v}%")
            )

            ->when(
                $request->http_code !== null,
                fn($q) =>
                $q->where('http_code', $request->http_code)
            )
            ->when($request->from_date || $request->to_date, function ($q) use ($request) {
                if ($request->from_date && $request->to_date) {
                    $q->whereBetween('created_at', [
                        Carbon::parse($request->from_date)->startOfDay(),
                        Carbon::parse($request->to_date)->endOfDay()
                    ]);
                } elseif ($request->from_date) {
                    $q->whereDate('created_at', '>=', $request->from_date);
                } elseif ($request->to_date) {
                    $q->whereDate('created_at', '<=', $request->to_date);
                }
            });
        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('api_url', function ($row) {
                return '<button class="btn btn-sm btn-secondary view-log" data-id="' . $row->id . '" data-type="api_url">View</button>';
            })

            ->addColumn('header', function ($row) {
                return '<button class="btn btn-sm btn-info view-log" data-id="' . $row->id . '" data-type="header">View</button>';
            })

            ->addColumn('request', function ($row) {
                return '<button class="btn btn-sm btn-warning view-log" data-id="' . $row->id . '" data-type="request">View</button>';
            })

            ->addColumn('response', function ($row) {
                return '<button class="btn btn-sm btn-success view-log" data-id="' . $row->id . '" data-type="response">View</button>';
            })

            /* 📅 DATE */
            ->editColumn('created_at', function ($t) {
                return $t->created_at
                    ? \Carbon\Carbon::parse($t->created_at)->format('d/m/Y H:i:s')
                    : '-';
            })

            ->rawColumns([
               'api_url', 'header', 'request', 'response','created_at'
            ])

            ->toJson();
    }

    public function showAjax($id)
    {
        $txn = UserApiLog::findOrFail($id);
        return response()->json($txn);
    }
}
