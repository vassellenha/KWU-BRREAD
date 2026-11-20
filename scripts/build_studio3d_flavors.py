"""Bakes the procedural slab textures for the multi-layer flavors of the 3D Brread! Studio.

The photo flavors (vanilla, strawberry, chocolate) come from scripts/build_studio3d_textures.py.
The flavors here have segments, marbling and inclusions that must line up over every side of the
slab, so they are not painted from a photo: each one is a solid block of ice cream described in 3D
(segments along the slab's long axis, custard chunks, fruit bits), and this script cuts it along the
slab's six faces. Because every face samples the same block, the layers meet at the edges without
a seam.

Writes, into public/img/studio3d/flavors:

    <slug>/albedo.webp    atlas, RGB colour + A ambient occlusion
    <slug>/surface.webp   atlas, RGB height (grey) + A roughness
    <slug>/frost.webp     atlas at 1/4 size, grey: rime frost on the rim and the frozen top
    detail.webp           tiling micro relief of ice crystals, shared by every flavor

and the flat artwork the rest of the site uses for these flavors:

    public/img/ingredients/icecream-<slug>.webp   picker / flat preview / cart art
    public/img/cutout/ice-<slug>.webp             order status page sandwich

Atlas layout (1024 x 1024, ATLAS_PPU pixels per world unit, rows counted from the top). studio3d.js
maps the slab onto it with a triplanar lookup, so keep REGIONS and ATLAS_PPU in sync with
FLAVOR_ATLAS there:

    top    the top face seen from above: x to the right, -z up (also used for the bottom face)
    front  the +z cut side: x to the right, y up
    back   the -z cut side: -x to the right, y up
    end_r  the +x cut end: -z to the right, y up
    end_l  the -x cut end: z to the right, y up

Owner textures: a file with the same name in public/img/studio/flavors/<slug>/ replaces the
generated one in the Studio, so it must follow the same layout.

Run from the project root:  python scripts/build_studio3d_flavors.py
Needs: pillow, numpy, scipy.
"""

from pathlib import Path

import numpy as np
from PIL import Image, ImageDraw, ImageFilter
from scipy import ndimage as ndi

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "public" / "img" / "studio3d" / "flavors"
INGREDIENTS = ROOT / "public" / "img" / "ingredients"
CUTOUT = ROOT / "public" / "img" / "cutout"

# Same numbers as SLABS.ice in studio3d.js (half-width, half-depth, half-thickness, plan corner radius).
ICE = dict(hw=1.08, hd=0.44, ht=0.19, rp=0.06)

ATLAS = 1024
ATLAS_PPU = 420
FROST_SIZE = 256
# name: (centre column, centre row, half-extent a, half-extent b) in px / world units.
REGIONS = {
    "top": (512, 210, 1.12, 0.47),
    "front": (512, 514, 1.12, 0.225),
    "back": (512, 715, 1.12, 0.225),
    "end_r": (290, 916, 0.47, 0.225),
    "end_l": (734, 916, 0.47, 0.225),
}
GUTTER = 6  # px of extra content painted round each region so mipmaps never bleed in the backdrop

# Relief is stored as 0..1 around 0.5; HEIGHT_RANGE world units span the full range (studio3d.js bumpScale).
HEIGHT_RANGE = 0.012


def srgb(hex_colour):
    hex_colour = hex_colour.lstrip("#")
    return np.array([int(hex_colour[i : i + 2], 16) for i in (0, 2, 4)], np.float32) / 255.0


def smoothstep(a, b, x):
    t = np.clip((x - a) / np.maximum(b - a, 1e-9), 0, 1)
    return t * t * (3 - 2 * t)


def mix(a, b, t):
    t = np.asarray(t, np.float32)
    if t.ndim == 2:
        t = t[..., None]
    return a * (1 - t) + b * t


# ---------------------------------------------------------------------------------------------
# Noise
# ---------------------------------------------------------------------------------------------

_GRAD3 = np.array(
    [[1, 1, 0], [-1, 1, 0], [1, -1, 0], [-1, -1, 0], [1, 0, 1], [-1, 0, 1], [1, 0, -1], [-1, 0, -1], [0, 1, 1], [0, -1, 1], [0, 1, -1], [0, -1, -1]],
    np.float32,
)


