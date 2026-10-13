<?php

namespace App\Support;

class AdjuntoPreview
{
    public static function kind(?string $nombre): string
    {
        $ext = strtolower(pathinfo((string) $nombre, PATHINFO_EXTENSION));

        if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
            return 'image';
        }

        if ($ext === 'pdf') {
            return 'pdf';
        }

        return 'other';
    }
}
