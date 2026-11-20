"""Builds the 30 frame walk atlas of the owner for the /admin trap from his sharp full body cutout.

Reads   public/img/honeypot/character-fullbody.webp   (676x1180 RGBA, standing, facing the camera)
Writes  public/img/honeypot/walk-atlas.webp           (6 x 5 cells, RGBA, one gait cycle of two steps)
        public/img/honeypot/walk-atlas.json           (cellW, cellH, anchorX, anchorY, cols, frames, scale, rig)

Why a rig: a walk sheet drawn at 217x241 px per frame had to be enlarged five times, so the face went soft and one leg did
all the work. Here the head, the torso and the boots keep the pixels of the cutout (the cells are rendered at 0.6x, so the
face stays sharp) and the gait is built from the cutout itself:
  * the two legs are cut out along a polygon each and the cape behind them is filled in, so both can move on their own;
  * each leg follows the same gait phase, half a cycle apart: the foot travels from nearest to the camera (heel strike) back
    to farthest (toe off), then swings forward again with a lift, while the hip stays and the leg shortens and lengthens;
  * the stance foot stays put against the body's bob (lowest at both heel strikes, highest at mid stance), the hips sway over
    the stance leg, the upper body rolls against them, the hands swing a little and the cape lags behind with a hem ripple.
Frame 0 is the left heel strike and frame 15 the right one, the cycle loops with no seam (frame 29 runs into frame 0).

The same run also writes the backlit variant used while he walks, public/img/honeypot/walk-atlas-dark.webp (see build_dark):
the door behind him is the only light, so he is a black silhouette with a thin red rim on his outline and two dim red points
for eyes; his face and glasses cannot be read. "python scripts/build_honeypot_walk.py --dark" rebuilds only that variant from
the existing walk-atlas.webp.

Run from the project root:  python scripts/build_honeypot_walk.py
Needs: pillow, numpy, scipy, opencv-python-headless.
"""

import json
import math
import sys
from pathlib import Path

import cv2
import numpy as np
from PIL import Image

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "public" / "img" / "honeypot"

FRAMES, ATLAS_COLS = 30, 6
SCALE = 0.6  # source px to cell px
MARGIN_X, MARGIN_TOP, MARGIN_BOT = 22, 14, 44  # room for the sway, the cape ripple and the lowest foot (source px)
CX, HIP_Y, SHOULDER_Y, FOOT_Y = 342.0, 590.0, 235.0, 1178.0

# gait amplitudes, source px
# DEPTH and LEG_SCALE set how far the planted foot travels against the body in one step. He walks toward the lens, and in
# the perspective of the room that is only a short way on screen, so the steps are short and heavy (a stalk, not a stride).
BOB, SWAY, LIFT, DEPTH = 9.0, 11.0, 74.0, 14.0
LEG_SCALE = 0.022
ROLL_DEG, ARM_SWING, CAPE_SWAY, HEM_RIPPLE = 1.3, 14.0, 11.0, 9.0

LEFT_LEG = [(215, 575), (CX, 575), (CX, 1100), (262, 1100), (258, 1180), (108, 1180), (120, 1120), (150, 1040), (158, 930),
            (170, 860), (182, 800), (190, 700), (202, 640)]
RIGHT_LEG = [(2 * CX - x, y) for x, y in LEFT_LEG]
HEM = [(0, 1040), (100, 1075), (200, 1098), (342, 1100), (484, 1098), (584, 1075), (676, 1040)]  # where the cape ends


def load():
    im = np.asarray(Image.open(OUT / "character-fullbody.webp").convert("RGBA")).astype(np.float32)
    return im


def poly_mask(h, w, pts, feather=2.2):
    m = np.zeros((h, w), np.uint8)
    cv2.fillPoly(m, [np.array(pts, np.int32)], 255)
    return cv2.GaussianBlur(m.astype(np.float32) / 255.0, (0, 0), feather)


def premul(im):
    out = im.copy()
    out[..., :3] *= out[..., 3:4] / 255.0
    return out


