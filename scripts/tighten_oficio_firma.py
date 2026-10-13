#!/usr/bin/env python3
"""
Ajusta la tabla de firmas: espacio para rubricar, sin desbordar a 2 páginas.
"""
from __future__ import annotations

import re
import zipfile
from io import BytesIO
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
TEMPLATES = ROOT / "resources" / "templates" / "oficios"

EMPTY_P = (
    '<w:p w14:paraId="SIGSP01" w14:textId="77777777" w:rsidR="004E1301" w:rsidRPr="00EC5A55" '
    'w:rsidRDefault="004E1301" w:rsidP="00EC5A55">'
    '<w:pPr><w:spacing w:before="0" w:after="40" w:line="240" w:lineRule="auto"/>'
    '<w:jc w:val="both"/>'
    '<w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:cs="Times New Roman"/>'
    '<w:lang w:val="es-EC"/></w:rPr></w:pPr></w:p>'
)

SIGNATURE_BLOCK = EMPTY_P * 3


def add_signature_space(tbl: str) -> str:
    # Espacio bajo "Aprobado por:" (celda derecha, fila 1).
    tbl = re.sub(
        r"(<w:t xml:space=\"preserve\">Aprobado por:\s*</w:t></w:r></w:p>)",
        r"\1" + SIGNATURE_BLOCK,
        tbl,
        count=1,
    )
    tbl = re.sub(
        r"(<w:t>Aprobado por:\s*</w:t></w:r></w:p>)",
        r"\1" + SIGNATURE_BLOCK,
        tbl,
        count=1,
    )

    # Espacio para firma del docente (celda izquierda, fila 1): tras abrir tc de la primera fila.
    first_tr = re.search(r"(<w:tr\b.*?</w:tr>)", tbl, re.DOTALL)
    if first_tr:
        row = first_tr.group(1)
        new_row = re.sub(
            r"(<w:tc><w:tcPr>.*?</w:tcPr>)",
            r"\1" + SIGNATURE_BLOCK,
            row,
            count=1,
            flags=re.DOTALL,
        )
        if new_row != row:
            tbl = tbl.replace(row, new_row, 1)

    # Espacio antes de "Decano de la facultad" (celda derecha, fila 2).
    tbl = re.sub(
        r"(<w:tc><w:tcPr><w:tcW w:w=\"4414\" w:type=\"dxa\"/></w:tcPr>)"
        r"(?=<w:p[^>]*>.*?Decano de la facultad)",
        r"\1" + (EMPTY_P * 2),
        tbl,
        count=1,
        flags=re.DOTALL,
    )

    return tbl


def tighten_xml(xml: str) -> str:
    xml = xml.replace("Fecha de generación del documento", "Generado el")
    xml = xml.replace("Fecha de generaci\u00f3n del documento", "Generado el")

    marker = "Atentamente,"
    pos = xml.find(marker)
    if pos < 0:
        return xml

    tail = xml[pos:]
    tbl_start = tail.find("<w:tbl>")
    if tbl_start < 0:
        return xml

    head = xml[: pos + tbl_start]
    tbl_and_rest = tail[tbl_start:]
    tbl_end = tbl_and_rest.find("</w:tbl>")
    if tbl_end < 0:
        return xml

    tbl = tbl_and_rest[: tbl_end + len("</w:tbl>")]
    rest = tbl_and_rest[tbl_end + len("</w:tbl>") :]

    # Altura mínima de la fila de encabezado de firmas (sin aplastar).
    tbl = re.sub(
        r"(<w:tr\b[^>]*>.*?Aprobado por.*?</w:tr>)",
        lambda m: re.sub(r'w:val="\d+"', 'w:val="980"', m.group(1), count=1),
        tbl,
        count=1,
        flags=re.DOTALL,
    )

    tbl = add_signature_space(tbl)

    if "<w:cantSplit/>" not in tbl[:3000]:
        idx = tbl.find("<w:tr ")
        if idx >= 0:
            close = tbl.find(">", idx)
            if close >= 0:
                tbl = tbl[: close + 1] + "<w:trPr><w:cantSplit/></w:trPr>" + tbl[close + 1 :]

    head = re.sub(
        r'(<w:spacing w:after=")\d+(")',
        r"\g<1>80\2",
        head,
        count=1,
    )

    return head + tbl + rest


def patch_docx(path: Path) -> None:
    raw = path.read_bytes()
    out = BytesIO()
    with zipfile.ZipFile(BytesIO(raw), "r") as zin, zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as zout:
        for item in zin.infolist():
            data = zin.read(item.filename)
            if item.filename == "word/document.xml":
                text = tighten_xml(data.decode("utf-8"))
                data = text.encode("utf-8")
            zout.writestr(item, data)
    path.write_bytes(out.getvalue())
    print("OK", path.name)


def main() -> None:
    for docx in sorted(TEMPLATES.glob("*.docx")):
        patch_docx(docx)


if __name__ == "__main__":
    main()
