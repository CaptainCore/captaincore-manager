# CaptainCore REST API

Base URL: `https://{your-site}/wp-json/captaincore/v1`

This is the **user** API: everything a logged-in account member can do with an application password. Non-admins are scoped to their own accounts and sites. Administrator-only fleet tools (users directory, global configuration, archives, security operations, provider credentials, hard site deletes) are not documented here.

## Authentication

CaptainCore uses WordPress application passwords over HTTP Basic Auth. Generate one from **Profile → API Access**, or via the `/me/application-password` routes below. The plaintext password is returned **once**.

```bash
curl -u username:application-password \
  https://{your-site}/wp-json/captaincore/v1/sites
```

- Username is your WordPress login (`GET /me` → `username`).
- Password is the application password (spaces are allowed; quote the `-u` argument).
- Cookie + `X-WP-Nonce` auth (the web UI) also works.

**Scoping.** An application password authenticates you as that WordPress user with the same access as a logged-in session. Each handler then limits you to sites and domains on accounts you belong to. Jobs are limited to tokens you created. Recipes and process-log notes you did not create cannot be mutated.

**API vs UI requests.** If `X-WP-Nonce` is **absent** (typical of `curl` + an application password), long-running commands execute **synchronously** (up to 5 minutes) unless you pass `"async": true`. If the nonce header is present, those commands return a job token for the UI instead.

---

## Conventions

| Identifier | What it is | Where you get it |
|------------|------------|------------------|
| `site_id` | CaptainCore site ID | `GET /sites` |
| `site` / slug | Short alphanumeric name (`example`) | `GET /sites` → `site` |
| `environment_id` | CaptainCore environment ID | `GET /sites/{id}/environments` |
| `environment` | Environment **name** | See casing below |
| `domain_id` | CaptainCore domain ID (DNS / registrar) | `GET /domains` |
| `remote_id` | Constellix zone ID | `GET /domains` → `remote_id` |
| `account_id` | CaptainCore account ID | `GET /accounts` |

**Environment names are inconsistent across routes:**

- Database / UI / `POST /sites/cli`: `Production`, `Staging`, or `Both` (capitalized). CLI only appends `-staging` when the value is exactly `Staging`.
- Many URL paths used by the dashboard: `production` / `staging` (lowercase). Logs, files, and performance-monitor expect lowercase.
- `GET /update-logs` and `GET /quicksaves` query param: `production` or `staging`.
- When in doubt, match the UI for that screen.

**Path prefixes.** Some older routes use singular `/site/{id}/…` (backups list, snapshots list, captures list, logs-archive). Newer ones use `/sites/{id}/…`. Use the path documented on each endpoint.

**Errors.** Some handlers return `{ "errors": ["…"] }` with HTTP 200 instead of a REST error. Permission failures are typically 403 `token_invalid` or `permission_denied`.

---

## Running Commands

The `/run/code` endpoint executes WP-CLI (or other) code on one or more environments you can access. There are two modes depending on how long the command takes.

### Quick commands (synchronous)

For commands that complete in under 5 minutes, send a request and get the result back directly:

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{
    "code": "wp option get home",
    "environments": [{"site_id": 135, "environment": "production"}]
  }' \
  https://{your-site}/wp-json/captaincore/v1/run/code
```

**Response:**
```json
{"status": "completed", "response": "https://example.com\n"}
```

### Long-running commands (async)

For commands that may take longer (backups, migrations, bulk operations), add `"async": true` to start the job immediately and get a token back for polling:

**1. Start the job:**
```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{
    "code": "wp plugin update --all",
    "environments": [{"site_id": 135, "environment": "production"}],
    "async": true
  }' \
  https://{your-site}/wp-json/captaincore/v1/run/code
```

**Response:**
```json
{"status": "queued", "token": "nwaBFBISZEsT"}
```

**2. Poll for the result:**
```bash
curl -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/my-jobs/nwaBFBISZEsT
```

While running:
```json
{"status": "started", "token": "nwaBFBISZEsT"}
```

When finished:
```json
{"status": "completed", "response": "...command output...", "token": "nwaBFBISZEsT"}
```

The `async` parameter also works with `/sites/cli` and `/sites/bulk-tools`.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `code` | string | Yes | Script to run |
| `environments` | array | Yes | Targets (formats below) |
| `async` | boolean | No | API requests only: queue instead of waiting |

**Environment formats** (mixed in one array is fine):

Integer environment IDs:
```json
{ "code": "wp option get home", "environments": [3365, 3358] }
```

Objects with `environment_id` (also accepts the typo `enviroment_id`):
```json
{ "code": "wp option get home", "environments": [{"environment_id": 3365}] }
```

Objects with `site_id` + environment name (`production` / `staging`; case-insensitive):
```json
{ "code": "wp option get home", "environments": [{"site_id": 135, "environment": "production"}] }
```

Targets you cannot access are skipped. If none remain → `403` `invalid_targets`. Empty `code` → `400` `missing_code`.

For **named built-in commands** (backup, launch, reset-permissions, …) use `POST /sites/cli` instead of freeform code.

---

## Jobs

Tokens are stored per user. The token in the path is `[a-zA-Z0-9]+` (no hyphens).

### Get my job status
```
GET /my-jobs/{token}
```

Looks up `token` + current `user_id`. Unknown or someone else's token → `404`.

```bash
curl -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/my-jobs/nwaBFBISZEsT
```

**In progress:**
```json
{"status": "started", "token": "nwaBFBISZEsT"}
```

If the CLI includes `progress`, it is passed through:
```json
{"status": "started", "token": "nwaBFBISZEsT", "progress": {"phase": "copy", "percent": 40}}
```

**Completed:**
```json
{"status": "completed", "response": "...command output...", "token": "nwaBFBISZEsT"}
```

### Stream job (SSE)
```
GET /my-jobs/{token}/stream
```

Same ownership check, then proxies the CLI as **Server-Sent Events** (`Content-Type: text/event-stream`). Browser `EventSource` cannot send Basic Auth — same-origin UI uses a cookie + `?_wpnonce=`.

```bash
curl -N -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/my-jobs/nwaBFBISZEsT/stream
```

### Cancel job
```
DELETE /my-jobs/{token}
```

```bash
curl -X DELETE -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/my-jobs/nwaBFBISZEsT
```

```json
{"status": "cancelled"}
```

---

## Current User

### Get current user
```
GET /me
```

Returns your profile. This is the REST `User::fetch()` payload — TFA status, pins, and email-subscriber are **not** on this response (those live in the UI bootstrap).

```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/me
```

```json
{
  "user_id": 12,
  "account_ids": [75, 81],
  "username": "ada",
  "first_name": "Ada",
  "last_name": "Lovelace",
  "email": "ada@example.com",
  "name": "Ada Lovelace",
  "roles": ["subscriber"],
  "created_at": "2024-03-02 14:11:00"
}
```

| Field | Type | Description |
|-------|------|-------------|
| `user_id` | integer | WordPress user ID |
| `account_ids` | array | CaptainCore account IDs you belong to |
| `username` | string | WP login (use this in Basic Auth) |
| `roles` | array | WordPress roles (not the account access tier) |

### Update profile
```
PUT /me/profile
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `display_name` | string | Yes | Must be non-empty. First/last name are **not** accepted here. |
| `email` | string | Yes | Valid email |
| `new_password` | string | No | If set: length ≥ 8, at least one letter and one number |

```bash
curl -X PUT -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"display_name":"Ada Lovelace","email":"ada@example.com"}' \
  https://{your-site}/wp-json/captaincore/v1/me/profile
```

**Success:** `{ "profile": { "display_name": "Ada Lovelace", "email": "ada@example.com" } }`

**Validation failure (still HTTP 200):**
```json
{"errors": ["Display name can't be empty."], "profile": {"display_name": "", "email": "ada@example.com"}}
```

### Update pinned environments
```
POST /me/pins
```

Replaces your pinned environments. The JSON body **is the array** (not `{ "pins": [...] }`).

| Field (each element) | Type | Description |
|----------------------|------|-------------|
| `site_id` | integer | Site to pin |
| `environment_id` | integer | Environment to pin |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '[{"site_id":135,"environment_id":3365}]' \
  https://{your-site}/wp-json/captaincore/v1/me/pins
```

```json
{"success": true, "pins": [{"site_id": 135, "environment_id": 3365}]}
```

Send `[]` to clear. Non-array body → `400` `invalid_data`.

### Application password management
```
POST   /me/application-password          # Generate new
POST   /me/application-password/rotate   # Rotate existing
DELETE /me/application-password          # Delete existing
```

These manage **one** WordPress application password named `{site title} API`. There is no GET — the UI reads existence from PHP bootstrap. The plaintext `password` is shown once.

**Generate** creates a new password (does not delete an existing one). Prefer rotate if one already exists.

```bash
curl -X POST -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/me/application-password
```

```json
{"password": "xxxx xxxx xxxx xxxx xxxx xxxx", "created": 1771427726}
```

**Rotate** deletes the existing `{site} API` password and creates a new one. If none exists → HTTP 400 `{ "error": "No application password exists to rotate." }`. Rotating **invalidates** the password you just used.

**Delete** → `{ "success": true }`, or 400 if none exists.

### Two-factor authentication
```
GET  /me/tfa_activate     # Begin TFA setup (returns otpauth URI)
POST /me/tfa_validate     # Verify TFA code to activate
GET  /me/tfa_deactivate   # Disable TFA
```

TOTP (issuer `Anchor Hosting`, label = your email). Flow: activate (new secret + URI) → scan/enter in an authenticator → validate with the 6-digit code. TFA is not on until validate succeeds. Calling activate **overwrites** the stored secret even if TFA is already on.

**Activate** returns a JSON string, not an object:
```json
"otpauth://totp/ada%40example.com?secret=JBSWY3DPEHPK3PXP&issuer=Anchor%20Hosting"
```

**Validate:**
```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"token":"123456"}' \
  https://{your-site}/wp-json/captaincore/v1/me/tfa_validate
```

Response is a JSON boolean (`true` / `false`). Invalid code does not enable TFA.

**Deactivate** deletes the secret and enabled flag. Response is a confirmation string.

### Email notifications
```
POST /me/email-subscriber
```

Adds or removes the WordPress role `email_subscriber` (blog-post notification emails).

| Field | Type | Description |
|-------|------|-------------|
| `enabled` | boolean | Truthy → subscribe; falsy → unsubscribe |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"enabled": true}' \
  https://{your-site}/wp-json/captaincore/v1/me/email-subscriber
```

```json
{"success": true, "message": "You will now receive blog post notifications."}
```

### Get API documentation
```
GET /me/api-docs
GET /me/api-docs?format=html
```

Substitutes `{your-site}` with this host. `format=html` returns `{ "html": "…" }` (Parsedown). Without that query param this is **not** JSON: `Content-Type: text/markdown` plus `Content-Disposition: attachment; filename="captaincore-api-docs.md"`.

```bash
curl -u user:pass \
  -o captaincore-api-docs.md \
  https://{your-site}/wp-json/captaincore/v1/me/api-docs
```

---

## Sessions

WordPress `session_tokens` (browser logins), used by **Profile → Active sessions**. Application-password REST calls **do not** create a WP session.

### List sessions
```
GET /sessions
```

```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/sessions
```

```json
{
  "sessions": [
    {
      "id": "a1b2c3d4e5f60789",
      "hash": "a1b2c3d4e5f60789…64-char-hex…",
      "ip": "203.0.113.10",
      "country": "US",
      "country_name": "United States",
      "ua_browser": "Chrome 131",
      "ua_os": "macOS 14.2.0",
      "login_at": 1771427726,
      "expires_at": 1772032526,
      "is_current": true
    }
  ]
}
```

| Field | Type | Description |
|-------|------|-------------|
| `id` | string | First 16 chars of the session hash |
| `hash` | string | Full sha256 session key (use this to destroy) |
| `login_at` / `expires_at` | integer | Unix timestamps |
| `is_current` | boolean | True only when this request has a WP auth cookie matching that session |

**Caveat (app passwords):** `is_current` is **never** true without a cookie. `DELETE` with `all_others` then destroys **every** browser session.

### Destroy a session
```
DELETE /sessions
```

JSON body — one of:

| Field | Type | Description |
|-------|------|-------------|
| `hash` | string | 64-char hex session hash to kill |
| `all_others` | boolean | Destroy all sessions except the current cookie session |

The current cookie session cannot be destroyed here (use sign-out).

```bash
curl -X DELETE -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"hash":"aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"}' \
  https://{your-site}/wp-json/captaincore/v1/sessions
```

**Success:** `{ "destroyed": 1, "sessions": [ …remaining ] }`

---

## Sites

### List all sites
```
GET /sites
```