class Perlin3:
    """Improved Perlin noise in 3D, vectorised. Output roughly in -1..1."""

    def __init__(self, seed):
        perm = np.random.default_rng(seed).permutation(256)
        self.p = np.concatenate([perm, perm]).astype(np.int64)

    def __call__(self, x, y, z):
        p = self.p
        xf, yf, zf = np.floor(x), np.floor(y), np.floor(z)
        xi, yi, zi = xf.astype(np.int64) & 255, yf.astype(np.int64) & 255, zf.astype(np.int64) & 255
        x, y, z = x - xf, y - yf, z - zf
        u, v, w = (t * t * t * (t * (t * 6 - 15) + 10) for t in (x, y, z))

        def grad(h, dx, dy, dz):
            g = _GRAD3[h % 12]
            return g[..., 0] * dx + g[..., 1] * dy + g[..., 2] * dz

        a, b = p[xi] + yi, p[xi + 1] + yi
        aa, ab, ba, bb = p[a] + zi, p[a + 1] + zi, p[b] + zi, p[b + 1] + zi
        x1 = grad(p[aa], x, y, z) * (1 - u) + grad(p[ba], x - 1, y, z) * u
        x2 = grad(p[ab], x, y - 1, z) * (1 - u) + grad(p[bb], x - 1, y - 1, z) * u
        x3 = grad(p[aa + 1], x, y, z - 1) * (1 - u) + grad(p[ba + 1], x - 1, y, z - 1) * u
        x4 = grad(p[ab + 1], x, y - 1, z - 1) * (1 - u) + grad(p[bb + 1], x - 1, y - 1, z - 1) * u
        return ((x1 * (1 - v) + x2 * v) * (1 - w) + (x3 * (1 - v) + x4 * v) * w).astype(np.float32)


def fbm3(pts, freq, seed, octaves=4, gain=0.5):
    """Fractal noise at the given frequency (scalar or per-axis), roughly in -1..1."""
    x, y, z = pts
    fx, fy, fz = (freq, freq, freq) if np.isscalar(freq) else freq
    out = np.zeros_like(x, dtype=np.float32)
    amp, total = 1.0, 0.0
    for o in range(octaves):
        noise = Perlin3(seed * 101 + o)
        s = 2.0**o
        out += amp * noise(x * fx * s + 17.3 * o, y * fy * s + 5.1 * o, z * fz * s - 9.7 * o)
        total += amp
        amp *= gain
    return out / total * 1.6


def _hash2(ix, iy, seed):
    h = (ix.astype(np.uint64) * np.uint64(374761393) + iy.astype(np.uint64) * np.uint64(668265263) + np.uint64(seed) * np.uint64(1442695041)) & np.uint64(0xFFFFFFFF)
    h = ((h ^ (h >> np.uint64(13))) * np.uint64(1274126177)) & np.uint64(0xFFFFFFFF)
    return ((h ^ (h >> np.uint64(16))) & np.uint64(0xFFFFFF)).astype(np.float32) / float(0xFFFFFF)


def voronoi(a, b, cell, seed, wrap=None):
    """F1, F2 distances (in cells) and a random value per nearest cell, for 2D points in world units."""
    ga, gb = a / cell, b / cell
    ia, ib = np.floor(ga).astype(np.int64), np.floor(gb).astype(np.int64)
    best = np.full(a.shape, 9.0, np.float32)
    second = np.full(a.shape, 9.0, np.float32)
    owner = np.zeros(a.shape, np.float32)
    for oa in (-1, 0, 1):
        for ob in (-1, 0, 1):
            ca, cb = ia + oa, ib + ob
            ha, hb = (ca % wrap, cb % wrap) if wrap else (ca, cb)
            ja = _hash2(ha + 1000, hb, seed)
            jb = _hash2(ha, hb + 1000, seed + 1)
            d = np.hypot(ca + 0.15 + 0.7 * ja - ga, cb + 0.15 + 0.7 * jb - gb).astype(np.float32)
            closer = d < best
            second = np.where(closer, best, np.minimum(second, d))
            owner = np.where(closer, _hash2(ha, hb, seed + 7), owner)
            best = np.minimum(best, d)
    return best, second, owner


# ---------------------------------------------------------------------------------------------
# The slab surface, region by region
# ---------------------------------------------------------------------------------------------


