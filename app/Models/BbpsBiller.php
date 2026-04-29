<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BbpsBiller extends Model
{
  use HasFactory;

  protected $table = 'bbps_billers';

  protected $fillable = [
    'biller_id',
    'category',
    'biller_name',
    'location',
    'biller_adhoc',
    'customer_params',
    'is_validation_api',
    'is_fetch_api',
    'is_plan_mdm_require',
    'payment_modes',
    'full_response'
  ];
}
