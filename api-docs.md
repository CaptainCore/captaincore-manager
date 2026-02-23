# CaptainCore REST API

Base URL: `https://{your-site}/wp-json/captaincore/v1`

## Authentication

CaptainCore uses WordPress application passwords for API authentication. Generate one from your **Profile > API Access** section, then authenticate with HTTP Basic Auth:

```bash
curl -u username:application-password https://{your-site}/wp-json/captaincore/v1/sites
```

Non-admin users are automatically scoped to their own accounts and sites.

---

## Running Commands

The `/run/code` endpoint lets you execute WP-CLI commands on one or more sites. There are two modes depending on how long the command takes.

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

Response:
```json
{"status": "completed", "response": "https://example.com"}
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

Response:
```json
{"status": "queued", "token": "nwaBFBISZEsT..."}
```

**2. Poll for the result:**
```bash
curl -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/my-jobs/{token}
```

While running:
```json
{"status": "started", "token": "nwaBFBISZEsT..."}
```

When finished:
```json
{"status": "completed", "response": "...command output...", "token": "nwaBFBISZEsT..."}
```

The `async` parameter also works with `/sites/cli` and `/sites/bulk-tools`.

---

## Current User

### Get current user
```
GET /me
```

Returns your profile details including name, email, role, and TFA status.

```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/me
```

### Update profile
```
PUT /me/profile
```

| Field | Type | Description |
|-------|------|-------------|
| `first_name` | string | First name |
| `last_name` | string | Last name |
| `email` | string | Email address |
| `new_password` | string | New password (optional) |

### Update pinned environments
```
POST /me/pins
```

### Application password management
```
POST   /me/application-password          # Generate new
POST   /me/application-password/rotate   # Rotate existing
DELETE /me/application-password          # Delete existing
```

### Two-factor authentication
```
GET  /me/tfa_activate     # Begin TFA setup (returns QR URI)
POST /me/tfa_validate     # Verify TFA code to activate
GET  /me/tfa_deactivate   # Disable TFA
```

### Email notifications
```
POST /me/email-subscriber
```

| Field | Type | Description |
|-------|------|-------------|
| `enabled` | boolean | Subscribe/unsubscribe from blog post emails |

### Get API documentation
```
GET /me/api-docs
```

Returns the API documentation content.

---

## Sites

### List all sites
```
GET /sites
```

Returns all sites visible to the authenticated user.

```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/sites
```

### Get a site
```
GET /sites/{site_id}
```

```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/sites/47
```

### Create a site
```
POST /sites
```

### Update a site
```
POST /sites/{site_id}
```

### Delete a site
```
DELETE /sites/{site_id}
```

### Get site details
```
GET /sites/{site_id}/details
```

### Fetch multiple sites
```
POST /sites/fetch
```

| Field | Type | Description |
|-------|------|-------------|
| `post_ids` | array | Array of site IDs |

### Bulk tools
```
POST /sites/bulk-tools
```

Run a bulk tool across multiple environments.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `tool` | string | Yes | Tool to run (`sync-data`, `deploy-defaults`, `activate`, `deactivate`, `apply-https`, `launch`, `scan-errors`, `backup`, `snapshot`) |
| `environments` | array | Yes | Array of environment IDs |
| `params` | object | No | Extra parameters (varies by tool) |
| `async` | boolean | No | If `true`, returns immediately with a job token for polling via `/my-jobs/{token}` |

**Async response:**
```json
{"status": "queued", "token": "abc123..."}
```

Poll for results with `GET /my-jobs/{token}`.

### Bulk update sites
```
PUT /sites/update
```

---

## Site Environments

### Get environments
```
GET /sites/{site_id}/environments
```

### Update environment settings
```
PUT /sites/{site_id}/settings
```

### Monitor settings
```
POST /sites/{site_id}/{environment}/monitor
```

### Captures settings
```
POST /sites/{site_id}/{environment}/captures
```

### Backup settings
```
POST /sites/{site_id}/backup
```

### Sync environment data
```
GET /sites/{site_id}/{environment}/sync/data
```

### New captures
```
GET /sites/{site_id}/{environment}/captures/new
```

### Push environments
```
GET  /sites/{site_id}/environments/{env_id}/push-targets
POST /sites/environments/push
```

| Field | Type | Description |
|-------|------|-------------|
| `source_environment_id` | integer | Source environment ID |
| `target_environment_id` | integer | Target environment ID |

---

## Site Domains

### List domains on a site environment
```
GET /sites/{site_id}/{environment}/domains
```

### Add a domain
```
POST /sites/{site_id}/{environment}/domains
```

### Remove a domain
```
DELETE /sites/{site_id}/{environment}/domains
```

### Set primary domain
```
PUT /sites/{site_id}/{environment}/domains/primary
```

---

## Site Admin Tools

### Magic login
```
GET /sites/{site_id}/{environment}/magiclogin
GET /sites/{site_id}/{environment}/magiclogin/{wp_user_id}
```

### PHPMyAdmin access
```
GET /sites/{site_id}/{environment}/phpmyadmin
```

### List WordPress users
```
GET /sites/{site_id}/users
```

### Site stats (Fathom analytics)
```
GET /sites/{site_id}/stats
```

Returns Fathom analytics for a site including visits, pageviews, bounce rate, and average duration.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `from_at` | string | Yes | Start date (e.g. `2026-01-01`) |
| `to_at` | string | Yes | End date (e.g. `2026-01-31`) |
| `grouping` | string | No | Group results by `day`, `month`, or `year` |
| `environment` | string | No | `Production` or `Staging` |
| `fathom_id` | string | No | Override the Fathom site ID |

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/sites/84/stats?from_at=2026-01-01&to_at=2026-01-31&grouping=day&environment=Production"
```

