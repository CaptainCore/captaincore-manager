# CaptainCore Manager v1.0 — core.php → core/ parity audit

Phase 1 of `V1-PLAN.md`. The unit of audit is the **REST call** and the **dialog**;
every one carries a disposition: **DONE** (exists in v3, cited) · **DROPPED**
(deliberate, with the why) · **GAP-BLOCKER** (build before 1.0) · **GAP-POST-1.0**
(real, but `?ui=legacy` covers it).

Since the plan was written the default already flipped: `templates/core.php` now
serves v3 and the old Vue app lives at `templates/core-legacy.php` behind
`?ui=legacy`. That makes this audit a *release gate* rather than a *flip gate* —
a GAP-BLOCKER here blocks 1.0, not the flip.

## Surface measured

| | legacy (`core-legacy.php`) | v3 (`core/*.js`) |
|---|---|---|
| lines | 26,549 | 8.5k across 26 mixins + `app.html` |
| REST calls | 315 `axios.*` | 216 `this.api()` / `fetch()` |
| distinct methods issuing REST | 285 | 166 |
| distinct routes called | 204 | 165 |
| dialogs | 67 `dialog_*` | — (screens + inline panels) |

Registered REST surface: **298** routes in `captaincore/v1`.
Route overlap: **149 shared** · **55 legacy-only** · **16 v3-only** · **36 called by neither**.

The v3-only routes are new backend surface built during the rebuild (file manager,
logs-archive browser, per-record DNS writes, application passwords, site identity /
environment editing, stats top-pages/referrers, site-filters) — they need no
disposition, they are strictly additive.

## Method

Mechanical extraction (scripts in the session scratchpad), not impressionistic:

1. Balanced-paren capture of every `axios.*(` in `core-legacy.php` and every
   `this.api(` / `fetch(boot.restRoot` in `core/*.js`, each tagged with its
   enclosing method name and line.
2. URL expressions normalized (template literals and `+` concatenation collapsed
   to `{p}` placeholders), then **matched against the 298 registered
   `register_rest_route()` patterns** so that a literal call (`/providers/kinsta/…`)
   and a parameterized one (`/providers/{p}/…`) resolve to the same route identity.
3. 20 fully-dynamic URLs (built from a variable path, e.g. `reportBase()`,
   `magicLogin`, `deleteZone`) were hand-resolved by reading each call site.
4. Each residual row classified against the v3 mixins + `app.html` + `STATUS.md`,
   requiring a v3 `file:line` for every DONE.

---

## Headline finding — six live controls wired to the design mock

v3 keeps the Claude Design sample data as a pre-hydration fallback, and each
`compute*` block binds its buttons to `runJob()` — a local function that only
pushes a fake row onto the dock (`app.js:812`). On hydration, `real*Vals(s)`
spreads over those keys and replaces them with real calls. That pattern is sound
and is used correctly in ~50 places.

**Six bindings never get a real override**, so on production data the control
renders, animates a dock job, and does nothing:

| control | binding | rendered at | consequence |
|---|---|---|---|
| ~~"Request changes" (account Plan)~~ **CLOSED 2026-08-23** — real override in accounts.js: dialog collects the request, POSTs `/billing/request-plan-changes` with the v1 `{subscription}` contract. Verified live (network POST + Mailer path). | `planRequest` app.js:449 | app.html:2283 | was: nothing reached ops |
| ~~"Update all (N)"~~ **CLOSED 2026-08-23** — real path dispatches the managed CLI `update` for the site via `/sites/bulk-tools` (new `update` tool case). Count is now computed from the fleet update-queue targets across both kinds (downgrades never offered). Verified with stubbed dispatch. | `doUpdateAll` | app.html:1129 | was: mock |
| ~~"Queue audits for 9 stale sites"~~ **NOT A LIVE DEFECT** (corrected 2026-08-23) — `covShowActions: false` in both realSecurityVals branches gates the button off on real data (app.html:2584 sc-if). Design-sample only. |  |  |  |
| ~~"Run update-before-audit steer queue"~~ **NOT A LIVE DEFECT** (corrected 2026-08-23) — same `covShowActions` gate as above. Design-sample only. |  |  |  |
| ~~"Rotate…" (Settings › SSH keys)~~ **CLOSED 2026-08-23** — the whole Management-key card was a design sample (hardcoded fake fingerprint, no rotate route in v1 either). Now hidden whenever CC_BOOT exists (`keysShowMgmt`); the real "Your public keys" list is untouched. Verified live. | `rotateKey` app.js:737 | app.html:2886 | was: fake fingerprint + inert Rotate shown on real data |
| ~~"Renew now" (domain › Registrar)~~ **NOT A LIVE DEFECT** (corrected 2026-08-23) — button gated on `regWarn`, and the real branch sets `regWarn: false` / `regShowRenew: false` (domains.js:639). Only the Auto-renew toggle is inert on real data, and that is the deliberate no-route hide recorded in STATUS.md; transfer lock + WHOIS privacy use the real `regToggle`. |  |  |  |

