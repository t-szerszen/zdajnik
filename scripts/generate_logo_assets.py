#!/usr/bin/env python3
"""
Generuje zasoby logo dla Zdajnika na podstawie bazowej i miniaturowej wersji logo.

Outputs:
- public/images/company/favicon.ico
- public/images/company/favicon-16x16.png
- public/images/company/favicon-32x32.png
- public/images/company/favicon-48x48.png
- public/images/company/apple-touch-icon.png
- public/images/company/android-chrome-192x192.png
- public/images/company/android-chrome-512x512.png
- public/images/company/zdajnik-og-1200x630.png
- public/images/company/zdajnik-twitter-1200x600.png
- public/images/company/zdajnik-logo-512.png
"""

from pathlib import Path

import cairosvg
from PIL import Image, ImageOps


ROOT = Path(__file__).resolve().parents[1]
COMPANY_DIR = ROOT / "public" / "images" / "company"

SMALL_SVG = COMPANY_DIR / "zdajnik-small.svg"
FULL_SVG = COMPANY_DIR / "zdajnik.svg"


def svg_to_png(svg_path: Path, out_path: Path, width: int, height: int) -> None:
    out_path.parent.mkdir(parents=True, exist_ok=True)
    cairosvg.svg2png(url=str(svg_path), write_to=str(out_path), output_width=width, output_height=height)


def make_square_icon(svg_path: Path, out_path: Path, size: int, inset_ratio: float = 0.14) -> None:
    temp_path = COMPANY_DIR / f"_tmp_{size}.png"
    svg_to_png(svg_path, temp_path, size, size)

    icon = Image.open(temp_path).convert("RGBA")
    canvas = Image.new("RGBA", (size, size), (255, 255, 255, 0))

    inset = int(size * inset_ratio)
    target = size - 2 * inset
    icon = ImageOps.contain(icon, (target, target), method=Image.Resampling.LANCZOS)

    x = (size - icon.width) // 2
    y = (size - icon.height) // 2
    canvas.alpha_composite(icon, (x, y))
    canvas.save(out_path, format="PNG")
    temp_path.unlink(missing_ok=True)


def build_social_image(
    svg_path: Path,
    out_path: Path,
    width: int,
    height: int,
    bg=(255, 255, 255, 255),
    horizontal_padding_ratio: float = 0.12,
    vertical_padding_ratio: float = 0.2,
) -> None:
    temp = COMPANY_DIR / f"_tmp_social_{width}x{height}.png"
    svg_to_png(svg_path, temp, width, height)
    logo = Image.open(temp).convert("RGBA")

    content_w = int(width * (1 - horizontal_padding_ratio * 2))
    content_h = int(height * (1 - vertical_padding_ratio * 2))
    logo = ImageOps.contain(logo, (content_w, content_h), method=Image.Resampling.LANCZOS)

    canvas = Image.new("RGBA", (width, height), bg)
    x = (width - logo.width) // 2
    y = (height - logo.height) // 2
    canvas.alpha_composite(logo, (x, y))
    canvas.save(out_path, format="PNG")
    temp.unlink(missing_ok=True)


def create_favicon_ico(source_png: Path, ico_path: Path) -> None:
    base = Image.open(source_png).convert("RGBA")
    sizes = [(16, 16), (32, 32), (48, 48)]
    base.save(ico_path, format="ICO", sizes=sizes)


def main() -> None:
    COMPANY_DIR.mkdir(parents=True, exist_ok=True)

    if not SMALL_SVG.exists():
        raise FileNotFoundError(f"Missing required file: {SMALL_SVG}")
    if not FULL_SVG.exists():
        raise FileNotFoundError(f"Missing required file: {FULL_SVG}")

    make_square_icon(SMALL_SVG, COMPANY_DIR / "favicon-16x16.png", 16)
    make_square_icon(SMALL_SVG, COMPANY_DIR / "favicon-32x32.png", 32)
    make_square_icon(SMALL_SVG, COMPANY_DIR / "favicon-48x48.png", 48)
    make_square_icon(SMALL_SVG, COMPANY_DIR / "apple-touch-icon.png", 180)
    make_square_icon(SMALL_SVG, COMPANY_DIR / "android-chrome-192x192.png", 192)
    make_square_icon(SMALL_SVG, COMPANY_DIR / "android-chrome-512x512.png", 512)
    make_square_icon(FULL_SVG, COMPANY_DIR / "zdajnik-logo-512.png", 512, inset_ratio=0.08)

    create_favicon_ico(COMPANY_DIR / "favicon-48x48.png", COMPANY_DIR / "favicon.ico")

    build_social_image(
        FULL_SVG,
        COMPANY_DIR / "zdajnik-og-1200x630.png",
        width=1200,
        height=630,
        bg=(255, 255, 255, 255),
    )
    build_social_image(
        FULL_SVG,
        COMPANY_DIR / "zdajnik-twitter-1200x600.png",
        width=1200,
        height=600,
        bg=(255, 255, 255, 255),
    )

    print("Generated assets in:")
    print(f"- {COMPANY_DIR}")


if __name__ == "__main__":
    main()