### Share site stats
```
POST /sites/{site_id}/stats/share
```

### Site timeline
```
GET /sites/{site_id}/timeline
```

### Usage breakdown
```
GET /sites/{site_id}/usage-breakdown
```

### Analytics
```
POST /site/{site_id}/analytics
```

### Fathom analytics
```
PUT /sites/{site_id}/fathom
```

### Site Mailgun settings
```
PUT /sites/{site_id}/mailgun
```

---

## Site Logs

### List logs
```
GET /sites/{site_id}/{environment}/logs
```

### Fetch logs
```
POST /sites/{site_id}/{environment}/logs/fetch
```

### Update logs (per site)
```
GET /sites/{site_id}/update-logs
```

Returns update logs stored on the site record.

### Update logs (with quicksave data)
```
GET /update-logs
```

Returns update logs with quicksave diff support. Requires query parameters:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `site_id` | integer | Yes | Site ID |
| `environment` | string | Yes | `production` or `staging` |

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/update-logs?site_id=135&environment=production"
```

---

## Backups & Snapshots

### List backups
```
GET /site/{site_id}/{environment}/backups
```

### Get a specific backup
```
GET /sites/{site_id}/{environment}/backups/{backup_id}
```

### List snapshots
```
GET /site/{site_id}/snapshots
```

### Get snapshot download link
```
GET /sites/{site_id}/snapshot-link/{snapshot_id}
```

### Download snapshot (public, token-protected)
```
GET /site/{site_id}/snapshots/{snapshot_id}-{token}/{snapshot_name}
```

---

## Site Captures

### List captures
```
GET /site/{site_id}/{environment}/captures
```

---

## Site Invitations

### Preview invite
```
GET /sites/{site_id}/invite-preview
```

### Send invite
```
POST /sites/{site_id}/invite
```

### Grant access
```
POST /sites/{site_id}/grant-access
```

---

## Domains

### List all domains
```
GET /domains
```

```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/domains
```

### Get domain details
```
GET /domain/{domain_id}
```

### Create a domain
```
POST /domains
```

### Delete a domain
```
DELETE /domains/{domain_id}
```

### Update domain account (admin)
```
PUT /domains/{domain_id}/account
```

### Domain registrar controls
```
GET  /domain/{domain_id}/lock_{status}      # Lock/unlock (status: on/off)
GET  /domain/{domain_id}/privacy_{status}    # Privacy on/off
GET  /domain/{domain_id}/auth_code           # Get transfer auth code
POST /domain/{domain_id}/contacts            # Update WHOIS contacts
POST /domain/{domain_id}/nameservers         # Update nameservers
```

### Update site link
```
POST /domain/{domain_id}/update-site-link
```

| Field | Type | Description |
|-------|------|-------------|
| `site_id` | integer | Site ID to link domain to |

---

## DNS

### Get DNS records
```
GET /dns/{domain_id}
```

```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/dns/37
```

### Create a DNS record
```
POST /dns/{domain_id}/records
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | string | Yes | Record type: A, AAAA, CNAME, MX, TXT, etc. |
| `name` | string | Yes | Record name (subdomain or @ for root) |
| `value` | mixed | Yes | Record value (format varies by type) |
| `ttl` | integer | Yes | TTL in seconds |

