#!/usr/bin/env python3
"""
Erzeugt das Hintergrund-Muster aus dem Favicon (assets/brand/favicon-master.png):
Smiley mit Zylinder freistellen, in Graustufen umwandeln und als nahtlose 100×100-Kachel speichern.
Aufruf im Repo-Hauptordner: python3 assets/brand/muster/muster-erzeugen.py   (braucht Pillow und NumPy)
"""
from collections import deque
from pathlib import Path
import numpy as np
from PIL import Image, ImageFilter

HERE = Path(__file__).resolve().parent
src = np.array(Image.open(HERE.parent / 'favicon-master.png').convert('RGBA')).astype(int)
H, W = src.shape[:2]
lum = 0.299 * src[..., 0] + 0.587 * src[..., 1] + 0.114 * src[..., 2]

def flood(mask, starts):
    seen = np.zeros_like(mask); q = deque()
    for y, x in starts:
        if mask[y, x] and not seen[y, x]: seen[y, x] = True; q.append((y, x))
    while q:
        y, x = q.popleft()
        for ny, nx in ((y+1, x), (y-1, x), (y, x+1), (y, x-1)):
            if 0 <= ny < H and 0 <= nx < W and mask[ny, nx] and not seen[ny, nx]: seen[ny, nx] = True; q.append((ny, nx))
    return seen

# 1) Schwarzer Hintergrund = dunkle Pixel, die mit dem Bildrand verbunden sind
border = [(y, x) for x in range(W) for y in (0, H-1)] + [(y, x) for y in range(H) for x in (0, W-1)]
fg = ~flood(lum < 70, border)
# 2) Zusammenhängende Teile; das größte ist das Gesicht, das zweitgrößte der helle Umriss
lab = np.zeros((H, W), int); sizes = {}; n = 0
for y in range(H):
    for x in range(W):
        if fg[y, x] and not lab[y, x]:
            n += 1; part = flood(fg & (lab == 0), [(y, x)]); lab[part] = n; sizes[n] = int(part.sum())
order = [k for k, _ in sorted(sizes.items(), key=lambda t: -t[1])]
face, outline = lab == order[0], lab == order[1]
outline[:, :62] = False                      # Kirche am linken Rand weglassen
# 3) Silhouette: zeilenweise zwischen äußerstem Umrisspunkt links und rechts füllen
sil = np.zeros((H, W), bool)
for y in range(H):
    xs = np.where(outline[y] | face[y])[0]
    if len(xs): sil[y, xs.min():xs.max()+1] = True
fy, fx = np.where(face); cy = (fy.min() + fy.max()) // 2; mx = (fx.min() + fx.max()) // 2
facewide = np.array(Image.fromarray((face * 255).astype('uint8')).filter(ImageFilter.MaxFilter(17))) > 0
sil[205:, :mx] &= facewide[205:, :mx]         # Kirchenrest links unter der Krempe
sil[cy+40:] &= facewide[cy+40:]               # unten rund statt flach
sil[:46] = False                              # Schriftreste oben
g = np.clip(lum, 0, 255).astype('uint8')
img = Image.fromarray(np.dstack([g, g, g, (sil * 255).astype('uint8')]), 'RGBA')
ring = Image.new('RGBA', img.size, (236, 236, 236, 0)); ring.putalpha(img.split()[3].filter(ImageFilter.MaxFilter(7)))
motif = Image.alpha_composite(ring, img); motif = motif.crop(motif.getbbox())

# 4) Kachel 100×100: ein Smiley in der Mitte, ein schräger über die vier Ecken verteilt (nahtlos)
def sized(rot, h):
    r = motif.rotate(rot, resample=Image.BICUBIC, expand=True); r = r.crop(r.getbbox())
    return r.resize((round(r.width * h / r.height), h), Image.LANCZOS)
tile = Image.new('RGBA', (100, 100), (0, 0, 0, 0))
def put(im, cx, cy):
    layer = Image.new('RGBA', (100, 100), (0, 0, 0, 0)); layer.paste(im, (cx - im.width // 2, cy - im.height // 2), im); tile.alpha_composite(layer)
put(sized(0, 44), 50, 50)
for cx in (0, 100):
    for cy in (0, 100): put(sized(-14, 44), cx, cy)
tile.save(HERE / 'muster-smiley-zylinder-100.png')
print('Gespeichert:', HERE / 'muster-smiley-zylinder-100.png')
