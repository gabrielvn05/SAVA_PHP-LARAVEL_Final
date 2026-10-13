<?php

namespace App\Support;

class ViteManifest
{
    public static function moduleUrl(string $entry): ?string
    {
        $manifestPath = public_path('build/manifest.json');
        if (! is_readable($manifestPath)) {
            return null;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($manifest)) {
            return null;
        }

        $file = $manifest[$entry]['file'] ?? null;
        if (! is_string($file) || $file === '') {
            return null;
        }

        return asset('build/'.$file);
    }
}