Three more sit one level up — a whole flow bound to `runJob`:

| flow | binding | consequence |
|---|---|---|
| ~~New site › **Request** tab~~ **CLOSED 2026-08-23** — real POST when hydrated (site-requests.js `submitSiteRequest`) | `nsCreate` app.js:1120 | was: silently discarded |
| New site › **Manual** (Connect site) | `nsCreate` app.js:1123 | a non-Kinsta site cannot be onboarded at all |
| ~~Sites list **bulk actions**~~ **CLOSED 2026-08-23** — real path resolves each selected site's Production environment id and POSTs `/sites/bulk-tools` (sync-data / update / backup / apply-https / scan-errors) after a confirm; toast reports dispatch. Verified with stubbed dispatch (2 sites → 2 env ids). | `bulkActions` | was: all no-op |

`importZone` (app.js:1695) is also mock-bound but is **not a defect** — the key is
dead, real zone import runs through `zoneAppend`/`zoneReplace` (app.js:1666-1667,
domains.js:633) off `parseZone()`.

Method for the table above: every key bound directly to `runJob()` in `app.js`,
cross-checked for a same-named key in any `real*Vals()` across all 26 mixins.

**A "verified live" note in STATUS.md does not catch this class** — the screen
renders and the dock animates, so the failure is invisible without watching the
network tab. Every row above should get one real execution in Phase 3d.

---

## GAP-BLOCKER — must close before 1.0

