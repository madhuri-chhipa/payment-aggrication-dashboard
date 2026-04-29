<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutTransaction extends Model
{
    protected $table = 'payout_transactions';

    protected $fillable = [
        'id',
        'user_id',
        'txn_id',
        'bene_account',
        'bene_ifsc',
        'bene_name',
        'bene_email',
        'bene_mobile',
        'basic_details',
        'platform_fee',
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
        'processed_at',
        'processed_by',
        'api',
        'api_txn_id',
        'utr',
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
