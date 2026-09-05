<h1 align="center">
  <a href="https://captaincore.io"><img src="https://captaincore.io/wp-content/uploads/2018/02/main-web-icons-captain.png" width="70" /></a><br />
CaptainCore Manager

</h1>

[CaptainCore](https://captaincore.io) is an open source WordPress management toolkit for geeky maintenance professionals. This plugin is the control plane behind [Anchor Hosting](https://anchor.host). It's the actual production code that runs the business: site management, backups, DNS, domains, billing, security operations and customer portals for an entire fleet of WordPress sites.

It's published here so other developers can see how a WordPress hosting business actually works under the hood, borrow ideas from it, or run it themselves.

[![emoji-log](https://cdn.rawgit.com/ahmadawais/stuff/ca97874/emoji-log/flat.svg)](https://github.com/ahmadawais/Emoji-Log/)

## How it fits together

CaptainCore is two cooperating halves:

- **CaptainCore Manager** (this repo). A WordPress plugin that holds the fleet's state. It provides custom database tables, a 304-route REST API, a single page interface served at `/account`, and a set of WP-CLI commands for scheduled fleet operations.
- **[CaptainCore CLI](https://github.com/CaptainCore/captaincore)**. A Go binary that connects to managed sites over SSH, collects data with WP-CLI and bash remote scripts, and posts the results back to the Manager's token-authenticated API.

The Manager never touches customer servers directly. It asks the CLI to do that, then stores and presents what comes back. That split keeps the WordPress side stateless about credentials for day-to-day syncs and keeps the heavy lifting on a dedicated server.

## What it manages

- **Sites and environments.** Every site tracks production and staging environments with plugins, themes, users, core version, usage and screenshots synced from the CLI.
- **Quicksaves.** Daily version history of themes, plugins and core per site, with file-level diffs and one-click rollbacks.
- **Backups.** Restic snapshot browsing and restores, plus downloadable full site snapshots.
- **DNS and domains.** A full DNS editor backed by Constellix, zone file import and export, and domain management through registrar integrations.
- **Billing.** Hosting plans, invoices and renewals powered by WooCommerce, with usage-based pricing.
- **Security operations.** Threat tracking, security patches, scan queues, update queues, session anomaly detection and Google Web Risk checks.
- **Customer portals.** Accounts get a scoped view of their own sites, and portals can be white-labeled on a custom domain with their own branding.
- **Provider integrations.** API wrappers for Kinsta, GridPane, Rocket.net, Cloudflare, Constellix, Mailgun, Missive, Fathom Analytics, Envato, Hover.com, Spaceship and ForwardEmail live under `app/Remote/` and `app/Providers/`.

## The interface

The dashboard at `/account` is a hand-maintained single page application in `templates/core/`. No build step, no bundler, no node_modules. Each section is a plain JavaScript file, and the whole interface ships with the plugin. It includes light and dark themes, a command palette and a terminal dock for streaming command output. The original Vue/Vuetify app from the 0.x era still ships as a fallback behind `?ui=legacy`.

## The REST API

Everything the interface does goes through the REST API under `/wp-json/captaincore/v1/`. Authentication uses standard WordPress application passwords over Basic Auth, so the whole platform is scriptable with `curl`. Non-admin users are automatically scoped to the accounts they belong to.

The user-facing API is documented in [api-docs.md](api-docs.md).

## WP-CLI commands

The Manager registers `wp captaincore` commands for scheduled and operational work, typically run from cron on the fleet server:

`dns`, `mailgun`, `remote`, `provider-sync`, `site-label`, `session-alerts`, `scheduled-reports`, `scan-queue`, `component-queue`, `update-queue`, `top-plugins`, `restic-cache`, `security-log-sizes`, `error-log-sizes`, `mu-manifest-generate` and `web-risk-check`.

## Data model

CaptainCore skips WordPress custom post types in favor of its own tables (`wp_captaincore_sites`, `wp_captaincore_environments`, `wp_captaincore_quicksaves` and friends), created and migrated through `CaptainCore\DB::upgrade()` with `dbDelta`. Models in `app/` are thin classes over those tables with a shared query layer, autoloaded via Composer PSR-4.

## Activity logs

CaptainCore automatically tracks user actions across the system for auditing purposes. Administrators can view all activity from the **Activity Logs** page in the sidebar. Account-level activity is also available within each account's **Activity** tab.

### Tracked actions

| Action | Entity Type | Trigger |
|--------|------------|---------|
| `created` | `site` | New site created |
| `updated` | `site` | Site settings updated |
| `deleted` | `site` | Site marked inactive |
| `shared` | `site` | Site shared with an account |
| `unshared` | `site` | Site removed from an account |
| `requested_removal` | `site` | Site removal requested |
| `cancelled_removal` | `site` | Site removal request cancelled |
| `locked` | `domain` | Domain locked |
| `unlocked` | `domain` | Domain unlocked |
| `updated` | `domain` | Domain nameservers changed |
| `transferred` | `domain` | Domain transfer initiated |
| `deleted` | `domain` | Domain deleted |
| `created` | `email_forward` | Email forwarding activated for domain |
| `created` | `dns_record` | DNS record added |
| `updated` | `dns_record` | DNS record modified |
| `deleted` | `dns_record` | DNS record removed |
| `toggled` | `environment` | Environment setting toggled (updates, backups, etc.) |
| `deployed` | `environment` | Deploy to production or staging |
| `created` | `site` | New site provisioned via provider |
| `invited` | `account` | User invited to an account |

Each log entry records the user, IP address, account context, entity type and ID, an entity name snapshot that persists even if the resource is later deleted, a description and a JSON context with details like old and new values. Logs are available over the REST API at `GET /wp-json/captaincore/v1/activity-logs`, with administrators seeing everything and non-admin users scoped to their own accounts.

## Installation

1. Download the latest release from [GitHub Releases](https://github.com/CaptainCore/captaincore-manager/releases) and install it through the WordPress Plugins screen, or upload it to `/wp-content/plugins/`.
2. Activate the plugin. Database tables are created on activation, and future updates arrive through the built-in self-updater, which verifies each package against the sha256 published in [manifest.json](manifest.json).
3. Connect a server running [CaptainCore CLI](https://github.com/CaptainCore/captaincore): install the CLI there and run `captaincore connect --server-url=https://your-cli-server`. The Manager generates its CLI token automatically and hands it over, and the CLI registers its own address for job dispatch, so no wp-config constants are needed. Both values are visible under Settings in the `/account` interface.

## A word of warning

This is opinionated software built to run one specific hosting business. It assumes a companion CLI server, and parts of it lean on third-party services like Constellix, Kinsta and Mailgun. You're welcome to run it, and the code is a good map of what running a WordPress maintenance business actually requires, but expect to read source rather than follow a setup wizard.

Changes are tracked in [changelog.md](changelog.md). Licensed under [MIT](LICENSE.txt).
