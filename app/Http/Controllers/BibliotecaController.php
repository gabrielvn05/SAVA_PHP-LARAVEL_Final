<?php

namespace App\Http\Controllers;

use App\Enums\AppRole;
use App\Models\BibliotecaDocumento;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BibliotecaController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(): View
    {
        return view('biblioteca.index', [
            'documentos' => BibliotecaDocumento::query()
                ->orderByDesc('fecha_documento')
                ->orderBy('titulo')
                ->get(),
            'puedeEditar' => $this->puedeEditar(auth()->user()),
        ]);
    }

    public function ver(BibliotecaDocumento $documento): BinaryFileResponse
    {
        abort_unless($documento->esPdf(), 404);

        return $this->responder($documento, 'inline');
    }

    public function descargar(BibliotecaDocumento $documento): BinaryFileResponse
    {
        return $this->responder($documento, 'attachment');
    }

    public function store(Request $request): RedirectResponse
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof User && $this->puedeEditar($usuario), 403);

        $validated = $request->validate([
            'titulo' => ['nullable', 'string', 'max:180'],
            'archivo' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:20480'],
        ], [
            'archivo.required' => 'Selecciona el archivo que deseas subir.',
            'archivo.mimes' => 'Solo se permiten archivos PDF o Word.',
            'archivo.max' => 'El archivo no puede superar 20 MB.',
        ]);

        $archivo = $request->file('archivo');
        $extension = strtolower($archivo->getClientOriginalExtension() ?: $archivo->extension());
        $nombreOriginal = $archivo->getClientOriginalName();
        $titulo = trim((string) ($validated['titulo'] ?? ''));
        if ($titulo === '') {
            $titulo = pathinfo($nombreOriginal, PATHINFO_FILENAME);
        }

        $ruta = 'biblioteca/'.Str::uuid().'.'.$extension;
        Storage::disk('local')->put($ruta, $archivo->get());

        $documento = BibliotecaDocumento::query()->create([
            'titulo' => $titulo,
            'autor_nombre' => mb_strtoupper($usuario->nombreCompleto(), 'UTF-8'),
            'fecha_documento' => now()->toDateString(),
            'nombre_archivo' => $nombreOriginal,
            'ruta' => $ruta,
            'disco' => 'local',
            'mime' => $archivo->getMimeType() ?: 'application/octet-stream',
            'tamano' => $archivo->getSize() ?: 0,
            'subido_por' => $usuario->id,
        ]);

        $this->audit->log('INSERT', $documento);

        return redirect()->route('biblioteca.index')->with('success', 'Documento publicado en la biblioteca.');
    }

    public function destroy(Request $request, BibliotecaDocumento $documento): RedirectResponse
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof User && $this->puedeEditar($usuario), 403);

        $anterior = $documento->toArray();
        $documento->eliminarArchivo();
        $documento->delete();
        $this->audit->log('DELETE', $documento, $anterior);

        return redirect()->route('biblioteca.index')->with('success', 'Documento quitado de la biblioteca.');
    }

    private function puedeEditar(?User $usuario): bool
    {
        return $usuario?->rol === AppRole::Secretaria;
    }

    private function responder(BibliotecaDocumento $documento, string $disposition): BinaryFileResponse
    {
        $path = $documento->rutaAbsoluta();
        abort_unless(is_readable($path), 404);

        return response()->download($path, $documento->nombre_archivo, [
            'Content-Type' => $documento->mime,
        ], $disposition);
    }
}
