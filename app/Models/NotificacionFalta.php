<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificacionFalta extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'destinatario_id',
        'decano_id',
        'emitido_por_id',
        'fecha_falta',
        'tipo_marcacion',
        'codigo_oficio',
        'emitido_delegado',
        'tiene_justificacion_aprobada',
        'notas',
        'aviso_correo_enviado_at',
        'aviso_correo_destino',
        'aviso_correo_error',
    ];

    protected function casts(): array
    {
        return [
            'fecha_falta' => 'date',
            'emitido_delegado' => 'boolean',
            'tiene_justificacion_aprobada' => 'boolean',
            'aviso_correo_enviado_at' => 'datetime',
        ];
    }

    public function destinatario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinatario_id');
    }

    public function decano(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decano_id');
    }

    public function emitidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emitido_por_id');
    }
}