Returns every site the authenticated user can access, sorted by name. Each row is a lightweight listing record (not full environment plugins/themes). Auth tokens, raw `details`, and `status` are stripped.

```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/sites
```

```json
[
  {
    "site_id": 135,
    "account_id": 12,
    "customer_id": 12,
    "name": "example.com",
    "site": "example",
    "provider": "kinsta",
    "core": "6.9.1",
    "home_url": "https://example.com",
    "storage": 123456789,
    "visits": 15000,
    "removed": false,
    "environments": [
      {
        "environment_id": 3365,
        "environment": "Production",
        "home_url": "https://example.com",
        "core": "6.9.1"
      }
    ]
  }
]
```

### Get a site
```
GET /sites/{site_id}
GET /sites/{slug}
```

Same handler. `{site_id}` can be numeric (`135`) or the short site slug (`example`). Returns the full site object used by the dashboard: environments, screenshots, usage placeholders, etc.

Optional path tricks: `{id}-staging` or `{id}@provider`.

```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/sites/135
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/sites/example
```

This is heavier than `/details`. For the UI “site header + account + domains” payload, use `/sites/{id}/details`.

### Get site details
```
GET /sites/{site_id}/details
```

Customer-facing site record plus account, linked domains, and accounts the site is shared with. This is what the site overview loads.

```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/sites/135/details
```

```json
{
  "site": {
    "site_id": 135,
    "name": "example.com",
    "site": "example",
    "provider": "kinsta",
    "removed": false,
    "backup_settings": {"mode": "direct", "interval": "daily", "active": true},
    "storage": 123456789,
    "visits": 15000
  },
  "account": {"account_id": 12, "name": "Example LLC"},
  "domains": [{"domain_id": 37, "name": "example.com"}],
  "shared_with": [{"account_id": 12}]
}
```

### Fetch multiple sites
```
POST /sites/fetch
```

Hydrates one or more sites the caller can access. IDs you cannot access are skipped (array form) rather than 403ing the whole request. A single `post_id` you cannot access returns 403.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `post_ids` | array | No* | Site IDs (*use this or `post_id`) |
| `post_id` | integer | No* | Single site ID |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"post_ids":[135,136]}' \
  https://{your-site}/wp-json/captaincore/v1/sites/fetch
```

### Create a site
```
POST /sites
```

Creates a site plus Production (and optional Staging) connection records. Body is nested under `site`. Site slug must be unique, alphanumeric, and at least 3 characters.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `site.name` | string | Yes | Display domain/name |
| `site.site` | string | Yes | Short alphanumeric slug |
| `site.account_id` | integer | Yes | Owning account |
| `site.provider` | string | Yes | Hosting provider (`kinsta`, `rocketdotnet`, …) |
| `site.customer_id` | integer | No | Customer account; a new account is created if empty |
| `site.provider_id` | integer | No | Provider credential row |
| `site.provider_site_id` | string | No | Remote host site ID |
| `site.shared_with` | array | No | Extra account IDs to share with |
| `site.environments` | array | Yes | At least Production connection fields |

Each environment object: `environment` (`Production` / `Staging`), `address`, `username`, `protocol` (`sftp` / `ssh` / `ftp`), `port`, optional `password`, `home_directory`, database credentials, offload fields.

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{
    "site": {
      "name": "example.com",
      "site": "example",
      "account_id": 12,
      "provider": "kinsta",
      "environments": [{
        "environment": "Production",
        "address": "example.sftp.kinsta.cloud",
        "username": "example",
        "protocol": "sftp",
        "port": "22"
      }]
    }
  }' \
  https://{your-site}/wp-json/captaincore/v1/sites
```

```json
{"errors": [], "response": "Successfully added new site", "site_id": 135}
```

Validation failures return `{ "errors": ["Error: Domain can't be empty."] }` with HTTP 200. Staging with an empty `address` is dropped.

### Update a site (details patch)
```
POST /sites/{site_id}
```

Merges keys into the site’s JSON `details` blob. This is **not** the same as `PUT /sites/{id}/settings` (plugin/theme auto-updates).

Typical customer use: request or cancel removal.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `details` | object | Yes | Keys merged into stored site details |
| `details.removed` | boolean | No | `true` emails a removal request; `false` cancels it |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"details":{"removed":true}}' \
  https://{your-site}/wp-json/captaincore/v1/sites/135
```

Hard delete (`DELETE /sites/{id}`) returns **403** for non-admins: *“Only administrators can delete a site. Request removal instead.”*

---

## Site Environments

### Get environments
```
GET /sites/{site_id}/environments
```

Full environment records: connection info, plugins/themes, Fathom IDs, capture pages, update settings, scheduled scripts, screenshot URLs.

```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/sites/135/environments
```

```json
[
  {
    "environment_id": 3365,
    "environment": "Production",
    "home_url": "https://example.com",
    "core": "6.9.1",
    "monitor_enabled": 1,
    "updates_enabled": 1,
    "updates_exclude_plugins": [],
    "updates_exclude_themes": [],
    "fathom_analytics": [{"code": "ABCDEF", "domain": "example.com"}],
    "capture_pages": [{"page": "/"}],
    "plugins": [{"name": "akismet", "version": "5.3", "status": "active"}],
    "themes": [{"name": "twentytwentyfive", "version": "1.3", "status": "active"}],
    "scheduled_scripts": [
      {"script_id": 42, "code": "wp cron event run --due-now", "run_at": 1771427726, "status": "scheduled"}
    ]
  }
]
```

Creating, connecting, updating SSH/SFTP connection settings, and deleting environments are administrator-only.

### List accessible environments
```
GET /environments
```

Kinsta **active** environments the current user can access. Used as a generic target picker (for example, push destinations).

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `source_environment_id` | integer | No | Exclude this environment from the list |

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/environments?source_environment_id=3365"
```

```json
[
  {
    "site_id": 136,
    "name": "staging.example.com",
    "environment": "Staging",
    "environment_id": 3366,
    "home_url": "https://staging.example.com"
  }
]
```

### Update environment settings
```
PUT /sites/{site_id}/settings
```

Saves plugin/theme auto-update flags for **one environment**, then queues `site sync`.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `environment` | string | Yes | DB name, typically `Production` or `Staging` |
| `value.updates_enabled` | integer/boolean | Yes | Enable automatic updates |
| `value.updates_exclude_themes` | array | No | Theme slugs to skip |
| `value.updates_exclude_plugins` | array | No | Plugin slugs to skip |

```bash
curl -X PUT -u user:pass \
  -H "Content-Type: application/json" \
  -d '{
    "environment": "Production",
    "value": {
      "updates_enabled": 1,
      "updates_exclude_themes": ["twentytwentyfive"],
      "updates_exclude_plugins": ["akismet"]
    }
  }' \
  https://{your-site}/wp-json/captaincore/v1/sites/135/settings
```

### Monitor settings
```
POST /sites/{site_id}/{environment}/monitor
```

Sets `monitor_enabled` on the environment and queues `site sync`. Empty success body.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `monitor` | integer | Yes | `1` on, `0` off |

Path `{environment}` is usually `production` / `staging`.

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"monitor":1}' \
  https://{your-site}/wp-json/captaincore/v1/sites/135/production/monitor
```

### Captures settings
```
POST /sites/{site_id}/{environment}/captures
```

Saves pages to screenshot (always includes `/`) and optional HTTP basic auth, then syncs the site. Returns the capture history for that environment.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `pages` | array | Yes | Objects `{ "page": "/" }` or `{ "page": "/about" }` |
| `auth` | object | No | `{ "username", "password" }` stored on environment details |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"pages":[{"page":"/"},{"page":"/about"}]}' \
  https://{your-site}/wp-json/captaincore/v1/sites/135/Production/captures
```

### Backup settings
```
POST /sites/{site_id}/backup
```

Site-level backup configuration (not a backup run). Queues `site sync`.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `settings.active` | boolean | Yes | Backups on/off |
| `settings.interval` | string | Yes | e.g. `daily` |
| `settings.mode` | string | Yes | e.g. `direct` |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"settings":{"active":true,"interval":"daily","mode":"direct"}}' \
  https://{your-site}/wp-json/captaincore/v1/sites/135/backup
```

**Response:** the saved `backup_settings` object.

### Sync environment data
```
GET /sites/{site_id}/{environment}/sync/data
```

Queues a live `sync-data` of plugins, themes, storage, and WP version from the remote site. **Async** (job token). `{environment}` is interpolated as-is into `{slug}-{environment}` (UI sends lowercase).

```bash
curl -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/sites/135/production/sync/data
```

### New captures
```
GET /sites/{site_id}/{environment}/captures/new
```

Queues a capture run if the site changed. `{environment}` is lowercased before the CLI call. Returns the numeric site ID.

```bash
curl -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/sites/135/Production/captures/new
```

### Push environments
```
GET  /sites/{site_id}/environments/{env_id}/push-targets
POST /sites/environments/push
```

**Push targets** lists Kinsta environments you can push **to**, excluding the source, limited to the same Kinsta provider account.

```bash
curl -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/sites/135/environments/3365/push-targets
```

```json
[
  {
    "site_id": 135,
    "name": "example.com",
    "environment": "Staging",
    "environment_id": 3366,
    "home_url": "https://staging.example.com"
  }
]
```

**Push** starts a provider copy from source → target. You must own **both** sites. This is **async at the provider** (HTTP 202), not a CaptainCore job token.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `source_environment_id` | integer | Yes | Source environment ID |
| `target_environment_id` | integer | Yes | Target environment ID |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"source_environment_id":3365,"target_environment_id":3366}' \
  https://{your-site}/wp-json/captaincore/v1/sites/environments/push
```

```json
{"operation_id": "12345", "message": "Push operation started."}
```

---

## Files

Browse, read, and delete files **inside an environment’s home directory**. The remote file-manager resolves every path with `realpath` and refuses anything outside home (including symlink escapes). Listings are not activity-logged; **view** and **delete** are.

Path `{id}` is **`environment_id`**, not site ID.

### List or view files
```
GET /environment/{environment_id}/files
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `path` | string | No | Relative path inside home; default `""` (home root) |
| `action` | string | No | `list` (default) or `view` |

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/environment/3365/files?path="
```

```json
{
  "path": "",
  "entries": [
    {"name": "wp-admin", "type": "dir", "link": false, "size": null, "mtime": 1754006400},
    {"name": "wp-config.php", "type": "file", "link": false, "size": 3358, "mtime": 1754006400}
  ]
}
```

**View a file:**
```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/environment/3365/files?action=view&path=wp-config.php"
```

Text files return `{ "path", "size", "content_b64" }` (may include `truncated`). Images may set `image` (mime) + `content_b64`. Binary files set `binary: true` without preview.

### Delete a file
```
DELETE /environment/{environment_id}/files
```

Deletes **one regular file**. Directories and symlinks are refused.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `path` | string | Yes | Relative file path (query or body) |

```bash
curl -X DELETE -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/environment/3365/files?path=wp-content/debug.log"
```

```json
{"deleted": "wp-content/debug.log"}
```

---

## Site Domains

Host-level domain mappings for a **site environment** (Kinsta, etc.). Separate from the CaptainCore Domains/DNS APIs. Add/delete/primary return **202** because the host API is asynchronous. Path `{environment}`: `production` is mapped to Kinsta `live`; other names pass through.

### List domains on a site environment
```
GET /sites/{site_id}/{environment}/domains
```

```bash
curl -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/sites/135/production/domains
```

```json
[
  {
    "id": 111,
    "name": "example.com",
    "is_primary": true,
    "is_active": true,
    "verification_records": []
  }
]
```

Wildcard `*.` domains are omitted. Unverified custom domains include `verification_records` and `is_active: false`. `id` here is the **provider** domain ID, not a CaptainCore `domain_id`.

### Add a domain
```
POST /sites/{site_id}/{environment}/domains
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `domain_name` | string | Yes | Hostname to add |
| `is_wildcardless` | boolean | No | Default `false` |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"domain_name":"www.example.com"}' \
  https://{your-site}/wp-json/captaincore/v1/sites/135/production/domains
```

### Remove a domain
```
DELETE /sites/{site_id}/{environment}/domains
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `domain_ids` | array | Yes | Provider domain IDs (not CaptainCore domain IDs) |

```bash
curl -X DELETE -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"domain_ids":[111]}' \
  https://{your-site}/wp-json/captaincore/v1/sites/135/production/domains
```

### Set primary domain
```
PUT /sites/{site_id}/{environment}/domains/primary
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `domain_id` | integer | Yes | Provider domain ID |
| `run_search_and_replace` | boolean | No | Default `true` (rewrites site URLs) |

```bash
curl -X PUT -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"domain_id":111,"run_search_and_replace":true}' \
  https://{your-site}/wp-json/captaincore/v1/sites/135/production/domains/primary
```

---

## Site Admin Tools

