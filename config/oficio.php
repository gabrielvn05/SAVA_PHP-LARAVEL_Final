<?php

return [

    /*
    | LibreOffice (soffice) para convertir el oficio DOCX a PDF en la vista previa.
    | En Windows suele estar en Program Files; en Linux, "soffice" en PATH.
    */
    'libreoffice_path' => env('OFICIO_LIBREOFFICE_PATH', PHP_OS_FAMILY === 'Windows'
        ? 'C:\\Program Files\\LibreOffice\\program\\soffice.exe'
        : 'soffice'),

    'pdf_cache_minutes' => (int) env('OFICIO_PDF_CACHE_MINUTES', 120),

    'libreoffice_timeout_seconds' => (int) env('OFICIO_LIBREOFFICE_TIMEOUT', 45),

    /*
    | false (recomendado): vista previa renderizando el DOCX (respeta márgenes y cuadros de firma).
    | true: convierte a PDF con LibreOffice (el PDF suele verse más compacto que en Word).
    */
    'usar_pdf_en_visor' => filter_var(env('OFICIO_USAR_PDF_EN_VISOR', false), FILTER_VALIDATE_BOOL),

    /*
    | Texto pequeño junto al QR en la firma .p12 (estilo sello electrónico).
    */
    'firma_electronica_aviso' => env('OFICIO_FIRMA_ELECTRONICA_AVISO', 'Validar en SAVA ULEAM.'),

    /*
    | Si se define, el QR apunta a esta URL + id de solicitud. Si está vacío, el QR lleva datos en texto.
    */
    'firma_electronica_qr_url' => env('OFICIO_FIRMA_ELECTRONICA_QR_URL'),

];
