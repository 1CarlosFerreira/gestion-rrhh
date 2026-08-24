<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Rut\Rut;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'rut',
        'email',
        'password',
        'active',
    ];

    public function asignacionesUnidad(): HasMany
    {
        return $this->hasMany(UserUnidad::class);
    }

    public function unidadesHabilitadas(): BelongsToMany
    {
        $today = now()->toDateString();

        return $this->belongsToMany(UnidadServicio::class, 'user_unidades')
            ->withPivot(['id', 'active', 'valid_from', 'valid_to'])
            ->withTimestamps()
            ->where('user_unidades.active', true)
            ->where(function ($query) use ($today): void {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', $today);
            })
            ->where(function ($query) use ($today): void {
                $query->whereNull('valid_to')->orWhere('valid_to', '>=', $today);
            });
    }

    public function setRutAttribute(?string $value): void
    {
        $this->attributes['rut'] = $value === null || $value === '' ? null : Rut::normalize($value);
    }

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
            'active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }
}
