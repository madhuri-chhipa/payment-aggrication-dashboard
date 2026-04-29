<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserService extends Model
{
  use HasFactory, SoftDeletes;

  protected $table = 'user_services';

  protected $fillable = [
    'user_id',
    'payout_status',
    'payin_status',
    'minimum_transaction',
    'maximum_transaction',
    'payin_minimum_transaction',
    'payin_maximum_transaction',
    'wallet_type',
    'virtual_charges',
    'payout_charges',
    'platform_fee',
    'virtual_type',
    'pslab_1000',
    'pslab_25000',
    'pslab_200000',
    'pflat_charges',
    'pflat_charges_2',
    'payin_charges',
    'active_payout_api',
    'active_payin_api',
    'payout_service_enable',
    'load_money_service_enable',
    'bill_payment_service_enable',
    'bbps_charges'
  ];

  /* ==========================
     | Relationships
     ==========================*/

  public function user()
  {
    return $this->belongsTo(User::class);
  }
}
