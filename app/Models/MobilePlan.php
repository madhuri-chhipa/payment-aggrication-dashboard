<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MobilePlan extends Model
{
    use HasFactory;

    protected $table = 'mobile_plans';

    protected $fillable = [
        'biller_id',
        'plan_id',
        'category_type',
        'amount',
        'plan_desc',
        'additional_info',
        'effective_from',
        'effective_to',
        'status'
    ];

    protected $casts = [
        'additional_info' => 'array',
        'effective_from'  => 'date',
        'effective_to'    => 'date',
    ];
}