| item | what v1 does | evidence |
|---|---|---|
| ~~`GET /invites` + `POST /invites/accept`~~ **CLOSED 2026-08-23** — `Router.php` now diverts base-path URLs carrying `?account=&token=` to `core-legacy.php` (same pattern as `welcome`/`connect`); verified live that an invite-style URL serves the legacy shell (whose 14724 flow reads those params) while the plain base path still serves v3. Fixes links already sent, too. | invite landing: preview account's sites/domains, then Accept | **Invite emails were broken on v3.** `app/Account.php:498` builds the link as `home_url() . {path} . "?account={id}&token={t}"` — the base route, which `app/Router.php:126` serves with `core.php` (v3). v3's `router.js` has zero `account`/`token` handling (grep clean), so the link opens the dashboard and the invite is unreachable. `Router.php:114-117` only diverts `welcome`/`connect` to legacy — not this URL. A newly invited customer cannot join an account. |
| ~~`POST /site-requests` + operator queue~~ **CLOSED 2026-08-23** — new `site-requests.js` mixin: Request tab POSTs `/site-requests` (v1 contract, client stamps created_at + step); Sites screen renders the queue for both roles — operators get Continue/Back, a Modify dialog (URL/name/notes → PUT `/site-requests/update`) and Finish/Cancel (delete); customers see and can cancel their own. Added GET `/site-requests` for the initial list (v1 embedded it at boot; same permission callback, admins see all users). Verified live end-to-end: create → continue ×2 → modify URL → finish, all five writes observed on the network with user_id riding admin writes. `/site-requests/back` + `/update` came along for free (no longer post-1.0). | customer requests a site; operator advances 1→2→3 and closes it out | was: mock-bound with no queue |
| manual **Add Site** (`dialog_new_site`) | full per-env credentials, SSH key, offload, env vars → `POST /sites` | v3 collects name/address/user/pass/env-count (app.html:4396-4404) then calls mock `runJob('connect-site')` (app.js:1123). No offload or `environment_vars` fields exist in v3 at all. Operators cannot onboard a non-Kinsta site |
| `GET/POST/DELETE /sites/{id}/{env}/domains` + `PUT …/domains/primary` | Kinsta/Rocket.net domain mappings: list, add, delete, set primary, DNS verify | zero hits in `templates/core/*.js` or `app.html`; site tab groups (app.js:2470-2482) have no domains tab. v1 entry core-legacy.php:5200 `showDomainMappings()`. Nothing in STATUS.md. Attaching a domain to an environment is impossible in v3 |
| `PUT /sites/{id}/settings` (`dialog_update_settings`) | per-env managed-update policy: on/off + excluded plugins/themes | v3 renders it read-only (site-detail.js:138); no `updates_exclude_plugins`/`updates_exclude_themes` binding anywhere. An operator cannot exclude a plugin that breaks a site from managed updates. No STATUS.md rationale |
| ~~`POST /billing/request-plan-changes`~~ **CLOSED 2026-08-23** — see the headline table; wired with a request dialog. | customer requests a plan/interval change; server notifies ops | was mock-bound |
| sites-list bulk selection + `/sites/bulk-tools` | multi-site tools | `bulkActions` is a mock (app.js:1052). Already a known blocker in V1-PLAN |
| ~~whole-site update ("Update all")~~ **CLOSED 2026-08-23** — see headline table row above. | update every pending component | was mock |
| ~~per-addon updates~~ **CLOSED 2026-08-23** — `realAddonSrc` now resolves each addon's `latest` from the fleet update-queue (`uqUpdateTarget`, newer-only), lighting the per-row "Update to X" ctx action, which runs `wp plugin|theme update <slug>` on the site via `/run/code`. The fleet-wide steering table (`POST /update-queue/run`) remains legacy/post-1.0 — the per-SITE need this blocker described is met. | steer one plugin/theme | was: queue read only for the home badge |

## GAP-POST-1.0 — real, but `?ui=legacy` covers it

**Operator analytics / queues:** `/subscriptions`, `/subscriptions/{id}`,
`/upcoming_subscriptions` (revenue, renewals — STATUS.md:414) · `dialog_month_renewals` ·
`/billing/ach/pending` + `/billing/ach/admin-verify` (admin micro-deposit verification) ·
`/component-queue` (unaudited-hash queue) · `/web-risk-logs` · `/security-patches` (patches
table) · `/running` + `/listen-processes` (fleet process monitor, STATUS.md:786-790) ·
`dialog_log_history` (fleet process-log table — `GET /process-logs` is unpaginated,
STATUS.md:802).

**Per-site config an operator sets rarely:** `/sites/{id}/backup` (backup interval/mode —
v3 hardcodes `backup: 'Direct'`, data.js:62) · `/sites/{id}/fathom` (tracker editor; v3 only
reads) · `/sites/{id}/{env}/monitor` (uptime toggle — read-only at site-detail.js:137) ·
`/sites/{id}/{env}/captures` + `/captures/new` (capture config + on-demand capture) ·
`/accounts/{id}/defaults` (per-account defaults; v3 has global only, settings.js:306) ·
`dialog_copy_site` (`command:'copy'` absent) · per-env `environment_vars` and offload
(absent from v3 entirely).

**Domains:** `/domain/{id}/email-forwarding/logs` + `dialog_forwarding_log_details` ·
`dialog_mailgun` (full event log — "View all logs →" at app.html:2053 is an inert span) ·
`dialog_mailgun_details` · `PUT /domains/{id}/account` (domain→account assignment,
STATUS.md:809-811) · `POST /domain/{id}/update-site-link`.

