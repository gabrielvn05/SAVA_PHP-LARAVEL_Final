<?php

namespace App\Models;

use App\Enums\AccountRequestStatus;
use App\Enums\AppRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountRequest extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'email',
        'nombres',
        'apellidos',
        'cedula',
        'celular',
        'carrera',
        'jornada',
        'rol_solicitado',
        'motivo',
        'status',
        'rechazo_comentario',
        'handled_by',
        'handled_at',
    ];

    protected function casts(): array
    {
        return [
            'rol_solicitado' => AppRole::class,
            'status' => AccountRequestStatus::class,
            'handled_at' => 'datetime',
        ];
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
