#!/usr/bin/env python3
import re
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
for name in ["viaje.docx", "reconocimiento.docx"]:
    p = ROOT / "resources/templates/oficios" / name
    xml = zipfile.ZipFile(p).read("word/document.xml").decode("utf-8")
    print("===", name, "===")
    for m in re.finditer(r"Correo Institucional", xml):
        start = max(0, m.start() - 400)
        end = min(len(xml), m.end() + 600)
        chunk = xml[start:end]
        print(chunk)
        print("---")
    for label in ["Generado", "Fecha de generación", "[Fecha automática"]:
        print(label, "->", label in xml)