**Smaller:** `PUT /keys/{id}` + `/keys/{id}/primary` (SSH key edit/set-primary; only DELETE is
real, settings.js:80-82) · `POST /me/pins` (v3 pins are `localStorage` only, app.js:2568-2583 —
they no longer follow the user across devices, and per-environment granularity became
per-site) · `POST /me/email-subscriber` (Profile → Notifications section absent) ·
`/plugin-diff-preview` (checksum rows show the modified-file list as text, security.js:110-117) ·
`/quicksaves/search` · `/sites/{id}/site-audits` (per-site audit history — the fleet Audits
screen is operator-gated, so **customers lose access to their own audits**) ·
`dialog_breakdown` (next-renewal overage estimate) · nonce-expiry retry (v1 re-scraped a
nonce and replayed; v3 `api()` just throws, data.js:13 — a reload recovers) ·
`/site-requests/back` + `/site-requests/update` (operator stepper conveniences).

## DROPPED — deliberate, with the reason

| item | why |
|---|---|
| `POST /dns/{id}/bulk` | replaced by per-record `POST/PUT/DELETE /dns/{id}/records[/{rid}]` (domains.js:158-161). Deliberate: v1's bulk `{id}` is the Constellix `remote_id` — an audited trap (STATUS.md:273-275) |
| `PUT /sites/update` | monolithic save split into scoped routes on purpose — it deletes any environment it doesn't carry (site-detail.js:416-419, :474-478). Now `/identity`, `/accounts`, per-env PUT/DELETE, `/environments/connect` |
| `POST /providers/kinsta/deploy-to-production` | replaced by provider-agnostic `POST /sites/environments/push` (site-detail.js:266-278) |
| `POST /sites/{id}/grant-access` | replaced by the Assign… picker → `PUT /sites/{id}/accounts` with `shared_with` (site-detail.js:434, :455-463) |
| `/me/application-password` + `/rotate` | superseded by named multi-password routes (`/me/application-passwords`, profile.js:39/89/100). Rotate = create new + revoke old |
| `POST /billing/cancel-plan` | control deliberately hidden on real data (`accShowCancel: false`, accounts.js:296); cancellation is out-of-band. **Note:** STATUS.md:459-460 justifies the hide with "neither has a v1 route" — that reason is factually wrong, the route exists. Worth correcting the comment |
| `POST /invites/accept` (logged-out flow) | `welcome`/`connect` deliberately still served by legacy (Router.php:114-117) — but see the invite BLOCKER above, which is a *different* URL |
| `POST /login` (`createAccount`) | invite signup stays on legacy with the flows above; sign-in/reset moved to `core-login.php:190-221`, sign-out to WP's logout URL |
| `PUT /sites/{id}/mailgun` (`dialog_mailgun_config`) | dead in v1 too — `saveMailgun` @25367 has no caller. v3 keeps Mailgun domain-scoped |
| `dialog_account_portal` | dead in v1 — its opener `editAccountPortal()` and submit `updateAccountPortal()` are **never defined**; the dialog could not open. Nearest v3 surface is global Settings › Branding |
| `dialog_theme_and_plugin_checks` | dead in v1 — no opener, and `savethemeAndPluginChecks()` is never defined; its table rows are a hardcoded literal |
| `dialog_job`, `dialog_bulk`, `dialog_new_site_rocketdotnet`, `dialog_processes` | dead state in v1 — declared, never rendered. (v3 does have the capability behind the last one: processes.js:12/19/73) |
| `POST /provider-actions` (initial fetch) | v3 seeds the same chain from `/provider-actions/check` on mount (app.js:2307) |
| `GET /environments` | v3 builds the console target list from hydrated fleet data (terminal.js:16-25) instead of a second round-trip |
| `GET /users/{id}/accounts` | `/users/` rows already carry `account_ids` (users.js:73-80) |
| `POST /domains/import` | zonefile parsing moved client-side (`parseZone`, app.js:1137-1152) |

## DONE with a delta worth knowing

These are ported and working, but v3 does less than v1 did. None blocks 1.0;
each is a one-line answer if a customer asks "where did X go?".

