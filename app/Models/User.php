<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
  /** @use HasFactory<\Database\Factories\UserFactory> */
  use HasFactory, Notifiable, SoftDeletes;

  /**
   * The attributes that are mass assignable.
   *
   * @var list<string>
   */
  protected $fillable = [
    'uid',
    'company_name',
    'email',
    'mobile_number',
    'password',
    'active',
    'payout_balance',
    'payin_balance',
    'reserve_balance',
    'freeze_balance',
    'virtual_balance',
    'auth_token',
    'auth_token_expiry',
    'login_otp',
    'login_otp_expires_at',
    'reset_otp',
    'reset_otp_expires_at',
  ];

  /**
   * The attributes that should be hidden for serialization.
   *
   * @var list<string>
   */
  protected $hidden = [
    'password',
    'remember_token',
  ];

  /**
   * Get the attributes that should be cast.
   *
   * @return array<string, string>
   */
  protected function casts(): array
  {
    return [
      'email_verified_at' => 'datetime',
      'password' => 'hashed',
    ];
  }

  public function companyDetail()
  {
    return $this->hasOne(CompanyDetail::class);
  }

  public function services()
  {
    return $this->hasOne(UserService::class);
  }

  public function apiKey()
  {
    return $this->hasOne(UserApiKey::class);
  }

  public static function findByEmailOrUsername($login)
  {
    return self::where('email', $login)
      ->orWhere('company_name', $login)
      ->first();
  }
  public function getJWTIdentifier()
  {
    return $this->getKey();
  }

  /**
   * Return a key value array, containing any custom claims to be added to the JWT.
   */
  public function getJWTCustomClaims(): array
  {
    return [];
  }
}
