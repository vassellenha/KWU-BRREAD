"""Builds the door swing for the /admin trap: a frame by frame atlas of the leaf opening, a puff of dust, and the room
plate without its open door.

Reads   public/img/honeypot/room-closed.webp, room-open.webp   (1536x1024)
Writes  public/img/honeypot/door-atlas.webp + door-atlas.json   18 frames of the leaf (6 x 3 cells, RGBA)
        public/img/honeypot/dust-atlas.webp                    8 frames of a dust puff (4 x 2 cells, RGBA)
        public/img/honeypot/room-open-nodoor.webp              room-open with the open leaf painted out (no seam)
        public/img/honeypot/door-leaf-front.webp, door-leaf-back.webp   the two leaf textures (for reference and reuse)

Geometry (room pixels). Closed: the leaf is LEAF, its hinges on the RIGHT edge. Open: the leaf stands on the right of the
opening, seen from behind (hinges on its left edge, handle on its right), the quad OPEN_QUAD. Every frame is a perspective
projection of the leaf turning about the hinge axis (a pinhole camera at the image centre), front face up to 90 degrees,
back face after it, and from 90 degrees on the corners glide to the measured OPEN_QUAD so the last frame IS the leaf of
room-open. Each frame also carries what the swing causes in the room: a slit of red light in the growing gap and the
leaf's shadow on the floor. The easing, one moment where the latch catches, and the rebound at the stopper are baked
into the angle table, so the page maps time to a frame index and nothing else.

Run from the project root:  python scripts/build_honeypot_door.py
Needs: pillow, numpy, scipy.
"""

import json
import math
from pathlib import Path

import numpy as np
from PIL import Image, ImageDraw, ImageFilter
from scipy import ndimage as ndi

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "public" / "img" / "honeypot"

LEAF = (648, 368, 902, 738)  # closed leaf: x0, y0, x1, y1 (hinge edge on the right)
OPEN_QUAD = [(872, 365), (997, 312), (985, 737), (872, 728)]  # TL (hinge), TR (free), BR, BL of the open leaf
REGION = (560, 300, 1060, 780)  # the part of the room the atlas covers
SCALE = 1.0
SS = 2  # frames are drawn at twice the size and scaled down, so the edge of the leaf is anti-aliased
FW, FH = round((REGION[2] - REGION[0]) * SCALE), round((REGION[3] - REGION[1]) * SCALE)
COLS = 6
CAM = (768.0, 512.0)
DIST = 1100.0
THETA_F = 121.0
# angle (degrees, 0 = shut) per frame: eases out of the latch, catches once around 6 degrees, lets go, swings, hits the stop
# and bounces back for two frames. The last value is flagged 'final' and lands exactly on room-open.
ANGLES = [0, 1.5, 3.5, 5.6, 5.6, 5.8, 10, 20, 35, 53, 71, 88, 102, 112, 118, 124.5, 119.5, THETA_F]


def coeffs(dst, src):
    """PIL perspective coefficients that map output (dst) pixels to input (src) pixels."""
    a, b = [], []
    for (x, y), (u, v) in zip(dst, src):
        a.append([x, y, 1, 0, 0, 0, -u * x, -u * y])
        a.append([0, 0, 0, x, y, 1, -v * x, -v * y])
        b += [u, v]
    return np.linalg.solve(np.array(a, dtype=np.float64), np.array(b, dtype=np.float64))


def warp(tex, quad, size):
    """Warp the rectangle `tex` onto the quad (TL, TR, BR, BL in frame coordinates), RGBA, premultiplied for clean edges."""
    w, h = tex.size
    src = [(0, 0), (w, 0), (w, h), (0, h)]
    c = coeffs(quad, src)
    arr = np.asarray(tex).astype(np.float32)
    a = arr[..., 3:4] / 255.0
    pre = Image.fromarray(np.dstack([arr[..., :3] * a, arr[..., 3:4]]).astype(np.uint8), "RGBA")
    out = pre.transform(size, Image.PERSPECTIVE, tuple(c), Image.BICUBIC)
    o = np.asarray(out).astype(np.float32)
    al = o[..., 3:4] / 255.0
    rgb = np.where(al > 0.01, o[..., :3] / np.maximum(al, 0.01), 0)
    return Image.fromarray(np.dstack([np.clip(rgb, 0, 255), o[..., 3:4]]).astype(np.uint8), "RGBA")