def split_layers(im):
    """Returns (base, left, right) as premultiplied float images, the legs taken out of the base and the cape filled in."""
    h, w, _ = im.shape
    ml, mr = poly_mask(h, w, LEFT_LEG), poly_mask(h, w, RIGHT_LEG)
    mlegs = np.clip(ml + mr, 0, 1)
    pm = premul(im)
    left, right = pm * ml[..., None], pm * mr[..., None]

    # the cape between and behind the legs: a dark fill that ends on the hem curve
    hem = np.zeros((h, w), np.uint8)
    pts = HEM + [(w, 0), (0, 0)]
    cv2.fillPoly(hem, [np.array(pts, np.int32)], 255)
    hem = cv2.GaussianBlur(hem.astype(np.float32) / 255.0, (0, 0), 1.5)
    ring = cv2.dilate((mlegs > 0.02).astype(np.uint8), np.ones((9, 9), np.uint8)) - (mlegs > 0.02).astype(np.uint8)
    rgb = im[..., :3] * (im[..., 3:4] / 255.0)
    sel = (ring > 0) & (im[..., 3] > 200) & (np.arange(h)[:, None] > 560) & (np.arange(h)[:, None] < 1060)
    fill = np.median(rgb[sel], axis=0) if sel.any() else np.array([6, 3, 5], np.float32)
    fill = np.minimum(fill, 14)  # the gap between the legs is almost black
    base = pm * (1 - mlegs[..., None])
    a_fill = hem * mlegs
    base[..., :3] += fill[None, None, :] * (a_fill[..., None] * 1.0)
    base[..., 3] = np.maximum(base[..., 3], a_fill * 255.0)
    return base, left, right


def smooth(x):
    x = np.clip(x, 0, 1)
    return x * x * (3 - 2 * x)


def leg_pose(theta):
    """Foot offset of one leg for the gait phase theta (0 = heel strike): dx, dy, scale about the hip, lift 0..1."""
    s = math.cos(theta)  # +1 nearest to the camera (heel strike), -1 farthest (toe off)
    swing = max(0.0, -math.sin(theta))  # the foot is in the air from toe off to the next heel strike
    lift = swing ** 0.8 * (1 - 0.15 * s)
    return dict(dx=0.0, dy=DEPTH * s - LIFT * lift, scale=1 + LEG_SCALE * s, lift=lift)


def remap(layer, field_x, field_y, out_w, out_h, ox, oy):
    """Backward map: the pixel at (x, y) of the output comes from (x + field), after the margin offset (ox, oy)."""
    yy, xx = np.mgrid[0:out_h, 0:out_w].astype(np.float32)
    mx, my = xx - ox + field_x, yy - oy + field_y
    return cv2.remap(layer, mx, my, cv2.INTER_LINEAR, borderMode=cv2.BORDER_CONSTANT, borderValue=0)


def build_frame(base, left, right, i):
    h, w, _ = base.shape
    out_w, out_h = w + 2 * MARGIN_X, h + MARGIN_TOP + MARGIN_BOT
    ox, oy = MARGIN_X, MARGIN_TOP
    phi = 2 * math.pi * i / FRAMES
    thL, thR = phi, phi + math.pi
    yy, xx = np.mgrid[0:out_h, 0:out_w].astype(np.float32)
    sx, sy = xx - ox, yy - oy  # source coordinates of the output grid

    bob = BOB * math.cos(2 * phi)  # lowest at heel strike, highest at mid stance
    sway = -SWAY * math.sin(phi)  # the body over the stance leg
    roll = math.radians(ROLL_DEG) * math.sin(phi)

    # base: the cape and the upper body, one smooth field (small enough to be used as its own backward map)
    wb = smooth((sy - SHOULDER_Y) / (FOOT_Y - SHOULDER_Y))  # 0 at the shoulders, 1 at the hem
    lag = math.sin(phi - 0.9)  # the cape lags the body
    side = (sx - CX) / 340.0
    fx = sway + roll * (HIP_Y - sy) * 0.0 + CAPE_SWAY * lag * wb ** 1.6 + 3.0 * math.sin(phi) * wb
    fy = bob + HEM_RIPPLE * np.sin(2 * phi - 1.3 + side * 2.2) * wb ** 3 * np.abs(side) * 0.8
    # arms: the left hand goes back when the left foot goes forward
    arm = np.clip((sy - 250.0) / 330.0, 0, 1) * np.clip(np.abs(side) * 3.0 - 1.15, 0, 1) * (1 - smooth((sy - 640.0) / 120.0))
    fy = fy + ARM_SWING * arm * (-np.sign(side)) * math.cos(phi) * 1.0
    # upper body roll about the hips, a rigid turn of whatever lies above them
    above = 1 - smooth((sy - HIP_Y) / 90.0)
    fx = fx + above * roll * (HIP_Y - sy)
    fy = fy + above * roll * (sx - CX) * -1.0
    base_f = remap(base, -fx, -fy, out_w, out_h, ox, oy)
    # legs
    layers = [base_f]
    for leg, th in ((left, thL), (right, thR)):
        p = leg_pose(th)
        wl = np.clip((sy - HIP_Y) / (FOOT_Y - HIP_Y), 0, 1) ** 1.25
        k = 1 - wl
        sc = p["scale"]
        # the leg shortens or lengthens about the hip, the foot takes the gait offset, the hip takes the body's bob and sway
        dyf = (sc - 1) * (sy - HIP_Y) * wl + p["dy"] * wl
        fy_l = k * bob + dyf
        fx_l = k * sway + p["dx"] * wl + (sc - 1) * (sx - CX) * wl
        layers.append(remap(leg, -fx_l, -fy_l, out_w, out_h, ox, oy))
    # composite, the base first and the legs over it (the leg that is farther back goes under the other)
    order = [layers[0]] + sorted(layers[1:], key=lambda _: 0)
    res = np.zeros_like(order[0])
    for L in order:
        res = L + res * (1 - L[..., 3:4] / 255.0)
    return res


