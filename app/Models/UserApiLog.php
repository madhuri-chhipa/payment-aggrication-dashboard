<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserApiLog extends Model
{
    use HasFactory;

    protected $table = 'user_api_logs';

    protected $fillable = [
        'user_id',
        'uid',
        'event',
        'api_url',
        'header',
        'request',
        'response',
        'http_code',
        'created_at',
    ];

    /**
     * Cast JSON fields to array automatically
     */
    protected $casts = [
        'header'   => 'array',
        'request'  => 'array',
        'response' => 'array',
    ];

    /**
     * Relationship: Log belongs to User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}