### Magic login
```
GET /sites/{site_id}/{environment}/magiclogin
GET /sites/{site_id}/{environment}/magiclogin/{wp_user_id}
```

Returns a one-time WordPress login URL for the remote site. If `{wp_user_id}` is omitted, the handler picks a WP user: matching admin email, then same email domain, then same local-part, then any administrator.

Response is a **plain URL string**, not JSON.

```bash
curl -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/sites/135/production/magiclogin
```

**Response:** `https://example.com/wp-login.php?user=admin&token=...`

### PHPMyAdmin access
```
GET /sites/{site_id}/{environment}/phpmyadmin
```

Returns a provider sign-on URL (Kinsta or Rocket.net). Response is a URL string.

```bash
curl -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/sites/135/production/phpmyadmin
```

### List WordPress users
```
GET /sites/{site_id}/users
```

Cached WP users for **each environment**, keyed by environment name (`Production` / `Staging`), sorted by role then login.

```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/sites/135/users
```

```json
{
  "Production": [
    {"ID": 1, "user_login": "admin", "user_email": "owner@example.com", "roles": "administrator"}
  ],
  "Staging": []
}
```

Empty/missing cache yields `{}`. Refresh users with `POST /sites/cli` `command: users-fetch`.

### Site stats (Fathom analytics)
```
GET /sites/{site_id}/stats
```

Aggregated visits, pageviews, bounce rate, and average duration. Environment match is case-insensitive. Defaults: last year through now, grouping `month`, first Fathom ID on that environment.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `from_at` | string | No | Start date (`2026-01-01`) |
| `to_at` | string | No | End date |
| `grouping` | string | No | `hour`, `day`, `month`, `year` |
| `environment` | string | No | `Production` or `production` (default `production`) |
| `fathom_id` | string | No | Override tracker ID |

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/sites/135/stats?from_at=2026-01-01&to_at=2026-01-31&grouping=day&environment=Production"
```

```json
{
  "fathom_id": "ABCDEF",
  "site": {"id": "ABCDEF", "name": "example.com", "sharing": "none"},
  "summary": {"pageviews": 12000, "visits": 4000, "bounce_rate": 0.42, "avg_duration": 98.5},
  "items": [{"date": "Jan 05 2026", "visits": 120, "pageviews": 340, "bounce_rate": 0.4, "avg_duration": 90}]
}
```

Missing Fathom config: `{ "Error": "There was a problem retrieving stats." }`.

`POST /site/{site_id}/analytics` is an older alias of the same call (fields in the JSON body).

### Top pages
```
GET /sites/{site_id}/stats/top-pages
```

Fathom pathnames for the range. Default window is **last 30 days** if dates are omitted (unlike `/stats`, which defaults to 1 year).

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `from_at` | string | No | Start date |
| `to_at` | string | No | End date |
| `environment` | string | No | Case-insensitive; default `production` |
| `limit` | integer | No | Default `10` |

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/sites/135/stats/top-pages?from_at=2026-01-01&to_at=2026-01-31&limit=5"
```

```json
[{"pathname": "/", "visits": 800, "uniques": 600, "pageviews": 1200}]
```

No tracker → `[]`.

### Top referrers
```
GET /sites/{site_id}/stats/top-referrers
```

Same date/environment/`limit` params as top-pages. Grouped by `referrer_hostname`, sorted by visits.

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/sites/135/stats/top-referrers?from_at=2026-01-01&to_at=2026-01-31&limit=5"
```

```json
[{"referrer_hostname": "google.com", "visits": 400, "uniques": 350, "pageviews": 520}]
```

### Share site stats
```
POST /sites/{site_id}/stats/share
```

Updates Fathom sharing for a tracker that already belongs to the site.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `fathom_id` | string | Yes | Must match a tracker on this site |
| `sharing` | string | Yes | `none`, `private`, or `public` |
| `share_password` | string | No | Used when `sharing` is `private` |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"fathom_id":"ABCDEF","sharing":"private","share_password":"secret"}' \
  https://{your-site}/wp-json/captaincore/v1/sites/135/stats/share
```

```json
{"success": true}
```

Unknown `fathom_id` is a silent no-op then `{ "success": true }`.

### Site timeline
```
GET /sites/{site_id}/timeline
```

Process-log history for the site (recipes run, permissions reset, notes, etc.). Descriptions are Markdown-rendered HTML; `description_raw` is the original. `created_at` is a Unix timestamp.

```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/sites/135/timeline
```

```json
[
  {
    "process_log_id": 501,
    "name": "Reset file permissions",
    "description_raw": "Reset file permissions",
    "description": "<p>Reset file permissions</p>",
    "created_at": 1771427726,
    "author": "Jane Owner",
    "author_avatar": "https://www.gravatar.com/avatar/…",
    "files": []
  }
]
```

To add a timeline note, use `POST /process-logs`.

### Usage breakdown
```
GET /sites/{site_id}/usage-breakdown
```

Plan usage for the **site’s account** (not a single site): hosted vs maintenance sites, storage GB vs plan limit, yearly visit estimate.

```bash
curl -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/sites/135/usage-breakdown
```

```json
{
  "sites": [{"site_id": 135, "name": "example.com", "storage": 123456789, "visits": 15000}],
  "maintenance_sites": [],
  "total": [
    "12% storage<br /><strong>1.2GB/10GB</strong>",
    "3% traffic<br /><strong>15,000</strong> <small>Yearly Estimate</small>"
  ]
}
```

---

## Performance

### Toggle performance monitor
```
POST /sites/{site_id}/{environment}/performance-monitor
```

Queues `performance-monitor activate|deactivate`. `{environment}` should be lowercase (`production`). Enabling is async.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `enabled` | boolean | Yes | `true` activate, `false` deactivate |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"enabled":true}' \
  https://{your-site}/wp-json/captaincore/v1/sites/135/production/performance-monitor
```

```json
{"status": "success", "action": "activate"}
```

The enabled flag lives in environment `details.performance_monitor_enabled` after the next data sync.

### Fetch performance samples
```
GET /sites/{site_id}/{environment}/performance-monitor
```

Synchronous fetch. Returns 404 if no data yet.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `hours` | integer | No | Lookback window; omit or `0` for all |
| `format` | string | No | `raw` for the sample series the UI charts |

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/sites/135/production/performance-monitor?format=raw&hours=24"
```

```json
{
  "max_workers": 4,
  "samples": [
    {"time": "2026-01-05 14:00:00", "db": 12, "load": 0.4, "code": 80, "resp": 210, "workers": 1, "max_workers": 4}
  ]
}
```

---

## Site Logs

### List logs
```
GET /sites/{site_id}/{environment}/logs
```

SSH list of current access/error logs. `{environment}` must be `production` or `staging` (`400 invalid_environment` otherwise).

```bash
curl -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/sites/135/production/logs
```

```json
{"files": [{"path": "access.log"}, {"path": "error.log"}]}
```

### Fetch logs
```
POST /sites/{site_id}/{environment}/logs/fetch
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `file` | string | Yes | Path from the list. Only `A–Z a–z 0–9 . _ / -`. |
| `limit` | integer | No | Line limit; default `1000` |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"file":"error.log","limit":1000}' \
  https://{your-site}/wp-json/captaincore/v1/sites/135/production/logs/fetch
```

**Response:** raw log text, not JSON. Invalid `file` → `400 invalid_file`.

### List archived logs
```
GET /site/{site_id}/{environment}/logs-archive
```

Long-term rotated Kinsta logs in object storage. Filenames: `{access|error}.log-YYYY-MM-DD-EPOCH[.gz]`. A fresh environment can legitimately return `[]`.

```bash
curl -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/site/135/production/logs-archive
```

```json
[
  {
    "name": "access.log-2026-04-01-1712000000.gz",
    "type": "access",
    "date": "2026-04-01",
    "epoch": 1712000000,
    "size": 45056
  }
]
```

### Download an archived log
```
GET /site/{site_id}/{environment}/logs-archive/download
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `file` | string | Yes | Exact `name` from the list. Must match `^(access|error)\.log-\d{4}-\d{2}-\d{2}-\d+(\.gz)?$`. |

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/site/135/production/logs-archive/download?file=access.log-2026-04-01-1712000000.gz"
```

```json
{"link": "https://…signed…", "expires_at": "…", "expires_in": "1 hour"}
```

The bucket has no CORS; browsers should open `link` directly, or use `/view` to proxy.

### View an archived log
```
GET /site/{site_id}/{environment}/logs-archive/view
```

Resolves the signed URL server-side, gunzips if needed, returns the last N lines.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `file` | string | Yes | Same pattern as download |
| `lines` | integer | No | 1–5000; default **1000** |

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/site/135/production/logs-archive/view?file=error.log-2026-04-01-1712000000.gz&lines=500"
```

```json
{
  "file": "error.log-2026-04-01-1712000000.gz",
  "total": 8400,
  "truncated": true,
  "content": "…last 500 lines…"
}
```

---

## Backups & Snapshots

Restic backups are listed/inspected over REST. Creating a backup, downloading a zip of selected paths, or rolling a point-in-time snapshot is done with `POST /sites/cli` (`command`: `backup`, `backup_download`, `snapshot`) — not these GET routes.

Path prefix is inconsistent: **list uses `/site/`**, **get uses `/sites/`**.

### List backups
```
GET /site/{site_id}/{environment}/backups
```

```bash
curl -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/site/135/production/backups
```

```json
[
  {
    "id": "a1b2c3d4",
    "time": "2026-04-01T12:00:00-04:00",
    "loading": true,
    "omitted": false,
    "files": [],
    "tree": [],
    "active": [],
    "preview": ""
  }
]
```

| Field | Type | Description |
|-------|------|-------------|
| `id` | string | Restic snapshot ID |
| `time` | string | Snapshot timestamp (ISO-8601) |

CLI failure / non-JSON → `[]`.

### Get a specific backup
```
GET /sites/{site_id}/{environment}/backups/{backup_id}
```

Without `file`, this returns the **raw CLI body**: a **https URL string** to a JSON tree (not a JSON object). Fetch that URL separately. The tree JSON has `files[]` (`id`, `name`, `size`, `count`, `type`, `path`, `ext`, `children`) and `omitted`.

```bash
curl -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/sites/135/production/backups/a1b2c3d4
```

### Preview a file from a backup
```
GET /sites/{site_id}/{environment}/backups/{backup_id}?file={path}
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `file` | string | Yes | File path. Prefer **base64url** (so names like `wp-config.php` are not blocked by WAF). Plain paths are accepted if they are not valid base64. |

```bash
# file = base64url("wp-config.php")
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/sites/135/production/backups/a1b2c3d4?file=d3AtY29uZmlnLnBocA"
```

**Response:** streamed file contents, not JSON.

Generating a zip of selected files is `POST /sites/cli` with `command=backup_download` and `value: { files, directories, backup_id }`. A link is emailed when ready.

### List snapshots
```
GET /site/{site_id}/snapshots
```

Zip snapshots grouped by environment **name** (`Production` / `Staging`). `created_at` is a Unix timestamp. `user_id` is replaced with `{ user_id, name }` (`"System"` when `user_id` is `0`).

```bash
curl -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/site/135/snapshots
```

```json
{
  "Production": [
    {
      "snapshot_id": 88,
      "site_id": 135,
      "environment_id": 3365,
      "created_at": 1771427726,
      "snapshot_name": "example-com-2026-04-01.zip",
      "storage": "104857600",
      "email": "user@example.com",
      "notes": "Everything",
      "expires_at": "2026-04-02 12:00:00",
      "token": "",
      "user": {"user_id": 0, "name": "System"}
    }
  ],
  "Staging": []
}
```

### Get snapshot download link
```
GET /sites/{site_id}/snapshot-link/{snapshot_id}
```

Writes a new 32-hex `token` and `expires_at` = now + 24 hours. The snapshot must belong to `{site_id}`.

```bash
curl -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/sites/135/snapshot-link/88
```

```json
{"token": "a1b2c3d4e5f6…", "expires_at": "2026-04-02 12:00:00"}
```

### Download snapshot (public, token-protected)
```
GET /site/{site_id}/snapshots/{snapshot_id}-{token}/{snapshot_name}
```

No WordPress auth. Compared in constant time: site, snapshot id, token, and name.

**`snapshot_name` in the URL is the filename without `.zip`.** The handler appends `.zip` and compares to the DB. The path regex is `[a-zA-Z0-9-]+` only.

```bash
curl -L \
  "https://{your-site}/wp-json/captaincore/v1/site/135/snapshots/88-TOKEN/example-com-2026-04-01" \
  -o snapshot.zip
```

**Response:** `302` to a short-lived storage URL. Invalid token/name/site → `403 token_invalid`.

---

## Site Captures

### List captures
```
GET /site/{site_id}/{environment}/captures
```

