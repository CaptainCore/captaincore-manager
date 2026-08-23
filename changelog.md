# Changelog

## **v1.0.0** - Unreleased

The interface release. CaptainCore Manager reaches 1.0 with a rebuilt `/account` experience: a fast, hand-maintained single-page interface (`templates/core/`) that replaces the original Vue dashboard as the default, while the legacy app remains one switch away. This release also puts the project on a proper release cycle with GitHub Releases, a signed update manifest, and a self-updater.

### Added

- New core interface: a rebuilt fleet dashboard served at `/account`, wearing the Minn Admin design system with light and dark themes, a command palette, and a working terminal dock.
- Self-updater: the plugin now checks a release manifest on GitHub and offers updates through the WordPress Plugins screen, verifying each download against the sha256 published in the manifest before install.
- Release tooling: `bin/build-zip.sh` builds the distributable zip with dev files excluded and prints the sha256 for the manifest stamp.
### Improved

### Fixed

## **v0.18.0** - May 6, 2024

### Added

- API wrappers for `CaptainCore\Remote\Constellix`, `CaptainCore\Remote\Kinsta`, `CaptainCore\Remote\Mailgun` and `CaptainCore\Remote\Missive`
- MyKinsta shared access
- Automatically track script actions in site timeline
- DNS import and export with support for `.zone` format
- Update logs powered by quicksaves
- Granular level `show changes` within quicksaves and update logs

### Improved

- Improvements to quicksave UI
- Improvements to rollback options
- Improvements to site timeline
- Improvements when adding new DNS records
- Upgraded Constellix API from v1 to v4
- Removed offical Mailgun PHP client
- Removed unused legacy code for CPTs and custom fields
- Removed legacy update logs
- Upgraded Vuetify to v2.7.1
- Upgraded Vue.js to v2.7.15

## **v0.17.0** - Feb 28, 2023

### Added

- Experimental integration with MyKinsta
- TFA support with one time passwords. Built using OTPHP library and Kjua.js.
- Envato integration, install paid themes and plugins
- Nameserver support with Hover.com
- Missive API
- Site shared section
- Quicksave search
- Tables `captaincore_providers` and `captaincore_provider_actions`
- REST endpoints for providers
- CaptainCore\Run::CLI
- CaptainCore\Providers\Hoverdotcom::credentials("username") to replace HOVERCOM_USERNAME
- CaptainCore\Providers\Fathom::credentials to replace FATHOM_API_KEY
- Endpoint for MagicLogin `/wp-json/captaincore/v1/sites/:id/:environment/magiclogin`

### Improved

- Fathom Analytics API improvements
- Logged in improvements
- Cleaned up navigation
- Standardized dialog transitions
- Upgraded Vuetify to v2.6.4
- Upgraded Vue.js to v2.6.14
- Upgraded Material Design Icons to v6.5.95
- Removed legacy CPT classes

### Fixed

- DNS save button layout
- PHP 8 compatibility issues

## **v0.16.0** - Nov 20, 2021

### Added

- Stats powered by Fathom Analytics API
- Stats sharing functionality via Fathom Analytics API
- Stat timeframes hourly, daily, monthly and yearly
- Stat date selectors
- Intercom integration
- Quicksave individual rollback options for this and previous version.
- Quicksave info overviews to see how items were changes.
- Add user dialog
- Automatically switch billing plans
- Edit domain accounts
- Magic WordPress login
- Failed customer renewal email
- Billing features track overpayments, refunds, charges and credits to invoices
- Subscription and upcoming renewal pages for admins.

### Improved

