<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserBankAccount extends Model
{
  use HasFactory, SoftDeletes;

  protected $table = 'user_bank_accounts';

  protected $fillable = [
    'bank_name',
    'branch_name',
    'account_holder_name',
    'account_number',
    'ifsc',
    'status',
  ];
}
