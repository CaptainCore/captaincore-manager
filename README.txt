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