| item | delta |
|---|---|
| `dialog_request_audit` | v3 hardcodes `environment: 'Production'` and `report_type: 'security_audit'` (security.js:202); the other 5 report types render as labels only, and the notes field is gone |
| `dialog_new_log_entry` | `process_id` hardcoded to `0` — no Handbook-process picker; no multi-site or site-less entry (v1 `showLogEntryBulk`/`showLogEntryGeneric`). v3 adds file-diff attachments |
| `dialog_edit_log_entry` | only description + attachments are editable; `created_at`, process and site list are not |
| `dialog_invoice` | pays with the account's **default** method only — v1's ad-hoc new-card Stripe Elements path is absent (STATUS.md:413) |
| `dialog_backup_snapshot` | single-choice component filter (v1 was multi-select), recipient fixed to `CC_BOOT.userEmail` |
| `dialog_new_site_user` | no "Generate password" button |
| `dialog_performance_monitor` | inline SVG replaces Chart.js, so "Reset zoom" is gone |
| `dialog_mailgun_usage` | CSS bar strip replaces the Chart.js chart (STATUS.md:980) |
| `dialog_store_archive` | SSE progress stream replaced by a 4s poll (archives.js:33) — deliberate, STATUS.md:443 |
| `dialog_archive_link` | dialog collapsed into a one-click copy-to-clipboard (archives.js:44) |
| `dialog_captures` | browser is read-only: no capture-page/basic-auth config card, no "Check for new Capture" |
| `dialog_edit_account` | the `account_portal_id` picker is gone (it belonged to the dead portal feature) |
| `dialog_edit_process` | no role picker — `captaincore_process_roles` has no REST surface, so `roles` rides through untouched (settings.js:204) |
| `dialog_connect` | v1's client-side cost preview was removed on purpose as fabricated (STATUS.md:1499-1503) |
| `dialog_mailgun_deploy` | the per-row magic-login shortcut in the target picker is not carried over |
| `dialog_toggle` / `dialog_apply_https_urls` | single-site only; the bulk variants ride with the bulk-tools blocker |

## DONE — clean ports

**149 of the 204 routes v1 calls are called by v3 as well.** Those need no
per-row disposition; the shared list is reproducible from the extraction scripts.
Dialogs that ported cleanly, with their v3 home:

| v1 dialog | v3 |
|---|---|
| `dialog_site` | promoted to a route — app.js:815 `openSite`, site-detail.js:17, app.html:794 |
| `dialog_domain` | promoted to a route — domains.js:28, router.js:36/56, app.html:1683 |
| `dialog_account` | accounts.js:183, app.html:2137 |
| `dialog_billing` / `dialog_invoice` | router.js:23/38, billing.js:129-203, app.html:2392 |
| `dialog_edit_site` | site-detail.js:482/494 → `PUT /sites/{id}/identity`, app.html:3804 |
| `dialog_push_to_other` | site-detail.js:185/268, app.html:940/3739 |
| `dialog_new_site_kinsta` | app.js:1404 `createKinstaSite`, app.html:4271 |
| `dialog_connect` | New site › Import — app.js:1262/1300, app.html:4355 |
| `dialog_new_domain` | app.js:1584, app.html:4451 |
| `dialog_new_account` / `dialog_edit_account` | accounts.js:167 / :197, app.html:4458 / 2139 |
| `dialog_modify_plan` | accounts.js:24/47/132 → `PUT /accounts/{id}/plan`, app.html:3968 |
| `dialog_new_user` / `dialog_user` | users.js:31/42 / :33/59, app.html:5400 |
| `dialog_new_site_user` / `dialog_delete_user` | site-detail.js:864 / :883, app.html:3676 / 3711 |
| `dialog_share` | site-detail.js:334/346/361, app.html:5340 |
| `dialog_new_provider` / `dialog_edit_provider` | settings.js:191/247/271/287, app.html:4747 |
| `dialog_cookbook` | settings.js:311/316/327, app.html:4581 |
| `dialog_handbook` / `dialog_edit_process` | settings.js:110 / :206/216, app.html:4638 / 4652 |
| `dialog_key` (delete only) | settings.js:80-82, app.html:2889 |
| `dialog_launch` / `dialog_migration` / `dialog_toggle` / `dialog_apply_https_urls` | tools.js:95 / :104 / :116 / :88, app.html:3375/3392/3413/3356 |
| `dialog_edit_script` | tools.js:133/141/157, app.html:3275 |
| `dialog_file_diff` | version-recovery.js:155/240, app.html:3597 — adds a unified/split toggle |
| `dialog_process_log_files` | version-recovery.js:607, app.html:4964 |
| `dialog_backup_snapshot` / `dialog_captures` | version-recovery.js:466 / :22/35 |
| `dialog_performance_monitor` | performance.js:60/163, app.html:4140 |
| `dialog_mailgun_usage` / `dialog_mailgun_suppressions` / `dialog_mailgun_deploy` | domains.js:405 / :421/437 / :456 |
| `dialog_store_archive` / `dialog_archive_link` | archives.js:22/33 / :44, app.html:2774 |
| `dialog_request_audit` / `dialog_cancel_audit` | security.js:199 / :193, app.html:2599/2639 |
| `dialog_new_log_entry` / `dialog_edit_log_entry` | version-recovery.js:509 / :517/529 |
| `dialog_processes` | processes.js:12/19/73, app.html:3154 |