class Surface:
    """Every texel of the atlas that the slab can show, with its 3D point and region coordinates."""

    def __init__(self, ppu=ATLAS_PPU, size=ATLAS):
        self.size, self.ppu = size, ppu
        self.regions = {}
        for name, (cx, cy, ha, hb) in REGIONS.items():
            s = size / ATLAS
            x0, x1 = int(round((cx - ha * ATLAS_PPU - GUTTER) * s)), int(round((cx + ha * ATLAS_PPU + GUTTER) * s))
            y0, y1 = int(round((cy - hb * ATLAS_PPU - GUTTER) * s)), int(round((cy + hb * ATLAS_PPU + GUTTER) * s))
            px, py = np.meshgrid(np.arange(x0, x1) + 0.5, np.arange(y0, y1) + 0.5)
            a = ((px / s - cx) / ATLAS_PPU).astype(np.float32)
            b = ((cy - py / s) / ATLAS_PPU).astype(np.float32)
            hw, hd, ht = ICE["hw"], ICE["hd"], ICE["ht"]
            full = np.full_like(a, 1.0)
            point = {
                "top": (a, full * ht, -b),
                "front": (a, b, full * hd),
                "back": (-a, b, -full * hd),
                "end_r": (full * hw, b, -a),
                "end_l": (-full * hw, b, a),
            }[name]
            self.regions[name] = dict(box=(x0, y0, x1, y1), a=a, b=b, pts=point, inside=self._inside(name, *point))

    @staticmethod
    def _inside(name, x, y, z):
        """Distance (world units) from the texel to the nearest edge of the face it belongs to (<0 outside)."""
        hw, hd, ht, rp = ICE["hw"], ICE["hd"], ICE["ht"], ICE["rp"]
        if name == "top":
            qx = np.abs(x) - (hw - rp)
            qz = np.abs(z) - (hd - rp)
            outside = np.hypot(np.maximum(qx, 0), np.maximum(qz, 0))
            return -(outside + np.minimum(np.maximum(qx, qz), 0) - rp)
        if name in ("front", "back"):
            return np.minimum(ht - np.abs(y), hw - np.abs(x))
        return np.minimum(ht - np.abs(y), hd - np.abs(z))

    def each(self):
        return self.regions.items()

    def all_points(self):
        xs, ys, zs = zip(*(r["pts"] for r in self.regions.values()))
        return tuple(np.concatenate([c.ravel() for c in comp]) for comp in (xs, ys, zs))


def threshold_for_area(values, inside, fraction):
    """The cut-off above which `fraction` of the visible surface lies."""
    return float(np.quantile(values[inside > 0], 1 - fraction))


# ---------------------------------------------------------------------------------------------
# Shared micro detail: crystals, air bubbles, knife marks, frost
# ---------------------------------------------------------------------------------------------


def micro_layers(name, region, seed, bubble_density=1.0):
    a, b = region["a"], region["b"]
    x, y, z = region["pts"]
    cut_side = name != "top"
    out = {}

    # Ice crystals: small facets, a few of which catch the light.
    f1, f2, cell_value = voronoi(a + 3.1, b - 1.7, 0.011, seed + 11)
    facet = (cell_value - 0.5) * smoothstep(0.0, 0.35, f2 - f1)
    sparkle = (cell_value > 0.93).astype(np.float32) * smoothstep(0.05, 0.3, f2 - f1)
    out["crystal_h"] = facet * 0.0014 - (1 - smoothstep(0.0, 0.08, f2 - f1)) * 0.0005
    out["sparkle"] = sparkle

    # Air bubbles: tiny round pits with a darker inside and a faint raised lip.
    g1, _, bubble_value = voronoi(a - 7.3, b + 2.9, 0.034, seed + 23)
    present = (bubble_value < (0.14 if cut_side else 0.05) * bubble_density).astype(np.float32)
    radius = (0.04 + 0.2 * _hash2(np.floor((a - 7.3) / 0.034).astype(np.int64), np.floor((b + 2.9) / 0.034).astype(np.int64), seed + 5)) * present
    pit = (1 - smoothstep(radius * 0.55, radius + 1e-4, g1)) * present
    lip = smoothstep(radius * 0.7, radius, g1) * (1 - smoothstep(radius, radius * 1.45 + 1e-4, g1)) * present
    # Fine porosity: the countless tiny air cells that make a cut through ice cream look matte and soft.
    p1, _, pore_value = voronoi(a + 11.9, b - 4.4, 0.0062, seed + 29)
    pore_r = 0.12 + 0.22 * pore_value
    pore = (1 - smoothstep(pore_r * 0.5, pore_r, p1)) * (pore_value < (0.55 if cut_side else 0.3))
    out["bubble"] = np.clip(pit + pore * 0.35, 0, 1)
    out["bubble_h"] = -pit * 0.0045 + lip * 0.0008 - pore * 0.0012
    out["grain"] = fbm3((x, y, z), 140.0, seed + 33, octaves=2)

    # Gentle unevenness of a frozen block, never flat like plastic.
    out["broad_h"] = fbm3((x, y, z), 9.0, seed + 31, octaves=4) * 0.0022 + fbm3((x, y, z), 35.0, seed + 35, octaves=3) * 0.0008

    if cut_side:
        # Knife marks: fine parallel drag lines, at a slight angle that wanders along the cut.
        angle = 0.06 + 0.05 * fbm3((x * 0.5, y * 0.5, z * 0.5), 2.0, seed + 41, octaves=2)
        across = b * np.cos(angle) - a * np.sin(angle)
        lines = fbm3((across * 160.0, a * 1.2, across * 3.0), 1.0, seed + 43, octaves=3)
        streak = smoothstep(0.25, 0.75, lines) * (0.55 + 0.45 * smoothstep(-0.2, 0.6, fbm3((x, y, z), 3.0, seed + 47, octaves=2)))
        out["knife"] = streak
        out["knife_h"] = -streak * 0.0014
        out["veil"] = np.zeros_like(a)
    else:
        out["knife"] = np.zeros_like(a)
        out["knife_h"] = np.zeros_like(a)
        # A fine frozen bloom over the exposed top.
        out["veil"] = smoothstep(-0.3, 0.8, fbm3((x, y, z), 38.0, seed + 53, octaves=3))
        out["broad_h"] = out["broad_h"] + fbm3((x, y, z), 70.0, seed + 59, octaves=2) * 0.0004

    # Rime frost: a paler rim along every edge of the face, uneven along its length.
    rim_width = 0.03 * (0.7 + 0.6 * (0.5 + 0.5 * fbm3((x, y, z), 6.0, seed + 61, octaves=2)))
    out["rim"] = 1 - smoothstep(0.0, rim_width, region["inside"])
    return out


