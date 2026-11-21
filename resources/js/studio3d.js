// Brread! Studio — real-time 3D sandwich preview.
//
// Loaded on demand by brreadBuilder() (see app.js), so three.js only ships to the Studio page.
// Everything here is plain three.js state: nothing from this module may be stored on an Alpine
// component, because Alpine's reactive proxies break three's internal identity checks.
//
// The models are built in code from measured slab shapes and wear PBR textures baked from the
// ingredient photos (scripts/build_studio3d_textures.py). To swap in scanned/sculpted .glb
// models later, replace buildSlab() calls in createStudio3D() — the layout, lighting, controls
// and state sync below do not care where the meshes come from.

import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';
import { mergeGeometries } from 'three/addons/utils/BufferGeometryUtils.js';

// Half-width, half-depth, half-thickness, plan corner radius, edge rounding radius (world units).
// Keep hw/hd/rp/re in sync with scripts/build_studio3d_textures.py.
const SLABS = {
    bread: { hw: 1.2, hd: 0.51, ht: 0.1, rp: 0.11, re: 0.034 },
    ice: { hw: 1.08, hd: 0.44, ht: 0.19, rp: 0.06, re: 0.035 },
};

const BREAD_TEXTURES = {
    'Plain White Bread': 'white',
    'Whole Wheat Bread': 'wheat',
};
const FLAVOR_TEXTURES = {
    Vanilla: 'vanilla',
    Strawberry: 'strawberry',
    Chocolate: 'chocolate',
};

// Flavors whose segments and inclusions have to line up over every side of the slab. Each one is a
// single atlas baked by scripts/build_studio3d_flavors.py and drawn with a triplanar lookup in the
// slab's own space, so it needs no UVs and works on any slab whose long axis is x (a folded-bread
// layout included). sss = strength of the faked warm glow at grazing angles.
const PROCEDURAL_FLAVORS = {
    Neapolitan: { slug: 'neapolitan', sss: 0.035, sheen: 0.3 },
    'Durian Musang King': { slug: 'durian-musang-king', sss: 0.055, sheen: 0.38 },
    'Es Teler': { slug: 'es-teler', sss: 0.04, sheen: 0.3 },
    // Matte stone-ground tea cream: a weaker warm glow and less sheen than the fruit flavors.
    Matcha: { slug: 'matcha', sss: 0.03, sheen: 0.22 },
};
const FLAVOR_MAPS = ['albedo.webp', 'surface.webp', 'frost.webp'];

// Keep in sync with ATLAS, ATLAS_PPU, REGIONS and HEIGHT_RANGE in scripts/build_studio3d_flavors.py.
// Region: centre column, centre row (px from the top), half-extents (world units).
const FLAVOR_ATLAS = {
    size: 1024,
    ppu: 420,
    heightRange: 0.012,
    regions: {
        top: [512, 210, 1.12, 0.47],
        front: [512, 514, 1.12, 0.225],
        back: [512, 715, 1.12, 0.225],
        endR: [290, 916, 0.47, 0.225],
        endL: [734, 916, 0.47, 0.225],
    },
};

const SPRINKLE_COLORS =['#e63946', '#ffd23f', '#3fa34d', '#2f80ed', '#ff8c42', '#8e44ad', '#ff6fae', '#fdfdfd'];

// Mini toppings, at 1 mm = MM world units (the slab is about 12 cm long and 2 cm thick). Same palettes
// as scripts/build_studio3d_toppings.py, which renders the flat art of these pieces.
const MM = 0.0175;
const MARSHMALLOW_CREAM = ['#f2e9da', '#f5eee2', '#eee4d2'];
const MARSHMALLOW_PINK = '#f6c6d1';
const GUMMY_COLORS = ['#d8261c', '#f0740f', '#f2b90c', '#3ea52a'];
// Pieces per topping and the radius each one keeps clear around it (world units). Both kinds are drawn
// from one Poisson-disc set, so each spreads evenly on its own and the two never overlap when combined.
const PIECES = {
    marshmallow: { count: 19, radius: 0.092 },
    gummy: { count: 13, radius: 0.088 },
    seed: 7301,
};

const DEFAULT_VIEW = { azimuth: -28, polar: 63 };

// ---------------------------------------------------------------------------------------------
// Deterministic noise (the models must look identical on every visit)
// ---------------------------------------------------------------------------------------------

