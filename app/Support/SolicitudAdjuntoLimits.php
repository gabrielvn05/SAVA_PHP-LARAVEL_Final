<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class SolicitudAdjuntoLimits
{
    public const MAX_FILES = 10;

    public const MAX_TOTAL_KB = 10240;

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

    public static function countNewUploads(Request $request): int
    {
        $count = 0;

        if ($request->hasFile('justificativo')) {
            $count++;
        }

        foreach ($request->file('anexos', []) as $file) {
            if ($file instanceof UploadedFile) {
                $count++;
            }
        }

        return $count;
    }

    public static function exceedsFileCount(int $existingCount, Request $request): bool
    {
        return ($existingCount + self::countNewUploads($request)) > self::MAX_FILES;
    }

    public static function totalLimitMessage(): string
    {
        return 'El peso total de los archivos adjuntos no puede superar 10 MB.';
    }

    public static function fileCountMessage(): string
    {
        return 'Puedes adjuntar hasta 10 archivos en total (incluidos los que ya están en la solicitud).';
    }
}