**Value formats by record type:**

For A, AAAA, ANAME, CNAME, TXT, SPF records — array of value objects:
```json
{ "type": "A", "name": "test", "value": [{"value": "192.0.2.1"}], "ttl": 300 }
```

For MX records — array with server and priority:
```json
{ "type": "MX", "name": "", "value": [{"server": "mail.example.com", "priority": 10}], "ttl": 3600 }
```

**Example:**
```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"type":"A","name":"test","value":[{"value":"192.0.2.1"}],"ttl":300}' \
  https://{your-site}/wp-json/captaincore/v1/dns/37/records
```

### Update a DNS record
```
PUT /dns/{domain_id}/records/{record_id}
```

Same fields as create.

### Delete a DNS record
```
DELETE /dns/{domain_id}/records/{record_id}
```

```bash
curl -X DELETE -u user:pass \
  https://{your-site}/wp-json/captaincore/v1/dns/37/records/60146555
```

### Bulk DNS update
```
POST /dns/{domain_id}/bulk
```

### Get zone file
```
GET /domains/{domain_id}/zone
```

### Import zone
```
POST /domains/import
```

### Activate DNS zone
```
POST /domain/{domain_id}/activate-dns-zone
```

### Delete DNS zone
```
DELETE /domain/{domain_id}/dns-zone
```

---

## Email Forwarding

### Activate email forwarding
```
POST /domain/{domain_id}/activate-forward-email
```

### List email forwards
```
GET /domain/{domain_id}/email-forwards
```

### Create email forward
```
POST /domain/{domain_id}/email-forwards
```

### Update email forward
```
PUT /domain/{domain_id}/email-forwards/{alias_id}
```

### Delete email forward
```
DELETE /domain/{domain_id}/email-forwards/{alias_id}
```

### Email forwarding status
```
GET /domain/{domain_id}/email-forwarding/status
```

### Email forwarding logs
```
GET /domain/{domain_id}/email-forwarding/logs
```

### Remove email forwarding (admin)
```
DELETE /domain/{domain_id}/email-forwarding
```

---

## Mailgun

### Get Mailgun details
```
GET /domain/{domain_id}/mailgun
```

### Setup Mailgun
```
POST /domain/{domain_id}/mailgun/setup
```

### Verify Mailgun DNS
```
POST /domain/{domain_id}/mailgun/verify
```

### Deploy Mailgun
```
POST /domain/{domain_id}/mailgun/deploy
```

### Delete Mailgun
```
DELETE /domain/{domain_id}/mailgun
```

### Mailgun events
```
GET /domain/{domain_id}/mailgun/events
GET /sites/{site_id}/mailgun-events
```

