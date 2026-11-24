<?php

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LoginAttempt extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'user_id',
        'ip_address',
        'user_agent',
        'success',
        'failure_reason',
        'attempted_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'attempted_at' => 'datetime',
        ];
    }

    /**
     * The table associated with the model.
     */
    protected $table = 'login_attempts';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that should be mutated to dates.
     */
    protected $dates = ['attempted_at'];

    /**
     * Log a successful login attempt.
     */
    public static function logSuccess(string $username, ?int $userId): self
    {
        return self::create([
            'username' => $username,
            'user_id' => $userId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'success' => true,
            'attempted_at' => now(),
        ]);
    }

    /**
     * Log a failed login attempt.
     */
    public static function logFailure(string $username, ?int $userId, string $reason): self
    {
        return self::create([
            'username' => $username,
            'user_id' => $userId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'success' => false,
            'failure_reason' => $reason,
            'attempted_at' => now(),
        ]);
    }
}