def to_cell(prem, cell_w, cell_h):
    a = prem[..., 3:4]
    rgb = np.where(a > 0.5, prem[..., :3] / np.maximum(a / 255.0, 1e-3), 0)
    rgba = np.concatenate([np.clip(rgb, 0, 255), a], -1)
    # downscale with premultiplied area averaging, which keeps the edges free of dark fringes
    pm = np.concatenate([prem[..., :3], a], -1)
    small = cv2.resize(pm, (cell_w, cell_h), interpolation=cv2.INTER_AREA)
    sa = small[..., 3:4]
    out_rgb = np.where(sa > 0.8, small[..., :3] / np.maximum(sa / 255.0, 1e-3), 0)
    return np.concatenate([np.clip(out_rgb, 0, 255), sa], -1).astype(np.uint8)


# The backlit grade: body and face crushed to near black, only the outline catches the red light from the door.
BODY_GAIN, HEAD_GAIN = 0.07, 0.03
RIM_PX, RIM_RGB = 3, (255.0, 46.0, 58.0)
EYE_DY, EYE_DX, EYE_R, EYE_RGB = 59, 16, 2.4, (190.0, 18.0, 28.0)  # cell px from the top of the head; a small, dim red point each


def build_dark():
    meta = json.loads((OUT / "walk-atlas.json").read_text())
    cw, ch, cols, frames = meta["cellW"], meta["cellH"], meta["cols"], meta["frames"]
    lit = np.asarray(Image.open(OUT / "walk-atlas.webp").convert("RGBA")).astype(np.float32)
    out = np.zeros_like(lit)
    for i in range(frames):
        x0, y0 = (i % cols) * cw, (i // cols) * ch
        cell = lit[y0:y0 + ch, x0:x0 + cw].copy()
        # The legs are feathered into the cape, which leaves a thin, half transparent seam; on a black silhouette in front of
        # the red door it would read as a lit line, so a small grey closing fills it (the outline itself does not move).
        cell[..., 3] = np.maximum(cell[..., 3], cv2.morphologyEx(cell[..., 3], cv2.MORPH_CLOSE, np.ones((7, 7), np.uint8)))
        a = cell[..., 3] / 255.0
        rows = np.where(a.max(1) > 0.5)[0]
        top, bot = int(rows[0]), int(rows[-1])
        fig_h = bot - top
        yy = np.arange(ch, dtype=np.float32)[:, None]
        # head and neck (the top 18 percent) go darker still than the body, through a soft ramp
        head = 1 - np.clip((yy - (top + 0.18 * fig_h)) / (0.08 * fig_h), 0, 1)
        gain = BODY_GAIN + (HEAD_GAIN - BODY_GAIN) * head
        rgb = np.minimum(cell[..., :3] * gain[..., None], 12.0)  # nothing on the body may catch light (the belt buckle did)
        # the rim: a thin band just inside the outline, strongest at the top of the head and the shoulders, fading down the legs
        # (measured from the empty space around him, so the seams where the legs overlap the cape never light up)
        empty = (a < 0.1).astype(np.uint8)
        near = cv2.dilate(empty, np.ones((2 * RIM_PX + 1, 2 * RIM_PX + 1), np.uint8))
        band = cv2.GaussianBlur((near * (1 - empty)).astype(np.float32), (0, 0), 1.0) * a
        reach = 1 - 0.75 * np.clip((yy - (top + 0.35 * fig_h)) / (0.5 * fig_h), 0, 1)
        rim = np.clip(band * reach, 0, 1)[..., None]
        rgb = rgb * (1 - rim) + np.array(RIM_RGB) * 0.78 * rim
        # the eyes: two small dim red points where the lenses are, so he looks at the camera out of the dark
        band_rows = a[top + EYE_DY - 20:top + EYE_DY + 20]
        xs = np.where(band_rows.max(0) > 0.5)[0]
        cx = (xs[0] + xs[-1]) / 2.0 if len(xs) else cw / 2.0
        yy2, xx2 = np.mgrid[0:ch, 0:cw].astype(np.float32)
        for ex in (cx - EYE_DX, cx + EYE_DX):
            d = np.exp(-((xx2 - ex) ** 2 + (yy2 - (top + EYE_DY)) ** 2) / (2 * EYE_R ** 2))[..., None]
            rgb = rgb * (1 - 0.8 * d) + np.array(EYE_RGB) * 0.8 * d
        out[y0:y0 + ch, x0:x0 + cw, :3] = rgb
        out[y0:y0 + ch, x0:x0 + cw, 3] = cell[..., 3]
        out[y0:y0 + ch, x0:x0 + cw, :3] *= (cell[..., 3:4] > 0)
    path = OUT / "walk-atlas-dark.webp"
    Image.fromarray(out.clip(0, 255).astype(np.uint8), "RGBA").save(path, quality=80, method=6, alpha_quality=90)
    print(path.name, path.stat().st_size, "bytes")


def main():
    if "--dark" in sys.argv:
        build_dark()
        return
    im = load()
    base, left, right = split_layers(im)
    h, w, _ = base.shape
    out_w, out_h = w + 2 * MARGIN_X, h + MARGIN_TOP + MARGIN_BOT
    cell_w, cell_h = int(round(out_w * SCALE)), int(round(out_h * SCALE))
    rows = -(-FRAMES // ATLAS_COLS)
    atlas = Image.new("RGBA", (cell_w * ATLAS_COLS, cell_h * rows), (0, 0, 0, 0))
    for i in range(FRAMES):
        fr = build_frame(base, left, right, i)
        cell = to_cell(fr, cell_w, cell_h)
        atlas.paste(Image.fromarray(cell, "RGBA"), ((i % ATLAS_COLS) * cell_w, (i // ATLAS_COLS) * cell_h))
    atlas.save(OUT / "walk-atlas.webp", quality=84, method=6, alpha_quality=92)
    anchor_x = round((MARGIN_X + CX) * SCALE)
    anchor_y = round((MARGIN_TOP + FOOT_Y + DEPTH) * SCALE)
    # Where each foot touches down in every frame (the lowest opaque row of each half of the cell), for the page: the walk is
    # driven by the planted foot, so it can never slide on the floor.
    arr = np.asarray(atlas)
    feet = []
    for i in range(FRAMES):
        cell = arr[(i // ATLAS_COLS) * cell_h:(i // ATLAS_COLS + 1) * cell_h, (i % ATLAS_COLS) * cell_w:(i % ATLAS_COLS + 1) * cell_w, 3]
        pair = []
        for x0, x1 in ((0, anchor_x), (anchor_x, cell_w)):
            rows = np.where((cell[:, x0:x1] > 128).sum(1) > 2)[0]
            yb = int(rows[-1]) if len(rows) else cell_h - 1
            cols = np.where(cell[max(0, yb - 6):yb + 1, x0:x1].max(0) > 128)[0]
            pair += [yb + 1, round(x0 + float(cols.mean()), 1) if len(cols) else float(anchor_x)]
        feet.append(pair)  # [left sole y, left x, right sole y, right x]
    anchor_y = max(max(pair[0], pair[2]) for pair in feet)  # the floor line is where a planted boot really ends, so no sole is cut off
    meta = dict(cellW=cell_w, cellH=cell_h, anchorX=anchor_x, anchorY=anchor_y, cols=ATLAS_COLS, frames=FRAMES, scale=SCALE,
                rig=dict(bob=BOB, sway=SWAY, lift=LIFT, depth=DEPTH, legScale=LEG_SCALE), feet=feet)
    (OUT / "walk-atlas.json").write_text(json.dumps(meta))
    print(meta, (OUT / "walk-atlas.webp").stat().st_size, "bytes")
    build_dark()


if __name__ == "__main__":
    main()
