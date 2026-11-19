"""Splits the aim plate into a clean plate (no pistol) and the pistol layer, so the pistol can rise on its own.

Reads   public/img/honeypot/aim-pose.webp   (1024x1536: the owner's face with the pistol and the gloved hand in front of it)
Writes  public/img/honeypot/aim-clean.webp  (the same plate without the pistol, the hand and the forearm)
        public/img/honeypot/aim-gun.webp    (RGBA, only the pistol, the hand and the forearm, same canvas)

The pistol and the hand are cut out along a traced outline (a feathered edge, taken one pixel in so no wall colour comes
along). What was behind them is rebuilt from the other half of the plate: the face is almost symmetrical about the line through
the nose (x = AXIS), so the mirrored cheek, jaw and collar fill the gap, and the edge is blended with a gradient domain
clone so the join cannot be seen. A gun layer laid over the clean plate gives the original plate again.

Run from the project root:  python scripts/build_honeypot_aim.py     Needs: pillow, numpy, opencv-python-headless.
"""

from pathlib import Path

import cv2
import numpy as np
from PIL import Image

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "public" / "img" / "honeypot"
AXIS = 545.0
CHEST0, CHEST_AXIS = 600, 612  # the chest fold: the source strip starts at x = 600; the fold line sits just right of the mask
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
    grow = cv2.dilate(m, np.ones((9, 9), np.uint8))
    # The real left side of the face is lit warmer than the mirrored right cheek. Compare both along the ring just outside
    # the outline (the face and the neck only, not the glove) and spread the ratio inwards, so the fill takes the light of its
    # surroundings and no darker patch is left on the cheek.
    # Only the skin just above the hole is trusted: the wall and the neck beside it differ between the two halves of the plate
    # and would drag the cheek towards the wrong colour.
    ys, xs = np.mgrid[0:h, 0:w]
    ring = (cv2.dilate(m, np.ones((2 * 14 + 1, 2 * 14 + 1), np.uint8)) > 0) & (cv2.dilate(m, np.ones((7, 7), np.uint8)) == 0)
    ring &= ((ys > 600) & (ys < 735) & (xs > 270) & (xs < 480)) | ((ys >= 735) & (ys < 985) & (xs > 440))
    blur_o = cv2.GaussianBlur(rgb.astype(np.float32), (0, 0), 6)
    blur_f = cv2.GaussianBlur(fill, (0, 0), 6)
    ratio = (blur_o + 6.0) / (blur_f + 6.0)
    ratio = np.clip(ratio, 0.8, 2.6)
    gain_src = np.where(ring[..., None], ratio, 0).astype(np.float32)
    known = ring.astype(np.float32)
    # spread by normalised convolution at a few scales (a cheap harmonic fill)
    gain = np.ones_like(gain_src)
    acc = np.zeros_like(gain_src)
    wacc = np.zeros((h, w), np.float32)
    for sg in (6, 14, 30, 60, 120):
        num = cv2.GaussianBlur(gain_src, (0, 0), sg)
        den = cv2.GaussianBlur(known, (0, 0), sg)
        est = num / np.maximum(den[..., None], 1e-4)
        wgt = np.clip(den * 40.0, 0, 1)
        acc += est * (wgt * (1 - wacc))[..., None]
        wacc = wacc + wgt * (1 - wacc)
    gain = acc + (1 - wacc)[..., None]
    face_w = 1 - wy  # the correction fades out down the chest, where the fill already continues the armour
    fill = fill * (1 + (gain - 1) * face_w)
    soft = cv2.GaussianBlur(grow.astype(np.float32) / 255.0, (0, 0), 7.0)[..., None]
    clean = (fill * soft + rgb.astype(np.float32) * (1 - soft)).clip(0, 255).astype(np.uint8)
    Image.fromarray(clean).save(OUT / "aim-clean.webp", quality=88, method=6)


    inner = cv2.erode(m, np.ones((3, 3), np.uint8))
    a = cv2.GaussianBlur(inner.astype(np.float32) / 255.0, (0, 0), 1.4)
    gun = np.dstack([rgb, np.clip(a * 255, 0, 255).astype(np.uint8)])
    Image.fromarray(gun, "RGBA").save(OUT / "aim-gun.webp", quality=88, method=6, alpha_quality=95)
    print("aim-clean", (OUT / "aim-clean.webp").stat().st_size, "aim-gun", (OUT / "aim-gun.webp").stat().st_size)


if __name__ == "__main__":
    main()
