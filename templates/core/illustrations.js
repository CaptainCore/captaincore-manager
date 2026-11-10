// CaptainCore v3 — illustrations and icons for the hosting screens (mixin).
// Spot illustrations are drawn for this app: one outline weight, fills mixed
// from a hue (--h on the holder) with the surface, so they follow the light
// and dark themes and the brand color. Classes (styled in app.html, .illo):
//   l  outline        t  light tint of --h     t2 stronger tint     a  --h solid
//   f  surface fill   s  ground shadow         la outline in --h    ok status green
//   d  dim fill       w  highlight (surface)
// Templates call this.illo(name, hue) / this.icon(name) as a ref: the SVG is a
// constant from this file, never data, so innerHTML is safe here.

const CC_ILLO = {
  // A two-unit server seen slightly from above: lid, side, two drive trays, lights.
  server: `<svg viewBox="0 0 96 72" aria-hidden="true">
    <ellipse class="s" cx="46" cy="64" rx="36" ry="4.5"/>
    <path class="t2 l" d="M10 18 L20 9 H86 L76 18 Z"/>
    <path class="t l" d="M76 18 L86 9 V50 L76 59 Z"/>
    <rect class="f l" x="10" y="18" width="66" height="20" rx="3.5"/>
    <rect class="f l" x="10" y="39" width="66" height="20" rx="3.5"/>
    <rect class="t" x="15" y="23" width="34" height="10" rx="2"/>
    <rect class="t" x="15" y="44" width="34" height="10" rx="2"/>
    <path class="l" d="M20 25.5v5M25 25.5v5M30 25.5v5M35 25.5v5M40 25.5v5M44 25.5v5M20 46.5v5M25 46.5v5M30 46.5v5M35 46.5v5M40 46.5v5M44 46.5v5"/>
    <circle class="ok" cx="62" cy="28" r="2.4"/><circle class="a" cx="69" cy="28" r="2.4"/>
    <circle class="ok" cx="62" cy="49" r="2.4"/><circle class="d" cx="69" cy="49" r="2.4"/>
    <path class="l" d="M80 22l3-2.7M80 28l3-2.7M80 34l3-2.7"/>
  </svg>`,

  // A rack of servers: the empty state and the page header.
  rack: `<svg viewBox="0 0 96 72" aria-hidden="true">
    <ellipse class="s" cx="48" cy="65" rx="34" ry="4"/>
    <rect class="t l" x="22" y="6" width="52" height="58" rx="5"/>
    <rect class="f l" x="27" y="11" width="42" height="11" rx="2.5"/>
    <rect class="f l" x="27" y="25" width="42" height="11" rx="2.5"/>
    <rect class="f l" x="27" y="39" width="42" height="11" rx="2.5"/>
    <rect class="f l" x="27" y="53" width="42" height="7" rx="2" stroke-dasharray="3 3"/>
    <path class="l" d="M32 16.5h14M32 30.5h14M32 44.5h14"/>
    <circle class="ok" cx="62" cy="16.5" r="2"/><circle class="ok" cx="62" cy="30.5" r="2"/><circle class="a" cx="62" cy="44.5" r="2"/>
    <path class="la" d="M80 18l4-4M83 27h5M80 36l4 4"/>
  </svg>`,

  // A site container: a crate with the site's label on its side.
  container: `<svg viewBox="0 0 96 72" aria-hidden="true">
    <ellipse class="s" cx="48" cy="64" rx="32" ry="4.5"/>
    <path class="t2 l" d="M48 6 L78 18 L48 30 L18 18 Z"/>
    <path class="t l" d="M18 18 V48 L48 60 V30 Z"/>
    <path class="f l" d="M78 18 V48 L48 60 V30 Z"/>
    <path class="la" d="M54 38 L72 30.5 M54 45 L72 37.5 M54 52 L72 44.5"/>
    <path class="f l" d="M24 30 L40 36.5 V45 L24 38.5 Z"/>
    <path class="l" d="M27.5 35 L36 38.5"/>
    <path class="l" d="M33 12 L63 24"/>
  </svg>`,

  // WordPress: a page made of blocks, with a pen at work on it.
  wordpress: `<svg viewBox="0 0 96 72" aria-hidden="true">
    <ellipse class="s" cx="46" cy="65" rx="32" ry="4"/>
    <rect class="f l" x="14" y="7" width="58" height="52" rx="5"/>
    <path class="t" d="M14.75 12a4.25 4.25 0 0 1 4.25-4.25h48a4.25 4.25 0 0 1 4.25 4.25v4.25h-56.5z"/>
    <path class="l" d="M14 16.5h58"/>
    <circle class="a" cx="20" cy="12" r="1.7"/><circle class="t2" cx="25.5" cy="12" r="1.7"/><circle class="t2" cx="31" cy="12" r="1.7"/>
    <rect class="t2" x="20" y="22" width="24" height="17" rx="2.5"/>
    <path class="l" d="M22 36.5l6.5-6.5 4.5 4.5 3.5-3.5 6 5.5"/>
    <circle class="w" cx="38" cy="27" r="2.3"/>
    <path class="l" d="M49 24h17M49 29.5h17M49 35h11M20 45h46M20 51h30"/>
    <g transform="rotate(32 76 40)">
      <rect class="a l" x="71.5" y="22" width="9" height="26" rx="2.5"/>
      <path class="f l" d="M71.5 48 L76 56 L80.5 48 Z"/>
      <path class="l" d="M71.5 28h9"/>
    </g>
  </svg>`,

  // A realtime app (chat, sockets): a console sending signals.
  realtime: `<svg viewBox="0 0 96 72" aria-hidden="true">
    <ellipse class="s" cx="42" cy="65" rx="30" ry="4"/>
    <rect class="f l" x="10" y="15" width="52" height="44" rx="5"/>
    <path class="t" d="M10.75 20a4.25 4.25 0 0 1 4.25-4.25h42a4.25 4.25 0 0 1 4.25 4.25v4.25h-50.5z"/>
    <path class="l" d="M10 24.5h52"/>
    <circle class="a" cx="16" cy="20" r="1.7"/><circle class="t2" cx="21.5" cy="20" r="1.7"/><circle class="t2" cx="27" cy="20" r="1.7"/>
    <path class="la" d="M18 33l7 5.5-7 5.5"/>
    <path class="l" d="M29 45h12"/>
    <rect class="t2" x="18" y="49" width="30" height="4" rx="2"/>
    <circle class="a" cx="74" cy="27" r="3.5"/>
    <path class="la" d="M80 20a10 10 0 0 1 0 14M85 15a17 17 0 0 1 0 24M68 20a10 10 0 0 0 0 14"/>
  </svg>`,

  // Any other app: a console with a gear.
  app: `<svg viewBox="0 0 96 72" aria-hidden="true">
    <ellipse class="s" cx="44" cy="65" rx="30" ry="4"/>
    <rect class="f l" x="10" y="15" width="52" height="44" rx="5"/>
    <path class="t" d="M10.75 20a4.25 4.25 0 0 1 4.25-4.25h42a4.25 4.25 0 0 1 4.25 4.25v4.25h-50.5z"/>
    <path class="l" d="M10 24.5h52"/>
    <circle class="a" cx="16" cy="20" r="1.7"/><circle class="t2" cx="21.5" cy="20" r="1.7"/><circle class="t2" cx="27" cy="20" r="1.7"/>
    <path class="la" d="M18 33l7 5.5-7 5.5"/>
    <path class="l" d="M29 45h12"/>
    <g transform="translate(72 40)">
      <path class="t2 l" d="M-2.5 -14h5l1 4.2 3.8 1.6 3.7-2.3 3.5 3.5-2.3 3.7 1.6 3.8 4.2 1v5l-4.2 1-1.6 3.8 2.3 3.7-3.5 3.5-3.7-2.3-3.8 1.6-1 4.2h-5l-1-4.2-3.8-1.6-3.7 2.3-3.5-3.5 2.3-3.7-1.6-3.8-4.2-1v-5l4.2-1 1.6-3.8-2.3-3.7 3.5-3.5 3.7 2.3 3.8-1.6z"/>
      <circle class="f l" r="4.5"/>
    </g>
  </svg>`,

  // MariaDB: a stack of database discs with activity lights.
  database: `<svg viewBox="0 0 96 72" aria-hidden="true">
    <ellipse class="s" cx="48" cy="65" rx="28" ry="4"/>
    <path class="t l" d="M24 16v38c0 4.4 10.7 8 24 8s24-3.6 24-8V16"/>
    <path class="l" d="M24 29c0 4.4 10.7 8 24 8s24-3.6 24-8M24 42c0 4.4 10.7 8 24 8s24-3.6 24-8"/>
    <ellipse class="t2 l" cx="48" cy="16" rx="24" ry="8"/>
    <ellipse class="w" cx="42" cy="14.5" rx="9" ry="2.4"/>
    <circle class="a" cx="31" cy="35" r="2"/><circle class="ok" cx="37" cy="36.6" r="2"/>
    <circle class="a" cx="31" cy="48" r="2"/><circle class="d" cx="37" cy="49.6" r="2"/>
  </svg>`,

  // The edge: a shield with a lock, traffic passing through it.
  edge: `<svg viewBox="0 0 96 72" aria-hidden="true">
    <ellipse class="s" cx="48" cy="65" rx="26" ry="4"/>
    <path class="la" d="M6 34h14M15 29l5 5-5 5"/>
    <path class="la" d="M76 34h14M85 29l5 5-5 5"/>
    <path class="t l" d="M48 6 L70 14 V31 C70 46 60 55 48 60 C36 55 26 46 26 31 V14 Z"/>
    <path class="t2" d="M48 11 L65 17.2 V31 C65 43 57.5 50.4 48 54.6 Z"/>
    <rect class="f l" x="39" y="30" width="18" height="14" rx="3"/>
    <path class="l" d="M42.5 30v-4.5a5.5 5.5 0 0 1 11 0V30"/>
    <circle class="a" cx="48" cy="36" r="2"/><path class="l" d="M48 38v2.5"/>
  </svg>`,

  // Visitors: the world, with a pointer arriving.
  visitor: `<svg viewBox="0 0 96 72" aria-hidden="true">
    <ellipse class="s" cx="44" cy="65" rx="26" ry="4"/>
    <circle class="t l" cx="42" cy="34" r="23"/>
    <path class="t2" d="M28 22c5 1 7 5 5 9s2 7 6 8 3 8-1 12c-7-1-13-6-15-12s-1-12 5-17zM50 14c6 2 11 6 13 12-4 1-7 0-9-3s-5-5-4-9z"/>
    <path class="l" d="M19 34h46M42 11c-8 7-8 39 0 46M42 11c8 7 8 39 0 46M23 22h38M23 46h38"/>
    <path class="a l" d="M60 38 L78 46 L70 48.5 L67 56 Z"/>
  </svg>`,

  // Local backups: snapshots stacked with a clock.
  backups: `<svg viewBox="0 0 96 72" aria-hidden="true">
    <ellipse class="s" cx="44" cy="65" rx="30" ry="4"/>
    <rect class="t l" x="22" y="10" width="44" height="32" rx="4"/>
    <rect class="t2 l" x="17" y="16" width="44" height="32" rx="4"/>
    <rect class="f l" x="12" y="22" width="44" height="34" rx="4"/>
    <path class="l" d="M17 50l9-10 7 7 5-5 12 8"/>
    <circle class="a" cx="44" cy="31" r="3"/>
    <circle class="f l" cx="70" cy="48" r="12"/>
    <path class="la" d="M70 41v7l5 3"/>
  </svg>`
};

