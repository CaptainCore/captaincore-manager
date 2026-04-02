/**
 * CaptainCore Manager — Faker / Demo Mode
 *
 * Replaces all real customer data with fake generated data for screenshots.
 * Generates unique canvas-based thumbnails for each site.
 *
 * Usage:
 *   1. Open anchor.localhost/account/ in your browser
 *   2. Wait for sites to fully load
 *   3. Paste this script into the browser console (or load via <script>)
 */

(function() {
    'use strict';

    // ── Word lists for generating fake domains ──────────────────────────

    const adjectives = [
        'bright', 'swift', 'bold', 'clear', 'prime', 'true', 'fair', 'keen',
        'noble', 'grand', 'iron', 'golden', 'silver', 'blue', 'red', 'green',
        'north', 'south', 'east', 'west', 'alpine', 'coastal', 'urban', 'mesa',
        'summit', 'apex', 'peak', 'core', 'deep', 'vast', 'pure', 'vital',
        'rapid', 'steady', 'solid', 'smart', 'sharp', 'fresh', 'modern', 'classic',
        'stellar', 'lunar', 'solar', 'ember', 'frost', 'stone', 'oak', 'cedar',
        'harbor', 'ridge', 'coral', 'canyon', 'brook', 'meadow', 'grove', 'field'
    ];

    const nouns = [
        'leaf', 'wave', 'forge', 'works', 'labs', 'tech', 'media', 'design',
        'studio', 'craft', 'build', 'stack', 'flow', 'pulse', 'spark', 'bridge',
        'path', 'gate', 'point', 'line', 'hub', 'base', 'dock', 'port',
        'nest', 'hive', 'den', 'lodge', 'camp', 'ranch', 'mill', 'barn',
        'atlas', 'prism', 'vault', 'grid', 'node', 'link', 'wire', 'beam',
        'crest', 'glen', 'vale', 'dale', 'marsh', 'reef', 'bay', 'cove',
        'pike', 'trail', 'arc', 'axis', 'edge', 'mark', 'sign', 'scope'
    ];

    const tlds = ['.com', '.com', '.com', '.com', '.net', '.org', '.co', '.io', '.dev'];
    const wpVersions = ['6.7', '6.7.1', '6.7.2', '6.8', '6.8.1', '6.9', '6.9.1', '6.9.4'];

    // ── Seeded random ───────────────────────────────────────────────────

    let _seed = 42;
    function rng() {
        _seed |= 0; _seed = _seed + 0x6D2B79F5 | 0;
        let t = Math.imul(_seed ^ _seed >>> 15, 1 | _seed);
        t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t;
        return ((t ^ t >>> 14) >>> 0) / 4294967296;
    }

    function randInt(min, max) { return Math.floor(rng() * (max - min + 1)) + min; }
    function pick(arr) { return arr[Math.floor(rng() * arr.length)]; }

    // ── Generate unique fake domain names ───────────────────────────────

    const usedNames = new Set();

    function generateDomain() {
        let name, domain;
        for (let i = 0; i < 200; i++) {
            const adj = pick(adjectives);
            const noun = pick(nouns);
            const tld = pick(tlds);
            name = rng() > 0.5 ? adj + noun : adj + '-' + noun;
            domain = name + tld;
            if (!usedNames.has(domain)) break;
        }
        usedNames.add(domain);
        return { name: domain, slug: name.replace(/[^a-z0-9]/g, '') };
    }

    // ── Canvas thumbnail generator (nautical themed) ──────────────────

    function hashString(str) {
        let h = 0;
        for (let i = 0; i < str.length; i++) {
            h = str.charCodeAt(i) + ((h << 5) - h);
        }
        return h;
    }

    // Nautical color palettes: [bg gradient start, bg gradient end]
    const nauticalPalettes = [
        ['#0a1628', '#1a3a5c'],  // deep ocean midnight
        ['#0d2137', '#2a6496'],  // atlantic blue
        ['#1b2838', '#3d7ea6'],  // steel sea
        ['#0b1a2e', '#1f5f8b'],  // navy depths
        ['#132a13', '#2d6a4f'],  // kelp forest
        ['#1a1a2e', '#16213e'],  // night watch
        ['#2c3e50', '#4ca1af'],  // coastal morning
        ['#0f2027', '#203a43'],  // foggy harbor
        ['#1c1c3c', '#3a539b'],  // twilight waters
        ['#1a2a3a', '#4a90a4'],  // tidal pool
        ['#2b1b17', '#6b3a2a'],  // mahogany deck
        ['#1a2634', '#34687c'],  // stormy seas
    ];

    function drawWaves(ctx, w, h, hash, alpha) {
        ctx.globalAlpha = alpha;
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 1.5;
        const waveCount = 2 + Math.abs((hash >> 3) % 3);
        for (let i = 0; i < waveCount; i++) {
            const baseY = h * 0.4 + i * (h * 0.15) + Math.abs((hash + i * 3001) % 40);
            const amp = 8 + Math.abs((hash + i * 1117) % 15);
            const freq = 0.008 + Math.abs((hash + i * 2237) % 10) * 0.001;
            const phase = Math.abs((hash + i * 5003) % 100) * 0.1;
            ctx.beginPath();
            for (let x = 0; x <= w; x += 3) {
                const y = baseY + Math.sin(x * freq + phase) * amp;
                x === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
            }
            ctx.stroke();
        }
    }

    function drawAnchor(ctx, cx, cy, size, alpha) {
        ctx.globalAlpha = alpha;
        ctx.strokeStyle = '#ffffff';
        ctx.fillStyle = '#ffffff';
        ctx.lineWidth = size * 0.08;
        ctx.lineCap = 'round';

        const s = size;
        // Vertical shaft
        ctx.beginPath();
        ctx.moveTo(cx, cy - s * 0.4);
        ctx.lineTo(cx, cy + s * 0.45);
        ctx.stroke();
        // Ring at top
        ctx.beginPath();
        ctx.arc(cx, cy - s * 0.48, s * 0.1, 0, Math.PI * 2);
        ctx.stroke();
        // Cross bar
        ctx.beginPath();
        ctx.moveTo(cx - s * 0.25, cy - s * 0.15);
        ctx.lineTo(cx + s * 0.25, cy - s * 0.15);
        ctx.stroke();
        // Curved flukes
        ctx.beginPath();
        ctx.moveTo(cx - s * 0.35, cy + s * 0.2);
        ctx.quadraticCurveTo(cx - s * 0.3, cy + s * 0.5, cx, cy + s * 0.45);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(cx + s * 0.35, cy + s * 0.2);
        ctx.quadraticCurveTo(cx + s * 0.3, cy + s * 0.5, cx, cy + s * 0.45);
        ctx.stroke();
    }

    function drawCompass(ctx, cx, cy, size, alpha) {
        ctx.globalAlpha = alpha;
        ctx.strokeStyle = '#ffffff';
        ctx.fillStyle = '#ffffff';
        ctx.lineWidth = 1.5;

        const r = size * 0.45;
        // Outer circle
        ctx.beginPath();
        ctx.arc(cx, cy, r, 0, Math.PI * 2);
        ctx.stroke();
        // Inner circle
        ctx.beginPath();
        ctx.arc(cx, cy, r * 0.15, 0, Math.PI * 2);
        ctx.fill();
        // Cardinal points
        const points = [
            [0, -1],  // N
            [1, 0],   // E
            [0, 1],   // S
            [-1, 0],  // W
        ];
        points.forEach(([dx, dy], i) => {
            const len = i === 0 ? r * 0.9 : r * 0.7; // North is longer
            ctx.beginPath();
            ctx.moveTo(cx, cy);
            ctx.lineTo(cx + dx * len, cy + dy * len);
            ctx.stroke();
            // Diamond tip
            const tipX = cx + dx * len;
            const tipY = cy + dy * len;
            ctx.beginPath();
            ctx.arc(tipX, tipY, 2.5, 0, Math.PI * 2);
            ctx.fill();
        });
        // Diagonal points (smaller)
        const diags = [[1,-1],[1,1],[-1,1],[-1,-1]];
        diags.forEach(([dx, dy]) => {
            const len = r * 0.45;
            const n = Math.sqrt(2);
            ctx.beginPath();
            ctx.moveTo(cx, cy);
            ctx.lineTo(cx + (dx/n) * len, cy + (dy/n) * len);
            ctx.stroke();
        });
    }

    function drawShipWheel(ctx, cx, cy, size, alpha) {
        ctx.globalAlpha = alpha;
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = size * 0.04;
        ctx.lineCap = 'round';

        const r = size * 0.4;
        // Outer ring
        ctx.beginPath();
        ctx.arc(cx, cy, r, 0, Math.PI * 2);
        ctx.stroke();
        // Inner ring
        ctx.beginPath();
        ctx.arc(cx, cy, r * 0.35, 0, Math.PI * 2);
        ctx.stroke();
        // Center dot
        ctx.fillStyle = '#ffffff';
        ctx.beginPath();
        ctx.arc(cx, cy, r * 0.08, 0, Math.PI * 2);
        ctx.fill();
        // 8 spokes with handles
        for (let i = 0; i < 8; i++) {
            const angle = (i / 8) * Math.PI * 2 - Math.PI / 2;
            const cos = Math.cos(angle), sin = Math.sin(angle);
            // Spoke from inner to outer ring
            ctx.beginPath();
            ctx.moveTo(cx + cos * r * 0.35, cy + sin * r * 0.35);
            ctx.lineTo(cx + cos * r, cy + sin * r);
            ctx.stroke();
            // Handle nub extending past outer ring
            ctx.beginPath();
            ctx.moveTo(cx + cos * r, cy + sin * r);
            ctx.lineTo(cx + cos * r * 1.25, cy + sin * r * 1.25);
            ctx.stroke();
            // Handle tip
            ctx.beginPath();
            ctx.arc(cx + cos * r * 1.25, cy + sin * r * 1.25, size * 0.025, 0, Math.PI * 2);
            ctx.fill();
        }
    }

    function drawSailboat(ctx, cx, cy, size, alpha) {
        ctx.globalAlpha = alpha;
        ctx.strokeStyle = '#ffffff';
        ctx.fillStyle = '#ffffff';
        ctx.lineWidth = 1.5;

        const s = size;
        // Hull
        ctx.beginPath();
        ctx.moveTo(cx - s * 0.35, cy + s * 0.15);
        ctx.quadraticCurveTo(cx, cy + s * 0.3, cx + s * 0.4, cy + s * 0.15);
        ctx.lineTo(cx - s * 0.35, cy + s * 0.15);
        ctx.stroke();
        // Mast
        ctx.beginPath();
        ctx.moveTo(cx - s * 0.05, cy + s * 0.15);
        ctx.lineTo(cx - s * 0.05, cy - s * 0.4);
        ctx.stroke();
        // Main sail
        ctx.beginPath();
        ctx.moveTo(cx - s * 0.05, cy - s * 0.38);
        ctx.quadraticCurveTo(cx + s * 0.2, cy - s * 0.1, cx - s * 0.05, cy + s * 0.12);
        ctx.globalAlpha = alpha * 0.3;
        ctx.fill();
        ctx.globalAlpha = alpha;
        ctx.stroke();
        // Jib sail
        ctx.beginPath();
        ctx.moveTo(cx - s * 0.05, cy - s * 0.35);
        ctx.quadraticCurveTo(cx - s * 0.22, cy - s * 0.1, cx - s * 0.05, cy + s * 0.05);
        ctx.globalAlpha = alpha * 0.2;
        ctx.fill();
        ctx.globalAlpha = alpha;
        ctx.stroke();
    }

    function drawStarfield(ctx, w, h, hash, alpha) {
        ctx.globalAlpha = alpha;
        ctx.fillStyle = '#ffffff';
        const count = 15 + Math.abs((hash >> 2) % 20);
        for (let i = 0; i < count; i++) {
            const sh = hash + i * 6131;
            const x = Math.abs((sh * 7) % w);
            const y = Math.abs((sh * 13) % (h * 0.5));
            const r = 0.5 + Math.abs((sh >> 4) % 15) * 0.1;
            ctx.beginPath();
            ctx.arc(x, y, r, 0, Math.PI * 2);
            ctx.fill();
        }
    }

    function drawRope(ctx, w, h, hash, alpha) {
        ctx.globalAlpha = alpha;
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 3;
        ctx.lineCap = 'round';

        const startX = Math.abs((hash >> 2) % (w * 0.3));
        const startY = Math.abs((hash >> 5) % (h * 0.3)) + h * 0.1;

        ctx.beginPath();
        ctx.moveTo(startX, startY);
        const segments = 3 + Math.abs((hash >> 7) % 3);
        let x = startX, y = startY;
        for (let i = 0; i < segments; i++) {
            const sh = hash + i * 4003;
            const cx1 = x + 30 + Math.abs((sh >> 2) % 60);
            const cy1 = y + (sh % 2 === 0 ? -1 : 1) * (20 + Math.abs((sh >> 4) % 30));
            x = x + 50 + Math.abs((sh >> 6) % 80);
            y = y + (sh % 3 === 0 ? 10 : -5);
            ctx.quadraticCurveTo(cx1, cy1, x, y);
        }
        ctx.stroke();
    }

    // Nautical drawing functions, picked per-site
    const nauticalElements = [drawAnchor, drawCompass, drawShipWheel, drawSailboat];

    function generateThumbnail(label, w, h) {
        const c = document.createElement('canvas');
        c.width = w; c.height = h;
        const ctx = c.getContext('2d');
        const hash = hashString(label);

        // Pick a nautical palette
        const palette = nauticalPalettes[Math.abs(hash) % nauticalPalettes.length];
        const g = ctx.createLinearGradient(0, 0, w * 0.3, h);
        g.addColorStop(0, palette[0]);
        g.addColorStop(1, palette[1]);
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, w, h);

        // Stars in the sky area
        drawStarfield(ctx, w, h, hash, 0.25);

        // Waves across the lower portion
        drawWaves(ctx, w, h, hash, 0.12);

        // Main nautical element (centered, large, subtle)
        const mainElement = nauticalElements[Math.abs(hash >> 4) % nauticalElements.length];
        const elX = w * 0.5 + Math.abs((hash >> 6) % 100) - 50;
        const elY = h * 0.42 + Math.abs((hash >> 8) % 40) - 20;
        const elSize = 100 + Math.abs((hash >> 10) % 60);
        mainElement(ctx, elX, elY, elSize, 0.1);

        // Optional second smaller element
        if (Math.abs(hash >> 5) % 3 === 0) {
            const secondEl = nauticalElements[Math.abs(hash >> 9) % nauticalElements.length];
            const x2 = Math.abs((hash >> 11) % (w - 120)) + 60;
            const y2 = Math.abs((hash >> 13) % (h * 0.4)) + 40;
            secondEl(ctx, x2, y2, 50 + Math.abs((hash >> 15) % 30), 0.06);
        }

        // Optional decorative rope
        if (Math.abs(hash >> 7) % 2 === 0) {
            drawRope(ctx, w, h, hash + 9999, 0.06);
        }

        // Fake browser chrome bar
        ctx.globalAlpha = 0.2;
        ctx.fillStyle = '#000000';
        ctx.fillRect(0, 0, w, 28);
        ctx.globalAlpha = 0.35;
        ctx.fillStyle = '#ffffff';
        for (let i = 0; i < 3; i++) {
            ctx.beginPath();
            ctx.arc(16 + i * 16, 14, 4, 0, Math.PI * 2);
            ctx.fill();
        }

        // Fake URL bar
        ctx.globalAlpha = 0.1;
        ctx.fillStyle = '#ffffff';
        const barW = w * 0.4;
        const barX = (w - barW) / 2;
        ctx.beginPath();
        ctx.roundRect(barX, 6, barW, 16, 8);
        ctx.fill();

        // Fake content blocks (like a website layout)
        ctx.globalAlpha = 0.05;
        ctx.fillStyle = '#ffffff';
        // Hero area
        ctx.fillRect(20, 45, w * 0.4, 20);
        // Text lines
        ctx.fillRect(20, 75, w * 0.6, 8);
        ctx.fillRect(20, 89, w * 0.5, 8);
        ctx.fillRect(20, 103, w * 0.45, 8);
        // Sidebar block
        ctx.fillRect(w * 0.7, 45, w * 0.25, 70);

        // Bottom gradient with site name
        ctx.globalAlpha = 1;
        const tg = ctx.createLinearGradient(0, h - 60, 0, h);
        tg.addColorStop(0, 'rgba(0,0,0,0)');
        tg.addColorStop(1, 'rgba(0,0,0,0.55)');
        ctx.fillStyle = tg;
        ctx.fillRect(0, h - 60, w, 60);

        ctx.fillStyle = '#ffffff';
        ctx.font = `bold ${Math.max(12, w / 50)}px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif`;
        ctx.textBaseline = 'bottom';
        ctx.fillText(label, 12, h - 10);

        return c.toDataURL('image/jpeg', 0.85);
    }

    // Cache thumbnails by label
    const thumbCache = {};
    function getThumb(label) {
        if (!thumbCache[label]) thumbCache[label] = generateThumbnail(label, 800, 500);
        return thumbCache[label];
    }

    // ── Marker used in screenshot_base to identify faker images ─────────

    const FAKER_MARKER = '__FAKER__';

    // Map: faker ID -> data URI
    const fakerImages = {};

    // ── Access Vue instance ─────────────────────────────────────────────

    function getVM() {
        const el = document.querySelector('#app');
        if (!el?.__vue_app__?._instance?.proxy) {
            console.error('[Faker] Vue app not found. Is the page fully loaded?');
            return null;
        }
        return el.__vue_app__._instance.proxy;
    }

    // ── Core: replace all site data ─────────────────────────────────────

    function applyFaker() {
        const vm = getVM();
        if (!vm) return;
        if (!vm.sites?.length) {
            console.log('[Faker] No sites loaded yet. Retrying...');
            setTimeout(applyFaker, 500);
            return;
        }

        console.log(`[Faker] Replacing ${vm.sites.length} sites with fake data...`);

        // Install our MutationObserver BEFORE data changes trigger re-render
        installObserver();

        vm.sites.forEach((site) => {
            const fake = generateDomain();
            const fakerId = fake.slug + '_' + site.site_id;

            // Site-level
            site.name = fake.name;
            site.site = fake.slug;
            site.subsites = rng() < 0.08 ? randInt(2, 8) : 0;

            // Environment-level
            if (site.environments) {
                site.environments.forEach(env => {
                    const isStaging = env.environment === 'Staging';
                    env.home_url = isStaging
                        ? `https://stg-${fake.slug}.kinsta.cloud`
                        : `https://${fake.name}`;
                    env.core = pick(wpVersions);
                    env.visits = isStaging ? randInt(0, 500) : randInt(500, 200000);
                    env.storage = randInt(200000000, 12000000000);

                    // Generate thumbnail and register it
                    const thumbLabel = fake.name + (isStaging ? ' (staging)' : '');
                    const thumbId = FAKER_MARKER + fakerId + '_' + env.environment;
                    fakerImages[thumbId] = getThumb(thumbLabel);

                    // Set screenshot_base so v-if="env.screenshot_base" is truthy,
                    // and the constructed URL will contain our FAKER_MARKER for the observer to match
                    env.screenshot_base = thumbId;
                });
            }
        });

        // Force reactivity update
        vm.sites = [...vm.sites];

        console.log('[Faker] Data replaced. Thumbnails will appear momentarily.');

        // Expose helper on window
        window.CaptainCoreFaker = {
            reapply: applyFaker,
            refresh: () => replaceAllImages(),
            vm: vm
        };
    }

    // ── MutationObserver: intercept img src as Vuetify renders them ──────

    let observer = null;

    function installObserver() {
        if (observer) return; // already installed

        observer = new MutationObserver((mutations) => {
            for (const m of mutations) {
                // Attribute change on an img
                if (m.type === 'attributes' && m.attributeName === 'src') {
                    maybeReplaceImg(m.target);
                }
                // New nodes added
                if (m.type === 'childList') {
                    for (const node of m.addedNodes) {
                        if (node.nodeType !== 1) continue;
                        if (node.tagName === 'IMG') maybeReplaceImg(node);
                        const imgs = node.querySelectorAll?.('img');
                        if (imgs) imgs.forEach(maybeReplaceImg);
                    }
                }
            }
        });

        observer.observe(document.querySelector('#app'), {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['src']
        });
    }

    function maybeReplaceImg(img) {
        if (!img || img.tagName !== 'IMG') return;
        const src = img.getAttribute('src') || '';
        if (!src.includes(FAKER_MARKER)) return;

        // Extract the faker ID from the URL
        // URL pattern: .../{screenshot_base}_thumb-{size}.jpg
        // screenshot_base = __FAKER__slugname_123_Production
        const match = src.match(/__FAKER__([^/]+?)_thumb-/);
        if (!match) return;

        const thumbId = FAKER_MARKER + match[1];
        const dataUri = fakerImages[thumbId];
        if (dataUri && img.src !== dataUri) {
            img.src = dataUri;
        }
    }

    // ── Fallback: scan and replace all images on page ────────────────────

    function replaceAllImages() {
        document.querySelectorAll('img').forEach(maybeReplaceImg);
    }

    // ── Entry point ─────────────────────────────────────────────────────

    const vm = getVM();
    if (vm?.sites?.length) {
        applyFaker();
    } else {
        console.log('[Faker] Waiting for sites to load...');
        let attempts = 0;
        const check = setInterval(() => {
            attempts++;
            const vm = getVM();
            if (vm?.sites?.length) {
                clearInterval(check);
                applyFaker();
            } else if (attempts > 60) {
                clearInterval(check);
                console.error('[Faker] Timed out waiting for sites.');
            }
        }, 500);
    }

})();
