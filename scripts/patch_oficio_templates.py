#!/usr/bin/env python3
"""
Genera plantillas SAVA en resources/templates/oficios/ desde los DOCX en Downloads.
Ejecutar tras actualizar los archivos «casi» de oficios.
"""
from __future__ import annotations

import re
import zipfile
from io import BytesIO
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SRC = Path(r"c:\Users\gabri\Downloads")
OUT = ROOT / "resources" / "templates" / "oficios"

FACULTAD_NOMBRE = (
    "Facultad de Ciencias de la Vida y la Tecnología (FACIVITEC)"
)

FILES = {
    "notificacion_faltas": "Oficio de informe de faltas dado por talento humano casi.docx",
    "falta_marcado": "Oficio por reconocimiento facial casi.docx",
    "calamidad_domestica": "Oficio por calamidad(1) casi.docx",
    "enfermedad": "Oficio medico casi.docx",
    "viaje": "Oficio por viaje casi.docx",
    "permiso": "Permiso de ausencia a horas o jornada laboral casi.docx",
    "justificacion": "Justificación por atraso casi.docx",
}

OUT_NAMES = {
    "notificacion_faltas": "notificacion-faltas-th.docx",
    "falta_marcado": "reconocimiento.docx",
    "calamidad_domestica": "calamidad.docx",
    "enfermedad": "medico.docx",
    "viaje": "viaje.docx",
    "permiso": "permiso.docx",
    "justificacion": "justificacion-atraso.docx",
}

LINE_REPLACEMENTS: dict[str, list[tuple[str, str]]] = {
    "notificacion_faltas": [
        (
            "Fecha de emisión:",
            "Fecha de emisión: [Fecha automática del sistema]",
        ),
    ],
    "falta_marcado": [
        ("Jornada:", "Jornada: [Jornada]"),
        ("Fecha del incidente:", "Fecha del incidente: [Fecha del incidente]"),
        (
            "Tipo de marcación omitida/fallida:",
            "Tipo de marcación omitida/fallida: [Tipo de marcación omitida/fallida]",
        ),
        ("Hora real de ingreso:", "Hora real de ingreso: [Hora real de ingreso]"),
        ("Hora real de salida:", "Hora real de salida: [Hora real de salida]"),
        ("Motivo:", "Motivo: [Motivo]"),
        (
            "Observaciones adicionales:",
            "Observaciones adicionales: [Observaciones adicionales]",
        ),
    ],
    "calamidad_domestica": [
        ("Tipo de calamidad:", "Tipo de calamidad: [Tipo de calamidad]"),
        (
            "Nombre completo del familiar afectado:",
            "Nombre completo del familiar afectado: [Nombre completo del familiar afectado]",
        ),
        (
            "Parentesco (hasta segundo grado de consanguinidad o afinidad):",
            "Parentesco (hasta segundo grado de consanguinidad o afinidad): [Parentesco]",
        ),
        ("Descripción del hecho:", "Descripción del hecho: [Descripción del hecho]"),
        ("Lugar donde ocurrió:", "Lugar donde ocurrió: [Lugar donde ocurrió]"),
        ("Fecha del hecho:", "Fecha del hecho: [Fecha del hecho]"),
    ],
    "viaje": [
        ("Tipo:", "Tipo: [Tipo de visita de campo]"),
        (
            "Nombre del lugar o institución de estudio:",
            "Nombre del lugar o institución de estudio: [Nombre del lugar o institución de estudio]",
        ),
        ("Lugar (ciudad, país):", "Lugar (ciudad, país): [Lugar (ciudad, país)]"),
        ("Rol específico (si aplica):", "Rol específico (si aplica): [Rol específico]"),
        (
            "Observaciones Adicionales:",
            "Observaciones Adicionales: [Observaciones adicionales]",
        ),
    ],
    "permiso": [
        ("Fecha del permiso:", "Fecha del permiso: [Fecha del permiso]"),
        ("Hora de inicio de falta:", "Hora de inicio de falta: [Hora de inicio de falta]"),
        ("Hora de fin de falta:", "Hora de fin de falta: [Hora de fin de falta]"),
        ("Motivo:", "Motivo: [Motivo]"),
        (
            "Observaciones adicionales:",
            "Observaciones adicionales: [Observaciones adicionales]",
        ),
    ],
    "justificacion": [
        ("Fecha de atraso:", "Fecha de atraso: [Fecha de atraso]"),
        (
            "Hora establecida de llegada:",
            "Hora establecida de llegada: [Hora establecida de llegada]",
        ),
        (
            "Hora de llegada de atraso:",
            "Hora de llegada de atraso: [Hora de llegada de atraso]",
        ),
        ("Motivo:", "Motivo: [Motivo]"),
        (
            "Observaciones adicionales:",
            "Observaciones adicionales: [Observaciones adicionales]",
        ),
    ],
}

