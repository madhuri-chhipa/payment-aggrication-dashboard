<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\UserDetail;
use App\Models\CompanyAccount;

class FundRequest extends Model
{
    use HasFactory;

    protected $table = 'fund_requests';

    protected $fillable = [
        'user_id',
        'request_id',
        'wallet_txn_id',
        'amount',
        'sender_account_number',
        'company_account_number',
        'transaction_utr',
        'mode',
        'remark',
        'description',
        'status',
        'source',
    ];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function companyAccount()
    {
        return $this->belongsTo(CompanyAccount::class,'company_account_number');
    }
}