function mulberry32(seed) {
    let a = seed >>> 0;
    return () => {
        a = (a + 0x6d2b79f5) | 0;
        let t = Math.imul(a ^ (a >>> 15), 1 | a);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

function hash2(ix, iy, seed) {
    let h = Math.imul(ix, 374761393) + Math.imul(iy, 668265263) + Math.imul(seed, 1442695041);
    h = Math.imul(h ^ (h >>> 13), 1274126177);
    return ((h ^ (h >>> 16)) >>> 0) / 4294967296;
}

function valueNoise(x, y, seed) {
    const ix = Math.floor(x);
    const iy = Math.floor(y);
    const fx = x - ix;
    const fy = y - iy;
    const sx = fx * fx * (3 - 2 * fx);
    const sy = fy * fy * (3 - 2 * fy);
    const a = hash2(ix, iy, seed);
    const b = hash2(ix + 1, iy, seed);
    const c = hash2(ix, iy + 1, seed);
    const d = hash2(ix + 1, iy + 1, seed);
    return a + (b - a) * sx + (c - a) * sy + (a - b - c + d) * sx * sy;
}

// Fractal noise centred on zero, roughly in -0.5..0.5.
function fbm(x, y, seed, octaves = 4) {
    let sum = 0;
    let amp = 0.5;
    let total = 0;
    for (let o = 0; o < octaves; o++) {
        sum += (valueNoise(x, y, seed + o * 17) - 0.5) * amp;
        total += amp;
        x *= 2.03;
        y *= 2.03;
        amp *= 0.5;
    }
    return sum / (total * 2) * 2;
}

// ---------------------------------------------------------------------------------------------
// Slab geometry: a rounded-rectangle outline swept through a pill-shaped edge profile.
// Material group 0 = the two big faces, group 1 = the band around the perimeter.
// ---------------------------------------------------------------------------------------------

// Points around a rounded rectangle, each as a skeleton position plus an outward normal, so the
// outline can be offset to any distance without re-sampling.
function sampleOutline({ hw, hd, rp }, edgeX, edgeZ, corner) {
    const a = hw - rp;
    const b = hd - rp;
    const samples = [];
    const edge = (x0, z0, x1, z1, nx, nz, steps) => {
        for (let i = 0; i < steps; i++) {
            const t = i / steps;
            samples.push({ sx: x0 + (x1 - x0) * t, sz: z0 + (z1 - z0) * t, nx, nz });
        }
    };
    const arc = (cx, cz, from, steps) => {
        for (let i = 0; i < steps; i++) {
            const angle = from + (i / steps) * (Math.PI / 2);
            samples.push({ sx: cx, sz: cz, nx: Math.cos(angle), nz: Math.sin(angle) });
        }
    };
    edge(a, -b, a, b, 1, 0, edgeZ);
    arc(a, b, 0, corner);
    edge(a, b, -a, b, 0, 1, edgeX);
    arc(-a, b, Math.PI / 2, corner);
    edge(-a, b, -a, -b, -1, 0, edgeZ);
    arc(-a, -b, Math.PI, corner);
    edge(-a, -b, a, -b, 0, -1, edgeX);
    arc(a, -b, Math.PI * 1.5, corner);

    // Normalised arc length at the outer offset, used as the wrap-around texture coordinate.
    let length = 0;
    const points = samples.map((s) => [s.sx + s.nx * rp, s.sz + s.nz * rp]);
    samples.forEach((s, i) => {
        if (i > 0) length += Math.hypot(points[i][0] - points[i - 1][0], points[i][1] - points[i - 1][1]);
        s.u = length;
    });
    const last = points.length - 1;
    length += Math.hypot(points[0][0] - points[last][0], points[0][1] - points[last][1]);
    samples.forEach((s) => (s.u /= length));
    return samples;
}

function buildSlab(dims, look) {
    const { hw, hd, ht, rp, re } = dims;
    const outline = sampleOutline(dims, look.edgeX, look.edgeZ, look.corner);
    const cols = outline.length;

    // Rows of the cross-section, from the centre of the top face, over the edge, to the centre of the bottom face.
    const rows = [];
    const faceRings = look.faceRings;
    for (let k = 0; k <= faceRings; k++) rows.push({ kind: 'face', s: k / faceRings, o: rp - re, y: ht, side: 0 });
    const profile = [];
    for (let j = 0; j <= look.arc; j++) {
        const phi = (j / look.arc) * (Math.PI / 2);
        profile.push({ o: rp - re + re * Math.sin(phi), y: ht - re + re * Math.cos(phi), side: Math.sin(phi) });
    }
    for (let j = 1; j < look.wall; j++) {
        profile.push({ o: rp, y: (ht - re) * (1 - (2 * j) / look.wall), side: 1 });
    }
    for (let j = 0; j <= look.arc; j++) {
        const phi = Math.PI / 2 + (j / look.arc) * (Math.PI / 2);
        profile.push({ o: rp - re + re * Math.sin(phi), y: -(ht - re) + re * Math.cos(phi), side: Math.sin(phi) });
    }
    let run = 0;
    profile.forEach((p, i) => {
        if (i > 0) run += Math.hypot(p.o - profile[i - 1].o, p.y - profile[i - 1].y);
        p.v = run;
    });
    profile.forEach((p) => rows.push({ kind: 'side', s: 1, o: p.o, y: p.y, side: p.side, v: 1 - p.v / run }));
    for (let k = faceRings; k >= 0; k--) rows.push({ kind: 'face', s: k / faceRings, o: rp - re, y: -ht, side: 0 });

    const stride = cols + 1; // one duplicated column so the wrap-around UV can go 0 → 1
    const positions = new Float32Array(rows.length * stride * 3);
    const uvs = new Float32Array(rows.length * stride * 2);

    rows.forEach((row, r) => {
        for (let c = 0; c <= cols; c++) {
            const sample = outline[c % cols];
            const theta = sample.u * Math.PI * 2;
            const ringX = Math.cos(theta) * look.outlineFreq;
            const ringZ = Math.sin(theta) * look.outlineFreq;

            // Uneven outline (applies to every row, so the slab keeps a consistent silhouette),
            // plus lumps on the perimeter band that fade out towards the faces.
            let offset = row.o + fbm(ringX + 11, ringZ + 5, look.seed + 1, 3) * look.outlineWobble;
            offset += fbm(ringX * look.sideFreq, ringZ * look.sideFreq + row.y * look.sideFreq * 2.2, look.seed + 2, 4) * look.sideLumps * row.side;

            const x = (sample.sx + sample.nx * offset) * row.s;
            const z = (sample.sz + sample.nz * offset) * row.s;

            // Whole-slab warp (both faces move together), plus thickness variation and edge puff.
            let y = row.y;
            y += fbm(x * look.warpFreq + 3, z * look.warpFreq + 9, look.seed + 3, 3) * look.warp;
            y += fbm(x * look.faceFreq + 21, z * look.faceFreq + 4, look.seed + 4 + (row.y > 0 ? 0 : 50), 4) * look.faceLumps * (row.y / ht);
            const edgeDistance = Math.min(hw - Math.abs(x), hd - Math.abs(z));
            y += Math.exp(-edgeDistance / look.puffWidth) * look.puff * (row.y / ht);

            const i = r * stride + c;
            positions[i * 3] = x;
            positions[i * 3 + 1] = y;
            positions[i * 3 + 2] = z;

            if (row.kind === 'face') {
                const fx = ((sample.sx + sample.nx * row.o) * row.s) / (2 * (hw - re)) + 0.5;
                const fz = ((sample.sz + sample.nz * row.o) * row.s) / (2 * (hd - re)) + 0.5;
                uvs[i * 2] = row.y > 0 ? fx : 1 - fx;
                uvs[i * 2 + 1] = 1 - fz;
            } else {
                uvs[i * 2] = c / cols >= 1 ? 1 : sample.u;
                uvs[i * 2 + 1] = row.v;
            }
        }
    });

    const indices = [];
    const groups = [];
    let groupStart = 0;
    let groupKind = rows[0].kind;
    for (let r = 0; r < rows.length - 1; r++) {
        if (rows[r].kind !== rows[r + 1].kind) {
            // Seam between a face and the perimeter band: the two rows coincide, no quads needed.
            groups.push({ start: groupStart, count: indices.length - groupStart, materialIndex: groupKind === 'face' ? 0 : 1 });
            groupStart = indices.length;
            groupKind = rows[r + 1].kind;
            continue;
        }
        for (let c = 0; c < cols; c++) {
            const p0 = r * stride + c;
            const p1 = p0 + 1;
            const p2 = p0 + stride;
            const p3 = p2 + 1;
            indices.push(p0, p1, p2, p1, p3, p2);
        }
    }
    groups.push({ start: groupStart, count: indices.length - groupStart, materialIndex: groupKind === 'face' ? 0 : 1 });

    const geometry = new THREE.BufferGeometry();
    geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
    geometry.setAttribute('uv', new THREE.BufferAttribute(uvs, 2));
    geometry.setIndex(indices);
    groups.forEach((g) => geometry.addGroup(g.start, g.count, g.materialIndex));
    geometry.computeVertexNormals();

    // Vertices duplicated only for their UVs must share one normal, or lighting shows a crease.
    const normals = geometry.attributes.normal;
    const weld = (ids) => {
        const sum = new THREE.Vector3();
        ids.forEach((id) => sum.add(new THREE.Vector3().fromBufferAttribute(normals, id)));
        sum.normalize();
        ids.forEach((id) => normals.setXYZ(id, sum.x, sum.y, sum.z));
    };
    const pairAt = (r, c) => {
        const ids = [r * stride + c];
        if (r > 0 && rows[r - 1].kind !== rows[r].kind) ids.push((r - 1) * stride + c);
        if (r < rows.length - 1 && rows[r + 1].kind !== rows[r].kind) ids.push((r + 1) * stride + c);
        return ids;
    };
    rows.forEach((row, r) => {
        if (row.s === 0) {
            weld(Array.from({ length: stride }, (_, c) => r * stride + c));
            return;
        }
        for (let c = 1; c < cols; c++) {
            const ids = pairAt(r, c);
            if (ids.length > 1) weld(ids);
        }
        weld([...pairAt(r, 0), ...pairAt(r, cols)]);
    });

    geometry.computeBoundingSphere();
    geometry.userData.outline = outline;
    return geometry;
}

const BREAD_LOOK = {
    edgeX: 72, edgeZ: 30, corner: 10, faceRings: 26, arc: 6, wall: 5,
    outlineFreq: 2.4, outlineWobble: 0.024, sideFreq: 3.6, sideLumps: 0.016,
    warpFreq: 0.9, warp: 0.022, faceFreq: 2.6, faceLumps: 0.012,
    puff: 0.012, puffWidth: 0.09, seed: 11,
};
const ICE_LOOK = {
    edgeX: 72, edgeZ: 30, corner: 8, faceRings: 26, arc: 5, wall: 10,
    outlineFreq: 2.8, outlineWobble: 0.02, sideFreq: 4.2, sideLumps: 0.028,
    warpFreq: 0.8, warp: 0.0, faceFreq: 3.4, faceLumps: 0.022,
    puff: -0.012, puffWidth: 0.07, seed: 97,
};

// ---------------------------------------------------------------------------------------------
// Toppings
// ---------------------------------------------------------------------------------------------

function buildSauce(material) {
    const { hw, hd, ht, rp } = SLABS.ice;
    const random = mulberry32(2024);
    const group = new THREE.Group();
    const radius = 0.043;
    const top = ht + radius * 0.5;

    // The drizzle that swings back and forth across the top of the slab.
    const turns = 9;
    const path = [];
    const frontTurns = [];
    const steps = (turns - 1) * 12;
    for (let i = 0; i <= steps; i++) {
        const t = i / steps;
        const phase = t * (turns - 1) * Math.PI;
        // A fattened sine: long diagonal passes with soft, rounded U-turns at the edges.
        const wave = Math.cos(phase);
        const swing = Math.sign(wave) * Math.abs(wave) ** 0.72;
        const x = THREE.MathUtils.lerp(-hw * 0.84, hw * 0.84, t) + Math.sin(phase * 0.37 + 1) * 0.012;
        path.push(new THREE.Vector3(x, 0, swing * hd * 0.8));
    }
    for (let i = 0; i < turns; i++) {
        frontTurns.push({ x: THREE.MathUtils.lerp(-hw * 0.84, hw * 0.84, i / (turns - 1)), front: i % 2 === 0 });
    }
    const curve = new THREE.CatmullRomCurve3(path, false, 'centripetal');
    const ribbon = new THREE.Mesh(new THREE.TubeGeometry(curve, 420, radius, 14, false), material);
    ribbon.scale.y = 0.5; // a poured ribbon settles flatter than a round tube
    ribbon.position.y = top - 0.016;
    group.add(ribbon);

    // Round off the two open ends.
    [path[0], path[path.length - 1]].forEach((end) => {
        const cap = new THREE.Mesh(new THREE.SphereGeometry(radius, 16, 12), material);
        cap.position.set(end.x, top - 0.016, end.z);
        cap.scale.y = 0.5;
        group.add(cap);
    });

    // Drips that run over the edge and down the cut side at each turn.
    frontTurns.forEach(({ x, front }, index) => {
        if (index === 0 || index === turns - 1) return;
        const dir = front ? 1 : -1;
        const length = 0.1 + random() * 0.2;
        const drift = (random() - 0.5) * 0.03;
        const wall = hd + 0.012;
        const dripRadius = 0.024 + random() * 0.006;
        const dripCurve = new THREE.CatmullRomCurve3([
            new THREE.Vector3(x, top - 0.016, dir * (hd * 0.8)),
            new THREE.Vector3(x + drift * 0.3, top - 0.014, dir * (hd - rp * 0.4)),
            new THREE.Vector3(x + drift * 0.6, ht - 0.03, dir * (wall + 0.006)),
            new THREE.Vector3(x + drift, ht - length * 0.6, dir * (wall + 0.01)),
            new THREE.Vector3(x + drift, ht - length, dir * (wall + 0.012)),
        ]);
        group.add(new THREE.Mesh(new THREE.TubeGeometry(dripCurve, 40, dripRadius, 12, false), material));

        const bead = new THREE.Mesh(new THREE.SphereGeometry(dripRadius * 1.45, 18, 14), material);
        bead.position.set(x + drift, ht - length - dripRadius * 0.4, dir * (wall + 0.014));
        bead.scale.set(1, 1.35, 0.8);
        group.add(bead);
    });

    group.traverse((child) => {
        if (child.isMesh) {
            child.castShadow = true;
            child.receiveShadow = true;
        }
    });
    return group;
}

function buildSprinkles(iceGeometry) {
    const { hw, hd, ht, rp } = SLABS.ice;
    const random = mulberry32(555);
    const outline = iceGeometry.userData.outline;
    const onTop = 110;
    const onSides = 44;

    const geometry = new THREE.CapsuleGeometry(0.0135, 0.052, 6, 12);
    const material = new THREE.MeshPhysicalMaterial({ roughness: 0.32, clearcoat: 0.6, clearcoatRoughness: 0.25 });
    const mesh = new THREE.InstancedMesh(geometry, material, onTop + onSides);

    const dummy = new THREE.Object3D();
    const axis = new THREE.Vector3();
    const up = new THREE.Vector3(0, 1, 0);
    const color = new THREE.Color();

    for (let i = 0; i < onTop + onSides; i++) {
        if (i < onTop) {
            dummy.position.set((random() * 2 - 1) * (hw - 0.09), ht + 0.012 + random() * 0.008, (random() * 2 - 1) * (hd - 0.07));
            const yaw = random() * Math.PI * 2;
            axis.set(Math.cos(yaw), (random() - 0.5) * 0.35, Math.sin(yaw)).normalize();
        } else {
            // Pressed into the cut sides, lying flat against them.
            const sample = outline[Math.floor(random() * outline.length)];
            const offset = rp + 0.003;
            dummy.position.set(sample.sx + sample.nx * offset, (random() * 2 - 1) * (ht - 0.07), sample.sz + sample.nz * offset);
            const lean = random() * Math.PI * 2;
            axis.set(-sample.nz * Math.cos(lean), Math.sin(lean), sample.nx * Math.cos(lean)).normalize();
        }
        dummy.quaternion.setFromUnitVectors(up, axis);
        const size = 0.85 + random() * 0.4;
        dummy.scale.set(size, size, size);
        dummy.updateMatrix();
        mesh.setMatrixAt(i, dummy.matrix);
        mesh.setColorAt(i, color.set(SPRINKLE_COLORS[Math.floor(random() * SPRINKLE_COLORS.length)]));
    }
    mesh.instanceMatrix.needsUpdate = true;
    mesh.instanceColor.needsUpdate = true;
    mesh.castShadow = true;
    mesh.receiveShadow = true;
    return mesh;
}

// ---------------------------------------------------------------------------------------------
// Mini toppings: marshmallows and gummy bears. One InstancedMesh per kind (one draw call each),
// spread over the top with Poisson-disc sampling and seated by casting rays at the slab's real surface,
// so a piece sinks a little into the cream instead of floating above it or cutting through the bread.
// ---------------------------------------------------------------------------------------------

// Dart throwing in turns (one marshmallow, one gummy bear, ...) until every kind has its count or the
// top is full. Each point keeps its own radius clear, so a piece never overlaps its neighbour.
function poissonOnTop(random, kinds) {
    const { hw, hd } = SLABS.ice;
    const points = [];
    const placed = Object.fromEntries(Object.keys(kinds).map((key) => [key, []]));
    const order = Object.keys(kinds);
    for (let tries = 0, turn = 0; tries < 40000; tries++) {
        const open = order.filter((key) => placed[key].length < kinds[key].count);
        if (!open.length) break;
        const key = open[turn % open.length];
        const r = kinds[key].radius;
        const x = (random() * 2 - 1) * (hw - 0.07 - r * 0.6);
        const z = (random() * 2 - 1) * (hd - 0.05 - r * 0.6);
        if (points.every((p) => Math.hypot(p.x - x, p.z - z) >= p.r + r)) {
            const point = { x, z, r };
            points.push(point);
            placed[key].push(point);
            turn++;
        }
    }
    return placed;
}

// Where a ray from above meets the slab top, and the surface normal there (slab space).
function seatOnTop(raycaster, slab, x, z) {
    raycaster.set(new THREE.Vector3(x, 1, z), new THREE.Vector3(0, -1, 0));
    const hit = raycaster.intersectObject(slab, false)[0];
    if (!hit) return { point: new THREE.Vector3(x, SLABS.ice.ht, z), normal: new THREE.Vector3(0, 1, 0) };
    return { point: hit.point, normal: hit.face.normal.clone().normalize() };
}

// A mini marshmallow: a short cylinder with softly rounded, slightly bulging sides (about 10 x 8.7 mm).
function marshmallowGeometry() {
    const radius = 5.0 * MM;
    const half = 4.35 * MM;
    const round = 1.7 * MM;
    const profile = [new THREE.Vector2(0, -half)];
    const arc = (cx, cy, from, to, steps) => {
        for (let i = 0; i <= steps; i++) {
            const a = from + ((to - from) * i) / steps;
            profile.push(new THREE.Vector2(cx + Math.cos(a) * round, cy + Math.sin(a) * round));
        }
    };
    arc(radius - round, -half + round, -Math.PI / 2, 0, 5);
    // The wall bows out a touch in the middle, like a marshmallow that set a little proud.
    for (let i = 1; i < 4; i++) {
        const y = -half + round + ((2 * (half - round)) * i) / 4;
        profile.push(new THREE.Vector2(radius + Math.sin((i / 4) * Math.PI) * 0.25 * MM, y));
    }
    arc(radius - round, half - round, 0, Math.PI / 2, 5);
    profile.push(new THREE.Vector2(0, half));
    const geometry = new THREE.LatheGeometry(profile, 22);
    geometry.userData = { radius, half };
    return geometry;
}

// A gummy bear lying on its back: head, two ears, a body, two arms and two legs (about 12 mm tall,
// 4.4 mm thick), merged into one geometry of under 500 triangles. Local +y runs up the bear, +z out of
// its face, so +z is the side that faces up once it lies on the slab.
function gummyBearGeometry() {
    const parts = [];
    const ellipsoid = (centre, radii, w, h) => {
        const g = new THREE.SphereGeometry(1, w, h);
        g.scale(radii[0] * MM, radii[1] * MM, radii[2] * MM);
        g.translate(centre[0] * MM, centre[1] * MM, centre[2] * MM);
        parts.push(g);
    };
    const limb = (from, to, radius) => {
        const a = new THREE.Vector3(...from).multiplyScalar(MM);
        const b = new THREE.Vector3(...to).multiplyScalar(MM);
        const g = new THREE.CapsuleGeometry(radius * MM, a.distanceTo(b), 2, 6);
        g.scale(1, 1, 0.85);
        g.applyQuaternion(new THREE.Quaternion().setFromUnitVectors(new THREE.Vector3(0, 1, 0), b.clone().sub(a).normalize()));
        g.translate((a.x + b.x) / 2, (a.y + b.y) / 2, (a.z + b.z) / 2);
        parts.push(g);
    };
    // Limbs and ears sink well into the body and head, so the bear reads as one moulded piece.
    ellipsoid([0, -0.9, 0], [2.75, 3.0, 2.15], 8, 7); // body
    ellipsoid([0, 2.85, 0.15], [2.6, 2.3, 2.0], 8, 7); // head
    ellipsoid([-1.75, 4.45, -0.05], [1.05, 1.05, 0.8], 6, 4); // ears
    ellipsoid([1.75, 4.45, -0.05], [1.05, 1.05, 0.8], 6, 4);
    limb([-1.75, 0.5, 0.55], [-2.75, -0.8, 0.85], 0.95); // arms
    limb([1.75, 0.5, 0.55], [2.75, -0.8, 0.85], 0.95);
    ellipsoid([-1.4, -3.35, 0.45], [1.3, 1.4, 1.3], 7, 5); // legs
    ellipsoid([1.4, -3.35, 0.45], [1.3, 1.4, 1.3], 7, 5);
    const geometry = mergeGeometries(parts.map((g) => g.toNonIndexed()));
    parts.forEach((g) => g.dispose());
    geometry.userData = { thickness: 2.2 * MM };
    return geometry;
}

// Lays out the pieces of one topping and fills its InstancedMesh. Returns the per-piece rest matrices
// (for the drop-in animation) and how far the tallest piece rises above the slab top.
function placePieces(mesh, slab, { points, seed, kind }) {
    const { ht } = SLABS.ice;
    const random = mulberry32(seed + 1);
    const raycaster = new THREE.Raycaster();
    const dummy = new THREE.Object3D();
    const up = new THREE.Vector3(0, 1, 0);
    const axis = new THREE.Vector3();
    const color = new THREE.Color();
    const rest = [];
    let rise = 0;
    const { radius = 0, half = 0, thickness = 0 } = mesh.geometry.userData;
    const sink = kind === 'marshmallow' ? 0.3 : 0.25; // share of the piece pressed into the cream

    const place = ({ point, normal }) => {
        const yaw = random() * Math.PI * 2;
        const tangent = new THREE.Vector3(Math.cos(yaw), 0, Math.sin(yaw));
        tangent.sub(normal.clone().multiplyScalar(tangent.dot(normal))).normalize();
        if (tangent.lengthSq() < 1e-6) tangent.set(1, 0, 0);
        const size = 0.9 + random() * 0.2;
        let lift;

        if (kind === 'marshmallow') {
            const standing = random() < 0.38;
            // Standing: the cylinder axis along the surface normal. Lying: across it, rolled to a random yaw.
            axis.copy(standing ? normal : tangent);
            dummy.quaternion.setFromUnitVectors(up, axis);
            if (standing) dummy.quaternion.premultiply(new THREE.Quaternion().setFromAxisAngle(normal, yaw));
            else dummy.quaternion.premultiply(new THREE.Quaternion().setFromAxisAngle(tangent, random() * Math.PI));
            const squish = 0.9 + random() * 0.12;
            dummy.scale.set(size, size * squish, size);
            lift = (standing ? half * squish : radius) * size * (1 - sink);
            const pink = random() < 0.2;
            mesh.setColorAt(rest.length, color.set(pink ? MARSHMALLOW_PINK : MARSHMALLOW_CREAM[Math.floor(random() * MARSHMALLOW_CREAM.length)]));
        } else {
            // Lying on its back, face up, spun to a random direction and rocked a little.
            const basis = new THREE.Matrix4().makeBasis(tangent, normal.clone().cross(tangent).normalize(), normal);
            dummy.quaternion.setFromRotationMatrix(basis);
            dummy.quaternion.multiply(new THREE.Quaternion().setFromEuler(new THREE.Euler((random() - 0.5) * 0.3, (random() - 0.5) * 0.3, 0)));
            dummy.scale.setScalar(size);
            lift = thickness * size * (1 - sink);
            mesh.setColorAt(rest.length, color.set(GUMMY_COLORS[rest.length % GUMMY_COLORS.length]));
        }

        dummy.position.copy(point).addScaledVector(normal, lift);
        dummy.updateMatrix();
        mesh.setMatrixAt(rest.length, dummy.matrix);
        rest.push(dummy.matrix.clone());
        rise = Math.max(rise, dummy.position.y + lift / (1 - sink) - ht);
    };

    points.forEach(({ x, z }) => place(seatOnTop(raycaster, slab, x, z)));

    mesh.count = rest.length;
    mesh.instanceMatrix.needsUpdate = true;
    mesh.instanceColor.needsUpdate = true;
    mesh.castShadow = true;
    mesh.receiveShadow = true;
    return { rest, rise };
}

// Gummy jelly without real transmission (too heavy on phones): a sharp clearcoat, a deep colour at the
// rim and a brighter core where the surface faces the viewer, as if light were glowing through it.
function createJellyMaterial() {
    const material = new THREE.MeshPhysicalMaterial({
        roughness: 0.18, clearcoat: 1, clearcoatRoughness: 0.1, specularIntensity: 1, ior: 1.45,
        sheen: 0.25, sheenRoughness: 0.4, sheenColor: new THREE.Color(0xffffff), envMapIntensity: 1.1,
    });
    material.onBeforeCompile = (shader) => {
        shader.fragmentShader = shader.fragmentShader.replace(
            '#include <emissivemap_fragment>',
            `#include <emissivemap_fragment>
            float jellyFacing = clamp(dot(normal, normalize(vViewPosition)), 0.0, 1.0);
            totalEmissiveRadiance += diffuseColor.rgb * pow(jellyFacing, 1.6) * 0.32;`,
        );
    };
    material.customProgramCacheKey = () => 'brread-jelly';
    return material;
}

function buildMiniToppings(iceGeometry) {
    const slab = new THREE.Mesh(iceGeometry);
    slab.updateMatrixWorld();

    const { marshmallow: marshmallowPoints, gummy: gummyPoints } = poissonOnTop(mulberry32(PIECES.seed), { marshmallow: PIECES.marshmallow, gummy: PIECES.gummy });

    const marshmallowMaterial = new THREE.MeshPhysicalMaterial({
        roughness: 0.68, sheen: 0.42, sheenRoughness: 0.6, sheenColor: new THREE.Color(0xfff6ea), normalScale: new THREE.Vector2(0.7, 0.7),
    });
    const marshmallows = new THREE.InstancedMesh(marshmallowGeometry(), marshmallowMaterial, PIECES.marshmallow.count);
    const marshmallowLayout = placePieces(marshmallows, slab, { points: marshmallowPoints, seed: PIECES.seed + 1, kind: 'marshmallow' });

    const gummies = new THREE.InstancedMesh(gummyBearGeometry(), createJellyMaterial(), PIECES.gummy.count);
    const gummyLayout = placePieces(gummies, slab, { points: gummyPoints, seed: PIECES.seed + 2, kind: 'gummy' });

    marshmallows.userData = marshmallowLayout;
    gummies.userData = gummyLayout;
    return { marshmallows, gummies };
}

// ---------------------------------------------------------------------------------------------
// Procedural flavor material: MeshPhysicalMaterial with its colour, relief, roughness and
// occlusion read from the flavor atlas by position instead of by UV.
// ---------------------------------------------------------------------------------------------

function regionUniform([column, row, halfA, halfB]) {
    const { size, ppu } = FLAVOR_ATLAS;
    // Centre in UV (the texture is flipped on upload, so rows count up from the bottom) and half-extent in UV.
    return new THREE.Vector4(column / size, 1 - row / size, (halfA * ppu) / size, (halfB * ppu) / size);
}

function createFlavorMaterial() {
    const { regions, ppu, size, heightRange } = FLAVOR_ATLAS;
    const uniforms = {
        slabAlbedo: { value: null },
        slabSurface: { value: null },
        slabFrost: { value: null },
        slabDetail: { value: null },
        slabTop: { value: regionUniform(regions.top) },
        slabFront: { value: regionUniform(regions.front) },
        slabBack: { value: regionUniform(regions.back) },
        slabEndR: { value: regionUniform(regions.endR) },
        slabEndL: { value: regionUniform(regions.endL) },
        slabScale: { value: ppu / size },
        slabBump: { value: heightRange },
        slabDetailScale: { value: 3.2 },
        slabDetailBump: { value: 0.003 },
        slabFrostAmount: { value: 0.6 },
        slabSss: { value: 0.06 },
    };

    const material = new THREE.MeshPhysicalMaterial({
        roughness: 0.65,
        sheen: 0.3,
        sheenRoughness: 0.55,
        sheenColor: new THREE.Color(0xffffff),
    });
    material.userData.slab = uniforms;
    material.customProgramCacheKey = () => 'brread-slab-triplanar-1';
    material.onBeforeCompile = (shader) => {
        Object.assign(shader.uniforms, uniforms);

        shader.vertexShader = shader.vertexShader
            .replace('#include <common>', '#include <common>\nvarying vec3 vSlabPos;\nvarying vec3 vSlabNormal;')
            .replace('#include <begin_vertex>', '#include <begin_vertex>\nvSlabPos = position;\nvSlabNormal = normal;');

        shader.fragmentShader = shader.fragmentShader
            .replace(
                '#include <common>',
                /* glsl */ `#include <common>
varying vec3 vSlabPos;
varying vec3 vSlabNormal;
uniform sampler2D slabAlbedo;
uniform sampler2D slabSurface;
uniform sampler2D slabFrost;
uniform sampler2D slabDetail;
uniform vec4 slabTop;
uniform vec4 slabFront;
uniform vec4 slabBack;
uniform vec4 slabEndR;
uniform vec4 slabEndL;
uniform float slabScale;
uniform float slabBump;
uniform float slabDetailScale;
uniform float slabDetailBump;
uniform float slabFrostAmount;
uniform float slabSss;

// A point on one face of the slab (in world units) to its place in the atlas, kept inside its region.
vec2 slabUv( vec4 region, vec2 ab ) {
	return clamp( region.xy + ab * slabScale, region.xy - region.zw, region.xy + region.zw );
}

// Screen-space height slope of one projection (forward differences, as in three's bump map).
vec2 slabSlope( sampler2D map, vec2 uv, float scale ) {
	float h = texture2D( map, uv ).r;
	return vec2( texture2D( map, uv + dFdx( uv ) ).r - h, texture2D( map, uv + dFdy( uv ) ).r - h ) * scale;
}

// Bump Mapping Unparametrized Surfaces on the GPU, Morten S. Mikkelsen. Unlike three's perturbNormalArb the
// position derivatives are not normalised, so heights are in world units and the relief keeps its real depth
// at every zoom level.
vec3 slabPerturb( vec3 surfPos, vec3 surfNorm, vec2 dHdxy, float faceDirection ) {
	vec3 sigmaX = dFdx( surfPos );
	vec3 sigmaY = dFdy( surfPos );
	vec3 r1 = cross( sigmaY, surfNorm );
	vec3 r2 = cross( surfNorm, sigmaX );
	float det = dot( sigmaX, r1 ) * faceDirection;
	vec3 grad = sign( det ) * ( dHdxy.x * r1 + dHdxy.y * r2 );
	return normalize( abs( det ) * surfNorm - grad );
}`,
            )
            .replace(
                '#include <map_fragment>',
                /* glsl */ `
	// Triplanar weights from the slab's own normal: sharp, so a face reads one region and only the
	// rounded edges blend two. The atlas regions are cut from one solid block, so they agree there.
	vec3 slabN = normalize( vSlabNormal );
	vec3 slabW = pow( abs( slabN ), vec3( 6.0 ) );
	slabW /= slabW.x + slabW.y + slabW.z;
	vec3 sp = vSlabPos;
	vec2 abX = slabN.x >= 0.0 ? vec2( - sp.z, sp.y ) : vec2( sp.z, sp.y );
	vec2 abY = vec2( sp.x, - sp.z );
	vec2 abZ = slabN.z >= 0.0 ? vec2( sp.x, sp.y ) : vec2( - sp.x, sp.y );
	vec2 uvX = slabUv( slabN.x >= 0.0 ? slabEndR : slabEndL, abX );
	vec2 uvY = slabUv( slabTop, abY );
	vec2 uvZ = slabUv( slabN.z >= 0.0 ? slabFront : slabBack, abZ );

	vec4 slabAlb = texture2D( slabAlbedo, uvX ) * slabW.x + texture2D( slabAlbedo, uvY ) * slabW.y + texture2D( slabAlbedo, uvZ ) * slabW.z;
	vec4 slabSurf = texture2D( slabSurface, uvX ) * slabW.x + texture2D( slabSurface, uvY ) * slabW.y + texture2D( slabSurface, uvZ ) * slabW.z;
	float slabFrostMask = texture2D( slabFrost, uvX ).r * slabW.x + texture2D( slabFrost, uvY ).r * slabW.y + texture2D( slabFrost, uvZ ).r * slabW.z;
	vec2 slabDH = slabSlope( slabSurface, uvX, slabBump ) * slabW.x + slabSlope( slabSurface, uvY, slabBump ) * slabW.y + slabSlope( slabSurface, uvZ, slabBump ) * slabW.z;
	// Micro ice crystals from a small tiling map at a finer scale, so close-ups stay crisp.
	slabDH += slabSlope( slabDetail, abX * slabDetailScale, slabDetailBump ) * slabW.x
		+ slabSlope( slabDetail, abY * slabDetailScale, slabDetailBump ) * slabW.y
		+ slabSlope( slabDetail, abZ * slabDetailScale, slabDetailBump ) * slabW.z;

	// Rime frost: paler and duller along the rim and over the frozen top.
	vec3 slabColor = mix( slabAlb.rgb, mix( slabAlb.rgb, vec3( 0.9, 0.93, 0.96 ), 0.45 ), slabFrostMask * slabFrostAmount );
	diffuseColor.rgb *= slabColor;`,
            )
            .replace(
                '#include <roughnessmap_fragment>',
                'float roughnessFactor = clamp( slabSurf.a + slabFrostMask * 0.2, 0.06, 1.0 );',
            )
            // Sheen picks up the cream's own colour, so dark flavours stay rich instead of turning grey.
            .replace(
                '#include <lights_physical_fragment>',
                '#include <lights_physical_fragment>\n\t#ifdef USE_SHEEN\n\t\tmaterial.sheenColor *= 0.3 + 0.7 * slabColor;\n\t#endif',
            )
            .replace('#include <normal_fragment_maps>', 'normal = slabPerturb( - vViewPosition, normal, slabDH, faceDirection );')
            .replace(
                '#include <emissivemap_fragment>',
                /* glsl */ `#include <emissivemap_fragment>
	// Faked subsurface scattering: light that went into the cream comes back out warm near the silhouette.
	float slabFresnel = pow( 1.0 - saturate( dot( normal, normalize( vViewPosition ) ) ), 2.5 );
	totalEmissiveRadiance += diffuseColor.rgb * slabSss * ( 0.15 + 1.85 * slabFresnel ) * ( 1.0 - 0.5 * slabFrostMask );`,
            )
            .replace(
                '#include <aomap_fragment>',
                /* glsl */ `#include <aomap_fragment>
	float slabOcclusion = slabAlb.a;
	reflectedLight.indirectDiffuse *= slabOcclusion;
	reflectedLight.directDiffuse *= mix( 1.0, slabOcclusion, 0.35 );
	#if defined( USE_SHEEN )
		sheenSpecularIndirect *= slabOcclusion;
	#endif
	reflectedLight.indirectSpecular *= computeSpecularOcclusion( saturate( dot( geometryNormal, geometryViewDir ) ), slabOcclusion, material.roughness );`,
            );
    };
    return material;
}

// ---------------------------------------------------------------------------------------------
// Small critically-damped-ish spring so layers settle instead of snapping
// ---------------------------------------------------------------------------------------------

class Spring {
    constructor(value, stiffness = 170, damping = 20) {
        this.value = value;
        this.target = value;
        this.velocity = 0;
        this.stiffness = stiffness;
        this.damping = damping;
    }

    step(dt) {
        const force = (this.target - this.value) * this.stiffness - this.velocity * this.damping;
        this.velocity += force * dt;
        this.value += this.velocity * dt;
        return this.value;
    }

    snap(value = this.target) {
        this.value = value;
        this.target = value;
        this.velocity = 0;
    }

    get resting() {
        return Math.abs(this.target - this.value) < 0.0005 && Math.abs(this.velocity) < 0.0005;
    }
}

// ---------------------------------------------------------------------------------------------
// Public entry point
// ---------------------------------------------------------------------------------------------

export function supportsWebGL() {
    try {
        const canvas = document.createElement('canvas');
        return !!(canvas.getContext('webgl2') || canvas.getContext('webgl'));
    } catch {
        return false;
    }
}

// autoRotate: false keeps the camera still until setView() is called (the About page drives it
// from scroll). interactive: false turns dragging off so the canvas never competes with scrolling.
export function createStudio3D(
    host,
    {
        textureBase = '/img/studio3d',
        // Owner textures: { slug: ['albedo.webp', ...] } for files present in flavorOverrideBase/<slug>/.
        flavorOverrides = {},
        flavorOverrideBase = '/img/studio/flavors',
        reducedMotion = false,
        autoRotate = true,
        interactive = true,
        onInteract,
    } = {},
) {
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.setClearColor(0x000000, 0);
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.NeutralToneMapping;
    renderer.toneMappingExposure = 1.02;
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFShadowMap;

    const canvas = renderer.domElement;
    canvas.style.cssText = 'display:block;width:100%;height:100%;outline:none;cursor:grab;touch-action:pan-y';
    canvas.setAttribute('role', 'img');
    canvas.setAttribute('aria-label', '3D preview of your ice cream sandwich. Drag to rotate.');
    host.appendChild(canvas);

    const scene = new THREE.Scene();
    const pmrem = new THREE.PMREMGenerator(renderer);
    const environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
    scene.environment = environment;
    scene.environmentIntensity = 0.82;

    const camera = new THREE.PerspectiveCamera(28, 1, 0.1, 50);

    // Key light for shape and shadows, a warm fill from the opposite side, a cool rim from behind.
    const key = new THREE.DirectionalLight(0xfff4e6, 2.3);
    key.position.set(2.6, 5.2, 3.4);
    key.castShadow = true;
    key.shadow.mapSize.set(2048, 2048);
    key.shadow.camera.left = -2.4;
    key.shadow.camera.right = 2.4;
    key.shadow.camera.top = 2.4;
    key.shadow.camera.bottom = -2.4;
    key.shadow.camera.near = 1;
    key.shadow.camera.far = 14;
    key.shadow.bias = -0.0004;
    key.shadow.normalBias = 0.018;
    key.shadow.radius = 5;
    scene.add(key);
    const fill = new THREE.DirectionalLight(0xffe2d6, 0.55);
    fill.position.set(-3.5, 1.8, 2.2);
    scene.add(fill);
    const rim = new THREE.DirectionalLight(0xdff1ff, 0.7);
    rim.position.set(-1.5, 2.5, -4);
    scene.add(rim);

    // Soft contact shadow under the hovering sandwich. Painted rather than projected, so it stays
    // feathered on every GPU and never shows a shadow-map edge.
    const shadowCanvas = document.createElement('canvas');
    shadowCanvas.width = shadowCanvas.height = 256;
    const shadowContext = shadowCanvas.getContext('2d');
    const gradient = shadowContext.createRadialGradient(128, 128, 0, 128, 128, 128);
    gradient.addColorStop(0, 'rgba(113, 59, 44, 0.42)');
    gradient.addColorStop(0.45, 'rgba(113, 59, 44, 0.2)');
    gradient.addColorStop(1, 'rgba(113, 59, 44, 0)');
    shadowContext.fillStyle = gradient;
    shadowContext.fillRect(0, 0, 256, 256);
    const shadowTexture = new THREE.CanvasTexture(shadowCanvas);
    shadowTexture.colorSpace = THREE.SRGBColorSpace;
    const floor = new THREE.Mesh(
        new THREE.PlaneGeometry(4.3, 2.5),
        new THREE.MeshBasicMaterial({ map: shadowTexture, transparent: true, depthWrite: false, toneMapped: false }),
    );
    floor.rotation.x = -Math.PI / 2;
    floor.position.y = -1.0;
    floor.renderOrder = -1;
    scene.add(floor);

    // --- Materials -------------------------------------------------------------------------------
    const maxAnisotropy = Math.min(8, renderer.capabilities.getMaxAnisotropy());
    const breadFace = new THREE.MeshPhysicalMaterial({
        roughness: 0.95, sheen: 0.3, sheenRoughness: 0.85, sheenColor: new THREE.Color(0xfff1dc),
        normalScale: new THREE.Vector2(2.2, 2.2),
    });
    const breadCrust = new THREE.MeshPhysicalMaterial({
        roughness: 0.86, sheen: 0.15, sheenRoughness: 0.8, sheenColor: new THREE.Color(0xffd9a8),
        normalScale: new THREE.Vector2(1.3, 1.3),
    });
    const iceFace = new THREE.MeshPhysicalMaterial({
        roughness: 0.5, clearcoat: 0.32, clearcoatRoughness: 0.5, sheen: 0.4, sheenRoughness: 0.6,
        normalScale: new THREE.Vector2(1.5, 1.5),
    });
    const iceSide = iceFace.clone();
    const iceMaterials = [iceFace, iceSide];
    const flavorMaterial = createFlavorMaterial();
    const slabUniforms = flavorMaterial.userData.slab;
    // Shown while a procedural flavor's atlas is still on its way: the catalog colour, no relief.
    const flavorFallback = new THREE.MeshPhysicalMaterial({ roughness: 0.62, sheen: 0.3, sheenRoughness: 0.6 });
    const sauceMaterial = new THREE.MeshPhysicalMaterial({
        color: 0x3a1708, roughness: 0.28, clearcoat: 0.7, clearcoatRoughness: 0.1, specularIntensity: 0.6, envMapIntensity: 0.6,
        sheen: 0.5, sheenRoughness: 0.5, sheenColor: new THREE.Color(0x7a3a16),
    });

    // --- Meshes ----------------------------------------------------------------------------------
    const sandwich = new THREE.Group();
    scene.add(sandwich);

    const breadGeometry = buildSlab(SLABS.bread, BREAD_LOOK);
    const breadTop = new THREE.Mesh(breadGeometry, [breadFace, breadCrust]);
    const breadBottom = new THREE.Mesh(breadGeometry, [breadFace, breadCrust]);
    breadBottom.rotation.y = Math.PI; // same slice, turned round, so the pair doesn't look cloned
    breadBottom.rotation.z = Math.PI;

    const iceGeometry = buildSlab(SLABS.ice, ICE_LOOK);
    const ice = new THREE.Mesh(iceGeometry, iceMaterials);
    const iceGroup = new THREE.Group();
    iceGroup.add(ice);
    const sauce = buildSauce(sauceMaterial);
    const sprinkles = buildSprinkles(iceGeometry);
    const { marshmallows, gummies } = buildMiniToppings(iceGeometry);
    iceGroup.add(sauce, sprinkles, marshmallows, gummies);

    [breadTop, breadBottom, ice].forEach((mesh) => {
        mesh.castShadow = true;
        mesh.receiveShadow = true;
    });
    sandwich.add(breadBottom, iceGroup, breadTop);

    const parts = {
        breadTop: { object: breadTop, y: new Spring(0), scale: new Spring(0, 220, 18) },
        breadBottom: { object: breadBottom, y: new Spring(0), scale: new Spring(0, 220, 18) },
        ice: { object: iceGroup, y: new Spring(0), scale: new Spring(0, 220, 17) },
        sauce: { object: sauce, y: null, scale: new Spring(0, 240, 19) },
        sprinkles: { object: sprinkles, y: null, scale: new Spring(0, 240, 19) },
        marshmallows: { object: marshmallows, y: null, scale: new Spring(0, 240, 19), pieces: true },
        gummies: { object: gummies, y: null, scale: new Spring(0, 240, 19), pieces: true },
    };
    Object.values(parts).forEach((part) => (part.object.visible = false));

    // --- Textures (fetched the first time an ingredient is picked, then cached) -------------------
    const loader = new THREE.TextureLoader();
    const textureCache = new Map();
    const loadTexture = (file, { color = false, repeatX = 1 } = {}) => {
        const key = `${file}|${repeatX}`;
        if (!textureCache.has(key)) {
            textureCache.set(
                key,
                loader.loadAsync(`${textureBase}/${file}`).then((texture) => {
                    if (color) texture.colorSpace = THREE.SRGBColorSpace;
                    texture.anisotropy = maxAnisotropy;
                    if (repeatX !== 1) texture.repeat.set(repeatX, 1);
                    texture.wrapS = THREE.RepeatWrapping;
                    return texture;
                }),
            );
        }
        return textureCache.get(key);
    };
    const loadBread = (kind) =>
        Promise.all([
            loadTexture(`bread-${kind}-face.webp`, { color: true }),
            loadTexture(`bread-${kind}-face-n.webp`),
            loadTexture(`bread-${kind}-crust.webp`, { color: true }),
            loadTexture(`bread-${kind}-crust-n.webp`),
        ]);
    const loadIce = (flavor) =>
        Promise.all([
            loadTexture(`ice-${flavor}-face.webp`, { color: true }),
            loadTexture(`ice-${flavor}-face-n.webp`),
            loadTexture(`ice-${flavor}-side.webp`, { color: true, repeatX: 2 }),
            loadTexture(`ice-${flavor}-side-n.webp`, { repeatX: 2 }),
        ]);

    // The marshmallow skin (scripts/build_studio3d_toppings.py): fetched the first time it is picked.
    let marshmallowMaps = null;
    const loadMarshmallow = () =>
        (marshmallowMaps ??= Promise.all([
            loader.loadAsync(`${textureBase}/toppings/marshmallow.webp`),
            loader.loadAsync(`${textureBase}/toppings/marshmallow-n.webp`),
        ]).then(([map, normalMap]) => {
            map.colorSpace = THREE.SRGBColorSpace;
            [map, normalMap].forEach((texture) => {
                texture.wrapS = texture.wrapT = THREE.RepeatWrapping;
                texture.repeat.set(2, 1);
                texture.anisotropy = maxAnisotropy;
            });
            marshmallows.material.map = map;
            marshmallows.material.normalMap = normalMap;
            marshmallows.material.needsUpdate = true;
            return [map, normalMap];
        }));

    const setMaps = (material, map, normalMap) => {
        material.map = map;
        material.normalMap = normalMap;
        material.needsUpdate = true;
    };

    // Procedural flavor atlases: fetched when the flavor is first picked. Only the flavor on screen
    // keeps its textures on the GPU; the others keep their decoded images and upload again if picked.
    const loadFlavorMap = (slug, file) => {
        const own = `${flavorOverrideBase}/${slug}/${file}`;
        const baked = `${textureBase}/flavors/${slug}/${file}`;
        const useOwn = (flavorOverrides[slug] ?? []).includes(file);
        return (useOwn ? loader.loadAsync(own).catch(() => loader.loadAsync(baked)) : loader.loadAsync(baked)).then((texture) => {
            if (file === 'albedo.webp') texture.colorSpace = THREE.SRGBColorSpace;
            texture.anisotropy = maxAnisotropy;
            texture.wrapS = texture.wrapT = THREE.ClampToEdgeWrapping;
            return texture;
        });
    };
    let detailMap = null;
    const loadDetail = () =>
        (detailMap ??= loader.loadAsync(`${textureBase}/flavors/detail.webp`).then((texture) => {
            texture.wrapS = texture.wrapT = THREE.RepeatWrapping;
            texture.anisotropy = maxAnisotropy;
            return texture;
        }));
    const flavorCache = new Map();
    let flavorProgram = null;
    const loadFlavor = (slug) => {
        if (!flavorCache.has(slug)) {
            const maps = Promise.all([...FLAVOR_MAPS.map((file) => loadFlavorMap(slug, file)), loadDetail()]);
            // Compile the shader while the images download, so the first switch does not stall a frame.
            flavorProgram ??= renderer.compileAsync(new THREE.Mesh(iceGeometry, flavorMaterial), camera, scene).catch(() => {});
            const ready = Promise.all([maps, flavorProgram]).then(([[albedo, surface, frost, detail]]) => ({ slug, albedo, surface, frost, detail }));
            ready.catch(() => flavorCache.delete(slug)); // let a later pick try again
            flavorCache.set(slug, ready);
        }
        return flavorCache.get(slug);
    };
    let flavorOnGpu = null;
    const useFlavorMaps = (spec, maps) => {
        [maps.albedo, maps.surface, maps.frost, maps.detail].forEach((texture) => renderer.initTexture(texture)); // upload now, not mid-frame
        slabUniforms.slabAlbedo.value = maps.albedo;
        slabUniforms.slabSurface.value = maps.surface;
        slabUniforms.slabFrost.value = maps.frost;
        slabUniforms.slabDetail.value = maps.detail;
        slabUniforms.slabSss.value = spec.sss;
        flavorMaterial.sheen = spec.sheen;
        if (flavorOnGpu && flavorOnGpu !== maps) [flavorOnGpu.albedo, flavorOnGpu.surface, flavorOnGpu.frost].forEach((t) => t.dispose());
        flavorOnGpu = maps;
        ice.material = flavorMaterial;
    };
    // A procedural flavor shows its catalog colour at once and gains its full surface when the atlas
    // arrives (straight away when cached), instead of holding the whole layer back like the photo flavors.
    const showProceduralFlavor = (spec, color) => {
        const loading = loadFlavor(spec.slug);
        if (ice.material === flavorMaterial && flavorOnGpu?.slug === spec.slug) return;
        flavorFallback.color.set(color || '#f3e9d2');
        ice.material = flavorFallback;
        loading
            .then((maps) => {
                if (PROCEDURAL_FLAVORS[current.flavor] === spec) useFlavorMaps(spec, maps);
            })
            .catch(() => {}); // the plain colour stays: still a usable preview
    };

    // --- Controls --------------------------------------------------------------------------------
    const controls = new OrbitControls(camera, canvas);
    controls.enablePan = false;
    controls.enableZoom = false; // the wheel must keep scrolling the page; zoom has its own buttons
    controls.enableDamping = true;
    controls.dampingFactor = 0.08;
    controls.rotateSpeed = 0.85;
    controls.minPolarAngle = THREE.MathUtils.degToRad(12);
    controls.maxPolarAngle = THREE.MathUtils.degToRad(158);
    controls.autoRotate = autoRotate && !reducedMotion;
    controls.enabled = interactive;
    if (!interactive) canvas.style.pointerEvents = 'none';
    controls.autoRotateSpeed = 1.1;
    canvas.style.touchAction = 'pan-y'; // a vertical swipe still scrolls the page on phones

    let idleTimer = null;
    controls.addEventListener('start', () => {
        canvas.style.cursor = 'grabbing';
        controls.autoRotate = false;
        clearTimeout(idleTimer);
        onInteract?.();
    });
    controls.addEventListener('end', () => {
        canvas.style.cursor = 'grab';
        if (reducedMotion || !autoRotate) return;
        clearTimeout(idleTimer);
        idleTimer = setTimeout(() => (controls.autoRotate = true), 6000);
    });

    let fitDistance = 8;
    let zoom = 1;
    const distance = new Spring(8, 120, 22);
    const offset = new THREE.Vector3();

    const setView = (azimuthDeg, polarDeg) => {
        const azimuth = THREE.MathUtils.degToRad(azimuthDeg);
        const polar = THREE.MathUtils.degToRad(polarDeg);
        camera.position
            .set(Math.sin(polar) * Math.sin(azimuth), Math.cos(polar), Math.sin(polar) * Math.cos(azimuth))
            .multiplyScalar(distance.value)
            .add(controls.target);
        camera.lookAt(controls.target);
        controls.update();
    };

    const resize = () => {
        const width = host.clientWidth;
        const height = host.clientHeight;
        if (!width || !height) return;
        renderer.setSize(width, height, false);
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
        const tanV = Math.tan(THREE.MathUtils.degToRad(camera.fov / 2));
        fitDistance = Math.max(1.55 / (tanV * camera.aspect), 1.32 / tanV);
        distance.target = fitDistance / zoom;
        if (!started) distance.snap();
    };
    let started = false;
    const resizeObserver = new ResizeObserver(resize);
    resizeObserver.observe(host);

    // --- State → layout --------------------------------------------------------------------------
    let current = { bread: null, flavor: null, flavorColor: null, sauce: false, sprinkles: false, marshmallows: false, gummies: false, exploded: false };
    let requestId = 0;

    const layout = (state) => {
        const hasBread = !!state.bread;
        const hasIce = hasBread && !!state.flavor;
        const breadHalf = SLABS.bread.ht;
        const iceHalf = SLABS.ice.ht;
        const sauceOn = hasIce && state.sauce;
        const sprinklesOn = hasIce && state.sprinkles;
        const marshmallowsOn = hasIce && !!state.marshmallows;
        const gummiesOn = hasIce && !!state.gummies;
        // Mini pieces sit on the sauce when both are picked, rather than sinking through it.
        const onSauce = sauceOn ? 0.012 : 0;
        marshmallows.position.y = onSauce;
        gummies.position.y = onSauce;

        let top = breadHalf;
        let bottom = -breadHalf;
        if (hasIce) {
            // The top slice rests on whatever stands highest on the slab.
            const lift = Math.max(
                sauceOn ? 0.03 : 0,
                sprinklesOn ? 0.014 : 0,
                marshmallowsOn ? marshmallows.userData.rise + onSauce - 0.006 : 0,
                gummiesOn ? gummies.userData.rise + onSauce - 0.004 : 0,
            );
            top = iceHalf + breadHalf - 0.012 + lift;
            bottom = -(iceHalf + breadHalf) + 0.012;
        }
        if (state.exploded) {
            top += hasIce ? 0.44 : 0.3;
            bottom -= hasIce ? 0.36 : 0.3;
        }

        parts.breadTop.y.target = top;
        parts.breadBottom.y.target = bottom;
        parts.breadTop.scale.target = hasBread ? 1 : 0;
        parts.breadBottom.scale.target = hasBread ? 1 : 0;
        parts.ice.scale.target = hasIce ? 1 : 0;
        parts.sauce.scale.target = sauceOn ? 1 : 0;
        parts.sprinkles.scale.target = sprinklesOn ? 1 : 0;
        parts.marshmallows.scale.target = marshmallowsOn ? 1 : 0;
        parts.gummies.scale.target = gummiesOn ? 1 : 0;
    };

    const setState = async (next) => {
        const id = ++requestId;
        const previous = current;
        const breadKind = next.bread ? (BREAD_TEXTURES[next.bread] ?? 'white') : null;
        const procedural = next.flavor ? PROCEDURAL_FLAVORS[next.flavor] : null;
        const flavorKind = next.flavor && !procedural ? (FLAVOR_TEXTURES[next.flavor] ?? 'vanilla') : null;

        if (procedural) loadFlavor(procedural.slug).catch(() => {}); // start the download alongside the bread

        // Have the textures in hand before a layer appears, so nothing pops in untextured.
        const [breadMaps, iceMaps] = await Promise.all([
            breadKind ? loadBread(breadKind) : null,
            flavorKind ? loadIce(flavorKind) : null,
            next.marshmallows ? loadMarshmallow().catch(() => null) : null,
        ]);
        if (id !== requestId) return;

        if (breadMaps && breadFace.map !== breadMaps[0]) {
            setMaps(breadFace, breadMaps[0], breadMaps[1]);
            setMaps(breadCrust, breadMaps[2], breadMaps[3]);
            if (previous.bread) {
                parts.breadTop.scale.value = 0.9;
                parts.breadBottom.scale.value = 0.9;
            }
        }
        if (procedural && previous.flavor && previous.flavor !== next.flavor) parts.ice.scale.value = 0.88;
        if (iceMaps) {
            if (iceFace.map !== iceMaps[0] || ice.material !== iceMaterials) {
                setMaps(iceFace, iceMaps[0], iceMaps[1]);
                setMaps(iceSide, iceMaps[2], iceMaps[3]);
                ice.material = iceMaterials;
                if (previous.flavor) parts.ice.scale.value = 0.88;
            }
            // A flavour with no baked texture set borrows vanilla's relief and takes its catalog colour.
            const tint = FLAVOR_TEXTURES[next.flavor] || !next.flavorColor ? '#ffffff' : next.flavorColor;
            iceFace.color.set(tint);
            iceSide.color.set(tint);
        }

        // A layer that is just arriving drops in from above / rises from below.
        if (next.bread && !previous.bread) {
            layout(next);
            parts.breadTop.y.snap(parts.breadTop.y.target + 0.7);
            parts.breadBottom.y.snap(parts.breadBottom.y.target - 0.35);
        }
        current = { ...next };
        layout(current);
        if (procedural) showProceduralFlavor(procedural, next.flavorColor);

        // Mini toppings drop onto the slab one after another; taking them off shrinks them away like the others.
        const hasIce = !!next.bread && !!next.flavor;
        [['marshmallows', !!next.marshmallows && hasIce, !!previous.marshmallows && !!previous.bread && !!previous.flavor], ['gummies', !!next.gummies && hasIce, !!previous.gummies && !!previous.bread && !!previous.flavor]].forEach(
            ([key, on, wasOn]) => {
                if (on && !wasOn) {
                    parts[key].scale.snap(1);
                    if (!reducedMotion && started) startDrop(parts[key]);
                }
            },
        );

        if (reducedMotion || !started) {
            Object.values(parts).forEach((part) => {
                part.scale.snap();
                part.y?.snap();
            });
        }
    };

    // --- Render loop -----------------------------------------------------------------------------
    const timer = new THREE.Timer();
    timer.connect(document);
    let frame = 0;
    let onScreen = true;
    let disposed = false;

    // Drop-in: every piece starts a little above its seat and lands in turn, with a small settle.
    const DROP = { height: 0.42, duration: 0.34, stagger: 0.028 };
    const dropMatrix = new THREE.Matrix4();
    const startDrop = (part) => {
        part.drop = { elapsed: 0 };
        stepDrop(part, 0);
    };
    const stepDrop = (part, dt) => {
        const { rest } = part.object.userData;
        part.drop.elapsed += dt;
        let running = false;
        rest.forEach((matrix, index) => {
            const t = THREE.MathUtils.clamp((part.drop.elapsed - index * DROP.stagger) / DROP.duration, 0, 1);
            if (t < 1) running = true;
            // Falls with gravity, then a short damped bounce on landing.
            const fall = t < 0.72 ? 1 - (t / 0.72) ** 2 : 0;
            const bounce = t >= 0.72 ? Math.sin(((t - 0.72) / 0.28) * Math.PI) * 0.05 : 0;
            dropMatrix.copy(matrix);
            dropMatrix.elements[13] += (fall + bounce) * DROP.height;
            part.object.setMatrixAt(index, dropMatrix);
        });
        part.object.instanceMatrix.needsUpdate = true;
        if (!running) part.drop = null;
    };
    const settleDrop = (part) => {
        if (!part.drop) return;
        part.object.userData.rest.forEach((matrix, index) => part.object.setMatrixAt(index, matrix));
        part.object.instanceMatrix.needsUpdate = true;
        part.drop = null;
    };

    const applyPart = (part, dt) => {
        const scale = Math.max(0, part.scale.step(dt));
        part.object.visible = scale > 0.02;
        if (part.object.visible) part.object.scale.setScalar(scale);
        if (part.y) part.object.position.y = part.y.step(dt);
    };

    const tick = () => {
        if (disposed) return;
        frame = requestAnimationFrame(tick);
        timer.update();
        if (!onScreen || document.hidden) return;
        const dt = Math.min(timer.getDelta(), 1 / 30);

        Object.values(parts).forEach((part) => {
            applyPart(part, dt);
            if (part.drop) stepDrop(part, dt);
        });
        if (!reducedMotion) sandwich.position.y = Math.sin(timer.getElapsed() * 1.1) * 0.022;
        floor.material.opacity = parts.breadBottom.scale.value;
        floor.visible = parts.breadBottom.object.visible;

        controls.update();
        distance.target = fitDistance / zoom;
        distance.step(dt);
        offset.copy(camera.position).sub(controls.target).setLength(distance.value);
        camera.position.copy(controls.target).add(offset);

        renderer.render(scene, camera);
    };

    const visibility = new IntersectionObserver(([entry]) => (onScreen = entry.isIntersecting), { threshold: 0.01 });
    visibility.observe(host);

    resize();
    distance.snap(fitDistance);
    setView(DEFAULT_VIEW.azimuth, DEFAULT_VIEW.polar);
    canvas.addEventListener('dblclick', () => api.resetView());

    const api = {
        async setState(next) {
            await setState(next);
            if (!started) {
                started = true;
                resize();
                tick();
            }
        },
        zoomBy(factor) {
            zoom = THREE.MathUtils.clamp(zoom * factor, 0.8, 1.8);
            onInteract?.();
        },
        resetView() {
            zoom = 1;
            distance.target = fitDistance;
            setView(DEFAULT_VIEW.azimuth, DEFAULT_VIEW.polar);
        },
        setView,
        // Draw calls and GPU resources of the last frame, for performance checks.
        info() {
            const { calls, triangles } = renderer.info.render;
            const { textures, geometries } = renderer.info.memory;
            return {
                calls, triangles, textures, geometries, programs: renderer.info.programs?.length ?? 0,
                pieces: { marshmallows: marshmallows.count, gummies: gummies.count },
                pieceTriangles: Object.fromEntries(
                    [['marshmallow', marshmallows.geometry], ['gummy', gummies.geometry]].map(([key, g]) => [key, (g.index ? g.index.count : g.attributes.position.count) / 3]),
                ),
            };
        },
        // Renders one frame right now with every animation settled (used by tests and for stills).
        renderStill() {
            Object.values(parts).forEach((part) => {
                settleDrop(part);
                part.scale.snap();
                part.y?.snap();
                applyPart(part, 0);
            });
            sandwich.position.y = 0;
            distance.snap(fitDistance / zoom);
            controls.autoRotate = false;
            controls.update();
            offset.copy(camera.position).sub(controls.target).setLength(distance.value);
            camera.position.copy(controls.target).add(offset);
            renderer.render(scene, camera);
        },
        // A still of the settled sandwich as an image, for thumbnails.
        snapshot(type = 'image/webp', quality = 0.9) {
            api.renderStill();
            return canvas.toDataURL(type, quality);
        },
        dispose() {
            disposed = true;
            cancelAnimationFrame(frame);
            clearTimeout(idleTimer);
            resizeObserver.disconnect();
            visibility.disconnect();
            controls.dispose();
            timer.dispose();
            textureCache.forEach((promise) => promise.then((texture) => texture.dispose()).catch(() => {}));
            flavorCache.forEach((promise) =>
                promise.then((maps) => [maps.albedo, maps.surface, maps.frost].forEach((texture) => texture.dispose())).catch(() => {}),
            );
            detailMap?.then((texture) => texture.dispose()).catch(() => {});
            [breadGeometry, iceGeometry, sprinkles.geometry, marshmallows.geometry, gummies.geometry].forEach((geometry) => geometry.dispose());
            marshmallowMaps?.then((maps) => maps.forEach((texture) => texture.dispose())).catch(() => {});
            sauce.traverse((child) => child.geometry?.dispose());
            [breadFace, breadCrust, iceFace, iceSide, flavorMaterial, flavorFallback, sauceMaterial, sprinkles.material, marshmallows.material, gummies.material, floor.material].forEach((m) => m.dispose());
            shadowTexture.dispose();
            floor.geometry.dispose();
            environment.dispose();
            pmrem.dispose();
            renderer.dispose();
            canvas.remove();
        },
    };
    return api;
}
