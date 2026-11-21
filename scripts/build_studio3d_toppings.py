"""Bakes the assets of the two mini toppings of the 3D Brread! Studio: Marshmallow Mini and Yupi Gummy Bear Mini.

The 3D pieces themselves are built in code (resources/js/studio3d.js, buildMarshmallows() and
buildGummyBears()). This script makes what they wear and the flat artwork the rest of the site uses:

    public/img/studio3d/toppings/marshmallow.webp     512 x 512, RGB albedo (near white, tinted per piece
                                                      in 3D) + A sugar dust mask, tiles in both directions
    public/img/studio3d/toppings/marshmallow-n.webp   512 x 512, tangent-space normal map of fine wrinkles
    public/img/ingredients/topping-marshmallow-mini.webp  picker / flat preview / cart art (1000 x 405)
    public/img/ingredients/topping-gummy-bears.webp       same, gummy bears
    public/img/cutout/top-marshmallow.webp            order status sandwich overlay (720 x 292)
    public/img/cutout/top-gummy-bears.webp            same, gummy bears

The flat art is rendered from signed distance fields of the same shapes the 3D Studio draws (a rounded
cylinder; a bear made of a head, two ears, a body, two arms and two legs blended together), ray-marched
straight down and lit, so the 2D and 3D toppings match. Every random choice comes from fixed seeds.

Run from the project root:  python scripts/build_studio3d_toppings.py
Needs: pillow, numpy, scipy.
"""

from pathlib import Path

import numpy as np
from PIL import Image, ImageFilter
from scipy import ndimage as ndi

ROOT = Path(__file__).resolve().parent.parent
TEXTURES = ROOT / "public" / "img" / "studio3d" / "toppings"
INGREDIENTS = ROOT / "public" / "img" / "ingredients"
CUTOUT = ROOT / "public" / "img" / "cutout"

MARSHMALLOW_COLORS = ["#f2e9da", "#f5eee2", "#eee4d2", "#f6c6d1"]  # last one is the pastel pink (about 1 in 5)
GUMMY_COLORS = ["#d8261c", "#f0740f", "#f2b90c", "#3ea52a"]


def srgb(hex_colour):
    hex_colour = hex_colour.lstrip("#")
    return np.array([int(hex_colour[i : i + 2], 16) for i in (0, 2, 4)], np.float32) / 255.0


def smoothstep(a, b, x):
    t = np.clip((x - a) / np.maximum(b - a, 1e-9), 0, 1)
    return t * t * (3 - 2 * t)


def save_webp(path, image, quality, alpha_quality=100):
    path.parent.mkdir(parents=True, exist_ok=True)
    image.save(path, "WEBP", quality=quality, alpha_quality=alpha_quality, method=6, exact=True)
    print(f"  {path.relative_to(ROOT).as_posix():58s} {image.width}x{image.height}  {path.stat().st_size / 1024:.0f} KB")


# ---------------------------------------------------------------------------------------------
# Tiling noise for the marshmallow textures
# ---------------------------------------------------------------------------------------------


def tiling_noise(size, cells, seed, octaves=4):
    """Value noise that wraps at the image edges, roughly in 0..1."""
    rng = np.random.default_rng(seed)
    out = np.zeros((size, size), np.float32)
    amp, total = 1.0, 0.0
    for o in range(octaves):
        n = cells * 2**o
        grid = rng.random((n, n)).astype(np.float32)
        yy, xx = np.mgrid[0:size, 0:size].astype(np.float32) / size * n
        x0, y0 = np.floor(xx).astype(int), np.floor(yy).astype(int)
        fx, fy = xx - x0, yy - y0
        fx, fy = fx * fx * (3 - 2 * fx), fy * fy * (3 - 2 * fy)
        x1, y1 = (x0 + 1) % n, (y0 + 1) % n
        x0, y0 = x0 % n, y0 % n
        top = grid[y0, x0] * (1 - fx) + grid[y0, x1] * fx
        bottom = grid[y1, x0] * (1 - fx) + grid[y1, x1] * fx
        out += amp * (top * (1 - fy) + bottom * fy)
        total += amp
        amp *= 0.5
    return out / total