## Phase 3d — destructive-action live-fire checklist

Each row needs one real execution on a safe target (austinginder.com, a SandyWP
sandbox, or test account 3). **Watch the network tab, not the dock** — the mock
class above proves a dock row is not evidence that anything ran.

| action | v3 entry | fired | date |
|---|---|---|---|
| migrate | tools.js:104 | ☐ | |
| launch | tools.js:95 | ☐ | |
| maintenance on / off | tools.js:116 | ☐ | |
| apply HTTPS URLs | tools.js:88 | ☐ | |
| deploy staging↔production | site-detail.js:266 | ☐ | |
| push to another site | site-detail.js:185/268 | ☐ | |
| deploy-defaults | settings.js:297 | ☐ | |
| reset-permissions | tools.js:61 | ☐ | |
| invite send / revoke (account) | accounts.js:325/329 | ☐ | |
| invite send (site) | site-detail.js:346 | ☐ | |
| TFA activate / deactivate | profile.js:57/69 / :79 | ☐ | |
| session revoke | profile.js:135 | ☐ | |
| application password create / revoke | profile.js:89 / :100 | ☐ | |
| profile save | profile.js:45 | ☐ | |
| threat track / resolve / note | security.js:40 / :44 / :48 | ☐ | |
| audit request / cancel / publish | security.js:199 / :193 / :189 | ☐ | |
| registrar lock / privacy toggle | domains.js:518 | ☐ | |
| contacts save | domains.js:657 | ☐ | |
| nameservers save | domains.js:648 | ☐ | |
| DNS record add / edit / delete | domains.js:158-161 | ☐ | |
| DNS zone / forwarding / sending delete | domains.js:197 | ☐ | |
| Mailgun deploy / verify / suppression delete | domains.js:456 / :437 | ☐ | |
| domain delete | domains.js:228 | ☐ | |
| Envato install | addons.js:117 | ☐ | |
| addon activate / deactivate / delete / update | app.js:1801-1804 | ☐ | |
| quicksave rollback (all / component) | version-recovery.js:220 / :230 | ☐ | |
| file restore | version-recovery.js:240 | ☐ | |
| backup now / restore | version-recovery.js:369 / :402 | ☐ | |
| snapshot create / link regen | version-recovery.js:466 / :450 | ☐ | |
| file manager delete | files.js:120 | ☐ | |
| account rename / delete | accounts.js:197 / :299 | ☐ | |
| edit plan save | accounts.js:132 | ☐ | |
| invoice pay / PDF | billing.js:193 / :203 | ☐ | |
| payment method add / delete / set primary | billing.js:267 / :— / :247 | ☐ | |
| ACH setup / verify | billing.js:116 | ☐ | |
| site identity save | site-detail.js:499 | ☐ | |
| site accounts assign | site-detail.js:434 | ☐ | |
| environment edit / delete / connect | site-detail.js:548-556 | ☐ | |
| WP user create / delete | site-detail.js:864 / :883 | ☐ | |
| magic login | site-detail.js:160 | ☐ | |
| provider create / edit / delete | settings.js:271 / :287 | ☐ | |
| provider import | app.js:1300 | ☐ | |
| Kinsta site create / clone | app.js:1404 | ☐ | |
| recipe save / delete / run | settings.js:316 / :327 | ☐ | |
| process log add / edit / delete | version-recovery.js:509/517/529 | ☐ | |
| archive store / share | archives.js:22 / :44 | ☐ | |
| SSH key delete | settings.js:82 | ☐ | |
| report preview / send | reports.js:56 / :67 | ☐ | |
| scheduled report save / delete | reports.js:123 | ☐ | |

