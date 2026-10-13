<?php

namespace App\Mail;

use App\Enums\SolicitudEstado;
use App\Models\Solicitud;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SolicitudResultadoMail extends Mailable
{
    use SerializesModels;

    public function __construct(public readonly Solicitud $solicitud) {}

    public function envelope(): Envelope
    {
        $aprobada = $this->solicitud->estado === SolicitudEstado::Aprobada;

        return new Envelope(
            subject: $aprobada
                ? 'SAVA: tu solicitud fue aprobada'
                : 'SAVA: tu solicitud fue rechazada',
        );
    }

    public function content(): Content
    {
        $solicitud = $this->solicitud->loadMissing('creador');
        $aprobada = $solicitud->estado === SolicitudEstado::Aprobada;
        $detalle = $solicitud->detalle ?? [];
        $observaciones = $aprobada
            ? ($solicitud->observaciones_decano ?: $solicitud->observaciones_secretaria)
            : ($solicitud->observaciones_decano ?: $solicitud->observaciones_secretaria);

        return new Content(
            htmlString: view('mail.solicitud-resultado', [
                'fullName' => $solicitud->creador->nombreCompleto(),
                'aprobada' => $aprobada,
                'tipo' => $solicitud->tipo->label(),
                'codigo' => $detalle['codigo_tramite'] ?? null,
                'periodo' => $solicitud->fecha_inicio->format('d/m/Y').' – '.$solicitud->fecha_fin->format('d/m/Y'),
                'motivo' => $solicitud->motivo,
                'observaciones' => $observaciones,
                'detalleUrl' => route('solicitudes.show', $solicitud),
            ])->render(),
        );
    }
}
