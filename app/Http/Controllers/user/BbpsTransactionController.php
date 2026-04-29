<?php

namespace App\Http\Controllers\user;

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
    return view('user.reports.bbps_transactions');
  }
  public function getBbpsTransactions(Request $request)
  {
    $query = BbpsBillTransaction::where('user_id', Auth::id())->with('user')
      ->when(
        $request->request_id,
        fn($q, $v) =>
        $q->where('request_id', 'like', "%{$v}%")
      )

      ->when(
        $request->bbps_txn_ref_id,
        fn($q, $v) =>
        $q->where('bbps_txn_ref_id', 'like', "%{$v}%")
      )
      ->when(
        $request->status !== null,
        fn($q) =>
        $q->where('status', $request->status)
      )
      ->when($request->biller, function ($q, $v) {
        $q->where('biller_id', 'like', "%{$v}%")
          ->orWhere('biller_name', 'like', "%{$v}%");
      })
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
          'SUCCESS' => '<span class="badge bg-success">Sucess</span>',
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
              <a class="dropdown-item"
                href="' . route('transactions.check-status-page') . '">
                <i class="fa fa-rotate text-info me-1"></i>
                Track Status
              </a>
            </li>
          </ul>
        </div>
      ';
      })
      ->rawColumns(['status', 'biller', 'actions'])
      ->toJson();
  }


  public function showAjax($id)
  {
    $txn = BbpsBillTransaction::findOrFail($id);
    $txn->created_at_formatted  = $txn->created_at->format('d/m/Y H:i:s');
    return response()->json($txn);
  }

  public function printTxn($id)
  {
    $txn = BbpsBillTransaction::with('user')->findOrFail($id);

    $api = [];

    if (!empty($txn->api_response)) {
      $api = json_decode($txn->api_response, true) ?? [];
    }

    return view('transactions.print', compact('txn', 'api'));
  }

  public function searchTxnRef(Request $request)
  {
    $search = $request->search;

    $query = BbpsBillTransaction::query()
      ->select('bbps_txn_ref_id')
      ->whereNotNull('bbps_txn_ref_id')
      ->where('bbps_txn_ref_id', '!=', '')
      ->orderBy('created_at', 'desc');   // descending latest first

    if ($search) {
      $query->where('bbps_txn_ref_id', 'like', "%{$search}%");
    }

    $transactions = $query->limit(10)->get();

    return response()->json($transactions);
  }
  public function checkTransactionStatus(Request $request, BbpsService $bbps)
  {
    try {

      if ($request->trackType == 'TRANS_REF_ID') {

        $data = [
          'trackType'  => 'TRANS_REF_ID',
          'trackValue' => $request->trackValue
        ];
      }

      if ($request->trackType == 'MOBILE_NO') {

        $data = [
          'trackType'  => 'MOBILE_NO',
          'trackValue' => $request->trackValue,
          'fromDate'   => $request->from_date,
          'toDate'     => $request->to_date
        ];
      }
      $responseXml = $bbps->transactionStatus($data);

      $xml = simplexml_load_string($responseXml);

      if (!$xml) {
        return response()->json([
          'status' => false,
          'message' => 'Invalid API response'
        ]);
      }

      $responseCode   = (string) $xml->responseCode;
      $responseReason = (string) $xml->responseReason;
      $errorCode      = (string) $xml->errorCode;
      $errorCode      = (string) ($xml->errorInfo->error->errorCode ?? '');
      $errorMessage   = (string) ($xml->errorInfo->error->errorMessage ?? '');

      if ($responseCode !== '000') {

        return response()->json([
          'status' => false,
          'message' => $errorMessage
        ]);
      }

      $txn = $xml->txnList;
      $txnDate = Carbon::parse((string)$txn->txnDate)
        ->format('d-m-Y H:i:s');
      return response()->json([
        'status' => true,
        'message' => 'Transaction fetched successfully',
        'data' => [
          'status' => (string)$txn->txnStatus,
          'date' => $txnDate,
          'amount' => (string)$txn->amount,
          'biller_id' => (string)$txn->billerId,
          'txn_reference' => (string)$txn->txnReferenceId,
          'mobile' => (string)$txn->mobile,
          'approval_ref' => (string)$txn->approvalRefNumber,
          'customer_name' => (string)$txn->respCustomerName,
          'bill_number' => (string)$txn->respBillNumber,
          'bill_period' => (string)$txn->respBillPeriod,
          'due_date' => (string)$txn->respDueDate,
          'conv_fee' => (string)$txn->custConvFee,
          'request_id' => (string)$txn->payRequestId
        ]
      ]);
    } catch (\Exception $e) {

      return response()->json([
        'status' => false,
        'message' => $e->getMessage()
      ]);
    }
  }
}