Visual screenshots of configured pages. `{environment}` in the path (typically `production` / `staging`).

```bash
curl -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/site/135/production/captures
```

```json
[
  {
    "capture_id": 12,
    "site_id": 135,
    "environment_id": 3365,
    "created_at": "2026-04-01 12:00:00",
    "created_at_friendly": "Wed, Apr 1st 2026 8:00 am",
    "git_commit": "446d35f6b1f845b4371bf109404e1269d19c65ca",
    "pages": [
      {
        "name": "/",
        "image": "home.png",
        "image_url": "https://uploads.example/site_135/production/captures/home.png"
      }
    ],
    "image_base_url": "https://uploads.example/site_135/production/captures/"
  }
]
```

Triggering a new capture is `GET /sites/{id}/{environment}/captures/new`.

---

## Site Invitations

### Preview invite
```
GET /sites/{site_id}/invite-preview
```

Shows which account, site count, and domain count an invite would share. Full site/domain lists are included only if the caller already has that account.

```bash
curl -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/sites/135/invite-preview
```

```json
{
  "site_name": "example.com",
  "account_name": "Example LLC",
  "total_sites": 3,
  "total_domains": 2,
  "has_account_access": true,
  "sites_list": [],
  "domains_list": []
}
```

### Send invite
```
POST /sites/{site_id}/invite
```

Invites an email to the site’s customer account (falls back to `account_id`). Always returns a generic success message (no user enumeration).

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `email` | string | Yes | Valid email |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"email":"teammate@example.com"}' \
  https://{your-site}/wp-json/captaincore/v1/sites/135/invite
```

```json
{"message": "Invitation sent successfully."}
```

This is different from **account** invites (`POST /accounts/{id}/invites`), which set an access tier (`full`, `sites-only`, `domains-only`).

### Grant access
```
POST /sites/{site_id}/grant-access
```

Shares the site with additional CaptainCore accounts. Non-admins must already belong to each target account.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `account_ids` | array or string | Yes | Account IDs to add (merged with current shares) |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"account_ids":[12,18]}' \
  https://{your-site}/wp-json/captaincore/v1/sites/135/grant-access
```

**Response:** the numeric site ID.

---

## Site CLI Commands

### Run a CLI command
```
POST /sites/cli
```

Runs a **named built-in command** (not arbitrary WP-CLI — use `POST /run/code` for that). Permission-checked per site ID.

**API requests** (application passwords, no `X-WP-Nonce`): background commands run **synchronously** (up to 5 minutes) unless `"async": true`. **UI/nonce requests** return a job token string for WebSocket streaming.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `post_id` | integer or array | Yes | Site ID, or IDs for bulk |
| `command` | string | Yes | One of the commands below |
| `environment` | string | No | **`Production`**, **`Staging`**, or **`Both`** (capitalized). Only `Staging` appends `-staging`. Empty = production |
| `value` | mixed | No | Command-specific |
| `async` | boolean | No | API only: queue instead of wait |
| `background` | boolean | No | Force background dispatch |
| `version` | string | No | Rollback version |
| `commit` / `hash` | string | No | Quicksave hash |
| `arguments` | object | No | `manage`: `{ "value": "command", "input": "wp ..." }` |
| `filters` | array | No | Snapshot filters: `database`, `themes`, `plugins`, `uploads` |
| `addon_type` | string | No | `plugin` or `theme` for `rollback` |
| `date` | string | No | Snapshot `--rollback=` timestamp |
| `notes` | string | No | Snapshot notes |
| `name` / `link` / `subject` / `status_msg` / `action_text` | string | No | Deactivate visitor copy |
| `update_urls` | string | No | `migrate`: string `"true"` enables URL rewrite |

Unknown `command` → 400 `Unknown command.`

| `command` | What it does | Extra fields |
|-----------|----------------|--------------|
| `sync-data` | Refresh plugins/themes/storage | |
| `users-fetch` | Refresh cached WP users | |
| `deploy-defaults` | Deploy account/site defaults | |
| `update` | `site sync {id}` | |
| `new` | `site sync {id} --update-extras` | |
| `update-wp` | Core/plugin/theme updates | |
| `reset-permissions` | Reset filesystem permissions | |
| `apply-https` | Rewrite URLs to HTTPS | |
| `apply-https-with-www` | HTTPS + www | |
| `launch` | Launch onto a domain | `value` = domain |
| `migrate` | Overwrite site from a backup ZIP URL | `value` = URL; `update_urls`: `"true"` |
| `copy` | Copy this site onto another CaptainCore site | `value` = destination **site ID** |
| `production-to-staging` | Copy prod → staging | `value` = optional notify email |
| `staging-to-production` | Copy staging → prod | `value` = optional notify email |
| `scan-errors` | Error/console scan | |
| `backup` | Full backup | |
| `quick_backup` | Generate a quicksave | |
| `snapshot` | Generate a downloadable snapshot | `value` = email; `notes`; `filters`; `date` for point-in-time |
| `backup_download` | Email selected files from a restic backup | `value`: `{ backup_id, files, directories }` |
| `activate` | Take site out of maintenance | |
| `deactivate` | Maintenance mode | `name`, `link`, `subject`, `status_msg`, `action_text` |
| `recipe` | Run a saved recipe | `value` = recipe ID |
| `run` | Run ad-hoc code (base64) | `value` = script/code |
| `manage` | Run a WP-CLI line via ssh | `value` usually `"ssh"`; `arguments.input` = WP-CLI |
| `view_quicksave_changes` | Show quicksave changeset | `value` = hash |
| `quicksave_file_diff` | HTML diff of one file | `commit`, `value` = path |
| `rollback` | Roll back one plugin/theme | `commit`, `version`, `addon_type`, `value` = slug |
| `quicksave_rollback` | Roll back entire quicksave | `commit`, `version` |
| `quicksave_file_restore` | Restore one file | `hash`, `value` = path |
| `remove` | CLI `site delete` (destructive) | |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"post_id":135,"command":"reset-permissions","environment":"Production"}' \
  https://{your-site}/wp-json/captaincore/v1/sites/cli
```

**Synchronous API response:**
```json
{"status": "completed", "response": "...command output..."}
```

Some foreground commands return the raw CLI body string instead of the `{status,response}` wrapper.

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"post_id":135,"command":"backup","environment":"Production","async":true}' \
  https://{your-site}/wp-json/captaincore/v1/sites/cli
```

```json
{"status": "queued", "token": "abc123..."}
```

Poll for results with `GET /my-jobs/{token}`.

---

## Bulk tools

### Run a bulk tool
```
POST /sites/bulk-tools
```

Runs one tool across many **environment IDs**. Each ID is permission-checked; unauthorized targets are skipped. If none remain, 403.

API requests: synchronous (5 min) unless `"async": true`. UI/nonce: job token.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `tool` | string | Yes | See table |
| `environments` | array | Yes | **Environment IDs** (integers), not site IDs |
| `params` | object | No | Tool-specific |
| `async` | boolean | No | API only |

| `tool` | Extra `params` |
|--------|----------------|
| `sync-data` | — |
| `deploy-defaults` | — |
| `activate` | — |
| `deactivate` | `business_name`, `business_link` |
| `apply-https` | `www`: truthy → HTTPS with www |
| `launch` | `domain` (hostname, no scheme) |
| `scan-errors` | — |
| `backup` | — |
| `snapshot` | — |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{
    "tool": "apply-https",
    "environments": [3365],
    "params": {"www": true},
    "async": true
  }' \
  https://{your-site}/wp-json/captaincore/v1/sites/bulk-tools
```

**Async:** `{ "status": "queued", "token": "abc123..." }` — poll `GET /my-jobs/{token}`.

**Sync:** `{ "status": "completed", "response": "..." }`.

---

## Quicksaves

Git snapshots of plugins, themes, and WordPress core for a site environment. Most routes take `site_id` and `environment` as query parameters (`production` or `staging`; other values are treated as production).

### List quicksaves
```
GET /quicksaves
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `site_id` | integer | Yes | Site ID |
| `environment` | string | No | `production` or `staging`. If omitted, the CLI lists both. |

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/quicksaves?site_id=135&environment=production"
```

```json
[
  {
    "hash": "446d35f6b1f845b4371bf109404e1269d19c65ca",
    "created_at": "1771427726",
    "core": "6.9.1",
    "theme_count": 2,
    "plugin_count": 39,
    "plugins": [],
    "themes": [],
    "status": ""
  }
]
```

`plugins` / `themes` are empty on the list; populated by GET `/quicksaves/{hash}`.

### Search quicksaves
```
GET /quicksaves/search
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `site_id` | integer | Yes | Site ID |
| `environment` | string | Yes | `production` or `staging` |
| `search` | string | Yes | `type:field:query`. `type` is `plugin` or `theme`. `field` is `name`, `title`, `status`, or `version`. |

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/quicksaves/search?site_id=135&environment=production&search=plugin:name:woocommerce"
```

**Response:** JSON array of matching quicksaves. Each item includes at least `hash` and an `item` object for the matched component.

### Get a quicksave
```
GET /quicksaves/{hash}
```

Returns detailed quicksave data including plugin/theme versions and what changed vs the previous snapshot.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `site_id` | integer | Yes | Query param |
| `environment` | string | Yes | Query param |

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/quicksaves/446d35f6...?site_id=135&environment=production"
```

| Field | Type | Description |
|-------|------|-------------|
| `plugins` / `themes` | array | Objects include `name`, `title`, `version`, `status`. `new`, `changed_version`, `changed_status` mark what changed. |
| `plugins_deleted` / `themes_deleted` | array | Components removed since the previous snapshot |
| `core` | string or object | Core version |
| `status` | string | Change summary |

### Get changed files
```
GET /quicksaves/{hash}/changed
```

Returns a **plain-text** git-style change list (not JSON). Lines are typically `STATUS\tpath` (`M` modified, `A` added, `D` deleted).

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `site_id` | integer | Yes | Query |
| `environment` | string | Yes | Query |
| `match` | string | No | Optional path prefix/filter |

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/quicksaves/446d35f6.../changed?site_id=135&environment=production"
```

### Get file diff
```
GET /quicksaves/{hash}/filediff
```

Returns a git unified diff as HTML-escaped text.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `site_id` | integer | Yes | Query |
| `environment` | string | Yes | Query |
| `file` | string | Yes | Path relative to the quicksave (e.g. `plugins/akismet/akismet.php`) |

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/quicksaves/446d35f6.../filediff?site_id=135&environment=production&file=plugins/akismet/akismet.php"
```

### Rollback a quicksave
```
POST /quicksaves/{hash}/rollback
```

Starts a CLI task. `type=all` rolls back the whole snapshot; otherwise `type` is `plugin` or `theme` and `value` is the slug.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `site_id` | integer | Yes | Site ID |
| `environment` | string | Yes | `production` or `staging` |
| `version` | string | Yes | Passed as `--version=` (the UI sends `this`) |
| `type` | string | Yes | `all`, `plugin`, or `theme` |
| `value` | string | If `type` ≠ `all` | Plugin/theme slug |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"site_id":135,"environment":"production","version":"this","type":"plugin","value":"woocommerce"}' \
  https://{your-site}/wp-json/captaincore/v1/quicksaves/446d35f6.../rollback
```

This is a background CLI task, not an immediate restore confirmation.

### Create a sandbox token
```
POST /quicksaves/{hash}/sandbox-token
```

Mints a 64-character token stored for **30 minutes**. The token unlocks the public blueprint and artifact URLs below (WordPress Playground).

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `site_id` | integer | Yes | Site ID |
| `environment` | string | No | Defaults to `production` |
| `include_database` | boolean | No | If true, the blueprint adds a `runSql` step |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"site_id":135,"environment":"production","include_database":true}' \
  https://{your-site}/wp-json/captaincore/v1/quicksaves/446d35f6.../sandbox-token
```

```json
{
  "token": "64-character-random-string",
  "blueprint_url": "https://{your-site}/wp-json/captaincore/v1/quicksaves/446d35f6.../blueprint?token=64-character-random-string"
}
```

Open Playground with `https://playground.wordpress.net/?blueprint-url=` + URL-encoded `blueprint_url`.

### Get Playground blueprint (public, token-protected)
```
GET /quicksaves/{hash}/blueprint
```

Public. Auth is the `token` query param from `/sandbox-token`. Missing/expired token → `403`. Response is WordPress Playground blueprint JSON (`Access-Control-Allow-Origin: *`).

### Download a sandbox artifact (public, token-protected)
```
GET /quicksaves/{hash}/artifact
```

Public. Streams a zip (plugin/theme) or SQL dump (database). Same token rules as blueprint.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `token` | string | Yes | Sandbox token |
| `type` | string | Yes | `plugin`, `theme`, or `database` |
| `name` | string | Yes except `database` | Slug; must match `^[a-zA-Z0-9_-]+$` |

