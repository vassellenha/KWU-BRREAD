"""Cuts the 30 frame walk cycle of the owner into a transparent sprite atlas for the /admin trap.

Reads   public/img/honeypot/walk-sheet/walk-cycle.png   (2172x724, 10 x 3 frames, left to right then top to bottom,
                                                          a dark crimson wall with vertical banding behind every frame)
Writes  public/img/honeypot/walk-atlas.webp            (6 x 5 cells, RGBA)
        public/img/honeypot/walk-atlas.json            (cellW, cellH, anchorX, anchorY, cols, frames, scale, seam)

How the wall is removed: a salient object model (rembg, u2net) mattes every frame at 3x, which keeps the torso solid and the
cloak soft. The matte is trimmed to the largest blob, taken one pixel in, its translucent part lifted a little (so the cloak
never reads as a hole), and the colour of the solid inside is pushed outwards over the soft edge, so no wall red is left in it.
Without rembg installed a hand made key is used (the wall is rebuilt from the border strips of each row, everything clearly
darker is subject), which is good for most frames but loses cloak and torso on some.
Every frame is then placed on one uniform scale (1.6x Lanczos plus a light unsharp), with the point between the feet at
(anchorX, anchorY), so the pose changes and the stride length stay true. The loop seam (frame 29 to 0) is measured and,
if it jumps, the last two frames are cross faded towards frame 0.

Run from the project root:  python scripts/build_honeypot_walk.py
Needs: pillow, numpy, scipy; and for the matte: pip install rembg onnxruntime (the u2net model downloads on first use).
"""

import json
from pathlib import Path

import numpy as np
from PIL import Image, ImageFilter
from scipy import ndimage as ndi

ROOT = Path(__file__).resolve().parent.parent
SRC = ROOT / "public" / "img" / "honeypot" / "walk-sheet" / "walk-cycle.png"
OUT = ROOT / "public" / "img" / "honeypot"

