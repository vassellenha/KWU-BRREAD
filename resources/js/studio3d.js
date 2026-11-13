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

const SPRINKLE_COLORS = ['#e63946', '#ffd23f', '#3fa34d', '#2f80ed', '#ff8c42', '#8e44ad', '#ff6fae', '#fdfdfd'];

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

export function createStudio3D(host, { textureBase = '/img/studio3d', reducedMotion = false, onInteract } = {}) {
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
    const ice = new THREE.Mesh(iceGeometry, [iceFace, iceSide]);
    const iceGroup = new THREE.Group();
    iceGroup.add(ice);
    const sauce = buildSauce(sauceMaterial);
    const sprinkles = buildSprinkles(iceGeometry);
    iceGroup.add(sauce, sprinkles);

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

    const setMaps = (material, map, normalMap) => {
        material.map = map;
        material.normalMap = normalMap;
        material.needsUpdate = true;
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
    controls.autoRotate = !reducedMotion;
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
        if (reducedMotion) return;
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
    let current = { bread: null, flavor: null, flavorColor: null, sauce: false, sprinkles: false, exploded: false };
    let requestId = 0;

    const layout = (state) => {
        const hasBread = !!state.bread;
        const hasIce = hasBread && !!state.flavor;
        const breadHalf = SLABS.bread.ht;
        const iceHalf = SLABS.ice.ht;
        const sauceOn = hasIce && state.sauce;
        const sprinklesOn = hasIce && state.sprinkles;

        let top = breadHalf;
        let bottom = -breadHalf;
        if (hasIce) {
            const lift = sauceOn ? 0.03 : sprinklesOn ? 0.014 : 0;
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
    };

    const setState = async (next) => {
        const id = ++requestId;
        const previous = current;
        const breadKind = next.bread ? (BREAD_TEXTURES[next.bread] ?? 'white') : null;
        const flavorKind = next.flavor ? (FLAVOR_TEXTURES[next.flavor] ?? 'vanilla') : null;

        // Have the textures in hand before a layer appears, so nothing pops in untextured.
        const [breadMaps, iceMaps] = await Promise.all([
            breadKind ? loadBread(breadKind) : null,
            flavorKind ? loadIce(flavorKind) : null,
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
        if (iceMaps) {
            if (iceFace.map !== iceMaps[0]) {
                setMaps(iceFace, iceMaps[0], iceMaps[1]);
                setMaps(iceSide, iceMaps[2], iceMaps[3]);
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

        Object.values(parts).forEach((part) => applyPart(part, dt));
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
        // Renders one frame right now with every animation settled (used by tests and for stills).
        renderStill() {
            Object.values(parts).forEach((part) => {
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
        dispose() {
            disposed = true;
            cancelAnimationFrame(frame);
            clearTimeout(idleTimer);
            resizeObserver.disconnect();
            visibility.disconnect();
            controls.dispose();
            timer.dispose();
            textureCache.forEach((promise) => promise.then((texture) => texture.dispose()).catch(() => {}));
            [breadGeometry, iceGeometry, sprinkles.geometry].forEach((geometry) => geometry.dispose());
            sauce.traverse((child) => child.geometry?.dispose());
            [breadFace, breadCrust, iceFace, iceSide, sauceMaterial, sprinkles.material, floor.material].forEach((m) => m.dispose());
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
