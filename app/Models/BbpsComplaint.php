<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BbpsComplaint extends Model
{
    use HasFactory;
    protected $table = 'bbps_complaints';

    protected $fillable = [
        'user_id',
        'txn_ref_id',
        'complaint_type',
        'biller_id',
        'agent_id',
        'participation_type',
        'serv_reason',
        'complaint_disposition',
        'complaint_desc',
        'bbps_complaint_assigned',
        'bbps_complaint_id',
        'response_code',
        'response_reason',
        'error_code',
        'error_message',
        'status',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