### Mailgun suppressions
```
GET    /domain/{domain_id}/mailgun/suppressions/{type}
DELETE /domain/{domain_id}/mailgun/suppressions/{type}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `address` | string | Yes | Email address to remove from suppression list |

---

## Accounts

### List accounts
```
GET /accounts
```

### Get account
```
GET /accounts/{account_id}
```

### Create account
```
POST /accounts
```

### Update account
```
PUT /accounts/{account_id}
```

### Delete account (admin)
```
DELETE /accounts/{account_id}
```

### Update account defaults
```
PUT /accounts/{account_id}/defaults
```

### Update plan (admin)
```
PUT /accounts/{account_id}/plan
```

### Invite user to account
```
POST /accounts/{account_id}/invites
```

### Remove invite
```
DELETE /accounts/{account_id}/invites/{invite_id}
```

### Remove user from account
```
DELETE /accounts/{account_id}/users/{user_id}
```

### Update user level
```
PUT /accounts/{account_id}/users/{user_id}/level
```

---

## Users (Admin)

### List users
```
GET /users
```

### Create user (admin)
```
POST /users
```

### Get user (admin)
```
GET /users/{user_id}
```

### Update user (admin)
```
PUT /users/{user_id}
```

### Get user's accounts
```
GET /users/{user_id}/accounts
```

---

## Invitations

### Verify an invitation
```
GET /invites
```

Looks up a specific invitation by account and token. Returns the account details if the invite is valid.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `account` | integer | Yes | Account ID (query param) |
| `token` | string | Yes | Invitation token (query param) |

```bash
curl -u user:pass "https://{your-site}/wp-json/captaincore/v1/invites?account=75&token=abc123"
```

### Accept an invitation
```
POST /invites/accept
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `account` | integer | Yes | Account ID |
| `token` | string | Yes | Invitation token |

---

## Quicksaves

All quicksave endpoints require `site_id` and `environment` as query parameters.

### List quicksaves
```
GET /quicksaves
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `site_id` | integer | Yes | Site ID |
| `environment` | string | Yes | `production` or `staging` |

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/quicksaves?site_id=2456&environment=production"
```

### Search quicksaves
```
GET /quicksaves/search
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `site_id` | integer | Yes | Site ID |
| `environment` | string | Yes | `production` or `staging` |

### Get a quicksave
```
GET /quicksaves/{hash}
```

Returns detailed quicksave data including plugin/theme versions and what changed.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `site_id` | integer | Yes | Site ID (query param) |
| `environment` | string | Yes | `production` or `staging` (query param) |

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/quicksaves/446d35f6...?site_id=2456&environment=production"
```

### Get changed files
```
GET /quicksaves/{hash}/changed
```

Returns a newline-delimited list of changed files with modification type (M=modified, A=added, D=deleted).

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `site_id` | integer | Yes | Site ID (query param) |
| `environment` | string | Yes | `production` or `staging` (query param) |

### Get file diff
```
GET /quicksaves/{hash}/filediff
```

Returns a git-style unified diff for a specific file.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `site_id` | integer | Yes | Site ID (query param) |
| `environment` | string | Yes | `production` or `staging` (query param) |
| `file` | string | Yes | File path to diff (query param) |

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/quicksaves/446d35f6.../filediff?site_id=2456&environment=production&file=plugins/share-one-drive/includes/UserFolders.php"
```

### Rollback a quicksave
```
POST /quicksaves/{hash}/rollback
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `site_id` | integer | Yes | Site ID |
| `environment` | string | Yes | `production` or `staging` |

---

## Update Logs

### List update logs
```
GET /update-logs
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `site_id` | integer | Yes | Site ID |
| `environment` | string | Yes | `production` or `staging` |

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/update-logs?site_id=135&environment=production"
```

### Get update log diff
```
GET /update-logs/{hash_before}_{hash_after}
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `site_id` | integer | Yes | Site ID (query param) |
| `environment` | string | Yes | `production` or `staging` (query param) |

```bash
curl -u user:pass \
  "https://{your-site}/wp-json/captaincore/v1/update-logs/abc123_def456?site_id=135&environment=production"
