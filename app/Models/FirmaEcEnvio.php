<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FirmaEcEnvio extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'firma_ec_envios';

    protected $fillable = [
        'solicitud_id',
        'user_id',
        'cedula',
        'nombre_documento',
        'estado',
        'pagina',
        'error',
        'firmado_en',
    ];

    protected function casts(): array
    {
        return [
            'firmado_en' => 'datetime',
            'pagina' => 'integer',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class);
    }
}