## Cross-check — the 36 routes neither UI calls

The plan's third check: an orphan route that only `core.php` called would mark a
feature the line-by-line pass had missed. **It found no v3 gaps.** Every
unreferenced route was already unreferenced in v1, or belongs to a consumer
outside the SPA.

**External consumers (keep):**

| consumer | routes |
|---|---|
| CaptainCore Go CLI | `/api` (command dispatcher), `/cli/connect` |
| Claude skills | `/site-lookup` (38 refs across `~/.claude/skills`), `/component-hashes` (`security-patch`), `/site-audits/{id}/findings` POST + PUT (`import-report`, `security-audit`, `performance-audit`, `malware-cleanup`) |
| `captaincore-security-finder` plugin | `/component-sites`, `/fleet-site-counts`, `/fleet-severity-counts` — **note:** it calls the PHP handlers in-process via a synthetic `WP_REST_Request`, so the REST wrappers are unexercised, but the handlers must stay |
| Minn Admin editor panel | `/newsletter/{id}`, `/newsletter/{id}/action` (registered via the `minn_admin_editor_panels` filter) |
| playground.wordpress.net | `/quicksaves/{hash}/blueprint`, `/quicksaves/{hash}/artifact` (CORS `*` exists for exactly this) |
| Outbound email links | `/email/subscription`, `/verify-login` |
| Missive webhook | `/missive` (HMAC-verified) |
| Documented customer API (`api-docs.md`) | `/me`, `/sites/{id}/mailgun-events`, `/sites/{id}/update-logs`, `/sites/{id}/usage-breakdown` — each has an internal twin the SPA prefers |
| Internet-facing by design | `/security-patches/check` (`__return_true`; `app/SecurityPatches.php:93-97`) — no caller located |

**Dead (candidates for removal in v1.1, not 1.0):** `/configs` · `/filters` POST and its
GET twin `/filters/{name}/sites/versions=…` (the GET handler is provably broken —
`captaincore_filter_sites_func` returns `$filters` never assigned, captaincore.php:5875) ·
`/jobs/{id}` · `/malware-alert` (the CLI sends alerts through `/api command=malware-alert`) ·
`/security-patches/manifest` (docblock says it's for the Cloudflare Worker, but it's
`manage_options`-gated so no anonymous worker can reach it; the read path moved to WP
Registry) · `/security-patches/{id}` DELETE (calls a deprecated method) ·
`/security-threats/affected-sites` (redundant — `/security-threats` already embeds
`affected_sites`) · `/site-accounts` POST · `/site-audits/{id}` DELETE ·
`/site-audits/{id}/findings/{id}` DELETE · `/site/{id}/analytics`.

**Built but never wired:** `/session-anomalies` and `/sites/{id}/{env}/session-snapshots`.
Ingest exists (`/api command=session-snapshot`, captaincore.php:1388); nothing reads it.
Neither UI ever had a surface, so this is unfinished work, not a regression.

**Extraction corrections:**

- `/site/{id}/snapshots/{id}-{token}/{name}` — **v3 does call it.** The URL is
  string-built for a download link (version-recovery.js:441) and opened via
  `safeOpen` (app.js:2136), so it never passed through `this.api()` and my
  scanner missed it. Not a gap.
- `/my-jobs/{token}/stream` — called by v1 only (`core-legacy.php:15458`,
  `EventSource` in the store-archive dialog). v3's poll (archives.js:35) is the
  deliberate swap recorded at STATUS.md:443, already dispositioned above.