```

---

## Activity & Process Logs

### Activity logs
```
GET /activity-logs
```

### Process logs
```
GET    /process-logs          # List all (admin)
POST   /process-logs          # Create
GET    /process-logs/{id}     # Get one
POST   /process-logs/{id}     # Update
DELETE /process-logs/{id}     # Delete
```

---

## Recipes

### List recipes
```
GET /recipes
```

### Create recipe
```
POST /recipes
```

### Update recipe
```
PUT /recipes/{recipe_id}
```

### Delete recipe
```
DELETE /recipes/{recipe_id}
```

---

## Site CLI Commands

### Run a CLI command
```
POST /sites/cli
```

Executes a built-in command on a site.

**API requests** (application passwords) execute synchronously and return the result directly. **UI requests** (nonce-based) return a job token for WebSocket streaming.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `post_id` | integer or array | Yes | Site ID (or array of site IDs for bulk) |
| `command` | string | Yes | Command to run (see list below) |
| `environment` | string | No | `Production` or `Staging` |
| `value` | string | No | Additional value (varies by command) |
| `async` | boolean | No | If `true`, returns immediately with a job token for polling via `/my-jobs/{token}` |

**Available commands:** `reset-permissions`, `apply-https`, `apply-https-with-www`, `production-to-staging`, `staging-to-production`, `launch`, `scan-errors`, and more.

**Example (synchronous):**
```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"post_id":135,"command":"reset-permissions","environment":"Production"}' \
  https://{your-site}/wp-json/captaincore/v1/sites/cli
```

**Synchronous response:**
```json
{"status": "completed", "response": "...command output..."}
```

**Example (async):**
```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"post_id":135,"command":"backup","environment":"Production","async":true}' \
  https://{your-site}/wp-json/captaincore/v1/sites/cli
```

**Async response:**
```json
{"status": "queued", "token": "abc123..."}
```

Poll for results with `GET /my-jobs/{token}`.

---

## Jobs

### Get job status (admin)
```
GET /jobs/{job_id}
```

Check the status of an async job by task ID. Admin only.

```bash
curl -u admin:pass https://{your-site}/wp-json/captaincore/v1/jobs/K9DmZxAuei...
```

**Response (in progress):**
```json
{"status": "queued", "job_id": "K9DmZxAuei..."}
```

**Response (completed):**
```json
{"status": "completed", "response": "...output...", "job_id": "K9DmZxAuei..."}
```

### Get my job status
```
GET /my-jobs/{token}
```

Check the status of a job by token. Scoped to the current user's jobs.

```bash
curl -u user:pass https://{your-site}/wp-json/captaincore/v1/my-jobs/2q8ZWFF88x...
```

**Response (in progress):**
```json
{"status": "queued", "token": "2q8ZWFF88x..."}
```

**Response (completed):**
```json
{"status": "completed", "response": "...output...", "token": "2q8ZWFF88x..."}
```

---

## Scripts

### Run code
```
POST /run/code
```

Runs a script (recipe or custom code) on one or more environments.

**API requests** (application passwords) execute synchronously and return the result directly. **UI requests** (nonce-based) return a job token for WebSocket streaming.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `code` | string | Yes | The script/code to execute |
| `environments` | array | Yes | Target environments (see formats below) |
| `async` | boolean | No | If `true`, returns immediately with a job token for polling via `/my-jobs/{token}` |

**Environment formats:**

Array of environment IDs:
```json
{ "code": "wp option get home", "environments": [3365, 3358] }
```

Array of objects with `site_id` and `environment`:
```json
{ "code": "wp option get home", "environments": [{"site_id": 135, "environment": "production"}] }
```

**Example (synchronous):**
```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"code":"wp option get home","environments":[{"site_id":135,"environment":"production"}]}' \
  https://{your-site}/wp-json/captaincore/v1/run/code
```

**Synchronous response:**
```json
{"status": "completed", "response": "https://example.com\n"}
```

**Example (async):**
```bash
curl -X POST -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"code":"sleep 15 && wp plugin list","environments":[{"site_id":135,"environment":"production"}],"async":true}' \
  https://{your-site}/wp-json/captaincore/v1/run/code
```

**Async response:**
```json
{"status": "queued", "token": "abc123..."}
```

Poll for results with `GET /my-jobs/{token}`.

### Schedule a script
```
POST /scripts/schedule
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `environment_id` | integer | Yes | Target environment ID |
| `code` | string | Yes | The code to execute |
| `run_at` | object | Yes | Schedule with `date`, `time`, and `timezone` fields |

### Update a script
```
POST /scripts/{script_id}
```

### Delete a script
```
DELETE /scripts/{script_id}
```

