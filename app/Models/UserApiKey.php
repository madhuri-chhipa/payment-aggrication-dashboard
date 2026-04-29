<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserApiKey extends Model
{
  use HasFactory, SoftDeletes;

  protected $table = 'user_api_keys';

  protected $fillable = [
    'user_id',
    'client_key',
    'client_secret',
    'payin_webhooks',
    'payout_webhooks',
    'ip',
    'bulkpe_auth_token',       
    'razorpay_account_number', 
    'razorpay_api_key',        
    'razorpay_secret_key',     
    'paywize_api_key',      
    'paywize_secret_key',      
    'buckbox_merchant_id',             
    'buckbox_merchant_name',           
    'buckbox_merchant_email',          
    'buckbox_api_key',         
    'buckbox_secret_key',      
    'buckbox_eny_key'         
  ];

  protected $hidden = [
    'client_secret',
  ];

  /* ==========================
     | Relationships
     ==========================*/

  public function user()
  {
    return $this->belongsTo(User::class);
  }
}