# ---------------------------------------------------------------------------------------------
# Flavors
# ---------------------------------------------------------------------------------------------


def segment_position(pts, seed, wobble, swirl):
    """x displaced by noise, so segment borders wave and lick into each other instead of being straight.

    The broad part of the wave depends on |y|, so the border meets the top and the bottom faces at the
    same place (the bottom face reuses the top texture); the fine part fades out at both faces.
    """
    x, y, z = pts
    ht = ICE["ht"]
    folded = (x, np.abs(y), z)
    broad = fbm3(folded, (1.2, 5.0, 3.5), seed, octaves=3, gain=0.45) * wobble
    edge_fade = np.clip(1 - (np.abs(y) / ht) ** 4, 0, 1)
    fine = fbm3((x, y, z), (3.0, 13.0, 10.0), seed + 3, octaves=2) * wobble * 0.3 * edge_fade
    warped = x + broad + fine
    if swirl:
        # Marbling: the border is dragged round in slow loops, as if the zones were folded together.
        warp = fbm3((x, y, z), (2.5, 6.0, 5.0), seed + 7, octaves=2)
        warped = warped + fbm3((x + warp * 0.15, y + warp * 0.05, z), (3.5, 9.0, 8.0), seed + 9, octaves=3) * swirl * (0.6 + 0.4 * edge_fade)
    return warped


def segment_weights(xw, borders, softness):
    """Soft weights for len(borders) + 1 segments ordered along x."""
    steps = [smoothstep(b - softness, b + softness, xw) for b in borders]
    weights = []
    previous = np.ones_like(xw)
    for s in steps:
        weights.append(previous - s)
        previous = s
    weights.append(previous)
    return [np.clip(w, 0, 1) for w in weights]


def tint_variation(colour, pts, seed, amount, hue_amount=0.0):
    """Low-frequency density changes in the cream, as a multiplier around 1 with a small hue drift."""
    broad = fbm3(pts, 4.5, seed, octaves=4)
    mottle = fbm3(pts, 26.0, seed + 1, octaves=3)
    shade = 1 + broad * amount + mottle * amount * 0.5
    out = colour[None, None, :] * shade[..., None]
    if hue_amount:
        drift = fbm3(pts, 3.0, seed + 2, octaves=2)[..., None]
        out = out + drift * hue_amount * np.array([0.6, 0.2, -0.8], np.float32)
    return out


def speckles(region, seed, cell, chance, radius):
    """Sparse tiny crisp dots (vanilla seeds, strawberry seeds); cell and radius in world units."""
    f1, _, value = voronoi(region["a"] + 0.37, region["b"] - 0.11, cell, seed)
    x, y, z = region["pts"]
    clustered = chance * (0.4 + 1.2 * smoothstep(-0.5, 0.6, fbm3((x, y, z), 7.0, seed + 1, octaves=2)))
    size = radius * (0.6 + 0.8 * (value / np.maximum(clustered, 1e-6)))
    return (1 - smoothstep(size * 0.55, size, f1 * cell)) * (value < clustered)


