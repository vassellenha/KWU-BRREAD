"""Bakes the PBR texture set used by the 3D Brread! Studio (resources/js/studio3d.js).

Reads the ingredient photos in public/img/ingredients and writes, into public/img/studio3d:

    bread-<kind>-face.webp / -face-n.webp    crumb cut face + baked crust rim, and its normal map
    bread-<kind>-crust.webp / -crust-n.webp  crust strip wrapped around the slice perimeter
    ice-<flavor>-face.webp / -face-n.webp    top/bottom of the ice cream slab
    ice-<flavor>-side.webp / -side-n.webp    seamless strip wrapped around the slab's cut sides

The slab dimensions below must match SLABS in studio3d.js, because the crust rim is painted
at the exact outline the geometry uses.

Run from the project root:  python scripts/build_studio3d_textures.py
Needs: pillow, numpy, scipy.
"""

from pathlib import Path

import numpy as np
from PIL import Image
from scipy import ndimage as ndi

ROOT = Path(__file__).resolve().parent.parent
SRC = ROOT / "public" / "img" / "ingredients"
OUT = ROOT / "public" / "img" / "studio3d"

# Half-width, half-depth, plan corner radius, edge rounding radius — same numbers as studio3d.js.
BREAD = dict(hw=1.2, hd=0.51, rp=0.11, re=0.034)
ICE = dict(hw=1.08, hd=0.44, rp=0.06, re=0.035)

FACE_W = 2048

# Clean interior of each photo (x0, y0, x1, y1): no cut-out fringe, no backdrop.
BREADS = {
    "white": dict(
        file="bread-white.png",
        crop=(95, 72, 805, 335),
        crust=((212, 160, 98), (172, 110, 54), (122, 72, 32)),  # light, mid, dark
        flecks=0,
    ),
    "wheat": dict(
        file="bread-wheat.png",
        crop=(105, 72, 800, 328),
        crust=((158, 108, 60), (118, 74, 36), (78, 46, 22)),
        flecks=170,
    ),
}
FLAVORS = {
    "vanilla": dict(file="icecream-vanilla.png", crop=(80, 150, 840, 328), bump=1.0),
    "strawberry": dict(file="icecream-strawberry.png", crop=(55, 70, 845, 330), bump=1.0),
    "chocolate": dict(file="icecream-chocolate.png", crop=(95, 80, 795, 315), bump=1.25),
}

rng = np.random.default_rng(7)


def fbm(h, w, base=6, octaves=5, tile_x=False, aspect=None):
    """Smooth fractal noise in 0..1. tile_x makes the left and right edges match."""
    aspect = aspect or (w / h)
    out = np.zeros((h, w), np.float32)
    amp, total = 1.0, 0.0
    for o in range(octaves):
        gh = max(2, int(round(base * 2**o)))
        gw = max(2, int(round(base * 2**o * aspect)))
        grid = rng.random((gh, gw)).astype(np.float32)
        if tile_x:
            grid = np.concatenate([grid, grid, grid], axis=1)
            layer = ndi.zoom(grid, (h / gh, 3 * w / (3 * gw)), order=3, mode="grid-wrap")
            layer = layer[:h, w : 2 * w]
        else:
            layer = ndi.zoom(grid, (h / gh, w / gw), order=3, mode="reflect")[:h, :w]
        if layer.shape != (h, w):
            layer = np.pad(layer, ((0, h - layer.shape[0]), (0, w - layer.shape[1])), mode="edge")
        out += layer * amp
        total += amp
        amp *= 0.5
    out /= total
    return (out - out.min()) / (out.max() - out.min() + 1e-6)


def smoothstep(a, b, x):
    t = np.clip((x - a) / (b - a), 0, 1)
    return t * t * (3 - 2 * t)


def load_crop(file, crop, size):
    im = Image.open(SRC / file).convert("RGBA").crop(crop)
    arr = np.asarray(im).astype(np.float32)
    rgb, a = arr[..., :3], arr[..., 3:4] / 255.0
    mean = (rgb * a).sum((0, 1)) / a.sum()
    rgb = rgb * a + mean * (1 - a)  # flatten any leftover translucency onto the mean colour
    im = Image.fromarray(rgb.astype(np.uint8)).resize(size, Image.LANCZOS)
    return np.asarray(im).astype(np.float32)


def sharpen(rgb, sigma=1.6, amount=0.7):
    blur = ndi.gaussian_filter(rgb, (sigma, sigma, 0))
    return np.clip(rgb + (rgb - blur) * amount, 0, 255)


def luminance(rgb):
    return rgb[..., 0] * 0.299 + rgb[..., 1] * 0.587 + rgb[..., 2] * 0.114


