"""Cuts the ice cream sandwich out of its pedestal for the shelf easter egg in the /admin trap.

Reads   public/img/honeypot/sandwich-hero.webp   (760x756 RGBA: the sandwich on a dark metal plate)
Writes  public/img/honeypot/egg-sandwich.webp        (about 160 px wide, plate removed, cold light)
        public/img/honeypot/egg-sandwich-lit.webp    (the same, tinted by the red light that floods in when the door opens)

Only the bread and the cream are kept: the plate is dark metal (low luminance, no colour), the sandwich is bright or
saturated, so a luminance plus saturation test above the plate line separates them. Hole filling and a 0.8 px feather
finish the edge.

Run from the project root:  python scripts/build_honeypot_egg.py
Needs: pillow, numpy, scipy.
"""

from pathlib import Path

import numpy as np
from PIL import Image, ImageFilter
from scipy import ndimage as ndi

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "public" / "img" / "honeypot"
WIDTH = 160


def build():
    im = Image.open(OUT / "sandwich-hero.webp").convert("RGBA")
    a = np.asarray(im).astype(np.float32)
    rgb, alpha = a[..., :3], a[..., 3]
    mx, mn = rgb.max(2), rgb.min(2)
    lum = rgb.mean(2)
    sat = (mx - mn) / np.maximum(mx, 1)
    keep = (alpha > 128) & ((lum > 62) | (sat > 0.32))
    keep[440:, :] &= (lum[440:, :] > 92) | (sat[440:, :] > 0.4)  # the plate's rim and its red reflection are darker or flatter
    keep[566:, :] = False
    keep = ndi.binary_opening(keep, iterations=2)
    lab, n = ndi.label(keep)
    sizes = ndi.sum(keep, lab, range(1, n + 1))
    keep = lab == (1 + int(np.argmax(sizes)))
    keep = ndi.binary_fill_holes(ndi.binary_closing(keep, iterations=4))
    mask = Image.fromarray((keep * 255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(1.4))
    cut = Image.fromarray(rgb.astype(np.uint8)).convert("RGBA")
    cut.putalpha(mask)
    box = cut.getbbox()
    cut = cut.crop(box)
    h = round(cut.height * WIDTH / cut.width)
    small = cut.resize((WIDTH, h), Image.LANCZOS)
    # cold, dim: the version the room shows with the door shut
    arr = np.asarray(small).astype(np.float32)
    cold = arr.copy()
    cold[..., :3] = arr[..., :3] * np.array([0.62, 0.78, 0.84])
    Image.fromarray(np.clip(cold, 0, 255).astype(np.uint8)).save(OUT / "egg-sandwich.webp", quality=80, method=6)
    # red multiply for the lit version
    lit = arr.copy()
    lit[..., :3] = arr[..., :3] * np.array([1.0, 0.34, 0.4])
    Image.fromarray(np.clip(lit, 0, 255).astype(np.uint8)).save(OUT / "egg-sandwich-lit.webp", quality=80, method=6)
    print(small.size, (OUT / "egg-sandwich.webp").stat().st_size, (OUT / "egg-sandwich-lit.webp").stat().st_size)


if __name__ == "__main__":
    build()
