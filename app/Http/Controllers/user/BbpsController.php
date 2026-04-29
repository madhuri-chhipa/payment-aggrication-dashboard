<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\BbpsBiller;
use App\Models\BbpsBillTransaction;
use App\Models\MobilePlan;
use App\Services\BbpsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Throwable;
use Yajra\DataTables\DataTables;

class BbpsController extends Controller
{
  protected $BbpsService;
  public function __construct(BbpsService $BbpsService)
  {
    $this->BbpsService = $BbpsService;
  }
  public function billPayment()
  {
    return view('user.services.bill_payment');
  }
  public function fetchBillers($category)
  {
    $billers = BbpsBiller::where('category', $category)->get();
    return view('user.services.bill_payment_category', compact('billers', 'category'));
  }
  // biller info api
  public function billerInfo($billerId, BbpsService $bbps)
  {
    try {
      // 1️⃣ Check DB First
      $biller = BbpsBiller::where('biller_id', $billerId)
        ->whereNotNull('customer_params')
        ->first();

      if ($biller) {
        return response()->json([
          'fields' => json_decode($biller->customer_params, true),
          'source' => 'db',
          'payment_method' => json_decode($biller->payment_modes, true),
          'is_fetch_api' => $biller->is_fetch_api,
          'is_validation_api' => $biller->is_validation_api,
          'is_plan_mdm_require' => $biller->is_plan_mdm_require
        ]);
      }
      // $biller = BbpsBiller::where('biller_id', $billerId)->first();
      // if (!$biller || empty($biller->full_response)) {
      //   return response()->json([
      //     'error' => 'No saved response found in DB'
      //   ], 404);
      // }

      // 2️⃣ Call API (Service handles logging)
      $responseXml = $bbps->billerInfo($billerId);
      // $responseXml = $biller->full_response;

      $xml = simplexml_load_string($responseXml);

      if (!$xml || (string)$xml->responseCode !== '000') {
        return response()->json([
          'error' => 'Invalid API Response'
        ], 500);
      }

      $params = [];

      foreach ($xml->biller as $billerData) {

        if (isset($billerData->billerInputParams->paramInfo)) {

          foreach ($billerData->billerInputParams->paramInfo as $param) {

            $params[] = [
              'paramName' => (string) $param->paramName,
              'dataType'  => (string) $param->dataType,
              'minLength' => (string) $param->minLength,
              'maxLength' => (string) $param->maxLength,
              'mandatory' => ((string)$param->isOptional === 'false') ? 'Y' : 'N',
              'visibility' => ((string)$param->visibility === 'true') ? 'Y' : 'N',
            ];
          }
        }
        // 3️⃣ Save / Update DB
        $res = BbpsBiller::updateOrCreate(
          ['biller_id' => (string) $billerData->billerId],
          [
            'biller_name'       => (string) $billerData->billerName,
            'category'          => (string) $billerData->billerCategory,
            'location'          => (string) $billerData->billerCoverage,
            'biller_adhoc'          => (string) $billerData->billerAdhoc,
            'customer_params'   => json_encode($params),
            'is_fetch_api'      => ((string)$billerData->billerFetchRequiremet === 'MANDATORY' || (string)$billerData->billerFetchRequiremet === 'OPTIONAL') ? '1' : '0',
            'is_validation_api' => ((string)$billerData->billerSupportBillValidation === 'MANDATORY' || (string)$billerData->billerSupportBillValidation === 'OPTIONAL') ? '1' : '0',
            'is_plan_mdm_require' => ((string)$billerData->planMdmRequirement === 'MANDATORY' || (string)$billerData->planMdmRequirement === 'OPTIONAL') ? '1' : '0',
            'payment_modes'     => json_encode($billerData->billerPaymentModes ?? []),
            'full_response'     => $responseXml
          ]
        );
      }
      return response()->json([
        'fields' => $params,
        'source' => 'api',
        'is_fetch_api' => $res->is_fetch_api,
        'is_validation_api' => $res->is_validation_api,
        'is_plan_mdm_require' => $res->is_plan_mdm_require,
      ]);
    } catch (\Exception $e) {
      return response()->json([
        'error' => $e->getMessage()
      ], 500);
    }
  }
  public function billFetch(Request $request): JsonResponse
  {
    try {
      $billerId = (string) $request->input('biller_id', '');
      $inputs   = (array) $request->input('inputs', []);
      $customer = (array) $request->input('customer', []);
      $device   = (array) $request->input('device', []);

      if ($billerId === '') {
        return response()->json([
          'success' => false,
          'message' => 'biller_id is required'
        ], 422);
      }
      $data = $this->BbpsService->billFetch($billerId, $inputs, $customer, $device);
      return response()->json([
        'success' => true,
        'data' => $data,
      ]);
    } catch (Throwable $e) {
      return response()->json([
        'success' => false,
        'message' => $e->getMessage(),
      ], 500);
    }
  }
  public function QuickbillPay(Request $request): JsonResponse
  {
    try {
      $billerId = (string) $request->input('biller_id', '');
      $inputs   = (array) $request->input('inputs', []);
      $customer = (array) $request->input('customer', []);
      $amount   = (string) $request->input('amount', '');
      $paymentMethod = (array) $request->input('paymentMethod', []);
      $paymentInfo   = (array) $request->input('paymentInfo', []);
      $planId   = (string) $request->input('plan_id', '');
      $isPlan = (string) $request->input('is_plan', '');
      if ($isPlan == '1') {
        if ($planId == '' || $planId == null) {
          return response()->json(['success' => false, 'message' => 'plan is required'], 422);
        }
      }
      if (!empty($planId)) {
        $planInfo = MobilePlan::find($planId);
        if (empty($planInfo)) {
          return response()->json(['success' => false, 'message' => 'plan not found'], 422);
        }
        $amount = $planInfo->amount;
      } else {
        $amount   = (string) $request->input('amount', '');
      }
      if ($billerId === '') {
        return response()->json(['success' => false, 'message' => 'biller_id is required'], 422);
      }
      if (trim((string)($customer['mobile'] ?? '')) === '') {
        return response()->json(['success' => false, 'message' => 'customer.mobile is required'], 422);
      }

      if ($amount === '' || (float)$amount <= 0) {
        return response()->json(['success' => false, 'message' => 'amount is required'], 422);
      }

      $data = $this->BbpsService->billPayNoFetch(
        billerId: $billerId,
        inputs: $inputs,
        customer: $customer,
        amount: $amount,
        paymentMethod: $paymentMethod,
        paymentInfo: $paymentInfo,
        device: (array) $request->input('device', []) // optional if you pass device
      );

      return response()->json(['success' => true, 'data' => $data]);
    } catch (Throwable $e) {
      return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
  }
  public function billPay(Request $request): JsonResponse
  {
    try {
      $billerId      = (string) $request->input('biller_id', '');
      $fetchRequestId = (string) $request->input('fetchRequestId', '');
      $inputs        = (array) $request->input('inputs', []);
      $customer      = (array) $request->input('customer', []);
      $amount        = (string) $request->input('amount', '');
      $paymentMethod = (array) $request->input('paymentMethod', []);
      $paymentInfo   = (array) $request->input('paymentInfo', []);

      // ✅ NEW (required for fetch-pay)
      $billerResponse = (array) $request->input('billerResponse', []);
      $additionalInfo = (array) $request->input('additionalInfo', []);

      if ($billerId === '') {
        return response()->json(['success' => false, 'message' => 'biller_id is required'], 422);
      }

      if (trim((string)($customer['mobile'] ?? '')) === '') {
        return response()->json(['success' => false, 'message' => 'customer.mobile is required'], 422);
      }

      if ($amount === '' || (float)$amount <= 0) {
        return response()->json(['success' => false, 'message' => 'amount is required'], 422);
      }

      // ✅ billerResponse.billAmount should be present in fetch-pay
      if (!isset($billerResponse['billAmount']) || trim((string)$billerResponse['billAmount']) === '') {
        return response()->json(['success' => false, 'message' => 'billerResponse.billAmount is required'], 422);
      }

      // ✅ Force not quickpay
      $paymentMethod['quickPay'] = 'N';

      $data = $this->BbpsService->billPayWithFetch(
        billerId: $billerId,
        requestID: $fetchRequestId,
        inputs: $inputs,
        customer: $customer,
        amount: $amount,
        paymentMethod: $paymentMethod,
        paymentInfo: $paymentInfo,
        billerResponse: $billerResponse,
        additionalInfo: $additionalInfo,
        device: (array) $request->input('device', [])
      );

      return response()->json(['success' => true, 'data' => $data]);
    } catch (Throwable $e) {
      return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
  }
  public function validateBill($billerId, Request $request, BbpsService $bbps)
  {
    try {
      $params = (array) $request->input('params', []);
      $device = (array) $request->input('device', []);

      $responseJson = $bbps->billValidation($billerId, $params, $device);

      $arr = json_decode(trim($responseJson), true);

      if (!is_array($arr)) {
        return response()->json([
          'error' => 'Invalid API response'
        ], 500);
      }

      return response()->json([
        'responseCode'   => (string)($arr['responseCode'] ?? ''),
        'responseReason' => (string)($arr['responseReason'] ?? ''),
        'approvalRefNo'  => (string)($arr['approvalRefNo'] ?? ''),
        'billAmount'     => (string)($arr['billAmount'] ?? ''),
        'customerName'   => (string)($arr['customerName'] ?? ''),
        'billNumber'     => (string)($arr['billNumber'] ?? ''),
        'billDate'       => (string)($arr['billDate'] ?? ''),
        'dueDate'        => (string)($arr['dueDate'] ?? ''),
        'additionalInfo' => $arr['additionalInfo'] ?? null,
        'raw'            => $arr,
      ]);
    } catch (\Exception $e) {
      return response()->json([
        'error' => $e->getMessage()
      ], 500);
    }
  }

  public function viewPlans($billerId, BbpsService $bbps)
  {
    try {
      // 1️⃣ Check DB first
      $plans = MobilePlan::where('biller_id', $billerId)->get();

      if ($plans->count() > 0) {
        return response()->json([
          'plans' => $plans,
          'source' => 'database'
        ]);
      }
      // 2️⃣ Call API
      $responseXml = $bbps->planPull($billerId);
      $xml = simplexml_load_string($responseXml);
      if (!$xml || (string)$xml->responseCode !== '000') {
        return response()->json([
          'error' => 'Invalid API Response'
        ], 500);
      }
      $plansData = [];
      if (isset($xml->planDetails)) {
        foreach ($xml->planDetails as $plan) {
          $plansData[] = [
            'biller_id'      => (string)$plan->billerId,
            'plan_id'        => (string)$plan->planId,
            'category_type'  => (string)$plan->categoryType,
            'amount'         => (float)$plan->amountInRupees,
            'plan_desc'      => (string)$plan->planDesc,
            'additional_info' => json_encode($plan->planAddnlInfo ?? []),
            'effective_from' => (string)$plan->effectiveFrom,
            'effective_to'   => (string)$plan->effectiveTo,
            'status'         => (string)$plan->status,
          ];
        }
      }

      // 3️⃣ Insert plans
      MobilePlan::insert($plansData);

      return response()->json([
        'plans' => $plansData,
        'source' => 'api'
      ]);
    } catch (\Exception $e) {

      return response()->json([
        'error' => $e->getMessage()
      ], 500);
    }
  }
}