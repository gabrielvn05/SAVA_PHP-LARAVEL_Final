<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class SolicitudAdjuntoLimits
{
    public const MAX_TOTAL_KB = 20480;

    public static function totalUploadSizeKb(Request $request): int
    {
        $bytes = 0;

        if ($request->hasFile('justificativo')) {
            $bytes += (int) $request->file('justificativo')->getSize();
        }

        foreach ($request->file('anexos', []) as $file) {
            if ($file instanceof UploadedFile) {
                $bytes += (int) $file->getSize();
            }
        }

        return (int) ceil($bytes / 1024);
    }

    public static function exceedsTotalLimit(Request $request): bool
    {
        return self::totalUploadSizeKb($request) > self::MAX_TOTAL_KB;
    }

    public static function totalLimitMessage(): string
    {
        return 'El peso total de los archivos adjuntos no puede superar 20 MB.';
    }
}
