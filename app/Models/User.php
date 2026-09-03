<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Rut\Rut;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'persona_id',
        'email',
        'password',
        'active',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function tramitesCreados(): HasMany
    {
        return $this->hasMany(Tramite::class, 'created_by');
    }

    public function historialTramites(): HasMany
    {
        return $this->hasMany(TramiteHistorial::class);
    }

    public function adjuntosCargados(): HasMany
    {
        return $this->hasMany(TramiteAdjunto::class, 'uploaded_by');
    }

    public function accesosOperativos(): HasMany
    {
        return $this->hasMany(UserUnidadAcceso::class);
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