def normal_from_height(height, strength, wrap_x=False):
    mode_x = "wrap" if wrap_x else "nearest"
    dx = ndi.sobel(height, axis=1, mode=mode_x) / 8.0
    dy = ndi.sobel(height, axis=0, mode="nearest") / 8.0
    nx, ny, nz = -dx * strength, dy * strength, np.ones_like(height)
    length = np.sqrt(nx * nx + ny * ny + nz * nz)
    n = np.stack([nx / length, ny / length, nz / length], -1)
    return ((n * 0.5 + 0.5) * 255).astype(np.uint8)


def height_from_photo(rgb, micro=0.35, wrap_x=False):
    """Photo luminance as a height proxy: pores and creases are dark, so they sit low."""
    lum = luminance(rgb) / 255.0
    lum = ndi.gaussian_filter(lum, 0.8)
    broad = ndi.gaussian_filter(lum, 22, mode="wrap" if wrap_x else "nearest")
    h = lum - broad  # drop slow colour changes, keep the relief
    h += (rng.random(h.shape).astype(np.float32) - 0.5) * 0.02 * micro
    return h


def save(arr, name, quality):
    OUT.mkdir(parents=True, exist_ok=True)
    Image.fromarray(np.clip(arr, 0, 255).astype(np.uint8)).save(OUT / name, "WEBP", quality=quality, method=6)
    print(f"  {name:32s} {arr.shape[1]}x{arr.shape[0]}  {(OUT / name).stat().st_size / 1024:.0f} KB")


def rounded_rect_inside_distance(h, w, radius):
    """Distance in px from each pixel to the edge of a rounded rect filling the canvas (>0 inside)."""
    y, x = np.mgrid[0:h, 0:w].astype(np.float32)
    qx = np.abs(x + 0.5 - w / 2) - (w / 2 - radius)
    qy = np.abs(y + 0.5 - h / 2) - (h / 2 - radius)
    outside = np.sqrt(np.maximum(qx, 0) ** 2 + np.maximum(qy, 0) ** 2)
    inside = np.minimum(np.maximum(qx, qy), 0)
    return -(outside + inside - radius)


def crust_colour(h, w, palette, tile_x=False):
    light, mid, dark = (np.array(c, np.float32) for c in palette)
    # On the long perimeter strip, stretch the browning patches along the loaf so they don't read as stripes.
    broad = fbm(h, w, base=3, octaves=4, tile_x=tile_x, aspect=(w / h) * (0.22 if tile_x else 1))
    fine = fbm(h, w, base=28, octaves=4, tile_x=tile_x)
    t = np.clip(0.5 + (broad - 0.5) * 0.7 + (fine - 0.5) * 0.3, 0, 1)[..., None]
    col = np.where(t < 0.5, dark + (mid - dark) * (t * 2), mid + (light - mid) * (t * 2 - 1))
    return col, broad, fine