def flavor_neapolitan(surface, seed):
    hw = ICE["hw"]
    third = 2 * hw / 3
    borders = (-hw + third, -hw + 2 * third)
    vanilla, strawberry, chocolate = srgb("#f3e9d2"), srgb("#ee8aa3"), srgb("#4a2c1e")

    # Strawberry pulp: irregular darker bits of fruit, cut wherever the block is cut.
    all_pts = surface.all_points()
    pulp_all = fbm3(all_pts, (26.0, 34.0, 34.0), seed + 70, octaves=3)
    pulp_cut = float(np.quantile(pulp_all, 0.955))

    layers = {}
    for name, region in surface.each():
        pts = region["pts"]
        xw = segment_position(pts, seed, wobble=0.025, swirl=0.0)
        w = segment_weights(xw, borders, 0.003)
        # A thin smear where two blocks were pressed together.
        seam = sum(1 - smoothstep(0.0, 0.007, np.abs(xw - b)) for b in borders)

        v_col = tint_variation(vanilla, pts, seed + 1, 0.035, 0.01)
        bean = speckles(region, seed + 2, 0.011, 0.16, 0.0028)
        v_col = mix(v_col, srgb("#2a1d16"), bean * 0.85)

        s_col = tint_variation(strawberry, pts, seed + 4, 0.05, 0.015)
        pulp_raw = fbm3(pts, (26.0, 34.0, 34.0), seed + 70, octaves=3)
        pulp = smoothstep(pulp_cut - 0.02, pulp_cut + 0.1, pulp_raw)
        pulp_tone = 0.5 + 0.5 * fbm3(pts, 90.0, seed + 72, octaves=2)
        s_col = mix(s_col, mix(srgb("#c24763")[None, None, :], srgb("#de6f86")[None, None, :], pulp_tone), pulp * 0.7)
        seeds = speckles(region, seed + 5, 0.02, 0.06, 0.002)
        s_col = mix(s_col, srgb("#e9c98f"), seeds * 0.35)

        c_col = tint_variation(chocolate, pts, seed + 6, 0.03, 0.0)

        albedo = v_col * w[0][..., None] + s_col * w[1][..., None] + c_col * w[2][..., None]
        albedo = mix(albedo, np.clip(albedo * 1.06 + 0.02, 0, 1), np.clip(seam, 0, 1) * 0.35)

        micro = micro_layers(name, region, seed + 100, bubble_density=1.0 - 0.6 * w[2])
        rough = 0.62 * w[0] + 0.6 * w[1] + 0.74 * w[2]
        rough = rough + fbm3(pts, 20.0, seed + 8, octaves=2) * 0.03 - pulp * w[1] * 0.08
        extra_h = pulp * w[1] * 0.0009 - seam * 0.0003
        # Chocolate is denser: fewer, flatter crystals.
        crystal_scale = 1.0 - 0.5 * w[2]
        layers[name] = (albedo, rough, extra_h, micro, crystal_scale)
    return layers


def flavor_durian(surface, seed):
    pale, deep = srgb("#d6dc7a"), srgb("#c8d268")
    custard, custard_edge = srgb("#e8b93f"), srgb("#c9952a")

    all_pts = surface.all_points()
    inside_all = np.concatenate([r["inside"].ravel() for r in surface.regions.values()])

    def chunk_field(pts):
        x, y, z = pts
        warp = fbm3(pts, 9.0, seed + 80, octaves=3)
        return fbm3((x + warp * 0.03, y - warp * 0.025, z + warp * 0.03), 5.0, seed + 81, octaves=2, gain=0.35)

    chunk_cut = threshold_for_area(chunk_field(all_pts), inside_all, 0.05)

    layers = {}
    for name, region in surface.each():
        pts = region["pts"]
        x, y, z = pts
        blend = smoothstep(-0.6, 0.6, fbm3(pts, 2.2, seed + 1, octaves=3))
        cream = mix(pale[None, None, :], deep[None, None, :], blend)
        cream = cream * (1 + fbm3(pts, 22.0, seed + 2, octaves=3)[..., None] * 0.035)

        # Thin paler fibres running along the slab.
        ridge = 1 - np.abs(fbm3((x, y, z), (3.0, 55.0, 55.0), seed + 3, octaves=2))
        fibre = smoothstep(0.86, 0.98, ridge) * smoothstep(-0.3, 0.5, fbm3(pts, (2.0, 6.0, 6.0), seed + 4, octaves=2))
        cream = mix(cream, np.clip(cream * 1.08 + np.array([0.05, 0.05, 0.03], np.float32), 0, 1), fibre * 0.6)

        # Golden custard chunks with a slightly deeper rim.
        field = chunk_field(pts)
        chunk = smoothstep(chunk_cut - 0.015, chunk_cut + 0.05, field)
        core = smoothstep(chunk_cut + 0.03, chunk_cut + 0.12, field)
        inner = mix(custard_edge[None, None, :], custard[None, None, :], 0.75 + 0.25 * core)
        inner = mix(inner, np.clip(inner * 1.08 + 0.04, 0, 1), smoothstep(0.0, 0.8, fbm3(pts, 18.0, seed + 9, octaves=2)) * 0.6)
        inner = inner * (1 + fbm3(pts, 40.0, seed + 6, octaves=2)[..., None] * 0.05)
        # The custard is folded into the cream, so its edge melts into the base instead of being outlined.
        albedo = mix(cream, inner, chunk * 0.9)

        micro = micro_layers(name, region, seed + 200, bubble_density=0.7)
        rough = 0.5 + fbm3(pts, 18.0, seed + 7, octaves=2) * 0.025 - chunk * 0.07 - fibre * 0.03
        extra_h = chunk * 0.0016 + fibre * 0.0005
        layers[name] = (albedo, rough, extra_h, micro, 0.8)
    return layers