```bash
curl -L \
  "https://{your-site}/wp-json/captaincore/v1/quicksaves/446d35f6.../artifact?token=TOKEN&type=plugin&name=woocommerce" \
  -o woocommerce.zip
```

---

## Update Logs

Update logs track plugin and theme updates applied to a site over time. Each log entry records the quicksave hashes before and after the update, a summary of files changed, and counts of plugins/themes that were updated.

### List update logs
```
GET /update-logs
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `site_id` | integer | Yes | Site ID (query param) |
| `environment` | string | No | `production` or `staging`. If omitted, lists both. |

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/update-logs?site_id=135&environment=production"
```

```json
[
  {
    "hash_before": "058d6264b1f845b4371bf109404e1269d19c65ca",
    "hash_after": "997b53b4097e04f482fe01da18c97c3e9918602d",
    "created_at": "1771427726",
    "started_at": "1771401498",
    "status": "275 files changed, 4757 insertions(+), 6959 deletions(-)",
    "core": "6.9.1",
    "theme_count": 2,
    "plugin_count": 39,
    "core_previous": "6.9.1",
    "themes_changed": 0,
    "plugins_changed": 6,
    "plugins": [],
    "themes": []
  }
]
```

| Field | Type | Description |
|-------|------|-------------|
| `hash_before` / `hash_after` | string | Quicksave hashes bracketing the update |
| `created_at` / `started_at` | string | Unix timestamps |
| `status` | string | Summary of file changes (git diff stat format) |
| `core` / `core_previous` | string | WordPress versions after / before |
| `plugins_changed` / `themes_changed` | integer | Number updated |

### Get update log diff
```
GET /update-logs/{hash_before}_{hash_after}
```

Returns the detailed diff between two quicksave snapshots. Same component fields as GET `/quicksaves/{hash}`.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `site_id` | integer | Yes | Query |
| `environment` | string | Yes | Query |

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/update-logs/058d6264..._997b53b4...?site_id=135&environment=production"
```

### List update logs for a site
```
GET /sites/{site_id}/update-logs
```

Same payload as GET `/update-logs` with environment omitted (`both`). Prefer `GET /update-logs` when you need a single environment and hash-level diffs.

---

## Site Audits

Malware / security / performance reports tied to a site environment. Non-admins only see audits for sites they can access. Creating, editing, deleting, and findings CRUD are administrator-only.

### List audits
```
GET /site-audits
```

```bash
curl -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/site-audits
```

Each row includes `site_audit_id`, `site_id`, `environment_id`, `status`, `report_type`, `site_name`, `finding_counts` (`critical`, `high`, `medium`, `low`, `open`, `resolved`, `total`), plus stored scan fields.

`status`: `requested`, `in_progress`, `clean`, `issues_found`, `compromised`, `remediated`.

`report_type`: `security_audit`, `malware_incident`, `performance_review`, `accessibility_audit`, `debug_report`, `incident_report`.

Not paginated.

### List audits for one site
```
GET /sites/{site_id}/site-audits
```

Same rows filtered by `site_id`.

### Get one audit
```
GET /site-audits/{id}
```

Adds `home_url`, `environment`, `site_name`, decoded JSON fields, and `findings` (severity, status, title, description, evidence, recommendation, resolution). `404` / `403` as appropriate.

### HTML report
```
GET /site-audits/{id}/html
```

Raw `text/html` (not JSON).

```bash
curl -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/site-audits/42/html
```

### Request an audit
```
POST /site-audits/request
```

Creates `status=requested` and emails operators. One open request per site+environment.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `site_id` | integer | Yes | Site |
| `environment_id` | integer | Yes | Must belong to `site_id` |
| `report_type` | string | No | Default `security_audit` |
| `notes` | string | No | Customer notes |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"site_id":135,"environment_id":3365,"report_type":"security_audit","notes":"Checkout errors after deploy"}' \
  https://{your-site}/wp-json/captaincore/v1/site-audits/request
```

```json
{"site_audit_id": 42}
```

`400 already_requested` if a `requested` row already exists.

### Cancel a queued request
```
POST /site-audits/{id}/cancel
```

Only while `status=requested`. Requester or admin. Deletes the row. Response: `{ "cancelled": true, "site_audit_id": 42 }`. `400 not_cancellable` if not `requested`.

### Publish / unpublish static HTML
```
POST   /site-audits/{id}/publish
DELETE /site-audits/{id}/publish
```

**POST response:** `{ "report_path": "…html", "report_url": "https://{your-site}/reports/…" }`

**DELETE response:** `{ "unpublished": true }` (`404` if nothing published).

### Audit coverage for an environment
```
GET /sites/{site_id}/environments/{environment_id}/audit-coverage
```

Public projection of component hashes vs the WP Registry (embargoed findings are not included).

```bash
curl -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/sites/135/environments/3365/audit-coverage
```

```json
{
  "site_id": 135,
  "environment_id": 3365,
  "environment": "Production",
  "summary": {
    "total": 40,
    "audited": 32,
    "unaudited": 8,
    "malware": 0,
    "critical": 0,
    "high": 1,
    "medium": 2,
    "low": 4,
    "clean": 25,
    "coverage_pct": 80,
    "generated_at": "2026-04-01T12:00:00+00:00"
  },
  "components": [
    {
      "kind": "plugin",
      "slug": "woocommerce",
      "name": "WooCommerce",
      "version": "9.8.1",
      "hash": "…full sha…",
      "short_hash": "bd48c24ce450",
      "status": "clean",
      "findings_count": 0
    }
  ]
}
```

`status` is `unaudited`, `clean`, `low`, `medium`, `high`, `critical`. `kind`: `plugin`, `theme`, `file`.

### Findings for one component hash
```
GET /sites/{site_id}/environments/{environment_id}/audit-coverage/{hash}
```

`hash` is 40–64 hex and must exist on that environment (`404` otherwise). If the registry has no audit, `status` is `unaudited` and `findings` is `[]`.

---

## Filters

All results are scoped to sites the user can access.

### List filter options
```
GET /site-filters
```

Fleet-wide unique themes and plugins for the current user.

```bash
curl -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/site-filters
```

**Response:** a single array: a Themes subheader, theme rows, a Plugins subheader, plugin rows.

```json
[
  {"type": "subheader", "title": "Themes", "name": "themes", "search": "Themes"},
  {"name": "twentytwentyfive", "title": "Twenty Twenty-Five", "search": "Twenty Twenty-Five (twentytwentyfive)", "type": "themes"},
  {"type": "subheader", "title": "Plugins", "name": "plugins", "search": "Plugins"},
  {"name": "woocommerce", "title": "WooCommerce", "search": "WooCommerce (woocommerce)", "type": "plugins"}
]
```

### Filter sites by plugin or theme
```
POST /filters/sites
```

Returns site/environment ID pairs matching the given plugin and/or theme filters.

| Field | Type | Description |
|-------|------|-------------|
| `plugins` | array | `{ "name": "slug" }` |
| `themes` | array | Same shape |
| `versions` | array | `{ "slug", "name" (version), "type": "plugins"|"themes" }` |
| `statuses` | array | `{ "slug", "name" (status), "type": "plugins"|"themes" }` |
| `core` | array | WordPress versions (OR) |
| `php` | array | PHP versions (OR) |
| `backup_mode` | string | Matches environment backup mode |
| `logic` | string | `AND` (default) or `OR` for plugin/theme name filters |
| `version_logic` / `status_logic` | string | `AND` (default) or `OR` |
| `version_mode` / `status_mode` | string | `include` (default) or `exclude` |

**Find all environments with a specific plugin:**
```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"plugins":[{"name":"woocommerce"}]}' \
  https://{your-site}/wp-json/captaincore/v1/filters/sites
```

```json
{
  "results": [
    {"site_id": "135", "environment_id": "3365"}
  ]
}
```

**Find environments matching multiple plugins (AND logic):**
```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"plugins":[{"name":"woocommerce"},{"name":"gravityforms"}]}' \
  https://{your-site}/wp-json/captaincore/v1/filters/sites
```

**Find environments matching either plugin (OR logic):**
```json
{"plugins":[{"name":"woocommerce"},{"name":"gravityforms"}],"logic":"OR"}
```

**Exclude a specific plugin version:**
```json
{
  "plugins": [{"name": "woocommerce"}],
  "versions": [{"slug": "woocommerce", "name": "9.8.1", "type": "plugins"}],
  "version_mode": "exclude"
}
```

**Filter by WordPress core version:**
```json
{"core": ["6.8.3", "6.7.4"]}
```

`POST /filters` is a weaker legacy handler — prefer `POST /filters/sites`.

### Get filter versions/statuses
```
GET /filters/{name}/versions/
GET /filters/{name}/statuses/
```

**Trailing slash is required.** `{name}` is a comma-separated list of slugs.

```bash
curl -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/filters/woocommerce/versions/
```

```json
[
  {
    "name": "woocommerce",
    "versions": [
      {"name": "9.8.1", "slug": "woocommerce", "type": "plugins", "count": 12}
    ]
  }
]
```

---

## WordPress.org plugins and themes

Used by the in-app “Add plugin/theme” dialog. Proxies `plugins_api` / `themes_api`. Always `per_page=9`.

### Search plugins
```
GET /wp-plugins
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `page` | integer | No | Page number |
| `value` | string | No | Search. If omitted, `browse=popular`. |

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/wp-plugins?page=1&value=woocommerce"
```

**Response:** WordPress.org object: `info` (`page`, `pages`, `results`) and `plugins` array.

### Search themes
```
GET /wp-themes
```

Same query params. Response: `info` + `themes`.

---

## Domains

Domain inventory (CaptainCore `domain_id`), distinct from hosting-provider domains on `GET/POST/DELETE /sites/{id}/{environment}/domains`.

Each domain-scoped handler also verifies you can access that domain. Admins see all domains. List vs detail use different path prefixes: `GET /domains` (plural) vs `GET /domain/{id}` (singular).

### List all domains
```
GET /domains
```

```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/domains
```

```json
[
  {
    "domain_id": 37,
    "remote_id": "123456",
    "provider_id": "4",
    "name": "example.com",
    "status": "active",
    "price": ""
  }
]
```

| Field | Description |
|-------|-------------|
| `domain_id` | CaptainCore domain ID (use this on `/domain/{id}` and `/dns/{id}`) |
| `remote_id` | Constellix zone ID, or empty if no DNS zone |
| `provider_id` | Registrar provider ID, or `""` if not registrar-managed |

### Get domain details
```
GET /domain/{domain_id}
```

Registrar snapshot, linked accounts, sites on those accounts, and Mailgun flags in `details`. Does **not** return `name`, `domain_id`, or `remote_id` at the top level — keep those from the list.

```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/domain/37
```

```json
{
  "provider": {
    "domain": "example.com",
    "nameservers": [{"value": "ns1.example.net"}, {"value": "ns2.example.net"}],
    "contacts": {
      "owner": {
        "first_name": "Jane",
        "last_name": "Doe",
        "org_name": "Example Org",
        "address1": "1 Main St",
        "city": "Austin",
        "state": "TX",
        "postal_code": "78701",
        "country": "US",
        "phone": "+1.5125550100",
        "email": "jane@example.com"
      },
      "admin": {},
      "billing": {},
      "tech": {}
    },
    "locked": "on",
    "whois_privacy": "on",
    "status": "active"
  },
  "accounts": [{"account_id": 75, "name": "Example Org"}],
  "provider_id": "4",
  "connected_sites": [
    {"id": 47, "name": "example.com", "environment": "Production"}
  ],
  "details": {
    "mailgun_id": "abc",
    "mailgun_zone": "mg.example.com",
    "mailgun_forwarding_id": "xyz"
  }
}
```

If the domain has no registrar (`provider_id` empty), `provider` is `{ "errors": ["No remote domain found."] }`. 403 if the domain is not in your account set.

### Create a domain
```
POST /domains
```

Adds a CaptainCore domain record and optionally creates a Constellix DNS zone.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Apex hostname (`example.com`) |
| `site_id` | integer | **Yes for non-admins** | Site whose `customer_id` becomes the domain account |
| `create_dns_zone` | boolean | No | Create/link a DNS zone. **Defaults to `true`** if omitted. Send `false` to skip |

Non-admins: omit `site_id` → `"Website must be selected."`. Site must be one you can access and must have a customer account. Name must be unique. Validation failures return **HTTP 200**: `{ "errors": ["Domain has already been added."] }`.

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"name":"example.com","site_id":47,"create_dns_zone":true}' \
  https://{your-site}/wp-json/captaincore/v1/domains
