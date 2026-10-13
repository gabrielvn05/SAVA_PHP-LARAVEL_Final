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

    /*
    | false (recomendado): vista previa renderizando el DOCX (respeta márgenes y cuadros de firma).
    | true: convierte a PDF con LibreOffice (el PDF suele verse más compacto que en Word).
    */
    'usar_pdf_en_visor' => filter_var(env('OFICIO_USAR_PDF_EN_VISOR', false), FILTER_VALIDATE_BOOL),

];
