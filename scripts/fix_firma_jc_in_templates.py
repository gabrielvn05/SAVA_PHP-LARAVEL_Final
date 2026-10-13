#!/usr/bin/env python3
"""Aplica alineación izquierda en tabla de firma de las plantillas ya generadas."""
from __future__ import annotations

import re
import zipfile
from io import BytesIO
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "resources" / "templates" / "oficios"


def normalize_firma_table_jc(xml: str) -> str:
    m = re.search(
        r"(<w:tbl\b[^>]*>(?:(?!</w:tbl>).)*Aprobado por:(?:(?!</w:tbl>).)*</w:tbl>)",
        xml,
        flags=re.DOTALL,
    )
    if not m:
        return xml
    table = m.group(1)
    fixed = table.replace('<w:jc w:val="both"/>', '<w:jc w:val="left"/>')
    fixed = fixed.replace('<w:jc w:val="distribute"/>', '<w:jc w:val="left"/>')
    return xml.replace(table, fixed, 1)


def patch_docx(path: Path) -> bool:
    raw = path.read_bytes()
    out_buf = BytesIO()
    changed = False
    with zipfile.ZipFile(BytesIO(raw), "r") as zin, zipfile.ZipFile(out_buf, "w", zipfile.ZIP_DEFLATED) as zout:
        for item in zin.infolist():
            data = zin.read(item.filename)
            if item.filename.endswith(".xml"):
                text = data.decode("utf-8")
                new_text = normalize_firma_table_jc(text)
                if new_text != text:
                    changed = True
                    data = new_text.encode("utf-8")
            zout.writestr(item, data)
    if changed:
        path.write_bytes(out_buf.getvalue())
    return changed


def main() -> None:
    for path in sorted(OUT.glob("*.docx")):
        print(path.name, "updated" if patch_docx(path) else "ok")


if __name__ == "__main__":
    main()