def flavor_es_teler(surface, seed):
    hw = ICE["hw"]
    third = 2 * hw / 3
    borders = (-hw + third, -hw + 2 * third)
    mango, strawberry, pandan = srgb("#f5b82e"), srgb("#ef8fa6"), srgb("#a8c66c")

    all_pts = surface.all_points()
    inside_all = np.concatenate([r["inside"].ravel() for r in surface.regions.values()])

    # Each fruit is a stretched 3D blob field pointing its own way, so cuts show shreds and strands.
    def coconut(pts):
        x, y, z = pts
        return fbm3((x * 0.8 + z * 0.6, y, z * 0.8 - x * 0.6), (5.0, 22.0, 22.0), seed + 90, octaves=3)

    def jackfruit(pts):
        x, y, z = pts
        return fbm3((x * 0.6 - y * 0.8, y * 0.6 + x * 0.8, z), (4.0, 15.0, 12.0), seed + 91, octaves=3)

    def avocado(pts):
        return fbm3(pts, 11.0, seed + 92, octaves=3)

    cuts = {
        "coconut": threshold_for_area(coconut(all_pts), inside_all, 0.01),
        "jackfruit": threshold_for_area(jackfruit(all_pts), inside_all, 0.009),
        "avocado": threshold_for_area(avocado(all_pts), inside_all, 0.007),
    }

    layers = {}
    for name, region in surface.each():
        pts = region["pts"]
        xw = segment_position(pts, seed, wobble=0.028, swirl=0.05)
        w = segment_weights(xw, borders, 0.004)

        m_col = tint_variation(mango, pts, seed + 1, 0.05, 0.01)
        s_col = tint_variation(strawberry, pts, seed + 2, 0.05, 0.01)
        p_col = tint_variation(pandan, pts, seed + 3, 0.045, 0.0)
        albedo = m_col * w[0][..., None] + s_col * w[1][..., None] + p_col * w[2][..., None]

        # Marble ribbons: thin wavy streaks of each zone dragged a little way into its neighbour.
        ribbon_pos = xw + fbm3(pts, (5.0, 16.0, 14.0), seed + 11, octaves=2) * 0.018
        breaks = smoothstep(-0.1, 0.4, fbm3(pts, (4.0, 9.0, 9.0), seed + 12, octaves=2))
        cols = (m_col, s_col, p_col)
        for i, b in enumerate(borders):
            for offset, source in ((-0.022, cols[i + 1]), (-0.011, cols[i + 1]), (0.013, cols[i]), (0.026, cols[i])):
                line = 1 - smoothstep(0.0015, 0.004, np.abs(ribbon_pos - (b + offset)))
                albedo = mix(albedo, source, line * breaks * 0.8)

        coco = smoothstep(cuts["coconut"], cuts["coconut"] + 0.03, coconut(pts))
        jack = smoothstep(cuts["jackfruit"], cuts["jackfruit"] + 0.03, jackfruit(pts))
        avo_field = avocado(pts)
        avo = smoothstep(cuts["avocado"], cuts["avocado"] + 0.03, avo_field)
        avo_core = smoothstep(cuts["avocado"] + 0.04, cuts["avocado"] + 0.09, avo_field)

        albedo = mix(albedo, srgb("#f6f1e6"), coco * 0.92)
        albedo = mix(albedo, srgb("#efc23e") * (1 + fbm3(pts, 60.0, seed + 12, octaves=2)[..., None] * 0.06), jack * 0.9)
        albedo = mix(albedo, mix(srgb("#7f9a3a")[None, None, :], srgb("#bccb6e")[None, None, :], avo_core), avo * 0.9)

        micro = micro_layers(name, region, seed + 300, bubble_density=1.0)
        rough = 0.6 + fbm3(pts, 20.0, seed + 13, octaves=2) * 0.03 - jack * 0.1 - avo * 0.12 + coco * 0.08
        extra_h = coco * 0.0008 + jack * 0.0007 + avo * 0.0009
        layers[name] = (albedo, rough, extra_h, micro, 1.0)
    return layers


FLAVORS = {
    "neapolitan": dict(build=flavor_neapolitan, seed=4101),
    "durian-musang-king": dict(build=flavor_durian, seed=4202),
    "es-teler": dict(build=flavor_es_teler, seed=4303),
}


