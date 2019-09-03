=== CaptainCore ===
Author URI: https://twitter.com/austinginder
Plugin URI: https://captaincore.io
Contributors: austinginder
Tags: hosting, dns, wp hosting, web host, website management, web host business
Requires at least: 3.0.1
Tested up to: 3.4
Stable tag: 4.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Open source toolkit for managing WordPress Sites via SSH & WP-CLI

== Description ==

[CaptainCore](https://captaincore.io) is an open source toolkit for managing WordPress sites via SSH & WP-CLI. This is a WordPress plugin that requires a connection to CaptainCore CLI.

== Installation ==

1. Upload `/captaincore/` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. CaptainCore requires access to a remote server running CaptainCore CLI. Add following auth info to wp-config.php file.

# CaptainCore CLI keys
define( 'CAPTAINCORE_CLI_TOKEN', "xxxxxxxxxxxxxxxxxxxxxxxxx" );
define( 'CAPTAINCORE_CLI_USER', "xxxxxxxx" );
define( 'CAPTAINCORE_CLI_KEY', "xxxxxxxxxxxxxxxxxxxxxxxxx" );
define( 'CAPTAINCORE_CLI_ADDRESS', "xxx.xxx.xxx.xxx" );
define( 'CAPTAINCORE_CLI_PORT', "xxxxx" );

# CaptainCore B2 keys
define( 'CAPTAINCORE_B2_ACCOUNT_ID', 'xxxxxxxxxxxx' );
define( 'CAPTAINCORE_B2_ACCOUNT_KEY', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' );
define( 'CAPTAINCORE_B2_BUCKET_ID', 'xxxxxxxxxxxxxxxxxxxxxxxx' );
define( 'CAPTAINCORE_B2_SNAPSHOTS', "Bucket/Foldername" );

== Frequently Asked Questions ==

TODO

== Screenshots ==

TODO

== Changelog ==
= 0.6.0: September 3 2019
* New: Renamed plugin from CaptainCore GUI to CaptainCore
* New: Decoupled CaptainCore from WooCommerce. Now runs within standalone PHP template for better compatibility.
* New: Snapshot management section. Links to generated snapshots now automatically expire after 24 hours. Links can be regenerated whenever.
* New: Delete default user
* New: Default recipes
* Tweak: Upgraded Vue.JS to 2.6.10
* Tweak: Upgraded Vuetify to v2.0.4
* Tweak: Combined Theme and Plugin tabs into new Addons tab.
* Tweak: Improvements to multisite column
* Tweak: Improvements to quicksave management
* Tweak: Improvements to stats tab
* Tweak: Improvements to site search performance
* Tweak: Improvements to DNS editor
* Tweak: Fix site stats rounding bug with bounce rate
* Tweak: Removed default plugins. New replacement is to configure default recipes instead.

= 0.5.0: July 24 2019 =
* New: Routing based on hash. Will now toggle between `/sites`, `/sites#dns`, `/sites#cookbook` and `/sites#handbook` without page reloading.
* New: Custom recipes which can be public or private to the author. Public recipes can run. Private recipes can be loaded and changed before running.
* New: DNS manager completed rewritten in Vue.JS. Replaces old jQuery DNS manager.
* New: Options for DNS introduction and DNS nameservers which are displayed in an info alert at top of DNS page.
* New: Option for managing timezone per account.
* New: Custom link `/my-account/sites#cookbook` added to WooCommerce endpoint for Cookbook.
* New: Configure default section for managing WordPress default settings per account. Settings include admin email, timezone, plugins and users. Replaces legacy WooCommerce endpoint for configs.
* New: Timeline logs sections. Replaces legacy WooCommerce endpoint for logs.
* Tweak: Replaced custom WooCommerce endpoints `/my-account/dns/` and `/my-account/handbook/` with new single page `/my-account/sites#dns` and `/my-account/sites#handbook`.
* Tweak: Improved managing user-defined recipes. 
* Tweak: Improved theme/plugin upload layout.
* Tweak: License deployments now handled by custom defined recipes. Replaces legacy WooCommerce endpoint for Licenses.
* Tweak: Many minor improvements to sites page. That includes clearable search, highlight button when toggled on/off and simplified top level buttons. Running Jobs, Bulk Management and Advanced Filters buttons are now called Job Activity, Bulk Tools and Filters. They have been reduced to icons with tooltips.
* Tweak: Custom links added to WooCommerce my-account menu now operate in single page mode. Selecting Sites, DNS, Cookbook or Handbook will not reload the page.

= 0.4.5: June 29 2019 =
* New: Admin dashboard with SVG menu icon. Replaces old admin pages. Includes link to legacy custom post types.
* New: Stats tab per environment. Fetches stats from Fathom Analytics.
* New: Dialog to reassign user content when deleting user.
* New: Dialog to run launch site script.
* Tweak: Improved site validations and deletions
* Tweak: Fixed links to Kinsta's database url and staging url
* Tweak: Fixed assigning existing customers to new site

= 0.4.4: June 4 2019 =
* New: Bulk scripts section
* New: Bulk log entry support
* New: Run custom code section
* New: Migrate script dialog
* Tweak: Added date field to edit process entry log.
* Tweak: Site sorting icons.
* Tweak: Improved file upload error handling. If /wp-content/deploy/ directory not exists, create it. If upload failed then report it. 
* Tweak: Improved theme & plugin uploads. 
* Tweak: Improved bulk actions environment support
* Tweak: Improved bulk management section. Moved toggle commands here.

= 0.4.3: May 12 2019 =
* New: Sites UI - Realtime websockets which replaces the `jobRetry` polling method. CLI commands are now run from a websocket and output streamed in realtime.
* New: Sites UI - Log history dialog to handbook section
* New: Sites UI - Cookbook section
* Tweak: Sites UI - Handbook section improved with new dialogs for viewing and editing. Added logging generic entry not tied to a website.
* Tweak: Sites UI - Overhauled main layout and greatly improved the advanced filter interface.
* Tweak: Consistent dialog stylings
* Tweak: Improved autocomplete UX based [example from John Leider](https://codepen.io/johnjleider/pen/MQRjme?&editors=101)
* Tweak: Compatibility fix for [ARVE](https://wordpress.org/plugins/advanced-responsive-video-embedder/) video embeds.
* Tweak: Compatibility fix when adding/updating process log entries. Force relationship fields to save in serialized format.

= 0.4.2: April 22 2019 =
* New: Sites UI - Timeline tab. Administrators can add new log entrys per site.
* New: Sites UI - Handbook section for administrators.
* New: Sites UI - Screenshot thumbnails of websites are automatically added/updated when synced.
* Tweak: Sites UI - Performance improvement when working with sites. Now only 1 site panel will be open at a time.
* Tweak: Replaced markdown support from using Jetpack plugin to [Parsedown](https://parsedown.org/)
* Tweak: Upgrade compatibility for CaptainCore helper v0.2.0

= 0.4.1: April 4 2019 =
* New: Sites UI - Site plan tab for managing hosting plans. Administrators can assign plans per customer which define storage, visits and number of sites usage.
* New: Sites UI - Added button for removing a site.
* New: Sites UI - Added icon to manual sync a site.
* Tweak: Include ACF field groups via PHP.
* Tweak: Removed WooCommerce tab for site health.
* Tweak: Display DNS tab if CONSTELLIX_API_KEY and CONSTELLIX_SECRET_KEY defined. 
* Tweak: Display domains on overview page if DNS is defined.
* Tweak: Sites UI - Improve snapshot dialog
* Tweak: Sites UI - Improve font used for code diff
* Tweak: Sites UI - Track Quicksave progress
* Tweak: Sites UI - Specify provider instead of extracting from the address.
* Tweak: Sites UI - Snapshot options
* Fix: Deactivate command passed to CLI

= 0.4.0: March 4 2019 =
* New: Sites UI - Environment support added (themes, plugins, users, updates, scripts, backups and quicksaves)
* New: Sites UI - Label for multisite networks
* New: Support for environments
* New: CLI support for Fathom code
* New: DB method `all`
* Tweak: Sites UI - Improvements to site edit dialog and file diff dialog
* Tweak: Removed legacy subsites support. Subsite should not be added as a seperate site.
* Tweak: Upgraded Vuetify to v1.5.4
* Fix: Sites UI - Staging links.
* Fix: Sites UI - Manage commands to Dispatch server.

= 0.3.3: February 9 2019 =
* New: Sites UI - Fathom tracker
* Tweak: Sites UI - Moved many of the commands like 'Mailgun', 'Site copy' into the background with proper status reporting.
* Tweak: Sites UI - Improve response when and added loading status when listing quicksave changes.
* Tweak: Sites UI - Added feedback to production/staging deployments and adding new sites. 
* Fix: Sites UI - Feedback when applying HTTPS urls and creating Snapshots
* Fix: Sites UI - SSH staging port
* Fix: Various PHP errors and warnings.
* Fix: Company Handbook - restore bullets

= 0.3.2: December 31 2018 =
* New: Integrated CaptainCore Dispatch
* Tweak: Refactor fetch functions `captaincore_fetch_customer`, `captaincore_fetch_domains` and `captaincore_fetch_sites` to new classes `CaptainCore/Customers`, `CaptainCore/Domains`and `CaptainCore/Sites`
* Tweak: Sites UI - Refactor code into CaptainCore Site class
* Tweak: Sites UI - Load customers and sites with new Rest APIs `captaincore/v1/customers/` and `captaincore/v1/sites/`
* Tweak: Sites UI - Load WP-API nonce before Vue.js mounted lifecycle 
* Tweak: Sites UI - Handle errors with jobs. Prompt notice when login failed
* Tweak: Upgraded Vuetify to v1.3.11
* Tweak: Removed phpseclib

= 0.3.1: December 3 2018 =
* NEW: Sites UI - Quick logins
* Tweak: Company Handbook - Allow administrators with multiple roles
* Tweak: Sites UI - Mask database passwords
* Tweak: Sites UI - Improve display of roles on user listing
* Tweak: Removed custom post type captcore_server since provider can be inferred directly based off address.
* Tweak: Support for Kinsta .cloud tld

= 0.3.0: October 14 2018 =
* Tweak: Improved feedback from 'usage-update' request.
* Tweak: Password reset bug fix on '/my-account/edit-account/' page.

= 0.2.9: September 26, 2018 =
* NEW: Site class method 'update'
* NEW: Sites UI - Edit site dialog
* NEW: Sites UI - Quicksave check dialog
* NEW: Sites UI - Quicksave compare and highlight changes.
* Tweak: Upgraded Vue.JS to 2.5.17.
* Tweak: Permission bug fixes for Quicksaves.
* Tweak: Fixed bug preventing sites from resetting when cleared.
* Tweak: Housecleaning - Moved files from /inc/ to /includes/.
* Tweak: Sites UI - Email notify after site deployments

= 0.2.8: August 20, 2018 =
* NEW: Overview stats after logged in: "You have access to *** WordPress Sites and DNS for *** domains."
* NEW: Manage DNS - List all domains.
* NEW: Combined interface for listing/managing sites - Combined old site listing and advanced options with new Vue.JS interface.
* NEW: Sites UI - Advanced tab (first draft)
* Tweak: Sites UI - Major improvements for site management. Preparation for adding staging to manage ui, see concept video: https://vimeo.com/284488960/10872cca8e.
* Tweak: Sites UI - Merged advanced tools with new Vue.JS powered interface: HTTPS dialog, Site copy dialog, Download snapshot dialog, production/staging deployments, usage breakdown dialog, site toggle dialog and Quicksave file diff dialog.
* Tweak: Function `captaincore_fetch_domains` now works with other user roles.
* Tweak: Fixed FontAwesome on backend pages.
* Tweak: Upgraded Vuetify to 1.1.13.
* Tweak: Cleaned up branding.
* Tweak: Renamed WooCommerce endpoint 'manage' to 'sites'. Manage UI is now the Sites page.
* Tweak: Renamed WooCommerce sidebar items. Manage UI to Sites. Manage DNS to DNS. Website Logs to Timeline.
* Tweak: Removed old WooCommerce websites endpoint.

= 0.2.7: August 6, 2018 =
* New: Manage UI - Quicksaves added.
* New: Manage UI - Ability to add new sites.
* New: Function `captaincore_fetch_customer` to populate customer dropdown.
* New: Class for sites.
* New: Added Readme.md
* New: [Emoji-Log](https://github.com/ahmadawais/Emoji-Log) to git commits.
* Tweak: Manage UI - Included home url for sites. Added new button "launch sites in browser".
* Tweak: Manage UI - Organized advanced filter options.
* Tweak: Manage UI - Fixed bulk dialog scrolling.
* Tweak: Manage UI - Run site prep after new site added.
* Tweak: Manage UI - New usage stats to site list.
* Tweak: Various improvements when displaying sites for customers.
* Tweak: Cleaned up `captaincore_verify_permissions`
* Tweak: Upgraded Vuetify to 1.1.7.

= 0.2.6: July 15, 2018 =
* New: Custom database tables for update logs and quicksaves. Significant Performance improvements.
* New: Manage UI - Dialog 'Update Settings' to manage autoupdate settings per site.
* New: Manage UI - Button to manually update sites.
* New: Manage UI - Bulk editing UI for themes, plugins and users.
* New: Manage UI - Theme drag and drop upload.
* Tweak: Moved CaptainCore API to custom rest endpoint. Replacing the custom page template API.
* Tweak: Upgraded Vuetify to 1.1.1.
* Tweak: Manage UI - Performance improvement. Fetch users only when tab clicked.
* Tweak: Manage UI - Sort update logs by date
* Tweak: Manage UI - Apply new toolbar headings to themes, users and update tabs.
* Tweak: Manage UI - Improved plugin UI. Now must-use and dropin plugins are displayed below the management tools.
* Tweak: Manage UI - Prep for opening it up to customers
* Tweak: Improved function `captaincore_fetch_sites` to reduce complexity looping through sites which current user has access to.
* Tweak: Removed custom post type Quicksaves. Replaced complex code with new custom table.
* Tweak: Removed need to pass `<git_hash_previous>` with `captaincore quicksave-file-diff`.

= 0.2.5: July 1, 2018 =
* New: Updates tab to Manage UI. Populates using 'update-fetch' cli command.
* New: Users tab to Manage UI. Populates using 'users-fetch' cli command.
* New: Sharing tab to Manage UI.
* New: Command sync-data to CaptainCore API.
* New: WooCommerce tab for site health (GUI for CaptainCore CLI monitor)
* New: Manage UI - Drag and drop uploader for adding plugins.
* New: Navigation toggle icon for WooCommerce my account pages.
* Tweak: Ability to use 'manage' with single command.
* Tweak: Manage UI - Performance improvements to Vue.js filtering by using a computed property
* Tweak: Manage UI - Fix for filtering by theme.
* Tweak: Manage UI - New option to select filtered sites.
* Tweak: Moved Manage UI into WooCommerce endpoint. Removed old custom page template (page-manage.php).

= 0.2.4: June 17, 2018 =
* New: Configurable preinstall plugins to option page.
* Tweak: Improvements to Manage UI. Adds pagination, basic/advanced filter, search by site name, new tabs (themes/plugins) per site. Added ability to manage themes and plugins.
* Tweak: Bug fixes and improvements to DNS editor.
* Tweak: Improvements to Quicksaves UI. File restore now closes open modal. Individual rollback displays response in toast popup.
* Tweak: Updated Materialize to v1.0.0-rc.1 (master branch). Fixes 2 major JS bugs.
* Tweak: Updated commands to new names in 0.2.4 CLI
* Tweak: Upgraded Font Awesome v5 CDN
* Tweak: Limit Materialize css/js to select few pages
* Tweak: Handle redirects of custom WooCommerce endpoints
* Tweak: Quicksaves will now match created time of git commit
* Tweak: Include express checkout payment link when manually sending order invoice for failed orders

= 0.2.3: June 3, 2018 =
* New: Toggle Site on advanced tab
* New: Button "Restore this file" to Quicksaves. Allow restoration of individual files.
* New: Add datapicker to "Download Backup Snapshot" on advanced tab if start backup date exists.
* Tweak: Improvements to Quicksaves UI.
* Tweak: Improvements to usage breakdown section. Sorted sites by name. Moved totals into new total row.
* Tweak: Upgraded to Materialize v1.0.0.-beta
* Tweak: Improved toggle on hosting dashboard for admins

= 0.2.2: May 20, 2018 =
* New: Site Copy to advanced tab
* New: Apply HTTPS to advanced tab
* New: Email when site copy completes
* Tweak: Cleaned up Quicksaves interface
* Tweak: Organized advanced tab links
* Tweak: Consistent button styling throughout advanced tab
* Tweak: B2 Snapshots now configurable from wp-config constant CAPTAINCORE_B2_SNAPSHOTS
* Tweak: Renamed various internal functions to captaincore prefix

= 0.2.1: May 8, 2018 =
* New: New button to deploy Mailgun
* New: Automatically add Mailgun subdomain to relating domain during Mailgun setup
* New: Added icons website actions
* Tweak: Updated to new quicksave format
* Tweak: Removed need of "ACF Enhanced Message Field" plugin
* Tweak: Renamed "Load Configs" to "Website Actions"
* Tweak: Renamed install field to site
* Fix: Bug causing site launch date to reset

= 0.2.0: April 22, 2018 =
* Tweak: Renamed plugin from CaptainCore Server to CaptainCore GUI
* Tweak: Renamed various commands to match CaptainCore CLI
* Tweak: Removed subsites from showing in main website list
* Fix: Removed additional pages from being picked up by Google. Added new 404 errors to remove existing pages from Google search results.
* Fix: CaptainCore API - Select site based on title rather then search. Prevents incorrect selection.

= 0.1.8: April 8, 2018 =
* New: Manage (admins only) - Replaced manage concept with entirely new version rewritten with Vue.js and Vuetify
* Tweak: Locked down quicksave and snapshot CPTs
* Tweak: Manage DNS - display improvements for srv records
* Tweak: Manage DNS - support for adding/editing srv records
* Tweak: Manage DNS - display fix when creating initial records
* Tweak: Allow json data with escaping to be stored in database.
* Tweak: Increased font size on Quicksaves for better readability
* Fix: Permission fix for non administrators with remote commands

= 0.1.7: March 25, 2018 =
* New: Manage (admins only) - Filter and selecting sites for bulk actions, commands and scripts.
* Tweak: Visually highlighted admin pages on WooCommerce my account tabs

= 0.1.6: March 18, 2018 =
* New: Rollback entire quicksave
* New: Licenses keys page viewable from hosting dashboard for granted users
* Tweak: Renamed Anchor DNS to Manage DNS
* Tweak: Reworked custom /my-account/ endpoints to load earlier
* Tweak: Styled progress bars
* Tweak: DNS Manager - Automatically add trailing dot when missed for cname and aname records
* Tweak: DNS Manager - Detect duplicate txt records and autocorrect
* Fix: Moved custom /my-account/ endpoints checks to load later
* Fix: DNS Manager - Filter out deleted sites from DNS list

= 0.1.5: March 11, 2018 =
* New: Website logs - displays report of actual work done
* New: Quicksave link to manually check for file changes
* New: Merge process functionality `captaincore_merge_process( $process_id_source, $process_id_destination )`
* Tweak: Added website logs for inactive sites
* Fix: Renamed process role taxonomy for new CPT name
* Fix: Only include Jetpack markdown if available
* Fix: Prevent duplicate quicksaves from being generated
* Fix: Renamed CPT on quicksave report
* Fix: Process log menu tab
* Fix: Website bulk actions for new CPT name

= 0.1.4: March 4, 2018 =
* New: Mailgun logs added to hosting dashboard advanced page
* New: Added fields to sites for plugins, themes, core and home url.
* Tweak: Revisions to single process template. In header there is now a button to go back to all processes and an icon next to the log completion button.
* Tweak: Updated CaptainCore API to auto update site info (plugins, themes, core and home url) when making a Quicksave
* Tweak: Matched CaptainCore CLI changes to `captaincore site` commands
* Tweak: Prefill database fields on staging
* Tweak: Reduced header spacing on WooCommerce pages
* Fix: Incorrect named post types in CaptainCore API

= 0.1.3: February 25, 2018 =
* New: Email after Kinsta deployment completed
* New: Quicksave file diff command
* New: Preload snapshot dialog with current user email address
* New: Kinsta push staging to production
* New: Options to control basic info displayed on CaptainCore Client
* Tweak: Improvements to Quicksave view changes. It now slides open a separate panel.
* Tweak: Switched staging urls to https on hosting dashboard
* Tweak: Standardized custom post type names
* Tweak: Display staging database info on hosting dashboard
* Tweak: REST API adjustments for 'paid by' and 'address' fields
* Tweak: Changed WordPress plugin titles to 'CaptainCore Server'
* Fix: Kinsta staging urls on hosting dashboard

= 0.1.2: February 18, 2018 =
* New: Rollbacks for quicksave themes and plugins
* New: Quicksave highligher now displays removed themes and plugins
* New: On hosting dashboard there is now an advanced option page
* New: Hide passwords on hosting dashboard with automatic reveal on hover
* New: Report to track Quicksaves per site
* Tweak: Improvements to Quicksave highligher
* Tweak: Moved most of the hosting dashboard features over to the advanced option page
* Tweak: Improvements to the single process template header. Moved log Completion button into header.
* Tweak: Renamed Anchor API to CaptainCore API
* Tweak: Switched CaptainCore API to POST request to handle larger payloads
* Tweak: Added css versioning directly to file rather then querystring.
* Fix: Highlight fixes. Only highlight theme/plugin version or status.

= 0.1.1: February 11, 2018 =
* New: Quicksaves feature - Daily version history for themes, plugins and core
* New: Moved all admin menu under single CaptainCore menu
* New: Pulled in CSS styles from original child theme
* New: Pulled in JS from original child theme
* Tweak: Updated delete command format
* Tweak: Upgraded to FontAwesome v5
* Tweak: Improved layout of database and ssh info on hosting dashboard
* Tweak: Improved menu tabs to display on edit/post/list screens
* Tweak: Consolidated backup template into Anchor API
* Fix: DNS record count while viewing individual domain
* Fix: Snapshot from backend
* Fix: WooCommerce submenu styling issue

= 0.1.0: February 4, 2018 =
* CaptainCore is born.
