# Core v3 — build status & remaining work

Ground-up rebuild of the CaptainCore Manager `/account` UI, served behind `?ui=v3`
(branch in `app/Router.php::load_template`). This is the **hand-maintained source of
truth**; the Claude Design project "Anchor Hosting UI Revamp" (Anchor Home.dc.html,
project `aa0b3f96-96ce-4fd8-bdc2-e5cfb72f64b1`) is now a visual reference only.

Full design brief: `../../captaincore-v2-design-spec.md` (Appendix B is the
"nothing gets lost" completeness contract; §10 is the slice rollout order).

## Design language: Minn Admin (2026-07-19)

The UI was restyled to the Minn Admin design system (Austin's ask, mockup first at
`anchor.localhost/core-v3-minn-mockup.html`). What changed and the rules that follow:

- **Tokens, not markup.** The `:root` / `[data-theme="dark"]` blocks in `app.html`'s
  helmet now carry Minn's palette (from `minn-admin/assets/css/app.css`), keeping the
  ORIGINAL token names (`--paper`/`--panel`/`--panel-2`/`--rule`/`--ink`/`--ink-dim`/
  `--canvas` + `--ok`/`--warn`/`--bad` softs). All 1,500+ inline styles and every
  `app.js`-computed color ride those tokens, so restyling stays a token edit. `--brand`
  is still injected by `applyBrand()` from `CC_BOOT.brandColor`.
- **Fonts are bundled** (Hanken Grotesk + JetBrains Mono variable woff2 in
  `public/fonts/`, @font-face in `core-v3.php`). No Google Fonts requests; do not
  reintroduce external font links.
- **Shell = Minn sidebar + slim topbar.** 240px sidebar: logo tile, ⌘K search button,
  grouped nav (Workspace / Operate / Manage labels), user card pinned bottom
  (`goProfile`, shows `userName` + `userRole`). Topbar: `screenTitle` (route-mapped in
  the shell section of `app.js`) + jobs chip (`runningLabel`, amber, opens dock) +
  activity bell + theme toggle. Static section `<h1>`s were removed (the topbar carries
  the title); detail screens keep their name `<h1>`s.
- **Shell variants retired.** `shellVariant` slim/topnav are no longer wired in the
  markup (sidebar is fixed-width; the old top header is gone). `railWidth`/
  `labelDisplay`/`railJustify`/`showTopNav` still compute in `app.js` but nothing
  consumes them; remove them if they get in the way.
- **Dock is bottom-right always** (`dockSide: 'right'`) so it never overlaps the
  sidebar user card.
- **Tabs are Minn segmented pills** (container: paper bg + rule border + radius 10 +
  padding 3; active tab: `--panel-2` bg + `--ink`). Tab builders emit `fg`/`bg` now,
  NOT `fg`/`line` — new tab groups must follow (accTab/billTab/secTab/setTab/domTab/
  siteTab in app.js, accTab in accounts.js, dlg*/aa* dialog tabs). Site-overview KPI
  strip is Minn stat cards (separate cards, 24px/800 values) and the sidebar shows
  live Sites/Domains counts via `navItem`'s `count`/`countDisplay` (hydration-gated).
- **Segmented controls share the pill language** (Sites Table/Cards, env
  Production/Staging, Addons Plugins/Themes, diff Unified/Side-by-side, AND/OR +
  plugin is/is-not chips): same pill container, active segment `--panel-2` + `--ink`.
  Sites filter facet chips are quiet (sentence case, `font:500 13px`, height 30) —
  no uppercase micro-labels in new chrome.
- **Users page** (`/users` route, Manage nav group, operator-only, 2026-07-19): v1
  parity for fleet user management in users.js — list + client-side filter over
  `GET /users/`, Add dialog (`POST /users`: first/last/email/username + account
  picker; server creates a subscriber with generated password + welcome email),
  Edit dialog (`GET/PUT /users/{id}`; PUT re-assigns `account_ids`, username is
  immutable so the field hides in edit mode), per-row "Access as" link when
  User Switching provides `switch_to_url`. Server-side validation errors from
  the routes render in the dialog. Nav entry is gated on isOp; the routes are
  admin-gated server-side regardless. Switched sessions get a Minn-style amber
  "Switch back to <admin>" pill above the sidebar user card — core-v3.php puts
  the User Switching back-link in CC_BOOT (switch_back_url() output is
  HTML-ESCAPED; it is entity-decoded there or the nonce breaks) with a
  redirect_to back into ?ui=v3. Dialog dropdowns must render IN-FLOW, not
  position:absolute — the dialog body scrolls, so absolute panels clip against
  it and collide with the footer (bit the account picker).
