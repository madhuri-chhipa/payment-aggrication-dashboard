<!DOCTYPE html>
<html>

<head>
  <title>Payment Status</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-light">

  <div class="container mt-5">

    <div class="row justify-content-center">
      <div class="col-md-6">

        <div class="card shadow">

          <div class="card-body text-center p-5">

            @if ($status == 'SUCCESS')
              <div class="text-success mb-3">
                <h2>Payment Successful</h2>
              </div>

              <p class="mb-1"><strong>Transaction ID:</strong> {{ $txn_id }}</p>
              <p class="mb-1"><strong>Amount:</strong> ₹{{ $amount }}</p>

              <p class="mt-3">Your wallet has been credited successfully.</p>
            @elseif($status == 'FAILED')
              <div class="text-danger mb-3">
                <h2>Payment Failed</h2>
              </div>

              <p class="mb-1"><strong>Transaction ID:</strong> {{ $txn_id }}</p>

              <p class="mt-3">Your payment could not be processed.</p>
            @else
              <div class="text-warning mb-3">
                <h2>Payment Pending</h2>
              </div>

              <p>Please wait while we confirm your payment.</p>
            @endif

            <a href="{{ url('/') }}" class="btn btn-primary mt-4">
              Go to Dashboard
            </a>

          </div>

        </div>

      </div>
    </div>

  </div>

</body>

</html>
