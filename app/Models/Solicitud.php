<?php

namespace App\Models;

use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Solicitud extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'solicitudes';

    protected $fillable = [
        'creado_por',
        'tipo',
        'fecha_inicio',
        'fecha_fin',
        'motivo',
        'detalle',
        'justificativo_path',
        'justificativo_nombre',
        'estado',
        'revisado_por',
        'firmado_por',
        'observaciones_secretaria',
        'observaciones_decano',
        'fecha_firma',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => SolicitudTipo::class,
            'estado' => SolicitudEstado::class,
            'detalle' => 'array',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'fecha_firma' => 'datetime',
        ];
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    public function firmante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'firmado_por');
    }
}
