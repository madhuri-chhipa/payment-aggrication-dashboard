<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\BbpsBillTransaction;
use App\Services\BbpsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class BbpsTransactionController extends Controller
{
  public function bbpsTxnLog()
  {
    return view('admin.reports.bbps_transactions');
  }
  public function getBbpsTransactions(Request $request)
  {
    $query = BbpsBillTransaction::with('user')
      ->when(
        $request->request_id,
        fn($q, $v) =>
        $q->where('request_id', 'like', "%{$v}%")
      )

      ->when(
        $request->bbps_txn,
        fn($q, $v) =>
        $q->where('bbps_txn_ref_id', 'like', "%{$v}%")
      )
      ->when($request->user, function ($q, $v) {
        $q->whereHas('user', function ($u) use ($v) {
          $u->where('company_name', 'like', "%$v%")
            ->orWhere('email', 'like', "%$v%");
        });
      })

      ->when($request->biller, function ($q, $v) {
        $q->where('biller_id', 'like', "%{$v}%")
          ->orWhere('biller_name', 'like', "%{$v}%");
      })

      ->when(
        $request->status !== null,
        fn($q) =>
        $q->where('status', $request->status)
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
      ->addColumn('user_info', function ($t) {
        if (!$t->user) return '-';
        return "
        <strong>{$t->user->company_name}</strong><br>
        <small>{$t->user->email}</small>
        ";
      })
      ->addColumn('biller', function ($t) {
        if (!$t->user) return '-';
        return "
        <strong>{$t->biller_id}</strong><br>
        <small>{$t->biller_name}</small>
        ";
      })
      ->editColumn('amount', fn($t) => '₹ ' . number_format($t->amount, 2))
      ->editColumn('status', function ($t) {
        return match ($t->status) {
          'SUCCESS' => '<span class="badge bg-success">Success</span>',
          'FAILED' => '<span class="badge bg-danger">Failed</span>',
          'PENDING' => '<span class="badge bg-warning">Pending</span>'
        };
      })
      ->editColumn('created_at', function ($t) {
        return $t->created_at
          ? \Carbon\Carbon::parse($t->created_at)->format('d/m/Y H:i:s')
          : '-';
      })
      ->addColumn('actions', function ($t) {
        return '
        <div class="dropdown">
          <button class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown">
          Actions
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li>
              <a class="dropdown-item view-txn cursor-pointer" data-id="' . $t->id . '">
                <i class="fa fa-eye text-primary"></i>
                View
              </a>
            </li>
            <li>
              <a class="dropdown-item print-txn cursor-pointer" data-id="' . $t->id . '">
                <i class="fa fa-print text-primary"></i>
                Print
              </a>
            </li>
            <li>
              <a class="dropdown-item check-status cursor-pointer" data-id="' . $t->id . '">
                <i class="fa fa-rotate text-primary"></i>
                Check Status
              </a>
            </li>
          </ul>
        </div>
      ';
      })
      ->rawColumns(['status', 'biller', 'actions', 'user_info'])
      ->toJson();
  }


  public function showAjax($id)
  {
    $txn = BbpsBillTransaction::findOrFail($id);
    return response()->json($txn);
  }

  public function printTxn($id)
  {
    $txn = BbpsBillTransaction::findOrFail($id);

    $xmlData = null;

    if (!empty($txn->api_response)) {

      // Decode HTML entities first
      $xmlString = html_entity_decode($txn->api_response);

      // Parse XML
      $xmlData = simplexml_load_string($xmlString);
    }

    return view('transactions.print', compact('txn', 'xmlData'));
  }
  public function checkStatus($id, BbpsService $bbps)
  {
    try {

      $txn = BbpsBillTransaction::findOrFail($id);

      if (!$txn->bbps_txn_ref_id) {
        return response()->json([
          'status' => false,
          'message' => 'Transaction reference ID not found'
        ]);
      }

      $data = [
        'trackingType'  => 'TRANS_REF_ID',
        'trackingValue' => $txn->bbps_txn_ref_id
      ];

      // call service
      $responseJson = $bbps->transactionStatus($data);
      print_r($responseJson);
      $arr = json_decode(trim($responseJson), true);

      if (!is_array($arr)) {
        return response()->json([
          'status' => false,
          'message' => 'Invalid API response'
        ]);
      }

      $responseCode   = (string)($arr['responseCode'] ?? '');
      $responseReason = (string)($arr['responseReason'] ?? '');

      $txnList = $arr['txnList'] ?? [];

      // if txnList is array
      if (isset($txnList[0])) {
        $txnList = $txnList[0];
      }

      $txnStatus = (string)($txnList['txnStatus'] ?? '');
      $approvalRefNumber = (string)($txnList['approvalRefNumber'] ?? '');

      // update DB
      $txn->update([
        'response_code' => $responseCode,
        'response_reason' => $responseReason,
        'status' => strtolower($txnStatus),
        'approval_ref_number' => $approvalRefNumber
      ]);

      return response()->json([
        'status' => true,
        'txn_status' => $txnStatus,
        'reference' => $approvalRefNumber
      ]);
    } catch (\Exception $e) {

      return response()->json([
        'status' => false,
        'message' => $e->getMessage()
      ]);
    }
  }
}