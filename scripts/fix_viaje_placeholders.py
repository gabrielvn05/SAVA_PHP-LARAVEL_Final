#!/usr/bin/env python3
"""Corrige placeholders partidos en viaje.docx (fechas evento, firma, etc.)."""
from __future__ import annotations

import re
import zipfile
from io import BytesIO
from pathlib import Path

DOCX = Path(__file__).resolve().parents[1] / "resources" / "templates" / "oficios" / "viaje.docx"

FECHAS_PARAGRAPH = (
    '<w:p w14:paraId="FECHAS001" w14:textId="77777777" w:rsidR="00E61206" w:rsidRPr="00EC5A55" '
    'w:rsidRDefault="00E61206" w:rsidP="00EC5A55">'
    "<w:pPr><w:spacing w:line=\"240\" w:lineRule=\"auto\" w:after=\"80\"/>"
    '<w:jc w:val="both"/>'
    '<w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:cs="Times New Roman"/>'
    '<w:lang w:val="es-EC"/></w:rPr></w:pPr>'
    '<w:r w:rsidRPr="00EC5A55"><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" '
    'w:cs="Times New Roman"/><w:lang w:val="es-EC"/></w:rPr>'
    "<w:t>Fechas del evento o actividad: [Fechas del evento o actividad]</w:t></w:r></w:p>"
)


def fix_xml(xml: str) -> str:
    # Reemplazar párrafo de fechas (partido o con guiones bajos).
    xml = re.sub(
        r"<w:p\b[^>]*>(?:(?!</w:p>).)*Fechas del evento o actividad:(?:(?!</w:p>).)*</w:p>",
        FECHAS_PARAGRAPH,
        xml,
        count=1,
        flags=re.DOTALL,
    )

    # Asegurar placeholders de pie de tabla si faltan.
    if "[Fecha automática del sistema]" not in xml and "[Fecha autom" in xml:
        xml = re.sub(
            r"\[Fecha autom[^\]]*\]",
            "[Fecha automática del sistema]",
            xml,
        )

    return xml


def main() -> None:
    raw = DOCX.read_bytes()
    out = BytesIO()
    with zipfile.ZipFile(BytesIO(raw), "r") as zin, zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as zout:
        for item in zin.infolist():
            data = zin.read(item.filename)
            if item.filename == "word/document.xml":
                data = fix_xml(data.decode("utf-8")).encode("utf-8")
            zout.writestr(item, data)
    DOCX.write_bytes(out.getvalue())
    print("Fixed", DOCX)


if __name__ == "__main__":
    main()