```

```json
{"name": "example.com", "domain_id": 37, "remote_id": 123456}
```

`remote_id` is `null` when `create_dns_zone` is false.

### Delete a domain
```
DELETE /domains/{domain_id}
```

Removes the CaptainCore record and, when present: Mailgun **sending** zone, Mailgun **apex forwarding** domain, Constellix DNS zone, and account↔domain links. If a registrar `provider_id` is set, Hover auto-renew is turned off. **Registration is not cancelled.**

```bash
curl -X DELETE -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/domains/37
```

```json
{
  "domain_id": 37,
  "message": "Deleted domain example.com (also removed: mailgun, email_forwarding, dns_zone)",
  "removed": ["mailgun", "email_forwarding", "dns_zone"],
  "warnings": []
}
```

### Update site link
```
POST /domain/{domain_id}/update-site-link
```

Replaces **all** account links on the domain with the selected site's `customer_id`. You must own both the domain and the site.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `site_id` | integer | Yes | Site whose customer account should own the domain |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"site_id":47}' \
  https://{your-site}/wp-json/captaincore/v1/domain/37/update-site-link
```

```json
{"message": "Domain billing account updated successfully."}
```

400 if `site_id` missing or the site has no customer account.

### Domain registrar controls

These require a registrar `provider_id` (Hover or Spaceship). Otherwise `{ "errors": ["No remote domain found."] }`. Status for lock/privacy is a **path segment**, not a body.

```
GET  /domain/{domain_id}/lock_on
GET  /domain/{domain_id}/lock_off
GET  /domain/{domain_id}/privacy_on
GET  /domain/{domain_id}/privacy_off
GET  /domain/{domain_id}/auth_code
POST /domain/{domain_id}/contacts
POST /domain/{domain_id}/nameservers
```

**Lock / privacy:**
```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/domain/37/lock_on
```

Status other than `on`/`off` → 404 `request_invalid`. Spaceship privacy `on` → level `high`; `off` → `public`.

**Auth code** returns a JSON string, e.g. `"EPP-AUTH-CODE"`. Hover may return `""` if none. No registrar: HTTP **200** error `{ "code": "no_domain", "message": "No records" }`.

**Update contacts:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `contacts` | object | Yes | Keys `owner`, `admin`, `tech`, `billing` |

Each contact: `first_name`, `last_name`, `org_name`, `address1`, `address2`, `city`, `state`, `postal_code`, `country`, `phone`, `email`.

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"contacts":{"owner":{"first_name":"Jane","last_name":"Doe","org_name":"Example Org","address1":"1 Main St","address2":"","city":"Austin","state":"TX","postal_code":"78701","country":"US","phone":"+1.5125550100","email":"jane@example.com"}}}' \
  https://{your-site}/wp-json/captaincore/v1/domain/37/contacts
```

**Success:** `{ "response": "Contacts have been updated." }`

**Update nameservers:** `{ "nameservers": ["ns1.example.net", "ns2.example.net"] }` (array of hostname **strings**, not `{value:…}` objects).

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"nameservers":["ns1.example.net","ns2.example.net"]}' \
  https://{your-site}/wp-json/captaincore/v1/domain/37/nameservers
```

**Success:** `{ "response": "Nameservers have been updated." }`

---

## DNS

DNS is hosted on Constellix. `{id}` on **GET/POST/PUT/DELETE `/dns/{id}…` record routes is CaptainCore `domain_id`**. The bulk route is different (see below). Root hostname: send `name` as `""` (empty). `@` is a UI convention; Constellix stores apex as empty.

### Get DNS records
```
GET /dns/{domain_id}
```

```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/dns/37
```

```json
{
  "records": [
    {
      "id": 60146555,
      "type": "A",
      "name": "www",
      "ttl": 3600,
      "value": [{"value": "192.0.2.1", "enabled": true}]
    },
    {
      "id": 60146556,
      "type": "MX",
      "name": "",
      "ttl": 3600,
      "value": [{"server": "mxa.mailgun.org.", "priority": 10, "enabled": true}]
    }
  ],
  "nameservers": ["ns1.example.net", "ns2.example.net"]
}
```

Records are sorted by `type` then `name`. 404 `no_zone` if `remote_id` is empty. `nameservers` here are the **Constellix zone** nameservers.

### Create a DNS record
```
POST /dns/{domain_id}/records
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | string | Yes | `A`, `AAAA`, `ANAME`, `CNAME`, `TXT`, `SPF`, `MX`, `SRV`, `HTTP` |
| `name` | string | Yes | Relative host. `""` for apex |
| `value` | mixed | Yes | See formats below |
| `ttl` | integer | Yes | Seconds (default **3600** if missing on create) |

**Value formats:**

A / AAAA / ANAME / CNAME / TXT / SPF — array of `{ "value": "…" }`:
```json
{"type": "A", "name": "www", "value": [{"value": "192.0.2.1"}], "ttl": 300}
```

MX — `{ "server", "priority" }`:
```json
{"type": "MX", "name": "", "value": [{"server": "mxa.mailgun.org.", "priority": 10}], "ttl": 3600}
```

SRV — `{ "host", "priority", "weight", "port" }`:
```json
{"type": "SRV", "name": "_sip._tcp", "value": [{"host": "sip.example.com.", "priority": 10, "weight": 5, "port": 5060}], "ttl": 3600}
```

HTTP (URL redirect) — **string URL**, not an array. Always stored as hard 301:
```json
{"type": "HTTP", "name": "go", "value": "https://example.com/new", "ttl": 3600}
```

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"type":"A","name":"www","value":[{"value":"192.0.2.1"}],"ttl":300}' \
  https://{your-site}/wp-json/captaincore/v1/dns/37/records
```

**Response:** HTTP 201, Constellix create payload (typically `{ "data": { "id": 60146555, … } }`).

### Update a DNS record
```
PUT /dns/{domain_id}/records/{record_id}
```

Same body as create (`type`, `name`, `value`, `ttl` all required).

```bash
curl -X PUT -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"type":"A","name":"www","value":[{"value":"192.0.2.2"}],"ttl":300}' \
  https://{your-site}/wp-json/captaincore/v1/dns/37/records/60146555
```

### Delete a DNS record
```
DELETE /dns/{domain_id}/records/{record_id}
```

```bash
curl -X DELETE -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/dns/37/records/60146555
```

```json
{"message": "Record deleted successfully."}
```

### Bulk DNS update
```
POST /dns/{remote_id}/bulk
```

**`{remote_id}` is the Constellix zone ID (`domain.remote_id`), not CaptainCore `domain_id`.**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `record_updates` | array | Yes | One object per change |

Each update: `record_id` (required for edit/remove), `record_type`, `record_name`, `record_value`, `record_ttl`, `record_status` (`new-record` | `edit-record` | `remove-record`).

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{
    "record_updates": [
      {
        "record_id": "",
        "record_type": "A",
        "record_name": "test",
        "record_value": [{"value": "192.0.2.1"}],
        "record_ttl": 300,
        "record_status": "new-record"
      }
    ]
  }' \
  https://{your-site}/wp-json/captaincore/v1/dns/123456/bulk
```

**Response:** array of Constellix results. Prefer per-record CRUD unless you need a batch.

### Get zone file
```
GET /domains/{domain_id}/zone
```

Returns a **plain BIND zonefile string** (not JSON).

```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/domains/37/zone
```

### Parse / import zone text
```
POST /domains/import
```

**Parses** a BIND zone into `{name,type,value}` rows. **Does not create a domain or write records.**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `domain` | string | No | Apex. Overridden if `$ORIGIN` appears in `zone` |
| `zone` | string | Yes | BIND zone text |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"domain":"example.com","zone":"$ORIGIN example.com.\\nwww 300 IN A 192.0.2.1\\n"}' \
  https://{your-site}/wp-json/captaincore/v1/domains/import
```

```json
[{"name": "www", "type": "A", "value": "192.0.2.1"}]
```

### Activate DNS zone
```
POST /domain/{domain_id}/activate-dns-zone
```

Creates a Constellix zone and stores `remote_id`. No body.

```bash
curl -X POST -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/domain/37/activate-dns-zone
```

```json
{"message": "DNS zone activated successfully.", "remote_id": "123456"}
```

### Delete DNS zone
```
DELETE /domain/{domain_id}/dns-zone
```

Deletes the Constellix zone and clears local `remote_id`. Domain record remains. 404 `zone_not_found` if no `remote_id`.

```bash
curl -X DELETE -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/domain/37/dns-zone
```

```json
{"message": "DNS zone deleted successfully."}
```

---

## Email Forwarding

Inbound Mailgun on the **apex** (`example.com`), stored as `details.mailgun_forwarding_id`. Distinct from sending (`mg.example.com` / `mailgun_zone`).

### Activate email forwarding
```
POST /domain/{domain_id}/activate-forward-email
```

Creates/reuses the Mailgun apex domain, writes MX (and unverified TXT/CNAME) on Constellix when a zone exists, then verifies.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `overwrite_mx` | boolean | No | If apex MX already exist, first call returns **409** `mx_conflict`. Retry with `true` to replace them |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"overwrite_mx":false}' \
  https://{your-site}/wp-json/captaincore/v1/domain/37/activate-forward-email
```

```json
{
  "id": "mailgun-domain-id",
  "name": "example.com",
  "has_mx_record": true,
  "forwarding_active": true
}
```

Already active → `already_active`. Without a DNS zone, Mailgun is still created; MX must be set at the external DNS host.

### List email forwards
```
GET /domain/{domain_id}/email-forwards
```

```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/domain/37/email-forwards
```

```json
[
  {
    "id": "64f1a2b3c4d5e6f7",
    "name": "hello",
    "recipients": ["inbox@example.com"],
    "description": "Email forward: hello@example.com",
    "expression": "match_recipient(\"hello@example.com\")",
    "priority": 0
  }
]
```

Catch-all aliases use `"name": "*"`. `{id}` is the Mailgun **route id**.

### Create email forward
```
POST /domain/{domain_id}/email-forwards
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Local part (`hello`), or `*` / `""` for catch-all |
| `recipients` | string \| string[] \| `{address}[]` | Yes | Destination addresses. Comma-separated string is split |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"name":"hello","recipients":"inbox@example.com,backup@example.com"}' \
  https://{your-site}/wp-json/captaincore/v1/domain/37/email-forwards
```

**Response:** one alias object (same shape as list).

### Update email forward
```
PUT /domain/{domain_id}/email-forwards/{alias_id}
```

`alias_id` is the Mailgun route id. Send `name` and/or `recipients`. Empty body → `no_changes`.

```bash
curl -X PUT -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"recipients":["inbox@example.com"]}' \
  https://{your-site}/wp-json/captaincore/v1/domain/37/email-forwards/64f1a2b3c4d5e6f7
```

### Delete email forward
```
DELETE /domain/{domain_id}/email-forwards/{alias_id}
```

```bash
curl -X DELETE -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/domain/37/email-forwards/64f1a2b3c4d5e6f7
```

### Email forwarding status
```
GET /domain/{domain_id}/email-forwarding/status
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `verify` | boolean | If truthy, triggers Mailgun verify first |

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/domain/37/email-forwarding/status?verify=1"
```

```json
{
  "id": "example.com",
  "name": "example.com",
  "has_mx_record": true,
  "has_txt_record": true,
  "forwarding_active": true,
  "state": "active"
}
```

### Email forwarding logs
```
GET /domain/{domain_id}/email-forwarding/logs
```

Events on the **apex** Mailgun domain. 404 `forwarding_not_configured` if forwarding was never activated.

| Parameter | Type | Description |
|-----------|------|-------------|
| `event` | string | Mailgun filter. Default `stored OR accepted OR delivered OR failed` |
| `page_url` | string | Full Mailgun paging URL from a previous response |

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/domain/37/email-forwarding/logs"
```

Response is Mailgun events JSON (`items`, `paging`). Limit is 100.

---

## Mailgun

Sending zone is usually `mg.example.com`, stored as `details.mailgun_zone` / `mailgun_id`. Distinct from apex email forwarding. 404 `mailgun_not_configured` when the sending zone is missing.

### Get Mailgun details
```
GET /domain/{domain_id}/mailgun
```

```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/domain/37/mailgun
```

```json
{
  "domain": {"name": "mg.example.com", "state": "active", "id": "abc"},
  "sending_dns_records": [
    {"record_type": "TXT", "name": "k1._domainkey.mg", "value": "k=rsa; p=…", "valid": "valid"}
  ],
  "receiving_dns_records": [
    {"record_type": "MX", "name": "mg", "value": "mxa.mailgun.org", "priority": "10", "valid": "valid"}
  ]
}
```

### Setup Mailgun
```
POST /domain/{domain_id}/mailgun/setup
```

Creates the Mailgun domain if needed and, when Constellix hosts the apex, injects MX/TXT/CNAME.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `domain` | string | Yes | Full Mailgun hostname, typically `mg.example.com` |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"domain":"mg.example.com"}' \
  https://{your-site}/wp-json/captaincore/v1/domain/37/mailgun/setup
