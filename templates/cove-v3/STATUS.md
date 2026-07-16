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
  sentinel → `onFinish`. The activity dock renders `activeJob()`; the dock footer is a
  live terminal (⌘⏎, `/run/code` on the open site's env).
- **`site-detail.js`** — `openSite()` override loads `/sites/{id}/environments|details|
  users`; real overview credentials, env rows, addons, users, logs, env switcher, magic
  login, sync, push/pull. **Env names are LOWERCASE in URL paths.**
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

## Remaining work (rough priority; each is a "slice")

Pattern for every slice: audit the v1 REST contract (subagent over `core.php` +
`captaincore.php` + `app/*.php`), write a `<area>.js` mixin, swap the design's mock
arrays in the matching `compute*()` to consult real data with mock fallback, test live.

1. **Home-screen truth** — the Needs-attention feed, Recent-activity feed, and the
   home jobs list are still design mock. Wire attention to real signals (security
   threats, update-queue, domain expirations, unassigned sites) and activity to
   `/activity-logs`.
2. **Stats tab** — Fathom analytics is entirely mock. Endpoints: `/sites/{id}/fathom`,
   `/sites/{id}/stats[/share]`. Charts (Chart.js) not yet vendored for v3.
3. **Domains / DNS / Email** — `computeDomains`/`computeDomain` are mock. DNS record
   editor, registrar (Hover/Spaceship), email forwarding (Mailgun routes, U+200B guard),
   Mailgun sending. Big surface — see spec §7.5.
4. **Accounts / Users / Access** — `computeAccounts`/`computeAccount` mock. Account
   detail tabs, 4 access levels, invites, transfer ownership, trusted devices (backend
   exists, zero UI), self-service profile (TFA/app-password/sessions). Spec §7.6.
5. **Billing / Subscriptions** — `computeBilling` mock. Stripe/WooCommerce: payment
   methods, invoices+PDF, plan editors, admin subscriptions. Spec §7.7. Gated by
   `modules.billing`.
6. **Security & Site Audits** — `computeSecurity`/`computeAudits` mock. 7 security tabs,
   threat tracking, checksums, coverage, audit queue. Spec §7.8. Admin-gated.
7. **Reports** — `computeReports` mock. Site/account preview/send/schedule. Spec §7.9.
8. **Settings** — `computeSettings` mock. Branding, providers+wizard, defaults, SSH
   keys, cookbook, handbook. Spec §7.10.
9. **Archives (global)** — `computeArchives` mock. Rclone list, store-from-URL with
   EventSource progress, 7-day B2 share links. Spec §7.11.
10. **Profile** — `computeProfile` mock (TFA QR, app password, active sessions).

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

### Known nits
- Terminal input text doesn't visually clear after a run — the DC runtime binds
  `value` like `defaultValue` (uncontrolled). Cosmetic; the command does dispatch and
  the field is cleared in state.
- `/sites` list records carry no theme/plugin/update data, so those Sites-list filters
  render empty until wired to `/filters/sites`.

## Recent commits
- `89e4ed8` NEW: initial cove-v3 template behind ?ui=v3
- `d954536` IMPROVE: fork into maintainable source (templates/cove-v3/)
- `941ff15` NEW: site detail on real data + live terminal streaming
- `914e10d` NEW: Version & Recovery slice on real data
- `f7a8f2a` IMPROVE: activity dock — selectable job history, ⌘⏎, collapsed pill