---

## Providers

### List providers
```
GET /providers
```

### Create provider
```
POST /providers
```

### Update provider
```
PUT /providers/{provider_id}
```

### Delete provider
```
DELETE /providers/{provider_id}
```

### Verify provider
```
GET /providers/{provider_id}/verify
```

### Provider themes/plugins
```
GET /providers/{provider}/themes
GET /providers/{provider}/plugins
GET /providers/{provider}/theme/{id}/download
GET /providers/{provider}/plugin/{id}/download
```

### Remote site management
```
GET  /providers/{provider_id}/remote-sites
POST /providers/{provider_id}/connect
POST /providers/{provider_id}/import
POST /providers/{provider}/new-site
POST /providers/{provider}/deploy-to-staging
POST /providers/{provider}/deploy-to-production
```

---

## Billing

### Get billing info
```
GET /billing
```

### Pay invoice
```
POST /billing/pay-invoice
```

### Payment methods
```
POST   /billing/payment-methods              # Add
PUT    /billing/payment-methods/{id}/primary  # Set as primary
DELETE /billing/payment-methods/{id}          # Remove
```

### ACH bank payments
```
POST /billing/ach/setup-intent       # Create setup intent
POST /billing/ach/payment-method     # Add bank account
POST /billing/ach/verify             # Verify micro-deposits
GET  /billing/ach/pending            # Pending verifications (admin)
POST /billing/ach/admin-verify       # Admin verify (admin)
```

### Plan changes
```
POST /billing/cancel-plan
POST /billing/request-plan-changes
PUT  /billing/update
```

---

## Subscriptions

### List subscriptions
```
GET /subscriptions
```

### Get subscription details
```
GET /subscriptions/{subscription_id}
```

### Upcoming subscriptions
```
GET /upcoming_subscriptions
```

---

## Invoices

### Get invoice
```
GET /invoices/{invoice_id}
```

### Download invoice PDF
```
GET /invoices/{invoice_id}/pdf
```

---

## Filters

### Filter sites
```
POST /filters/sites
POST /filters
```

### Get filter versions/statuses
```
GET /filters/{name}/versions
GET /filters/{name}/statuses
```

---

## Configurations (Admin)

### Get configurations
```
GET /configurations
```

### Update configurations
```
POST /configurations
PUT  /configurations/global
```

### Defaults
```
GET /defaults
PUT /defaults/global
```

---

## Archives

### Get archive
```
GET /archive
```

### Share archive
```
POST /archive/share
```

---

## Security Monitoring (Admin)

### Web risk logs
```
GET /web-risk-logs
```

### Checksum failures
```
GET /checksum-failures
```

---

## Reports (Admin)

### Send report
```
POST /report/send
```

### Preview report
```
POST /report/preview
```

### Default recipient
```
POST /report/default-recipient
```

### Scheduled reports
```
GET    /scheduled-reports
POST   /scheduled-reports
PUT    /scheduled-reports/{id}
DELETE /scheduled-reports/{id}
```

---

## Other

### WordPress plugins/themes
```
GET /wp-plugins
GET /wp-themes
```

### Running processes
```
GET /running               # Running background jobs (admin)
GET /processes             # List all processes
GET /processes/{id}        # Get a process
PUT /processes/{id}        # Update a process
GET /processes/{id}/raw    # Get raw process data
```

### Environments list
```
GET /environments
```

### Site requests
```
POST /site-requests
POST /site-requests/back
POST /site-requests/continue
PUT  /site-requests/update
POST /site-requests/delete
GET  /requested-sites
```

---

## SSH Keys

### List SSH keys
```
GET /keys
```

### Create SSH key
```
POST /keys
```

### Update SSH key
```
PUT /keys/{key_id}
```

### Delete SSH key
```
DELETE /keys/{key_id}
```

### Set primary SSH key
```
PUT /keys/{key_id}/primary
```

---

## Provider Actions (Admin)

### List provider actions
```
GET /provider-actions
```

### Run a provider action
```
GET /provider-actions/{id}/run
```

### Check provider actions
```
GET /provider-actions/check
```