```

```json
{"success": true, "message": "Mailgun zone created and DNS records are being added."}
```

### Verify Mailgun DNS
```
POST /domain/{domain_id}/mailgun/verify
```

No body. 404 `mailgun_zone_missing` if setup was never run.

```bash
curl -X POST -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/domain/37/mailgun/verify
```

### Deploy Mailgun
```
POST /domain/{domain_id}/mailgun/deploy
```

Mints SMTP credentials if needed and deploys Gravity SMTP on a site you can access.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `site_id` | integer | Yes | Target site |
| `from_name` | string | Yes | Send-from display name |
| `environment` | string | No | If not `production`, site slug becomes `{site}-{environment}` |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"site_id":47,"environment":"production","from_name":"Example"}' \
  https://{your-site}/wp-json/captaincore/v1/domain/37/mailgun/deploy
```

```json
{"success": true, "output": "…"}
```

### Delete Mailgun
```
DELETE /domain/{domain_id}/mailgun
```

Deletes the Mailgun **sending** domain and clears `mailgun_id`, `mailgun_zone`, `mailgun_smtp_password`. Does not remove apex forwarding.

```bash
curl -X DELETE -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/domain/37/mailgun
```

If nothing was configured: `{ "message": "No Mailgun zone configured." }`.

### Mailgun events
```
GET /domain/{domain_id}/mailgun/events
GET /sites/{site_id}/mailgun-events
```

**Domain events** query the sending zone (`mailgun_zone`). All query parameters are forwarded to Mailgun (`event`, `limit`, `begin`, `end`, `recipient`). `page_url` uses Mailgun paging.

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/domain/37/mailgun/events?limit=25"
```

**Site events** use the site's `mailgun` field. Without `page`, events are `accepted OR rejected OR delivered OR failed OR complained`, `limit` 300. Query `page` is the Mailgun paging URL.

```bash
curl -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/sites/47/mailgun-events
```

### Mailgun usage
```
GET /domain/{domain_id}/mailgun/usage
```

Outgoing stats for `mailgun_zone`. Cached 10 minutes.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `period` | string | `day` | `day` (30d daily), `month` (12m monthly), `year` (24m rolled up by calendar year) |

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/domain/37/mailgun/usage?period=day"
```

```json
{
  "zone": "mg.example.com",
  "period": "day",
  "totals": {"sent": 1200, "received": 10, "delivered": 1180, "failed": 20, "delivery_rate": 98.3},
  "series": [
    {"time": 1770000000, "label": "Jul 21", "sent": 40, "received": 0, "delivered": 39, "failed": 1}
  ]
}
```

### Mailgun suppressions
```
GET    /domain/{domain_id}/mailgun/suppressions/{type}
DELETE /domain/{domain_id}/mailgun/suppressions/{type}
```

`{type}` must be one of: `bounces`, `unsubscribes`, `complaints`, `whitelists` (allowlist). Anything else → 400 `invalid_type`.

```bash
curl -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/domain/37/mailgun/suppressions/bounces
```

```json
{"items": [{"address": "user@example.com", "code": "550", "error": "mailbox unavailable"}]}
```

**Delete** requires `address` (query or JSON). For allowlist, pass Mailgun `value` as `address`.

```bash
curl -X DELETE -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/domain/37/mailgun/suppressions/bounces?address=user%40example.com"
```

---

## Accounts

Access tiers (pivot `level`, except owner which is `plan.billing_user_id`):

| Level | Sites | Domains | Users | Invites | Invoices / plan |
|-------|-------|---------|-------|---------|-----------------|
| `full-billing` (Owner) | yes | yes | manage | manage | yes |
| `full` | yes | yes | view | send | no |
| `sites-only` | yes | no | no | no | no |
| `domains-only` | no | yes | no | no | no |

### List accounts
```
GET /accounts
```

Returns accounts the caller belongs to, sorted by name.

```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/accounts
```

```json
[
  {
    "account_id": 12,
    "name": "Example Co",
    "defaults": {"email": "", "timezone": "", "recipes": [], "users": []},
    "metrics": {"sites": 2, "users": 1, "domains": 3, "outstanding_invoices": 0},
    "plan_name": "Standard",
    "filtered": true
  }
]
```

### Get account
```
GET /accounts/{account_id}
```

You must belong to the account. Recalculates usage/totals before returning.

**Always present:** `account` (`account_id`, `name`, `plan`, `metrics`, `defaults`), `level` (your tier), `owner` (`true` if `level === 'full-billing'`).

**Tier-gated** (empty / omitted when you lack that perm): `timeline`, `users`, `invites`, `domains`, `sites`, `usage_breakdown`, `invoices` (`full-billing` only).

```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/accounts/12
```

```json
{
  "account": {
    "account_id": 12,
    "name": "Example Co",
    "plan": {
      "name": "Standard",
      "addons": [],
      "charges": [],
      "credits": [],
      "limits": {"storage": 20, "visits": 100000, "sites": 5},
      "interval": "12",
      "billing_user_id": 34
    },
    "metrics": {"sites": 2, "users": 2, "domains": 1, "outstanding_invoices": 0},
    "defaults": {"email": "ops@example.com", "timezone": "America/New_York", "recipes": [], "users": []}
  },
  "level": "full-billing",
  "owner": true,
  "users": [{"user_id": 34, "name": "Jane Doe", "email": "jane@example.com", "level": "full-billing"}],
  "invites": [],
  "sites": [{"site_id": 47, "name": "example.com", "visits": 1200, "storage": 536870912}],
  "domains": [{"domain_id": 9, "name": "example.com"}],
  "invoices": []
}
```

### Create account
```
POST /accounts
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Trimmed account name |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"name":"Example Co"}' \
  https://{your-site}/wp-json/captaincore/v1/accounts
```

**Response:** the new integer `account_id` (not an object). Non-admins are attached at pivot level **`full`**, not owner. Owner is `plan.billing_user_id`, which is not set here.

### Update account
```
PUT /accounts/{account_id}
```

Permission: **owner** (`full-billing`) or admin.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `account.name` | string | Yes | Trimmed name |
| `account.billing_user_id` | integer | Yes | Written to the accounts table column, not `plan.billing_user_id` |

```bash
curl -X PUT -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"account":{"name":"Example Co","billing_user_id":34}}' \
  https://{your-site}/wp-json/captaincore/v1/accounts/12
```

Does **not** transfer ownership. To transfer owner, `PUT .../users/{user_id}/level` with `full-billing`.

### Update account defaults
```
PUT /accounts/{account_id}/defaults
```

Permission: member of the account (not owner-only). Replaces stored defaults JSON.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `defaults.email` | string | No | Default WP admin email for new sites |
| `defaults.timezone` | string | No | Default timezone |
| `defaults.recipes` | array | No | Forced to `[]` if omitted |
| `defaults.users` | array | No | Forced to `[]` if omitted |

```bash
curl -X PUT -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"defaults":{"email":"ops@example.com","timezone":"America/New_York","recipes":[],"users":[]}}' \
  https://{your-site}/wp-json/captaincore/v1/accounts/12/defaults
```

Response: `"Record updated."`

### Invite user to account
```
POST /accounts/{account_id}/invites
```

Permission: member **and** level `full-billing` or `full`.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `invite` | string | Yes | Invitee email |
| `level` | string | No | `full` (default), `sites-only`, or `domains-only`. **Cannot invite as `full-billing`.** |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"invite":"alex@example.com","level":"sites-only"}' \
  https://{your-site}/wp-json/captaincore/v1/accounts/12/invites
```

If that email already has a WP user: they are added immediately and `{ "message": "Account already exists. Access granted and notification sent." }`. If new: invite row + email, `{ "message": "Invite has been sent." }`. Invites expire after **14 days**.

### Remove invite
```
DELETE /accounts/{account_id}/invites/{invite_id}
```

Permission: **`full-billing` only**. Response: `"Invite deleted."`

```bash
curl -X DELETE -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/accounts/12/invites/88
```

### Remove user from account
```
DELETE /accounts/{account_id}/users/{user_id}
```

Permission: **`full-billing` only**. Cannot remove the billing owner — transfer ownership first.

```bash
curl -X DELETE -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/accounts/12/users/56
```

```json
{"success": true}
```

### Update user level
```
PUT /accounts/{account_id}/users/{user_id}/level
```

Permission: **`full-billing` only**. You cannot change your own level, or the current billing owner's level directly.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `level` | string | Yes | `full-billing`, `full`, `sites-only`, `domains-only` |

`level: "full-billing"` **transfers ownership**: writes `plan.billing_user_id`, demotes previous owner pivot to `full`.

```bash
curl -X PUT -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"level":"sites-only"}' \
  https://{your-site}/wp-json/captaincore/v1/accounts/12/users/56/level
```

```json
{"success": true, "level": "sites-only"}
```

---

## Invitations

Account-membership invites (see Site Invitations for sharing a site). Still requires login.

### Verify an invitation
```
GET /invites
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `account` | integer | Yes | Account ID (query param) |
| `token` | string | Yes | Invitation token (query param) |

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/invites?account=12&token=abc123def456"
```

Success: same payload as GET `/accounts/{id}`. Errors: 400 missing params; 404 `"Invite not found or expired."` Invalid if already accepted, or older than 14 days.

### Accept an invitation
```
POST /invites/accept
```

Attaches the **current user** (not the invite email) to the account, sets pivot `level` from the invite (default `full`), marks invite accepted (single-use).

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `account` | integer | Yes | Account ID |
| `token` | string | Yes | Invitation token |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"account":12,"token":"abc123def456"}' \
  https://{your-site}/wp-json/captaincore/v1/invites/accept
```

```json
{"success": true}
```

---

## Billing

All billing methods operate on the **current WP user’s** WooCommerce customer / Stripe customer. They are **not** account-scoped in the URL.

### Get billing info
```
GET /billing
```

```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/billing
```

```json
{
  "valid": true,
  "address": {
    "first_name": "Jane",
    "last_name": "Doe",
    "company": "Example Co",
    "address_1": "123 Main St",
    "address_2": "",
    "city": "Springfield",
    "state": "IL",
    "postcode": "62701",
    "country": "US",
    "email": "jane@example.com",
    "phone": ""
  },
  "subscriptions": [],
  "invoices": [
    {"order_id": 1001, "date": "August 1, 2026", "status": "Pending payment", "total": "99.00"}
  ],
  "payment_methods": [
    {
      "type": "card",
      "method": {"brand": "Visa", "gateway": "stripe", "last4": "4242"},
      "expires": "12/28",
      "is_default": true,
      "token": 15,
      "verified": true
    }
  ]
}
```

`subscriptions` here are raw account rows where you are the billing user — not the same list as `GET /subscriptions`.

### Update billing address
```
PUT /billing/update
```

Send the full `address` object (`first_name`, `last_name`, `company`, `address_1`, `address_2`, `city`, `state`, `postcode`, `country`, `email`, `phone`).

```bash
curl -X PUT -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"address":{"first_name":"Jane","last_name":"Doe","company":"Example Co","address_1":"123 Main St","address_2":"","city":"Springfield","state":"IL","postcode":"62701","country":"US","email":"jane@example.com","phone":""}}' \
  https://{your-site}/wp-json/captaincore/v1/billing/update
```

```json
{"success": true}
```

### Pay invoice
```
POST /billing/pay-invoice
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `value` | integer | Yes | WooCommerce order ID |
| `payment_id` | integer or string | One of `payment_id` / `source_id` | Saved method: WC token id **or** `ach_…` |
| `source_id` | string | One of | New Stripe card source |

The order must belong to an account you are on. Pass `payment_id` — PHP does not auto-pick the default method.

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"value":1001,"payment_id":15}' \
  https://{your-site}/wp-json/captaincore/v1/billing/pay-invoice
```

Card success: `{ "result": "success", "redirect": "https://{your-site}/checkout/order-received/…" }`. Failure: `{ "result": "fail", "message": "…" }`. Unverified ACH: `"ACH payment method has not been verified."`

### Payment methods
```
POST   /billing/payment-methods              # Add card (`source_id`)
PUT    /billing/payment-methods/{id}/primary  # Set as primary
DELETE /billing/payment-methods/{id}          # Remove
```

`{id}` is a WooCommerce token integer or `ach_…`.

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"source_id":"src_xxx"}' \
  https://{your-site}/wp-json/captaincore/v1/billing/payment-methods
```

### ACH bank payments

Customer flow: setup-intent → Stripe.js confirmation → save payment-method → verify micro-deposits. Amounts are **cents**.

```
POST /billing/ach/setup-intent
POST /billing/ach/payment-method
POST /billing/ach/verify
```

**SetupIntent** (body unused):
```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{}' \
  https://{your-site}/wp-json/captaincore/v1/billing/ach/setup-intent