FECHAS_VIAJE_PARAGRAPH = (
    '<w:p w14:paraId="FECHAS001" w14:textId="77777777" w:rsidR="00E61206" w:rsidRPr="00EC5A55" '
    'w:rsidRDefault="00E61206" w:rsidP="00EC5A55">'
    '<w:pPr><w:spacing w:line="240" w:lineRule="auto" w:after="60"/>'
    '<w:jc w:val="both"/>'
    '<w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:cs="Times New Roman"/>'
    '<w:lang w:val="es-EC"/></w:rPr></w:pPr>'
    '<w:r w:rsidRPr="00EC5A55"><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" '
    'w:cs="Times New Roman"/><w:lang w:val="es-EC"/></w:rPr>'
    "<w:t>Fechas del evento o actividad: [Fechas del evento o actividad]</w:t></w:r></w:p>"
)


def unwrap_sdt(xml: str) -> str:
    prev = None
    while prev != xml:
        prev = xml
        xml = re.sub(
            r"<w:sdt\b[^>]*>.*?<w:sdtContent[^>]*>(.*?)</w:sdtContent>.*?</w:sdt>",
            r"\1",
            xml,
            count=0,
            flags=re.DOTALL,
        )
    return xml


def fix_split_bracket_placeholders(xml: str) -> str:
    pattern = r"\[(?:[^\[\]]|<[^>]+>)*[^\[\]]*\]"
    prev = None
    while prev != xml:
        prev = xml

        def repl(match: re.Match[str]) -> str:
            raw = match.group(0)
            if "<" not in raw:
                return raw
            clean = re.sub(r"<[^>]+>", "", raw)
            clean = re.sub(r"\s+", " ", clean).strip()
            return clean

        xml = re.sub(pattern, repl, xml)
    return xml


def xml_friendly_replace(xml: str, old: str, new: str, count: int = 1) -> str:
    pattern = "(?:<[^>]+>)*".join(re.escape(char) for char in old)
    result = re.sub(pattern, new, xml, count=count)
    return result if isinstance(result, str) else xml


def normalize_oficio_codigo(xml: str) -> str:
    patterns = [
        r"OFICIO N° FACIVITEC-MMAAAA-NNNN",
        r"OFICIO N° FACIVITEC-032026-0005",
        r"<w:t[^>]*>OFICIO N° F</w:t></w:r><w:r[^>]*>.*?<w:t>ACIVITEC</w:t></w:r><w:r[^>]*>.*?<w:t>-MMAAAA-NNNN</w:t>",
        r"<w:t[^>]*>OFICIO N° F</w:t></w:r><w:r[^>]*>.*?<w:t>ACIVITEC</w:t></w:r><w:r[^>]*>.*?<w:t>-032026-0005</w:t>",
    ]
    for pattern in patterns:
        xml = re.sub(
            pattern,
            "OFICIO N° [Código del oficio]",
            xml,
            count=1,
            flags=re.DOTALL | re.IGNORECASE,
        )

    codigo_entre_tags = (
        r"(?:Oficio|OFICIO)(?:<[^>]+>|\s)*"
        r"No\.?(?:<[^>]+>|\s)*"
        r"FACIVITEC(?:<[^>]+>|\s|-)*"
        r"(?:ADM(?:<[^>]+>|\s|-)*)?"
        r"[A-Z0-9]+(?:<[^>]+>|\s|-)*"
        r"\d+"
    )
    xml = re.sub(
        codigo_entre_tags,
        "OFICIO N° [Código del oficio]",
        xml,
        count=1,
        flags=re.DOTALL | re.IGNORECASE,
    )

    notif_codigo = (
        r"NOTIFICACI(?:Ó|O)N(?:<[^>]+>|\s)*"
        r"No\.?(?:<[^>]+>|\s)*"
        r"FACIVITEC(?:<[^>]+>|\s|-)*"
        r"(?:ADM(?:<[^>]+>|\s|-)*)?"
        r"\d+"
    )
    xml = re.sub(
        notif_codigo,
        "NOTIFICACIÓN No. [Código del oficio]",
        xml,
        count=1,
        flags=re.DOTALL | re.IGNORECASE,
    )

    return xml


def normalize_facultad_y_rol(xml: str) -> str:
    for nombre in (
        "Facultad de Ciencias de la Vida y Tecnologías",
        "Facultad de Ciencias de la Vida y la Tecnología (FACIVITEC)",
    ):
        xml = xml.replace(nombre, "[facultad]")
    xml = re.sub(
        r",\s*ADMINISTRATIVO\s+de la carrera",
        ", [Rol del personal] de la carrera",
        xml,
        flags=re.IGNORECASE,
    )
    xml = re.sub(
        r"\nADMINISTRATIVO\n",
        "\n[Rol del personal]\n",
        xml,
    )
    xml = xml.replace(">ADMINISTRATIVO<", ">[Rol del personal]<")
    return xml