# ---------------------------------------------------------------------------------------------
# Atlas assembly and output
# ---------------------------------------------------------------------------------------------


def save_webp(path, image, quality, alpha_quality=100):
    path.parent.mkdir(parents=True, exist_ok=True)
    image.save(path, "WEBP", quality=quality, alpha_quality=alpha_quality, method=6, exact=True)
    print(f"  {path.relative_to(ROOT).as_posix():58s} {image.width}x{image.height}  {path.stat().st_size / 1024:.0f} KB")


def bake(slug, spec):
    print(f"flavor / {slug}")
    surface = Surface()
    layers = spec["build"](surface, spec["seed"])

    albedo_atlas = np.zeros((ATLAS, ATLAS, 3), np.float32)
    ao_atlas = np.ones((ATLAS, ATLAS), np.float32)
    height_atlas = np.full((ATLAS, ATLAS), 0.5, np.float32)
    rough_atlas = np.full((ATLAS, ATLAS), 0.65, np.float32)
    frost_atlas = np.zeros((ATLAS, ATLAS), np.float32)
    covered = np.zeros((ATLAS, ATLAS), bool)

    for name, region in surface.each():
        albedo, rough, extra_h, micro, crystal_scale = layers[name]
        x0, y0, x1, y1 = region["box"]

        height = micro["broad_h"] + micro["crystal_h"] * crystal_scale + micro["bubble_h"] + micro["knife_h"] + extra_h
        # Cavity occlusion from the relief itself, plus the deep inside of each bubble.
        cavity = height - ndi.gaussian_filter(height, 3.0)
        ao = np.clip(1 + cavity * 90.0, 0.6, 1.0) * (1 - micro["bubble"] * 0.38)

        albedo = albedo * (1 - micro["bubble"][..., None] * 0.06) * (1 + micro["grain"][..., None] * 0.035)
        albedo = albedo * (1 + (micro["sparkle"] * 0.05 * crystal_scale)[..., None])
        rough = rough - micro["sparkle"] * 0.22 * crystal_scale - micro["knife"] * 0.06 + micro["bubble"] * 0.1
        frost = np.clip(np.maximum(micro["rim"] * 0.85, micro["veil"] * 0.25), 0, 1)

        albedo_atlas[y0:y1, x0:x1] = np.clip(albedo, 0, 1)
        ao_atlas[y0:y1, x0:x1] = ao
        height_atlas[y0:y1, x0:x1] = np.clip(0.5 + height / HEIGHT_RANGE, 0, 1)
        rough_atlas[y0:y1, x0:x1] = np.clip(rough, 0.2, 0.95)
        frost_atlas[y0:y1, x0:x1] = frost
        covered[y0:y1, x0:x1] = True

    # Fill the unused corners with the nearest painted texel so the lowest mip levels stay on colour.
    _, (iy, ix) = ndi.distance_transform_edt(~covered, return_indices=True)
    albedo_atlas, ao_atlas = albedo_atlas[iy, ix], ao_atlas[iy, ix]
    height_atlas, rough_atlas, frost_atlas = height_atlas[iy, ix], rough_atlas[iy, ix], frost_atlas[iy, ix]

    out = OUT / slug
    to8 = lambda a: np.clip(np.round(a * 255), 0, 255).astype(np.uint8)
    rgba = np.dstack([to8(albedo_atlas), to8(ao_atlas)])
    save_webp(out / "albedo.webp", Image.fromarray(rgba, "RGBA"), 84, alpha_quality=80)
    h8 = to8(height_atlas)
    save_webp(out / "surface.webp", Image.fromarray(np.dstack([h8, h8, h8, to8(rough_atlas)]), "RGBA"), 85, alpha_quality=70)
    frost_small = Image.fromarray(to8(frost_atlas)).resize((FROST_SIZE, FROST_SIZE), Image.LANCZOS).convert("RGB")
    save_webp(out / "frost.webp", frost_small, 82)

    shaded = shade_for_flat_art(albedo_atlas, height_atlas, ao_atlas, frost_atlas)
    write_flat_art(slug, shaded)


def shade_for_flat_art(albedo, height, ao, frost):
    """A simple lit version of the atlas for the 2D artwork (the 3D view does its own lighting)."""
    h = ndi.gaussian_filter(height * HEIGHT_RANGE * ATLAS_PPU, 0.7)
    gx = ndi.sobel(h, axis=1) / 8.0
    gy = ndi.sobel(h, axis=0) / 8.0
    n = np.dstack([-gx * 2.2, gy * 2.2, np.ones_like(h)])
    n /= np.linalg.norm(n, axis=2, keepdims=True)
    light = np.array([-0.35, 0.55, 0.76], np.float32)
    lambert = np.clip((n * light).sum(2), 0, 1)
    lit = albedo * (0.72 + 0.4 * lambert)[..., None] * (0.55 + 0.45 * ao)[..., None]
    lit = lit * (1 - frost[..., None] * 0.2) + np.array([0.93, 0.95, 0.97], np.float32) * frost[..., None] * 0.2
    return np.clip(lit, 0, 1)


