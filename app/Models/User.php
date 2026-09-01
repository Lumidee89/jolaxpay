<?php

namespace App\Models;

use App\Enums\AccountType;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Shared by mobile customers and admin/support staff (see the users
 * migration for why). Sanctum powers the mobile guard; the web session
 * guard + HasRoles powers admin access — the two are otherwise independent
 * (TRD §2.2, §7).
 */
#[Fillable([
    'full_name', 'phone_number', 'email', 'avatar_path', 'password', 'country_code', 'is_diaspora',
    'is_active', 'email_verified_at', 'phone_verified_at', 'account_type', 'referral_code', 'agent_approved_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_diaspora' => 'boolean',
            'is_active' => 'boolean',
            'account_type' => AccountType::class,
            'agent_approved_at' => 'datetime',
        ];
    }

    public function businessLedgerEntries(): HasMany
    {
        return $this->hasMany(BusinessLedgerEntry::class);
    }

    public function isAgentAccount(): bool
    {
        return $this->account_type === AccountType::Agent;
    }

    /** Backward-compatible internal alias while /business routes are retired. */
    public function isBusinessAccount(): bool
    {
        return $this->isAgentAccount();
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    /** Convenience accessor for the primary (NGN) wallet. */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class)->where('currency', 'NGN');
    }

    public function meters(): HasMany
    {
        return $this->hasMany(Meter::class);
    }

    public function meterGroups(): HasMany
    {
        return $this->hasMany(MeterGroup::class);
    }

    /** Saved airtime/data/cable_tv/education recipients — the non-electricity counterpart to meters(). */
    public function beneficiaries(): HasMany
    {
        return $this->hasMany(Beneficiary::class);
    }

    public function powerCircle(): HasMany
    {
        return $this->hasMany(PowerCircleContact::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function scheduledPurchases(): HasMany
    {
        return $this->hasMany(ScheduledPurchase::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function devicePushTokens(): HasMany
    {
        return $this->hasMany(DevicePushToken::class);
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function referralsMade(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    public function referralAttribution(): HasOne
    {
        return $this->hasOne(Referral::class, 'referred_user_id');
    }

    public function agentCommissions(): HasMany
    {
        return $this->hasMany(AgentCommission::class, 'agent_id');
    }

    /** Is this user staff (any admin-panel role), as opposed to a customer? */
    public function isStaff(): bool
    {
        return $this->roles()->exists();
    }
}
