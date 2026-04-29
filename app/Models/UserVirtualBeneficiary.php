<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserVirtualBeneficiary extends Model
{
  use HasFactory;

  protected $table = 'user_virtual_beneficiaries';

  protected $primaryKey = 'id';

  public $timestamps = true;

  /**
   * Mass assignable fields
   */
  protected $fillable = [
    'user_id',
    'bene_name',
    'bene_account',
    'bene_ifsc',
    'bene_email',
    'bene_mobile',
    'bene_proof_path',
  ];

  /**
   * Hidden fields (optional)
   * Hide sensitive info if you ever return this model as JSON
   */
  protected $hidden = [
    'bene_account',
    'bene_ifsc',
  ];

  /**
   * Casts
   */
  protected $casts = [
    'user_id' => 'integer',
  ];

  /**
   * Relationship: beneficiary belongs to user
   * Adjust model + key if you use `superUser` instead of `users`
   */
  public function user()
  {
    return $this->belongsTo(\App\Models\User::class, 'user_id', 'id');
  }
}