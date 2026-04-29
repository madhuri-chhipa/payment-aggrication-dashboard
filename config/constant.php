<?php

use Illuminate\Support\Str;

return [
  "ACTIVE_PIPE_TYPE" => [
    "None" => "None",
    "Bulkpe" => "Bulkpe",
    "Finkeda" => "Finkeda",
    "Buckbox" => "Buckbox",
    "Unionbank" => "Unionbank",
    "Bluswap" => "Bluswap",
  ],
  "COMPLAINT_DISPOSITION" => [
    'Transaction Successful, Amount Debited but services not received',
    'Transaction Successful, Amount Debited but Service Disconnected or Service Stopped',
    'Transaction Successful, Amount Debited but Late Payment Surcharge Charges add in next bill',
    'Erroneously paid in wrong account',
    'Duplicate Payment',
    'Erroneously paid the wrong amount',
    'Payment information not received from Biller or Delay in receiving payment information from the Biller',
    'Bill Paid but Amount not adjusted or still showing due amount',
  ],
];