def region_crop(atlas, name, inset=0.0):
    cx, cy, ha, hb = REGIONS[name]
    ha, hb = ha - inset, hb - inset
    return atlas[int(cy - hb * ATLAS_PPU) : int(cy + hb * ATLAS_PPU), int(cx - ha * ATLAS_PPU) : int(cx + ha * ATLAS_PPU)]


def write_flat_art(slug, shaded):
    to_img = lambda a: Image.fromarray(np.clip(np.round(a * 255), 0, 255).astype(np.uint8))
    hw, hd, ht = ICE["hw"], ICE["hd"], ICE["ht"]

    # Order status sandwich: the cut front of the slab, edge to edge (same framing as the other cutouts).
    cy, cx = REGIONS["front"][1], REGIONS["front"][0]
    front = shaded[int(cy - ht * ATLAS_PPU) : int(cy + ht * ATLAS_PPU), int(cx - hw * ATLAS_PPU) : int(cx + hw * ATLAS_PPU)]
    cut = to_img(front).resize((720, 252), Image.LANCZOS).convert("RGBA")
    save_webp(CUTOUT / f"ice-{slug}.webp", cut, 86)

    # Picker / flat preview: the slab seen from the front and a little above, on transparency.
    W, H = 900, 339
    body_w, front_h, top_h = 830, 228, 70
    left, top_y = (W - body_w) // 2, 26
    canvas = Image.new("RGBA", (W, H), (0, 0, 0, 0))

    shadow = Image.new("L", (W, H), 0)
    ImageDraw.Draw(shadow).ellipse((left + 10, top_y + top_h + front_h - 16, left + body_w - 10, top_y + top_h + front_h + 22), fill=120)
    shadow = shadow.filter(ImageFilter.GaussianBlur(14))
    canvas.paste((70, 40, 30, 255), (0, 0), shadow.point(lambda v: int(v * 0.6)))

    top = region_crop(shaded, "top")
    # The top face, squashed into a strip that recedes a little at both ends.
    top_img = to_img(np.clip(top * 1.06, 0, 1)).resize((body_w, top_h), Image.LANCZOS)
    top_layer = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    top_layer.paste(top_img.convert("RGBA"), (left, top_y))
    mask_top = Image.new("L", (W, H), 0)
    ImageDraw.Draw(mask_top).polygon(
        [(left + 26, top_y), (left + body_w - 14, top_y), (left + body_w - 2, top_y + top_h), (left + 2, top_y + top_h)], fill=255
    )
    canvas.paste(top_layer, (0, 0), Image.composite(top_layer, Image.new("RGBA", (W, H)), mask_top).split()[3])

    front_img = to_img(front).resize((body_w, front_h), Image.LANCZOS).convert("RGBA")
    mask_front = Image.new("L", (body_w, front_h), 0)
    ImageDraw.Draw(mask_front).rounded_rectangle((0, 0, body_w - 1, front_h - 1), radius=14, fill=255)
    canvas.paste(front_img, (left, top_y + top_h - 2), mask_front)
    save_webp(INGREDIENTS / f"icecream-{slug}.webp", canvas, 86)


def bake_detail():
    """Tiling micro relief: ice crystals plus a fine grain, shared by every flavor."""
    size, cells = 512, 48
    yy, xx = np.mgrid[0:size, 0:size].astype(np.float32) + 0.5
    a, b = xx / size * cells, yy / size * cells
    f1, f2, value = voronoi(a, b, 1.0, 9001, wrap=cells)
    facet = (value - 0.5) * smoothstep(0.0, 0.3, f2 - f1)
    crack = 1 - smoothstep(0.0, 0.06, f2 - f1)
    g1, g2, _ = voronoi(a * 2.0, b * 2.0, 1.0, 9002, wrap=cells * 2)
    fine = 1 - smoothstep(0.0, 0.08, g2 - g1)
    height = 0.5 + facet * 0.5 - crack * 0.25 - fine * 0.12
    height = (height - height.min()) / (height.max() - height.min())
    g = np.clip(np.round(height * 255), 0, 255).astype(np.uint8)
    save_webp(OUT / "detail.webp", Image.fromarray(g).convert("RGB"), 85)


if __name__ == "__main__":
    bake_detail()
    for slug, spec in FLAVORS.items():
        bake(slug, spec)
