<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <title>Bank Statement PDF</title>
  <style>
    body {
      font-family: DejaVu Sans, sans-serif;
      font-size: 10px;
    }

    .title {
      text-align: center;
      font-size: 16px;
      font-weight: bold;
      margin-bottom: 5px;
    }

    .subtitle {
      text-align: center;
      font-size: 11px;
      margin-bottom: 15px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th,
    td {
      border: 1px solid #000;
      padding: 5px;
      font-size: 9px;
      vertical-align: top;
    }

    th {
      background: #f2f2f2;
    }

    .text-right {
      text-align: right;
    }

    .text-center {
      text-align: center;
    }
  </style>
</head>

<body>
  <div class="title">Bank Statement</div>
  <div class="subtitle">From {{ $startDate }} To {{ $endDate }}</div>

  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Tran ID</th>
        <th>Value Date</th>
        <th>Tran Date</th>
        <th>Post Date</th>
        <th>Particulars</th>
        <th>Amount</th>
        <th>Dr/Cr</th>
        <th>Type</th>
        <th>Sub Type</th>
        <th>Balance After Tran</th>
        <th>Instrument No</th>
        <th>Serial No</th>
        <th>Last Tran</th>
      </tr>
    </thead>
    <tbody>
      @forelse($transactions as $index => $txn)
        <tr>
          <td class="text-center">{{ $index + 1 }}</td>
          <td>{{ $txn['tranId'] ?? '' }}</td>
          <td>
            @if (!empty($txn['tranValueDate']))
              {{ \Carbon\Carbon::createFromFormat('Ymd', $txn['tranValueDate'])->format('d-m-Y') }}
            @endif
          </td>
          <td>
            @if (!empty($txn['tranDate']))
              {{ \Carbon\Carbon::createFromFormat('Ymd', $txn['tranDate'])->format('d-m-Y') }}
            @endif
          </td>
          <td>
            @if (!empty($txn['tranPostDate']))
              {{ \Carbon\Carbon::createFromFormat('YmdHis', $txn['tranPostDate'])->format('d-m-Y h:i:s A') }}
            @endif
          </td>
          <td>{{ $txn['tranParticulars'] ?? '' }}</td>
          <td class="text-right">{{ $txn['tranAmount'] ?? '' }}</td>
          <td>{{ $txn['drCRIndicator'] ?? '' }}</td>
          <td>{{ $txn['tranType'] ?? '' }}</td>
          <td>{{ $txn['tranSubType'] ?? '' }}</td>
          <td class="text-right">{{ $txn['balAfterTran'] ?? '' }}</td>
          <td>{{ $txn['instrumentNumber'] ?? '' }}</td>
          <td>{{ $txn['serialNo'] ?? '' }}</td>
          <td>{{ $txn['isLastTran'] ?? '' }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="14" class="text-center">No transactions found.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</body>

</html>