```

```json
{"client_secret": "seti_…_secret_…", "setup_intent_id": "seti_…"}
```

Then complete with Stripe.js (`collectBankAccountForSetup` / `confirmUsBankAccountSetup`).

**Save bank account:** `{ "setup_intent_id": "seti_xxx" }` → `{ "success": true, "token_id": "ach_ab12cd34", "verified": false, "message": "…" }`. New ACH is **not** default.

**Verify micro-deposits:** `{ "token_id": "ach_ab12cd34", "amounts": [32, 45] }` — exactly two integers in cents (e.g. $0.32 and $0.45).

```json
{"success": true, "message": "Bank account verified successfully"}
```

### Plan changes

These **email the operator**. They do not change `plan` JSON.

```
POST /billing/cancel-plan
POST /billing/request-plan-changes
```

**Cancel:** `{ "subscription": { "account_id": 12, "name": "Example Co" } }`. `name` must match a subscription in your `GET /billing` list.

**Request changes:** `{ "subscription": { "name": "Example Co", "plan": { "name": "Plus", "interval": "12" } } }`.

Both return `{ "success": true }`.

---

## Subscriptions

### List subscriptions
```
GET /subscriptions
```

Accounts with a `next_renewal` where you are the billing user.

```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/subscriptions
```

```json
[
  {
    "account_id": 12,
    "name": "Example Co",
    "next_renewal": "2026-09-01",
    "interval": "12",
    "billing_mode": "standard",
    "addons": [],
    "base_price": 99,
    "total": "99.00",
    "billing_user_id": 34,
    "status": "active"
  }
]
```

### Upcoming subscriptions
```
GET /upcoming_subscriptions
```

Next 12 calendar months of `revenue`, `transactions`, `renewals` for accounts you bill. Intervals honored: `1`, `3`, `6`, `12`.

```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/upcoming_subscriptions
```

```json
{
  "revenue": {"Sep 2026": 99},
  "transactions": {"Sep 2026": 1},
  "renewals": {
    "Sep 2026": [
      {"account_id": 12, "name": "Example Co", "interval": "12", "total": 99}
    ]
  }
}
```

---

## Invoices

Invoice **list** is `GET /billing` (and account GET for owners). These routes are per WooCommerce order. Non-admins: the order must belong to an account you are on.

### Get invoice
```
GET /invoices/{invoice_id}
```

```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/invoices/1001
```

```json
{
  "order_id": 1001,
  "created_at": 1754006400,
  "status": "pending",
  "line_items": [
    {"name": "Hosting", "quantity": 1, "description": [], "total": "$99.00"}
  ],
  "payment_method": "Payment via Check",
  "paid_on": "",
  "total": "99.00"
}
```

Refunds appear as extra line items; `total` is reduced.

### Download invoice PDF
```
GET /invoices/{invoice_id}/pdf
```

Raw PDF (`Content-Type: application/pdf`, `Content-Disposition: attachment; filename="invoice-{id}.pdf"`), not JSON.

```bash
curl -u user:pass -o invoice-1001.pdf \
  https://{your-site}/wp-json/captaincore/v1/invoices/1001/pdf
```

---

## Site Requests

Stored in the requester’s user meta. Customers typically create + cancel. Continue/back are the operator wizard.

### List requested sites
```
GET /requested-sites
```

```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/requested-sites
```

### Create a site request
```
POST /site-requests
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `request.name` | string | Yes (UI) | Name or domain |
| `request.account_id` | integer | Yes (UI) | Target account |
| `request.notes` | string | No | |
| `request.created_at` | integer | UI sets | Unix seconds; later matching key |
| `request.step` | integer | UI sets `1` | Wizard step |

Emails operators. Returns the full requested-sites list.

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"request":{"name":"newsite.example","account_id":12,"notes":"Please clone staging","created_at":1754006400,"step":1}}' \
  https://{your-site}/wp-json/captaincore/v1/site-requests
```

### Continue / back / update / delete
```
POST /site-requests/continue
POST /site-requests/back
PUT  /site-requests/update
POST /site-requests/delete
```

`request.created_at` must match an existing row. Continue increments `step`; back decrements. Delete removes by `created_at`. All return the (scoped) requested-sites list.

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"request":{"created_at":1754006400,"name":"newsite.example","account_id":12,"step":1}}' \
  https://{your-site}/wp-json/captaincore/v1/site-requests/delete
```

---

## Reports

Site-scoped reports you can preview, send, and schedule. Non-admins are limited to sites they can access.

### Preview / send / default recipient
```
POST /report/preview
POST /report/send
POST /report/default-recipient
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `site_ids` | array of integers | Yes | Must all be sites you can access |
| `start_date` | string | No | Date range for preview/send |
| `end_date` | string | No | |
| `recipient` | string | send only | Destination email |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"site_ids":[47],"start_date":"2026-07-01","end_date":"2026-07-31"}' \
  https://{your-site}/wp-json/captaincore/v1/report/preview
```

```json
{"html": "<html>…</html>"}
```

Send: `{ "success": true, "message": "Report sent to ops@example.com" }`. Default recipient: `{ "email": "billing@example.com" }`. 403 `unauthorized_site` if any id is outside your fleet.

### Scheduled reports
```
GET    /scheduled-reports
POST   /scheduled-reports
PUT    /scheduled-reports/{id}
DELETE /scheduled-reports/{id}
```

Non-admins only see/update/delete **their own** rows.

**Create:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `site_ids` | array | One of `site_ids` / `account_id` | Your sites only |
| `account_id` | integer | One of | Must be an account you can access |
| `interval` | string | No | Default `monthly`. Also `quarterly`, `yearly` |
| `recipient` | string | Yes | Single email |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"site_ids":[47],"interval":"monthly","recipient":"ops@example.com"}' \
  https://{your-site}/wp-json/captaincore/v1/scheduled-reports
```

```json
{"success": true, "id": 3}
```

Update (own row only): optional `site_ids`, `interval`, `recipient`, `account_id`. Delete: `{ "success": true }`.

---

## Recipes

Reusable scripts (cookbook). You see **your** recipes plus **public** ones. Non-admins always save `public = 0`; only admins may publish. Create/update return the **entire list**, sorted by title.

### List recipes
```
GET /recipes
```

```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/recipes
```

```json
[
  {
    "recipe_id": 18,
    "user_id": 12,
    "title": "Flush object cache",
    "content": "wp cache flush",
    "public": 0
  },
  {
    "recipe_id": 2,
    "user_id": "system",
    "title": "Reset permalinks",
    "content": "wp rewrite flush",
    "public": 1
  }
]
```

Recipes you do not own have `user_id` rewritten to `"system"`.

### Create recipe
```
POST /recipes
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `title` | string | Yes | Name |
| `content` | string | Yes | Script body |
| `public` | 0/1 | No | **Admins only.** Non-admins always get `0`. |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"title":"Flush object cache","content":"wp cache flush"}' \
  https://{your-site}/wp-json/captaincore/v1/recipes
```

**Response:** same array as `GET /recipes` (includes the new row).

### Update recipe
```
PUT /recipes/{recipe_id}
```

Same body as create. You may only update recipes you own. Not owner → `403`. Response: full list.

```bash
curl -X PUT -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"title":"Flush object cache","content":"wp cache flush --skip-plugins"}' \
  https://{your-site}/wp-json/captaincore/v1/recipes/18
```

### Delete recipe
```
DELETE /recipes/{recipe_id}
```

Owner (or admin) only.

```bash
curl -X DELETE -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/recipes/18
```

Success: empty body (HTTP 200).

---

## Scripts

Schedule code on an environment you can access. There is **no list endpoint**. Scheduled rows are attached to `GET /sites/{site_id}/environments` as `scheduled_scripts` (`script_id`, `code`, `run_at` Unix timestamp, `author`, `status`).

`run_at` is always `{ date, time, timezone }`. The server parses `"$date $time"` in that IANA zone, converts to UTC, and stores a Unix timestamp. Do not pre-convert to UTC. A cron runner executes due rows (`status = scheduled`) then sets `status` to `done`.

### Schedule a script
```
POST /scripts/schedule
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `environment_id` | integer | Yes | Target environment |
| `code` | string | Yes | The code to execute |
| `run_at` | object | Yes | `{ "date": "YYYY-MM-DD", "time": "HH:MM", "timezone": "America/New_York" }` |

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{
    "environment_id": 3365,
    "code": "wp cron event run --due-now",
    "run_at": {"date": "2026-08-24", "time": "14:30", "timezone": "America/New_York"}
  }' \
  https://{your-site}/wp-json/captaincore/v1/scripts/schedule
```

**Response:** the new `script_id` (integer), e.g. `42`. No access → `403`.

### Update a script
```
POST /scripts/{script_id}
```

Not PUT. Recalculates `run_at` the same way; replaces `code`. Response: rows affected (typically `1`).

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"code":"wp cron event run --due-now","run_at":{"date":"2026-08-25","time":"09:00","timezone":"America/New_York"}}' \
  https://{your-site}/wp-json/captaincore/v1/scripts/42
```

### Delete a script
```
DELETE /scripts/{script_id}
```

```bash
curl -X DELETE -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/scripts/42
```

**Response:** rows deleted (typically `1`).

---

## Activity logs

```
GET /activity-logs
```

Account-scoped audit trail (home feed, account Activity tab). Non-admins only see rows for their `account_ids`. If you pass `account_id` you do not belong to → `403`.

| Query | Type | Default | Description |
|-------|------|---------|-------------|
| `page` | integer | `1` | Page |
| `per_page` | integer | `50` | Page size |
| `action` | string | | Exact action |
| `entity_type` | string | | e.g. `site`, `domain`, `dns_record`, `account`, `email_forward`, `session` |
| `user_id` | integer | | Actor |
| `account_id` | integer | | Single account (must be yours) |
| `date_from` | string | | Inclusive `YYYY-MM-DD` |
| `date_to` | string | | Inclusive `YYYY-MM-DD` |

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/activity-logs?per_page=20&page=1"
```

```json
{
  "items": [
    {
      "activity_log_id": 901,
      "user_id": 12,
      "account_id": 75,
      "action": "updated",
      "entity_type": "dns_record",
      "entity_id": 60146555,
      "entity_name": "example.com",
      "description": "Updated A record",
      "context": {"type": "A", "name": "www"},
      "ip_address": "203.0.113.10",
      "created_at": 1771427726,
      "user_name": "Ada Lovelace",
      "avatar_url": "https://secure.gravatar.com/avatar/…?s=48&d=identicon"
    }
  ],
  "total": 128,
  "page": 1,
  "pages": 7
}
```

`created_at` is a Unix timestamp. `user_id` `0` → `user_name` `"System"`.

---

## Process logs (timeline notes)

These are **site timeline** notes (markdown description + optional file diffs), not the administrator handbook. Create/update/delete responses are **timelines**: `{ "<site_id>": [ /* process log objects */ ] }` for every affected site — the same shape as `GET /sites/{id}/timeline`.

### Create
```
POST /process-logs
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `site_ids` | array of integers | Yes | Sites to attach. You must have access to all of them. |
| `process_id` | integer | No | Handbook process ID. UI uses `0` for freeform notes. |
| `description` | string | No | Markdown (stored raw) |
| `files` | array | No | File diffs |

Always stored as `public = 1`, `status = completed`, `user_id` = you.

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"site_ids":[135],"process_id":0,"description":"Reset permalinks after launch."}' \
  https://{your-site}/wp-json/captaincore/v1/process-logs
```

### Get one
```
GET /process-logs/{id}
```

Owner always. Non-owners may read if they can access **all associated sites**. Otherwise `403`. Missing → `404`.

```bash
curl -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/process-logs/4401
```

`description` is HTML (Parsedown). `description_raw` is markdown. `created_at` is Unix.

### Update
```
POST /process-logs/{id}
```

Permission callback **requires** `websites` (array of objects with `site_id`). Only the **owner** (or admin) may edit. Description field is **`description_raw`** (not `description`).

```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"process_log_id":4401,"process_id":0,"description_raw":"Reset permalinks **and** flushed cache.","websites":[{"site_id":135}]}' \
  https://{your-site}/wp-json/captaincore/v1/process-logs/4401
```

### Delete
```
DELETE /process-logs/{id}
```

Owner or admin only. Non-owners with site access **cannot** delete.

```bash
curl -X DELETE -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/process-logs/4401
```

### File attachments (`files`)

Optional on create and update. Each item: `path` or `file_path`, `change_type` (default `modified`), `site_id`, `hunks` (`line_start`, `context_before[]`, `removed[]`, `added[]`, `context_after[]`), `lines_added` / `lines_removed`. Update **replaces** the whole file set. Omit `files` on update to leave files unchanged; send `[]` to clear.
