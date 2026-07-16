# Cove v3 — build status & remaining work

Ground-up rebuild of the CaptainCore Manager `/account` UI, served behind `?ui=v3`
(branch in `app/Router.php::load_template`). This is the **hand-maintained source of
truth**; the Claude Design project "Anchor Hosting UI Revamp" (Anchor Home.dc.html,
project `aa0b3f96-96ce-4fd8-bdc2-e5cfb72f64b1`) is now a visual reference only.

Full design brief: `../../captaincore-v2-design-spec.md` (Appendix B is the
"nothing gets lost" completeness contract; §10 is the slice rollout order).

## How it's wired

- **`cove-v3.php`** — thin PHP shell. Redirects logged-out users to the v1 login,
  injects `window.CC_BOOT` (nonce, restRoot, role→dcRole, userFirstName, brandColor,
  path, loginUrl, socket, userEmail), then `readfile()`s the parts below into the DC
  runtime. `$v3_scripts` lists the JS modules concatenated into the one dc-script tag
  (order matters: `app.js` defines `Component`, the rest extend `Component.prototype`).
- **`app.html`** — the DC template markup (visual design).
- **`app.js`** — `class Component extends DCLogic`; `renderVals()` returns the binding
  object, `compute*(s)` build per-route slices. Site detail reads `real = this._detail`
  when it matches the open site, falling back to the design's sample data otherwise.
- **`data.js`** — REST helper `api(path,{method,body})` + fleet hydration (`/sites/`,
  `/accounts/`, `/domains/`) into FLEET/ACCOUNTS/DOMAINS. Sets `this._hydrated`.
- **`jobs.js`** — the job engine. `startJob({label,target,command,dispatch,onFinish})`
  → daemon token → WebSocket `{token,action:"start"}` → plain-text frames → `"Finished."`
  sentinel → `onFinish`. The activity dock renders `activeJob()`.