def normalize_viaje_fechas(xml: str) -> str:
    replacements = [
        "Fechas del evento o actividad: desde el [fecha inicio] hasta el [fecha fin] de [mes] de [año].",
        "Fechas del evento o actividad: desde el [fecha inicio] hasta el [fecha fin] de [mes] de [a\u00f1o].",
        "Fechas del evento o actividad: desde el ____ hasta el ____ de ______ de _______",
    ]
    for text in replacements:
        if text in xml:
            xml = xml.replace(
                text,
                "Fechas del evento o actividad: [Fechas del evento o actividad]",
            )

    xml = re.sub(
        r"<w:p\b[^>]*>(?:(?!</w:p>).)*Fechas del evento o actividad:(?:(?!</w:p>).)*</w:p>",
        FECHAS_VIAJE_PARAGRAPH,
        xml,
        count=1,
        flags=re.DOTALL,
    )
    return xml


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


def patch_xml(xml: str, key: str) -> str:
    xml = unwrap_sdt(xml)
    xml = fix_split_bracket_placeholders(xml)
    xml = normalize_oficio_codigo(xml)
    xml = normalize_facultad_y_rol(xml)

    if key == "viaje":
        xml = xml.replace(
            "Justificación de faltas por viaje académico",
            "Justificación de faltas por visita técnica de campo",
        )
        xml = normalize_viaje_fechas(xml)

    if key == "notificacion_faltas":
        xml = xml.replace("Decano (s)", "[Título del firmante]")
        xml = xml_friendly_replace(xml, "Correo:", "Correo Institucional: [Correo]", count=1)
        for cargo_placeholder in (
            "[Cargo: Docente / Administrativo / Código de trabajo ]",
            "[Cargo: Docente / Administrativo / Código de trabajo]",
        ):
            xml = xml.replace(cargo_placeholder, "[Cargo del colaborador]")
        xml = xml.replace(
            "[Carrera / Departamento / Unidad]",
            "[Carrera o unidad]",
        )
        xml = xml.replace("[Fecha]", "[Fecha de la falta]")
        xml = re.sub(
            r"\[entrada / salida / entrada y salida\]",
            "[Tipo de marcación faltante]",
            xml,
        )

    for old, new in LINE_REPLACEMENTS.get(key, []):
        xml = xml_friendly_replace(xml, old, new)

    xml = xml.replace("Ángel Cristian Mera Macias", "[Nombre del decano]")
    xml = xml.replace("Alex Andrés Santamaria Philco, Ph.D.", "[Nombre del decano]")
    xml = normalize_firma_table_jc(xml)

    return xml


def patch_docx(src: Path, dest: Path, key: str) -> None:
    raw = src.read_bytes()
    out_buf = BytesIO()
    with zipfile.ZipFile(BytesIO(raw), "r") as zin, zipfile.ZipFile(out_buf, "w", zipfile.ZIP_DEFLATED) as zout:
        for item in zin.infolist():
            data = zin.read(item.filename)
            if item.filename.endswith(".xml"):
                text = patch_xml(data.decode("utf-8"), key)
                data = text.encode("utf-8")
            zout.writestr(item, data)
    dest.parent.mkdir(parents=True, exist_ok=True)
    dest.write_bytes(out_buf.getvalue())


def validate_docx(path: Path, key: str) -> list[str]:
    xml = zipfile.ZipFile(path).read("word/document.xml").decode("utf-8")
    errors: list[str] = []
    required = {
        "falta_marcado": [
            "[Código del oficio]",
            "[Fecha del incidente]",
            "[Jornada]",
            "[Correo]",
        ],
        "calamidad_domestica": [
            "[Tipo de calamidad]",
            "[Fecha de la falta]",
            "[Fecha de retorno a actividades normales]",
            "[Correo]",
        ],
        "enfermedad": [
            "[Carrera]",
            "[Diagnóstico principal]",
            "[Fecha y hora completa automática del sistema]",
        ],
        "viaje": [
            "[Fechas del evento o actividad]",
            "[Tipo de visita de campo]",
            "visita técnica de campo",
        ],
        "permiso": [
            "[Fecha del permiso]",
            "[Hora de inicio de falta]",
            "[Motivo]",
        ],
        "justificacion": [
            "[Código del oficio]",
            "[Fecha de atraso]",
            "[Hora de llegada de atraso]",
        ],
        "notificacion_faltas": [
            "[Código del oficio]",
            "[Nombre completo del colaborador]",
            "[Cargo del colaborador]",
            "[Carrera o unidad]",
            "[Fecha de la falta]",
            "[Tipo de marcación faltante]",
            "[Nombre del decano]",
            "[Correo]",
        ],
    }
    for token in required.get(key, []):
        if token not in xml:
            errors.append(f"Falta {token}")
    if "____" in xml:
        errors.append("Contiene guiones bajos sin reemplazar")
    if "[fecha inicio]" in xml or "[fecha fin]" in xml:
        errors.append("Placeholders viejos de fecha de evento")
    if re.search(r"\[<\/w:t>", xml):
        errors.append("Placeholder XML partido")
    return errors


def main() -> None:
    for key, filename in FILES.items():
        src = SRC / filename
        if not src.exists():
            raise FileNotFoundError(f"No se encontró {src}")
        dest = OUT / OUT_NAMES[key]
        patch_docx(src, dest, key)
        errors = validate_docx(dest, key)
        if errors:
            raise RuntimeError(f"{dest.name}: " + "; ".join(errors))
        print("OK", dest)


if __name__ == "__main__":
    main()
