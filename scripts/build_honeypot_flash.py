"""Gives the muzzle flash of the /admin trap a real alpha channel, so it can never leave a dark box on the face.

Reads   public/img/honeypot/muzzle-flash.webp   (any mode: a flash on black, or one that already has alpha)
Writes  public/img/honeypot/muzzle-flash.webp   (RGBA, same size)

The flash is first laid on black (what the eye sees), then its alpha is taken from the brightest channel, eased so the faint
smoke stays faint (alpha = smoothstep of max(R, G, B) between 6 and 150), and the colour is un-premultiplied by that alpha.
Any pixel darker than the floor becomes fully transparent, so nothing dark is left to cover the face behind it. The page also
draws it with mix-blend-mode: screen, which hides any dark pixel that might be left.

Run from the project root:  python scripts/build_honeypot_flash.py
Needs: pillow, numpy.
"""

from pathlib import Path

import numpy as np
from PIL import Image

PATH = Path(__file__).resolve().parent.parent / "public" / "img" / "honeypot" / "muzzle-flash.webp"
FLOOR, FULL = 6.0, 150.0


def main():
    im = np.asarray(Image.open(PATH).convert("RGBA")).astype(np.float32)
    rgb = im[..., :3] * (im[..., 3:4] / 255.0)  # on black

    peak = rgb.max(axis=2)
    x = np.clip((peak - FLOOR) / (FULL - FLOOR), 0.0, 1.0)
    alpha = x * x * (3.0 - 2.0 * x)

    safe = np.maximum(alpha, 1e-3)[..., None]
    colour = np.clip(rgb / safe, 0, 255)
    colour[alpha <= 0] = 0

    out = np.dstack([colour, alpha[..., None] * 255.0]).round().astype(np.uint8)
    Image.fromarray(out, "RGBA").save(PATH, "WEBP", quality=86, method=6, exact=True)

    dark = ((alpha > 0.25) & (colour.max(axis=2) < 40)).sum()
    print(f"{PATH.name}: {out.shape[1]}x{out.shape[0]}, {PATH.stat().st_size // 1024} KB, opaque dark pixels: {dark}")


if __name__ == "__main__":
    main()
