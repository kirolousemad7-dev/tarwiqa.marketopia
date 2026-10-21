<?php

namespace App\Models;

use App\Traits\HasPageAccess;
use Database\Factories\UserFactory;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable(['name', 'username', 'email', 'phone', 'additional_phone', 'national_id', 'address', 'notes', 'password', 'role', 'gender', 'platform', 'dob', 'status', 'ban_type', 'verification_status', 'screenshot_allowed', 'city_id', 'governorate_id', 'locale', 'timezone', 'settings'])]
#[Hidden(['password', 'remember_token', 'national_id'])]
class User extends Authenticatable implements CanResetPasswordContract, HasMedia, JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use CanResetPassword, HasFactory, HasPageAccess, InteractsWithMedia, Notifiable, SoftDeletes;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_SUPPORTER = 'supporter';

    public const ROLE_PARTNER = 'partner';

    public function isRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function dashboardRoute(): string
    {
        return match ($this->role) {
            self::ROLE_SUPER_ADMIN => 'admin.dashboard',
            self::ROLE_SUPPORTER => 'supporter.dashboard',
            self::ROLE_PARTNER => 'partner.dashboard',
            default => 'login',
        };
    }

    public function accountStateLabel(): string
    {
        return match (true) {
            $this->status === 'suspended' && $this->ban_type === 'permanent' => 'Permanently Banned',
            $this->status === 'suspended' => 'Temporarily Banned',
            $this->status === 'restricted' => 'Restricted',
            $this->status === 'inactive' => 'Inactive',
            default => 'Active',
        };
    }

    public function accountStateClass(): string
    {
        return match ($this->status) {
            'inactive', 'restricted' => 'inactive',
            'suspended' => 'banned',
            default => 'active',
        };
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return ['role' => $this->role];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $user->uuid ??= (string) Str::uuid();
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->width(160)->height(160)->nonQueued();
    }

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
            'screenshot_allowed' => 'boolean',
            'settings' => 'array',
        ];
    }

    /**
     * Stored encrypted. Legacy plaintext values remain readable until the row is saved again.
     * The value is hidden from array and JSON serialization.
     */
    protected function nationalId(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                if ($value === null || $value === '') {
                    return $value;
                }

                try {
                    return Crypt::decryptString($value);
                } catch (DecryptException) {
                    return $value;
                }
            },
            set: function (?string $value): ?string {
                if ($value === null || $value === '') {
                    return $value;
                }

                return Crypt::encryptString($value);
            },
        );
    }

    public function maskedNationalId(): ?string
    {
        $value = $this->national_id;
        if (! is_string($value) || $value === '') {
            return null;
        }

        $visible = substr($value, -4);

        return str_repeat('•', max(strlen($value) - 4, 0)).$visible;
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function maids(): HasMany
    {
        return $this->hasMany(Maid::class, 'partner_id');
    }

    public function assignedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'partner_id');
    }

    public function governorates(): BelongsToMany
    {
        return $this->belongsToMany(Governorate::class, 'user_governorates');
    }

    public function partnerAreas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'partner_areas');
    }

    public function appShares(): HasMany
    {
        return $this->hasMany(AppShare::class);
    }
}
