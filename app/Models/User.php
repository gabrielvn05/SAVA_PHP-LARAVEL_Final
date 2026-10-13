<?php

namespace App\Models;

use App\Enums\AppRole;
use App\Enums\CapabilityType;
use App\Services\CapabilitySeeder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'email',
        'password',
        'microsoft_id',
        'nombres',
        'apellidos',
        'rol',
        'activo',
        'cedula',
        'celular',
        'carrera',
        'jornada',
        'email_verified_at',
        'force_password_change',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'rol' => AppRole::class,
            'activo' => 'boolean',
            'email_verified_at' => 'datetime',
            'force_password_change' => 'boolean',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (User $user): void {
            app(CapabilitySeeder::class)->seedForUser($user);
        });

        static::updated(function (User $user): void {
            if ($user->wasChanged('rol')) {
                app(CapabilitySeeder::class)->seedForUser($user);
            }
        });
    }

    public function nombreCompleto(): string
    {
        return trim("{$this->nombres} {$this->apellidos}");
    }

    public function capabilities(): HasMany
    {
        return $this->hasMany(UserCapability::class);
    }

    public function solicitudesCreadas(): HasMany
    {
        return $this->hasMany(Solicitud::class, 'creado_por');
    }

    public function hasCapability(CapabilityType $capability): bool
    {
        if ($this->rol === AppRole::Superusuario) {
            return true;
        }

        foreach ($this->rol->defaultCapabilities() as $default) {
            if ($default === $capability) {
                return true;
            }
        }

        return $this->capabilities()
            ->where('capability', $capability->value)
            ->exists();
    }

    public function isSolicitante(): bool
    {
        return in_array($this->rol, [
            AppRole::Administrativo,
            AppRole::Docente,
            AppRole::Mantenimiento,
        ], true);
    }

    public function needsProfileCompletion(): bool
    {
        if ($this->rol === AppRole::Superusuario) {
            return false;
        }

        return trim((string) $this->cedula) === ''
            || trim((string) $this->carrera) === ''
            || trim((string) $this->celular) === '';
    }
}
