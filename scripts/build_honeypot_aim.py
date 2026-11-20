"""Splits the aim plate into a clean plate (no pistol) and the pistol layer, so the pistol can rise on its own.

Reads   public/img/honeypot/aim-pose.webp   (1024x1536: the owner's face with the pistol and the gloved hand in front of it)
Writes  public/img/honeypot/aim-clean.webp  (the same plate without the pistol, the hand and the forearm)
        public/img/honeypot/aim-gun.webp    (RGBA, only the pistol, the hand and the forearm, same canvas)

The pistol and the hand are cut out along a traced outline (a feathered edge, taken one pixel in so no wall colour comes
along). What was behind them is rebuilt from the other half of the plate: the face is almost symmetrical about the line through
the nose (x = AXIS), so the mirrored cheek, jaw and collar fill the gap, and the edge is blended with a gradient domain
clone (Poisson) that takes its colours from the border of the hole, so the lit cheek stays lit and the join cannot be seen. A gun layer laid over the clean plate gives the original plate again.

Run from the project root:  python scripts/build_honeypot_aim.py     Needs: pillow, numpy, opencv-python-headless.
"""

from pathlib import Path

import cv2
import numpy as np
from PIL import Image

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "public" / "img" / "honeypot"
AXIS = 545.0
CHEST0, CHEST_AXIS = 600, 612
PAD = 40  # reflected border, so the clone also works where the hole touches the edge of the plate  # the chest fold: the source strip starts at x = 600; the fold line sits just right of the mask
OUTLINE = [(330, 664), (398, 668), (436, 722), (478, 782), (493, 860), (497, 984), (530, 998), (572, 1040), (590, 1110), (580, 1182),
           (554, 1242), (542, 1322), (526, 1402), (512, 1472), (508, 1536), (0, 1536), (0, 1162), (22, 1130), (62, 1090), (112, 1022),
           (168, 984), (190, 950), (196, 890), (212, 820), (244, 760), (282, 704)]


def main():
    rgb = np.asarray(Image.open(OUT / "aim-pose.webp").convert("RGB"))
    h, w, _ = rgb.shape
    m = np.zeros((h, w), np.uint8)
    cv2.fillPoly(m, [np.array(OUTLINE, np.int32)], 255)
    # What was behind the hand is rebuilt from the other side of the plate. Up on the face the plate is mirrored about the
    # line through the nose; down on the chest the mask reaches that line, so the chest is folded back and forth between
    # x = CHEST0 and the right edge of the plate (a reflection keeps the armour lines running on at the seam).
    xs_face = np.clip(np.round(2 * AXIS - np.arange(w)).astype(int), 0, w - 1)
    span = (w - 1) - CHEST0
    t = np.mod(2 * CHEST_AXIS - np.arange(w) - CHEST0, 2 * span)
    xs_chest = (CHEST0 + np.where(t > span, 2 * span - t, t)).astype(int)
    face, chest = rgb[:, xs_face].astype(np.float32), rgb[:, xs_chest].astype(np.float32)
    yy = np.arange(h, dtype=np.float32)[:, None, None]
    wy = np.clip((yy - 930.0) / 90.0, 0, 1)
    wy = wy * wy * (3 - 2 * wy)
    fill = face * (1 - wy) + chest * wy
    # The mirrored right cheek is the unlit side of his face, so copied as it is it leaves a dark, smooth patch on the lit
    # left cheek and a black smudge beside the jaw (the dark wall of the right side). Instead the fill only lends its detail:
    # a Poisson clone (gradient domain) keeps the mirrored jaw, mouth and collar lines and takes every colour from the border
    # of the hole, so the cheek picks up the red rim light of the ear and the sideburn, and the wall left of the jaw stays red.
    grow = cv2.dilate(m, np.ones((15, 15), np.uint8))
    fill8 = fill.clip(0, 255).astype(np.uint8)
    pad = lambda a: cv2.copyMakeBorder(a, PAD, PAD, PAD, PAD, cv2.BORDER_REFLECT)
    src, dst, mk = pad(fill8), pad(rgb), pad(grow)
    mk[:PAD, :], mk[-PAD:, :], mk[:, :PAD], mk[:, -PAD:] = 0, 0, 0, 0
    x, y, bw, bh = cv2.boundingRect(mk)
    cloned = cv2.seamlessClone(np.ascontiguousarray(src[:, :, ::-1]), np.ascontiguousarray(dst[:, :, ::-1]), mk, (x + bw // 2, y + bh // 2),
                               cv2.NORMAL_CLONE)[:, :, ::-1][PAD:-PAD, PAD:-PAD].astype(np.float32)
    # Under the collar the folded chest is kept as it is (the clone would wash the armour out with the glow of the glove).
    wb = np.clip((yy - 960.0) / 120.0, 0, 1)
    wb = wb * wb * (3 - 2 * wb)
    soft = cv2.GaussianBlur(grow.astype(np.float32) / 255.0, (0, 0), 6.0)[..., None]
    folded = fill * soft + rgb.astype(np.float32) * (1 - soft)
    clean = (cloned * (1 - wb) + folded * wb).clip(0, 255).astype(np.uint8)
    Image.fromarray(clean).save(OUT / "aim-clean.webp", quality=88, method=6)


    inner = cv2.erode(m, np.ones((3, 3), np.uint8))
    a = cv2.GaussianBlur(inner.astype(np.float32) / 255.0, (0, 0), 1.4)
    gun = np.dstack([rgb, np.clip(a * 255, 0, 255).astype(np.uint8)])
    Image.fromarray(gun, "RGBA").save(OUT / "aim-gun.webp", quality=88, method=6, alpha_quality=95)
    print("aim-clean", (OUT / "aim-clean.webp").stat().st_size, "aim-gun", (OUT / "aim-gun.webp").stat().st_size)


if __name__ == "__main__":
    main()
