# Core v3 — build status & remaining work

Ground-up rebuild of the CaptainCore Manager `/account` UI — **the DEFAULT
template since 2026-08-22** (`templates/core.php`; the shell reads this
directory's modules). The old Vue app was renamed to `templates/core-legacy.php`
and stays reachable behind `?ui=legacy` / `?ui=v1`; the retired `?ui=v3`
dev-gate param is still accepted (it's simply the default) and the SPA router
strips it from pushed URLs. This file is the **hand-maintained source of
truth**; the Claude Design project "Anchor Hosting UI Revamp" (Anchor Home.dc.html,
project `aa0b3f96-96ce-4fd8-bdc2-e5cfb72f64b1`) is now a visual reference only.
Historical notes below that mention `core-v3.php` / `?ui=v3` refer to the
pre-rename filenames, and this directory itself was `templates/core-v3/` until
2026-08-23 (renamed to `templates/core/` once it became the primary UI).

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
- **Shell = Minn sidebar + slim topbar.** 240px sidebar: bare brand-ink anchor glyph
  (matches the anchor-theme lockup — no tile), ⌘K search button,
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
  screenshot exists. Card action is "WP Login" (label shortened 2026-08-23; shared
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
- **`processes.js`** — fleet bulk-process monitor (v1's bulk-progress dashboard).
  Operator-only 15s poll of `GET /progress/` (Manager proxies the CLI dispatch
  server's progress files — the runs started by `captaincore ssh @staging …`,
  update, backup, etc. on the CLI server or a local dev CLI). Rows render in the
  dock above the job strip (pulse dot, mini bar, `545/1157 · 47.1% · ETA 27m`,
  Stale chip + dismiss ✕ for dead runs); clicking opens the detail dialog
  (`bp*` bindings): progress bar, Elapsed/ETA/Parallel/Failed/PID/Target stats,
  args, Pending/Completed/Failed pill tabs with per-site chips (capped at 400,
  fed by `GET /progress/{pid}`, refreshed on each poll while open), Kill process
  (`DELETE /progress/{pid}`, confirm() first) and Dismiss for stale. The topbar
  running chip counts fleet runs too — a lone fleet run reads as live progress
  ("update · 47.1%"). A run that finishes deletes its progress files server-side
  and vanishes from the list — the poll toasts "<command> finished across N
  sites" and closes its dialog if open. Local dev: the topbar chip reflects
  bulk runs of the LOCAL `captaincore` CLI via the local daemon
  (`CAPTAINCORE_CLI_ADDRESS` → captaincore-api.localhost).
- **`registry.js`** — WP Registry audit coverage as a site tab (v1 parity with
  core.php's showAuditCoverage/showAuditFindings dialogs). `loadRegistry()` hits
  `GET /sites/{id}/environments/{envId}/audit-coverage` (summary + every
  installed component's content hash matched against findings.wpregistry.io via
  RegistryClient), cached per environment_id on `_detail.reg`. `computeRegistry`
  renders severity chips + a coverage bar, then groups rows by kind
  (plugin/theme/mu-plugin/loose files). Clicking an AUDITED row opens the
  findings dialog (`rg*` bindings) — per-hash `GET …/audit-coverage/{hash}`,
  accordion findings with description/location/CVE/code/recommendation.
  **Links go to the PUBLIC registry**: `https://wpregistry.io/finding/<hash>`
  (the worker's public per-hash page — findings.wpregistry.io is the private
  origin and 401s for customers). The per-hash route is the public projection,
  so embargoed findings never reach the browser and the tab is customer-safe.
  Env switch clears the dialog and reloads (both setEnv and the tab handler).
  **Summary chips are filters** (2026-08-07): clicking "3 high" etc. filters
  the component groups to that bucket (`rgFilter` state; active chip wears its
  text color as a ring; re-click or "total" clears; status labels match
  non-malware rows only — a malware row shows only under its malware chip,
  mirroring the chip-label logic). Cleared on env switch and openSite.
- **`tools.js`** — the v1 Scripts tab's **System Tools**, rehomed into the site
  Overview (the old "Actions" card split into **Deploy** and **Tools**). Six
  tools, all streaming through `startJob` like any other action:
  deploy-defaults · apply-https (www variant) · launch (domain) go through
  `POST /sites/bulk-tools {tool, environments:[envId], params}`;
  reset-permissions · migrate (`value` + `update_urls`, compared to the STRING
  "true" server-side) · maintenance on/off go through `POST /sites/cli`
  (bulk-tools' deactivate can't carry the visitor-facing subject/status/action
  copy). Both endpoints return a job token — a bare string from `Run::task`,
  `{token}` from `background_task` — and `dispatchJob` already accepts either.
  **No role gate**, matching v1: the routes are site-scoped
  (`captaincore_verify_permissions` per environment), so a customer can only
  act on their own sites. Maintenance defaults its business name/link from
  `CC_BOOT.name`/`homeLink` (no fetch), preferring a loaded Settings config.
  Also owns **Activity → Scheduled** (`computeScheduled`): the list needs NO
  fetch — `Site::environments()` already attaches `scheduled_scripts` to every
  environment — plus the edit dialog (`POST /scripts/{id}`, note POST not PUT)
  and cancel (`DELETE /scripts/{id}`). **Its bindings use an `ss` prefix, not
  `sched`** — reports.js owns `schedRows`/`schedEmpty` for scheduled REPORTS
  and spreads after computeDetail, so the `sched` names get silently clobbered
  (this bit once: the count read "1 scheduled script" beside an empty list).
- **`jobs.js`** — the job engine. `startJob({label,target,command,dispatch,onFinish})`
  → daemon token → WebSocket `{token,action:"start"}` → plain-text frames → `"Finished."`
  sentinel → `onFinish`. The activity dock renders `activeJob()`.
- **`terminal.js`** — the dock-footer terminal (loads after jobs.js; owns `termRun`).
  **`termRef` SEEDS the textarea from `state.termCmd` on mount** — do not
  simplify it back to `el => { this._termEl = el; }`. The textarea has no value
  binding (the runtime treats `value` like `defaultValue`), so anything that
  fills the input while the dock is CLOSED was silently lost the moment the
  dock mounted a fresh empty node. That was the Cookbook → Run bug: the recipe
  only appeared if the terminal happened to be open already. The `_seeded` flag
  lives on the DOM node so it re-seeds per real remount while surviving the
  ref(null)/ref(el) churn of ordinary re-renders. Any NEW "send this text to the
  terminal" entry point gets this for free; it also means a typed draft now
  survives closing and reopening the dock.
  **Schedule** (v1's terminal_schedule): a button beside Run, shown once
  something is typed, opens a date/time dialog and POSTs `/scripts/schedule`
  once per selected target (`{environment_id, code, run_at:{date,time,timezone}}`).
  The server parses date+time in the SUPPLIED IANA zone and stores UTC, so send
  the local wall-clock values plus the zone — never a pre-converted time. On
  success it clears the input and reloads the open site so Activity → Scheduled
  picks the new row up.
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
- **Stats tab** (verified on a customer site with a live Fathom tracker) — KPI tiles,
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
- **Accounts / Users / Access** (verified live on a multi-user customer account
  with real plan usage bars) — account detail on real data:
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
- **Reports** (verified live: real Maintenance-Report HTML for a customer site in the preview
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
  no-backend controls on real data: domain **Auto-renew** toggle (no v1 route)
  and account **Cancel plan…** (`POST /billing/cancel-plan` exists, but
  cancellation is deliberately handled out-of-band — correction 2026-08-23,
  the original "no v1 route" reason here was wrong).

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

### Round: admin gaps + polish (2026-08-02, verified live)
- **Sidebar collapse animates** (Minn's `width/padding/opacity .25s`). The nav is
  no longer wrapped in `sc-if showRail` — it stays MOUNTED and shrinks to width 0
  (an sc-if unmounts and kills the transition), with `.cc-rail>*{min-width:216px}`
  in the helmet so rows slide out of the clip instead of squishing. `showRail`
  still computes but nothing consumes it.
- **Credentials passwords are masked** (site Overview): any row whose key matches
  /password/i renders `••••`; a Show/Hide control toggles reveal. Clicking
  anywhere on the row copies the real value. Reveal state is keyed
  `siteId|env|key` so switching sites/envs re-masks.
- **Edit plan → Next renewal is a real date picker** (`<input type="datetime-local"
  step="1">`). The API stores `YYYY-MM-DD HH:MM:SS` and the input wants a `T`
  separator — accounts.js converts both ways (and appends `:00` when the browser
  omits seconds).
- **Delete account** (operator-only) on account detail — `DELETE /accounts/{id}`
  (v1's deleteAccount; the route also queues the CLI's background `account delete`),
  confirm → toast → drop from ACCOUNTS → route back to the list.
- **Users listing shows accounts per user.** Backend: `Users::list()` now attaches
  `account_ids` from ONE grouped pass over the AccountUser pivot (a per-user
  `accounts()` call is an N+1 across 2k users). Frontend: brand chips per row that
  `openAccount(id)`, names resolved from hydrated ACCOUNTS; also in the ctx menu.
- **Domains — forwarding add-row is gated.** The alias list + "Add forward" row
  only render once a Mailgun forwarding zone exists (`fwdActive`); before that the
  POST just 400s, so the row was a trap.
- **Domains — operator zone teardown.** Each tab gained a delete row (all three
  v1 routes already existed; only the UI was missing): DNS `DELETE /domain/{id}/
  dns-zone`, forwarding `DELETE /domain/{id}/email-forwarding`, sending
  `DELETE /domain/{id}/mailgun`. Each confirms naming the domain and reloads the
  detail. Creation stays each tab's existing Activate button.
- **Mailgun Usage chart has the Stats hover tooltip** (`mg*` mirror of stats.js's
  `chartHoverIdx/X/Y`): bucket label + sent/delivered/failed.
- **Chart tooltips SNAP TO THE COLUMN, not the cursor** (Chart.js behavior, v1
  parity). Shared `barAnchor(e, prefix, idx)` in app.js returns the
  `<prefix>Idx/X/Y` state patch from the hovered bar's own geometry —
  `offsetLeft + offsetWidth/2` and `offsetTop - 8`, relative to the
  position:relative plot area — clamped ±90px so an end bar can't push the
  bubble outside the card. Both the Stats chart and the Mailgun Usage chart use
  it; the old `onMouseMove` cursor-tracking handlers are GONE (only the
  `onMouseLeave` reset remains on the plot container). New bar charts should
  call `barAnchor` from the bar's `onMouseEnter` rather than re-deriving
  coordinates from mouse events.

### Site-detail tabs: 11 → 6 groups (2026-08-02)
Eleven tabs became six GROUPS over the same twelve leaves, driven by
`SITE_TAB_GROUPS` in app.js:

| Tab | Leaves |
|---|---|
| Overview | overview |
| Stats | stats |
| **Inventory** | plugins · themes · registry |
| Users | users |
| **History** | versions · backups · snapshots · captures |
| **Activity** | logs · timeline · scheduled |

- **Users is deliberately TOP-LEVEL, not inside Inventory** (Austin's call after
  a first pass grouped it there). Grouping pays off for tabs you skip past;
  Users is the opposite — "pick a site → Users → Login as" is a spoken
  instruction to customers, so it has to stay one click and one word. Frequency
  beats taxonomy when the two disagree.

- **`state.siteTab` still stores the LEAF**, which is also the URL segment — so
  `/account/sites/135/backups` and every `tab*` flag, lazy-load and KPI-tile jump
  keep working untouched. The tab bar highlights the leaf's GROUP
  (`siteGroupOf`); a second pill row picks the leaf. Single-leaf groups render no
  segment row (Overview/Stats don't grow a blank line).
- **Secondary pills sit on their own line** below the group tabs.
- **`goSiteTab(tab)` is the single entry point** — normalizes via `siteLeaf()`
  (which resolves `SITE_TAB_ALIASES`), syncs `addonKind` for plugins/themes, and
  fires that leaf's lazy load. Router `applyUrl`, the Overview KPI tiles, and
  `goCaptures` all call it; do NOT `setState({ siteTab })` directly or you skip
  all three.
- **Legacy `/addons` links alias to `plugins`** (`SITE_TAB_ALIASES`). The old
  in-tab Plugins/Themes toggle was REMOVED from the addons markup — the segment
  row is now the only control (`setAddP`/`setAddT`/`akp*`/`akt*` bindings still
  compute but nothing consumes them).
- `computeDetail` also derives addonKind defensively from the leaf, so a cold
  deep link to `/themes` renders themes before goSiteTab has synced state.

### v1 Scripts tab: ported (2026-08-02)
The v1 Scripts tab had four sections. Two needed no port — its **Command
Console** bar is just a terminal launcher (v3 reaches the terminal three ways
already) and its **Recipes** grid is the dock's cookbook popup. The other two
shipped: **System Tools** → the Overview Tools card (tools.js) and **Scheduled
Scripts** → Activity → Scheduled + a terminal Schedule button. There is no v3
"Scripts" tab and there should not be one.

Verified live on austinginder.com: all six tools render and every dialog opens;
a real script was scheduled from the terminal (appeared in the tab with author,
avatar and formatted run time), edited (`wp option get home` → `siteurl`,
persisted), then cancelled — the table is empty again and no rows were left in
`captaincore_scripts`. The destructive tools themselves (migrate, launch,
maintenance, deploy-defaults, reset-permissions) were NOT fired against a real
site; only their dialogs and payload construction were exercised.

### Timeline composer + share preview (2026-08-02)
- **Timeline entries are Markdown, so the composer is a TEXTAREA.** Add and edit
  were single-line `<input>`s, which couldn't author or edit the multi-line
  entries the `/captaincore-log` skill posts (headings, lists, links, code).
  Both are now auto-sizing textareas with a "Markdown · ⌘⏎" hint; ⌘⏎ submits
  via `submitTlDraft()`/`submitTlEdit()` (the dock check in the keydown handler
  runs first, so the terminal still owns ⌘⏎ while it's open). The DC runtime
  binds textarea `value` like `defaultValue`, so both are seeded and cleared
  through refs (`tlDraftRef`/`tlEditRef`) — the edit ref re-seeds on `_forUid`
  change so switching rows loads the right text. Rows also list attached files
  (the diffs the CLI logs alongside an entry) instead of ignoring them.
  **Add/edit/delete themselves already worked** — verified end to end against
  real data before touching anything.
- **Share Access lists domain NAMES.** `invite-preview` returns a full
  `domains_list`, which v1 fetches and then prints only the count of — v3
  inherited that. Both sites and domains now render as chip lists capped at 12
  with an explicit "+N more" (never a silent truncation), and the dialog got
  `max-height:82vh` + a scrolling body since the preview can now be tall.

### Edit plan: next renewal can be cleared (2026-08-16)
Empty `next_renewal` is how accounts are deactivated — `Accounts::update_plan()`
already persisted `""`, and the renewal cron / `User::subscriptions()` skip a
blank date. v1's text field could be deleted; v3's `datetime-local` picker
cannot be emptied from the keyboard. A **Clear** link now sits beside the
picker (same treatment as billing user) and remounts the input (DC binds
`value` like `defaultValue`, so a state-only write would leave the old date
on screen). An empty picker shows "none — account inactive". Cancel leaves
the stored date alone; Save writes `""`.

### Edit plan: billing user can be cleared (2026-08-03)
The picker was select-only — once a billing user was set there was no way back
to "none". Clicking the SELECTED row now toggles it off, and a **Clear** link
sits beside the "Billing user" label whenever one is set (with a "none
selected" hint when it isn't). This is a legitimate state, not a hack:
`Accounts::update_plan()` itself normalizes an empty billing user to `""`
(Accounts.php:133), `Account.php:156` reads it back as `0`, and the invoice
path guards on `empty()`. Verified end to end on account 3: pick → toggle off →
re-pick → Clear → save, persisted as the server's canonical `""`.

**Backend fix that came with it:** `Account::calculate_usage()` fataled on any
plan that exists but predates the `usage` key — the old guard only caught a
wholly EMPTY plan, so `$account->plan->usage->storage = …` threw and took the
entire account detail page down (500 on `GET /accounts/{id}`). One account of
4,119 was affected (account 3, the local test account), which is why this went
unnoticed. `usage` is now initialized whenever it's missing or not an object.

### Accounts card: customer/billing contacts + assignment (2026-08-07)
v1's Edit-Site dialog handled account assignment with icon toggles (person =
`customer_id`, $ = `account_id` — account_id MEANS BILLING). v3 decomposes it
into the Overview "Shared with" card, retitled **Accounts** ("access &
contacts"):

- **Role chips per row** — labeled "Customer" (brand-soft) and "Billing"
  (ok-soft) instead of v1's tooltip-only icons; a row can wear both. Sample /
  pending-invite rows keep the old single level chip.
- **Row menu** (right-click or ⋯, operator-only): Set as customer contact ·
  Set as billing contact · Open account · Remove from site. The current
  customer/billing account can't be removed — reassign the role first (v1's
  mandatory-toggle invariant). Remove confirms.
- **Assign… button** (operator, beside Share) → searchable picker dialog
  (`asg*` bindings in site-detail.js `computeAssignAccount`, spread GUARDED in
  computeDetail) listing hydrated ACCOUNTS minus already-assigned.
- **Immediate commit per action** (DNS-per-record pattern): each action PUTs
  the NEW SCOPED ROUTE `PUT /sites/{id}/accounts` `{customer_id, account_id,
  shared_with}` — `captaincore_site_accounts_update_func` in captaincore.php
  (admin-gated; plain function, no classmap regen). It updates the two Sites
  columns, dedups customer/billing out of the pivot, syncs via
  `Site::assign_accounts` (which logs shared/unshared + recalcs pivot-account
  totals), logs 'updated', and recalcs totals on the previous + new billing
  accounts. **Never reuse `PUT /sites/update` from a scoped UI** — its payload
  round-trips environments and DELETES any environment it doesn't carry
  (Site.php update()), and it chains a full CLI update job.
- Response returns refreshed `site` + `shared_with`; the client patches
  `_detail` in place (no full reload). Toast loading→success per action.

Verified live headless on a customer site with three assigned accounts: chips
render for the baseline (distinct billing + customer + plain-shared rows);
moved customer then billing onto the shared account (dual-chip row); assigned
a fourth account via the picker; removed it (confirm); restored baseline. DB
checked after each phase (Sites columns + AccountSite pivot exactly restored)
and the activity log carries the full shared/unshared/updated trail.
Unauthenticated PUT → 401.

### Archived server logs: phase 1 browser (2026-08-07)
The Logs leaf (Activity → Logs) gained a **Live / Archive** segmented toggle.
Archive view exposes the long-term B2 log retention (the CLI server's daily
`captaincore logs archive` cron) through the two EXISTING site-scoped routes —
no backend changes:

- `GET /site/{id}/{env}/logs-archive` → `[{name, type: access|error, date,
  epoch, size}]` (CLI `logs archive-list` over rclone lsjson, newest first)
- `GET /site/{id}/{env}/logs-archive/download?file=…` → `{link, expires_at,
  expires_in}` signed B2 URL (rclone link, 24h)

UI (`computeLogsArchive` + `loadLogsArchive`/`downloadArchivedLog` in
site-detail.js; mode toggle bindings in app.js computeDetail): range presets
(7/30/90 days/All, default 30) + All/Access/Error pills, rows grouped by
month (weekday-date · type chip · mono filename · size · Download), count
line overrides `logMeta` ("80 files · 12.4 MB") via a spread placed AFTER the
base logMeta. All filtering is client-side over the one list call (~2
files/day). Download = toast → signed link → window.open. List cached per env
on `_detail.la`; env switch reloads when the tab+mode are active; `openSite`
resets to Live. Empty state distinguishes "no archives yet" (a fresh env, or
staging before the 2026-08 cron) from "filters match nothing".

**Local-dev gotcha:** the local CLI config's `rclone_backup` points at the
DEV bucket path, so archive lists are legitimately EMPTY locally — the CLI
appends `/<captainID>` in fleet mode, so the production layout `Sites/1/…` =
system `…/Sites` + captain 1. Verified live by temporarily pointing the local
config at the production path (restored after): 30d/90d counts, Error filter,
month grouping, real signed-link download (HTTP 200 from B2, correct object),
and the staging empty state — no page errors.

**Phase 2 — in-browser viewer (2026-08-07, same day):** clicking a row (or
its View link) swaps the list for an inline viewer — ← Archive back link,
mono filename, line count ("last 1,000 of N lines" when truncated), Download,
and the same line-number + `logSegments` highlighting as the Live view.
**CORS check came back negative** — a signed-link GET returns no
`Access-Control-Allow-Origin`, so the client-side `DecompressionStream` plan
was dropped for the fallback, minus the CLI command: NEW Manager route
`GET /site/{id}/{env}/logs-archive/view?file=…[&lines=N]`
(`captaincore_site_logs_archive_view_func`, plain function — no classmap
regen) resolves the signed link via the existing CLI `archive-get`, then
`wp_remote_get`s and `gzdecode`s it SERVER-SIDE and returns
`{file, total, truncated, content}` capped at the last 1000 lines (max 5000).
Same filename validation + site-scoped permission as the download route; no
bucket or CLI changes. Client: `viewArchivedLog` caches per env+file on
`_detail.la[env].content`; row Download stops propagation (the row itself
opens the viewer); `laView` clears on env switch and `openSite`. Verified
live (same config-flip method, restored after): 382-line error log rendered
highlighted, back-to-list, instant cached reopen, viewer-header download,
no page errors.

### Edit site: identity + environment connection (2026-08-07)
The last two thirds of v1's Edit-Site dialog (the first third — account
assignment — became the Accounts card). Both operator-only, both on scoped
routes, because **v1's `PUT /sites/update` DELETES any environment missing
from its payload** (Site.php update()) — a partial payload from a scoped UI
would destroy environments, so it must never be called from v3.

New routes (plain functions in captaincore.php, admin-gated, no classmap regen):
- `PUT /sites/{id}/identity` `{name, provider, key}` — writes name/provider +
  `details.key` (SSH override; `''` clears to the primary key), ActivityLog,
  returns the refreshed site. Does NOT touch environments or accounts.
- `PUT /sites/{id}/environments/{environment_id}` — **whitelisted partial**
  update (address/home_directory/username/password/protocol/port/database_*).
  Only keys present in the payload are written; passwords bypass
  sanitize_text_field (it mangles legitimate characters), protocol is enum-
  checked, port is digits-only.
- `DELETE /sites/{id}/environments/{environment_id}` — removes ONE environment
  record. **Production is refused (400)**, and both env routes 404 unless the
  environment_id belongs to that site (verified: a cross-site id is rejected).

UI (`computeEditSite`/`computeEnvEdit` in site-detail.js): a pencil button in
the site-detail header opens **Edit site** (domain input, provider segmented
pills, SSH-key pick list lazy-loaded once from `GET /keys/`); the Overview
Credentials card header gained an **Edit** link opening **Edit
&lt;env&gt; connection** (address, home dir, username, password, protocol
pills, port). Saving an environment fires `realSync` afterward so the CLI
validates the new connection (v1 kicked `update` the same way). Staging-only:
**Preload from Production** (v1's preloadStagingEnvironment — kinsta-cloud
address prefix, username suffix vs. kinsta password copy; mutates the draft
then remounts the dialog because the DC runtime binds input value like
defaultValue) and **Delete environment…** (confirm, then reload).

**BINDING COLLISION (bit this round, cost a debug cycle):** tools.js already
returns `esOpen` for the scheduled-script edit dialog. Naming the new dialog's
bindings `es*` mounted BOTH dialogs at once (the later spread won). Renamed to
`eds*`; `ee*` for the environment dialog was clear. Grep every new binding
prefix against the other mixins before using it — see the render-time mixin
guard note above.

Verified live: dialog seeds real values (name/provider chip/SSH key ✓);
renamed a site and renamed it back (header + FLEET row followed both times);
env dialog seeded address/home/user/port, changed a port and confirmed the
write in the DB, then restored it; staging dialog showed both staging-only
controls, Preload copied production's address/user/port into the draft, and
Cancel left the DB untouched. Production dialog correctly hides both. Guards
re-checked as admin: production delete → 400, cross-site env id → 404,
unauthenticated PUT → 401. Activity log carries both new entry types.

### Still-dead controls (need bigger UI or a missing backend)
- **Branding**: logo upload (drop-zone), DNS-copy-labels edit.
- **Site detail**: "Delete site…". (Edit site + per-environment connection
  editing are DONE — see the Edit-site section above.)
- **Domains**: Mailgun "View all logs →" pager. (Deploy-to-site and suppressions
  are DONE — see the Sending-tab parity section below.)
- Gated-off-on-real: "Access as" (User Switching plugin — no per-user REST route),
  provider Import wizard, archive Delete (no v1 route).

### Cross-cutting / smaller
- **Sites list gaps** — theme/plugin filter facets and per-site update counts need
  `POST /filters/sites` + `/filters/{name}/versions|statuses` and the update-queue.
  Bulk selection + `/sites/bulk-tools`. The 4 site-create flows (Request, Kinsta
  new/clone, Connect import wizard, Manual). "Select all in filter."
- **Addons** — per-item "update available" badge + per-row Update (needs update-queue);
  whole-site update. (Add dialog is DONE — see addons.js above.)
- **Realtime depth** — the design has a single dock; v1 also has **archive SSE**
  (`/my-jobs/{token}/stream`) and a **fleet process monitor** (`running listen` WS).
  Not yet built. Also: WS **reconnect + reload-resume** (jobs are lost on refresh
  today; v1 also lacks reconnect). (The bulk-progress dashboard is DONE — processes.js.)
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
- **Domains slice leftovers.** Not wired: forwarding
  logs pager, domain→account assignment (admin `PUT /domains/{id}/account`),
  update-site-link. Domain detail now links the owning account (v1's
  Shared With cards). Wired but not live-toggled
  (registrar writes on real domains): lock/privacy toggles, contacts save,
  nameservers save. Domains list still can't show account or expiry columns
  (list payload carries neither). Domain delete + per-tab zone teardown
  are DONE — see the domain-delete section below.
- **Stats tab leftovers.** The "Performance monitor" card is design-only (no v1
  endpoint exists — needs a backend before it can be real). Multi-tracker sites
  (v1 shows a tracker autocomplete when `fathom_analytics.length > 1`) always use
  the first tracker in v3. `/sites/{id}/environments` can take ~8s on the local DB —
  the Stats tab defers its first load until envs arrive (hook in `loadSiteDetail`).

### Known nits
- `/sites` list records carry no theme/plugin/update data, so those Sites-list filters
  render empty until wired to `/filters/sites`.
- Terminal: v1's extras not yet ported — save-input-as-recipe and fullscreen mode.
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
- **Deploy confirm dialog**: push/pull staging no longer fires immediately —
  the Actions-card buttons set `deployConfirm: 'up'|'down'` and open a styled
  confirm dialog (`computeDeployConfirm` slice in site-detail.js, markup next
  to the delete-user dialog in app.html). Confirm button is `var(--bad)` red
  whenever a production env would be overwritten; warning text names source and
  target as "env on site". Escape + backdrop + Cancel all close it; `depGo`
  runs the old realPush/runJob path. Verified live headless on a customer site
  (both directions, open/cancel/Escape).
- **Edit plan dialog** (v1 parity, core.php `modifyPlan`/`updatePlan`): the
  account Plan tab gains an operator-only **Edit plan** button (customer keeps
  Request changes). `computeEditPlan` slice in accounts.js, `ep*` bindings;
  draft plan lives on `this._ep` (instance, mutated in place + `setState({})`),
  hosting plans lazy-fetched once from `GET /configurations/` + synthetic
  "Custom" entry (cached on `this._epPlans`). Plan/interval/billing-mode as
  chip rows (STATUS rule: no absolute dropdowns in dialogs), billing user as
  in-flow pick list, autopay/auto-switch toggles, limits row renders one of
  three variants (per_site price-only / Custom editable / fixed read-only),
  editable addons/credits/charges lists (required addons locked + stripped
  from the payload, v1 convention), additional emails. Interval change
  recalculates price from the base plan (base price ÷ base interval × new).
  Save = `PUT /accounts/{id}/plan {plan}` (admin-gated) with auto_pay/
  auto_switch serialized to 'true'/'false' strings (v1 consumers compare
  strings) → toast + account reload. Verified live headless end-to-end on
  account 3 (chips, Custom variant, addon row, save persisted + Plan tab
  updated; test data restored after).
- **Push to another site** (v1 parity, `showPushToOtherDialog`): Actions-card
  "Push to another site…" opens a target-picker dialog (`computePushToOther`
  slice, `pto*` bindings/state). Targets from
  `GET /sites/{id}/environments/{envId}/push-targets` (source = currently
  selected env; provider- and permission-filtered, Kinsta-only; includes the
  source site's own other env). Search filters name+home_url client-side,
  rows capped at 100. Picking a target sets `deployConfirm: 'other'` and
  reuses the same confirm dialog; `depGo` posts the same
  `/sites/environments/push` via new `realPushTo(real, srcEnvId, tgtEnvId,
  label)` (realPush is now a thin wrapper over it) with the usual provider-op
  dock tracking. Sample mode demos targets from FLEET. Gotcha: the target list
  is big (~3.4k envs locally) — first load takes several seconds; the dialog
  shows a loading line until then. Verified live headless: picker → search for
  a target site → confirm (red button, correct from/to sentence) → cancel.
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
- **Mailgun usage panel (Sending tab)**: new `GET /domain/{id}/mailgun/usage?period=day|month|year`
  (`captaincore_mailgun_usage()` in `captaincore.php`, normalizing Mailgun's
  `v3/{zone}/stats/total`; 10-minute transient). The Sending tab gained a **Usage**
  card above Recent events — Daily/Monthly/Yearly pills, Sent/Delivered/Failed/
  delivery-rate stat tiles, and a token-styled CSS bar strip (no chart library in
  v3). Mailgun resolves to hour/day/month with 24 months of retention, so "yearly"
  is rolled up from monthly buckets server-side; the earliest yearly bucket is a
  partial year. Bar labels render every ~6th bucket with `overflow:visible` so a
  label wider than its 1/30 slot spills into the blank neighbors instead of clipping.
  Parity note: v1 (`core.php`) got the same feature as a "View Usage" dialog with a
  Chart.js bar chart plus a per-bucket table.
- **Sending-tab parity (setup gate, suppressions, deploy-to)**: before a domain has
  a Mailgun zone (`details.mailgun_id` absent), the Sending tab now shows ONLY the
  intro line + "Set up mg.{domain}" banner — the DNS verification / Usage / Recent
  events cards are wrapped in a new `mgActive` `sc-if` (previously they rendered
  empty). Once active, two header buttons replace the old dead "Deploy to site
  (SMTP)" stub: **Suppressions** opens a dialog with Bounces / Unsubscribes /
  Complaints / Allowlist pills over the existing v1 routes
  (`GET|DELETE /domain/{id}/mailgun/suppressions/{type}` — delete passes
  `?address=`, allowlist rows key on `value`/`createdAt` instead of
  `address`/`created_at`); **Deploy to…** opens a two-step dialog — filterable
  `connected_sites` list (from the `/domain/{id}` payload) → send-from-name prompt
  → `POST /domain/{id}/mailgun/deploy` `{site_id, environment, from_name}` (the v1
  handler ignores the `domain` field core.php sends, so v3 omits it). Verified live
  on both states: a no-Mailgun domain collapses to the setup banner; an active zone
  shows the buttons, tab switching, empty-state copy, and the step-2 prompt.
- **Inventory row delete (plugins + themes)**: the addon row context menu gained a
  danger-styled "Delete…" after Copy slug (`realDeleteAddon` in site-detail.js).
  Confirms naming the slug/site/env, then dispatches `wp {plugin|theme} delete
  --skip-themes --skip-plugins` through the same `POST /run/code` job pipeline as
  toggle/install — an active plugin chains `wp plugin deactivate … && …` first, and
  the ACTIVE THEME is refused with a toast (WP-CLI can't delete it). Optimistic row
  removal + `realSync` on finish restores truth if the remote delete failed. Demo
  mode runs the usual simulated job. Verified live: menu entry renders red, the
  confirm carries the real wording, cancel leaves the row (actual deletion not
  exercised against a customer site — same dispatch path as the proven actions).
- **Must-use plugins are no longer toggleable, and the Add dialog delete got
  guards.** fetch-site-data reports mu-plugins/drop-ins with `status:
  "must-use"`/`"dropin"`; the old `active: status === 'active'` collapsed them
  into "inactive", rendering a dead Activate button (wp plugin activate/
  deactivate/delete can't touch them). `realAddonSrc` now carries `mu`/`muLabel`
  (and treats `active-network` as active); mu rows render a dimmed dot + a
  static "Must-use"/"Drop-in" chip instead of the toggle, and their context
  menu is Copy slug only. The Add dialog's wp.org "Uninstall" link (which fired
  with NO confirm) is now "Delete" with the same confirm + guards as the row
  delete: hidden for mu slugs, refused for the active theme, active plugins
  deactivate first. Verified live: 4 mu rows render chips, mu context menu has
  only Copy slug, dialog Delete confirms and cancel is a no-op.
- **File manager (Inventory → Files leaf)**: read-only browser over the
  environment's home directory. New Manager route `GET
  /environment/{id}/files?path=&action=list|view` (permission via env→site
  ownership) dispatches `ssh <target> --script=file-manager` through the CLI
  server's SYNCHRONOUS `/run` (the plugin-diff pattern) — no job dock involved.
  The path travels base64-encoded; the new CLI remote-script (`lib/remote-scripts/
  file-manager`, bash + a PHP payload piped `echo | php` so it can't eat the ssh
  stdin the script itself arrives on) resolves every request through `realpath`
  and refuses anything that lands outside the home dir — traversal AND symlink
  escapes — so the lock is enforced on the site, not in the UI. Output protocol:
  the JSON rides base64-encoded in SHORT WRAPPED LINES between CC_FM_BEGIN /
  CC_FM_END markers (MOTD noise outside the markers is ignored). Short lines
  are load-bearing, not cosmetic: the dispatch server's runCommand reads child
  output with a default bufio.Scanner (64 KB/line cap) — one long JSON line
  (a >64 KB file view, a huge directory) kills the scanner, the pipe stops
  draining, ssh blocks, and the request hangs to the 120 s curl timeout (hit
  live on a 261 KB file view). server.go also got an s.Buffer bump to 8 MB as
  defense-in-depth, but that only takes effect on the next CLI server rebuild.
  Listing sort is numeric-aware (`localeCompare` with `{ numeric: true }`) so
  numbered directories order 1, 2 … 10, 11 instead of lexically.
- **Mobile pass (≤760px)**: the layout is inline-styled, so responsiveness
  lives in one media block in app.html plus a handful of structural classes:
  `cc-page` (page wrappers, tighter padding), `cc-cols` (side-by-side content
  grids stack to one column — home, site overview, captures split, security/
  archives), `cc-tbl` (sites/users/domains/accounts tables scroll sideways
  with a 680px floor instead of crushing columns), `cc-tabs` (pill bars
  scroll instead of clipping), `cc-stack-sm` (attach-form textareas stack),
  `cc-plf-body`/`cc-plf-side` (code-diff dialog stacks its file sidebar).
  The rail becomes a fixed overlay drawer: phones boot with it closed
  (`isMobile()` in app.js), picking a nav destination closes it, a `.cc-
  backdrop` scrim behind it is the tap-to-close affordance (the overlay
  covers the topbar toggle, so the scrim is load-bearing, found via mobile-
  emulation Playwright), a phone toggle doesn't overwrite the desktop
  localStorage preference, and the collapsed rail carries `pointer-events:
  none` — mobile Chromium's compositor still hit-tests the zero-width
  opacity-0 fixed layer and swallowed taps on the topbar toggle without it.
  `cc-shell` upgrades 100vh→100dvh under @supports. Desktop is pixel-
  unchanged (verified 1440px before/after).
- **Activity group defaults to Timeline**: the Activity tab's leaves are
  ordered Timeline → Logs → Scheduled (a group click lands on `leaves[0]`).
  Timeline renders from already-loaded data; Logs needs a slow ssh fetch, so
  defaulting there felt broken.
- **Timeline file-diff chips + code-diff dialog**: attachment chips now show
  `+N −N` stats and open a code-diff dialog (file sidebar with per-file stats,
  hunk rendering with line numbers and red/green rows, stats footer — the
  core.php pld dialog re-done in v3 style). Hunks come from the stored
  process_log_file rows (`/captaincore-log` contract); the timeline REST
  already shipped them.
- **Timeline attach-a-diff form**: the composer and the row editor both carry
  "+ Attach file diff" — path + original + updated content, hunks computed
  client-side (`tlComputeHunks`: LCS on the trimmed middle, 2 context lines,
  whole-block fallback for very large inputs). Attachments are removable in
  the row editor; saves ride the existing process-logs REST (create accepts
  `files`, update replaces the set). Server fix that ships with this:
  `ProcessLog::assign_files` now accepts the read shape's `file_path` as well
  as `path` — before that, ANY edit round-trip (v1's edit dialog included)
  silently wiped the entry's attachments because the GET shape came back
  without `path`.
- **File manager image preview**: viewing an image (png/jpg/gif/webp/svg/ico/
  bmp/avif by extension) renders it inline in the view dialog instead of
  "Binary file — no preview". The remote script returns the WHOLE file for
  images (a truncated image is corrupt) with an `image` mime field, up to a
  hard 8 MB cap — larger falls back to the binary answer and the UI says
  "Image too large to preview". Rendered as `<img src="data:…">` (the backup-
  browser precedent): a data-URI image, SVG included, can't execute scripts,
  so a hostile file on a managed site stays inert. Server keeps `binary: true`
  on image responses so an older cached frontend degrades to no-preview rather
  than dumping bytes as text. Needs the CLI repo's `file-manager` remote
  script pulled on the core server (script-only — no rebuild).
- **File manager stale-while-revalidate cache**: every listing and file view is
  cached for the session (`_fmCache`/`_fmViewCache`, keyed environment_id +
  path). Navigating to a cached path renders instantly with a quiet
  "Refreshing…" note while the ssh roundtrip re-fetches in the background and
  swaps in changes; Refresh forces the same cycle. A background refresh that
  errors from the server (path deleted) REPLACES the stale listing with the
  error; a network-level failure keeps the cached copy silently. Responses that
  land after the user navigated away still update the cache. Measured live:
  cold subdir 3.3 s → warm 0.04 s; warm file reopen 0.04 s.
- **File VIEWS are audit-logged** (`ActivityLog`: action `viewed`, entity_type
  `file`, entity_id = site_id, path + environment in context, account-scoped) —
  the home dir holds wp-config.php/.env/backups, so contents reaching a browser
  gets a row, same precedent as "Retrieved auth code". Logged only on a
  SUCCESSFUL view (permission + parse + no remote error). Listings are
  deliberately unlogged (navigation + SWR background refreshes = noise), and
  duplicate rows from the view cache's background refresh are accepted by
  design — each row is a real server-side read. Renders on the Activity page
  with no UI changes.
- **File manager right-click menu + file delete**: every row now has a context
  menu (`openCtxMenu`, the Minn pattern used across the app) — files get View
  file / Copy path / Delete…, folders get Open folder / Copy path (NO delete).
  Delete is a new remote-script action (`--action=delete`) + Manager route
  (HTTP DELETE on the same `/environment/{id}/files`, `path` required). The
  script deletes the LITERAL path (not realpath — you never want to unlink a
  symlink's target), checks containment on the parent dir, and refuses
  directories AND symlinks; the UI also hides Delete on symlinked files so the
  two agree. `deleteFmFile` confirms, calls DELETE, then optimistically drops
  the row from the live list + `_fmCache` + evicts the `_fmViewCache` entry
  before a truth re-fetch. Deletes are audit-logged (`deleted`/`file`, same
  shape as views). Verified live end-to-end: menu contents differ file vs
  folder, directory delete refused server-side, a scratch file deleted through
  the menu (confirm → row vanished) with an audit row written.
- **Activity rows show actor + type + gravatar**: `ActivityLog::fetch()` now
  adds `avatar_url` (`get_avatar_url` with an `identicon` default so every
  actor renders something; 'System'/user_id 0 rows get ''). `activityRow()` in
  home.js is the shared mapper for both the Home "Recent activity" card and the
  full Activity page — each row carries `user`, a `type` chip label
  (entity_type → DNS/Site/Domain/Account/Deploy/Email/File/Security, else
  title-cased), and an avatar with a 3-way precedence: gravatar img → system
  gear icon (automated/user_id-0 rows) → initials circle. Verified live:
  gravatars + FILE/SITE chips on both surfaces, 24 system-gear rows rendered.
- **Activity page pagination + deep link**: the page was hard-capped at
  per_page=100 with no controls. `loadActivityPage(page)` now pages through the
  server's existing `total`/`pages` (100/page), with a "1–100 of 401" range +
  Newer/Older controls (disabled at the ends / while a page is in flight) and
  the header count switched from the visible-row count to the true total.
  Deep-linking was ALSO broken: `activity` was missing from the router's
  ROUTE_SEG/SEG_ROUTE maps, so `/account/activity` fell through to home and the
  URL never updated when opening the page. Added both map entries (server-side
  is already a `/account/*` catch-all, no rewrite change). Verified live: direct
  load of `/account/activity/` renders the paginated log, "View all →" from
  home now pushes `/account/activity`, and Older advances to "101–200 of 401". UI (`files.js`): breadcrumb + dirs-first listing (name/size/modified,
  symlink chip, `..` row), click a file → viewer dialog (512 KB cap, binary
  detection, TextDecoder for UTF-8). Listing state on `this._fm` keyed by
  environment_id so env switches self-heal via the render-time lazy loader.
  Verified live end-to-end on a Freighter tenant: root listing, subdir
  navigation, file preview; traversal (`../../etc`) refused remotely. NOTE: the
  CLI half ships from the captaincore repo — the remote script must be pulled on
  the core server for prod to work.

### New-site dialog: Kinsta path round (2026-08-11)
- **Default path is `kinsta`, labeled "New", listed FIRST** (was Request /
  "New on Kinsta" second).
- **Datacenter is a searchable autocomplete** over v1's full 26-region list
  (`NS_DATACENTERS` at the top of app.js, copied from core.php `datacenters`;
  default `Ashburn (US East)` = v1's `us-ashburn-1`). State holds the TITLE;
  map back to the region value via NS_DATACENTERS when the create call gets
  wired for real.
- **Dialog dropdowns de-landlocked — third pattern, ANCHORED FIXED OVERLAY**
  (`ddToggleAt(key, e)` in app.js): the panel is `position:fixed` at the
  toggle's `getBoundingClientRect()` (state `ddRect` → `ddRectTop/Left/Width`
  bindings), z-index 80 over the dialog's 70, catcher at 79. Why: absolute
  panels clip against the scrolling dialog body (the users-dialog trap), and
  in-flow panels (first fix attempt) push the form down — Austin wanted the
  dropdown to overlay without shifting content AND overflow the dialog edge.
  Fixed-anchored gives both; top is clamped to viewport-200px. Applied to all
  three ns dropdowns (Datacenter, Clone-from, Request-path Account). New
  dialog dropdowns should use `ddToggleAt` + the shared `ddRect*` bindings.
- **v1 token-refresh flow ported** (`verifyNsProvider`/`connectNsProvider` in
  app.js): opening the dialog as an OPERATOR fires
  `GET /providers/1/verify` (provider 1 = the Kinsta row; v1 hardcodes the
  same id) → "Verifying Kinsta connection…" line; a false/failed verify shows
  the "Kinsta token outdated" panel (token input + Connect →
  `POST /providers/1/connect`), and Create on Kinsta is dimmed + gated until
  verified. Customers never fire the check and never see the prompt
  (`verifyNsProvider(isOp)` short-circuits to 'ok', v1-parity with
  showNewSiteKinsta). Verified live headless: default tab New, autocomplete
  search→pick (Tokyo), clone list fully visible in-flow, real verify came
  back false (~12s — the REST round-trip to Kinsta's API is that slow, same
  as v1) and the outdated prompt rendered with the CTA gated. Connect was NOT
  fired (would overwrite the stored provider token).
- **Create on Kinsta is REAL now** (`createKinstaSite` + `pollProviderActions`
  in app.js; the earlier design-sample runJob is gone — Austin hit that on
  prod: activity showed a fake job, DevTools showed no request). v1's
  newKinstaSite verbatim: `POST /providers/kinsta/new-site { site: { name,
  domain, clone_site_id, provider_id: "1", datacenter: <region value mapped
  from the NS_DATACENTERS title>, shared_with: [{account_id}], account_id:
  billing, customer_id } }`. Client pre-validates name 5–32 (server rules);
  `{errors:[]}` responses render as bad-soft rows in the dialog; success
  toasts, closes, resets. Added the missing **Domain field** (blank → falls
  back to the site name; run() would otherwise mint `<name>.kinsta.cloud`).
  **Clone-from now lists only Kinsta fleet sites with a provider_site_id**
  (data.js keeps `providerSiteId` on FLEET) and sends that Kinsta UUID as
  `clone_site_id` — v1's clone list for the default provider was actually
  EMPTY (Kinsta::list_sites skips provider 1), so fleet-sourced ids are the
  capability upgrade, same backend contract. Datacenter row hides while
  cloning (clone ignores region, v1 parity). Customers opening the dialog
  default their first account as billing (v1's showNewSiteKinsta).
  **THE POLL IS THE ENGINE**: after the POST the BROWSER must drive the
  ProviderAction chain — `GET /provider-actions/check` every 10s (flips a
  finished Kinsta operation to "waiting"), then `GET …/{id}/run` executes
  the next server step (create the CaptainCore Site w/ accounts → disable
  edge caching → image optimization → final `site sync`). No polling = site
  exists on Kinsta but never appears in CaptainCore. v3 polls after create
  AND once on mount (4s, resumes chains orphaned by a reload); the loop
  stops when the active list is empty; an action leaving the list after
  run() toasts "created at Kinsta's <datacenter>" and rehydrates the fleet.
  Verified live headless with ROUTE-INTERCEPTED provider endpoints (no real
  Kinsta calls): client validation blocks short names with zero requests;
  payload captured byte-correct incl. roles + Tokyo region + clone UUID
  (2,685 cloneable fleet sites); server-error rows render; success closes +
  toasts; check→run chain fires and the completion toast lands. NOT tested
  against real Kinsta — the next real create on prod is the live test.
- **Account/customer/billing assignment** (operator-only, v1's admin
  `shared_with` section restyled to the site-detail Accounts-card language):
  an Accounts row with an anchored-overlay account picker (search, excludes
  already-assigned, capped 50); each assigned account renders as a row with
  toggleable "Customer" (brand-soft) / "Billing" (ok-soft) chips — ONE of
  each across rows, click again to clear, exactly v1's v-btn-toggle
  semantics — plus a per-row ✕ that also clears that account's roles. State:
  `nsShared` (account ids) + `nsCustomerId`/`nsBillingId`, reset on create;
  feeds v1's `site.shared_with`/`customer_id`/`account_id` payload when the
  create call gets wired. Customers never see the section (v1 gives them
  billing/customer selects over their own accounts — not built yet, belongs
  to the real-create round). Verified live headless: assign two accounts,
  role chips exclusive + toggle-off, row remove keeps the dialog open and
  drops the removed account's role. NOTE for tests: `{{ x }}` interpolations
  render inside `<span class="sc-interp">` — anchor Playwright locators on
  that, not on bare text()/parent axes.

### Pinned-chip context menu + illustrated thumbnail fallback (2026-08-11)
- **One site context menu, five surfaces.** The six-entry menu (Open site ·
  Login to WordPress · Pin/Unpin · Visit site · Open terminal · Copy domain)
  moved out of computeList's row builder into `siteCtxEntries(x)` on the
  Component class. Sites table rows, cards, list sections, the **pinned-strip
  chips** (new — the ask) and the **home pinned rows** all build from it, so
  the menu can't drift per surface. Home's pinned rows previously had a
  3-entry subset; they now get the full menu including Pin/Unpin, whose label
  flips off `pinnedIds()`. New site-ish rows should call `siteCtxEntries`
  rather than inlining entries.
- **Broken screenshots now fall back to an SVG illustration.** `hasThumb`
  only proves a thumb URL could be BUILT from `screenshot_base`, so sites
  whose capture 404s on the public bucket mounted an `<img>` that the browser
  painted its own broken-image glyph into — on TOP of the monogram
  placeholder (Austin caught this on prod: 3 of 25 visible rows). Fix is two
  halves: (1) `thumbFallbackRef(el)` binds a native error/load listener via
  `ref` (the DC runtime has no onError prop, same constraint as onDrop in
  addons.js) and toggles `visibility` — NOT `display`, so a later successful
  load can un-hide the same node across re-renders; (2) the placeholder under
  it became a browser-window SVG (rounded frame + title bar + two dots) with
  the site initials sitting in the window body. Present at all three sizes
  (48×34 table, 130px card hero, 150×94 list env card), each with its own
  viewBox so stroke weight stays even. Strokes/fills are `var(--ink-dim)` so
  it themes automatically. Verified live: 3 broken table thumbs hidden and
  illustrated, 4 in list view, cards view clean, dark mode correct, and the
  pinned chip menu opened → Unpin removed the chip.

### Performance Monitor (2026-08-12)
v1 parity for the Stats-tab toggle + the fullscreen monitor, on the two
EXISTING site-scoped routes — **no backend changes**:
`POST /sites/{id}/{env}/performance-monitor {enabled}` (CLI activate/
deactivate) and `GET …/performance-monitor?format=raw[&hours=N]` →
`{samples:[{time,db,load,code,resp,workers,max_workers}], max_workers}`.
New mixin **`performance.js`** (registered last in `$v3_scripts`); bindings use
the `pm` prefix; `computePerf` is spread GUARDED into computeDetail.

- **Stats-tab card** replaces the design's fake `perfRows` placeholder: a
  Minn switch + status line + "View performance monitor". The enabled flag
  lives in the environment's `details.performance_monitor_enabled` (parsed
  defensively — object today, could be a JSON string on old rows, same as
  v1). Toggling flips `details` locally first so the switch doesn't lag the
  CLI round-trip, and rolls back if the POST fails. No role gate (the route
  is site-scoped), matching v1.
- **Sampling cadence is 30 SECONDS, not 5 minutes** (Austin caught the wrong
  copy). Confirmed twice: the CLI sizes its tail as `hours * 120 * 2` lines
  (`cmd/performance-monitor.go`), and consecutive sample stamps measure
  exactly 30s apart — a 1H fetch returns exactly 120 samples.
- **Charts are inline SVG — v3 vendors no Chart.js** (v1 uses Chart.js + the
  zoom plugin). The pattern that makes an SVG chart fill a fluid card without
  distorting: fixed `viewBox="0 0 1000 200"` + `preserveAspectRatio="none"`
  so the plot stretches, plus `vector-effect="non-scaling-stroke"` on every
  stroked element so lines stay hairline at any width. Axis text is HTML
  positioned AROUND the plot — text inside a stretched SVG stretches too.
  Reuse this for any future v3 line chart.
- **Downsampling keeps the bucket MAX, not the average** (`pmReduce`, capped
  at 900 points): this dashboard exists to show spikes — a 120/120 worker pin,
  a 10s response — and averaging is exactly what hides them. The KPI tiles are
  computed from the FULL sample array, so Avg/Peak stay exact regardless.
- **Synced crosshair without a chart library**: one mousemove stores a 0–1
  position in `pmHover`, and each chart resolves its own nearest point from
  it — so hovering any chart moves the crosshair + dot + value readout on all
  four, with one shared timestamp above the grid (v1's Chart.js crosshair
  plugin, ~10 lines). Ranges 1H/24H/3D/7D/14D/ALL + refresh. **Dropped v1's
  "reset zoom"** — there is no zoom plugin to reset; drag-to-zoom is the
  obvious future add.
- Verified live on a real monitored environment: card + 30s copy, toggle
  off/on (POST intercepted — a real one dispatches a CLI activate), 524
  samples over 24H, 7 KPI tiles, 2×2 charts with the `16 max` worker ceiling
  line, synced hover readouts across all four, 1H range → 120 samples, close,
  and dark mode. No page errors.
- **Local-dev gotcha:** `GET /sites/{id}/environments` takes ~50s on
  anchor.localhost, so the Stats card (which needs `_detail.envs`) appears
  late — wait on the element in tests, never a fixed sleep. The SVG
  `points`/`y1` console warnings at first paint are the same benign
  pre-hydration parse noise as the existing `{{ n.icon }}` path warnings.

### Site removal: request vs delete (2026-08-12)
v3 had **none** of v1's removal flow, and `data.js` was FILTERING marked sites
out of FLEET — so a marked site vanished entirely: the requester could not
reach it to cancel, and operators had no pending-removal queue. Ported with a
sharper split than v1, per Austin: customers may only REQUEST; operators may
request, cancel, and hard-delete.

- **SECURITY FIX (server-side).** `DELETE /sites/{id}` only ran
  `captaincore_verify_permissions( $site_id )` — a SITE-ACCESS check, not an
  admin check. v1 hid the button behind `role == 'administrator'` in markup
  only, so **any customer owning a site could hard-delete it** (the callback
  dispatches the CLI `site delete` and marks the record inactive).
  `captaincore_site_delete_func` now returns 403 unless
  `( new CaptainCore\User )->is_admin()`, checked BEFORE the ownership test.
  Verified over real HTTP as a non-admin: 403 + "Only administrators can
  delete a site. Request removal instead.", and the row survived. Plain
  function — no classmap regen.
- **Two operations, deliberately different affordances** (site-detail.js):
  *Request* = `POST /sites/{id} { details: { removed: bool } }`, which the
  server merges into details, emails operators
  (`Mailer::send_site_removal_request`) and logs `requested_removal` /
  `cancelled_removal`. Nothing is destroyed. *Delete* = `DELETE /sites/{id}`,
  operator-only, irreversible. Labels differ by role — "Request site
  deletion…" for customers, "Mark for removal…" for operators (an operator is
  queueing their own work, not asking).
- **Hard delete needs a TYPED site name**, not v1's bare `confirm()` — an
  irreversible fleet action deserves more than a stray Return keypress.
  Verified all three paths: wrong name → refused with a toast and zero
  requests; dismissed prompt → nothing; exact name → one DELETE, FLEET row
  dropped, routed back to the list.
- **Marked sites stay in the fleet** (v1 lists them too). They wear a red
  "Removal" chip in the Sites table, and operators get a `removal requested`
  pseudo-label filter chip beside `unassigned` (state `fRemoved`, same shape,
  cleared by Clear filters) — that chip IS the removal queue. Site detail
  shows a bad-soft banner under the meta line with "Cancel removal request",
  plus matching entries in the Overview Tools card. **Note the fleet count
  rises** by however many sites are marked (17 locally) now that they are no
  longer hidden.
- Cancel is available to anyone with site access, matching v1's banner ("If
  that was not your intentions then…") — a customer who mis-clicks should not
  need to email support. Say the word to restrict it to operators.
- Verified live as BOTH roles (real accounts, marking POSTs intercepted so no
  operator emails or ActivityLog rows were written): operator sees mark +
  cancel + permanent delete and the queue chip; customer sees only
  "Request site deletion…", no delete link, no queue chip; banner appears and
  clears on cancel for both. No page errors.

### Stale site identity after Launch (2026-08-12)
Launching a site swaps its `.kinsta.cloud` name for the real domain, but the
site page kept showing the OLD name until a full browser reload. Cause: the
header (`dName`), the Sites row, the ⌘K palette and every ctx menu read the
**FLEET** record, which `hydrate()` builds ONCE — while the tools' `onFinish`
(`_detail = null; loadSiteDetail(id)`) only refreshed `_detail`. Fix is one
shared step, not a per-tool patch: `syncFleetFromDetail(detail)` in
site-detail.js re-points the FLEET row at the authoritative record (name, site
slug, provider, provider_site_id, removed, account, core/visits/storage) plus
`environmentsRaw`/`home_url`/`envs` from the refreshed environment list, and
`loadSiteDetail` calls it from BOTH fetch callbacks. Every refresh path already
funnels through loadSiteDetail, so launch, migrate, rename, terminal sync and
the tools dialogs all inherit it. Verified by renaming the row in the DB and
firing the exact `onFinish` the launch tool runs: header, FLEET row and Sites
list row all followed with no reload (DB restored after).

### Mailgun forwarding verification (2026-08-12)
v3 fetched `GET /domain/{id}/email-forwarding/status` but used only
`has_mx_record` for a row label — it never showed WHICH records were missing,
and had no re-check button, so an unverified domain was a dead end. Ported v1's
panel (domains.js + app.html), no backend changes:

- **Records panel** — "Domain not yet verified" callout, then Sending /
  verification records (TXT/CNAME with Name + Value) and Receiving records (MX
  with priority), each row carrying Mailgun's own `valid` flag as a green ✓ /
  red ✕ and per-field Copy links (`ctxCopy`). Shown only when forwarding is
  active, Mailgun says not-active, and records actually came back.
- **Verify DNS records** → `GET …/status?verify=true`, which asks Mailgun to
  re-check and returns the refreshed record set; toasts verified vs still
  pending.
- **Add records to Anchor DNS** → re-runs `POST …/activate-forward-email`.
  The Constellix injection lives in `Domain::activate_email_forwarding()`, so
  re-running activation is how you (re)push the records — needed when the DNS
  zone was created AFTER forwarding was switched on, or records were edited
  away. Hidden when the domain has no Anchor zone (`noZone`).
- **Payload note:** `state` IS top-level on the status response (also mirrored
  at `mailgun_domain.state`); the records are `sending_dns_records` /
  `receiving_dns_records`. A verified domain (state `active`) correctly renders
  no panel.
- Verified live: real verified domain → no panel (correct); unverified fixture
  → callout + 3 sending + 2 MX rows with mixed ✓/✕, 8 Copy links, both buttons,
  and Verify firing exactly one `?verify=true` call. The DNS-writing inject was
  intercepted and never fired in testing.

### Overview stat tiles: real backup + version counts (2026-08-13)
- **The Backups and Versions tiles sat at an em-dash forever.** Both read
  `real.backups` / `real.qs`, which are lazy — only the History tabs ever
  filled them — so the Overview showed `—` unless you'd already visited the
  tab and come back. `loadOverviewCounts()` (site-detail.js) now prefetches
  both once the environment list resolves, and again on every environment
  switch. Cost is small: `backup list` answers off a cached `list.json` on the
  daemon (**~1.1s for a 2,126-snapshot repo**, measured against prod site 84),
  and quicksaves is a plain DB read.
- **`real.backups ? … : '—'` swallowed a legitimate zero.** New
  `statCount(list)` (data.js) is the shared tile formatter: not-an-array →
  `…` (undefined = never requested, null = in flight), otherwise a
  locale-formatted length. A repo with no snapshots now reads `0`.
- **The tile's subtitle carries the long-term story** — `since Oct 2020`,
  computed from the OLDEST snapshot in the list rather than the newest, which
  is the retention window operators actually want off the Overview. Falls back
  to `nightly + PITR` before the fetch lands. Don't trust list order for this:
  `Site::backups()` usorts with a bool comparator (newest-first only by
  accident), and the times carry mixed offsets (`Z` and `-04:00`), so the min
  is computed with `Date.parse`, not a string compare.
- **Both loaders are now environment-keyed** (`backupsEnv` / `qsEnv`). The old
  `!== undefined` guard alone meant a Production list stayed on screen — and
  in the tile — after switching to Staging. Both also bail if the detail
  changed under them mid-flight.
- Verified live headless on site 84: one intercepted `/backups` call carrying
  a 2,126-row fixture (mixed `Z`/`-04:00` offsets, oldest Oct 2020) rendered
  **Backups 2,126 / since Oct 2020**; the Versions tile rendered `…` while its
  fetch was still open and `584` once it landed.

### Edit site: environments, and three ways to get a staging (2026-08-13)
The Edit site dialog was identity-only (name / provider / SSH key), and the
environment editor was reachable only for whichever environment the header
happened to be showing. A site with no Staging record had **no path at all**
to one from v3 — the Deploy card's "Pull production → staging" silently
`return`s when `realPush` can't find both environments.

- **Environments section in the Edit site dialog.** One row per environment
  (label + `address:port`, red when there are no connection details), each
  opening the existing connection dialog via `openEnvEdit(environment_id)` —
  which now takes a target instead of always editing `currentEnv`. The
  connection dialog renders over the site dialog on DOM order alone (both
  z-index 70, it comes later in app.html), so closing it returns you to the
  site dialog.
- **Pull from <provider>** → `POST /sites/{id}/remote-sync`, the same
  `Site::remote_sync()` the throttled `wp captaincore provider-sync` sweep
  uses. Reconciles address/port/user/password/web-root from the host. The
  route already existed; it now also accepts `dry_run` and echoes back the
  reconciled `environments` so the dialog repaints without a second fetch.
- **Three ways to get a Staging row**, shown only when there isn't one:
  1. **Create staging at <provider>** → `POST /providers/{p}/deploy-to-staging`
     (existing route). Kinsta clones live → staging server-side; the response
     is an operation id, not an environment. `pollProviderActions` now also
     handles `deploy-to-staging`: on completion it toasts and reloads the open
     site detail, because that chain's last server step calls
     `connect_staging()` and the detail in the browser is stale.
  2. **Link existing staging** → **NEW** `POST /sites/{id}/environments/connect`
     → `Site::connect_provider_environment()`. For staging that already exists
     at the host but was never recorded here. Reports `connected` / `none` /
     `exists` / `skipped` rather than erroring, and confirms against the
     environments table instead of trusting `connect_staging()`'s return (it's
     a silent no-op when there's nothing to link).
  3. **Add manually…** → **NEW** `POST /sites/{id}/environments` →
     `Site::create_environment()`. Reuses the connection dialog in create mode
     (a draft with no `environment_id`; save POSTs instead of PUTs, the title
     reads "Add … connection", the delete link is hidden). "Preload from
     Production" works here, which is the whole point of it.
- **Backend guards.** `create_environment()` writes every column explicitly
  (a partial payload leaving NULLs breaks the CLI's rclone/ssh config
  generation), rejects a duplicate environment name with a 409, refreshes the
  sites-table environments cache, and kicks a background `site sync` so the
  CLI has configs for the new connection before anyone deploys against it.
  Passwords are taken raw, matching the PUT handler — `sanitize_text_field`
  mangles legitimate characters.
- **Two bugs found on the way.** The environment DELETE handler never
  refreshed the environments cache, so a deleted environment kept showing in
  the fleet listing. And `Kinsta::connect_staging()` warned and looped over
  null for a stale `provider_site_id` (site deleted or moved between Kinsta
  companies), or with no Production row to copy credentials from — both now
  bail early.
- Verified live headless on site 84 with every writing endpoint intercepted:
  section renders with the Production row + all three staging actions;
  "Add manually…" → preload → save posts
  `{environment:"Staging", address, home_directory, username, password,
  protocol:"sftp", port}`; "Link existing staging" posts
  `{environment:"Staging"}` and a `none` reply renders the info toast;
  "Pull from Kinsta" posts to `remote-sync` and toasts the change count;
  the Production row opens "Edit Production connection" **without** a delete
  link; "Create staging at Kinsta" posts
  `/providers/kinsta/deploy-to-staging {site_id}`. Server-side the create,
  duplicate-409, link, and delete paths were exercised through
  `rest_do_request` against the local DB copy (including a real read-only
  Kinsta lookup that correctly answered `none` for a site with no remote
  staging, and `connected` for one that had an unlinked staging — reverted
  after).

### New site → "Import from provider" is real now (2026-08-13)
The Import tab was still design-sample: three hardcoded fake domains, provider
chips that were four literal strings, a fabricated "+$45/mo" billing preview,
and a CTA that fired `runJob('provider-import', …)` — a fake job, no request.
Both backend routes already existed and are now wired end to end.

- **Provider chips are the real connected providers**, by NAME not by type —
  a fleet can have two Kinsta rows (different companies/API tokens), and the
  hardcoded chips couldn't express that. `Provider::all()` gained a
  **`supports_import`** flag (`method_exists($class, 'fetch_remote_sites')`);
  only Kinsta and GridPane implement it today, so the email/DNS/analytics
  provider rows never show up. The flag is additive on `GET /providers`;
  settings.js ignores it.
- **`GET /providers/{id}/remote-sites`** fills the list (~8s and 716 rows for
  one of the connected Kinsta providers, so: an explicit loading row, a search
  box, and a **60-row render cap** with a "search to narrow" note — the Sites
  list learned the same lesson about thousands of rows janking a re-render).
- **Rows already in the fleet render dimmed with a green check and "Already
  connected"** and refuse selection. `import_sites()` skips them by
  `provider_site_id` anyway; this says so before you click.
- **`POST /providers/{id}/import { sites, account_id }`.** The selected
  **remote-site objects are sent back VERBATIM** — `import_sites()` forwards
  each one to the provider's `enrich_imported_site()`, which reads
  provider-specific fields (GridPane needs `server_ip` + `system_user_id`), so
  a trimmed `{remote_id, name}` payload would silently import sites with no
  SFTP details. Account is required client-side (the server takes
  `intval($request['account_id'])`, so a missing one would quietly import
  everything into account 0).
- **The Import tab is now operator-only** — it assigns sites to accounts and
  bills them. Customers don't see the tab at all.
- **Fetches are kicked from the tab switch and the provider chips
  (`primeImport`), never from `computeNsImport`** — compute\* runs during
  render, and a setState from there is a React no-no. This is the pattern for
  any future lazy dialog data.
- Verified live headless with only the write intercepted (`/providers` and
  `/remote-sites` hit the real backend and the real provider API): both
  provider chips render by name; the loading row shows during the ~4s fetch;
  716 rows with the cap note; search narrows to 19 matches with connected vs
  importable correctly split; selecting two flips the CTA to "Import 2 sites";
  clicking Import with no account picked is refused with a toast and fires
  ZERO requests; after picking an account the POST body carries the two full
  remote-site objects (`remote_id`/`name`/`label`/`slug`/`status`) plus
  `account_id`, and the success message toasts. The route itself was also
  exercised server-side with an empty `sites` array (200,
  `{success, imported:0, skipped:0, message}`) — no real import was run.

### Domain delete (2026-08-16)
v3 could tear down the three *zones* (DNS / forwarding / sending) but not the
domain record itself — leftover from the Domains slice. `DELETE /domains/{id}`
already existed (v1's deleteDomain); it only dropped the Constellix zone and
the local row, and it looked up account links *after* deleting the row so the
activity log never got an account_id.

`Domains::delete_domain()` now cascades, then removes the local record:

| Linked service | When | Remote call |
|---|---|---|
| Mailgun sending | `details.mailgun_id` + `mailgun_zone` | `DELETE v3/domains/{mailgun_zone}` |
| Email forwarding | `details.mailgun_forwarding_id` | `DELETE v3/domains/{apex}` (skipped if sending already used the same zone) |
| DNS zone | `remote_id` | Constellix `DELETE domains/{remote_id}` |
| Account links | any pivot rows | `captaincore_account_domain` |

Remote "already gone" responses are treated as success so a retry doesn't
stick. Other remote errors land in `warnings[]` and the local row is still
removed (the operator's intent is to drop it from CaptainCore). Registrar
registration is **not** cancelled; Hover auto-renew still turns off (v1
behavior), with `renew_off()`'s leftover echo swallowed so it can't corrupt
the REST body.

UI (operator-only, matching account delete + zone teardown): **Delete
domain…** on the domain-detail header, plus a danger entry on the list
context menu. Confirm names whichever linked services the open detail
actually has. Success drops the row from `DOMAINS`, toasts, and routes
back to the list.

v1 customers who already had the button keep working — the route is still
`captaincore_permission_check` + `Domains::verify()`, not admin-only. The
handler now maps `{errors}` to a 403/404 `WP_Error` instead of a 200.

### Overview rows copy on click (2026-08-18)
Credentials and Environment rows on site Overview share the Domains-row
affordance: the whole row is the hit target (`cursor:pointer`, hover
`--panel-2` + `--brand-ink`). Click copies the field value; the existing
Copy mark still flips to `Copied ✓`. Password rows keep a Show/Hide
control (stopPropagation) so reveal is no longer tied to clicking the
masked value.

### Domain detail links its account (2026-08-18)
`GET /domain/{id}` already returns `accounts[]` (v1's Shared With cards).
The names were jammed into the status line as plain text, so there was
no way to open the account. They now render under the title as
clickable brand-ink links (`openAccount`). Status stays DNS + registrar.

### Account names decode WP entities (2026-08-18)
Site Overview → Accounts card (and the Accounts list/detail) showed
`&#038;` literally because WordPress stores `&` as that entity and the
interpolated text then escaped the ampersand. `decodeHtml()` runs at
hydrate / shared-with / account-detail so the name renders as `&`.

### DNS loading affordance (2026-08-23)
While the zone fetch runs, the DNS tab shows a spinner beside the existing
"Loading DNS records…" notice AND five `.cc-skel` shimmer rows inside the
otherwise-empty records card (`dnsSpin` — also on while saving —
`dnsSkelShow`/`dnsSkelRows` in domains.js). Verified with a stalled fetch:
loading state renders mid-stall, everything clears when the rows land.

### DNS sub-record editor + rename trim (2026-08-23)
- **DNS records edit as structured sub-values** (legacy-editor parity).
  `dnsRowFromApi` keeps the joined display string but now also carries
  `subs` — MX priority/server pairs, SRV quads, round-robin `{value}` lists
  for A/AAAA/TXT/NS/… (CNAME/HTTP stay single-input via `DNS_SINGLE_TYPES`).
  Editing a multi-value row renders per-value input rows (✕ per row when >1,
  "+ Add value"; inputs seed via suid-keyed refs so removing a middle row
  can't leave stale text in reused DOM nodes — the defaultValue trap).
  Done drops empty rows and stages `subs`; save sends the structured API
  shape. **This kills a real corruption class**: the old join-then-split-
  on-comma round trip shredded any TXT value containing a comma into
  multiple entries. The add-record bar still takes the string form.
- Verified against the REAL Constellix zone for a test domain — an
  interception glob failed on the first headless pass, so an MX priority and
  a TXT value actually round-tripped through Constellix and back (then were
  restored to the original values): pairs edit cleanly, the comma TXT stayed
  ONE value, PUT payloads carry `[{server,priority:int}]` / `[{value}]`.
- **Account rename editor trims** the stored leading/trailing whitespace on
  seed (accounts with names like " \tsquarepegengr.com" showed a gulf of
  space before the text); accName display trims too.

### Account detail: inline rename (2026-08-23)
Pencil beside the account name (shown to operators and the account owner,
matching the route's `verify_account_owner` gate) swaps the h1 for an inline
editor — Enter/Save commits, Esc/Cancel backs out (`accRename` is in
closeAllDialogs; openAccount resets it). Save PUTs the EXISTING
`/accounts/{id}` route, which was **hardened to partial-safe writes**: it
used to write `billing_user_id` unconditionally, so a name-only payload
would have nulled the billing user (v1 always sent both — v3 still sends it
when set, for parity). Renames trim, the header + hydrated ACCOUNTS row
patch immediately, then the detail reloads. Data quirk hit while testing:
account 3900's stored name began with whitespace (" \tsquarepegengr.com")
— renames normalize that now. Verified live: pencil → seeded/focused/
selected editor → Esc cancel → Enter rename persisted to header, list row
and DB → restored.

### Profile: first/last name fields (2026-08-23)
The profile card gained a "Name" row (First + Last inputs sharing the row,
above Display name), seeded from `CC_BOOT.profFirst/profLast` (raw user meta
— `userFirstName` stays the greeting fallback). `PUT /me/profile` now writes
`first_name`/`last_name`, but ONLY when the payload carries the keys, so
older clients (legacy's profile save) can't blank stored names. Verified
live: fields seed from stored meta, an edited last name persisted to user
meta via the UI save (restored after).

### Profile: managed application-password listing (2026-08-23)
The single fixed-name "Application password · Generate/Rotate" card became a
full management listing (Minn Admin's AI-Access card): every WP application
password with name + "created <date> · last used <date>/never used"
(`fmtApDate` — year only when not current), per-row **Revoke** (confirm),
and a name input + **+ New password** footer. Create reveals the plaintext
ONCE in a brand-soft row (name + mono password + Copy + shown-once warning);
it never comes back. Backend: `User::list_application_passwords/
create_named_application_password/revoke_application_password` (existing
class — no classmap regen) behind new self-scoped plural routes
`GET|POST /me/application-passwords` + `DELETE …/{uuid}`; the singular
fixed-name routes stay for the legacy dashboard. Verified live end-to-end:
6 real rows listed, created a named password, its plaintext authenticated
via HTTP Basic (200 on /wp/v2/users/me), row showed "never used", revoke
removed it and the credential stopped authenticating (401); no leftovers.

### Per-user legacy-dashboard preference (2026-08-23)
Users can opt back into the old interface per user, from either side:

- **User meta `captaincore_legacy_ui`** via new `POST /me/legacy-ui
  {enabled}` (self-scoped). `Router::load_template` serves core-legacy.php
  for logged-in users with the meta; **`?ui=v3` overrides the stored
  preference per request** (and `?ui=legacy` still forces the other way),
  so neither side can strand you. Login/welcome/connect routing unchanged.
- **New UI**: Profile → "Legacy dashboard" card (Minn switch, above Active
  sessions; `CC_BOOT.legacyUi` seeds it — normally false, true only when the
  page was forced via ?ui=v3). Turning it ON saves then reloads into the old
  interface.
- **Legacy UI**: Profile → "Interface" section (v-switch, `legacy_ui` data
  prop seeded from user meta by PHP, `toggleLegacyUi()` beside
  updateAccount). Turning it OFF redirects into the new dashboard.
- Verified live round-trip as a real customer: v3 toggle → legacy served →
  ?ui=v3 override (CC_BOOT.legacyUi true) → legacy Profile switch shows ON →
  toggle off → lands hydrated in the new UI; meta empty at the end.

### Sites view: right-click default (2026-08-23)
Right-clicking the Table/Cards/List toggle opens a ctx menu (theme-toggle
pattern: `openSitesViewMenu`, ✓ on the current pick) — "Default: Table /
Cards / List". Picking stores `cc-sites-view` and switches immediately;
`state.view` boots from the stored value. Verified live: pick → applied +
stored, reload boots into Cards, check mark follows, restored to Table.

### Type scale tokenized + bumped (2026-08-23)
"Font size overall still feels too small" — the structural answer, not
another spot-bump: font sizes were the one thing the token system never
covered (1,400+ inline `font:` declarations with literal px). All sizes in
the 11–15.5px band now reference **`--fs-*` tokens** in the helmet `:root`
(mechanical sweep: `font:<w> 12.5px …` → `font:<w> var(--fs-125) …`).
**Token names carry the ORIGINAL design px size; values carry the current
scale** — so resizing the whole app is a token edit, exactly like the color
rule. Current values: the 12–15px reading band sits one step higher
(12→13, 12.5→13.5, 13→14, 14→15…); ≤11.5px micro-labels/chips and ≥16px
headings are unchanged to preserve hierarchy. New markup must use the
tokens, never literal px in that band. Verified live: tokens resolve
(row titles 15px, nav 15.5px), table/nav/dock/dialogs render clean in both
themes, no page errors. The standalone login page keeps its own (already
larger) scale.

### Billing fixes + Settings hardening for customers (2026-08-23)
Three fixes from a switched-session review:

- **Invoice view/PDF 403'd rightful owners.** Both `/invoices/{id}` and
  `…/pdf` authorized non-admins ONLY via the order's `captaincore_account_id`
  meta — which most orders never received, so owners saw "Could not load
  invoice". The check now leads with WooCommerce ownership
  (`$order->get_customer_id() === get_current_user_id()`), meta as fallback.
  Verified: owner 200 (detail + real PDF), a DIFFERENT customer still 403.
- **Billing-address dialog type bumped** to the standard dialog scale
  (labels 13px, inputs 14px/36px — were 12/12.5px/32px).
- **Settings hardening.** Customers now get exactly two tabs — Providers
  (self-scoped) + Cookbook — with a forced fall-through for any other tab id.
  Site defaults, SSH keys (the fleet management key + rotate control) and
  Handbook are operator surfaces. Server-side to match: `GET /defaults/`
  (fleet default users incl. usernames), `GET /keys/` (fleet key list — the
  callback already returned `[]` but the route now 403s outright) and
  `GET /processes/` (the ENTIRE internal ops handbook, previously readable by
  any logged-in customer via REST) are all `captaincore_admin_permission_check`
  now. loadSettings skips those three fetches for customers. Legacy's
  admin-only screens are unaffected; a legacy customer fetch just 403s into
  an axios catch. NOTE for tests: `rest_do_request` 404s trailing-slash
  paths — probe `/captaincore/v1/keys`, not `/keys/`.

### Cookbook: Mine / System scope tabs (2026-08-23)
The Settings Cookbook list gained a segmented **Mine / System** toggle (counts
in the labels, `cookScope` state). Split is on the `public` flag — which
doubles as the ownership split, since non-admins can never own a public
recipe. **Defaults differ by role, deliberately**: customers land on Mine
(their scripts are what they manage; empty state carries a "+ New recipe"
CTA), operators land on System (the fleet library is their working set).
Saving a recipe lands you on the tab it lives in. Verified live as both
roles: customer Mine (0) with CTA → create → lands on Mine with the row →
System tab shows 21 public rows with zero Edit links; admin defaults to
System (21, all editable) with Mine (11) all private.

### Settings mock flash + Intercom/dock corner conflict (2026-08-23)
- **Settings no longer flashes design samples.** computeSettings was the one
  screen the global mock-flash rule missed — fake providers (Kinsta/WP
  Engine/…), recipes, handbook rows, defaults, key rows and swatches all
  rendered until `realSettingsVals` hydrated. Every sample block now gates on
  `booted` (empty until real data), and `state.brandName` seeds from
  `CC_BOOT.name` instead of the literal sample. Verified by stalling all six
  settings endpoints 4s: zero sample rows/names mid-stall, real data after.
- **Intercom launcher yields the corner to the dock.** Customer sessions load
  the Intercom bubble bottom-right — exactly where the activity dock opens.
  componentDidUpdate now sends `Intercom('update', { hide_default_launcher })`
  on dockOpen transitions ONLY (no churn on ordinary re-renders): launcher
  hides while the dock is open, returns on close; an already-open messenger
  window is untouched. Chosen over moving the bubble left (collides with the
  sidebar user card + switch-back pill) or permanently offsetting the dock
  (wastes the corner for sessions that never open chat).

### Cookbook: customers manage only their own recipes (2026-08-23)
Customers could open Edit on public SYSTEM recipes (screenshot from a
switched session) — the UI showed the code, and worse, `Recipes::list()`
shipped public recipe CONTENT to every customer's browser. Both halves fixed:

- **Server** (`Recipes::list()`): non-admins now get their own recipes plus
  public rows with **content stripped**; other users' PRIVATE recipes are
  omitted entirely (their titles previously leaked as inert "system" rows).
  Write paths were already safe (create/update force `public=0` for
  non-admins; update/delete verify ownership) — verified 403s live.
- **Server** (`/run/code`): accepts **`recipe_id`** and resolves the STORED
  content after an access check (public / own / admin) — so customers can run
  system recipes whose code they can no longer read. Foreign private id → 403.
- **Client**: `runRecipeNow` dispatches `{environments, recipe_id}` (never
  code) for everyone; Settings › Cookbook rows carry `canEdit`
  (`isOp || user_id !== 'system'`) — no Edit link on system rows for
  customers, and Run… routes public recipes through the confirm-run dialog
  instead of inserting code into the terminal. The New-recipe dialog hides
  the Public toggle for customers (dead control — server forces 0).
- Verified live as a REAL customer (11 checks): 21 content-free system rows,
  zero Edit links, no foreign private titles, hidden toggle, own recipe
  create→edit→delete round-trip, confirm-run dispatching by id with no code
  key; admin regression: all rows editable, toggle shown, content intact,
  admin runs also dispatch by id.
- **Legacy caveat**: core-legacy.php inserts `recipe.content` client-side, so
  a customer on `?ui=legacy` now gets an EMPTY insert for public recipes.
  Accepted — legacy is the escape hatch, and the alternative was leaving the
  content leak open.

### List pagination round (2026-08-23)
The three paginated lists (Sites / Domains / Accounts) moved onto ONE shared
footer builder — `pagerVals(prefix, stateKey, …)` + `pageSize()`/
`setPageSize()` in app.js — so they can't drift:

- **Per page 25 / 50 / 100 / 250** segmented pill in the footer (default 25 —
  briefly shipped as 100, walked back same day per Austin). One preference for
  all three lists, persisted in localStorage `cc-page-size`; changing it
  resets every list to page 1.
- **Range label**: `1–100 of 2,941 sites · page 1 of 30` (locale-formatted)
  instead of "Page 1 of 118".
- **Page changes scroll the main pane back to the top** (`mainRef` →
  `this._mainEl` on the `<main>` scroller) — clicking Next at the list bottom
  used to strand you there.
- **« First / Last »** jump buttons, shown only when there are 3+ pages.
- Footer now shows whenever rows exceed the SMALLEST size (25), not only at
  2+ pages — otherwise the per-page control is unreachable right when you'd
  want to shrink it. Under 25 rows it hides entirely.
- Deliberately skipped: ←/→ keyboard paging (the toolbar search autofocuses on
  every list, so arrow keys belong to the input).
- Verified live headless (11 checks): default 100, pill → 25 + stored, Domains
  inheriting the size, reload persistence, Next scrolling 2000→0, Last/First
  jumps, small-filter footer hide, Accounts on the shared builder.

### Profile → API documentation viewer (2026-08-23)
Legacy-parity port of the API Access docs row. New card on Profile (between
Application password and Active sessions): "API documentation · Markdown
reference for use with coding agents" with **View** and **Download**. Existing
backend only — `GET /me/api-docs?format=html` → `{html}` (server Parsedown,
`{your-site}` pre-substituted) and the bare route streams the raw markdown
with attachment headers. Viewer dialog (`ad*` bindings in profile.js
`apiDocsVals`, spread into BOTH realProfileVals returns): 1000×88vh, 250px TOC
rail built client-side from the rendered h2/h3s (v1's exact slugging,
duplicates suffixed), content injected via ref (`.cc-md .cc-apidoc` — no
innerHTML binding in the DC runtime; `.cc-apidoc` adds document-scale type
over the timeline-scale `.cc-md` base, incl. table + scroll-margin rules).
TOC click scrolls the content container; HTML is cached on `_adHtml` so
reopen is instant; Download builds a blob link (`captaincore-api-docs.md`);
Escape closes via closeAllDialogs (`adOpen`). Mobile: the rail stacks via the
existing `cc-plf-body`/`cc-plf-side` classes. Verified live headless
(10 checks): 202-entry TOC, rendered body with the substituted host, TOC
scroll jump, ESC, cached reopen firing zero refetches, and a real download
whose file starts `# CaptainCore REST API`.

### Checksum rows: home_url + environment chip (2026-08-23)
Core/plugin checksum failure rows titled by `site_name` repeated identically
for production + staging. Rows now title by the environment's `home_url`
(protocol stripped, site_name fallback) with an environment chip — both fields
were already in the REST payloads (`/checksum-failures`,
`/plugin-checksum-failures`); frontend-only fix (`envRow()` in security.js).
Verified with fixture REST responses: prod row shows the bare domain,
staging row shows the kinsta.cloud staging URL, chips on both.

### Sites listing: hydration skeleton + grid page sizes (2026-08-23)
Pre-hydration the sites screen showed an empty table and "0 sites · 0
environments". Now `sitesSkel` (same `booted && !_hydrated` gate as homeSkel)
drives 6 shimmer rows per view via `sitesSkelRows` (empty array once hydrated,
so the markup self-hides) and the count chip reads "Loading fleet…". All three
views (table / cards / list) have matching skeleton markup. Same treatment on
Domains and Accounts (`domSkelRows` / `accSkelRows`, "Loading domains…" /
"Loading accounts…" chips, verified live the same way), plus Billing invoices
(`billSkelRows`), Security vulnerabilities (`secSkelRows`) and Users
(`usersSkelRows`, upgraded from the old 2-bar shimmer to 6 grid-aligned rows).
Billing and Security skeletons stay up through BOTH gates — pre-hydration
(base vals) and each screen's own lazy fetch (`realBillingVals` /
`realSecurityVals` !loaded branches override; the loaded branch omits the key
so the base's hydrated [] clears it). The Security threats endpoint takes
5–10s across the fleet, so its skeleton earns its keep. PAGE_SIZES moved
25/50/100/250 → 24/48/96/240 (divisible by 12 so the auto-fill card grid fills
its rows at 2/3/4/6 columns; a stored legacy size falls back to 24). Verified
live headless with REST responses held 8s: skeletons + chip in all three
views, then a clean flip to `1–24 of 2,941 sites · page 1 of 123` with zero
skeleton cells left.

### Sidebar lockup: split links (2026-08-23)
The brand lockup was one `goHome` click target. Now the anchor glyph is a real
`<a href="{{ homeLink }}">` (CC_BOOT.homeLink = `home_url()`, exposed as
`homeLink` state in app.js) to the site homepage, while the "Anchor Hosting"
wordmark keeps the SPA `goHome` route. Verified live headless: glyph href
resolves to the site root, wordmark click routes /account/sites → /account/
with no page load.

### User API docs expansion (2026-08-23)
`api-docs.md` was a path listing (many endpoints as a method+path stub,
admin routes mixed in). Audited every `register_rest_route` in
`captaincore.php` and rewrote it as a **user** reference — logged-in
account members with an application password, not operators.

Dropped administrator-only surface (users directory, fleet configurations,
archives, security ops, provider credentials, hard site delete, `/jobs/{id}`,
SSH key mutations, account plan/delete, GET `/users` which returns `[]` for
non-admins). Expanded the rest with request fields, curl, response shapes,
and the gotchas a coding agent actually hits: environment casing
(`Production` vs `production`), `/site` vs `/sites` prefixes, API-vs-UI
sync/async (`X-WP-Nonce` absent = wait 5 min unless `"async": true`),
`POST /me/pins` body is the array itself, CLI command table, bulk-tools
taking environment IDs, DNS bulk using Constellix `remote_id`.

New user-facing sections that were missing entirely: Conventions, Sessions,
Files, Performance, logs-archive, sandbox-token / Playground, Site Audits
(request/cancel/coverage — not findings CRUD), Mailgun usage, site-filters,
scheduled reports, activity-log query params, process-log timeline notes.

38 H2 / 157 H3 (195 TOC entries, 83 tables). Viewer unchanged — still
Parsedown + client TOC from h2/h3. Verified live headless desktop +
mobile: host substituted, no leftover `{your-site}`, no admin TOC
sections, Files / Site Audits TOC jumps land on the heading.

### Leading-input autofocus + Archives filter (2026-08-23)
- **`leadFocusRef`** (app.js, beside `ddClose`): the one focus-on-mount ref for
  a screen's LEADING search/filter input. Flag lives on the DOM node so
  re-renders can't steal focus mid-typing; an sc-if remount (entering the
  route/tab) focuses again. **Skipped on phones** (keyboard pop). Wired on:
  Users / Sites / Domains / Accounts / Archives toolbars, terminal target
  picker + cookbook popovers (cookQRef retired into it), push-to-other and
  assign-account dialogs, Add plugin/theme wp.org + Envato tab searches, and
  the New-site Import tab filter. The two dialogs' dead raw `autofocus`
  attributes were dropped (that attribute only fires on document load — the
  reason the palette needed its componentDidUpdate focus, which still handles
  the `ddOpen` popovers). New list screens put this ref on their toolbar input.
- **Archives page gained the standard toolbar filter** ("Filter archives…",
  matches name or yyyy-mm-dd date, "N of total" count line, no-match empty
  state naming the query) **plus a 500-row render cap** — the store is 5,000+
  rows and rendering all of them janks every re-render (the remote-sites
  lesson); the cap is announced in the count line, never silent.
- Verified live headless (12 checks) incl. focus surviving typed re-renders
  and mobile emulation skipping autofocus.

### v3 is the default + standalone login page (2026-08-22)
The rename Austin asked for: `templates/core.php` (old Vue app) →
`templates/core-legacy.php`, `templates/core-v3.php` → `templates/core.php`
(git mv, history preserved). `Router::load_template` now picks:

| Condition | Template |
|---|---|
| `?ui=legacy` or `?ui=v1` | `core-legacy.php` (escape hatch) |
| route `welcome` / `connect` | `core-legacy.php` — logged-out invite/connect flows not rebuilt yet |
| route `login` | **`core-login.php`** (new) |
| everything else (incl. old `?ui=v3` bookmarks) | `core.php` (this UI) |

**`templates/core-login.php`** — standalone login page, no React/DC runtime:
Minn tokens + bundled fonts + the same theme pre-paint and `captaincore-theme`
localStorage key (a theme toggle on the page carries the choice into the app),
brand color/name from Configurations. Drives the existing open
`POST /captaincore/v1/login/` endpoint with full v1 parity: signIn (redirect on
success), TFA field revealed on "Enter one time password." / invalid-OTP,
untrusted-location verification-email info state, and a reset-password mode.
**Reset responses are read as text, never parsed** — the endpoint answers
`true` for a real account and an EMPTY body for an unknown one, and both must
render the same non-enumerating copy. Logged-in visits to `/login` redirect to
the app (that template-level redirect is also what breaks the old
shell→login→shell loop risk). Verified live headless (17 checks): logged-out
redirect → login, both themes, bad-password error, TFA reveal+focus, reset
round-trip, a REAL sign-in with a throwaway subscriber landing hydrated in the
new UI (user deleted after), logged-in `/login` redirect, `?ui=legacy` and
`/account/welcome` still serving the Vue app, and `?ui=v3` bookmarks cleaning
themselves on first navigation. Follow-ups: `welcome`/`connect` rebuilds, and
the login page shows no logo image yet (branding logo upload is still dead).

### UX round: dialogs, cookbook, defaults, handbook, new-site progress (2026-08-22)
Seven fixes from a screenshot review, all verified live headless (28 checks):

- **Branding is operator-only.** `computeSettings` derives isOp and drops the
  Branding tab + pane for customers (a customer sitting on the default
  'branding' state falls through to Providers). The save route was already
  admin-gated server-side; this closes the UI side.
- **Escape cancels every dialog.** New `closeAllDialogs()` on the Component
  class holds the one canonical patch — every modal's open flag or the state
  key its flag DERIVES from (qsDialog, bpPid, esId, plfUid, deployConfirm,
  rgHash, toolDlg…). **A new dialog must add its key there** or Escape won't
  cancel it. Escape peels one layer at a time: rollback sub-dialog → open
  `ddOpen` dropdown → context menu → all dialogs, so Esc inside a dialog's
  dropdown closes just the dropdown (verified).
- **Cookbook popup autofocuses its search** (`cookQRef`, focus flag on the DOM
  node so re-renders can't steal focus mid-typing — the termRef trick).
- **Public recipes never reveal their code.** Picking a public recipe in the
  terminal cookbook opens a Run-recipe confirm (recipe + target set named)
  instead of inserting; Run dispatches the stored content through the normal
  session job path and the scrollback echoes `$ [recipe] <title>`, not the
  code (v1 ran public recipes behind a confirm; private recipes still insert
  for review). The admin Settings › Cookbook tab is unchanged — editors see
  code there anyway.
- **Site defaults default-users editor**: added the missing Last-name input,
  and Role became a pick list (v1's roles vocabulary — lowercase value,
  capitalized label, `WP_ROLES` in settings.js) using the anchored-fixed
  `ddToggleAt` dropdown pattern, one `defRole{i}` key per row.
- **New-site provisioning streams progress into the console.** Create on
  Kinsta starts a manual dock job (expand) and `pollProviderActions` pushes
  one line per provider-action state change (`nsProgress`, deduped via a
  status|step|operation key on the job): building WP install (operation id) →
  site record created / disabling edge caching → image optimization →
  `✓ provisioned at <datacenter>` + finishJob. A chain resumed after a reload
  recreates its row mini-mode ("Resumed tracking…"); a chain that leaves the
  queue without this browser running its final step ends with an honest
  wrap-up line (`nsProgressGone`) instead of spinning forever. Jobs keyed by
  site name on `this._nsJobs`.
- **Handbook rows are fully clickable and editable.** Row click opens the
  viewer (hover affordance); a per-row Edit link (stopPropagation) opens the
  new Edit-process dialog — v1's exact contract: `GET /processes/{id}/raw`
  seeds name / time estimate / repeat (fixed `PROC_REPEAT` vocabulary) /
  quantity / markdown description (textarea seeded via ref keyed by
  process_id), and Save PUTs the raw object back with edits applied, so
  role/created_at/user_id ride through untouched. **Roles have no picker yet**
  — the `captaincore_process_roles` vocabulary has no REST surface.

### Theme: System / Light / Dark (2026-08-18)
Topbar theme button matches Minn Admin. Preference is `light` | `dark` |
`system` in `captaincore-theme`. Click still flips light ↔ dark (from
System it locks the opposite of the current OS paint). Right-click opens
the existing ctx menu: System, Light, Dark, with a check + brand-ink on
the current pick. System follows `prefers-color-scheme` live. First visit
(no stored key) persists System; existing light/dark locks are left
alone. Icon shows the preference (half-circle / sun / moon), not the
next-toggle glyph. A head pre-paint script in `core.php` applies the
resolved surface before first paint. Shared with the marketing theme
(anchor-theme) on the same origin: both read/write this key, so a Dark
pick here is Dark on the public site. A leftover `ah-theme` value is
copied in once, then dropped. A `storage` listener keeps an open
dashboard tab in sync if the marketing toggle changes the key.
