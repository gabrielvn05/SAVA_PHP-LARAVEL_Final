#!/usr/bin/env python3
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / "public" / "branding" / "logo-pagina-source.png"

OUT = ROOT / "public" / "branding"


def main() -> None:
    img = Image.open(SRC).convert("RGBA")
    pixels = img.load()
    w, h = img.size
    for y in range(h):
        for x in range(w):
            r, g, b, a = pixels[x, y]
            if r < 35 and g < 35 and b < 35:
                pixels[x, y] = (r, g, b, 0)

    bbox = img.getbbox()
    content = img.crop(bbox) if bbox else img
    side = max(content.size)
    square = Image.new("RGBA", (side, side), (0, 0, 0, 0))
    square.paste(content, ((side - content.width) // 2, (side - content.height) // 2))

    OUT.mkdir(parents=True, exist_ok=True)
    for dim, name in ((192, "icon-192.png"), (512, "icon-512.png"), (32, "favicon-32.png")):
        square.resize((dim, dim), Image.Resampling.LANCZOS).save(OUT / name)

    square.resize((180, 180), Image.Resampling.LANCZOS).save(OUT / "apple-touch-icon.png")

    favicon = square.resize((32, 32), Image.Resampling.LANCZOS)
    favicon.save(OUT.parent / "favicon.ico", format="ICO", sizes=[(16, 16), (32, 32)])
    print(f"Generated icons from {SRC} ({w}x{h})")


if __name__ == "__main__":
    main()