// Line icons (24 px grid, currentColor) for section headers and small rows.
const CC_ICON = {
  globe: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c-3 3-3 15 0 18M12 3c3 3 3 15 0 18"/>',
  code: '<path d="M8 7l-5 5 5 5M16 7l5 5-5 5M14 4l-4 16"/>',
  redirect: '<path d="M4 17V11a5 5 0 0 1 5-5h9"/><path d="M15 3l3 3-3 3"/><path d="M4 21h16"/>',
  shield: '<path d="M12 3l7 3v5c0 5-3 8-7 10-4-2-7-5-7-10V6z"/><path d="M9 12l2 2 4-4"/>',
  wrench: '<path d="M14.7 6.3a4.5 4.5 0 0 1 5.6-1.1l-3 3 .5 2.5 2.5.5 3-3a4.5 4.5 0 0 1-6.2 5.4L9 21a2.1 2.1 0 0 1-3-3l7.4-7.4a4.5 4.5 0 0 1 1.3-4.3z"/>',
  layers: '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3 13l9 5 9-5"/>',
  history: '<path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/><path d="M12 8v4l3 2"/>',
  key: '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17 6l2 2M15 8l2 2"/>',
  clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
  route: '<circle cx="6" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="M8.5 6H15a3 3 0 0 1 0 6H9a3 3 0 0 0 0 6h6.5"/>',
  cpu: '<rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/>',
  memory: '<rect x="3" y="7" width="18" height="10" rx="2"/><path d="M7 7v10M11 7v10M15 7v10M5 20v-3M19 20v-3"/>',
  disk: '<ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
  lock: '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
  logs: '<path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
  restart: '<path d="M20 12a8 8 0 1 1-2.3-5.7"/><path d="M20 4v5h-5"/>',
  edit: '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M13 7l4 4"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  apps: '<rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/>',
  arrow: '<path d="M5 12h14M13 6l6 6-6 6"/>',
  external: '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
  terminal: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9l3 3-3 3M12 15h5"/>'
};