def bake_marshmallow_textures():
    print("topping / marshmallow textures")
    size = 512
    # Fine wrinkles of a skin that dried while the foam set: stretched noise in two directions, a few creases.
    a = tiling_noise(size, 6, 11, octaves=5)
    b = tiling_noise(size, 14, 12, octaves=3)
    crease = 1 - np.abs(tiling_noise(size, 5, 13, octaves=4) * 2 - 1)
    crease = smoothstep(0.82, 0.97, crease)
    height = a * 0.55 + b * 0.25 - crease * 0.35
    height = ndi.gaussian_filter(height, 1.0, mode="wrap")
    gx = (np.roll(height, -1, 1) - np.roll(height, 1, 1)) * 0.5
    gy = (np.roll(height, -1, 0) - np.roll(height, 1, 0)) * 0.5
    strength = 9.0
    n = np.dstack([-gx * strength, gy * strength, np.ones_like(height)])
    n /= np.linalg.norm(n, axis=2, keepdims=True)
    normal = np.clip(np.round((n * 0.5 + 0.5) * 255), 0, 255).astype(np.uint8)
    save_webp(TEXTURES / "marshmallow-n.webp", Image.fromarray(normal, "RGB"), 85)

    # Albedo: almost white, faint warm mottling, and specks of icing sugar (alpha = sugar mask, used for roughness).
    rng = np.random.default_rng(21)
    mottle = tiling_noise(size, 4, 22, octaves=4)
    base = np.dstack([np.full((size, size), 0.97), np.full((size, size), 0.955), np.full((size, size), 0.93)]).astype(np.float32)
    base *= (0.97 + mottle * 0.05)[..., None]
    base -= (crease * 0.035)[..., None]
    sugar = np.zeros((size, size), np.float32)
    for _ in range(2600):
        x, y = rng.integers(0, size, 2)
        r = rng.uniform(0.6, 1.6)
        sugar[y, x] = max(sugar[y, x], r)
    sugar = ndi.gaussian_filter(np.tile(sugar, (3, 3)), 0.9)[size : 2 * size, size : 2 * size]
    sugar = np.clip(sugar / max(sugar.max(), 1e-6) * 2.2, 0, 1) * smoothstep(0.3, 0.7, tiling_noise(size, 3, 23, octaves=3))
    albedo = np.clip(base + sugar[..., None] * 0.05, 0, 1)
    rgba = np.dstack([np.round(albedo * 255), np.round(sugar * 255)]).astype(np.uint8)
    save_webp(TEXTURES / "marshmallow.webp", Image.fromarray(rgba, "RGBA"), 84, alpha_quality=80)


# ---------------------------------------------------------------------------------------------
# Signed distance fields of the pieces (units: millimetres)
# ---------------------------------------------------------------------------------------------


def rotation(yaw, pitch, roll):
    cy, sy, cp, sp, cr, sr = np.cos(yaw), np.sin(yaw), np.cos(pitch), np.sin(pitch), np.cos(roll), np.sin(roll)
    rz = np.array([[cy, -sy, 0], [sy, cy, 0], [0, 0, 1]])
    rx = np.array([[1, 0, 0], [0, cp, -sp], [0, sp, cp]])
    ry = np.array([[cr, 0, sr], [0, 1, 0], [-sr, 0, cr]])
    return (rz @ rx @ ry).astype(np.float32)


def sd_rounded_cylinder(p, radius, half_height, round_r):
    """Axis along local y."""
    x, y, z = p
    d0 = np.hypot(x, z) - radius + round_r
    d1 = np.abs(y) - half_height + round_r
    outside = np.hypot(np.maximum(d0, 0), np.maximum(d1, 0))
    return np.minimum(np.maximum(d0, d1), 0) + outside - round_r


def sd_ellipsoid(p, centre, radii):
    x, y, z = (p[i] - centre[i] for i in range(3))
    rx, ry, rz = radii
    k0 = np.sqrt((x / rx) ** 2 + (y / ry) ** 2 + (z / rz) ** 2)
    k1 = np.sqrt((x / rx**2) ** 2 + (y / ry**2) ** 2 + (z / rz**2) ** 2)
    return k0 * (k0 - 1) / np.maximum(k1, 1e-6)


def sd_capsule(p, a, b, r):
    pa = np.stack([p[i] - a[i] for i in range(3)])
    ba = np.array(b, np.float32) - np.array(a, np.float32)
    bb = ba.reshape((3,) + (1,) * (pa.ndim - 1))
    h = np.clip((pa * bb).sum(0) / (ba @ ba), 0, 1)
    d = pa - bb * h
    return np.sqrt((d * d).sum(0)) - r


def smin(a, b, k):
    h = np.clip(0.5 + 0.5 * (b - a) / k, 0, 1)
    return b * (1 - h) + a * h - k * h * (1 - h)


# The bear, standing along local +y, face towards +z, about 12 mm tall, 4.3 mm deep. Same parts as
# gummyBearGeometry() in studio3d.js.
BEAR_PARTS = dict(
    head=((0, 2.85, 0.15), (2.6, 2.3, 2.0)),
    ear_l=((-1.75, 4.45, -0.05), (1.05, 1.05, 0.8)),
    ear_r=((1.75, 4.45, -0.05), (1.05, 1.05, 0.8)),
    body=((0, -0.9, 0.0), (2.75, 3.0, 2.15)),
    leg_l=((-1.4, -3.35, 0.45), (1.3, 1.4, 1.3)),
    leg_r=((1.4, -3.35, 0.45), (1.3, 1.4, 1.3)),
)
BEAR_ARMS = (((-1.75, 0.5, 0.55), (-2.75, -0.8, 0.85), 0.95), ((1.75, 0.5, 0.55), (2.75, -0.8, 0.85), 0.95))


