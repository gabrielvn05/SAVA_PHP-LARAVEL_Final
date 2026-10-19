<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BibliotecaDocumento extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'biblioteca_documentos';

    protected $fillable = [
        'titulo',
        'autor_nombre',
        'fecha_documento',
        'nombre_archivo',
        'ruta',
        'disco',
        'mime',
        'tamano',
        'subido_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_documento' => 'date',
            'tamano' => 'integer',
        ];
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    public function esPdf(): bool
    {
        return $this->mime === 'application/pdf' || str_ends_with(strtolower($this->nombre_archivo), '.pdf');
    }

    public function rutaAbsoluta(): string
    {
        if ($this->disco === 'recurso') {
            return resource_path('biblioteca/'.basename((string) $this->ruta));
        }

        return Storage::disk('local')->path((string) $this->ruta);
    }

    public function eliminarArchivo(): void
    {
        if ($this->disco === 'local' && $this->ruta !== '') {
            Storage::disk('local')->delete($this->ruta);
        }
    }

    public function fechaLegible(): string
    {
        $meses = [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
        ];

        $fecha = $this->fecha_documento;

        return $fecha->day.' de '.$meses[(int) $fecha->month].' de '.$fecha->year;
    }
}