def build_bread(kind, spec):
    face_hw, face_hd = BREAD["hw"] - BREAD["re"], BREAD["hd"] - BREAD["re"]
    w, h = FACE_W, int(round(FACE_W * face_hd / face_hw / 2) * 2)
    ppu = w / (2 * face_hw)  # pixels per world unit

    crumb = sharpen(load_crop(spec["file"], spec["crop"], (w, h)), sigma=1.3, amount=1.1)
    # The photo is upscaled ~2.8x; fine grain restores the crisp, floury micro-texture close up.
    grain = ndi.gaussian_filter(rng.random((h, w)).astype(np.float32), 0.7)
    crumb = np.clip(crumb * (1 + (grain - 0.5) * 0.22)[..., None], 0, 255)

    # Crust rim baked onto the cut face, following the slice outline with an uneven inner edge.
    dist = rounded_rect_inside_distance(h, w, (BREAD["rp"] - BREAD["re"]) * ppu)
    wobble = fbm(h, w, base=10, octaves=3)
    rim_px = 0.016 * ppu * (0.6 + 0.9 * wobble)
    rim = 1 - smoothstep(rim_px * 0.55, rim_px * 1.25, dist)
    crust, _, fine = crust_colour(h, w, spec["crust"])
    crust *= (0.9 + 0.2 * fine)[..., None]

    # Crumb right under the crust is denser and a touch more baked.
    under = (1 - smoothstep(rim_px, rim_px + 0.045 * ppu, dist))[..., None]
    tint = np.array(spec["crust"][0], np.float32) / 255.0
    crumb = crumb * (1 - under * 0.12 * (1 - tint))
    face = crumb * (1 - rim[..., None]) + crust * rim[..., None]
    save(face, f"bread-{kind}-face.webp", 88)

    height = height_from_photo(face, micro=2.0) * (1 - rim * 0.55) - rim * 0.01
    save(normal_from_height(height, 7.5), f"bread-{kind}-face-n.webp", 90)

    # Crust strip: u runs once around the slice, v runs over the rounded edge from top face to bottom face.
    sw, sh = 4096, 192
    strip, broad, fine = crust_colour(sh, sw, spec["crust"], tile_x=True)
    v = np.linspace(0, 1, sh, dtype=np.float32)[:, None]
    edge = 1 - smoothstep(0.0, 0.16, np.minimum(v, 1 - v))  # lighter where the knife cut through
    strip = strip * (1 - 0.1 * edge[..., None]) + np.array(spec["crust"][0], np.float32) * 0.1 * edge[..., None]
    wrinkle = fbm(sh, sw, base=34, octaves=3, tile_x=True)  # fine dimpling of the baked skin
    strip *= (0.9 + 0.16 * wrinkle)[..., None]
    speck = fbm(sh, sw, base=90, octaves=2, tile_x=True)  # tiny blisters and pores in the baked skin
    strip *= (1 - 0.22 * smoothstep(0.62, 0.8, speck))[..., None]

    bump = broad * 0.35 + fine * 0.4 + wrinkle * 0.6 - smoothstep(0.62, 0.8, speck) * 0.5
    if spec["flecks"]:  # oat flakes and seeds pressed into a wholegrain crust
        ys = rng.integers(8, sh - 8, spec["flecks"])
        xs = rng.integers(0, sw, spec["flecks"])
        mask = np.zeros((sh, sw), np.float32)
        mask[ys, xs] = rng.random(spec["flecks"]) * 0.7 + 0.3
        mask = ndi.gaussian_filter(mask, (2.6, 4.2), mode=("nearest", "wrap"))
        mask = smoothstep(0.003, 0.008, mask)
        oat = np.array((226, 198, 150), np.float32)
        strip = strip * (1 - mask[..., None] * 0.85) + oat * mask[..., None] * 0.85
        bump += mask * 0.6
    save(strip, f"bread-{kind}-crust.webp", 88)
    save(normal_from_height(bump.astype(np.float32), 5.0, wrap_x=True), f"bread-{kind}-crust-n.webp", 90)


def build_ice(flavor, spec):
    face_hw, face_hd = ICE["hw"] - ICE["re"], ICE["hd"] - ICE["re"]
    w, h = FACE_W, int(round(FACE_W * face_hd / face_hw / 2) * 2)
    face = sharpen(load_crop(spec["file"], spec["crop"], (w, h)), amount=0.6)
    save(face, f"ice-{flavor}-face.webp", 88)
    save(normal_from_height(height_from_photo(face), 9.0 * spec["bump"]), f"ice-{flavor}-face-n.webp", 90)

    # Side strip (repeats twice around the slab): two bands of the same photo, cross-faded into a loop.
    sw, sh, overlap = 4096, 512, 256
    seg_w = sw // 2 + overlap
    src = Image.open(SRC / spec["file"]).convert("RGB").crop(spec["crop"])
    seg_h = max(sh, int(round(src.height * seg_w / src.width)))
    big = np.asarray(src.resize((seg_w, seg_h), Image.LANCZOS)).astype(np.float32)
    a = big[:sh]
    b = big[seg_h - sh :][::-1, ::-1]
    ramp = smoothstep(0, 1, np.linspace(0, 1, overlap, dtype=np.float32))[None, :, None]
    strip = np.zeros((sh, sw, 3), np.float32)
    step = seg_w - overlap  # == sw / 2
    strip[:, overlap:step] = a[:, overlap:step]
    strip[:, step : step + overlap] = a[:, step:] * (1 - ramp) + b[:, :overlap] * ramp
    strip[:, step + overlap :] = b[:, overlap:step]
    strip[:, :overlap] = b[:, step:] * (1 - ramp) + a[:, :overlap] * ramp
    strip = sharpen(strip, amount=0.25)
    save(strip, f"ice-{flavor}-side.webp", 86)
    save(normal_from_height(height_from_photo(strip, wrap_x=True), 9.0 * spec["bump"], wrap_x=True), f"ice-{flavor}-side-n.webp", 90)


if __name__ == "__main__":
    for kind, spec in BREADS.items():
        print(f"bread / {kind}")
        build_bread(kind, spec)
    for flavor, spec in FLAVORS.items():
        print(f"ice cream / {flavor}")
        build_ice(flavor, spec)
