<table border="1">
  <thead>
    <tr>
      <th>Date</th>
      <th>Request ID</th>
      <th>Wallet Txn ID</th>
      <th>Amount</th>
      <th>Status</th>
      <th>UTR</th>
      <th>Sender Account</th>
      <th>Mode</th>
      <th>Description</th>
    </tr>
  </thead>
  <tbody>
    @foreach ($rows as $r)
      <tr>
        <td>{{ $r->created_at }}</td>
        <td>{{ $r->request_id }}</td>
        <td>{{ $r->wallet_txn_id }}</td>
        <td>{{ $r->amount }}</td>
        <td>{{ $r->status }}</td>
        <td>{{ $r->transaction_utr }}</td>
        <td>{{ $r->sender_account_number }}</td>
        <td>{{ $r->mode }}</td>
        <td>{{ $r->description }}</td>
      </tr>
    @endforeach
  </tbody>
</table>