- Upgraded Vuetify to v2.5.14
- Upgraded Frappe Charts to v1.6.1
- Expanded invoices to include credits, charges and refunds.
- Expanded adding removing domain DNS zones to regular users.
- Improved script and deployment prompts with better indication which environment will be affected.
- Improved listing of nameservers
- Filter version by OR operator
- Move package HTML2PDF to [CaptainCore Supporting Files](https://github.com/CaptainCore/captaincore-supporting-files) plugin

### Fixed

- Compatibility with CaptainCore v0.12.0
- DNS response logic after making edits
- Magic login responses
- Stripe credit cards on file to be properly associated with customer

## **v0.15.0** - Jan 9, 2021

### Added

- Billing interface powered by WooCommerce and CaptainCore account plans.
- Download PDFs for invoices.
- Site and domain autocomplete selections.
- Running processes.
- Direct deep links for `/account/sites/new`, `/account/sites/<site-id>`, `/account/accounts/<account-id>` and `/account/dns/<domain-id>/`.
- CaptainCore cron for handling background processes like billing renewals.

### Improved

- Upgraded Vuetify to v2.4.2
- Improved toggle site defaults to use global configurations.
- Improved UI consistency for search fields.
- Improved sites listing image overlay.
- Expanded account invites.
- Replaced `/` to search functionality with autofocus text fields.
- Removed Lodash which was previously used to speed up searches. This is no longer needed.
- Improvements when adding SRV records with DNS editor

## **v0.14.0** - Oct 28, 2020

### Added

- Restic backup UI
- Ability to assign customer and billing roles per site
- Configurations for usage pricing

### Improved

- Upgraded Vue.js to 2.6.12
- Upgraded Vuetify to v2.3.14
- Revamped backup section.
- Overhauled site filters. Moved heavy Javascript to PHP for better performance.
- Performance improvements by conditionally loading routes and reducing site listing data.
- Disable many slider transition effects between screens for better performance.
- Moved admin icon for adding log entry within site for better visibility.
- Moved plan tab from site to account section.
- Expanded token support per environment.
- Reduce reliance on ACF.
- Removed legacy custom page templates.
- Expanded quicksave endpoint for per environment.

### Fixed

- Kinsta database url for new format.
- Locked down users api endpoint.

## **v0.13.0** - September 5, 2020

### Added

- Health section for displaying errors collected by the new scan-errors cli command.
- Sync scan errors

### Improved

- Site UI improvements to adding and editing sites.
- Ability to add shell environment variables per site which get loaded on each ssh request.
- Removed need to enter database info. This is auto populated when syncing site data.
- Login page will now login by pressing enter on the keyboard.
- Improved filter and bulk selections

### Fixed

- Fix bug with DNS layout

## **v0.12.0** - June 16, 2020

### Added

- Thumbnails based on screenshot captures
- List sites as thumbnails
- Console for advanced options
- Shell environmental variables
- Task activity section
- Export task results to json
- Account method `calculate_usage`
- Select sites that are currently filtered for bulk actions

### Improved

- Upgraded Vuetify to v2.3.0
- Consistency improvements to interface. Domains section now behaves similar to the sites section. Expand content and removed unnecessary borders.
- Moved bulk tools, task activity and filters to console

### Fixed

- Fix site storage bug

## **v0.11.0** - May 2, 2020

### Added

- Global site defaults
- Configurations
- Account sync
- Account create dialog
- Support for Constellix vanity nameservers. Added wp-config constants CAPTAINCORE_CONSTELLIX_VANITY_ID and CAPTAINCORE_CONSTELLIX_SOA_NAME
- Run commands in background
- Mailgun dialog
- Timeline export to json
- DNS records import and export
- DB method `where_compare`

### Improved

- Upgraded Vuetify to v2.2.26
- Upgraded Material Design Icons to v4
- Expanded classes Account and Site to support syncing with CLI.
- Increased default TTL from 1800 to 3600
- Removed unnecessary code
- Improvements to DB method `where`

### Fixed

- Fix edit site sync button

## **v0.10.1** - February 18, 2020

### Added

- Account levels
- Common script reset permissions
- Create account dialog

### Improved

- Upgraded Vuetify to v2.2.12
- Upgraded QS.js to v6.9.1
- Moved account related sections: timelines and defaults from sites section to new accounts section.
- Delegate snapshot link generate to CLI
- Compatibility fixes for new database architecture

### Fixed

- Fix timezone issue with update logs
- Fix mobile layout with script section
- Fix bugs with editing/saving process logs
- Fix `/` in process logs

## **v0.10.0** - January 29, 2020

### Added

- Database architecture. Migrated CaptainCore custom post types to custom tables.
- Routing method using pushState js and WordPress catch all `/account` custom template.
- Mailgun PHP client v3.0.0 added via Composer
- Mailgun pagination event logs
- [ARVE Pro](https://nextgenthemes.com/) plugin support
- Users management page
- Administrator switch to link
- Persistent completed job count

### Improved

- Upgraded Vue.js to 2.6.11
- Upgraded Vuetify to v2.2.5
- Consistent labeling of headers in format of "Listing <number> <items>"
- Significantly reduce amount of data for the site listing page
- Sort update logs by date created
- Sort timeline log by account name
- Timeline logs now include activity from all sites, not just active sites
- Site filters now generated by custom SQL and PHP rather then custom JS
- Removed browser spell check from textarea for scripts
- Remember logins by default
- Deleting sites now properly moves back to the list view
- Firefox bug workaround fix for scrolling within 'flex-direction: column-reverse' used in console output. [Details here](https://stackoverflow.com/questions/34249501/flexbox-column-reverse-in-firefox-edge-and-ie).

## **v0.9.0** - November 11, 2019

### Added

- Button to clear job activity
- Searchable site users

### Improved

- Upgraded Vuetify to v2.1.9
- Improvements to site listing
- Improvements to running jobs status
- Improvements to migrate dialog
- Improvements to bulk site selection
- Renamed column in site listing from "Multisite" to "Subsites"
- Site users are sorted by roles then login
- Lazy load screenshot thumbnails
- Improvements to file diff color highlight (Thanks @dustinleer)

## **v0.8.0** - October 21, 2019

### Added

- Composer psr-4 autoloading
- Historical captures feature

### Improved

- Upgraded Vuetify to v2.1.2
- Upgraded license from GPL to MIT license
- Broke up single file of CaptainCore classes into proper psr-4 loaded classes.
- Move database upgrade function to CaptainCore\DB::upgrade()
- Replaced PHP `array()` with shorthand `[]`.
- Structure of CaptainCore\Site changed to match other class. Site ID is now assigned within the __construct function.
- Improvements configure default sections. Sort accounts by name, administrators now see all accounts and accounts are now searchable.
- Improvements when adding sites.

## **v0.7.0** - September 27, 2019

### Added

- Site filters for administrators: healthy only, outdated only, with assigned plan, without assigned plan and reset.
- Account profile section with Gravatar thumbnail
- Sharing section for administrators
- SSH key management
- Last sync time ago. Site which haven't been synced within the last 48 hours will display a label for administrators.
- Filters for administrators to toggle between healthy and outdated sites. Sites which haven't received sync with CaptainCore in over 48 hours are considered unhealthy.
- CaptainCore\Accounts() class for managing accounts. Replaces legacy CaptainCore\Customers
- CaptainCore\upgrade() function which replaces legacy captaincore_create_tables() function

### Improved

- Upgraded Vuetify to v2.0.19
- Improvements to DNS section. Ability to add and remove domains.
- Improvements to JS includes. Moved JS code from header to footer and consolidated CDN usage to jsDelivr for better performance.
- Revamped site credentials UI. Built in password hidden with one click copy site details.
- Removed Font Awesome. Replaced with Material Design Icons
- Removed bottom footer
- Removed jQuery usage
- Mobile fixes

## **v0.6.0** - September 3, 2019

### Added

- Renamed plugin from CaptainCore GUI to CaptainCore
- Decoupled CaptainCore from WooCommerce. Now runs within standalone PHP template for better compatibility.
- Snapshot management section. Links to generated snapshots now automatically expire after 24 hours. Links can be regenerated whenever.
- Delete default user
- Default recipes

### Improved

- Upgraded Vue.js to 2.6.10
- Upgraded Vuetify to v2.0.4
- Combined Theme and Plugin tabs into new Addons tab.
- Improvements to multisite column
- Improvements to quicksave management
- Improvements to stats tab
- Improvements to site search performance
- Improvements to DNS editor
- Removed default plugins. New replacement is to configure default recipes instead.

### Fixed

- Fix site stats rounding bug with bounce rate

## **v0.5.0** - July 24, 2019

### Added

- Routing based on hash. Will now toggle between `/sites`, `/sites#dns`, `/sites#cookbook` and `/sites#handbook` without page reloading.
- Custom recipes which can be public or private to the author. Public recipes can run. Private recipes can be loaded and changed before running.
- DNS manager completed rewritten in Vue.js. Replaces old jQuery DNS manager.
- Options for DNS introduction and DNS nameservers which are displayed in an info alert at top of DNS page.
- Option for managing timezone per account.
- Custom link `/my-account/sites#cookbook` added to WooCommerce endpoint for Cookbook.
- Configure default section for managing WordPress default settings per account. Settings include admin email, timezone, plugins and users. Replaces legacy WooCommerce endpoint for configs.
- Timeline logs sections. Replaces legacy WooCommerce endpoint for logs.

### Improved

- Replaced custom WooCommerce endpoints `/my-account/dns/` and `/my-account/handbook/` with new single page `/my-account/sites#dns` and `/my-account/sites#handbook`.
- Improved managing user-defined recipes.
- Improved theme/plugin upload layout.
- License deployments now handled by custom defined recipes. Replaces legacy WooCommerce endpoint for Licenses.
- Many minor improvements to sites page. That includes clearable search, highlight button when toggled on/off and simplified top level buttons. Running Jobs, Bulk Management and Advanced Filters buttons are now called Job Activity, Bulk Tools and Filters. They have been reduced to icons with tooltips.
- Custom links added to WooCommerce my-account menu now operate in single page mode. Selecting Sites, DNS, Cookbook or Handbook will not reload the page.

## **v0.4.5** - June 29, 2019

### Added

- Admin dashboard with SVG menu icon. Replaces old admin pages. Includes link to legacy custom post types.
- Stats tab per environment. Fetches stats from Fathom Analytics.
- Dialog to reassign user content when deleting user.
- Dialog to run launch site script.

### Improved

- Improved site validations and deletions

### Fixed

- Fixed links to Kinsta's database url and staging url
- Fixed assigning existing customers to new site

## **v0.4.4** - June 4, 2019

### Added

- Bulk scripts section
- Bulk log entry support
- Run custom code section
- Migrate script dialog

### Improved

- Added date field to edit process entry log.
- Site sorting icons.
- Improved file upload error handling. If /wp-content/deploy/ directory not exists, create it. If upload failed then report it.
- Improved theme & plugin uploads.
- Improved bulk actions environment support
- Improved bulk management section. Moved toggle commands here.

## **v0.4.3** - May 12, 2019

### Added

- Sites UI - Realtime websockets which replaces the `jobRetry` polling method. CLI commands are now run from a websocket and output streamed in realtime.
- Sites UI - Log history dialog to handbook section
- Sites UI - Cookbook section

### Improved

- Sites UI - Handbook section improved with new dialogs for viewing and editing. Added logging generic entry not tied to a website.
- Sites UI - Overhauled main layout and greatly improved the advanced filter interface.
- Consistent dialog stylings
- Improved autocomplete UX based [example from John Leider](https://codepen.io/johnjleider/pen/MQRjme?&editors=101)
- Compatibility fix for [ARVE](https://wordpress.org/plugins/advanced-responsive-video-embedder/) video embeds.
- Compatibility fix when adding/updating process log entries. Force relationship fields to save in serialized format.

## **v0.4.2** - April 22, 2019

### Added

- Sites UI - Timeline tab. Administrators can add new log entrys per site.
- Sites UI - Handbook section for administrators.
- Sites UI - Screenshot thumbnails of websites are automatically added/updated when synced.

### Improved

- Sites UI - Performance improvement when working with sites. Now only 1 site panel will be open at a time.
- Replaced markdown support from using Jetpack plugin to [Parsedown](https://parsedown.org/)
- Upgrade compatibility for CaptainCore helper v0.2.0

## **v0.4.1** - April 4, 2019

### Added

- Sites UI - Site plan tab for managing hosting plans. Administrators can assign plans per customer which define storage, visits and number of sites usage.
- Sites UI - Added button for removing a site.
- Sites UI - Added icon to manual sync a site.

### Improved

- Include ACF field groups via PHP.
- Removed WooCommerce tab for site health.
- Display DNS tab if CONSTELLIX_API_KEY and CONSTELLIX_SECRET_KEY defined.
- Display domains on overview page if DNS is defined.
- Sites UI - Improve snapshot dialog
- Sites UI - Improve font used for code diff
- Sites UI - Track quicksave progress
- Sites UI - Specify provider instead of extracting from the address.
- Sites UI - Snapshot options

### Fixed

- Deactivate command passed to CLI

## **v0.4.0** - March 4, 2019

### Added

- Sites UI - Environment support added (themes, plugins, users, updates, scripts, backups and quicksaves)
- Sites UI - Label for multisite networks
- Support for environments
- CLI support for Fathom code
- DB method `all`

### Improved

- Sites UI - Improvements to site edit dialog and file diff dialog
- Removed legacy subsites support. Subsite should not be added as a seperate site.
- Upgraded Vuetify to v1.5.4

### Fixed

- Sites UI - Staging links.
- Sites UI - Manage commands to Dispatch server.

## **v0.3.3** - February 9, 2019

### Added

- Sites UI - Fathom tracker

### Improved

- Sites UI - Moved many of the commands like 'Mailgun', 'Site copy' into the background with proper status reporting.
- Sites UI - Improve response when and added loading status when listing quicksave changes.
- Sites UI - Added feedback to production/staging deployments and adding new sites.

### Fixed

- Sites UI - Feedback when applying HTTPS urls and creating Snapshots
- Sites UI - SSH staging port
- Various PHP errors and warnings.
- Company Handbook - restore bullets

## **v0.3.2** - December 31, 2018

### Added

- Integrated CaptainCore Dispatch

### Improved

- Refactor fetch functions `captaincore_fetch_customer`, `captaincore_fetch_domains` and `captaincore_fetch_sites` to new classes `CaptainCore/Customers`, `CaptainCore/Domains`and `CaptainCore/Sites`
- Sites UI - Refactor code into CaptainCore Site class
- Sites UI - Load customers and sites with new Rest APIs `captaincore/v1/customers/` and `captaincore/v1/sites/`
- Sites UI - Load WP-API nonce before Vue.js mounted lifecycle
- Sites UI - Handle errors with jobs. Prompt notice when login failed
- Upgraded Vuetify to v1.3.11
- Removed phpseclib

## **v0.3.1** - December 3, 2018

### Added

- Sites UI - Quick logins

### Improved

- Company Handbook - Allow administrators with multiple roles
- Sites UI - Mask database passwords
- Sites UI - Improve display of roles on user listing
- Removed custom post type captcore_server since provider can be inferred directly based off address.
- Support for Kinsta .cloud tld

## **v0.3.0** - October 14, 2018

### Improved

- Improved feedback from 'usage-update' request.
- Password reset bug fix on '/my-account/edit-account/' page.

## **v0.2.9** - September 26, 2018

### Added

- Site class method 'update'
- Sites UI - Edit site dialog
- Sites UI - Quicksave check dialog
- Sites UI - Quicksave compare and highlight changes.

### Improved

- Upgraded Vue.js to 2.5.17.
- Permission bug fixes for quicksaves.
- Housecleaning - Moved files from /inc/ to /includes/.
- Sites UI - Email notify after site deployments

### Fixed

- Fixed bug preventing sites from resetting when cleared.

## **v0.2.8** - August 20, 2018

### Added

- Overview stats after logged in: "You have access to *** WordPress Sites and DNS for *** domains."
- Manage DNS - List all domains.
- Combined interface for listing/managing sites - Combined old site listing and advanced options with new Vue.js interface.
- Sites UI - Advanced tab (first draft)

### Improved

- Sites UI - Major improvements for site management. Preparation for adding staging to manage ui, see concept video: https://vimeo.com/284488960/10872cca8e.
- Sites UI - Merged advanced tools with new Vue.js powered interface: HTTPS dialog, Site copy dialog, Download snapshot dialog, production/staging deployments, usage breakdown dialog, site toggle dialog and quicksave file diff dialog.
- Function `captaincore_fetch_domains` now works with other user roles.
- Upgraded Vuetify to 1.1.13.
- Cleaned up branding.
- Renamed WooCommerce endpoint 'manage' to 'sites'. Manage UI is now the Sites page.
- Renamed WooCommerce sidebar items. Manage UI to Sites. Manage DNS to DNS. Website Logs to Timeline.
- Removed old WooCommerce websites endpoint.

### Fixed

- Fixed FontAwesome on backend pages.

## **v0.2.7** - August 6, 2018

### Added

- Manage UI - Quicksaves added.
- Manage UI - Ability to add new sites.
- Function `captaincore_fetch_customer` to populate customer dropdown.
- Class for sites.
- Added Readme.md
- [Emoji-Log](https://github.com/ahmadawais/Emoji-Log) to git commits.

### Improved

- Manage UI - Included home url for sites. Added new button "launch sites in browser".
- Manage UI - Organized advanced filter options.
- Manage UI - Fixed bulk dialog scrolling.
- Manage UI - Run site prep after new site added.
- Manage UI - New usage stats to site list.
- Various improvements when displaying sites for customers.
- Cleaned up `captaincore_verify_permissions`
- Upgraded Vuetify to 1.1.7.

## **v0.2.6** - July 15, 2018

### Added

- Custom database tables for update logs and quicksaves. Significant Performance improvements.
- Manage UI - Dialog 'Update Settings' to manage autoupdate settings per site.
- Manage UI - Button to manually update sites.
- Manage UI - Bulk editing UI for themes, plugins and users.
- Manage UI - Theme drag and drop upload.

### Improved

- Moved CaptainCore API to custom rest endpoint. Replacing the custom page template API.
- Upgraded Vuetify to 1.1.1.
- Manage UI - Performance improvement. Fetch users only when tab clicked.
- Manage UI - Sort update logs by date
- Manage UI - Apply new toolbar headings to themes, users and update tabs.
- Manage UI - Improved plugin UI. Now must-use and dropin plugins are displayed below the management tools.
- Manage UI - Prep for opening it up to customers
- Improved function `captaincore_fetch_sites` to reduce complexity looping through sites which current user has access to.
- Removed custom post type Quicksaves. Replaced complex code with new custom table.
- Removed need to pass `<git_hash_previous>` with `captaincore quicksave-file-diff`.

## **v0.2.5** - July 1, 2018

### Added

- Updates tab to Manage UI. Populates using 'update-fetch' cli command.
- Users tab to Manage UI. Populates using 'users-fetch' cli command.
- Sharing tab to Manage UI.
- Command sync-data to CaptainCore API.
- WooCommerce tab for site health (GUI for CaptainCore CLI monitor)
- Manage UI - Drag and drop uploader for adding plugins.
- Navigation toggle icon for WooCommerce my account pages.

### Improved

- Ability to use 'manage' with single command.
- Manage UI - Performance improvements to Vue.js filtering by using a computed property
- Manage UI - Fix for filtering by theme.
- Manage UI - New option to select filtered sites.
- Moved Manage UI into WooCommerce endpoint. Removed old custom page template (page-manage.php).

## **v0.2.4** - June 17, 2018

### Added

- Configurable preinstall plugins to option page.

### Improved

- Improvements to Manage UI. Adds pagination, basic/advanced filter, search by site name, new tabs (themes/plugins) per site. Added ability to manage themes and plugins.
- Bug fixes and improvements to DNS editor.
- Improvements to Quicksaves UI. File restore now closes open modal. Individual rollback displays response in toast popup.
- Updated Materialize to v1.0.0-rc.1 (master branch). Fixes 2 major JS bugs.
- Updated commands to new names in 0.2.4 CLI
- Upgraded Font Awesome v5 CDN
- Limit Materialize css/js to select few pages
- Handle redirects of custom WooCommerce endpoints
- Quicksaves will now match created time of git commit
- Include express checkout payment link when manually sending order invoice for failed orders

## **v0.2.3** - June 3, 2018

### Added

- Toggle Site on advanced tab
- Button "Restore this file" to Quicksaves. Allow restoration of individual files.
- Add datapicker to "Download Backup Snapshot" on advanced tab if start backup date exists.

### Improved

- Improvements to Quicksaves UI.
- Improvements to usage breakdown section. Sorted sites by name. Moved totals into new total row.
- Upgraded to Materialize v1.0.0.-beta
- Improved toggle on hosting dashboard for admins

## **v0.2.2** - May 20, 2018

### Added

- Site Copy to advanced tab
- Apply HTTPS to advanced tab
- Email when site copy completes

### Improved

- Cleaned up Quicksaves interface
- Organized advanced tab links
- Consistent button styling throughout advanced tab
- B2 Snapshots now configurable from wp-config constant CAPTAINCORE_B2_SNAPSHOTS
- Renamed various internal functions to captaincore prefix

## **v0.2.1** - May 8, 2018

### Added

- New button to deploy Mailgun
- Automatically add Mailgun subdomain to relating domain during Mailgun setup
- Added icons website actions

### Improved

- Updated to new quicksave format
- Removed need of "ACF Enhanced Message Field" plugin
- Renamed "Load Configs" to "Website Actions"
- Renamed install field to site

### Fixed

- Bug causing site launch date to reset

## **v0.2.0** - April 22, 2018

### Improved

- Renamed plugin from CaptainCore Server to CaptainCore GUI
- Renamed various commands to match CaptainCore CLI
- Removed subsites from showing in main website list

### Fixed

- Removed additional pages from being picked up by Google. Added new 404 errors to remove existing pages from Google search results.
- CaptainCore API - Select site based on title rather then search. Prevents incorrect selection.

## **v0.1.8** - April 8, 2018

### Added

- Manage (admins only) - Replaced manage concept with entirely new version rewritten with Vue.js and Vuetify

### Improved

- Locked down quicksave and snapshot CPTs
- Manage DNS - display improvements for srv records
- Manage DNS - support for adding/editing srv records
- Manage DNS - display fix when creating initial records
- Allow json data with escaping to be stored in database.
- Increased font size on Quicksaves for better readability

### Fixed

- Permission fix for non administrators with remote commands

## **v0.1.7** - March 25, 2018

### Added

- Manage (admins only) - Filter and selecting sites for bulk actions, commands and scripts.

### Improved

- Visually highlighted admin pages on WooCommerce my account tabs

## **v0.1.6** - March 18, 2018

### Added

- Rollback entire quicksave
- Licenses keys page viewable from hosting dashboard for granted users

### Improved

- Renamed Anchor DNS to Manage DNS
- Reworked custom /my-account/ endpoints to load earlier
- Styled progress bars
- DNS Manager - Automatically add trailing dot when missed for cname and aname records
- DNS Manager - Detect duplicate txt records and autocorrect

### Fixed

- Moved custom /my-account/ endpoints checks to load later
- DNS Manager - Filter out deleted sites from DNS list

## **v0.1.5** - March 11, 2018

### Added

- Website logs - displays report of actual work done
- Quicksave link to manually check for file changes
- Merge process functionality `captaincore_merge_process( $process_id_source, $process_id_destination )`

### Improved

- Added website logs for inactive sites

### Fixed

- Renamed process role taxonomy for new CPT name
- Only include Jetpack markdown if available
- Prevent duplicate quicksaves from being generated
- Renamed CPT on quicksave report
- Process log menu tab
- Website bulk actions for new CPT name

## **v0.1.4** - March 4, 2018

### Added

- Mailgun logs added to hosting dashboard advanced page
- Added fields to sites for plugins, themes, core and home url.

### Improved

- Revisions to single process template. In header there is now a button to go back to all processes and an icon next to the log completion button.
- Updated CaptainCore API to auto update site info (plugins, themes, core and home url) when making a Quicksave
- Matched CaptainCore CLI changes to `captaincore site` commands
- Prefill database fields on staging
- Reduced header spacing on WooCommerce pages

### Fixed

- Incorrect named post types in CaptainCore API

## **v0.1.3** - February 25, 2018

### Added

- Email after Kinsta deployment completed
- Quicksave file diff command
- Preload snapshot dialog with current user email address
- Kinsta push staging to production
- Options to control basic info displayed on CaptainCore Client

### Improved

- Improvements to Quicksave view changes. It now slides open a separate panel.
- Switched staging urls to https on hosting dashboard
- Standardized custom post type names
- Display staging database info on hosting dashboard
- REST API adjustments for 'paid by' and 'address' fields
- Changed WordPress plugin titles to 'CaptainCore Server'

### Fixed

- Kinsta staging urls on hosting dashboard

## **v0.1.2** - February 18, 2018

### Added

- Rollbacks for quicksave themes and plugins
- Quicksave highligher now displays removed themes and plugins
- On hosting dashboard there is now an advanced option page
- Hide passwords on hosting dashboard with automatic reveal on hover
- Report to track Quicksaves per site

### Improved

- Improvements to Quicksave highligher
- Moved most of the hosting dashboard features over to the advanced option page
- Improvements to the single process template header. Moved log Completion button into header.
- Renamed Anchor API to CaptainCore API
- Switched CaptainCore API to POST request to handle larger payloads
- Added css versioning directly to file rather then querystring.

### Fixed

- Highlight fixes. Only highlight theme/plugin version or status.

## **v0.1.1** - February 11, 2018

### Added

- Quicksaves feature - Daily version history for themes, plugins and core
- Moved all admin menu under single CaptainCore menu
- Pulled in CSS styles from original child theme
- Pulled in JS from original child theme

### Improved

- Updated delete command format
- Upgraded to FontAwesome v5
- Improved layout of database and ssh info on hosting dashboard
- Improved menu tabs to display on edit/post/list screens
- Consolidated backup template into Anchor API

### Fixed

- DNS record count while viewing individual domain
- Snapshot from backend
- WooCommerce submenu styling issue

## **v0.1.0** - February 4, 2018

CaptainCore is born.
