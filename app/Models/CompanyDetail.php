<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyDetail extends Model
{
  use HasFactory, SoftDeletes;

  protected $table = 'company_details';

  protected $fillable = [
    'user_id',
    'name',
    /* Company */
    'company_type',
    'gst_no',
    'gst_image',
    'cin',
    'cin_image',
    'pan',
    'pan_image',
    'udhyam_number',
    'udhyam_image',
    'address',
    'moa_image',
    'br_image',

    /* Director */
    'director_name',
    'director_email',
    'director_mobile',
    'director_aadhar_no',
    'director_aadhar_image',
    'director_pan_no',
    'director_pan_image',
  ];

  /* ==========================
     | Relationships
     ==========================*/

  public function user()
  {
    return $this->belongsTo(User::class);
  }
}