- **`terminal.js`** — the dock-footer terminal (loads after jobs.js; owns `termRun`).
  Multi-target @ picker (search + multi-select over `FLEET[].environmentsRaw`
  `environment_id`s, falls back to the open site's current env), cookbook popup
  (`GET /recipes` lazily, click inserts `content` into the input — never auto-runs),
  auto-growing textarea (Enter = newline, ⌘⏎ = run; cleared via `this._termEl` ref
  because the DC runtime binds value like defaultValue). Dispatch:
  `POST /run/code { environments: [ids], code }` → one combined streamed job.
- **`home.js`** — home-screen truth. `hydrateHome()` (fired from `componentDidMount`,
  parallel to `hydrate()`) pulls `/activity-logs?per_page=20` (both roles, self-scoped)
  plus `/security-threats` and `/update-queue` (operator only — a 403 in `api()` would
  bounce to login, so gate on `dcRole` before fetching). `realAttention()` builds the
  needs-attention rows; unassigned sites are derived from `FLEET[].unassigned`.
- **`site-detail.js`** — `openSite()` override loads `/sites/{id}/environments|details|
  users`; real overview credentials, env rows, addons, users, logs, env switcher, magic
  login, sync, push/pull. **Env names are LOWERCASE in URL paths.**
- **`stats.js`** — site Stats tab (Fathom). `loadStats()` hits `/sites/{id}/stats`
  (+ the new `/stats/top-pages` & `/stats/top-referrers` routes) with the tracker code
  from the current env's `fathom_analytics[0]`. Chart series is zero-filled client-side
  (Fathom omits empty buckets; labels must byte-match PHP `date('M d Y'/'M Y'/'Y')`).
  Sharing chips POST `/stats/share`; private password auto-saves debounced. No Chart.js
  needed — the design's bar chart renders the series directly.
- **`domains.js`** — Domains/DNS/Email. `openDomain()` override loads `GET /domain/{id}`
  (registrar `provider`, accounts, mailgun `details`) + `GET /dns/{id}` (Constellix zone;
  `no_zone` → Activate flow). DNS edits stage locally, then commit per-record via
  `POST|PUT|DELETE /dns/{domain_id}/records[/{rid}]` — **not** v1's bulk endpoint, whose
  `{id}` is the Constellix `remote_id` (audited trap). Forwarding/Sending tabs lazy-load
  Mailgun routes / sending-domain records; registrar toggles hit `lock_`/`privacy_` routes.
- **`accounts.js`** — Accounts/Users/Access. `openAccount()` loads `GET /accounts/{id}`
  (tier-gated bundle: users w/ levels, pending invites, sites, domains, plan
  limits+usage). Levels map `full-billing→Owner / full / sites-only / domains-only`;
  ownership = `plan.billing_user_id`. Invites POST/DELETE under `/accounts/{id}/invites`;
  member remove DELETE `/accounts/{id}/users/{uid}`. Activity lazy via
  `/activity-logs?account_id=`. Trusted devices have NO REST surface — section hidden.
- **`billing.js`** — Billing (WooCommerce-backed). Lazy `GET /billing/` on first billing
  render → invoices (+PDF blob download, pay-invoice w/ confirm), payment methods
  (set-primary/remove; add needs Stripe elements — hidden), WC billing address.
- **`version-recovery.js`** — Versions/Backups/Snapshots/Timeline (see below).

### Vendored runtime (`../../public/js/v3/`)
`support.js` (DC runtime), `react.production.min.js`, `react-dom.production.min.js`.

### Local test recipe
```bash
P=/Users/austin/Cove/Sites/anchor.localhost/public
VAL=$(wp --path=$P eval 'echo wp_generate_auth_cookie( 3, time()+3600, "logged_in" );')
NAME=$(wp --path=$P eval 'echo LOGGED_IN_COOKIE;')
# Playwright: import from file:///Users/austin/node_modules/playwright/index.mjs,
# launch --ignore-certificate-errors, addCookies({name,value,domain:'anchor.localhost'}),
# goto https://anchor.localhost/account/?ui=v3
```
- Live remote-command test site: **austinginder.com** (site_id 135, host austinginder.kinsta.cloud).
- Go daemon runs locally on `:8000` behind Caddy → `wss://captaincore-api.localhost/ws`.
- `node --check <file>.js` each module + `php -l cove-v3.php` before testing.

## Done (verified live on austinginder.com)

- **Shell** — nav rail, ⌘K palette (searches real fleet + commands), theme toggle,
  brand color from config, greeting/stats from real data, home launcher/pinned counts.
- **Sites list** — real fleet table, label facets, search, provider/backup/core filters.
  (Design's 3 views + filter chrome present.)
- **Site detail** — Overview (real credentials, env rows, domains, shared-with), env
  switcher gated to real environments, Login to WordPress (magic login), manual Sync,
  push/pull staging, Addons (plugins/themes from env JSON, activate/deactivate jobs),
  Users (real, per-user magic login), Logs (real file list + contents).
- **Version & Recovery** — Versions (quicksaves + update-log events merged; detail
  dialog with real component deltas, changed files, git diffs unified/side-by-side,
  rollback whole-site/component/file, Playground sandbox, new quicksave); Backups
  (restic list, browse tree w/ base64url paths, preview, selected download, back-up-now,
  restore=PITR snapshot); Snapshots (real, tokenized 24h links, filtered create);
  Timeline (process logs CRUD + JSON export).
- **Activity dock / terminal** — real streamed jobs, click any job row to view its
  output, collapsed pill shows dot-only when idle, ⌘⏎ to run, red dot on error.
- **Home screen** — Needs-attention feed real (`/security-threats` count + severity +
  affected sites; `/update-queue` pending count when built; unassigned-site count —
  fixed to match v1's rule, `account_id` `""`/`"0"`, the string `"0"` was truthy and
  hid all 27); "all clear" row (excluded from the badge count) when nothing's open.
  Recent-activity feed real from `/activity-logs`. Security launcher tile shows the
  live open-threat count. Home jobs list = real session-dispatched jobs (see gaps).
- **Stats tab** (verified on 1777americanainn.com, tracker DPRQEUGO) — KPI tiles,
  zero-filled pageviews chart, top pages/referrers, grouping (Daily/Monthly/Yearly →
  Fathom day/month/year; no week grouping upstream) + range presets, sharing chips,
  empty state when no tracker. **Includes a backend change**: `captaincore.php` now
  registers `GET /sites/{id}/stats/top-pages|top-referrers` (wires the previously
  dead `Site::top_pages()/top_referrers()`) — functions only, no new class, so no
  composer classmap regen needed on deploy.
- **Idle dock pill** — collapsed dock is a compact circle with a muted dot when no
  jobs run; the pulse pill with count + live tail only shows while jobs stream.
- **Terminal round** (verified live: recipe insert + `wp option get home` streamed
  from austinginder.com) — 780×520 dock; target chip above the input opens the @
  environment picker (fleet-wide multi-select — the terminal no longer requires an
  open site); cookbook popup inserts recipes; multiline auto-growing input; console
  caret only blinks while streaming; input reliably clears after a run.
- **Label facets (Sites list)** — fixed: counter only read each site's first label
  (3 of 6 types were invisible); chips now colored from label metadata (v1 semantics).
- **Accounts / Users / Access** (verified live on the Launch Kits account: 9 users,
  plan usage 3/8 sites · 10.2/20 GB · 562k/1M visits) — account detail on real data:
  users with level labels (Owner row protected), pending invites (send/copy-link/
  revoke wired), sites + domains tabs from the bundle, Plan tab with real usage bars
  and plan facts, Activity tab from account-scoped activity logs. List shows real
  invoice-due status. Not wired: transfer ownership (route exists — needs picker UI),
  admin "Access as", level editing, trusted devices (no REST surface), invite
  send/revoke not live-fired (real emails).
- **Billing** (verified live: real invoice w/ working PDF download, payment-methods
  empty state, real WC billing address) — invoices with status chips + Pay-now
  (confirm → default method), payment methods set-primary/remove, address tab.
  Not wired: add card/ACH (needs Stripe elements — buttons hidden), address edit
  (`PUT /billing/update` exists), invoice line-item detail, admin Subscriptions +
  Pending-ACH views (no design markup yet), My-Plan request-changes. Billing module
  gate (`CAPTAINCORE_CUSTOM_DOMAIN` hides billing in v1) not yet honored in v3.
- **Domains / DNS / Email** (verified live: TXT add/save/delete cycle on the real
  austinginder.com Constellix zone; forwarding + sending real on wpfreighter.com) —
  domain detail on real data across all four tabs. DNS staged editor with per-record
  commit, zone import (staged) / BIND export / activate-zone; Registrar shows real
  nameservers, contacts, lock/privacy toggles (wired, not live-toggled — registrar
  writes), auth-code fetch+copy; Email forwarding activate/list/add/delete + status
  badge; Mailgun sending records + verify + setup + live events. Domain create wired
  to `POST /domains`.

## Remaining work (rough priority; each is a "slice")

Pattern for every slice: audit the v1 REST contract (subagent over `core.php` +
`captaincore.php` + `app/*.php`), write a `<area>.js` mixin, swap the design's mock
arrays in the matching `compute*()` to consult real data with mock fallback, test live.

1. **Security & Site Audits** — `computeSecurity`/`computeAudits` mock. 7 security tabs,
   threat tracking, checksums, coverage, audit queue. Spec §7.8. Admin-gated.
2. **Reports** — `computeReports` mock. Site/account preview/send/schedule. Spec §7.9.
3. **Settings** — `computeSettings` mock. Branding, providers+wizard, defaults, SSH
   keys, cookbook, handbook. Spec §7.10.
4. **Archives (global)** — `computeArchives` mock. Rclone list, store-from-URL with
   EventSource progress, 7-day B2 share links. Spec §7.11.
5. **Profile** — `computeProfile` mock (TFA QR, app password, active sessions).

### Cross-cutting / smaller
- **Sites list gaps** — theme/plugin filter facets and per-site update counts need
  `POST /filters/sites` + `/filters/{name}/versions|statuses` and the update-queue.
  Bulk selection + `/sites/bulk-tools`. The 4 site-create flows (Request, Kinsta
  new/clone, Connect import wizard, Manual). "Select all in filter."
- **Addons** — per-item "update available" badge + per-row Update (needs update-queue);
  the Add dialog (Upload zip / wp.org search / Envato); whole-site update.
- **Realtime depth** — the design has a single dock; v1 also has a **bulk-progress**
  dashboard (`GET /progress` poll), **archive SSE** (`/my-jobs/{token}/stream`), and a
  **fleet process monitor** (`running listen` WS). Not yet built. Also: WS **reconnect +
  reload-resume** (jobs are lost on refresh today; v1 also lacks reconnect).
- **Deep-linking** — routes are in-memory `state.route`; the design does not yet use the
  History API. Spec §8.2 wants every view/tab/filter URL-addressable with back/forward.
  Current `Router.php` rewrite already supports `/account/<route>` paths.
- **Permissions** — no central `can(action, ctx)` yet (spec §8.1). Role gating is via
  `dcRole` operator/customer only. Customer-role screens largely unexercised.

### v1 API contract gaps (found wiring the home screen)
- **Domain expirations aren't exposed.** `Domains::list()` projects only
  `domain_id, remote_id, provider_id, name, status, price` — no expiry field anywhere
  in the v1 REST surface (the v2 SPA references `domain.expiration_date` but it never
  arrives). The design's "domain expires in N days" attention row needs a backend
  change first.
- **`GET /process-logs` is unpaginated** — returns the whole table (~12 MB locally).
  Unusable for a home jobs backfill; the home jobs list stays session-only until the
  endpoint grows `page`/`per_page` (or the home screen uses `GET /progress/` for live
  fleet jobs — see Realtime depth).
- **Customer-role attention is minimal.** The admin-gated signals 403 for customers,
  so they get real activity plus an "all clear"/site-count row. The design's customer
  mock (invoice due, report ready) belongs to the Billing and Reports slices.
- **Domains slice leftovers.** Not wired: Mailgun deploy-to-site (needs a site/env/
  from-name picker — button hidden when real), suppressions view/delete, forwarding
  logs pager, domain→account assignment (admin `PUT /domains/{id}/account`),
  update-site-link, domain delete, DNS-zone delete. Wired but not live-toggled
  (registrar writes on real domains): lock/privacy toggles, contacts save,
  nameservers save. Domains list still can't show account or expiry columns
  (list payload carries neither).
- **Stats tab leftovers.** The "Performance monitor" card is design-only (no v1
  endpoint exists — needs a backend before it can be real). Multi-tracker sites
  (v1 shows a tracker autocomplete when `fathom_analytics.length > 1`) always use
  the first tracker in v3. `/sites/{id}/environments` can take ~8s on the local DB —
  the Stats tab defers its first load until envs arrive (hook in `loadSiteDetail`).

### Known nits
- `/sites` list records carry no theme/plugin/update data, so those Sites-list filters
  render empty until wired to `/filters/sites`.
- Terminal: v1's extras not yet ported — save-input-as-recipe, schedule-command, and
  fullscreen mode. Public recipes insert (v1 runs them immediately with a confirm).
  Fleet envs without a cached `environment_id` (stale sync) don't appear in the @ picker.

## Recent commits
- `89e4ed8` NEW: initial cove-v3 template behind ?ui=v3
- `d954536` IMPROVE: fork into maintainable source (templates/cove-v3/)
- `941ff15` NEW: site detail on real data + live terminal streaming
- `914e10d` NEW: Version & Recovery slice on real data
- `f7a8f2a` IMPROVE: activity dock — selectable job history, ⌘⏎, collapsed pill
- `dbc7662` NEW: home screen on real data — attention + activity feeds
- `bb320e9` IMPROVE: idle dock pill — compact dot-only circle
- `14059da` NEW: Stats tab on real Fathom data + top-pages/referrers routes
- `6fdf9fe` NEW: terminal round — multi-target picker, cookbook, multiline input
- `4aa3402` NEW: Domains/DNS/Email slice on real data
- `7113104` FIX: label facets — count all labels, v1 colors
- `622de8a` NEW: Accounts/Users/Access slice on real data
- `630a5ad` NEW: Billing slice on real data