def feather(img, px):
    a = np.asarray(img.getchannel("A")).astype(np.float32) / 255.0
    inner = ndi.binary_erosion(a > 0.5, iterations=max(1, int(px)))
    soft = Image.fromarray((inner * 255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(px / 1.6))
    out = img.copy()
    out.putalpha(Image.fromarray((np.minimum(a, np.asarray(soft) / 255.0) * 255).astype(np.uint8)))
    return out


def project(x, y, z):
    f = DIST / (DIST - z)
    return (CAM[0] + (x - CAM[0]) * f, CAM[1] + (y - CAM[1]) * f)


def smooth(x):
    x = min(1.0, max(0.0, x))
    return x * x * (3 - 2 * x)


def lerp(a, b, t):
    return a + (b - a) * t


def leaf_quad(theta):
    """Corners (TL, TR, BR, BL) of the leaf in room pixels, and which face is toward the camera."""
    x0, y0, x1, y1 = LEAF
    w = x1 - x0
    xh = lerp(x1, OPEN_QUAD[0][0], smooth((theta - 50) / 60))
    th = math.radians(min(theta, 179))
    fx, fz = xh - w * math.cos(th), w * math.sin(th)
    top_h, bot_h = project(xh, y0, 0), project(xh, y1, 0)
    top_f, bot_f = project(fx, y0, fz), project(fx, y1, fz)
    if theta <= 90:  # front face: free edge on the left, hinge on the right
        quad = [top_f, top_h, bot_h, bot_f]
        face = "front"
    else:  # back face: hinge on the left, free edge on the right
        quad = [top_h, top_f, bot_f, bot_h]
        face = "back"
        q = (theta - 90) / (THETA_F - 90)
        k = smooth(min(q, 1.0)) if q <= 1 else 1.0 + (q - 1) * 0.9
        quad = [(lerp(a[0], b[0], k), lerp(a[1], b[1], k)) for a, b in zip(quad, OPEN_QUAD)]
    return quad, face


def textures(closed, opened):
    """The closed leaf exactly as it is in the plate (opaque, no margin: it must meet the plate pixel for pixel at rest),
    and the open leaf straightened to a rectangle (200 x 540) from the quad it occupies in room-open."""
    front = closed.crop(LEAF).convert("RGBA")
    c = coeffs([(0, 0), (200, 0), (200, 540), (0, 540)], OPEN_QUAD)
    back = opened.convert("RGBA").transform((200, 540), Image.PERSPECTIVE, tuple(c), Image.BICUBIC)
    back = feather(back, 2)
    return front, back


def room_open_nodoor(closed, opened):
    """Paint the open leaf out of room-open: closed-room pixels (shape of the wall) coloured like the red wall beside it."""
    o = np.asarray(opened.convert("RGB")).astype(np.float32)
    c = np.asarray(closed.convert("RGB")).astype(np.float32)
    mask = Image.new("L", opened.size, 0)
    d = ImageDraw.Draw(mask)
    cx = sum(p[0] for p in OPEN_QUAD) / 4
    cy = sum(p[1] for p in OPEN_QUAD) / 4
    d.polygon([(cx + (x - cx) * 1.1 + (6 if x > cx else -6), cy + (y - cy) * 1.04) for x, y in OPEN_QUAD], fill=255)
    m = np.asarray(mask.filter(ImageFilter.GaussianBlur(3))).astype(np.float32)[..., None] / 255.0
    # wall colour: the strip just right of the leaf
    ref = o[330:720, 1004:1030].reshape(-1, 3)
    wall = np.percentile(ref, 40, axis=0)
    lum = c.mean(2, keepdims=True)
    fill = lum / max(1.0, float(np.percentile(lum[330:720, 1004:1030], 50))) * wall
    # keep a hint of the floor's light on the lower part
    t = np.clip((np.arange(o.shape[0])[:, None, None] - 640) / 100.0, 0, 1)
    fill = fill * (1 - 0.0 * t)
    out = o * (1 - m) + fill * m
    return Image.fromarray(np.clip(out, 0, 255).astype(np.uint8))


def frame_for(theta, front, back, final=False):
    """One frame of the atlas, REGION sized, drawn at SS times the size and scaled down."""
    rw, rh = (REGION[2] - REGION[0]) * SS, (REGION[3] - REGION[1]) * SS
    quad, face = leaf_quad(theta)
    shifted = [((x - REGION[0]) * SS, (y - REGION[1]) * SS) for x, y in quad]
    base = Image.new("RGBA", (rw, rh), (0, 0, 0, 0))
    x0, y0, x1, y1 = [v * SS for v in (LEAF[0] - REGION[0], LEAF[1] - REGION[1], LEAF[2] - REGION[0], LEAF[3] - REGION[1])]

    # light in the gap: a slit of red between the jamb and the free edge while the leaf is turning (the room brings the rest)
    if 0 < theta < 78:
        gap = Image.new("L", (rw, rh), 0)
        free = shifted[0][0] if face == "front" else shifted[1][0]
        ImageDraw.Draw(gap).polygon([(x0, y0), (free, shifted[0][1]), (free, shifted[3][1]), (x0, y1)], fill=255)
        g = np.asarray(gap.filter(ImageFilter.GaussianBlur(2 * SS))).astype(np.float32) / 255.0
        yy = (np.arange(rh)[:, None] - y0) / (y1 - y0)
        prof = 0.55 + 0.45 * np.sin(np.clip(yy, 0, 1) * math.pi)
        amount = min(1.0, theta / 18.0) * (1 - smooth((theta - 30) / 45)) * 0.8
        glow = np.zeros((rh, rw, 4), np.float32)
        glow[..., 0], glow[..., 1], glow[..., 2] = 255, 46, 78
        glow[..., 3] = 255 * g * prof * amount
        base = Image.alpha_composite(base, Image.fromarray(glow.astype(np.uint8), "RGBA"))

    # the leaf's shadow on the floor: a dark parallelogram under its bottom edge, thrown toward the camera
    if 0 < theta < 108:
        s = math.sin(math.radians(theta))
        sh = Image.new("L", (rw, rh), 0)
        ImageDraw.Draw(sh).polygon(
            [shifted[3], shifted[2], (shifted[2][0] + 46 * SS * s, shifted[2][1] + 34 * SS * s), (shifted[3][0] + 46 * SS * s, shifted[3][1] + 34 * SS * s)],
            fill=int(150 * (1 - smooth((theta - 75) / 33))),
        )
        sh = sh.filter(ImageFilter.GaussianBlur(7 * SS))
        shadow = Image.new("RGBA", (rw, rh), (0, 0, 0, 0))
        shadow.putalpha(sh)
        base = Image.alpha_composite(base, shadow)

    tex = front if face == "front" else back
    leaf = warp(tex, shifted, (rw, rh))
    if face == "front":  # the leaf darkens a little as it turns away from the light
        arr = np.asarray(leaf).astype(np.float32)
        arr[..., :3] *= 1 - 0.28 * smooth(theta / 90)
        leaf = Image.fromarray(arr.astype(np.uint8), "RGBA")
    base = Image.alpha_composite(base, leaf)
    return base.resize((FW, FH), Image.LANCZOS)


def dust_frames(n=8, size=128):
    rng = np.random.default_rng(7)
    seeds = rng.normal(size=(size, size)).astype(np.float32)
    layers = [ndi.gaussian_filter(seeds, s) for s in (2, 5, 10)]
    layers = [(l - l.mean()) / l.std() for l in layers]
    yy, xx = np.mgrid[0:size, 0:size].astype(np.float32)
    frames = []
    for i in range(n):
        t = i / (n - 1)
        r = 14 + 40 * (1 - (1 - t) ** 2)
        cx, cy = size / 2 + 8 * t, size * 0.62 - 10 * t
        d = np.hypot((xx - cx) / 1.35, (yy - cy) / 0.95)
        shape = np.clip(1 - d / r, 0, 1) ** 0.8
        tex = np.clip(0.55 + 0.28 * layers[0] + 0.2 * layers[1] + 0.12 * layers[2], 0, 1)
        a = shape * tex * (1 - t) ** 1.4 * 0.75
        rgba = np.zeros((size, size, 4), np.uint8)
        rgba[..., 0], rgba[..., 1], rgba[..., 2] = 150, 112, 112
        rgba[..., 3] = np.clip(a * 255, 0, 255)
        frames.append(Image.fromarray(rgba, "RGBA"))
    return frames


def build():
    closed = Image.open(OUT / "room-closed.webp").convert("RGB")
    opened = Image.open(OUT / "room-open.webp").convert("RGB")
    front, back = textures(closed, opened)
    front.save(OUT / "door-leaf-front.webp", quality=80, method=6)
    back.save(OUT / "door-leaf-back.webp", quality=80, method=6)
    room_open_nodoor(closed, opened).save(OUT / "room-open-nodoor.webp", quality=80, method=6)

    atlas = Image.new("RGBA", (FW * COLS, FH * math.ceil(len(ANGLES) / COLS)), (0, 0, 0, 0))
    for i, th in enumerate(ANGLES):
        atlas.paste(frame_for(th, front, back, i == len(ANGLES) - 1), ((i % COLS) * FW, (i // COLS) * FH))
    atlas.save(OUT / "door-atlas.webp", quality=80, method=6)

    dust = dust_frames()
    da = Image.new("RGBA", (128 * 4, 128 * 2), (0, 0, 0, 0))
    for i, f in enumerate(dust):
        da.paste(f, ((i % 4) * 128, (i // 4) * 128))
    da.save(OUT / "dust-atlas.webp", quality=80, method=6)

    meta = dict(
        frameW=FW,
        frameH=FH,
        scale=SCALE,
        offsetX=REGION[0],
        offsetY=REGION[1],
        regionW=REGION[2] - REGION[0],
        regionH=REGION[3] - REGION[1],
        cols=COLS,
        frames=len(ANGLES),
        angleAt=ANGLES,
        latch=[3, 4, 5],  # the frames where the latch catches
        stop=[15, 16, 17],  # the hit, the rebound, the rest
        hole=list(LEAF),  # the closed leaf's rectangle (the closed plate is cut open here)
        dust=dict(cellW=128, cellH=128, cols=4, frames=8),
    )
    (OUT / "door-atlas.json").write_text(json.dumps(meta, indent=2) + "\n")
    for n in ("door-atlas", "dust-atlas", "room-open-nodoor", "door-leaf-front", "door-leaf-back"):
        print(n, (OUT / f"{n}.webp").stat().st_size)


if __name__ == "__main__":
    build()
