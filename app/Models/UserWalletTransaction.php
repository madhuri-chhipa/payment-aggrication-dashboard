<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserWalletTransaction extends Model
{
  protected $table = 'user_wallet_transactions';

  protected $fillable = [
    'user_id',
    'service_name',
    'refid',
    'opening_balance',
    'total_charge',
    'total_amount',
    'amount',
    'closing_balance',
    'credit',
    'debit',
    'description',
  ];

  public function user()
  {
    return $this->belongsTo(\App\Models\User::class, 'user_id', 'id');
  }
}