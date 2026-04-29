<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BbpsBillTransaction extends Model
{
  use HasFactory;

  protected $table = 'bbps_bill_transactions';

  protected $fillable = [
    'user_id',
    'biller_id',
    'category',
    'biller_name',
    'request_id',
    'amount',
    'bill_params',
    'response_code',
    'response_reason',
    'bbps_txn_ref_id',
    'approval_ref_number',
    'customer_name',
    'customer_email',
    'customer_mobile',
    'bill_amount',
    'status',
    'api_request',
    'api_response',
  ];

  protected $casts = [
    'bill_params' => 'array',
    'api_request' => 'array',
    'api_response' => 'array',
    'amount' => 'decimal:2',
    'bill_amount' => 'decimal:2',
  ];

  /**
   * Relationship with User
   */
  public function user()
  {
    return $this->belongsTo(User::class);
  }
}