=== CaptainCore Server ===
Author URI: https://anchor.host
Plugin URI: https://captaincore.io
Contributors: austinginder
Tags: hosting, dns, wp hosting, web host, website management, web host business
Requires at least: 3.0.1
Tested up to: 3.4
Stable tag: 4.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Toolkit for running your own WordPress hosting business

== Description ==

CaptainCore is an open sourced toolkit made and used by [Anchor Hosting](https://anchor.host). If your a geek take a look on Github and help contribute! If you just want to pay someone to setup and run CaptainCore for your business then check out [CaptainCore.io](https://captaincore.io). We'll handle getting your business and hosting customers moved over.

== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Upload `/captaincore/` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. CaptainCore requires access to a remote server running CaptainCore CLI. (TODO: add instructions for adding keys to wp-config.php)

== Frequently Asked Questions ==

TODO

== Screenshots ==

TODO

== Changelog ==

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
