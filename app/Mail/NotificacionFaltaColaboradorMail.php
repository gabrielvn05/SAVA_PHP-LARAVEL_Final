<?php

namespace App\Mail;

use App\Models\NotificacionFalta;
use App\Support\NotificacionFaltaMarcacion;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificacionFaltaColaboradorMail extends Mailable
{
    use SerializesModels;

    public function __construct(public readonly NotificacionFalta $notificacion) {}

    public function envelope(): Envelope
    {
        $this->notificacion->loadMissing('destinatario');
        $destinatario = $this->notificacion->destinatario;

        if ($destinatario === null) {
            throw new \InvalidArgumentException('La notificación no tiene colaborador destinatario.');
        }

        $emailDestino = trim((string) $destinatario->email);
        if ($emailDestino === '') {
            throw new \InvalidArgumentException('El colaborador notificado no tiene correo.');
        }

        return new Envelope(
            to: [new Address($emailDestino, $destinatario->nombreCompleto())],
            subject: 'SAVA: notificación de falta de marcación ('.$this->notificacion->codigo_oficio.')',
        );
    }

    public function content(): Content
    {
        $this->notificacion->loadMissing(['destinatario', 'decano']);

        return new Content(
            htmlString: view('mail.notificacion-falta-colaborador', [
                'fullName' => $this->notificacion->destinatario?->nombreCompleto() ?? 'Colaborador(a)',
                'codigo' => $this->notificacion->codigo_oficio,
                'fechaFalta' => $this->notificacion->fecha_falta->format('d/m/Y'),
                'tipoMarcacion' => NotificacionFaltaMarcacion::label($this->notificacion->tipo_marcacion),
                'decanoNombre' => $this->notificacion->decano?->nombreCompleto(),
                'loginUrl' => route('login'),
                'solicitudesUrl' => route('solicitudes.wizard'),
            ])->render(),
        );
    }
}
