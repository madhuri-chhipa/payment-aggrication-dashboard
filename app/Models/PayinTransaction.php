<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayinTransaction extends Model
{
  protected $table = 'payin_transactions';

  protected $fillable = [
    'id',
    'user_id',
    'txn_id',
    'payer_name',
    'basic_details',
    'transfer_mode',
    'amount',
    'charge_amount',
    'gst_amount',
    'total_charge',
    'total_amount',
    'status',
    'payment_status',
    'api_payment_status',
    'created_at',
    'created_by',
    'updated_at',
    'updated_by',
    'api',
    'api_txn_id',
    'utr',
    'payment_link',
    'description',
    'response_message',
    'ip',
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }
  public $timestamps = false; // Because you're manually handling created_at / updated_at
}