- **Site thumbnails** (2026-07-19, v1 parity): screenshots ride the public B2
  bucket — `CC_BOOT.remoteUploadUri + {site}_{site_id}/{env}/screenshots/
  {screenshot_base}_thumb-{100|800}.jpg` (thumbOf in computeList prefers the
  Production env's base, falls back to any env with one; data.js keeps `site`
  slug on FLEET records). Table rows carry a 48px thumb column, cards a 130px
  hero image; both fall back to a two-letter monogram placeholder when no
  screenshot exists. Card action is "Login to WordPress" now (shared
  `magicLogin(siteId, envLower, user)` in site-detail.js — realMagicLogin
  delegates to it; production env from the list, runJob sample fallback in
  design mode). The card Terminal chip is gone (terminal = topbar/⌃`/palette).
- **Standard list-page header** (2026-07-19): every list route follows the Sites
  pattern — count pill beside the topbar title (route-gated `screenSub` via
  conditional spreads in each compute; later spreads override listVals'
  default), primary action in the topbar (sc-if per route: New site / New
  domain / New account / Add user), search field LEADS the page toolbar
  (250px, height 36, radius 9). No in-page count lines or in-page primary
  buttons on list pages. Applies to Sites, Domains, Accounts, Users, Activity.
- **Sortable table columns** (2026-07-19, Minn pattern): shared `mkSortCols(
  stateKey, cols)` + `sortRows(stateKey, cols, list)` in app.js; per-route sort
  state (`sitesSort`/`domSort`/`accSort`), header cells render via sc-for with
  direction arrows (click toggles asc/desc; numeric-aware localeCompare).
  Sites (name/env count/provider/core/visits), Domains (name/registrar/dns),
  Accounts (name/users/sites/domains/plan/billing-due). NOTE for tests: header
  rows are CSS-uppercased — assert against "REGISTRAR ↑", not "Registrar ↑".
- **Environment pills + List view** (2026-07-19): `openSite(id, env)` takes an
  optional environment (BOTH copies — app.js AND the site-detail.js OVERRIDE;
  the mixin replaces the class method, so signature changes must land in both).
  Table and card env labels are now per-env pills via rows' `envChips`
  (stopPropagation + openSite at that env; falls back to parsing `envs` string
  in design mode). Third Sites view "List" (`view: 'list'`) mirrors v1's
  per-environment listing via rows' `envCards` (env badge, WP chip, home_url
  link, per-env visits/storage from environmentsRaw, per-env `_thumb-800`
  screenshot, Manage site + WP Login per env).
- **Terminal is available to all roles** (2026-07-19): `termShow` is now `true`
  (was `isOp`); customers get the dock console + @ target picker, not just an
  activity feed. SAFE because the server scopes `/run/code`: the callback runs
  `captaincore_verify_permissions($site_id)` PER environment and 403s if no
  owned targets remain, and the client @ picker reads FLEET which `/sites/` has
  already scoped to the caller. The topbar dock button + idle console line are
  no longer role-split (terminal glyph / "$ idle …" for everyone). The
  site-detail header terminal button (`dTerm`) now opens a working console for
  customers too (was the original "terminal not loading the site" report).
- **Intercom chat** (2026-07-19, v1 parity): core-v3.php boots the messenger for
  NON-admin sessions only, when `configurations->intercom_embed_id` is set —
  server-rendered `window.intercomSettings` (name/email/created_at + `user_hash`
  HMAC from User->profile()) + the standard async loader at the end of body.
  The `intercom_secret_key` stays server-side (same rule as the configurations
  REST route). Admin pages ship zero Intercom bytes.
- **Right-click context menus** (2026-07-19, Minn pattern): shared primitive in
  app.js — `openCtxMenu(e, entries)` (viewport-clamped fixed menu + full-screen
  click-catcher, state `ctxMenu`, closed by `go()` on route change) with entries
  built FROM each row's own actions so menu and row can never drift (Minn rule).
  Wired on Users, Sites (table + cards), Domains, Accounts, and home pinned-site
  rows via `onContextMenu` (the DC runtime forwards React events fine). `ctxCopy`
  = clipboard + toast. New list rows should add a `ctx` builder alongside `open`.
- **Activity page** (`/activity` route, Operate nav group, 2026-07-19): full fleet
  event log from `GET /activity-logs?per_page=100` (self-scoped for customers),
  lazy-loaded on first visit via `computeActivityPage`/`loadActivityPage` in home.js.
  The home launcher's Terminal card became the Activity card ("N today" derived from
  the home feed's relTime suffixes); Recent activity's "View all →" routes here. The
  terminal keeps three entry points: topbar icon, ⌃`, and the palette.
- **Dock entry lives in the topbar** (terminal icon button, running dot; jobs chip
  while running). The floating bottom-right pill/circle is GONE (`dockClosed`/
  `dockIdle`/`liveTail` computed values are now unused). Dock opens with the quicker
  `ccpop .12s` animation; close is unmount (instant).
- **Home launcher tiles read as stat cards** (20px/700 meta values, 16px/700 labels),
  home card titles are 16px/700, all paper cards are radius 13. Light sidebar surface
  is pure white (`--panel:#ffffff` light) and the sidebar search field sits on
  `--paper` (Minn's search-btn treatment, both themes). TRAP that shipped once:
  making light `--panel` white made every `style-hover="background:var(--panel)"`
  row hover INVISIBLE (white on white). Row hovers must use `--panel-2` (Minn's
  `--hover` value in both themes) — swept 17 of them 2026-07-19; new rows follow.
- **Buttons: radius 9 regular / 7 small** (primary + ghost normalized). **Focus rings
  are global CSS** in the helmet (`--ring` token, `box-shadow` on `:focus`); borderless
  composed inputs are excluded via `[style*="border-style: none"]` — NOTE the runtime
  serializes `border:none` to longhand `border-style: none`, so both attribute forms
  are in the selector. New borderless inputs must keep `border:none` inline to opt out.

## How it's wired

- **`core-v3.php`** — thin PHP shell. Redirects logged-out users to the v1 login,
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
- **`addons.js`** — the Addons-tab "+ Add" dialog (plugin or theme by `addonKind`).
  Three sources, v1 parity: **Upload** (drag & drop .zip → `upload.php` w/ REST nonce →
  install returned URL; admin-only, tab hidden for customers; the DC runtime has no
  `onDrop` prop so drop/change listeners bind natively via `ref` callbacks),
  **WordPress.org** (`GET /wp-plugins|/wp-themes` passthrough; search + pagination;
  fetches carry a sequence guard — slow local REST let a dialog-open browse response
  land AFTER a search response and clobber it), **Envato** (`GET
  /providers/envato/plugins|themes` cached purchase list, client-side filter; install
  resolves a signed URL via `.../{id}/download`). Installs dispatch `POST /run/code`
  on the current env and chain `realSync` so the list refreshes; wp.org cards show
  Installed/Uninstall when the slug is already in the env JSON. Titles run through
  `aaCleanName` — Minn Admin's keyword-stuffing trimmer — full name in the tooltip.
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
- **`security.js`** — Security (admin) + Site Audits. `GET /security-threats` (track/note/
  resolve), `/security-coverage`, `/checksum-failures`, `/plugin-checksum-failures`;
  `GET /site-audits` list + request/publish/cancel + report view (report_url or /html
  nonce→blob).
- **`reports.js`** — Reports. `POST /report[/account-report]/preview` renders the server
  HTML in an iframe dialog; `/send`; `/default-recipient` prefill; scheduled-reports CRUD.
- **`settings.js`** — Settings (admin). `GET /configurations/` (branding), `/providers`,
  `/defaults/`, `/keys/`, `/recipes/` (Cookbook), `/processes/` (Handbook); save branding
  via `PUT /configurations/global`; provider verify; key delete; recipe→terminal.
- **`archives.js`** — Archives (admin). `GET /archive` rclone list filtered to .zip;
  `POST /archive/share` 7-day B2 link; `POST /archive/store` + `/my-jobs/{token}` poll.
- **`profile.js`** — Profile (self-service). `PUT /me/profile`; TFA via `/me/tfa_*`
  (secret shown for manual entry); app password via `/me/application-password`; sessions
  via `GET/DELETE /sessions`. Initial tfa/name/email from CC_BOOT (`User::profile()`).
- **`router.js`** — deep-linking. `initRouter()` (mount) parses `location` → state +
  popstate listener; `syncUrl()` (in `componentDidUpdate`) pushState's the path when
  route/detail-id/site-tab drift. Routes: `/account/<seg>[/<id>[/<tab>]]`. `?ui=v3`
  preserved during the dev gate; URL re-applied after hydration so deep-linked details
  fetch. **This is what makes v3 production-navigable.**
- **`toast.js`** — `this.toast(text,{kind})` (loading/success/error/info) → dismissable
  pills, bottom-center. `updateToast` flips a loading toast to a result. Background jobs
  and magic login use it; `finishJob` resolves a job's dispatch toast.
- **`sites-filters.js`** — theme/plugin Sites-list filters. `GET /site-filters`
  (fleet-wide option list), pick → `POST /filters/sites` → intersect matched site-ids
  with the fleet. Picking a plugin also loads its Version/Status sub-filter options
  (`GET /filters/<name>/versions/` + `/statuses/`, per-option site counts, sorted
  largest-count first); selections ride the same POST as `versions`/`statuses`, and
  the IS / IS NOT chip maps to `status_mode: include|exclude`. AND/OR + IS chips +
  clears re-run the server filter when hydrated.
- **Filter builder UI** (app.js `facetDefs` + app.html): only ACTIVE facets render
  as chips (label + ▾ + ✕); adding one goes through a "+ Filter" two-level menu
  (category list → searchable options). The active Plugin chip opens a popover
  holding Version list, Status list, IS/IS NOT, and Remove — picks keep the popover
  open for stacking. AND/OR pill only shows with ≥2 active conditions. "Unassigned"
  moved out of the filter row into the Labels row as an operator-only pseudo-label
  (warn-colored chip toggling `fUnassigned`).
- **`version-recovery.js`** — Versions/Backups/Snapshots/Captures/Timeline (see below).
  Captures tab: `loadCaptures()` (GET `/site/{id}/{env}/captures`, cached per env on
  `_detail.caps`) + `computeCaptures()` (history rail w/ show-older paging, per-page
  screenshot cards with broken-image fallback, Overview teaser via env `captures`
  count). Tab sits after Snapshots; deep-links via `/account/sites/{id}/captures`.
- **Invoice detail page** (`billing.js` `openInvoice`/`computeInvoice` + `invoice`
  route): `/account/billing/{order_id}` renders a full-page invoice (line items from
  GET `/invoices/{id}`, WC price HTML flattened to text, PDF download + pay-now).
  Invoice rows on Billing link into it. Router: `invoice` route maps to the billing
  segment; hydrate's deep-link re-apply list includes it; stub whitelist too.
- **RENDER-TIME MIXIN GUARD (convention):** computeList/computeDetail/computeBilling
  run for every screen on every render — any method defined in a LATER mixin file
  must be called guarded (`this.method ? this.method(...) : fallback`), else a render
  that fires mid-script-eval shows "Root.renderVals(): … is not a function" (seen
  once on the user-switching return URL).

### Vendored runtime (`../../public/js/v3/`)
`support.js` (DC runtime), `react.production.min.js`, `react-dom.production.min.js`.

**LOCAL PATCH in support.js** (`createPseudoSheet`, marked with a `LOCAL PATCH`
comment): generated `style-hover`/`style-focus` rules get `!important` appended
to every declaration. Without it a plain `.scpN:hover` class rule loses to the
inline `style=""` that sets the same property, which left every
`style-hover="border-color:var(--brand)"` in app.html silently dead (only
properties absent from the inline style, like box-shadow, ever hovered).
Re-apply the patch if support.js is ever re-vendored from upstream.

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
- `node --check <file>.js` each module + `php -l core-v3.php` before testing.

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
- **Label chip icons** — each Sites-list label filter now shows its mdi icon (captured
  during hydration) as an inline SVG, colored per label type.
- **Security & Site Audits** (admin; verified live: 21 critical threats, coverage 90.4%,
  real core-checksum failures, published anchor.host audits) — Vulnerabilities from
  `/security-threats` (severity/patch chips, affected-site links, notes, track/note/
  resolve, open-in-terminal preselects affected envs); Checksums from `/checksum-failures`
  + `/plugin-checksum-failures`; Coverage from `/security-coverage` (fleet %, per-type
  bars). Site Audits list + view (report_url or /html nonce→blob) + publish/cancel/
  request. Not fired live: track/resolve/publish/request (real side effects).
- **Reports** (verified live: real Maintenance-Report HTML for 1-act.com in the preview
  iframe) — Site/Account preview via `/report[/account-report]/preview`; send; default
  recipient prefill; scheduled-reports list/add/delete. Recipient is singular per contract.
- **Settings** (admin; verified live: all 6 tabs real) — Branding name+swatches
  (`/configurations/`, save via `PUT /configurations/global`), Providers (10 real,
  Verify), Site defaults, SSH keys (real fingerprint, delete), Cookbook (32 recipes,
  Run→terminal), Handbook (146 processes). Not built: provider wizard/import, defaults
  edit, key add flow (private-key mismatch), branding logo upload.
- **Archives** (admin; verified live: 5035 real .zip archives, 6.6 TB on B2) — rclone
  list filtered to .zip, 7-day B2 share link (`/archive/share`), store-from-URL
  (`/archive/store` + `/my-jobs/{token}` poll). No delete (no v1 route); SSE stream
  noted but poll used instead.
- **Profile** (verified live: real name/email/sessions, TFA secret fetched, UA parsing) —
  `PUT /me/profile`; TFA `/me/tfa_*` (secret shown for manual entry — no QR lib vendored);
  app password `/me/application-password[/rotate]`; sessions `GET/DELETE /sessions`.
  `core-v3.php` exposes `tfaEnabled/appPassword/sessions` on CC_BOOT. Not fired live:
  profile save, TFA activate, session revoke (real side effects).

- **UI gap-wiring round** (verified live) — Billing address **Edit** dialog
  (`PUT /billing/update`); **+ Add payment method** → WooCommerce native
  add-payment-method page (Stripe SCA) via `CC_BOOT.addPaymentUrl` (functional on
  prod; the local WC my-account 302-redirects into the portal); **+ New account**
  dialog (`POST /accounts/`); Cookbook **New/Edit recipe** editor
  (`POST|PUT|DELETE /recipes`); Handbook **View** → process HTML in an iframe dialog;
  the header **activity bell** now opens the dock (dot only while jobs run). Hid dead
  no-backend controls on real data: domain **Auto-renew** toggle and account
  **Cancel plan…** (neither has a v1 route).

- **Production-readiness round** (verified live) —
  - **Deep linking** (`router.js`): URL syncs with navigation, back/forward restores the
    screen, and cold deep links (`/account/billing`, `/account/sites/3`,
    `/account/settings`) render correctly. **Spec §8.2 deep-linking is done.**
  - **Theme/plugin filters** now work (`GET /site-filters` + `POST /filters/sites`;
    Kadence theme → 2926→32 sites).
  - **Toast/snackbar feedback** for actions (magic login "Signing in…"; jobs get a
    loading→result toast).
  - **Mini-mode jobs**: Sync/addons/etc. show the collapsed activity pill, not the full
    dock (only the terminal `run` expands it) — customers aren't dropped into the console.
  - **Command palette autofocuses** on open.
  - **Mock-flash fixes**: the terminal console/job list and the billing page no longer
    flash design sample data before hydration.
  - **Providers add/edit** (`POST|PUT|DELETE /providers`, key/value credential editor).
  - **Site defaults** expanded to recipes multi-select + default-users editor.
  - **Add payment method** via embedded **Stripe Elements** (createSource →
    `POST /billing/payment-methods`) — verified add + remove of a test card.
  - `fmtStorage` renders **TB** above 1 TB.

**All spec §7 area slices are now on real data, and §8.2 deep-linking works.** Remaining
work is cross-cutting depth (below) and the deferred per-slice items noted above.

### Gap-wiring round 2 (verified live)
- **Settings › Branding**: theme-color swatches are now native color inputs bound to
  `configurations.colors`; Save branding persists name + colors.
- **Settings › Site defaults**: email/timezone rows have a working Edit dialog
  (`PUT /defaults/global`).
- **Reports**: scheduled-report Edit dialog (interval + recipient → `PUT
  /scheduled-reports/{id}`).
- **Accounts**: Transfer ownership member-picker dialog (`PUT .../users/{uid}/level`
  `full-billing`, owner excluded, confirm) — shown when caller is owner/admin.

### Still-dead controls (need bigger UI or a missing backend)
- **Branding**: logo upload (drop-zone), DNS-copy-labels edit.
- **Site detail**: "Delete site…".
- **Domains**: Mailgun "View all logs →" pager; Mailgun deploy-to-site (needs a
  site/env/from-name picker — real SMTP write).
- **Handbook**: process Edit (`PUT /processes/{id}` proxies to the CLI dispatch server).
- Gated-off-on-real: "Access as" (User Switching plugin — no per-user REST route),
  provider Import wizard, archive Delete (no v1 route).

### Cross-cutting / smaller
- **Sites list gaps** — theme/plugin filter facets and per-site update counts need
  `POST /filters/sites` + `/filters/{name}/versions|statuses` and the update-queue.
  Bulk selection + `/sites/bulk-tools`. The 4 site-create flows (Request, Kinsta
  new/clone, Connect import wizard, Manual). "Select all in filter."
- **Addons** — per-item "update available" badge + per-row Update (needs update-queue);
  whole-site update. (Add dialog is DONE — see addons.js above.)
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

### 2026-07-16 evening round
- **Renamed `templates/cove-v3` → `templates/core-v3`** (and the shell to
  `core-v3.php`; Router branch updated). "Cove" was a typo for "Core".
- **Add plugin/theme dialog** (addons.js — see wiring above). Verified live on
  austinginder.com: hello-dolly installed from wp.org search → job streamed →
  chained sync → row appeared in the real addons list → dialog showed
  Installed/Uninstall → uninstalled → row gone. Envato tab lists real purchases;
  Envato install not live-fired (real signed download). `cove-v3.php` gained
  `uploadUrl` on CC_BOOT.
- **Mock-data flash removed globally.** All 34 design sample class fields
  (FLEET, DOMAINS, THREATS, INVOICES, …) initialize EMPTY when `window.CC_BOOT`
  exists (class-field ternary — samples remain for the DC editor preview); the
  inline home mocks (launcher counts, attention, activity, pinned, statsLine,
  palette, state.jobs seed) are gated the same way ('…'/'Loading fleet…' until
  hydration). `computeDetail/computeAccount/computeDomain` grew empty-fleet stub
  fallbacks (the `|| FLEET[0]` mock-fallback pattern now ends in a blank stub);
  `PREVIEWS.default` needed a `|| []` (was the one render crash). Route sweep
  (all 10 routes + cold site deep link) clean.

### 2026-07-16 late round — site-detail gap fixes (verified live)
- **Domains card**: "Configure →" routes to Domains; each listed domain opens its
  domain detail (`/sites/{id}/details` domains carry `domain_id`).
- **Shared with**: now real — rows from the detail bundle's `shared_with`
  (Owner chip = `site.customer_id`); the Share button opens a v1-parity
  **Share Access dialog** (`GET /sites/{id}/invite-preview` → account/sites/
  domains preview box, `POST /sites/{id}/invite`). Fixes the old mock doShare
  that faked a `grant-access` job in the dock. Gotcha found: the dialog's send
  binding was first named `sendInvite`, which the Accounts slice also returns —
  later spread in renderVals silently overrode it (no-op button). Renamed
  `shareSend`. **Watch for binding-name collisions across compute slices.**
- **Open phpMyAdmin**: `GET /sites/{id}/{env}/phpmyadmin` → signed URL popup
  (Kinsta mysqleditor verified live on 2912). Shown only for kinsta/rocketdotnet.
  Note: a site with empty `provider_site_id` in the local DB 400s (`not_kinsta_site`)
  — data staleness, same in v1 (site 135 locally).
- **Deploy tracking**: push/pull now stays a live dock job. Backend:
  `app/ProviderAction.php` never handled `push_environment` actions (they sat
  "started" forever, v1 included) — added it to `check()`'s Kinsta operations
  poll and gave `run()` a completion handler (done + ActivityLog 'deployed').
  Frontend: `trackProviderOp` polls `/provider-actions/check` every 10s
  (operator-only — role_check gates it), runs "waiting" follow-ups
  (v1 runProviderActions), finishes the job when the action leaves the active
  list, then reloads the site detail. Verified live on 2912: pull
  production→staging tracked ~3.5 min to "Deploy complete".
- `89e4ed8` NEW: initial core-v3 template behind ?ui=v3
- `d954536` IMPROVE: fork into maintainable source (templates/core-v3/)
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
- `8bdf506` IMPROVE: label chips — per-type SVG icons
- `b92fe69` NEW: Security & Site Audits slice on real data
- `df2f0d5` NEW: Reports slice on real data
- `9e52191` NEW: Settings slice on real data
- `23e5938` NEW: Archives slice on real data
- `495355f` NEW: Profile slice on real data
- `19bd60c` IMPROVE: trim table columns (Sites Theme, Domains Expires/Auto-renew)
- `f96279f` NEW: wire up UI gaps — billing edit, payment methods, new account, recipes
- `26a66f4` NEW: Settings — editable brand colors + site-defaults dialog
- `ecb2246` NEW: Reports schedule edit + account transfer ownership

### 2026-07-16 polish round
- **Logs tab**: horizontal chip strip → vertical file list (232px left rail,
  selected row gets brand accent + soft bg; full name in tooltip), log content
  fills the right column.
- **Timeline**: dropped the initials avatar per row (author name column stays).
- **Ghost-dialog fix**: first click on Versions/Backups flashed the quicksave/
  browse dialog — the loading-placeholder rows carry hash/id '' which matched
  the initial `qsDialog: ''` / `bkDialog: ''` state. Dialog gates now require a
  non-empty selection (`s.qsDialog ? find(...) : null`). Pattern to keep:
  never let placeholder rows share the "closed" sentinel value.
- **Logs highlighting + line numbers**: `logSegments()` in site-detail.js — a
  dependency-free tokenizer (one alternation regex: timestamps, access-log
  dates, error/warn/notice severities, quoted strings, IPs, paths) rendered as
  nested sc-for spans; dim right-aligned line-number gutter (placeholder rows
  flagged `ph` and left unnumbered).
- **Timeline markdown**: rows render the server-side Parsedown HTML
  (`description`; same data v1 trusts with v-html) through a `ref`-injected
  innerHTML span (`.cc-md` styles in the head) — the DC runtime has no raw-HTML
  binding, so the ref is the escape hatch. `description_raw` stays the edit
  source; rows without HTML fall back to escaped text.
- **Visual refresh (minimal round)**: new `--canvas` token — the page/app-shell
  background is now a faintly brand-tinted gray (light `oklch(0.972 0.006 245)`,
  dark `oklch(0.165 0.016 240)`) while cards stay `--paper`, giving real
  figure/ground separation ("too white" fix). New `--acc-sites/domains/security/
  billing/terminal/reports` accent tokens (dark variants brightened); home
  launcher tiles color their icon chips per section via `l.acc` →
  `chipBg/chipFg`. Mockup comparison page that drove the decision:
  scratchpad `ui-mockups.html` (Current / Minimal / Full variants — hero band,
  nautical illustrations, and table warmth from "Full" were deliberately NOT
  applied; revisit later if wanted).
- **Polish round (home)**: launcher grid minmax 210→200px so all five tiles fit
  one row at the 1160px content width (no more orphaned Terminal tile);
  skeleton shimmer (`.cc-skel` + `homeSkel` flag) replaces '…'/blank while
  hydrating — greeting stats, tile metas, attention, activity, pinned all get
  placeholder rows; new **Fleet at a glance** card fills the right column
  (client-side from hydrate-time `_fleetTotals`: dominant-core share, provider
  mix, total visits/wk, total storage — no new endpoint);
  `font-variant-numeric:tabular-nums` on body. Hero band idea rejected.
- **Iconography + customer-safe dock**: site-overview KPI tiles (visits/backups/
  versions/timeline) and the Actions card (push/pull/phpMyAdmin/delete) gained
  inline stroke SVGs matching the nav icon style; the idle corner pill is now an
  "Activity" button with a pulse icon (was an ambiguous dot). The dock's console
  prompt/target picker/cookbook/⌃` hint are wrapped in `termShow` (operator
  only) — customers get the same dock as a plain activity feed with friendly
  idle copy, so background jobs stay visible without a scary terminal.