def sd_bear(p):
    d = sd_ellipsoid(p, *BEAR_PARTS["body"])
    d = smin(d, sd_ellipsoid(p, *BEAR_PARTS["head"]), 0.6)
    for ear in ("ear_l", "ear_r"):
        d = smin(d, sd_ellipsoid(p, *BEAR_PARTS[ear]), 0.35)
    for leg in ("leg_l", "leg_r"):
        d = smin(d, sd_ellipsoid(p, *BEAR_PARTS[leg]), 0.45)
    for a, b, r in BEAR_ARMS:
        d = smin(d, sd_capsule(p, a, b, r), 0.4)
    return d


def sd_marshmallow(p):
    return sd_rounded_cylinder(p, 5.0, 4.4, 1.6)


# ---------------------------------------------------------------------------------------------
# Rendering one piece, seen from straight above (orthographic, looking down -z of the image space)
# ---------------------------------------------------------------------------------------------


def render_piece(sdf, rot, px_per_mm, extent_mm, colour, kind, seed):
    """Returns an RGBA float sprite and its alpha, lit from the upper left."""
    n = int(round(extent_mm * 2 * px_per_mm))
    coords = (np.arange(n) + 0.5) / px_per_mm - extent_mm
    xs, ys = np.meshgrid(coords, -coords)
    inv = rot.T

    def field(points):
        local = np.tensordot(inv, points, axes=1)
        return sdf(local)

    # March from above.
    z = np.full(xs.shape, extent_mm, np.float32)
    hit = np.zeros(xs.shape, bool)
    for _ in range(90):
        d = field(np.stack([xs, ys, z]))
        hit |= d < 0.01
        z = np.where(hit, z, z - np.maximum(d, 0.02))
        if (z < -extent_mm).all():
            break
    hit &= z > -extent_mm

    eps = 0.03
    points = np.stack([xs, ys, z])
    grad = []
    for axis in range(3):
        o = np.zeros((3, 1, 1), np.float32)
        o[axis] = eps
        grad.append(field(points + o) - field(points - o))
    normal = np.stack(grad)
    normal /= np.maximum(np.linalg.norm(normal, axis=0, keepdims=True), 1e-6)

    light = np.array([-0.45, 0.55, 0.7], np.float32)
    light /= np.linalg.norm(light)
    view = np.array([0, 0, 1], np.float32)
    half = (light + view) / np.linalg.norm(light + view)
    nl = np.clip(np.tensordot(light, normal, axes=1), 0, 1)
    nh = np.clip(np.tensordot(half, normal, axes=1), 0, 1)
    facing = np.clip(normal[2], 0, 1)

    # Ambient occlusion: how far inside the shape a short step along the normal still is.
    ao = np.ones(xs.shape, np.float32)
    for step in (0.35, 0.8, 1.4):
        ao -= np.clip(step - field(points + normal * step), 0, step) / step * 0.12
    ao = np.clip(ao, 0.55, 1)

    c = srgb(colour)
    if kind == "marshmallow":
        rng = np.random.default_rng(seed)
        wrinkle = ndi.gaussian_filter(rng.random(xs.shape).astype(np.float32), 1.1)
        diffuse = c[:, None, None] * (0.76 + 0.3 * nl)[None] * (0.35 + 0.65 * ao)[None] * (0.97 + 0.06 * wrinkle)[None]
        sheen = (1 - facing) ** 3 * 0.22
        spec = nh**18 * 0.08
        sugar = (rng.random(xs.shape) > 0.985).astype(np.float32) * facing
        rgb = diffuse + (sheen + spec + sugar * 0.06)[None]
    else:
        # Jelly: deep colour at the rim, a lit core (light travelling through), sharp highlights.
        core = facing**1.2
        body = c * 0.62
        glow = np.clip(c * 1.25 + np.array([0.12, 0.1, 0.04], np.float32), 0, 1)
        diffuse = body[:, None, None] * (0.65 + 0.35 * nl)[None] + (glow[:, None, None] - body[:, None, None]) * (core * 0.85)[None]
        diffuse *= ao[None]
        spec = nh**70 * 1.1 + nh**12 * 0.12
        rim = (1 - facing) ** 4 * 0.12
        rgb = diffuse + (spec + rim)[None]
    rgb = np.clip(rgb, 0, 1).transpose(1, 2, 0)

    alpha = ndi.gaussian_filter(hit.astype(np.float32), 0.6)
    return rgb, np.clip(alpha, 0, 1), z


