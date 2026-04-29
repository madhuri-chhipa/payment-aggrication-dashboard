<!DOCTYPE html>
<html>

<head>

  <title>Transaction Receipt</title>

  <style>
    body {
      font-family: Arial, Helvetica, sans-serif;
      background: #f5f6fa;
      padding: 30px;
    }

    .receipt {
      max-width: 520px;
      margin: auto;
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
      padding: 25px;
    }

    /* HEADER */

    .header {
      border-bottom: 2px dashed #ddd;
      padding-bottom: 15px;
      margin-bottom: 20px;
    }

    .header-top {
      display: flex;
      justify-content: flex-end;
      align-items: center;
    }

    .header-top img {
      width: 110px;
    }

    .date {
      text-align: right;
      font-size: 13px;
      color: #555;
      margin-top: 5px;
    }

    .header-title {
      text-align: center;
      margin-top: 10px;
    }

    .header-title h2 {
      margin: 0;
      font-size: 22px;
    }

    .sub {
      color: #777;
      font-size: 13px;
    }

    /* AMOUNT */

    .amount {
      font-size: 24px;
      font-weight: bold;
      color: #2e7d32;
      text-align: center;
      margin: 15px 0;
    }

    /* STATUS */

    .status {
      text-align: center;
      font-weight: bold;
      margin-bottom: 15px;
    }

    .success {
      color: #2e7d32;
    }

    /* SECTION */

    .section {
      margin-top: 20px;
      font-weight: bold;
      font-size: 14px;
      color: #444;
      border-bottom: 1px solid #eee;
      padding-bottom: 5px;
      margin-bottom: 10px;
    }

    .row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 8px;
      font-size: 14px;
    }

    .label {
      color: #555;
    }

    .value {
      font-weight: 500;
    }

    /* PARAM ROW */

    .param-row {
      display: flex;
      justify-content: space-between;
      font-size: 13px;
      margin-bottom: 4px;
    }

    /* FOOTER */

    .footer {
      text-align: center;
      font-size: 12px;
      color: #888;
      border-top: 1px dashed #ddd;
      margin-top: 20px;
      padding-top: 10px;
    }
  </style>

</head>

<body onload="window.print()">

  <div class="receipt">

    <!-- HEADER -->

    <div class="header">

      <div class="header-top">
        <img src="{{ asset('assets/img/logo/bbpsAssured.jpg') }}">
      </div>

      <div class="date">
        Date : {{ $txn->created_at->format('d/m/Y H:i:s') }}
      </div>

      <div class="header-title">
        <h2>Payment Receipt</h2>
        <div class="sub">BBPS Transaction</div>
      </div>

    </div>


    <!-- AMOUNT -->

    <div class="amount">
      ₹ {{ $api['respAmount'] ?? $txn->amount }}
    </div>

    <div class="status success">
      {{ strtoupper($txn->status) }}
    </div>


    <!-- TRANSACTION INFO -->

    <div class="section">Transaction Info</div>

    <div class="row">
      <div class="label">Request ID</div>
      <div class="value">{{ $txn->request_id }}</div>
    </div>

    <div class="row">
      <div class="label">BBPS Ref</div>
      <div class="value">{{ $api['txnRefId'] ?? '-' }}</div>
    </div>

    <div class="row">
      <div class="label">Approval Ref</div>
      <div class="value">{{ $api['approvalRefNumber'] ?? '-' }}</div>
    </div>

    <div class="row">
      <div class="label">Biller</div>
      <div class="value">{{ $txn->biller_name ?? '-' }}</div>
    </div>


    <!-- CUSTOMER INFO -->

    <div class="section">Customer Info</div>

    <div class="row">
      <div class="label">Customer Name</div>
      <div class="value">{{ $api['respCustomerName'] ?? '-' }}</div>
    </div>

    <div class="row">
      <div class="label">Mobile</div>
      <div class="value">{{ $txn->customer_mobile }}</div>
    </div>

    <div class="row">
      <div class="label">Email</div>
      <div class="value">{{ $txn->customer_email }}</div>
    </div>


    <!-- BILL DETAILS -->

    <div class="section">Bill Details</div>

    <div class="row">
      <div class="label">Bill Number</div>
      <div class="value">{{ $api['respBillNumber'] ?? '-' }}</div>
    </div>

    <div class="row">
      <div class="label">Bill Period</div>
      <div class="value">{{ $api['respBillPeriod'] ?? '-' }}</div>
    </div>

    <div class="row">
      <div class="label">Bill Date</div>
      <div class="value">{{ $api['respBillDate'] ?? '-' }}</div>
    </div>

    <div class="row">
      <div class="label">Due Date</div>
      <div class="value">{{ $api['respDueDate'] ?? '-' }}</div>
    </div>

    <div class="row">
      <div class="label">Convenience Fee</div>
      <div class="value">₹ {{ $api['custConvFee'] ?? '0' }}</div>
    </div>


    <!-- EXTRA PARAMS -->

    @if (!empty($api['inputParams']['input']))
      @foreach ($api['inputParams']['input'] as $param)
        <div class="param-row">
          <div>{{ $param['paramName'] }}</div>
          <div>{{ $param['paramValue'] }}</div>
        </div>
      @endforeach
    @endif


    <!-- FOOTER -->

    <div class="footer">
      This is a system generated receipt.<br>
      Please keep this for future reference.
    </div>

  </div>

</body>

</html>
