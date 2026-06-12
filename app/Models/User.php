<?php

namespace App\Models;

use App\Concerns\HasTeams;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

#[Fillable(['name', 'username', 'email', 'password', 'current_team_id', 'supplier_id', 'role_id', 'role', 'status'])]
#[Hidden(['password'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasTeams, Notifiable;

    /**
     * Disable remember_token — column was removed intentionally.
     */
    protected $rememberTokenName = '';

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (User $user) {
            if (empty($user->uuid)) {
                $user->uuid = (string) Str::uuid();
            }

            if (empty($user->username)) {
                $user->username = Str::of((string) Str::before($user->email, '@'))
                    ->lower()
                    ->replaceMatches('/[^a-z0-9._-]+/', '-')
                    ->trim('-._')
                    ->value();
            }
        });

        static::saving(function (User $user): void {
            if ($user->username !== null) {
                $user->username = Str::lower($user->username);
            }
        });

        static::saved(function (User $user): void {
            Cache::forget("supplier.{$user->supplier_id}.billable_users.count");
        });

        static::deleted(function (User $user): void {
            Cache::forget("supplier.{$user->supplier_id}.billable_users.count");
        });
    }

    /**
     * Get the supplier assigned to the user.
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the assigned portal role.
     *
     * @return BelongsTo<Role, $this>
     */
    public function portalRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Scope a query to only include active users.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include users for a given supplier.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeForSupplier($query, ?int $supplierId)
    {
        if ($supplierId === null) {
            return $query;
        }

        return $query->where('supplier_id', $supplierId);
    }

    /**
     * Scope a query to filter by name, email or role.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeSearch($query, string $term)
    {
        $isPostgres = $query->getConnection()->getDriverName() === 'pgsql';
        $op = $isPostgres ? 'ilike' : 'like';

        return $query->where(function ($query) use ($term, $op) {
            $query->where('name', $op, "%{$term}%")
                ->orWhere('username', $op, "%{$term}%")
                ->orWhere('email', $op, "%{$term}%")
                ->orWhere('role', $op, "%{$term}%");
        });
    }
}