def scatter(width, height, kind, count, seed, piece_mm, px_per_mm, margin=0.06, min_gap=1.0):
    """A field of pieces with soft contact shadows, on transparency. Poisson-disc positions."""
    rng = np.random.default_rng(seed)
    radius_px = piece_mm * px_per_mm * 0.5
    points = []
    tries = 0
    while len(points) < count and tries < 20000:
        tries += 1
        edge = radius_px * 1.15 + max(width, height) * margin * 0.2
        p = np.array([rng.uniform(edge, width - edge), rng.uniform(edge, height - edge)])
        if all(np.hypot(*(p - q)) > radius_px * 2 * min_gap for q in points):
            points.append(p)

    colour_layer = np.zeros((height, width, 3), np.float32)
    alpha_layer = np.zeros((height, width), np.float32)
    shadow = np.zeros((height, width), np.float32)
    extent = piece_mm * 0.85
    palette = MARSHMALLOW_COLORS if kind == "marshmallow" else GUMMY_COLORS
    sdf = sd_marshmallow if kind == "marshmallow" else sd_bear

    for i, (cx, cy) in enumerate(points):
        if kind == "marshmallow":
            # Lying on their side or standing up, like a handful dropped on the slab.
            standing = rng.random() < 0.35
            rot = rotation(rng.uniform(0, np.pi), (np.pi / 2 if standing else 0) + rng.uniform(-0.2, 0.2), rng.uniform(-0.25, 0.25))
            colour = palette[3] if rng.random() < 0.2 else palette[rng.integers(0, 3)]
        else:
            rot = rotation(rng.uniform(0, 2 * np.pi), rng.uniform(-0.25, 0.25), rng.uniform(-0.3, 0.3))
            colour = palette[i % len(palette)] if rng.random() < 0.7 else palette[rng.integers(0, 4)]
        scale = rng.uniform(0.9, 1.1)
        rgb, alpha, _ = render_piece(sdf, rot, px_per_mm * scale, extent, colour, kind, seed + i)
        h, w = alpha.shape
        x0, y0 = int(cx - w / 2), int(cy - h / 2)
        xs0, ys0 = max(0, -x0), max(0, -y0)
        xe, ye = min(w, width - x0), min(h, height - y0)
        if xe <= xs0 or ye <= ys0:
            continue
        a = alpha[ys0:ye, xs0:xe]
        region = (slice(y0 + ys0, y0 + ye), slice(x0 + xs0, x0 + xe))
        colour_layer[region] = colour_layer[region] * (1 - a[..., None]) + rgb[ys0:ye, xs0:xe] * a[..., None]
        alpha_layer[region] = alpha_layer[region] * (1 - a) + a

        # Contact shadow, offset away from the light.
        off = int(radius_px * 0.18)
        sy, sx = slice(y0 + ys0 + off, y0 + ye + off), slice(x0 + xs0 + off, x0 + xe + off)
        target = shadow[sy, sx]
        shadow[sy, sx] = np.maximum(target, a[: target.shape[0], : target.shape[1]])

    shadow = ndi.gaussian_filter(shadow, radius_px * 0.22) * 0.38
    tint = np.array([0.24, 0.12, 0.08], np.float32)
    out_a = alpha_layer + shadow * (1 - alpha_layer)
    out_rgb = (colour_layer * alpha_layer[..., None] + tint * (shadow * (1 - alpha_layer))[..., None]) / np.maximum(out_a, 1e-6)[..., None]
    rgba = np.dstack([np.clip(out_rgb, 0, 1), np.clip(out_a, 0, 1)])
    return Image.fromarray(np.round(rgba * 255).astype(np.uint8), "RGBA"), len(points)


def bake_flat_art():
    for kind, slug, art, count, mm in (
        ("marshmallow", "marshmallow-mini", "top-marshmallow", (34, 22), 10.0),
        ("gummy", "gummy-bears", "top-gummy-bears", (26, 16), 12.0),
    ):
        print(f"topping / {slug}")
        image, placed = scatter(1000, 405, kind, count[0], 900 + len(slug), mm, 6.2)
        save_webp(INGREDIENTS / f"topping-{slug}.webp", image, 86)
        image, placed_small = scatter(720, 292, kind, count[1], 700 + len(slug), mm, 5.0)
        save_webp(CUTOUT / f"{art}.webp", image, 86)
        print(f"    {placed} pieces on the picker art, {placed_small} on the order status art")


if __name__ == "__main__":
    bake_marshmallow_textures()
    bake_flat_art()