COLS, ROWS = 10, 3
EXTRA_L, EXTRA_R = 80, 8
FIRST_CENTRE = 138  # x of the figure in the first cell, measured on the sheet
SCALE = 1.6
CELL_W, CELL_H = 400, 408  # wide enough for the cloak when it flares out to the left
ATLAS_COLS, ATLAS_ROWS = 6, 5
ANCHOR = (CELL_W // 2, CELL_H - 18)


def frame_window(sheet, i):
    h, w, _ = sheet.shape
    cw, ch = w / COLS, h / ROWS
    r, c = divmod(i, COLS)
    # the window reaches past the cell on the left, where the cloak flares; the neighbours' figures stay outside it
    x0 = int(round(FIRST_CENTRE + c * cw - cw / 2)) - EXTRA_L
    x1 = int(round(FIRST_CENTRE + c * cw - cw / 2)) + int(cw) + EXTRA_R
    x0, x1 = max(0, x0), min(w, x1)
    y0, y1 = int(round(r * ch)), int(round((r + 1) * ch))
    f = sheet[y0:y1, x0:x1]
    pad = (EXTRA_L + int(cw) + EXTRA_R) - f.shape[1]
    if pad > 0:  # the first and the last cell: pad with the wall colour of their own edge
        edge = f[:, :1] if x0 == 0 else f[:, -1:]
        f = np.concatenate([np.repeat(edge, pad, 1), f] if x0 == 0 else [f, np.repeat(edge, pad, 1)], 1) if x0 == 0 else np.concatenate([f, np.repeat(edge, pad, 1)], 1)
    return f


def segment(f):
    R, G, B = f[..., 0], f[..., 1], f[..., 2]
    h, w = R.shape
    left = np.median(R[:, :10], axis=1)
    right = np.median(R[:, -10:], axis=1)
    t = np.linspace(0, 1, w)[None, :]
    wall = np.maximum(left[:, None] * (1 - t) + right[:, None] * t, 8)
    dark = R < 0.62 * wall + 2
    lit = ((G + B) / (2 * R + 6) > 0.3) & ((R + G + B) > 40)
    m = dark | lit
    m[:, :6] = False
    m[:, -6:] = False
    m = ndi.binary_opening(m, iterations=1)
    lab, n = ndi.label(m)
    if n == 0:
        return m
    sizes = ndi.sum(m, lab, range(1, n + 1))
    m = lab == (1 + int(np.argmax(sizes)))

    cols = np.where(m.any(0))[0]
    cx = int(np.median(cols[(cols > cols.min() + 10) & (cols < cols.max() - 10)])) if len(cols) > 30 else w // 2
    width = m.sum(1)
    sh = int(np.argmax(width > 28)) if (width > 28).any() else 60
    diff = np.abs(R - wall) > (0.36 * wall + 6)
    head = np.zeros_like(m)
    y0, y1, x0, x1 = max(0, sh - 48), sh + 6, max(0, cx - 26), cx + 26
    head[y0:y1, x0:x1] = diff[y0:y1, x0:x1]
    head = ndi.binary_opening(head, iterations=2)
    m = m | head
    m = ndi.binary_closing(m, iterations=3)
    lab, n = ndi.label(m)
    sizes = ndi.sum(m, lab, range(1, n + 1))
    m = lab == (1 + int(np.argmax(sizes)))
    return ndi.binary_fill_holes(m)


def feet_anchor(m):
    """Where the feet are, in the pixels of the mask it is given: the middle of the bottom rows and the bottom row."""
    ys, xs = np.where(m)
    bottom = ys.max()
    band = ys > bottom - 16
    return float(xs[band].mean()), float(bottom)


def model_session():
    """The matte comes from a salient object model (rembg, u2net) when it is installed: pip install rembg onnxruntime.
    Without it the hand made key above is used, which is good for most frames but loses cape and torso on some."""
    try:
        from rembg import new_session

        return new_session("u2net")
    except Exception:
        return None


def model_alpha(f, session):
    from rembg import remove

    h, w = f.shape[:2]
    big = Image.fromarray(f.astype(np.uint8)).resize((w * 3, h * 3), Image.LANCZOS)
    return remove(big, session=session, only_mask=True)  # a soft matte at 3x


def keep_largest(m):
    lab, n = ndi.label(m)
    if n <= 1:
        return m
    sizes = ndi.sum(m, lab, range(1, n + 1))
    return lab == (1 + int(np.argmax(sizes)))


def build():
    sheet = np.asarray(Image.open(SRC).convert("RGB")).astype(np.float32)
    session = model_session()
    frames = []
    for i in range(COLS * ROWS):
        f = frame_window(sheet, i)
        h0, w0 = f.shape[:2]
        nw, nh = int(round(w0 * SCALE)), int(round(h0 * SCALE))
        if session is not None:
            alpha = model_alpha(f, session).resize((nw, nh), Image.LANCZOS)
            a = np.asarray(alpha).astype(np.float32) / 255.0
            m = keep_largest(a > 0.5)
            # drop specks the model left around the figure, keep its soft edge (the cape is meant to be soft)
            near = ndi.binary_dilation(m, iterations=6)
            a = np.where(near, a, 0)
            a = ndi.grey_erosion(a, size=(3, 3))  # one pixel in: the wall red stays out of the edge
            a = np.clip((a - 0.06) / 0.84, 0, 1) ** 0.75  # lift the translucent cape a little, so it never reads as a hole
            a = np.asarray(Image.fromarray((a * 255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(0.7))).astype(np.float32) / 255.0
        else:
            m0 = segment(f)
            mask = ndi.binary_erosion(m0, iterations=1)
            alpha = Image.fromarray((mask * 255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(1.2)).resize((nw, nh), Image.LANCZOS)
            a = np.asarray(alpha).astype(np.float32) / 255.0
            m = np.asarray(Image.fromarray((m0 * 255).astype(np.uint8)).resize((nw, nh), Image.NEAREST)) > 127
        ax, ay = feet_anchor(m)
        # colour: scale the frame, then push the colour of the solid inside outwards over the soft edge, so no wall red is left in it
        rgb = np.asarray(Image.fromarray(f.astype(np.uint8)).resize((nw, nh), Image.LANCZOS).filter(ImageFilter.UnsharpMask(radius=1.2, percent=60, threshold=2))).astype(np.float32)
        solid = a > 0.92
        if solid.any():
            idx = ndi.distance_transform_edt(~solid, return_distances=False, return_indices=True)
            ext = rgb[idx[0], idx[1]]
            w = np.clip((a - 0.5) / 0.42, 0, 1)[..., None]  # inside the solid the true colour, on the edge the extended one
            rgb = rgb * w + ext * (1 - w)
        out = np.dstack([np.clip(rgb, 0, 255), a * 255]).astype(np.uint8)
        ox = int(round(ANCHOR[0] - ax))
        oy = int(round(ANCHOR[1] - ay))
        big = Image.new("RGBA", (CELL_W + 2 * nw, CELL_H + 2 * nh), (0, 0, 0, 0))
        big.alpha_composite(Image.fromarray(out, "RGBA"), (ox + nw, oy + nh))
        cell = big.crop((nw, nh, nw + CELL_W, nh + CELL_H))
        frames.append(cell)

    # frames whose key went wrong (a hole in the torso, a lost cape, a stray streak) are rebuilt from the two frames around
    # them: premultiplied average, so the stride stays continuous. Measured on the alpha, never judged by eye.
    def stats(fr):
        al = np.asarray(fr.getchannel("A")) > 128
        xs = np.where(al.any(0))[0]
        return int(al.sum()), int(xs.max() - xs.min()) if len(xs) else 0

    st = [stats(f) for f in frames]
    med_area = float(np.median([a for a, _ in st]))
    med_w = float(np.median([w for _, w in st]))
    bad = [i for i, (a, w) in enumerate(st) if a < 0.8 * med_area or w > 1.5 * med_w]
    print('alpha area per frame', [a for a, _ in st], 'repaired', bad)
    for i in bad:
        lo = i - 1
        while lo % len(frames) in bad:
            lo -= 1
        hi = i + 1
        while hi % len(frames) in bad:
            hi += 1
        a = np.asarray(frames[lo % len(frames)]).astype(np.float32)
        b = np.asarray(frames[hi % len(frames)]).astype(np.float32)
        t = (i - lo) / (hi - lo)
        pa, pb = a[..., :3] * a[..., 3:] / 255.0, b[..., :3] * b[..., 3:] / 255.0
        al = a[..., 3:] * (1 - t) + b[..., 3:] * t
        rgb = (pa * (1 - t) + pb * t) / np.maximum(al / 255.0, 1e-3)
        frames[i] = Image.fromarray(np.dstack([np.clip(rgb, 0, 255), al]).astype(np.uint8), "RGBA")

    # loop seam: frame 29 to 0 against the typical step to step difference
    def d(a, b):
        return float(np.abs(np.asarray(a).astype(np.int16) - np.asarray(b).astype(np.int16))[..., :3].mean())

    steps = [d(frames[i], frames[i + 1]) for i in range(len(frames) - 1)]
    seam = d(frames[-1], frames[0])
    blended = False
    if seam > 3.0 * float(np.median(steps)):  # a ghosted double figure reads worse than a small pop
        blended = True
        for k, t in ((len(frames) - 2, 1 / 3), (len(frames) - 1, 2 / 3)):
            frames[k] = Image.blend(frames[k], frames[0], t)

    atlas = Image.new("RGBA", (CELL_W * ATLAS_COLS, CELL_H * ATLAS_ROWS), (0, 0, 0, 0))
    for i, fr in enumerate(frames):
        atlas.paste(fr, ((i % ATLAS_COLS) * CELL_W, (i // ATLAS_COLS) * CELL_H))
    atlas.save(OUT / "walk-atlas.webp", quality=80, method=6)
    meta = dict(
        cellW=CELL_W,
        cellH=CELL_H,
        anchorX=ANCHOR[0],
        anchorY=ANCHOR[1],
        cols=ATLAS_COLS,
        frames=len(frames),
        scale=SCALE,
        repaired=bad,
        seam=dict(jump=round(seam, 2), typicalStep=round(float(np.median(steps)), 2), crossfaded=blended),
    )
    (OUT / "walk-atlas.json").write_text(json.dumps(meta, indent=2) + "\n")
    print(meta, (OUT / "walk-atlas.webp").stat().st_size, "bytes")


if __name__ == "__main__":
    build()
