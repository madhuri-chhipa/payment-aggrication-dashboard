<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserVirtualAccount extends Model
{
  protected $table = 'users_virtual_accounts';

  protected $fillable = [
    'user_id',
    'virtual_account_id',
    'customer_id',
    'receiver_id',
    'entity',
    'status',
    'bank_name',
    'account_number',
    'ifsc',
    'description',
    'name',
    'closed_at',
    'created_on',
    'created_at',
  ];

  public $timestamps = false;
}