// Hues per kind, from the app's accent tokens (so the brand color carries).
const CC_ILLO_HUE = {
  server: 'var(--acc-terminal)', rack: 'var(--acc-terminal)', container: 'var(--brand)', wordpress: 'var(--brand)',
  realtime: 'var(--acc-domains)', app: 'var(--acc-domains)', database: 'var(--acc-reports)', edge: 'var(--acc-billing)',
  visitor: 'var(--brand)', backups: 'var(--brand)'
};

Object.assign(Component.prototype, {
  // ref callback: draws illustration `name` into the element (hue optional).
  illo(name, hue) {
    return el => {
      if (!el) return;
      const key = name + '|' + (hue || '');
      if (el._illo === key) return;
      el._illo = key;
      el.classList.add('illo');
      el.style.setProperty('--h', hue || CC_ILLO_HUE[name] || 'var(--brand)');
      el.innerHTML = CC_ILLO[name] || '';
    };
  },
  // ref callback: a 24 px line icon in currentColor.
  icon(name) {
    return el => {
      if (!el || el._icon === name) return;
      el._icon = name;
      el.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:block;width:100%;height:100%">' + (CC_ICON[name] || '') + '</svg>';
    };
  },
  // Which illustration fits an app: realtime for sockets and chat, else a generic app.
  appIllo(a) {
    const s = ((a && a.command) || '') + ' ' + ((a && a.path) || '') + ' ' + ((a && a.name) || '');
    return /\b(ws|wss|socket|realtime|chat|live)\b|\/ws\b/i.test(s) ? 'realtime' : 'app';
  }
});
