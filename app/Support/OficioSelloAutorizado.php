<?php

namespace App\Support;

class OficioSelloAutorizado
{
    public const PLACEHOLDER = 'Sello de APROBADO O RECHAZADO CON FIRMA DEL DECANO';

    public const MEDIA_PATH = 'word/media/sello-autorizado.jpg';

    public static function assetPath(): string
    {
        return resource_path('templates/oficio-media/sello-autorizado.jpg');
    }

    public static function puedeAplicar(): bool
    {
        return is_readable(self::assetPath());
    }

    public static function aplicarDocumento(string $documentXml, string $relId): string
    {
        if (! str_contains($documentXml, self::PLACEHOLDER)) {
            return $documentXml;
        }

        $patternAlternate = '/<mc:AlternateContent>\s*<mc:Choice\b(?:(?!<\/mc:AlternateContent>).)*'
            .preg_quote(self::PLACEHOLDER, '/')
            .'(?:(?!<\/mc:AlternateContent>).)*<\/mc:AlternateContent>/su';

        $result = preg_replace_callback(
            $patternAlternate,
            static fn (array $matches): string => self::drawingConImagen($matches[0], $relId),
            $documentXml,
            1,
        );

        if (! is_string($result)) {
            $result = $documentXml;
        }

        if (str_contains($result, self::PLACEHOLDER)) {
            $patternDrawing = '/<w:drawing>\s*<wp:anchor(?:(?!<\/w:drawing>).)*'
                .preg_quote(self::PLACEHOLDER, '/')
                .'(?:(?!<\/w:drawing>).)*<\/w:drawing>/su';

            $fallback = preg_replace_callback(
                $patternDrawing,
                static fn (array $matches): string => self::drawingConImagen($matches[0], $relId),
                $result,
                1,
            );
            $result = is_string($fallback) ? $fallback : $result;
        }

        return str_replace(self::PLACEHOLDER, '', $result);
    }

    public static function agregarRelacionImagen(string $relsXml, string $relId): string
    {
        if (str_contains($relsXml, 'sello-autorizado.jpg')) {
            return $relsXml;
        }

        $relationship = '<Relationship Id="'.$relId.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/sello-autorizado.jpg"/>';

        return str_replace('</Relationships>', $relationship.'</Relationships>', $relsXml);
    }

    public static function agregarContentTypeJpeg(string $contentTypesXml): string
    {
        if (str_contains($contentTypesXml, 'Extension="jpg"')) {
            return $contentTypesXml;
        }

        return str_replace(
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">',
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="jpg" ContentType="image/jpeg"/>',
            $contentTypesXml,
        );
    }

    public static function siguienteRelId(string $relsXml): string
    {
        if (! preg_match_all('/\bId="rId(\d+)"/', $relsXml, $matches)) {
            return 'rId50';
        }

        $max = max(array_map(intval(...), $matches[1]));

        return 'rId'.($max + 1);
    }

    private static function drawingConImagen(string $original, string $relId): string
    {
        preg_match('/<wp:anchor\b([^>]*)>/', $original, $anchorAttrs);
        preg_match('/<wp:posOffset>(\d+)<\/wp:posOffset>/', $original, $posH);
        preg_match_all('/<wp:posOffset>(\d+)<\/wp:posOffset>/', $original, $posOffsets);
        preg_match('/<wp:extent cx="(\d+)" cy="(\d+)"/', $original, $extent);

        $posHVal = $posOffsets[1][0] ?? '3706327';
        $posVVal = $posOffsets[1][1] ?? '13359';
        $cx = $extent[1] ?? '1454150';
        $cy = $extent[2] ?? '984250';
        $anchorExtra = $anchorAttrs[1] ?? ' distT="0" distB="0" distL="114300" distR="114300" simplePos="0" relativeHeight="251659264" behindDoc="0" locked="0" layoutInCell="1" allowOverlap="1"';

        return '<w:drawing>'
            .'<wp:anchor'.$anchorExtra.'>'
            .'<wp:simplePos x="0" y="0"/>'
            .'<wp:positionH relativeFrom="column"><wp:posOffset>'.$posHVal.'</wp:posOffset></wp:positionH>'
            .'<wp:positionV relativeFrom="paragraph"><wp:posOffset>'.$posVVal.'</wp:posOffset></wp:positionV>'
            .'<wp:extent cx="'.$cx.'" cy="'.$cy.'"/>'
            .'<wp:effectExtent l="0" t="0" r="0" b="0"/>'
            .'<wp:wrapNone/>'
            .'<wp:docPr id="991001" name="Sello AUTORIZADO"/>'
            .'<wp:cNvGraphicFramePr>'
            .'<a:graphicFrameLocks xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" noChangeAspect="1"/>'
            .'</wp:cNvGraphicFramePr>'
            .'<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
            .'<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:nvPicPr><pic:cNvPr id="0" name="Sello AUTORIZADO FACIVITEC"/><pic:cNvPicPr/></pic:nvPicPr>'
            .'<pic:blipFill><a:blip r:embed="'.$relId.'"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            .'<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm>'
            .'<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
            .'</pic:pic></a:graphicData></a:graphic>'
            .'</wp:anchor></w:drawing>';
    }
}
