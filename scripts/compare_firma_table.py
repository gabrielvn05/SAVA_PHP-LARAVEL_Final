import re
import zipfile
from pathlib import Path


def extract_firma_table(path: Path) -> str:
    xml = zipfile.ZipFile(path).read("word/document.xml").decode("utf-8")
    m = re.search(r"<w:tbl>.*?Aprobado por.*?</w:tbl>", xml, re.DOTALL)
    return m.group(0) if m else ""


orig = extract_firma_table(Path(r"c:\Users\gabri\Downloads\Oficio por viaje Sava.docx"))
out = extract_firma_table(Path(r"c:\SAVA_PHP+LARAVEL\resources\templates\oficios\viaje.docx"))

# Normalizar placeholders para comparar estructura.
def norm(s: str) -> str:
    s = re.sub(r"\[Código del oficio\]", "OFICIO", s)
    s = re.sub(r"\[Fechas del evento o actividad\]", "FECHAS", s)
    s = re.sub(r"\[[^\]]+\]", "PH", s)
    return s


print("orig len", len(orig), "out len", len(out))
print("tables equal after norm", norm(orig) == norm(out))
if norm(orig) != norm(out):
    for i, (a, b) in enumerate(zip(norm(orig), norm(out))):
        if a != b:
            print("first diff at", i, orig[max(0, i - 80) : i + 80])
            break
