<?php
if ( ! function_exists( 'is_plugin_active' ) ) {
	include_once ABSPATH . 'wp-admin/includes/plugin.php';
}

$plugin_url        = plugin_dir_url( __DIR__ );
$user              = ( new CaptainCore\User )->profile();
$configurations    = ( new CaptainCore\Configurations )->get();
$colors            = CaptainCore\Configurations::colors();
$footer            = json_decode( captaincore_footer_content_extracted(), true );
$socket            = captaincore_fetch_socket_address() . '/ws';
$site_filters      = ( new CaptainCore\Environments )->filters();
$site_filters_core = ( new CaptainCore\Environments )->filters_for_core();
$modules           = [
	'billing' => ! defined( 'CAPTAINCORE_CUSTOM_DOMAIN' ),
	'dns'     => defined( 'CONSTELLIX_API_KEY' ) && defined( 'CONSTELLIX_SECRET_KEY' ),
];

if ( ! is_array( $footer ) ) {
	$footer = [
		'switch_to_link' => '',
		'switch_to_text' => '',
	];
}

$color_value = static function ( $key, $fallback ) use ( $colors ) {
	if ( is_object( $colors ) && isset( $colors->{$key} ) ) {
		return $colors->{$key};
	}
	if ( is_array( $colors ) && isset( $colors[ $key ] ) ) {
		return $colors[ $key ];
	}
	return $fallback;
};

$json_options = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
?><!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
	<title><?php echo esc_html( $configurations->name ); ?> - Account</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="description" content="Manage your sites, billing, and account details.">
	<meta charset="utf-8">
	<?php captaincore_header_content_extracted(); ?>
	<link href="<?php echo esc_url( home_url( $configurations->path ?? '/account/' ) ); ?>" rel="canonical">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
	<style>
		[v-cloak] { display: none !important; }
		:root {
			--cc-primary: <?php echo esc_attr( $color_value( 'primary', '#2c3e50' ) ); ?>;
			--cc-accent: <?php echo esc_attr( $color_value( 'accent', '#f8f9fb' ) ); ?>;
			--cc-success: <?php echo esc_attr( $color_value( 'success', '#2e7d32' ) ); ?>;
			--cc-warning: <?php echo esc_attr( $color_value( 'warning', '#f59e0b' ) ); ?>;
			--cc-error: <?php echo esc_attr( $color_value( 'error', '#c2410c' ) ); ?>;
			--paper: oklch(1 0 0);
			--paper-2: oklch(0.975 0.003 240);
			--paper-3: oklch(0.955 0.005 240);
			--rule: oklch(0.88 0.008 240);
			--ink: oklch(0.30 0.014 250);
			--ink-2: oklch(0.48 0.012 250);
			--ink-3: oklch(0.64 0.010 250);
			--brand: oklch(0.72 0.14 235);
			--brand-ink: oklch(0.54 0.16 235);
			--ok: oklch(0.68 0.16 150);
			--warn: oklch(0.78 0.16 80);
			--bad: oklch(0.62 0.18 25);
			--radius-sm: 6px;
			--radius: 8px;
			--shadow: 0 16px 40px -24px rgb(20 28 45 / 0.28), 0 1px 0 rgb(20 28 45 / 0.04);
			--app-maxw: 1380px;
			--gutter: 32px;
			--sans: "Space Grotesk", ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
			--mono: "JetBrains Mono", ui-monospace, "SF Mono", Menlo, Consolas, monospace;
		}
		:root[data-theme="dark"] {
			--paper: oklch(0.19 0.018 240);
			--paper-2: oklch(0.23 0.020 240);
			--paper-3: oklch(0.28 0.022 240);
			--rule: oklch(0.36 0.022 240);
			--ink: oklch(0.96 0.005 240);
			--ink-2: oklch(0.80 0.008 240);
			--ink-3: oklch(0.64 0.010 240);
			--brand: oklch(0.78 0.14 235);
			--brand-ink: oklch(0.84 0.12 235);
			--shadow: 0 24px 48px -28px rgb(0 0 0 / 0.72);
		}
		* { box-sizing: border-box; }
		html, body { margin: 0; padding: 0; min-height: 100%; }
		body {
			background: var(--paper-2);
			color: var(--ink);
			font-family: var(--sans);
			font-size: 15px;
			line-height: 1.45;
			-webkit-font-smoothing: antialiased;
			text-rendering: optimizeLegibility;
		}
		body.cc-v2-lock { overflow: hidden; }
		button, input, select, textarea { font: inherit; }
		button { cursor: pointer; }
		a { color: inherit; text-decoration: none; }
		img, svg { display: block; max-width: 100%; }
		.cc-v2 { min-height: 100vh; }
		.cc-v2.is-login .app-topbar { display: none; }
		.app-topbar {
			position: sticky;
			top: 0;
			z-index: 60;
			background: color-mix(in oklab, var(--paper) 92%, transparent);
			border-bottom: 1px solid var(--rule);
			backdrop-filter: saturate(140%) blur(12px);
			-webkit-backdrop-filter: saturate(140%) blur(12px);
		}
		.topbar__inner {
			max-width: var(--app-maxw);
			height: 64px;
			margin: 0 auto;
			padding: 0 var(--gutter);
			display: flex;
			align-items: center;
			gap: 24px;
		}
		.brand {
			display: inline-flex;
			align-items: center;
			gap: 10px;
			min-width: 0;
		}
		.brand__logo {
			width: auto;
			max-width: 150px;
			max-height: 32px;
			object-fit: contain;
		}
		.brand__name {
			font-size: 17px;
			font-weight: 700;
			color: var(--ink);
			white-space: nowrap;
		}
		.app-nav {
			display: flex;
			align-items: center;
			gap: 4px;
			margin-left: 8px;
		}
		.app-nav a {
			border-radius: var(--radius-sm);
			color: var(--ink-3);
			font-family: var(--mono);
			font-size: 11px;
			letter-spacing: 0;
			padding: 8px 11px;
			text-transform: uppercase;
			transition: background .15s ease, color .15s ease;
			white-space: nowrap;
		}
		.app-nav a:hover,
		.app-nav a.active {
			background: var(--paper-2);
			color: var(--ink);
		}
		.app-tools {
			display: flex;
			align-items: center;
			gap: 8px;
			margin-left: auto;
		}
		.icon-btn,
		.segmented button,
		.row-action,
		.drawer__close {
			width: 34px;
			height: 34px;
			border: 1px solid var(--rule);
			border-radius: var(--radius-sm);
			background: var(--paper);
			color: var(--ink-2);
			display: inline-grid;
			place-items: center;
			transition: background .15s ease, color .15s ease, border-color .15s ease;
		}
		.icon-btn:hover,
		.segmented button:hover,
		.row-action:hover,
		.drawer__close:hover {
			background: var(--paper-3);
			color: var(--ink);
			border-color: color-mix(in oklab, var(--ink) 24%, var(--rule));
		}
		.user-chip {
			border: 1px solid var(--rule);
			border-radius: 999px;
			background: var(--paper);
			display: inline-flex;
			align-items: center;
			gap: 8px;
			min-width: 0;
			padding: 4px 10px 4px 4px;
		}
		.avatar {
			width: 26px;
			height: 26px;
			border-radius: 999px;
			background: linear-gradient(135deg, var(--brand), color-mix(in oklab, var(--brand) 45%, var(--ink)));
			color: white;
			display: grid;
			place-items: center;
			font-family: var(--mono);
			font-size: 11px;
			font-weight: 600;
		}
		.user-chip span {
			max-width: 150px;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
		.app-shell {
			max-width: var(--app-maxw);
			margin: 0 auto;
			padding: 28px var(--gutter) 96px;
		}
		.summary {
			display: flex;
			align-items: flex-end;
			justify-content: space-between;
			gap: 16px;
			margin-bottom: 20px;
		}
		.summary__title {
			display: flex;
			align-items: baseline;
			gap: 14px;
			min-width: 0;
		}
		h1 {
			font-size: 28px;
			font-weight: 650;
			line-height: 1.1;
			margin: 0;
			color: var(--ink);
		}
		.summary__sub,
		.table-caption,
		.meta-label {
			color: var(--ink-3);
			font-family: var(--mono);
			font-size: 11px;
			letter-spacing: 0;
			text-transform: uppercase;
		}
		.summary__pills,
		.chips,
		.badges {
			display: flex;
			align-items: center;
			flex-wrap: wrap;
			gap: 8px;
		}
		.pill,
		.chip-filter,
		.badge {
			display: inline-flex;
			align-items: center;
			gap: 7px;
			border: 1px solid var(--rule);
			border-radius: 999px;
			background: var(--paper);
			color: var(--ink-2);
			font-family: var(--mono);
			font-size: 11px;
			letter-spacing: 0;
			line-height: 1;
			min-height: 28px;
			padding: 7px 11px;
			text-transform: uppercase;
			white-space: nowrap;
		}
		.pill b { color: var(--ink); font-weight: 700; }
		.dot {
			width: 7px;
			height: 7px;
			border-radius: 999px;
			background: var(--ink-3);
			flex: none;
		}
		.dot.ok { background: var(--ok); }
		.dot.warn { background: var(--warn); }
		.dot.bad { background: var(--bad); }
		.toolbar {
			display: grid;
			grid-template-columns: minmax(0, 1fr) auto auto;
			gap: 10px;
			align-items: center;
			background: var(--paper);
			border: 1px solid var(--rule);
			border-radius: var(--radius);
			margin-bottom: 14px;
			padding: 8px 8px 8px 14px;
		}
		.search-input {
			display: flex;
			align-items: center;
			gap: 10px;
			min-width: 0;
		}
		.search-input svg { color: var(--ink-3); flex: none; }
		.search-input input,
		.filter-search input,
		.form-field input,
		.form-field select,
		.form-field textarea {
			width: 100%;
			border: 0;
			background: transparent;
			color: var(--ink);
			outline: 0;
		}
		.search-input input { min-width: 80px; font-size: 15px; }
		.search-input input::placeholder,
		.filter-search input::placeholder { color: var(--ink-3); }
		.scope { color: var(--ink-3); font-family: var(--mono); font-size: 11px; white-space: nowrap; }
		.segmented {
			display: inline-flex;
			gap: 2px;
			border: 1px solid var(--rule);
			border-radius: var(--radius-sm);
			background: var(--paper-2);
			padding: 2px;
		}
		.segmented button {
			width: 30px;
			height: 28px;
			border: 0;
			background: transparent;
		}
		.segmented button.active {
			background: var(--paper);
			color: var(--ink);
			box-shadow: 0 1px 0 rgb(0 0 0 / 0.05);
		}
		.primary-btn,
		.secondary-btn,
		.danger-btn {
			border: 1px solid var(--ink);
			border-radius: var(--radius-sm);
			display: inline-flex;
			align-items: center;
			justify-content: center;
			gap: 7px;
			min-height: 34px;
			padding: 8px 13px;
			font-size: 13px;
			font-weight: 600;
			white-space: nowrap;
		}
		.primary-btn { background: var(--ink); color: var(--paper); }
		.primary-btn:hover { background: color-mix(in oklab, var(--ink) 90%, white); }
		.secondary-btn {
			background: var(--paper);
			border-color: var(--rule);
			color: var(--ink);
		}
		.secondary-btn:hover { background: var(--paper-3); }
		.danger-btn {
			background: color-mix(in oklab, var(--bad) 10%, var(--paper));
			border-color: color-mix(in oklab, var(--bad) 34%, var(--rule));
			color: var(--bad);
		}
		.chips {
			margin-bottom: 18px;
			position: relative;
			z-index: 25;
		}
		.chip-filter { cursor: pointer; }
		.chip-filter:hover {
			border-color: color-mix(in oklab, var(--ink) 24%, var(--rule));
			color: var(--ink);
		}
		.chip-filter.active {
			background: var(--ink);
			border-color: var(--ink);
			color: var(--paper);
		}
		.count {
			border-radius: 999px;
			background: var(--paper-2);
			color: var(--ink-2);
			padding: 2px 6px;
		}
		.chip-filter.active .count {
			background: color-mix(in oklab, white 20%, transparent);
			color: var(--paper);
		}
		.chip-wrap { position: relative; }
		.filter-pop {
			position: absolute;
			top: calc(100% + 8px);
			left: 0;
			width: 340px;
			max-width: calc(100vw - 28px);
			background: var(--paper);
			border: 1px solid var(--rule);
			border-radius: var(--radius);
			box-shadow: var(--shadow);
			display: none;
			overflow: hidden;
		}
		.filter-pop.open { display: block; }
		.filter-pop__head {
			border-bottom: 1px solid var(--rule);
			display: grid;
			gap: 8px;
			padding: 12px;
		}
		.filter-search {
			display: flex;
			align-items: center;
			gap: 8px;
			border: 1px solid var(--rule);
			border-radius: var(--radius-sm);
			background: var(--paper-2);
			padding: 7px 9px;
		}
		.filter-list {
			max-height: 300px;
			overflow-y: auto;
			padding: 6px;
		}
		.filter-option {
			width: 100%;
			border: 0;
			border-radius: var(--radius-sm);
			background: transparent;
			color: var(--ink);
			display: grid;
			grid-template-columns: minmax(0, 1fr) auto;
			gap: 12px;
			padding: 8px 9px;
			text-align: left;
		}
		.filter-option:hover,
		.filter-option.active { background: var(--paper-2); }
		.filter-option .name {
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
		.filter-option .muted { color: var(--ink-3); font-family: var(--mono); font-size: 11px; }
		.sites {
			background: var(--paper);
			border: 1px solid var(--rule);
			border-radius: var(--radius);
			overflow: hidden;
		}
		.sites__head,
		.site-row {
			display: grid;
			grid-template-columns: 32px minmax(260px, 1.7fr) 110px 110px 110px 116px 116px 74px;
			gap: 12px;
			align-items: center;
		}
		.sites__head {
			background: var(--paper-2);
			border-bottom: 1px solid var(--rule);
			color: var(--ink-3);
			font-family: var(--mono);
			font-size: 11px;
			letter-spacing: 0;
			padding: 11px 14px;
			text-transform: uppercase;
		}
		.site-group + .site-group { border-top: 1px solid var(--rule); }
		.site-group__bar {
			background: color-mix(in oklab, var(--paper-2) 80%, var(--paper));
			border-bottom: 1px solid var(--rule);
			display: flex;
			align-items: center;
			gap: 12px;
			min-height: 38px;
			padding: 8px 14px 8px 58px;
		}
		.site-group__bar .domain {
			color: var(--ink);
			font-weight: 650;
		}
		.site-group__bar .meta {
			color: var(--ink-3);
			font-size: 13px;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
		.site-row {
			border: 0;
			border-bottom: 1px solid var(--rule);
			background: var(--paper);
			color: var(--ink);
			cursor: pointer;
			min-height: 64px;
			padding: 10px 14px;
			text-align: left;
			width: 100%;
		}
		.site-row:last-child { border-bottom: 0; }
		.site-row:hover { background: color-mix(in oklab, var(--brand) 5%, var(--paper)); }
		.site-row:focus-visible {
			outline: 2px solid color-mix(in oklab, var(--brand) 55%, transparent);
			outline-offset: -2px;
		}
		.site-row.selected { background: color-mix(in oklab, var(--brand) 10%, var(--paper)); }
		.site-row input[type="checkbox"],
		.sites__head input[type="checkbox"] { margin: 0; accent-color: var(--brand); }
		.site-row__main {
			display: flex;
			align-items: center;
			gap: 12px;
			min-width: 0;
		}
		.site-thumb {
			width: 42px;
			height: 42px;
			border: 1px solid var(--rule);
			border-radius: var(--radius);
			background: linear-gradient(135deg, color-mix(in oklab, var(--brand) 45%, var(--paper-3)), var(--paper-3));
			flex: none;
			object-fit: cover;
		}
		.site-thumb.placeholder {
			display: grid;
			place-items: center;
			color: var(--ink-3);
		}
		.site-info { min-width: 0; }
		.site-info .url {
			color: var(--ink);
			font-size: 14px;
			font-weight: 650;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
		.badges {
			color: var(--ink-3);
			font-size: 12px;
			gap: 6px;
			margin-top: 4px;
		}
		.badge {
			background: var(--paper-2);
			border-color: var(--rule);
			color: var(--ink-2);
			font-size: 10px;
			min-height: 20px;
			padding: 4px 7px;
		}
		.badge.production {
			background: color-mix(in oklab, var(--ok) 11%, var(--paper));
			color: color-mix(in oklab, var(--ok) 65%, var(--ink));
		}
		.badge.staging {
			background: color-mix(in oklab, var(--warn) 13%, var(--paper));
			color: color-mix(in oklab, var(--warn) 58%, var(--ink));
		}
		.metric {
			display: grid;
			gap: 2px;
			min-width: 0;
		}
		.metric .label {
			color: var(--ink-3);
			font-family: var(--mono);
			font-size: 10px;
			letter-spacing: 0;
			text-transform: uppercase;
		}
		.metric .val {
			color: var(--ink);
			font-size: 13px;
			font-weight: 650;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
		.status-cell {
			display: inline-flex;
			align-items: center;
			gap: 7px;
			color: var(--ink-2);
			font-size: 13px;
			white-space: nowrap;
		}
		.row-actions {
			display: flex;
			align-items: center;
			justify-content: flex-end;
			gap: 6px;
			min-width: 0;
		}
		.row-action {
			width: 30px;
			height: 30px;
			background: transparent;
		}
		.sites__foot {
			background: var(--paper-2);
			border-top: 1px solid var(--rule);
			color: var(--ink-3);
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 12px;
			padding: 10px 14px;
		}
		.pagination-foot {
			border: 1px solid var(--rule);
			border-radius: var(--radius);
			background: var(--paper-2);
			color: var(--ink-3);
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 12px;
			margin-top: 10px;
			padding: 10px 12px;
		}
		.pager {
			display: inline-flex;
			gap: 6px;
		}
		.pager button {
			border: 1px solid var(--rule);
			border-radius: var(--radius-sm);
			background: var(--paper);
			color: var(--ink-2);
			min-width: 34px;
			height: 30px;
		}
		.pager button:disabled { opacity: .45; cursor: not-allowed; }
		.grid-sites {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
			gap: 14px;
		}
		.grid-card {
			border: 1px solid var(--rule);
			border-radius: var(--radius);
			background: var(--paper);
			overflow: hidden;
			text-align: left;
			cursor: pointer;
			transition: border-color .15s ease, transform .15s ease;
		}
		.grid-card:hover {
			border-color: color-mix(in oklab, var(--brand) 45%, var(--rule));
			transform: translateY(-1px);
		}
		.grid-preview {
			aspect-ratio: 16 / 10;
			background: var(--paper-3);
			position: relative;
		}
		.grid-preview img { width: 100%; height: 100%; object-fit: cover; }
		.grid-preview .placeholder {
			position: absolute;
			inset: 0;
			display: grid;
			place-items: center;
			color: var(--ink-3);
		}
		.grid-card__body {
			padding: 12px;
		}
		.grid-card__title {
			color: var(--ink);
			font-weight: 650;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
		.directory {
			background: var(--paper);
			border: 1px solid var(--rule);
			border-radius: var(--radius);
			overflow: hidden;
		}
		.directory-row {
			display: grid;
			grid-template-columns: minmax(180px, 1.4fr) minmax(150px, 1fr) minmax(120px, .8fr) 42px;
			gap: 14px;
			align-items: center;
			border-bottom: 1px solid var(--rule);
			padding: 13px 14px;
		}
		.directory-row:last-child { border-bottom: 0; }
		.directory-row.header {
			background: var(--paper-2);
			color: var(--ink-3);
			font-family: var(--mono);
			font-size: 11px;
			text-transform: uppercase;
		}
		.empty-state,
		.loading-state,
		.error-state {
			background: var(--paper);
			border: 1px solid var(--rule);
			border-radius: var(--radius);
			color: var(--ink-2);
			display: grid;
			gap: 12px;
			justify-items: center;
			padding: 44px 22px;
			text-align: center;
		}
		.login-shell {
			min-height: 100vh;
			display: grid;
			place-items: center;
			padding: 24px;
			background:
				linear-gradient(180deg, color-mix(in oklab, var(--brand) 8%, var(--paper-2)), var(--paper-2) 45%),
				var(--paper-2);
		}
		.login-panel,
		.modal-card {
			width: min(100%, 420px);
			background: var(--paper);
			border: 1px solid var(--rule);
			border-radius: var(--radius);
			box-shadow: var(--shadow);
			padding: 22px;
		}
		.login-panel h1 {
			font-size: 24px;
			margin-bottom: 4px;
		}
		.login-panel .sub {
			color: var(--ink-2);
			margin: 0 0 18px;
		}
		.form-grid {
			display: grid;
			gap: 12px;
		}
		.form-field {
			display: grid;
			gap: 6px;
		}
		.form-field label {
			color: var(--ink-3);
			font-family: var(--mono);
			font-size: 11px;
			text-transform: uppercase;
		}
		.form-field input,
		.form-field select,
		.form-field textarea {
			border: 1px solid var(--rule);
			border-radius: var(--radius-sm);
			background: var(--paper-2);
			min-height: 38px;
			padding: 8px 10px;
		}
		.form-field textarea { min-height: 88px; resize: vertical; }
		.alert {
			border-radius: var(--radius-sm);
			border: 1px solid var(--rule);
			padding: 10px 12px;
		}
		.alert.error {
			background: color-mix(in oklab, var(--bad) 10%, var(--paper));
			border-color: color-mix(in oklab, var(--bad) 30%, var(--rule));
			color: var(--bad);
		}
		.alert.info {
			background: color-mix(in oklab, var(--brand) 10%, var(--paper));
			border-color: color-mix(in oklab, var(--brand) 30%, var(--rule));
			color: var(--brand-ink);
		}
		.bulk-bar {
			position: fixed;
			left: 50%;
			bottom: 18px;
			z-index: 70;
			transform: translateX(-50%) translateY(120%);
			opacity: 0;
			pointer-events: none;
			background: var(--ink);
			color: var(--paper);
			border-radius: var(--radius);
			box-shadow: var(--shadow);
			display: flex;
			align-items: center;
			gap: 12px;
			padding: 10px 12px;
			transition: transform .18s ease, opacity .18s ease;
		}
		.bulk-bar.visible {
			opacity: 1;
			pointer-events: auto;
			transform: translateX(-50%) translateY(0);
		}
		.bulk-bar .count { background: color-mix(in oklab, white 14%, transparent); color: var(--paper); }
		.bulk-bar button {
			border: 1px solid color-mix(in oklab, white 18%, transparent);
			border-radius: var(--radius-sm);
			background: transparent;
			color: var(--paper);
			min-height: 30px;
			padding: 6px 10px;
		}
		.drawer-backdrop,
		.modal-backdrop {
			position: fixed;
			inset: 0;
			background: rgb(15 23 42 / .35);
			z-index: 80;
			opacity: 0;
			pointer-events: none;
			transition: opacity .16s ease;
		}
		.drawer-backdrop.open,
		.modal-backdrop.open {
			opacity: 1;
			pointer-events: auto;
		}
		.drawer {
			position: fixed;
			top: 0;
			right: 0;
			z-index: 90;
			width: min(540px, 100vw);
			height: 100vh;
			background: var(--paper);
			border-left: 1px solid var(--rule);
			box-shadow: var(--shadow);
			display: flex;
			flex-direction: column;
			transform: translateX(100%);
			transition: transform .18s ease;
		}
		.drawer.open { transform: translateX(0); }
		.drawer__head {
			border-bottom: 1px solid var(--rule);
			display: grid;
			grid-template-columns: 84px minmax(0, 1fr) 34px;
			gap: 14px;
			align-items: center;
			padding: 16px;
		}
		.drawer__preview {
			width: 84px;
			height: 60px;
			border: 1px solid var(--rule);
			border-radius: var(--radius);
			background: var(--paper-3);
			object-fit: cover;
		}
		.drawer h2 {
			font-size: 20px;
			line-height: 1.15;
			margin: 0 0 5px;
			overflow-wrap: anywhere;
		}
		.drawer__tabs {
			border-bottom: 1px solid var(--rule);
			display: flex;
			gap: 4px;
			overflow-x: auto;
			padding: 8px 14px 0;
		}
		.drawer__tabs button {
			border: 0;
			border-bottom: 2px solid transparent;
			background: transparent;
			color: var(--ink-2);
			font-size: 13px;
			padding: 9px 10px;
			white-space: nowrap;
		}
		.drawer__tabs button.active {
			border-bottom-color: var(--brand);
			color: var(--ink);
		}
		.drawer__body {
			flex: 1;
			overflow-y: auto;
			padding: 16px;
		}
		.kv-grid {
			display: grid;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 10px;
			margin-bottom: 18px;
		}
		.kv {
			border: 1px solid var(--rule);
			border-radius: var(--radius);
			padding: 12px;
		}
		.kv .k {
			color: var(--ink-3);
			font-family: var(--mono);
			font-size: 10px;
			text-transform: uppercase;
		}
		.kv .v {
			color: var(--ink);
			font-size: 20px;
			font-weight: 650;
			margin-top: 4px;
			overflow-wrap: anywhere;
		}
		.section-title {
			color: var(--ink-3);
			font-family: var(--mono);
			font-size: 11px;
			margin: 18px 0 8px;
			text-transform: uppercase;
		}
		.activity {
			border: 1px solid var(--rule);
			border-radius: var(--radius);
			overflow: hidden;
		}
		.activity__row {
			display: grid;
			grid-template-columns: 10px minmax(0, 1fr) auto;
			gap: 10px;
			align-items: center;
			border-bottom: 1px solid var(--rule);
			padding: 10px 12px;
		}
		.activity__row:last-child { border-bottom: 0; }
		.activity__row span:nth-child(2) {
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
		.activity__row time {
			color: var(--ink-3);
			font-family: var(--mono);
			font-size: 11px;
		}
		.drawer__cta {
			border-top: 1px solid var(--rule);
			display: flex;
			flex-wrap: wrap;
			gap: 8px;
			padding: 14px 16px;
		}
		.modal-shell {
			position: fixed;
			inset: 0;
			z-index: 95;
			display: none;
			place-items: center;
			padding: 18px;
		}
		.modal-shell.open { display: grid; }
		.modal-card {
			width: min(100%, 640px);
			max-height: 88vh;
			overflow-y: auto;
		}
		.modal-head {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 12px;
			margin-bottom: 16px;
		}
		.modal-head h2 { margin: 0; font-size: 20px; }
		.search-modal {
			width: min(100%, 860px);
			padding: 0;
			overflow: hidden;
		}
		.search-modal__bar {
			border-bottom: 1px solid var(--rule);
			display: grid;
			grid-template-columns: 20px minmax(0, 1fr) auto;
			gap: 12px;
			align-items: center;
			padding: 16px;
		}
		.search-modal__bar svg {
			color: var(--ink-3);
		}
		.search-modal__bar input {
			border: 0;
			background: transparent;
			color: var(--ink);
			font-size: 19px;
			outline: none;
			width: 100%;
		}
		.search-modal__bar kbd {
			border: 1px solid var(--rule);
			border-radius: 5px;
			color: var(--ink-3);
			font-family: var(--mono);
			font-size: 10px;
			padding: 4px 6px;
		}
		.search-modal__scopes {
			border-bottom: 1px solid var(--rule);
			display: flex;
			gap: 6px;
			overflow-x: auto;
			padding: 10px 16px;
		}
		.search-modal__scopes button {
			border: 1px solid var(--rule);
			border-radius: 999px;
			background: var(--paper);
			color: var(--ink-2);
			font-size: 12px;
			min-height: 30px;
			padding: 6px 10px;
			white-space: nowrap;
		}
		.search-modal__scopes button.active {
			background: var(--ink);
			border-color: var(--ink);
			color: var(--paper);
		}
		.search-modal__body {
			max-height: min(66vh, 620px);
			overflow: auto;
			padding: 10px;
		}
		.search-group {
			display: grid;
			gap: 5px;
			margin-bottom: 12px;
		}
		.search-group__title {
			color: var(--ink-3);
			font-family: var(--mono);
			font-size: 10px;
			padding: 4px 7px;
			text-transform: uppercase;
		}
		.search-result {
			border: 1px solid transparent;
			border-radius: var(--radius);
			display: grid;
			grid-template-columns: 34px minmax(0, 1fr) auto;
			gap: 10px;
			align-items: center;
			color: inherit;
			padding: 9px 10px;
			text-decoration: none;
		}
		.search-result:hover,
		.search-result.active {
			background: var(--paper-2);
			border-color: var(--rule);
		}
		.search-result__icon {
			border: 1px solid var(--rule);
			border-radius: 8px;
			display: grid;
			place-items: center;
			height: 34px;
			width: 34px;
			color: var(--ink-2);
		}
		.search-result strong {
			color: var(--ink);
			display: block;
			font-size: 14px;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
		.search-result small {
			color: var(--ink-3);
			display: block;
			font-size: 12px;
			margin-top: 2px;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
		.search-result__type {
			color: var(--ink-3);
			font-family: var(--mono);
			font-size: 10px;
			text-transform: uppercase;
		}
		.search-modal__foot {
			border-top: 1px solid var(--rule);
			color: var(--ink-3);
			display: flex;
			flex-wrap: wrap;
			gap: 8px 14px;
			font-size: 12px;
			padding: 10px 16px;
		}
		.captures-modal {
			display: grid;
			grid-template-rows: auto minmax(0, 1fr);
			height: min(92vh, 900px);
			overflow: hidden;
			padding: 0;
			width: min(100%, 1180px);
		}
		.captures-modal .modal-head {
			border-bottom: 1px solid var(--rule);
			margin: 0;
			padding: 16px 18px;
		}
		.captures-content {
			display: grid;
			grid-template-rows: auto minmax(0, 1fr);
			min-height: 0;
		}
		.captures-toolbar {
			align-items: end;
			border-bottom: 1px solid var(--rule);
			display: flex;
			flex-wrap: wrap;
			gap: 10px;
			justify-content: space-between;
			padding: 12px 18px;
		}
		.captures-controls,
		.captures-actions {
			align-items: end;
			display: flex;
			flex-wrap: wrap;
			gap: 8px;
		}
		.captures-control {
			display: grid;
			gap: 4px;
			min-width: 180px;
		}
		.captures-control label {
			color: var(--ink-3);
			font-family: var(--mono);
			font-size: 10px;
			text-transform: uppercase;
		}
		.captures-control select,
		.capture-page-row input,
		.capture-auth-grid input {
			border: 1px solid var(--rule);
			border-radius: var(--radius-sm);
			background: var(--paper);
			color: var(--ink);
			min-height: 34px;
			padding: 7px 9px;
		}
		.captures-body {
			background: var(--paper-2);
			display: grid;
			gap: 14px;
			overflow: auto;
			padding: 16px 18px 22px;
		}
		.capture-config-panel {
			background: var(--paper);
			border: 1px solid var(--rule);
			border-radius: var(--radius);
			display: grid;
			gap: 12px;
			padding: 14px;
		}
		.capture-pages-list {
			display: grid;
			gap: 8px;
		}
		.capture-page-row {
			display: grid;
			gap: 8px;
			grid-template-columns: minmax(0, 1fr) auto;
		}
		.capture-auth-grid {
			display: grid;
			gap: 10px;
			grid-template-columns: repeat(2, minmax(0, 1fr));
		}
		.capture-auth-grid label {
			color: var(--ink-3);
			display: grid;
			font-family: var(--mono);
			font-size: 10px;
			gap: 4px;
			text-transform: uppercase;
		}
		.capture-viewer {
			display: grid;
			gap: 10px;
			justify-items: center;
			min-width: 0;
		}
		.capture-viewer__meta {
			color: var(--ink-3);
			font-family: var(--mono);
			font-size: 11px;
			overflow-wrap: anywhere;
			text-align: center;
		}
		.capture-image-frame {
			background: var(--paper);
			border: 1px solid var(--rule);
			border-radius: var(--radius);
			box-shadow: var(--shadow);
			max-width: 100%;
			overflow: auto;
			padding: 8px;
		}
		.capture-image-frame img {
			display: block;
			height: auto;
			max-width: 100%;
		}
		.toast {
			position: fixed;
			left: 50%;
			bottom: 22px;
			z-index: 120;
			transform: translateX(-50%) translateY(20px);
			opacity: 0;
			pointer-events: none;
			border-radius: var(--radius);
			background: var(--ink);
			color: var(--paper);
			box-shadow: var(--shadow);
			padding: 11px 14px;
			transition: opacity .16s ease, transform .16s ease;
		}
		.toast.visible {
			opacity: 1;
			transform: translateX(-50%) translateY(0);
		}
		.detail-shell {
			display: grid;
			gap: 14px;
		}
		.detail-toolbar {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 12px;
			flex-wrap: wrap;
		}
		.detail-actions {
			display: flex;
			align-items: center;
			gap: 8px;
			flex-wrap: wrap;
		}
		.detail-tabs {
			display: flex;
			gap: 4px;
			overflow-x: auto;
			border-bottom: 1px solid var(--rule);
			background: var(--paper);
			border: 1px solid var(--rule);
			border-radius: var(--radius) var(--radius) 0 0;
			padding: 0 10px;
		}
		.detail-tabs button {
			border: 0;
			border-bottom: 2px solid transparent;
			background: transparent;
			color: var(--ink-2);
			font-size: 13px;
			min-height: 42px;
			padding: 10px 11px 8px;
			white-space: nowrap;
		}
		.detail-tabs button.active {
			border-bottom-color: var(--brand);
			color: var(--ink);
			font-weight: 650;
		}
		.detail-panel {
			background: var(--paper);
			border: 1px solid var(--rule);
			border-top: 0;
			border-radius: 0 0 var(--radius) var(--radius);
			padding: 16px;
		}
		.detail-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
			gap: 10px;
		}
		.detail-card {
			border: 1px solid var(--rule);
			border-radius: var(--radius);
			background: var(--paper);
			padding: 13px;
			min-width: 0;
		}
		.detail-card .k {
			color: var(--ink-3);
			font-family: var(--mono);
			font-size: 10px;
			text-transform: uppercase;
		}
		.detail-card .v {
			color: var(--ink);
			font-size: 17px;
			font-weight: 650;
			margin-top: 5px;
			overflow-wrap: anywhere;
		}
			.detail-card .sub {
				color: var(--ink-3);
				font-size: 12px;
				margin-top: 3px;
				overflow-wrap: anywhere;
			}
			.site-detail-shell {
				display: grid;
				gap: 18px;
			}
			.site-crumb {
				align-items: center;
				color: var(--ink-3);
				display: flex;
				flex-wrap: wrap;
				font-family: var(--mono);
				font-size: 11px;
				gap: 8px;
			}
			.site-crumb button {
				background: transparent;
				border: 0;
				color: var(--brand-ink);
				font: inherit;
				padding: 0;
			}
			.site-crumb__sep { opacity: .45; }
			.site-hero {
				align-items: center;
				background: var(--paper);
				border: 1px solid var(--rule);
				border-radius: var(--radius);
				display: grid;
				gap: 18px;
				grid-template-columns: 92px minmax(0, 1fr) auto;
				padding: 18px;
			}
			.site-hero__thumb {
				aspect-ratio: 4 / 3;
				background: linear-gradient(135deg, var(--paper-3), var(--paper-2));
				border: 1px solid var(--rule);
				border-radius: var(--radius-sm);
				display: grid;
				overflow: hidden;
				place-items: center;
			}
			.site-hero__thumb img {
				height: 100%;
				object-fit: cover;
				width: 100%;
			}
			.site-hero__thumb svg {
				color: var(--ink-3);
				height: 30px;
				width: 30px;
			}
			.site-hero__main {
				display: grid;
				gap: 5px;
				min-width: 0;
			}
			.site-hero__title {
				align-items: center;
				display: flex;
				flex-wrap: wrap;
				gap: 10px;
				min-width: 0;
			}
			.site-hero h1 {
				color: var(--ink);
				font-size: 26px;
				font-weight: 650;
				line-height: 1.15;
				margin: 0;
				overflow-wrap: anywhere;
			}
			.site-hero__meta {
				align-items: center;
				color: var(--ink-3);
				display: flex;
				flex-wrap: wrap;
				font-family: var(--mono);
				font-size: 12px;
				gap: 7px;
				overflow-wrap: anywhere;
			}
			.site-hero__meta svg {
				flex: none;
				height: 13px;
				width: 13px;
			}
			.site-hero__meta button {
				background: transparent;
				border: 0;
				color: inherit;
				font: inherit;
				padding: 0;
				text-align: left;
			}
			.site-hero__meta button:hover { color: var(--brand-ink); }
			.site-env-badge {
				background: var(--paper-2);
				border: 1px solid var(--rule);
				border-radius: 999px;
				color: var(--ink-2);
				font-family: var(--mono);
				font-size: 10px;
				font-weight: 600;
				padding: 4px 8px;
				text-transform: uppercase;
			}
			.site-env-badge.production {
				background: color-mix(in oklab, var(--brand) 13%, var(--paper));
				border-color: color-mix(in oklab, var(--brand) 42%, var(--rule));
				color: var(--brand-ink);
			}
			.site-env-badge.staging {
				background: color-mix(in oklab, var(--warn) 14%, var(--paper));
				border-color: color-mix(in oklab, var(--warn) 44%, var(--rule));
				color: oklch(0.46 0.12 70);
			}
			.site-hero__actions,
			.site-detail-toolbar,
			.site-toolbar-actions {
				align-items: center;
				display: flex;
				flex-wrap: wrap;
				gap: 8px;
			}
			.site-action-btn {
				align-items: center;
				background: var(--paper);
				border: 1px solid var(--rule);
				border-radius: var(--radius-sm);
				color: var(--ink);
				display: inline-flex;
				font-size: 13px;
				font-weight: 550;
				gap: 7px;
				min-height: 36px;
				padding: 8px 12px;
			}
			.site-action-btn:hover {
				border-color: color-mix(in oklab, var(--ink) 26%, var(--rule));
			}
			.site-action-btn.primary {
				background: var(--ink);
				border-color: var(--ink);
				color: var(--paper);
			}
			.site-action-btn.danger {
				border-color: color-mix(in oklab, var(--bad) 32%, var(--rule));
				color: var(--bad);
			}
			.site-action-btn svg {
				flex: none;
				height: 14px;
				width: 14px;
			}
			.site-detail-toolbar {
				background: var(--paper);
				border: 1px solid var(--rule);
				border-radius: var(--radius);
				justify-content: space-between;
				padding: 12px 14px;
			}
			.site-env-switch {
				background: var(--paper-2);
				border: 1px solid var(--rule);
				border-radius: var(--radius-sm);
				display: inline-flex;
				gap: 3px;
				padding: 3px;
			}
			.site-env-switch button {
				background: transparent;
				border: 0;
				border-radius: 5px;
				color: var(--ink-2);
				font-family: var(--mono);
				font-size: 11px;
				min-height: 30px;
				padding: 6px 12px;
				text-transform: uppercase;
			}
			.site-env-switch button.active {
				background: var(--paper);
				box-shadow: 0 1px 0 rgb(20 28 45 / .06);
				color: var(--ink);
			}
			.site-detail-body {
				align-items: start;
				display: grid;
				gap: 22px;
				grid-template-columns: 208px minmax(0, 1fr);
			}
			.site-detail-tabs {
				background: var(--paper);
				border: 1px solid var(--rule);
				border-radius: var(--radius);
				display: grid;
				gap: 2px;
				padding: 8px;
				position: sticky;
				top: 82px;
			}
			.site-detail-tabs button {
				align-items: center;
				background: transparent;
				border: 0;
				border-radius: var(--radius-sm);
				color: var(--ink-2);
				display: grid;
				font-size: 13px;
				font-weight: 550;
				gap: 10px;
				grid-template-columns: 16px minmax(0, 1fr) auto;
				min-height: 38px;
				padding: 9px 10px;
				text-align: left;
				width: 100%;
			}
			.site-detail-tabs button:hover {
				background: var(--paper-2);
				color: var(--ink);
			}
			.site-detail-tabs button.active {
				background: color-mix(in oklab, var(--brand) 12%, var(--paper));
				color: var(--brand-ink);
			}
			.site-detail-tabs svg {
				height: 16px;
				width: 16px;
			}
			.site-tab-count {
				background: var(--paper-2);
				border-radius: 4px;
				color: var(--ink-3);
				font-family: var(--mono);
				font-size: 10px;
				padding: 2px 6px;
			}
			.site-detail-tabs button.active .site-tab-count {
				background: color-mix(in oklab, var(--brand) 20%, var(--paper));
				color: var(--brand-ink);
			}
			.site-detail-panel {
				display: grid;
				gap: 18px;
				min-width: 0;
			}
			.site-section {
				background: var(--paper);
				border: 1px solid var(--rule);
				border-radius: var(--radius);
				padding: 22px;
			}
			.site-section__head {
				align-items: center;
				display: flex;
				gap: 16px;
				justify-content: space-between;
				margin-bottom: 20px;
			}
			.site-section__sub,
			.site-subhead {
				color: var(--ink-3);
				font-family: var(--mono);
				font-size: 11px;
				font-weight: 600;
				margin-bottom: 4px;
				text-transform: uppercase;
			}
			.site-section h2 {
				font-size: 20px;
				line-height: 1.2;
				margin: 0;
			}
			.site-overview-grid {
				display: grid;
				gap: 28px;
				grid-template-columns: minmax(0, 1fr) minmax(340px, .9fr);
			}
			.site-overview-col {
				display: grid;
				gap: 18px;
				min-width: 0;
			}
			.site-preview-frame {
				aspect-ratio: 16 / 10;
				background: linear-gradient(145deg, var(--paper-3), var(--paper-2));
				border: 1px solid var(--rule);
				border-radius: var(--radius);
				display: grid;
				overflow: hidden;
				place-items: center;
				position: relative;
			}
			button.site-preview-frame {
				color: inherit;
				cursor: pointer;
				padding: 0;
				text-align: inherit;
				width: 100%;
			}
			button.site-preview-frame:hover {
				border-color: color-mix(in oklab, var(--brand) 38%, var(--rule));
			}
			.site-preview-frame img {
				height: 100%;
				object-fit: cover;
				width: 100%;
			}
			.site-preview-frame__overlay {
				align-items: center;
				background: linear-gradient(180deg, transparent, rgb(15 23 42 / .72));
				color: white;
				display: flex;
				font-size: 13px;
				font-weight: 650;
				gap: 7px;
				inset: auto 0 0;
				justify-content: space-between;
				opacity: 0;
				padding: 12px;
				position: absolute;
				transition: opacity .14s ease;
			}
			button.site-preview-frame:hover .site-preview-frame__overlay,
			button.site-preview-frame:focus-visible .site-preview-frame__overlay {
				opacity: 1;
			}
			.site-preview-frame__empty {
				color: var(--ink-3);
				display: grid;
				gap: 8px;
				justify-items: center;
				padding: 24px;
				text-align: center;
			}
			.site-preview-frame__empty svg {
				height: 30px;
				width: 30px;
			}
			.site-kv-list {
				display: grid;
			}
			.site-kv {
				align-items: center;
				border-bottom: 1px solid var(--rule);
				display: grid;
				gap: 12px;
				grid-template-columns: minmax(0, 1fr) auto;
				padding: 11px 0;
			}
			.site-kv:last-child { border-bottom: 0; }
			.site-kv__label {
				color: var(--ink);
				font-size: 13px;
				font-weight: 550;
			}
			.site-kv__value {
				color: var(--ink-3);
				font-family: var(--mono);
				font-size: 12px;
				margin-top: 2px;
				overflow-wrap: anywhere;
			}
			.site-icon-btn {
				align-items: center;
				background: transparent;
				border: 0;
				border-radius: var(--radius-sm);
				color: var(--ink-3);
				display: inline-grid;
				height: 30px;
				justify-items: center;
				place-items: center;
				width: 30px;
			}
			.site-icon-btn:hover {
				background: var(--paper-2);
				color: var(--ink);
			}
			.site-icon-btn svg {
				height: 14px;
				width: 14px;
			}
			.site-secret-section {
				background: var(--paper-2);
				border: 1px solid var(--rule);
				border-radius: var(--radius);
				padding: 16px 18px;
			}
			.site-secret-section__head {
				align-items: center;
				display: flex;
				justify-content: space-between;
				margin-bottom: 10px;
				gap: 12px;
			}
			.site-secret-title {
				align-items: center;
				color: var(--ink);
				display: flex;
				font-size: 14px;
				font-weight: 650;
				gap: 8px;
			}
			.site-secret-title svg {
				color: var(--ink-3);
				height: 14px;
				width: 14px;
			}
			.site-secret-toggle {
				align-items: center;
				background: var(--paper);
				border: 1px solid var(--rule);
				border-radius: var(--radius-sm);
				color: var(--ink-2);
				display: inline-flex;
				font-family: var(--mono);
				font-size: 10px;
				gap: 6px;
				min-height: 28px;
				padding: 5px 9px;
				text-transform: uppercase;
			}
			.site-secret-row {
				align-items: center;
				border-top: 1px solid var(--rule);
				display: grid;
				gap: 12px;
				grid-template-columns: 112px minmax(0, 1fr) auto;
				padding: 8px 0;
			}
			.site-secret-row:first-of-type { border-top: 0; }
			.site-secret-row__label {
				color: var(--ink-2);
				font-size: 12px;
			}
			.site-secret-row__value {
				color: var(--ink);
				font-family: var(--mono);
				font-size: 12px;
				overflow-wrap: anywhere;
			}
			.site-secret-row__value.masked {
				color: var(--ink-3);
				letter-spacing: 0;
			}
			.site-shared-grid,
			.site-action-strip {
				display: flex;
				flex-wrap: wrap;
				gap: 8px;
			}
			.site-shared-pill {
				align-items: center;
				background: var(--paper);
				border: 1px solid var(--rule);
				border-radius: var(--radius);
				display: flex;
				gap: 12px;
				justify-content: space-between;
				min-width: min(100%, 220px);
				padding: 12px 14px;
			}
			.site-shared-pill strong {
				display: block;
				font-size: 14px;
			}
			.site-shared-pill span {
				color: var(--ink-3);
				display: block;
				font-family: var(--mono);
				font-size: 11px;
				margin-top: 2px;
			}
		.feature-bar {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 10px;
			flex-wrap: wrap;
			margin-bottom: 12px;
		}
		.feature-controls {
			display: flex;
			align-items: center;
			gap: 8px;
			flex-wrap: wrap;
		}
		.feature-controls input,
		.feature-controls select {
			border: 1px solid var(--rule);
			border-radius: var(--radius-sm);
			background: var(--paper);
			color: var(--ink);
			min-height: 32px;
			padding: 6px 9px;
		}
		.stats-chart {
			border: 1px solid var(--rule);
			border-radius: var(--radius);
			background: var(--paper);
			margin-top: 14px;
			padding: 12px;
		}
		.stats-chart svg {
			display: block;
			width: 100%;
			height: auto;
			overflow: visible;
		}
		.stats-legend {
			color: var(--ink-3);
			display: flex;
			gap: 12px;
			font-size: 12px;
			margin-top: 8px;
		}
		.stats-key {
			display: inline-flex;
			align-items: center;
			gap: 6px;
		}
		.stats-key::before {
			content: "";
			border-radius: 999px;
			display: inline-block;
			height: 7px;
			width: 7px;
			background: var(--brand);
		}
		.stats-key.pageviews::before { background: var(--accent); }
		.stats-toolbar {
			align-items: flex-end;
			margin-bottom: 16px;
		}
		.stats-controls {
			align-items: end;
			display: flex;
			flex-wrap: wrap;
			gap: 10px;
		}
		.stats-control {
			display: grid;
			gap: 4px;
			min-width: 132px;
		}
		.stats-control label {
			color: var(--ink-3);
			font-family: var(--mono);
			font-size: 10px;
			text-transform: uppercase;
		}
		.stats-control select,
		.stats-control input {
			border: 1px solid var(--rule);
			border-radius: var(--radius-sm);
			background: var(--paper);
			color: var(--ink);
			min-height: 34px;
			padding: 7px 9px;
		}
		.stats-timeframes {
			align-items: center;
			background: var(--paper-2);
			border: 1px solid var(--rule);
			border-radius: var(--radius-sm);
			display: inline-flex;
			gap: 2px;
			padding: 2px;
		}
		.stats-timeframes button {
			border: 0;
			border-radius: calc(var(--radius-sm) - 2px);
			background: transparent;
			color: var(--ink-2);
			font-size: 12px;
			font-weight: 650;
			min-height: 30px;
			padding: 5px 9px;
		}
		.stats-timeframes button.active {
			background: var(--paper);
			box-shadow: 0 1px 0 rgb(0 0 0 / 0.05);
			color: var(--ink);
		}
		.stats-kpi-grid {
			display: grid;
			gap: 8px;
			grid-template-columns: repeat(4, minmax(0, 1fr));
			margin: 14px 0 8px;
		}
		.stats-kpi {
			min-width: 0;
			text-align: center;
		}
		.stats-kpi .k {
			color: var(--ink-2);
			font-size: 12px;
			font-weight: 650;
			text-transform: uppercase;
		}
		.stats-kpi .v {
			color: var(--ink);
			font-size: clamp(28px, 4vw, 52px);
			font-weight: 250;
			letter-spacing: 0;
			line-height: 1.08;
			margin-top: 8px;
			overflow-wrap: anywhere;
		}
		.stats-chart {
			border: 0;
			background: transparent;
			margin: 18px 0 8px;
			padding: 0;
		}
		.stats-chart__legend {
			align-items: center;
			color: var(--ink-3);
			display: flex;
			gap: 16px;
			font-size: 12px;
			justify-content: center;
			margin-bottom: 6px;
		}
		.stats-chart__plot {
			position: relative;
		}
		.stats-chart svg {
			display: block;
			height: auto;
			overflow: visible;
			width: 100%;
		}
		.stats-axis-label {
			fill: var(--ink-3);
			font-size: 11px;
		}
		.stats-hover-point {
			background: transparent;
			border: 0;
			bottom: 0;
			cursor: crosshair;
			padding: 0;
			position: absolute;
			top: 0;
		}
		.stats-hover-point::before {
			background: var(--paper);
			border: 2px solid var(--brand);
			border-radius: 999px;
			content: "";
			height: 11px;
			left: var(--point-left, 50%);
			opacity: 0;
			position: absolute;
			top: var(--point-top, 50%);
			transform: translate(-50%, -50%);
			transition: opacity .12s ease;
			width: 11px;
		}
		.stats-hover-point:hover::before,
		.stats-hover-point:focus::before,
		.stats-hover-point:focus-visible::before {
			opacity: 1;
		}
		.stats-tooltip {
			background: color-mix(in oklab, var(--ink) 92%, black);
			border-radius: var(--radius-sm);
			box-shadow: var(--shadow);
			color: var(--paper);
			display: grid;
			font-size: 13px;
			gap: 6px;
			left: var(--point-left, 50%);
			min-width: 190px;
			opacity: 0;
			padding: 10px 11px;
			pointer-events: none;
			position: absolute;
			text-align: left;
			top: var(--point-top, 50%);
			transform: translateX(-50%) translateY(calc(-100% - 4px));
			transition: opacity .12s ease, transform .12s ease;
			z-index: 5;
		}
		.stats-tooltip strong {
			color: var(--paper);
			font-size: 14px;
		}
		.stats-tooltip span {
			align-items: center;
			display: flex;
			gap: 7px;
			white-space: nowrap;
		}
		.stats-tooltip span::before {
			border: 2px solid currentColor;
			content: "";
			display: inline-block;
			height: 10px;
			width: 10px;
		}
		.stats-tooltip span.pageviews::before {
			color: var(--ink-3);
		}
		.stats-tooltip span.visits::before {
			color: var(--brand);
		}
		.stats-hover-point.edge-left .stats-tooltip {
			transform: translateX(-12%) translateY(calc(-100% - 4px));
		}
		.stats-hover-point.edge-right .stats-tooltip {
			left: auto;
			right: calc(100% - var(--point-left, 50%));
			transform: translateX(12%) translateY(calc(-100% - 4px));
		}
		.stats-hover-point:hover .stats-tooltip,
		.stats-hover-point:focus .stats-tooltip,
		.stats-hover-point:focus-visible .stats-tooltip {
			opacity: 1;
			transform: translateX(-50%) translateY(calc(-100% - 8px));
		}
		.stats-hover-point.edge-left:hover .stats-tooltip,
		.stats-hover-point.edge-left:focus .stats-tooltip,
		.stats-hover-point.edge-left:focus-visible .stats-tooltip {
			transform: translateX(-12%) translateY(calc(-100% - 8px));
		}
		.stats-hover-point.edge-right:hover .stats-tooltip,
		.stats-hover-point.edge-right:focus .stats-tooltip,
		.stats-hover-point.edge-right:focus-visible .stats-tooltip {
			transform: translateX(12%) translateY(calc(-100% - 8px));
		}
		.stats-breakdown-head {
			align-items: baseline;
			display: flex;
			gap: 10px;
			justify-content: space-between;
			margin-top: 22px;
		}
		.stats-breakdown-head .section-title {
			margin: 0 0 8px;
		}
		.stats-breakdown-head span {
			color: var(--ink-3);
			font-family: var(--mono);
			font-size: 11px;
		}
		.stats-sharing {
			border-top: 1px solid var(--rule);
			display: grid;
			gap: 16px;
			grid-template-columns: minmax(0, 1.3fr) minmax(260px, .7fr);
			margin-top: 24px;
			padding-top: 20px;
		}
		.stats-sharing h3 {
			font-size: 14px;
			margin: 0 0 10px;
			text-transform: uppercase;
		}
		.stats-sharing p {
			color: var(--ink-2);
			font-size: 14px;
			line-height: 1.55;
			margin: 0;
		}
		.stats-share-chips {
			display: flex;
			flex-wrap: wrap;
			gap: 8px;
			margin-top: 14px;
		}
		.stats-share-chip {
			align-items: center;
			background: var(--paper-2);
			border: 1px solid var(--rule);
			border-radius: 999px;
			color: var(--ink-2);
			display: inline-flex;
			gap: 7px;
			min-height: 34px;
			padding: 7px 13px;
		}
		.stats-share-chip.active {
			background: var(--ink);
			border-color: var(--ink);
			color: var(--paper);
		}
		.stats-share-panel {
			background: var(--paper-2);
			border: 1px solid var(--rule);
			border-radius: var(--radius);
			display: grid;
			gap: 10px;
			min-width: 0;
			padding: 12px;
		}
		.stats-share-panel a {
			color: var(--ink);
			font-family: var(--mono);
			font-size: 12px;
			overflow-wrap: anywhere;
		}
		.stats-share-password {
			align-items: end;
			display: grid;
			gap: 8px;
			grid-template-columns: minmax(0, 1fr) auto;
		}
		.stats-share-password label {
			color: var(--ink-3);
			display: grid;
			font-family: var(--mono);
			font-size: 10px;
			gap: 4px;
			text-transform: uppercase;
		}
		.stats-share-password input {
			border: 1px solid var(--rule);
			border-radius: var(--radius-sm);
			background: var(--paper);
			color: var(--ink);
			min-height: 34px;
			padding: 7px 9px;
		}
		.stats-share-error {
			color: var(--bad);
			font-size: 12px;
		}
		.row-button-group {
			display: flex;
			gap: 6px;
			justify-content: flex-end;
			flex-wrap: wrap;
		}
		.row-button-group .secondary-btn,
		.row-button-group .danger-btn {
			min-height: 28px;
			padding: 5px 8px;
		}
		.changed-files {
			border: 1px solid var(--rule);
			border-radius: var(--radius);
			background: var(--paper-2);
			display: grid;
			gap: 6px;
			margin: -3px 0 8px;
			padding: 10px;
		}
		.changed-file {
			border: 1px solid var(--rule);
			border-radius: var(--radius-sm);
			background: var(--paper);
			display: grid;
			grid-template-columns: minmax(0, 1fr) auto;
			gap: 8px;
			align-items: center;
			padding: 7px 9px;
		}
		.changed-file code {
			color: var(--ink-2);
			font-family: var(--mono);
			font-size: 12px;
			overflow-wrap: anywhere;
		}
		.site-users-toolbar,
		.site-logs-toolbar {
			align-items: center;
			display: flex;
			flex-wrap: wrap;
			gap: 10px;
			justify-content: space-between;
			margin-bottom: 14px;
		}
		.site-users-filters,
		.site-log-controls {
			align-items: center;
			display: flex;
			flex: 1;
			flex-wrap: wrap;
			gap: 8px;
			min-width: min(100%, 280px);
		}
		.site-users-filters .search-input,
		.site-log-controls .search-input {
			border: 1px solid var(--rule);
			border-radius: var(--radius-sm);
			max-width: 380px;
			padding: 7px 10px;
			width: min(100%, 380px);
		}
		.site-users-filters select,
		.site-log-controls select,
		.site-log-controls input {
			border: 1px solid var(--rule);
			border-radius: var(--radius-sm);
			background: var(--paper);
			color: var(--ink);
			min-height: 34px;
			padding: 7px 9px;
		}
		.user-summary-grid {
			display: grid;
			gap: 10px;
			grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
			margin-bottom: 14px;
		}
		.user-pill {
			border: 1px solid var(--rule);
			border-radius: var(--radius);
			background: var(--paper);
			padding: 12px;
		}
		.user-pill span {
			color: var(--ink-3);
			display: block;
			font-family: var(--mono);
			font-size: 10px;
			text-transform: uppercase;
		}
		.user-pill strong {
			color: var(--ink);
			display: block;
			font-size: 22px;
			margin-top: 4px;
		}
		.user-name-cell strong {
			color: var(--ink);
			display: block;
		}
		.user-name-cell small {
			color: var(--ink-3);
			display: block;
			margin-top: 2px;
		}
		.logs-grid {
			align-items: start;
			display: grid;
			gap: 16px;
			grid-template-columns: minmax(240px, 360px) minmax(0, 1fr);
		}
		.log-file-list {
			border: 1px solid var(--rule);
			border-radius: var(--radius);
			background: var(--paper);
			display: grid;
			gap: 4px;
			max-height: 520px;
			overflow: auto;
			padding: 8px;
		}
		.log-file {
			border: 1px solid transparent;
			border-radius: var(--radius-sm);
			background: transparent;
			color: var(--ink-2);
			display: grid;
			gap: 2px;
			padding: 9px 10px;
			text-align: left;
			width: 100%;
		}
		.log-file:hover,
		.log-file.active {
			background: var(--paper-2);
			border-color: var(--rule);
			color: var(--ink);
		}
		.log-file strong {
			color: inherit;
			font-family: var(--mono);
			font-size: 12px;
			overflow-wrap: anywhere;
		}
		.log-file small {
			color: var(--ink-3);
			font-size: 11px;
		}
		.log-output {
			background: oklch(0.18 0.016 255);
			border: 1px solid oklch(0.26 0.018 255);
			border-radius: var(--radius);
			color: oklch(0.86 0.012 255);
			font-family: var(--mono);
			font-size: 12px;
			line-height: 1.55;
			margin: 0;
			max-height: 620px;
			min-height: 360px;
			overflow: auto;
			padding: 14px;
			white-space: pre-wrap;
		}
		.log-panel {
			align-content: start;
			align-self: start;
			display: grid;
			gap: 12px;
			grid-auto-rows: max-content;
			min-width: 0;
		}
		.log-meta {
			color: var(--ink-3);
			font-family: var(--mono);
			font-size: 11px;
		}
		.audit-list {
			display: grid;
			gap: 8px;
		}
		.audit-card {
			align-items: center;
			border: 1px solid var(--rule);
			border-radius: var(--radius);
			background: var(--paper);
			display: grid;
			gap: 10px;
			grid-template-columns: minmax(0, 1fr) auto;
			padding: 12px;
		}
		.audit-card strong {
			color: var(--ink);
			display: block;
		}
		.audit-card small {
			color: var(--ink-3);
			display: block;
			margin-top: 2px;
		}
		.diff-output {
			max-height: 62vh;
		}
		.diff-line {
			display: block;
			min-height: 18px;
		}
		.diff-line.added { color: var(--ok); }
		.diff-line.removed { color: var(--bad); }
		.detail-table {
			width: 100%;
			border-collapse: collapse;
			background: var(--paper);
			border: 1px solid var(--rule);
			border-radius: var(--radius);
			overflow: hidden;
		}
		.detail-table th,
		.detail-table td {
			border-bottom: 1px solid var(--rule);
			padding: 10px 12px;
			text-align: left;
			vertical-align: top;
		}
		.detail-table th {
			background: var(--paper-2);
			color: var(--ink-3);
			font-family: var(--mono);
			font-size: 11px;
			text-transform: uppercase;
		}
		.detail-table tr:last-child td { border-bottom: 0; }
		.detail-table td {
			color: var(--ink-2);
			font-size: 13px;
			overflow-wrap: anywhere;
		}
		.env-switcher {
			display: flex;
			flex-wrap: wrap;
			gap: 7px;
		}
		.env-switcher button {
			border: 1px solid var(--rule);
			border-radius: 999px;
			background: var(--paper);
			color: var(--ink-2);
			font-family: var(--mono);
			font-size: 11px;
			min-height: 30px;
			padding: 7px 11px;
			text-transform: uppercase;
		}
		.env-switcher button.active {
			background: var(--ink);
			border-color: var(--ink);
			color: var(--paper);
		}
		.code-block {
			background: var(--paper-2);
			border: 1px solid var(--rule);
			border-radius: var(--radius);
			color: var(--ink-2);
			font-family: var(--mono);
			font-size: 12px;
			line-height: 1.5;
			margin: 0;
			max-height: 360px;
			overflow: auto;
			padding: 12px;
			white-space: pre-wrap;
		}
		.inline-list {
			display: flex;
			flex-wrap: wrap;
			gap: 7px;
		}
		.stack-list {
			display: grid;
			gap: 8px;
		}
		.stack-row {
			border: 1px solid var(--rule);
			border-radius: var(--radius);
			background: var(--paper);
			display: grid;
			grid-template-columns: minmax(0, 1fr) auto;
			gap: 12px;
			align-items: center;
			min-width: 0;
			padding: 14px;
			transition: background .14s ease, border-color .14s ease, box-shadow .14s ease, transform .14s ease;
		}
		.stack-row.clickable {
			cursor: pointer;
		}
		.stack-row.clickable:hover {
			background: var(--paper-2);
			border-color: color-mix(in oklab, var(--ink) 22%, var(--rule));
			box-shadow: 0 10px 24px rgb(15 23 42 / 0.07);
			transform: translateY(-1px);
		}
		.stack-row.clickable:focus-visible {
			outline: 2px solid var(--brand);
			outline-offset: 2px;
		}
		.stack-row.expanded {
			border-color: color-mix(in oklab, var(--brand) 36%, var(--rule));
			box-shadow: 0 10px 22px rgb(15 23 42 / 0.06);
		}
		.stack-row__primary {
			min-width: 0;
		}
		.stack-row__title {
			align-items: center;
			color: var(--ink);
			display: flex;
			gap: 8px;
			min-width: 0;
			overflow-wrap: anywhere;
		}
		.stack-row__title strong {
			min-width: 0;
			overflow-wrap: anywhere;
		}
		.stack-row small {
			color: var(--ink-3);
			display: block;
			margin-top: 3px;
		}
		.stack-row__hint {
			color: var(--ink-3);
			font-family: var(--mono);
			font-size: 10px;
			margin-top: 8px;
			text-transform: uppercase;
		}
		.timeline-markdown {
			color: var(--ink);
			overflow-wrap: anywhere;
		}
		.timeline-markdown p {
			margin: 0;
		}
		.timeline-markdown p + p,
		.timeline-markdown p + ul,
		.timeline-markdown p + ol,
		.timeline-markdown ul + p,
		.timeline-markdown ol + p {
			margin-top: 8px;
		}
		.timeline-markdown ul,
		.timeline-markdown ol {
			margin: 6px 0 0;
			padding-left: 18px;
		}
		.timeline-markdown li + li {
			margin-top: 3px;
		}
		.timeline-markdown a {
			color: var(--brand-ink);
			text-decoration: underline;
			text-underline-offset: 2px;
		}
		.timeline-markdown code {
			background: var(--paper-2);
			border: 1px solid var(--rule);
			border-radius: 4px;
			color: var(--ink-2);
			font-family: var(--mono);
			font-size: .92em;
			padding: 1px 4px;
		}
		.script-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
			gap: 10px;
		}
		.script-card {
			border: 1px solid var(--rule);
			border-radius: var(--radius);
			background: var(--paper);
			color: var(--ink);
			display: grid;
			gap: 5px;
			min-height: 92px;
			padding: 13px;
			text-align: left;
			width: 100%;
		}
		.script-card:hover {
			background: var(--paper-2);
			border-color: color-mix(in oklab, var(--brand) 35%, var(--rule));
		}
		.script-card strong {
			font-size: 14px;
			overflow-wrap: anywhere;
		}
		.script-card small {
			color: var(--ink-3);
			line-height: 1.35;
		}
		.terminal-region {
			position: fixed;
			inset: auto 18px 18px auto;
			z-index: 95;
			pointer-events: none;
		}
		.terminal-island {
			border: 1px solid var(--rule);
			border-radius: 999px;
			background: var(--paper);
			box-shadow: var(--shadow);
			color: var(--ink);
			display: flex;
			align-items: center;
			gap: 10px;
			min-width: min(420px, calc(100vw - 36px));
			max-width: min(620px, calc(100vw - 36px));
			padding: 9px 10px 9px 14px;
			pointer-events: auto;
		}
		.terminal-island__status {
			width: 10px;
			height: 10px;
			border-radius: 999px;
			background: var(--ok);
			box-shadow: 0 0 0 4px color-mix(in oklab, var(--ok) 16%, transparent);
			flex: 0 0 auto;
		}
		.terminal-island__status.running {
			background: var(--warn);
			box-shadow: 0 0 0 4px color-mix(in oklab, var(--warn) 18%, transparent);
		}
		.terminal-island__text {
			min-width: 0;
			flex: 1;
		}
		.terminal-island__text strong,
		.terminal-island__text small {
			display: block;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
		.terminal-island__text small {
			color: var(--ink-3);
			font-family: var(--mono);
			font-size: 11px;
		}
		.terminal-window {
			position: fixed;
			right: 18px;
			bottom: 18px;
			z-index: 96;
			width: min(1040px, calc(100vw - 36px));
			height: min(720px, calc(100vh - 36px));
			border: 1px solid color-mix(in oklab, white 12%, transparent);
			border-radius: var(--radius);
			background: oklch(0.17 0.018 245);
			box-shadow: var(--shadow);
			color: oklch(0.93 0.01 245);
			display: grid;
			grid-template-rows: auto minmax(0, 1fr);
			overflow: hidden;
			pointer-events: auto;
		}
		.terminal-window.fullscreen {
			inset: 12px;
			width: auto;
			height: auto;
		}
		.terminal-head {
			background: oklch(0.22 0.018 245);
			border-bottom: 1px solid color-mix(in oklab, white 10%, transparent);
			display: flex;
			align-items: center;
			gap: 12px;
			min-height: 44px;
			padding: 9px 12px;
		}
		.terminal-dots {
			display: inline-flex;
			gap: 7px;
		}
		.terminal-dot {
			width: 11px;
			height: 11px;
			border-radius: 999px;
			border: 0;
			padding: 0;
		}
		.terminal-dot.close { background: #ff5f56; }
		.terminal-dot.hide { background: #ffbd2e; }
		.terminal-dot.full { background: #27c93f; }
		.terminal-title {
			color: oklch(0.72 0.015 245);
			font-family: var(--mono);
			font-size: 12px;
			min-width: 0;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
			flex: 1;
			text-align: center;
		}
		.terminal-body {
			display: grid;
			grid-template-columns: 300px minmax(0, 1fr);
			min-height: 0;
		}
		.terminal-sidebar {
			background: oklch(0.20 0.018 245);
			border-right: 1px solid color-mix(in oklab, white 10%, transparent);
			display: grid;
			grid-template-rows: auto auto minmax(0, 1fr);
			gap: 10px;
			min-height: 0;
			padding: 12px;
		}
		.terminal-search,
		.terminal-command {
			border: 1px solid color-mix(in oklab, white 12%, transparent);
			border-radius: var(--radius-sm);
			background: oklch(0.15 0.016 245);
			color: oklch(0.92 0.012 245);
			width: 100%;
		}
		.terminal-search {
			min-height: 34px;
			padding: 7px 9px;
		}
		.terminal-section-title {
			color: oklch(0.67 0.014 245);
			font-family: var(--mono);
			font-size: 10px;
			text-transform: uppercase;
			margin: 8px 0 6px;
		}
		.terminal-targets,
		.terminal-tools {
			overflow-y: auto;
			min-height: 0;
		}
		.terminal-target,
		.terminal-tool {
			border: 1px solid transparent;
			border-radius: var(--radius-sm);
			background: transparent;
			color: oklch(0.89 0.012 245);
			display: grid;
			gap: 2px;
			margin-bottom: 4px;
			padding: 8px;
			text-align: left;
			width: 100%;
		}
		.terminal-target.active,
		.terminal-tool:hover,
		.terminal-target:hover {
			background: color-mix(in oklab, white 7%, transparent);
			border-color: color-mix(in oklab, white 12%, transparent);
		}
		.terminal-target strong,
		.terminal-tool strong {
			font-size: 12px;
			overflow-wrap: anywhere;
		}
		.terminal-target small,
		.terminal-tool small {
			color: oklch(0.62 0.014 245);
			font-size: 11px;
			overflow-wrap: anywhere;
		}
		.terminal-main {
			display: grid;
			grid-template-rows: minmax(0, 1fr) auto;
			min-width: 0;
			min-height: 0;
		}
		.terminal-output {
			font-family: var(--mono);
			font-size: 12px;
			line-height: 1.55;
			overflow-y: auto;
			padding: 16px;
			white-space: pre-wrap;
		}
		.terminal-job {
			margin-bottom: 18px;
		}
		.terminal-job__head {
			color: oklch(0.78 0.16 150);
			display: flex;
			align-items: center;
			gap: 8px;
			margin-bottom: 5px;
		}
		.terminal-job__head strong {
			min-width: 0;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
		.terminal-job__head time {
			color: oklch(0.62 0.014 245);
			margin-left: auto;
			white-space: nowrap;
		}
		.terminal-line {
			color: oklch(0.78 0.012 245);
			display: block;
			overflow-wrap: anywhere;
		}
		.terminal-line.error { color: oklch(0.72 0.17 25); }
		.terminal-input {
			border-top: 1px solid color-mix(in oklab, white 10%, transparent);
			display: grid;
			grid-template-columns: auto minmax(0, 1fr) auto;
			gap: 10px;
			align-items: end;
			padding: 10px;
		}
		.terminal-prompt {
			color: oklch(0.78 0.16 150);
			font-family: var(--mono);
			font-weight: 700;
			padding-bottom: 8px;
		}
		.terminal-command {
			font-family: var(--mono);
			line-height: 1.45;
			max-height: 150px;
			min-height: 38px;
			padding: 9px 10px;
			resize: vertical;
		}
		.terminal-actions {
			display: flex;
			gap: 7px;
			flex-wrap: wrap;
			justify-content: flex-end;
		}
		.terminal-actions button,
		.terminal-job__head button,
		.terminal-head button:not(.terminal-dot) {
			border: 1px solid color-mix(in oklab, white 12%, transparent);
			border-radius: var(--radius-sm);
			background: color-mix(in oklab, white 7%, transparent);
			color: oklch(0.92 0.012 245);
			min-height: 30px;
			padding: 6px 9px;
		}
		.terminal-actions button.primary {
			background: oklch(0.62 0.14 235);
			border-color: oklch(0.62 0.14 235);
			color: white;
		}
		.terminal-empty {
			color: oklch(0.58 0.014 245);
			display: grid;
			place-items: center;
			height: 100%;
			text-align: center;
		}
			@media (max-width: 1100px) {
				.sites__head,
				.site-row {
					grid-template-columns: 32px minmax(220px, 1.7fr) 90px 90px 90px 74px;
				}
				.col-storage,
				.col-backup { display: none; }
				.site-detail-body,
				.site-overview-grid {
					grid-template-columns: 1fr;
				}
				.site-detail-tabs {
					position: static;
					display: flex;
					overflow-x: auto;
				}
				.site-detail-tabs button {
					min-width: 150px;
				}
			}
			@media (max-width: 820px) {
				:root { --gutter: 18px; }
			.topbar__inner { gap: 12px; }
			.app-nav {
				order: 3;
				width: 100%;
				margin-left: 0;
				overflow-x: auto;
				padding-bottom: 8px;
			}
			.topbar__inner {
				height: auto;
				min-height: 64px;
				flex-wrap: wrap;
				padding-top: 12px;
			}
			.user-chip span,
			.scope,
			.summary__pills { display: none; }
			.summary { align-items: flex-start; }
			.summary__title {
				display: grid;
				gap: 5px;
			}
			.toolbar {
				grid-template-columns: minmax(0, 1fr) auto;
			}
			.toolbar .primary-btn {
				grid-column: 1 / -1;
				width: 100%;
			}
			.sites__head { display: none; }
			.site-group__bar { padding-left: 14px; }
			.site-row {
				grid-template-columns: 28px minmax(0, 1fr) 72px;
				gap: 10px;
			}
			.col-wp,
			.col-visits,
			.col-storage,
			.col-backup,
			.status-cell { display: none; }
			.row-actions { justify-content: flex-end; }
			.sites__foot {
				align-items: flex-start;
				flex-direction: column;
			}
			.pagination-foot {
				align-items: flex-start;
				flex-direction: column;
			}
			.bulk-bar {
				left: 12px;
				right: 12px;
				transform: translateY(120%);
				flex-wrap: wrap;
			}
			.bulk-bar.visible { transform: translateY(0); }
				.detail-toolbar {
					align-items: stretch;
					flex-direction: column;
				}
				.detail-actions > * { flex: 1; }
				.site-hero {
					align-items: start;
					grid-template-columns: 72px minmax(0, 1fr);
				}
				.site-hero__actions {
					grid-column: 1 / -1;
					width: 100%;
				}
				.site-hero__actions > *,
				.site-toolbar-actions > * {
					flex: 1;
					justify-content: center;
				}
				.site-detail-toolbar {
					align-items: stretch;
					flex-direction: column;
				}
				.site-env-switch {
					overflow-x: auto;
				}
				.site-section {
					padding: 18px;
				}
				.site-secret-row {
					grid-template-columns: minmax(0, 1fr) auto;
				}
				.site-secret-row__label {
					grid-column: 1 / -1;
				}
				.stats-toolbar {
					align-items: stretch;
					flex-direction: column;
				}
				.stats-controls,
				.stats-control,
				.stats-timeframes {
					width: 100%;
				}
				.stats-kpi-grid {
					grid-template-columns: 1fr 1fr;
				}
				.stats-sharing {
					grid-template-columns: 1fr;
				}
				.stats-kpi .v {
					font-size: 34px;
				}
				.stats-share-password {
					grid-template-columns: 1fr;
				}
				.captures-modal {
					height: calc(100vh - 24px);
				}
				.captures-toolbar,
				.captures-controls,
				.captures-actions,
				.captures-control {
					align-items: stretch;
					width: 100%;
				}
				.captures-actions > * {
					flex: 1;
					justify-content: center;
				}
				.capture-auth-grid,
				.logs-grid {
					grid-template-columns: 1fr;
				}
				.audit-card {
					grid-template-columns: 1fr;
				}
				.stack-row { grid-template-columns: 1fr; }
				.terminal-window {
					inset: 8px;
				width: auto;
				height: auto;
			}
			.terminal-body {
				grid-template-columns: 1fr;
				grid-template-rows: 220px minmax(0, 1fr);
			}
			.terminal-sidebar {
				border-right: 0;
				border-bottom: 1px solid color-mix(in oklab, white 10%, transparent);
			}
			.terminal-input {
				grid-template-columns: auto minmax(0, 1fr);
			}
			.terminal-actions {
				grid-column: 1 / -1;
			}
		}
	</style>
</head>
<body>
<div id="cc-v2-app" class="cc-v2" v-cloak>
	<header class="app-topbar">
		<div class="topbar__inner">
			<a class="brand" href="#" data-route="/sites" aria-label="CaptainCore Manager">
				<img class="brand__logo" data-brand-logo alt="" hidden>
				<span class="brand__name" data-brand-name><?php echo esc_html( $configurations->name ); ?></span>
			</a>
			<nav class="app-nav" data-region="nav"></nav>
			<div class="app-tools">
				<button class="icon-btn" type="button" data-action="focus-search" title="Search" aria-label="Search">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
				</button>
				<button class="icon-btn" type="button" data-action="toggle-theme" title="Theme" aria-label="Theme">
					<svg data-theme-icon="sun" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
				</button>
				<button class="icon-btn" type="button" data-action="sign-out" title="Log out" aria-label="Log out">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>
				</button>
				<div class="user-chip">
					<div class="avatar" data-user-avatar></div>
					<span data-user-name></span>
				</div>
			</div>
		</div>
	</header>
	<main id="cc-v2-main" class="app-shell"></main>
	<div class="bulk-bar" data-region="bulk"></div>
	<div class="drawer-backdrop" data-action="close-drawer"></div>
	<aside class="drawer" data-region="drawer" aria-hidden="true"></aside>
	<div class="modal-backdrop" data-action="close-modal"></div>
	<div class="modal-shell" data-region="modal"></div>
	<div class="toast" data-region="toast"></div>
	<div class="terminal-region" data-region="terminal"></div>
</div>

<script>
window.__CAPTAINCORE_V2__ = {
	configurations: <?php echo wp_json_encode( $configurations, $json_options ); ?>,
	colors: <?php echo wp_json_encode( $colors, $json_options ); ?>,
	modules: <?php echo wp_json_encode( $modules, $json_options ); ?>,
	user: {
		id: <?php echo (int) get_current_user_id(); ?>,
		email: <?php echo wp_json_encode( $user->email, $json_options ); ?>,
		login: <?php echo wp_json_encode( $user->login, $json_options ); ?>,
		registered: <?php echo wp_json_encode( $user->registered, $json_options ); ?>,
		hash: <?php echo wp_json_encode( $user->hash, $json_options ); ?>,
		display_name: <?php echo wp_json_encode( $user->display_name, $json_options ); ?>,
		first_name: <?php echo wp_json_encode( $user->first_name, $json_options ); ?>,
		last_name: <?php echo wp_json_encode( $user->last_name, $json_options ); ?>,
		role: <?php echo wp_json_encode( $user->role, $json_options ); ?>,
	},
	footer: <?php echo wp_json_encode( $footer, $json_options ); ?>,
	socket: <?php echo wp_json_encode( $socket, $json_options ); ?>,
	home_link: <?php echo wp_json_encode( home_url(), $json_options ); ?>,
	plugin_url: <?php echo wp_json_encode( $plugin_url, $json_options ); ?>,
	remote_upload_uri: <?php echo wp_json_encode( get_option( 'options_remote_upload_uri' ), $json_options ); ?>,
	site_filters: <?php echo wp_json_encode( $site_filters, $json_options ); ?>,
	site_filters_core: <?php echo wp_json_encode( $site_filters_core, $json_options ); ?>,
	rest_root: <?php echo wp_json_encode( esc_url_raw( rest_url() ), $json_options ); ?>,
	wp_nonce: ( typeof wpApiSettings !== 'undefined' && wpApiSettings.nonce ) ? wpApiSettings.nonce : '<?php echo is_user_logged_in() ? esc_js( wp_create_nonce( 'wp_rest' ) ) : ''; ?>',
};

(function () {
	'use strict';

	const CC = window.__CAPTAINCORE_V2__ || {};
	const app = document.getElementById('cc-v2-app');
	const main = document.getElementById('cc-v2-main');
	const navRegion = document.querySelector('[data-region="nav"]');
	const bulkRegion = document.querySelector('[data-region="bulk"]');
	const drawerRegion = document.querySelector('[data-region="drawer"]');
	const modalRegion = document.querySelector('[data-region="modal"]');
	const toastRegion = document.querySelector('[data-region="toast"]');
	const terminalRegion = document.querySelector('[data-region="terminal"]');
	const drawerBackdrop = document.querySelector('.drawer-backdrop');
	const modalBackdrop = document.querySelector('.modal-backdrop');

	app.removeAttribute('v-cloak');

	const icons = {
		search: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>',
		list: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>',
		grid: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>',
		plus: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
		external: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>',
		more: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="6" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="18" r="1"/></svg>',
		monitor: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8"/><path d="M12 16v4"/></svg>',
		login: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>',
		close: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
		back: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>',
		globe: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 0 20"/><path d="M12 2a15.3 15.3 0 0 0 0 20"/></svg>',
		users: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
		credit: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>',
			terminal: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>',
			book: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M4 4v15.5A2.5 2.5 0 0 1 6.5 22H20V6a2 2 0 0 0-2-2H6.5A2.5 2.5 0 0 0 4 6.5"/></svg>',
			save: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>',
			clock: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
			info: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
			chart: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
			file: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
			plug: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4v6"/><path d="M15 4v6"/><path d="M12 14v8"/><path d="M5 10h14"/><path d="M6 10v2a6 6 0 0 0 12 0v-2"/></svg>',
			database: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5"/><path d="M3 12c0 1.7 4 3 9 3s9-1.3 9-3"/></svg>',
			lock: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
			eye: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
			copy: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',
			image: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
			refresh: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 1-15.36 6.36L3 16"/><path d="M3 12a9 9 0 0 1 15.36-6.36L21 8"/><path d="M21 3v5h-5"/><path d="M3 21v-5h5"/></svg>',
			settings: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09A1.65 1.65 0 0 0 15 4.6a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.3.31.48.73.51 1.17H21a2 2 0 0 1 0 4h-.09c-.03.44-.21.86-.51 1.17z"/></svg>',
		};

	const state = {
		nonce: CC.wp_nonce || '',
		route: routeFromLocation(),
		viewMode: localStorage.getItem('captaincore-v2-view') || 'list',
		theme: localStorage.getItem('captaincore-v2-theme') || 'light',
		search: '',
		envFilter: 'all',
		popover: '',
		filterSearch: { core: '', themes: '', plugins: '' },
		selectedCore: [],
		selectedThemes: [],
		selectedPlugins: [],
		filteredSiteIds: null,
		filteredEnvIds: null,
		sites: [],
		domains: [],
		accounts: [],
		subscriptions: [],
		siteDetails: {},
		domainDetails: {},
		accountDetails: {},
		siteFeatures: {},
		detailTabs: { site: 'overview', domain: 'dns', account: 'overview' },
		detailEnvironments: {},
		siteStats: { from_at: dateOffsetIso(-365), to_at: dateOffsetIso(0), grouping: 'Month', fathom_id: '', timeframe: '12m', sharePassword: '', shareSaving: false, shareError: '' },
		userSearch: '',
		userRoleFilter: 'all',
		logSearch: '',
		quicksaveSearch: '',
		loading: {},
		error: {},
		fetched: {},
		selection: new Set(),
		drawer: null,
		drawerTab: 'overview',
		modal: null,
		captures: {
			siteId: '',
			envId: '',
			envName: '',
			siteName: '',
			homeUrl: '',
			captures: [],
			selectedCaptureId: '',
			selectedPageKey: '',
			pages: [{ page: '/' }],
			auth: { username: '', password: '' },
			loading: false,
			saving: false,
			requesting: false,
			error: '',
			showConfig: false,
		},
		globalSearch: { query: '', scope: 'all', activeIndex: 0 },
			fileDiff: { hash: '', file: '', content: '', loading: false, error: '' },
			actionLoading: {},
			revealedSecrets: {},
			jobs: [],
			recipes: [],
		terminal: {
			open: false,
			show: false,
			fullscreen: localStorage.getItem('captaincore-v2-terminal-fullscreen') === 'true',
			command: '',
			selectedTargets: [],
			targetSearch: '',
			toolSearch: '',
			toolTab: 'system',
			saveRecipe: { title: '', public: 1, loading: false, error: '' },
			schedule: { date: '', time: '', loading: false, error: '' },
		},
		systemTools: [
			{ key: 'sync-data', label: 'Manual Sync Details', description: 'Refresh environment metadata.' },
			{ key: 'deploy-defaults', label: 'Deploy Defaults', description: 'Apply account defaults and recipes.' },
			{ key: 'apply-https', label: 'Apply HTTPS URLs', description: 'Replace http:// references.' },
			{ key: 'backup', label: 'Run Backup', description: 'Create a fresh backup.' },
			{ key: 'snapshot', label: 'Generate Snapshot', description: 'Create a restorable snapshot.' },
			{ key: 'scan-errors', label: 'Scan Errors', description: 'Collect recent site errors.' },
		],
		login: { user_login: '', user_password: '', tfa_code: '', lost: false, errors: '', info: '', loading: false },
		newSite: { name: '', domain: '', site: '', address: '', username: '', password: '', loading: false, errors: '' },
		page: 1,
		pages: {},
		perPage: 100,
	};

	document.documentElement.dataset.theme = state.theme;

	function basePath() {
		let path = (CC.configurations && CC.configurations.path) ? CC.configurations.path : '/account/';
		if (!path.startsWith('/')) path = '/' + path;
		if (!path.endsWith('/')) path += '/';
		return path;
	}

	function routeFromLocation() {
		const base = basePath();
		const baseRoot = base.replace(/\/+$/, '');
		let path = window.location.pathname;
		if (base !== '/' && (path === baseRoot || path === base)) {
			path = '';
		} else if (base !== '/' && path.startsWith(base)) {
			path = path.slice(base.length);
		} else {
			path = path.replace(/^\/+/, '');
		}
		path = path.replace(/\/+$/, '');
		return path ? '/' + path : '/sites';
	}

	function routeUrl(route) {
		const clean = route.replace(/^\/+/, '');
		return basePath() + clean + '?ui=v2';
	}

	function navTo(route) {
		state.route = route || '/sites';
		state.popover = '';
		state.page = 1;
		window.history.pushState({}, '', routeUrl(state.route));
		loadForRoute();
		render();
	}

	function esc(value) {
		return String(value == null ? '' : value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function attr(value) {
		return esc(value).replace(/`/g, '&#096;');
	}

	function safeMarkdownUrl(value) {
		const url = String(value || '').trim().replace(/[\u0000-\u001f\u007f]/g, '');
		if (/^(https?:|mailto:)/i.test(url)) return url;
		return '';
	}

	function renderInlineMarkdown(value) {
		const tokens = [];
		const stash = html => {
			const token = '\uE000MD' + tokens.length + '\uE001';
			tokens.push(html);
			return token;
		};
		let source = String(value == null ? '' : value);
		source = source.replace(/`([^`\n]+)`/g, (match, code) => stash('<code>' + esc(code) + '</code>'));
		source = source.replace(/\[([^\]\n]+)\]\(([^)\s]+)(?:\s+["'][^"']*["'])?\)/g, (match, label, url) => {
			const safeUrl = safeMarkdownUrl(url);
			if (!safeUrl) return label;
			const target = /^https?:/i.test(safeUrl) ? ' target="_blank" rel="noopener noreferrer"' : '';
			return stash('<a href="' + attr(safeUrl) + '"' + target + '>' + esc(label) + '</a>');
		});
		let html = esc(source);
		html = html.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
		html = html.replace(/(^|[\s([{])__([^_\n]+)__(?=$|[\s)\].,!?;:}])/g, '$1<strong>$2</strong>');
		html = html.replace(/(^|[\s([{])\*([^*\n]+)\*(?=$|[\s)\].,!?;:}])/g, '$1<em>$2</em>');
		html = html.replace(/(^|[\s([{])_([^_\n]+)_(?=$|[\s)\].,!?;:}])/g, '$1<em>$2</em>');
		return html.replace(/\uE000MD(\d+)\uE001/g, (match, index) => tokens[Number(index)] || '');
	}

	function renderMarkdown(value) {
		const source = String(value == null ? '' : value).replace(/\r\n?/g, '\n').trim();
		if (!source) return '';
		const lines = source.split('\n');
		const html = [];
		let paragraph = [];
		let list = null;

		const flushParagraph = () => {
			if (!paragraph.length) return;
			html.push('<p>' + renderInlineMarkdown(paragraph.join('\n')).replace(/\n/g, '<br>') + '</p>');
			paragraph = [];
		};
		const flushList = () => {
			if (!list) return;
			html.push('<' + list.type + '>' + list.items.map(item => '<li>' + renderInlineMarkdown(item) + '</li>').join('') + '</' + list.type + '>');
			list = null;
		};

		lines.forEach(line => {
			if (!line.trim()) {
				flushParagraph();
				flushList();
				return;
			}
			const unordered = line.match(/^\s*[-*+]\s+(.+)$/);
			const ordered = line.match(/^\s*\d+[.)]\s+(.+)$/);
			if (unordered || ordered) {
				flushParagraph();
				const type = ordered ? 'ol' : 'ul';
				if (list && list.type !== type) flushList();
				if (!list) list = { type, items: [] };
				list.items.push((unordered || ordered)[1]);
				return;
			}
			flushList();
			paragraph.push(line);
		});
		flushParagraph();
		flushList();
		return html.join('');
	}

	function sanitizeRenderedHtml(value) {
		const template = document.createElement('template');
		template.innerHTML = String(value == null ? '' : value);
		const allowedTags = new Set(['A', 'B', 'BLOCKQUOTE', 'BR', 'CODE', 'EM', 'I', 'LI', 'OL', 'P', 'PRE', 'STRONG', 'UL']);
		const elements = [];
		const walker = document.createTreeWalker(template.content, NodeFilter.SHOW_ELEMENT);
		while (walker.nextNode()) elements.push(walker.currentNode);
		elements.forEach(element => {
			if (!allowedTags.has(element.tagName)) {
				element.replaceWith(document.createTextNode(element.textContent || ''));
				return;
			}
			Array.from(element.attributes).forEach(attribute => {
				const name = attribute.name.toLowerCase();
				if (element.tagName === 'A' && name === 'href') {
					const safeUrl = safeMarkdownUrl(attribute.value);
					if (safeUrl) {
						element.setAttribute('href', safeUrl);
						if (/^https?:/i.test(safeUrl)) {
							element.setAttribute('target', '_blank');
							element.setAttribute('rel', 'noopener noreferrer');
						}
					} else {
						element.removeAttribute(attribute.name);
					}
					return;
				}
				element.removeAttribute(attribute.name);
			});
		});
		return template.innerHTML;
	}

	function renderTimelineMarkdown(value) {
		const source = String(value == null ? '' : value).replace(/\r\n?/g, '\n').trim();
		if (!source) return '';
		if (/<\/?[a-z][\s\S]*>/i.test(source)) return sanitizeRenderedHtml(source);
		return renderMarkdown(source);
	}

	function normalizeText(value) {
		return String(value == null ? '' : value).toLowerCase();
	}

	function initials(name) {
		const source = String(name || CC.user?.display_name || CC.user?.login || 'CC').trim();
		return source.split(/\s+/).slice(0, 2).map(part => part.charAt(0).toUpperCase()).join('') || 'CC';
	}

	function formatNumber(value) {
		if (value == null || value === '' || Number.isNaN(Number(value))) return '-';
		return Number(value).toLocaleString();
	}

	function formatCompactNumber(value) {
		if (value == null || value === '' || Number.isNaN(Number(value))) return '-';
		return new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 }).format(Number(value)).toUpperCase();
	}

	function formatStorage(bytes) {
		const n = Number(bytes);
		if (!n || Number.isNaN(n)) return '-';
		if (n >= 1073741824) return (n / 1073741824).toFixed(2) + ' GB';
		if (n >= 1048576) return (n / 1048576).toFixed(0) + ' MB';
		return n + ' B';
	}

	function formatUrl(url) {
		return String(url || '').replace(/^https?:\/\//, '').replace(/\/$/, '') || '-';
	}

	function safeOpen(url) {
		if (typeof url !== 'string') return;
		const trimmed = url.trim();
		if (/^https?:\/\//i.test(trimmed)) window.open(trimmed, '_blank', 'noopener');
	}

	function dateOffsetIso(days) {
		const date = new Date();
		date.setDate(date.getDate() + Number(days || 0));
		return date.toISOString().slice(0, 10);
	}

	function routeId(type) {
		const parts = state.route.split('/').filter(Boolean);
		return parts[0] === type ? (parts[1] || '') : '';
	}

	function detailKey(type, id) {
		return type + 'Detail:' + id;
	}

	function firstDefined() {
		for (let i = 0; i < arguments.length; i++) {
			if (arguments[i] !== undefined && arguments[i] !== null && arguments[i] !== '') return arguments[i];
		}
		return '';
	}

	function asArray(value) {
		if (Array.isArray(value)) return value;
		if (!value || value === 'Loading') return [];
		if (typeof value === 'string') {
			try {
				const parsed = JSON.parse(value);
				return Array.isArray(parsed) ? parsed : [];
			} catch (error) {
				return [];
			}
		}
		return [];
	}

	function asObject(value) {
		if (!value || typeof value !== 'object' || Array.isArray(value)) return {};
		return value;
	}

	function formatMoney(value) {
		if (value == null || value === '' || Number.isNaN(Number(value))) return '-';
		return '$' + Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	function formatDate(value) {
		if (!value) return '-';
		const numeric = Number(value);
		const date = Number.isFinite(numeric) && String(value).length <= 10 ? new Date(numeric * 1000) : new Date(value);
		if (Number.isNaN(date.getTime())) return String(value);
		return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
	}

	function formatTime(value) {
		const seconds = Number(value);
		if (!Number.isFinite(seconds)) return '-';
		const minutes = Math.floor(seconds / 60);
		const rest = Math.round(seconds % 60);
		if (minutes <= 0) return rest + 's';
		return minutes + 'm ' + rest + 's';
	}

	function formatPercent(value) {
		const n = Number(value);
		if (!Number.isFinite(n)) return '-';
		const pct = n <= 1 ? n * 100 : n;
		return pct.toFixed(1) + '%';
	}

	function statusBadge(value) {
		const text = value == null || value === '' ? '-' : String(value);
		const normalized = text.toLowerCase();
		const positive = ['active', 'valid', 'healthy', 'paid', 'success', 'primary', 'dns active'].includes(normalized);
		const klass = positive ? 'production' : '';
		return '<span class="badge ' + klass + '">' + esc(text) + '</span>';
	}

	function recordValue(record) {
		const value = record?.value;
		if (Array.isArray(value)) {
			return value.map(item => {
				if (item == null) return '';
				if (typeof item !== 'object') return String(item);
				if (item.server) return [item.priority, item.server].filter(Boolean).join(' ');
				if (item.host) return [item.priority, item.weight, item.port, item.host].filter(Boolean).join(' ');
				return item.value || item.url || JSON.stringify(item);
			}).filter(Boolean).join(', ');
		}
		if (value && typeof value === 'object') return value.url || value.value || JSON.stringify(value);
		return String(value == null ? '' : value);
	}

	function renderDetailTabs(type, tabs) {
		if (!tabs.length) return '';
		const active = tabs.some(tab => tab.key === state.detailTabs[type]) ? state.detailTabs[type] : tabs[0].key;
		state.detailTabs[type] = active;
		return `
			<nav class="detail-tabs" aria-label="${attr(type)} sections">
				${tabs.map(tab => '<button type="button" class="' + (tab.key === active ? 'active' : '') + '" data-action="detail-tab" data-detail-type="' + attr(type) + '" data-tab="' + attr(tab.key) + '">' + esc(tab.label) + '</button>').join('')}
			</nav>
		`;
	}

	function paginateRows(pageKey, rows, perPage) {
		const total = rows.length;
		const pages = Math.max(1, Math.ceil(total / perPage));
		let page = Number(state.pages[pageKey] || 1);
		if (!Number.isFinite(page) || page < 1) page = 1;
		if (page > pages) page = pages;
		state.pages[pageKey] = page;
		const start = (page - 1) * perPage;
		const end = Math.min(start + perPage, total);
		return { rows: rows.slice(start, end), total, page, pages, start, end, perPage };
	}

	function renderPagination(pageKey, pageInfo, label) {
		if (!pageInfo || pageInfo.total <= pageInfo.perPage) return '';
		return `
			<div class="pagination-foot">
				<span>Showing ${formatNumber(pageInfo.start + 1)}-${formatNumber(pageInfo.end)} of ${formatNumber(pageInfo.total)} ${esc(label || 'records')}</span>
				<div class="pager">
					<button type="button" data-action="set-page" data-page-key="${attr(pageKey)}" data-page="${pageInfo.page - 1}" ${pageInfo.page <= 1 ? 'disabled' : ''} aria-label="Previous">Prev</button>
					<button type="button" disabled>${pageInfo.page} / ${pageInfo.pages}</button>
					<button type="button" data-action="set-page" data-page-key="${attr(pageKey)}" data-page="${pageInfo.page + 1}" ${pageInfo.page >= pageInfo.pages ? 'disabled' : ''} aria-label="Next">Next</button>
				</div>
			</div>
		`;
	}

	function renderDetailTable(headers, rows, emptyLabel, options = {}) {
		if (!rows || !rows.length) return renderEmpty(emptyLabel || 'No data available.');
		const perPage = options.perPage || 25;
		const pageKey = options.pageKey || '';
		const pageInfo = pageKey ? paginateRows(pageKey, rows, perPage) : null;
		const visibleRows = pageInfo ? pageInfo.rows : rows;
		return `
			<div style="overflow:auto">
				<table class="detail-table">
					<thead><tr>${headers.map(header => '<th>' + esc(header) + '</th>').join('')}</tr></thead>
					<tbody>
						${visibleRows.map(row => '<tr>' + row.map(cell => '<td>' + cell + '</td>').join('') + '</tr>').join('')}
					</tbody>
				</table>
			</div>
			${pageKey ? renderPagination(pageKey, pageInfo, options.label || 'records') : ''}
		`;
	}

	function renderDataAttrs(dataset) {
		return Object.entries(dataset || {}).map(([key, value]) => {
			if (value == null || value === '' || !/^[a-z0-9_-]+$/i.test(key)) return '';
			return ' data-' + key + '="' + attr(value) + '"';
		}).join('');
	}

	function renderStackRows(rows, emptyLabel, options = {}) {
		if (!rows || !rows.length) return renderEmpty(emptyLabel || 'No data available.');
		const perPage = options.perPage || 25;
		const pageKey = options.pageKey || '';
		const pageInfo = pageKey ? paginateRows(pageKey, rows, perPage) : null;
		const visibleRows = pageInfo ? pageInfo.rows : rows;
		return '<div class="stack-list">' + visibleRows.map(row => `
			<div class="stack-row ${row.action || row.route ? 'clickable' : ''} ${row.className || ''}"${row.route ? ' role="link" tabindex="0" data-route="' + attr(row.route) + '" aria-label="' + attr(row.ariaLabel || row.title || 'Open item') + '"' : (row.action ? ' role="button" tabindex="0" data-action="' + attr(row.action) + '"' + renderDataAttrs(row.dataset) + ' aria-label="' + attr(row.ariaLabel || row.title || 'Open item') + '"' : '')}>
				<div class="stack-row__primary">${row.html || '<div class="stack-row__title"><strong>' + row.title + '</strong></div>'}${row.meta ? '<small>' + row.meta + '</small>' : ''}${row.hint ? '<div class="stack-row__hint">' + row.hint + '</div>' : ''}</div>
				${row.aside ? '<div>' + row.aside + '</div>' : ''}
			</div>
		`).join('') + '</div>' + (pageKey ? renderPagination(pageKey, pageInfo, options.label || 'records') : '');
	}

	async function apiFetch(path, options = {}) {
		const headers = Object.assign({
			'Accept': 'application/json',
			'X-WP-Nonce': state.nonce,
		}, options.headers || {});
		if (options.body && !headers['Content-Type']) headers['Content-Type'] = 'application/json';
		const response = await fetch(path, Object.assign({}, options, { headers }));
		const contentType = response.headers.get('content-type') || '';
		const payload = contentType.includes('application/json') ? await response.json() : await response.text();
		if (!response.ok) {
			if (response.status === 403 && state.route !== '/login') {
				state.nonce = '';
				state.route = '/login';
				window.history.replaceState({}, '', routeUrl('/login'));
				render();
			}
			const err = new Error((payload && payload.message) || 'Request failed');
			err.response = response;
			err.payload = payload;
			throw err;
		}
		return payload;
	}

	async function loadForRoute() {
		if (!state.nonce && state.route !== '/login') {
			state.route = '/login';
			window.history.replaceState({}, '', routeUrl('/login'));
			return;
		}
		const siteId = routeId('sites');
		const domainId = routeId('domains');
		const accountId = routeId('accounts');
		if (siteId) return loadSiteDetail(siteId);
		if (domainId) return loadDomainDetail(domainId);
		if (accountId) return loadAccountDetail(accountId);
		if (state.route.startsWith('/sites')) return loadCollection('sites', '/wp-json/captaincore/v1/sites');
		if (state.route.startsWith('/domains')) return loadCollection('domains', '/wp-json/captaincore/v1/domains/');
		if (state.route.startsWith('/accounts')) return loadCollection('accounts', '/wp-json/captaincore/v1/accounts/');
		if (state.route.startsWith('/billing') || state.route.startsWith('/subscriptions')) return loadCollection('subscriptions', '/wp-json/captaincore/v1/subscriptions/');
	}

	async function loadCollection(key, endpoint, force = false) {
		if (state.loading[key] || (state.fetched[key] && !force)) return;
		state.loading[key] = true;
		state.error[key] = '';
		render();
		try {
			const data = await apiFetch(endpoint);
			state[key] = Array.isArray(data) ? data : [];
			state.fetched[key] = true;
		} catch (error) {
			state.error[key] = error.message || 'Unable to load data.';
		} finally {
			state.loading[key] = false;
			render();
		}
	}

	async function loadSiteDetail(id, force = false) {
		const key = detailKey('site', id);
		if (state.loading[key] || (state.siteDetails[id] && !force)) return;
		state.loading[key] = true;
		state.error[key] = '';
		render();
		try {
			const [envs, details] = await Promise.all([
				apiFetch('/wp-json/captaincore/v1/sites/' + encodeURIComponent(id) + '/environments'),
				apiFetch('/wp-json/captaincore/v1/sites/' + encodeURIComponent(id) + '/details'),
			]);
			const environments = Array.isArray(envs) ? envs : [];
			const payload = details || {};
			state.siteDetails[id] = {
				environments,
				details: payload,
				site: payload.site || state.sites.find(item => String(item.site_id) === String(id)) || {},
			};
			if (!state.detailEnvironments[id] && environments.length) {
				const primary = environments.find(env => env.environment === 'Production') || environments[0];
				state.detailEnvironments[id] = primary.environment_id || primary.environment || 'Production';
			}
		} catch (error) {
			state.error[key] = error.message || 'Unable to load site.';
		} finally {
			state.loading[key] = false;
			render();
		}
	}

	async function loadDomainDetail(id, force = false) {
		const key = detailKey('domain', id);
		if (state.loading[key] || (state.domainDetails[id] && !force)) return;
		state.loading[key] = true;
		state.error[key] = '';
		render();
		try {
			const listDomain = state.domains.find(item => String(item.domain_id) === String(id));
			const domainPayload = await apiFetch('/wp-json/captaincore/v1/domain/' + encodeURIComponent(id));
			const domain = Object.assign({}, listDomain || {}, domainPayload || {});
			let records = [];
			if (domain.remote_id || listDomain?.remote_id) {
				try {
					const dnsPayload = await apiFetch('/wp-json/captaincore/v1/dns/' + encodeURIComponent(id));
					records = Array.isArray(dnsPayload) ? dnsPayload : asArray(dnsPayload?.records);
				} catch (error) {
					records = [];
				}
			}
			state.domainDetails[id] = {
				domain,
				provider: domainPayload?.provider || {},
				details: domainPayload?.details || {},
				records,
			};
		} catch (error) {
			state.error[key] = error.message || 'Unable to load domain.';
		} finally {
			state.loading[key] = false;
			render();
		}
	}

	async function loadAccountDetail(id, force = false) {
		const key = detailKey('account', id);
		if (state.loading[key] || (state.accountDetails[id] && !force)) return;
		state.loading[key] = true;
		state.error[key] = '';
		render();
		try {
			state.accountDetails[id] = await apiFetch('/wp-json/captaincore/v1/accounts/' + encodeURIComponent(id));
		} catch (error) {
			state.error[key] = error.message || 'Unable to load account.';
		} finally {
			state.loading[key] = false;
			render();
		}
	}

	function environmentSlug(env) {
		return String(env?.environment || 'production').toLowerCase();
	}

	function siteFeatureKey(siteId, env) {
		return [siteId || 'site', env?.environment_id || env?.environment || 'primary'].join(':');
	}

	function siteFeature(siteId, env) {
		const key = siteFeatureKey(siteId, env);
		if (!state.siteFeatures[key]) {
			state.siteFeatures[key] = {
				stats: null,
				statsLoaded: false,
				statsLoading: false,
				statsError: '',
				backups: [],
				backupsLoaded: false,
				backupsLoading: false,
				backupsError: '',
				quicksaves: [],
				quicksavesLoaded: false,
				quicksavesLoading: false,
				quicksavesError: '',
				expandedQuicksave: '',
				quicksaveFiles: {},
				quicksaveFilesLoading: {},
				quicksaveFilesError: {},
				timeline: [],
				timelineLoaded: false,
				timelineLoading: false,
				timelineError: '',
				users: [],
				usersLoaded: false,
				usersLoading: false,
				usersError: '',
				logFiles: [],
				logsLoaded: false,
				logsLoading: false,
				logsError: '',
				selectedLog: '',
				logLimit: '1000',
				logContent: '',
				logContentLoaded: false,
				logContentLoading: false,
				logContentError: '',
			};
		}
		return state.siteFeatures[key];
	}

	function isSiteFeatureTab(tab) {
		return ['stats', 'backups', 'quicksaves', 'timeline', 'users', 'logs'].includes(tab);
	}

	function extractItems(payload, siteId) {
		if (Array.isArray(payload)) return payload;
		if (Array.isArray(payload?.items)) return payload.items;
		if (Array.isArray(payload?.results)) return payload.results;
		if (siteId && Array.isArray(payload?.[siteId])) return payload[siteId];
		return [];
	}

	function arrayFromMaybe(value) {
		if (Array.isArray(value)) return value;
		if (value == null || value === '' || value === 'Loading') return [];
		if (typeof value === 'string') {
			try {
				const parsed = JSON.parse(value);
				if (Array.isArray(parsed)) return parsed;
			} catch (error) {}
			return value.trim() ? [value] : [];
		}
		return [value];
	}

	function isIgnoredConsoleError(item) {
		const data = typeof item === 'string' ? { description: item } : asObject(item);
		const location = asObject(firstDefined(data.sourceLocation, data.source_location, data.location));
		const description = String(firstDefined(data.description, data.message, data.text, ''));
		const source = String(data.source || '').toLowerCase();
		const url = String(firstDefined(location.url, data.url, data.href, ''));
		const cleanUrl = url.split('?')[0].replace(/\/+$/, '');
		const mentionsFavicon = /(^|\/)favicon\.ico$/i.test(cleanUrl) || /favicon\.ico/i.test(description);
		const isMissingResource = /failed to load resource/i.test(description) || /status of 404/i.test(description) || Number(data.status) === 404;

		return mentionsFavicon && (isMissingResource || source === 'network');
	}

	function visibleConsoleErrorsForEnvironment(env) {
		const envDetails = asObject(env?.details);
		return arrayFromMaybe(firstDefined(envDetails.console_errors, env?.console_errors)).filter(item => !isIgnoredConsoleError(item));
	}

	function usersForEnvironment(payload, env) {
		const data = asObject(payload);
		const keys = [
			env?.environment,
			String(env?.environment || '').toLowerCase(),
			environmentSlug(env),
			env?.environment_id,
		].filter(Boolean);
		for (const key of keys) {
			if (Array.isArray(data[key])) return data[key];
		}
		return extractItems(payload);
	}

	function normalizeLogFiles(payload) {
		const files = Array.isArray(payload?.files) ? payload.files : extractItems(payload);
		return files.map(file => {
			if (typeof file === 'string') {
				return { path: file, label: file.split('/').pop() || file, meta: '' };
			}
			const path = firstDefined(file?.path, file?.file, file?.name, file?.filename, file?.value);
			return {
				path,
				label: firstDefined(file?.label, file?.name, file?.filename, String(path || '').split('/').pop(), path),
				meta: [file?.size ? (Number.isNaN(Number(file.size)) ? file.size : formatStorage(file.size)) : '', file?.modified || file?.updated_at || file?.date || ''].filter(Boolean).join(' - '),
			};
		}).filter(file => file.path);
	}

	function backupId(backup) {
		return firstDefined(backup?.backup_id, backup?.id, backup?.snapshot_id, backup?.name);
	}

	function quicksaveHash(quicksave) {
		return firstDefined(quicksave?.git_hash, quicksave?.hash, quicksave?.hash_after, quicksave?.id);
	}

	function cleanFileName(fileName) {
		const value = String(fileName || '');
		return value.includes('\t') ? value.split('\t').pop() : value;
	}

	function parseChangedFiles(payload) {
		const files = Array.isArray(payload) ? payload : String(payload || '').trim().split('\n');
		return files.map(file => String(file || '').trim()).filter(Boolean);
	}

	function renderDiffContent(content) {
		if (!content) return '';
		return String(content).split('\n').map(line => {
			const klass = line.startsWith('+') && !line.startsWith('+++') ? ' added' : (line.startsWith('-') && !line.startsWith('---') ? ' removed' : '');
			return '<span class="diff-line' + klass + '">' + esc(line || ' ') + '</span>';
		}).join('');
	}

	function currentSiteContext() {
		const id = routeId('sites');
		const data = state.siteDetails[id];
		if (!id || !data) return {};
		const site = Object.assign({}, state.sites.find(item => String(item.site_id) === String(id)) || {}, data.site || {});
		const envs = getEnvironments({ environments: data.environments || site.environments || [] });
		const selected = state.detailEnvironments[id];
		const env = envs.find(item => String(item.environment_id) === String(selected)) || envs.find(item => String(item.environment) === String(selected)) || envs.find(item => item.environment === 'Production') || envs[0];
		return { id, site, env };
	}

	function siteTabFromRoute() {
		const parts = state.route.split('/').filter(Boolean);
		if (parts[0] !== 'sites') return '';
		const slug = parts[2] || '';
		const map = {
			stats: 'stats',
			logs: 'logs',
			addons: 'addons',
			users: 'users',
			updates: 'addons',
			scripts: 'scripts',
			backups: 'backups',
			'backup-overview': 'backups',
			quicksaves: 'quicksaves',
			timeline: 'timeline',
			domains: 'domains',
		};
		return map[slug] || '';
	}

	function targetKey(target) {
		return String(target?.environment_id || target?.envId || target?.site_id + ':' + target?.environment || '');
	}

	function normalizeTarget(site, env) {
		if (!site || !env) return null;
		const envId = env.environment_id || env.enviroment_id || env.id;
		if (!envId) return null;
		return {
			site_id: site.site_id || env.site_id,
			name: site.name || site.site || env.name || 'Site',
			site: site.site || '',
			environment_id: envId,
			environment: env.environment || 'Production',
			home_url: env.home_url || env.link || site.name || '',
		};
	}

	function terminalTargets() {
		const byKey = new Map();
		state.sites.forEach(site => {
			getEnvironments(site).forEach(env => {
				const target = normalizeTarget(site, env);
				if (target) byKey.set(targetKey(target), target);
			});
		});
		Object.values(state.siteDetails).forEach(data => {
			const site = data.site || {};
			asArray(data.environments || site.environments).forEach(env => {
				const target = normalizeTarget(site, env);
				if (target) byKey.set(targetKey(target), target);
			});
		});
		return Array.from(byKey.values()).sort((a, b) => {
			const labelA = [a.name, a.environment, a.home_url].join(' ');
			const labelB = [b.name, b.environment, b.home_url].join(' ');
			return labelA.localeCompare(labelB);
		});
	}

	function filteredTerminalTargets() {
		const q = normalizeText(state.terminal.targetSearch);
		return terminalTargets().filter(target => {
			if (!q) return true;
			return normalizeText([target.name, target.site, target.environment, target.home_url].join(' ')).includes(q);
		});
	}

	function terminalTargetSelected(target) {
		const key = targetKey(target);
		return state.terminal.selectedTargets.some(item => targetKey(item) === key);
	}

	function toggleTerminalTarget(target) {
		const key = targetKey(target);
		const index = state.terminal.selectedTargets.findIndex(item => targetKey(item) === key);
		if (index >= 0) state.terminal.selectedTargets.splice(index, 1);
		else state.terminal.selectedTargets.push(target);
	}

	function openTerminal(targets = [], focus = true) {
		if (targets.length) {
			const byKey = new Map(state.terminal.selectedTargets.map(target => [targetKey(target), target]));
			targets.forEach(target => {
				if (target) byKey.set(targetKey(target), target);
			});
			state.terminal.selectedTargets = Array.from(byKey.values());
		}
		state.terminal.open = true;
		state.terminal.show = true;
		loadRecipes();
		renderTerminal();
		if (focus) {
			requestAnimationFrame(() => {
				const input = document.querySelector('[data-input="terminal-command"]');
				if (input) input.focus();
			});
		}
	}

	function openTerminalForCurrentEnv(focus = true) {
		const { site, env } = currentSiteContext();
		const target = normalizeTarget(site, env);
		state.terminal.selectedTargets = target ? [target] : [];
		openTerminal([], focus);
	}

	function selectFilteredTargetsForTerminal() {
		const visible = visibleSites().flatMap(site => visibleEnvironments(site).map(env => normalizeTarget(site, env))).filter(Boolean);
		openTerminal(visible);
		if (!visible.length) showToast('No filtered environments available.');
	}

	async function loadRecipes(force = false) {
		if (state.loading.recipes || (state.fetched.recipes && !force)) return;
		state.loading.recipes = true;
		state.error.recipes = '';
		renderTerminal();
		try {
			const payload = await apiFetch('/wp-json/captaincore/v1/recipes/');
			state.recipes = Array.isArray(payload) ? payload : [];
			state.fetched.recipes = true;
		} catch (error) {
			state.error.recipes = error.message || 'Unable to load recipes.';
		} finally {
			state.loading.recipes = false;
			renderTerminal();
			render();
		}
	}

	function filteredTerminalTools() {
		const q = normalizeText(state.terminal.toolSearch);
		if (state.terminal.toolTab === 'cookbook') {
			return state.recipes.filter(recipe => !q || normalizeText([recipe.title, recipe.content].join(' ')).includes(q));
		}
		return state.systemTools.filter(tool => !q || normalizeText([tool.label, tool.description, tool.key].join(' ')).includes(q));
	}

	function createJob(description) {
		const job = {
			clientId: 'job-' + Date.now() + '-' + Math.round(Math.random() * 100000),
			job_id: '',
			description,
			status: 'queued',
			stream: [],
			created_at: new Date().toISOString(),
			conn: null,
		};
		state.jobs.push(job);
		state.terminal.show = true;
		renderTerminal();
		return job;
	}

	function jobTokenFromResponse(response) {
		if (typeof response === 'string' || typeof response === 'number') return String(response);
		if (response?.token) return String(response.token);
		if (response?.job_id) return String(response.job_id);
		if (response?.task_id) return String(response.task_id);
		return '';
	}

	function markJobCompleteFromResponse(job, response) {
		job.status = response?.status === 'error' ? 'error' : 'done';
		if (response?.response) job.stream.push(typeof response.response === 'string' ? response.response : JSON.stringify(response.response, null, 2));
		if (response?.message) job.stream.push(response.message);
		renderTerminal();
	}

	function streamJob(job, token) {
		job.job_id = token;
		job.status = 'running';
		if (!CC.socket || CC.socket === '/ws') {
			job.stream.push('Started job ' + token + '.');
			job.stream.push('Realtime websocket address is not configured for this portal.');
			job.status = 'queued';
			renderTerminal();
			return;
		}
		try {
			const conn = new WebSocket(CC.socket);
			job.conn = conn;
			conn.onopen = () => {
				if (conn.readyState === WebSocket.OPEN) {
					conn.send(JSON.stringify({ token, action: 'start' }));
				}
			};
			conn.onmessage = event => {
				job.stream.push(event.data);
				renderTerminal();
				scrollTerminalToBottom();
			};
			conn.onerror = () => {
				job.stream.push('Websocket connection failed.');
			};
			conn.onclose = () => {
				const last = job.stream.length ? String(job.stream[job.stream.length - 1]).trim() : '';
				if (job.status !== 'cancelled') job.status = last === 'Finished.' ? 'done' : (job.stream.length ? 'error' : 'error');
				renderTerminal();
			};
		} catch (error) {
			job.status = 'error';
			job.stream.push(error.message || 'Unable to open websocket.');
		}
		renderTerminal();
	}

	async function startTerminalRequest(description, endpoint, payload) {
		const job = createJob(description);
		try {
			const response = await apiFetch(endpoint, {
				method: 'POST',
				body: JSON.stringify(payload),
			});
			const token = jobTokenFromResponse(response);
			if (token) streamJob(job, token);
			else markJobCompleteFromResponse(job, response);
		} catch (error) {
			job.status = 'error';
			job.stream.push(error.message || 'Unable to start job.');
			renderTerminal();
		}
		return job;
	}

	function selectedTerminalEnvironmentIds() {
		return state.terminal.selectedTargets.map(target => target.environment_id).filter(Boolean);
	}

	function executeTerminalCommand(code = state.terminal.command, targets = state.terminal.selectedTargets) {
		const command = String(code || '').trim();
		if (!command) return;
		if (!targets.length) {
			showToast('Select at least one target environment.');
			return;
		}
		const description = targets.length === 1 ? 'Running command on ' + (targets[0].home_url || targets[0].name) : 'Running command on ' + targets.length + ' environments';
		state.terminal.command = '';
		startTerminalRequest(description, '/wp-json/captaincore/v1/run/code', {
			environments: targets.map(target => target.environment_id),
			code: command,
		});
		renderTerminal();
	}

	function runTerminalSystemTool(toolKey) {
		const tool = state.systemTools.find(item => item.key === toolKey);
		const environments = selectedTerminalEnvironmentIds();
		if (!tool || !environments.length) {
			showToast('Select at least one target environment.');
			return;
		}
		const description = tool.label + ' on ' + (environments.length === 1 ? state.terminal.selectedTargets[0].home_url : environments.length + ' environments');
		startTerminalRequest(description, '/wp-json/captaincore/v1/sites/bulk-tools', {
			tool: tool.key,
			environments,
			params: {},
		});
	}

	function cancelTerminalJob(clientId) {
		const job = state.jobs.find(item => item.clientId === clientId);
		if (!job) return;
		if (job.conn && job.conn.readyState === WebSocket.OPEN) {
			job.conn.send(JSON.stringify({ token: job.job_id, action: 'kill' }));
		}
		if (job.job_id) {
			apiFetch('/wp-json/captaincore/v1/my-jobs/' + encodeURIComponent(job.job_id), { method: 'DELETE' }).catch(() => {});
		}
		job.status = 'cancelled';
		job.stream.push('Process cancelled by user.');
		renderTerminal();
	}

	function copyJobStream(clientId) {
		const job = state.jobs.find(item => item.clientId === clientId);
		if (!job) return;
		const text = job.stream.join('\n');
		if (navigator.clipboard) navigator.clipboard.writeText(text).then(() => showToast('Output copied.'));
	}

	function terminalActiveJob() {
		return [...state.jobs].reverse().find(job => job.status === 'running' || job.status === 'queued') || state.jobs[state.jobs.length - 1] || null;
	}

	function runningJobCount() {
		return state.jobs.filter(job => job.status === 'running' || job.status === 'queued').length;
	}

	function lastJobLine(job) {
		if (!job || !job.stream.length) return '';
		return String(job.stream[job.stream.length - 1]).trim();
	}

	function scrollTerminalToBottom() {
		requestAnimationFrame(() => {
			const output = document.querySelector('[data-terminal-output]');
			if (output) output.scrollTop = output.scrollHeight;
		});
	}

	async function loadSiteFeature(feature, siteId, env, force = false) {
		if (!siteId || !env || !isSiteFeatureTab(feature)) return;
		const bucket = siteFeature(siteId, env);
		const loadedKey = feature + 'Loaded';
		const loadingKey = feature + 'Loading';
		const errorKey = feature + 'Error';
		if (bucket[loadingKey] || (bucket[loadedKey] && !force)) return;
		bucket[loadingKey] = true;
		bucket[errorKey] = '';
		render();
		try {
			if (feature === 'stats') {
				const params = new URLSearchParams({
					from_at: state.siteStats.from_at,
					to_at: state.siteStats.to_at,
					grouping: state.siteStats.grouping,
					environment: env.environment || 'Production',
				});
				const trackerValues = asArray(env.fathom_analytics).map(statsTrackerValue).filter(Boolean);
				if (state.siteStats.fathom_id && trackerValues.includes(state.siteStats.fathom_id)) {
					params.set('fathom_id', state.siteStats.fathom_id);
				}
				const payload = await apiFetch('/wp-json/captaincore/v1/sites/' + encodeURIComponent(siteId) + '/stats?' + params.toString());
				if (typeof payload === 'string') bucket.statsError = payload;
				else if (payload?.Error) bucket.statsError = String(payload.Error);
				else if (payload?.errors) bucket.statsError = typeof payload.errors === 'string' ? payload.errors : JSON.stringify(payload.errors);
				else bucket.stats = payload;
				bucket.statsLoaded = true;
			}
			if (feature === 'backups') {
				const payload = await apiFetch('/wp-json/captaincore/v1/site/' + encodeURIComponent(siteId) + '/' + encodeURIComponent(environmentSlug(env)) + '/backups');
				bucket.backups = extractItems(payload, siteId);
				bucket.backupsLoaded = true;
			}
			if (feature === 'quicksaves') {
				const params = new URLSearchParams({ site_id: siteId, environment: environmentSlug(env) });
				const payload = await apiFetch('/wp-json/captaincore/v1/quicksaves?' + params.toString());
				bucket.quicksaves = extractItems(payload, siteId);
				bucket.quicksavesLoaded = true;
			}
			if (feature === 'timeline') {
				let payload;
				try {
					payload = await apiFetch('/wp-json/captaincore/v1/sites/' + encodeURIComponent(siteId) + '/timeline');
				} catch (error) {
					payload = await apiFetch('/wp-json/captaincore/v1/activity-logs?site_id=' + encodeURIComponent(siteId) + '&per_page=250');
				}
				bucket.timeline = extractItems(payload, siteId);
				bucket.timelineLoaded = true;
			}
			if (feature === 'users') {
				const payload = await apiFetch('/wp-json/captaincore/v1/sites/' + encodeURIComponent(siteId) + '/users');
				bucket.users = usersForEnvironment(payload, env);
				env.users = bucket.users;
				bucket.usersLoaded = true;
			}
			if (feature === 'logs') {
				const payload = await apiFetch('/wp-json/captaincore/v1/sites/' + encodeURIComponent(siteId) + '/' + encodeURIComponent(environmentSlug(env)) + '/logs');
				bucket.logFiles = normalizeLogFiles(payload);
				if (!bucket.selectedLog || !bucket.logFiles.some(file => file.path === bucket.selectedLog)) {
					bucket.selectedLog = bucket.logFiles[0]?.path || '';
				}
				bucket.logsLoaded = true;
				if (bucket.selectedLog) {
					await fetchSiteLogFile(siteId, env, bucket.selectedLog, false);
				}
			}
		} catch (error) {
			bucket[errorKey] = error.message || 'Unable to load ' + feature + '.';
		} finally {
			bucket[loadingKey] = false;
			render();
		}
	}

	function ensureSiteFeature(feature, siteId, env) {
		if (!isSiteFeatureTab(feature) || !siteId || !env) return;
		const bucket = siteFeature(siteId, env);
		if (bucket[feature + 'Loaded'] || bucket[feature + 'Loading']) return;
		setTimeout(() => loadSiteFeature(feature, siteId, env), 0);
	}

	async function fetchSiteLogFile(siteId, env, file, shouldRender = true) {
		if (!siteId || !env || !file) return;
		const bucket = siteFeature(siteId, env);
		bucket.selectedLog = file;
		bucket.logContentLoading = true;
		bucket.logContentError = '';
		if (shouldRender) render();
		try {
			const payload = await apiFetch('/wp-json/captaincore/v1/sites/' + encodeURIComponent(siteId) + '/' + encodeURIComponent(environmentSlug(env)) + '/logs/fetch', {
				method: 'POST',
				body: JSON.stringify({
					file,
					limit: bucket.logLimit || '1000',
				}),
			});
			bucket.logContent = typeof payload === 'string' ? payload : JSON.stringify(payload, null, 2);
			bucket.logContentLoaded = true;
		} catch (error) {
			bucket.logContentError = error.message || 'Unable to fetch log file.';
		} finally {
			bucket.logContentLoading = false;
			if (shouldRender) render();
		}
	}

	function captureBaseUrl(site, env) {
		if (!site || !env || !CC.remote_upload_uri) return '';
		const slug = firstDefined(site.site, site.slug, site.name);
		const id = site.site_id || site.id;
		if (!slug || !id) return '';
		return CC.remote_upload_uri + slug + '_' + id + '/' + environmentSlug(env) + '/captures/';
	}

	function captureConfigPages(env) {
		const pages = asArray(env?.capture_pages).map(item => {
			if (typeof item === 'string') return { page: item || '/' };
			return { page: firstDefined(item?.page, item?.name, item?.path, '/') };
		}).filter(item => item.page);
		return pages.length ? pages : [{ page: '/' }];
	}

	function captureAuth(env) {
		const auth = asObject(asObject(env?.details).auth);
		return {
			username: auth.username || '',
			password: auth.password || '',
		};
	}

	function captureId(capture, index) {
		return String(firstDefined(capture?.capture_id, capture?.id, capture?.created_at, index));
	}

	function captureLabel(capture, index) {
		return firstDefined(capture?.created_at_friendly, capture?.created_at, capture?.date, 'Capture ' + (index + 1));
	}

	function normalizedCapturePages(capture) {
		return asArray(capture?.pages).map((page, index) => {
			if (typeof page === 'string') {
				return { name: page || '/', page: page || '/', image: '' };
			}
			return Object.assign({ name: firstDefined(page?.name, page?.page, page?.path, 'Page ' + (index + 1)) }, page || {});
		});
	}

	function capturePageKey(page, index) {
		return String(firstDefined(page?.name, page?.page, page?.path, page?.image, page?.image_url, index));
	}

	function selectedCapture() {
		const selected = state.captures.selectedCaptureId;
		return state.captures.captures.find((capture, index) => captureId(capture, index) === selected) || state.captures.captures[0] || null;
	}

	function selectedCapturePage(capture) {
		const pages = normalizedCapturePages(capture);
		return pages.find((page, index) => capturePageKey(page, index) === state.captures.selectedPageKey) || pages[0] || null;
	}

	function capturePageImageUrl(capture, page) {
		if (!capture || !page) return '';
		const direct = firstDefined(page.image_url, page.url, page.href);
		if (/^https?:\/\//i.test(String(direct))) return direct;
		const image = firstDefined(page.image, direct);
		if (!image) return '';
		if (/^https?:\/\//i.test(String(image))) return image;
		const base = firstDefined(capture.image_base_url, state.captures.imageBaseUrl);
		if (!base) return '';
		return base + String(image).replace(/^\/+/, '').replace(/#/g, '%23');
	}

	function setDefaultCaptureSelection() {
		const capture = selectedCapture();
		if (!capture) {
			state.captures.selectedCaptureId = '';
			state.captures.selectedPageKey = '';
			return;
		}
		const captureIndex = state.captures.captures.indexOf(capture);
		state.captures.selectedCaptureId = captureId(capture, captureIndex);
		const pages = normalizedCapturePages(capture);
		if (!pages.some((page, index) => capturePageKey(page, index) === state.captures.selectedPageKey)) {
			state.captures.selectedPageKey = pages.length ? capturePageKey(pages[0], 0) : '';
		}
	}

	function syncCaptureFormState(form) {
		const source = form || modalRegion.querySelector('[data-capture-config-form]');
		if (!source) return;
		const pages = Array.from(source.querySelectorAll('[name="capture_pages[]"]'))
			.map(input => ({ page: input.value.trim() }))
			.filter(item => item.page);
		state.captures.pages = pages.length ? pages : [{ page: '/' }];
		state.captures.auth = {
			username: source.querySelector('[name="auth_username"]')?.value || '',
			password: source.querySelector('[name="auth_password"]')?.value || '',
		};
	}

	async function openCaptures(siteId, envId) {
		const context = siteId ? findSiteAndEnv(siteId, envId) : currentSiteContext();
		const site = context.site;
		const env = context.env;
		if (!site || !env) return;
		const resolvedSiteId = site.site_id || siteId;
		const resolvedEnvId = env.environment_id || env.environment || envId;
		const envName = environmentSlug(env);
		state.drawer = null;
		state.modal = 'captures';
		state.captures = Object.assign({}, state.captures, {
			siteId: resolvedSiteId,
			envId: resolvedEnvId,
			envName,
			siteName: site.name || site.site || 'Site',
			homeUrl: env.home_url || env.link || '',
			captures: [],
			selectedCaptureId: '',
			selectedPageKey: '',
			pages: captureConfigPages(env),
			auth: captureAuth(env),
			imageBaseUrl: captureBaseUrl(site, env),
			loading: true,
			saving: false,
			requesting: false,
			error: '',
			showConfig: false,
		});
		render();
		try {
			const payload = await apiFetch('/wp-json/captaincore/v1/site/' + encodeURIComponent(resolvedSiteId) + '/' + encodeURIComponent(envName) + '/captures');
			state.captures.captures = extractItems(payload);
			const firstBase = state.captures.captures.find(capture => capture?.image_base_url)?.image_base_url;
			if (firstBase) state.captures.imageBaseUrl = firstBase;
			setDefaultCaptureSelection();
		} catch (error) {
			state.captures.error = error.message || 'Unable to load visual captures.';
		} finally {
			state.captures.loading = false;
			render();
		}
	}

	async function requestNewCapture() {
		if (!state.captures.siteId || !state.captures.envName) return;
		state.captures.requesting = true;
		render();
		try {
			await apiFetch('/wp-json/captaincore/v1/sites/' + encodeURIComponent(state.captures.siteId) + '/' + encodeURIComponent(state.captures.envName) + '/captures/new');
			showToast('Capture check started.');
		} catch (error) {
			showToast('Capture request failed.');
		} finally {
			state.captures.requesting = false;
			render();
		}
	}

	async function saveCaptureConfig(form) {
		if (!state.captures.siteId || !state.captures.envName) return;
		syncCaptureFormState(form);
		state.captures.saving = true;
		state.captures.error = '';
		render();
		try {
			await apiFetch('/wp-json/captaincore/v1/sites/' + encodeURIComponent(state.captures.siteId) + '/' + encodeURIComponent(state.captures.envName) + '/captures', {
				method: 'POST',
				body: JSON.stringify({
					pages: state.captures.pages,
					auth: state.captures.auth,
				}),
			});
			state.captures.showConfig = false;
			showToast('Capture configuration saved.');
		} catch (error) {
			state.captures.error = error.message || 'Unable to save capture configuration.';
		} finally {
			state.captures.saving = false;
			render();
		}
	}

	async function runBackupAction(command, backupIdValue) {
		const { id, env } = currentSiteContext();
		if (!id || !env || !backupIdValue) return;
		if (command === 'backup_restore' && !window.confirm('Restore this backup? This will overwrite the current site data.')) return;
		const loadingKey = command + ':' + backupIdValue;
		state.actionLoading[loadingKey] = true;
		render();
		try {
			await apiFetch('/wp-json/captaincore/v1/sites/cli', {
				method: 'POST',
				body: JSON.stringify({
					post_id: id,
					command,
					value: { backup_id: backupIdValue },
					environment: environmentSlug(env),
				}),
			});
			showToast(command === 'backup_restore' ? 'Restore started.' : 'Backup download started.');
		} catch (error) {
			showToast(command === 'backup_restore' ? 'Restore failed.' : 'Download failed.');
		} finally {
			state.actionLoading[loadingKey] = false;
			render();
		}
	}

	async function rollbackQuicksave(hash) {
		const { id, env } = currentSiteContext();
		if (!id || !env || !hash) return;
		if (!window.confirm('Rollback to this quicksave? Changes will be applied to the live site.')) return;
		const loadingKey = 'quicksave-rollback:' + hash;
		state.actionLoading[loadingKey] = true;
		render();
		try {
			await apiFetch('/wp-json/captaincore/v1/quicksaves/' + encodeURIComponent(hash) + '/rollback', {
				method: 'POST',
				body: JSON.stringify({ site_id: id, environment: environmentSlug(env) }),
			});
			showToast('Rollback started.');
		} catch (error) {
			showToast('Rollback failed.');
		} finally {
			state.actionLoading[loadingKey] = false;
			render();
		}
	}

	async function toggleQuicksaveFiles(hash) {
		const { id, env } = currentSiteContext();
		if (!id || !env || !hash) return;
		const bucket = siteFeature(id, env);
		if (bucket.expandedQuicksave === hash) {
			bucket.expandedQuicksave = '';
			render();
			return;
		}
		bucket.expandedQuicksave = hash;
		if (bucket.quicksaveFiles[hash]) {
			render();
			return;
		}
		bucket.quicksaveFilesLoading[hash] = true;
		bucket.quicksaveFilesError[hash] = '';
		render();
		try {
			const params = new URLSearchParams({ site_id: id, environment: environmentSlug(env) });
			const payload = await apiFetch('/wp-json/captaincore/v1/quicksaves/' + encodeURIComponent(hash) + '/changed?' + params.toString());
			bucket.quicksaveFiles[hash] = parseChangedFiles(payload);
		} catch (error) {
			bucket.quicksaveFilesError[hash] = error.message || 'Unable to load changed files.';
			bucket.quicksaveFiles[hash] = [];
		} finally {
			bucket.quicksaveFilesLoading[hash] = false;
			render();
		}
	}

	async function openQuicksaveDiff(hash, fileName) {
		const { id, env } = currentSiteContext();
		if (!id || !env || !hash || !fileName) return;
		const cleanName = cleanFileName(fileName);
		state.modal = 'quicksave-diff';
		state.fileDiff = { hash, file: cleanName, content: '', loading: true, error: '' };
		render();
		try {
			const params = new URLSearchParams({ site_id: id, environment: environmentSlug(env), file: cleanName });
			const payload = await apiFetch('/wp-json/captaincore/v1/quicksaves/' + encodeURIComponent(hash) + '/filediff?' + params.toString());
			state.fileDiff.content = typeof payload === 'string' ? payload : JSON.stringify(payload, null, 2);
		} catch (error) {
			state.fileDiff.error = error.message || 'Unable to load file diff.';
		} finally {
			state.fileDiff.loading = false;
			render();
		}
	}

	function getEnvironments(site) {
		const envs = Array.isArray(site?.environments) ? site.environments.slice() : [];
		return envs.sort((a, b) => {
			if (a.environment === 'Production') return -1;
			if (b.environment === 'Production') return 1;
			return String(a.environment || '').localeCompare(String(b.environment || ''));
		});
	}

	function primaryEnv(site) {
		const envs = getEnvironments(site);
		return envs.find(env => env.environment === 'Production') || envs[0] || {};
	}

	function envKey(site, env) {
		return [site?.site_id || site?.id || site?.site || '', env?.environment_id || env?.environment || 'primary'].join(':');
	}

		function screenshotUrl(site, env, size = 100) {
			if (size >= 800 && env?.screenshots?.large) return env.screenshots.large;
			if (size <= 100 && env?.screenshots?.small) return env.screenshots.small;
			if (!site || !env || !env.screenshot_base || !CC.remote_upload_uri) return '';
			const envName = String(env.environment || 'production').toLowerCase();
			return CC.remote_upload_uri + site.site + '_' + site.site_id + '/' + envName + '/screenshots/' + env.screenshot_base + '_thumb-' + size + '.jpg';
		}

	function activeFilters() {
		return state.selectedCore.length || state.selectedThemes.length || state.selectedPlugins.length;
	}

	function visibleEnvironments(site) {
		let envs = getEnvironments(site);
		if (state.envFilter === 'production') envs = envs.filter(env => env.environment === 'Production');
		if (state.envFilter === 'staging') envs = envs.filter(env => env.environment !== 'Production');
		if (state.filteredEnvIds) {
			envs = envs.filter(env => state.filteredEnvIds.has(Number(env.environment_id)) || state.filteredEnvIds.has(String(env.environment_id)));
		}
		return envs;
	}

	function visibleSites() {
		const q = normalizeText(state.search);
		let result = state.sites.slice();
		if (state.filteredSiteIds) {
			result = result.filter(site => state.filteredSiteIds.has(Number(site.site_id)) || state.filteredSiteIds.has(String(site.site_id)));
		}
		if (q) {
			result = result.filter(site => {
				const haystack = [
					site.name, site.site, site.account, site.customer, site.customer_name,
					...getEnvironments(site).flatMap(env => [env.home_url, env.environment, env.core, env.php_version]),
				].map(normalizeText).join(' ');
				return haystack.includes(q);
			});
		}
		result = result.filter(site => visibleEnvironments(site).length > 0);
		return result;
	}

	function summaryStats() {
		const sites = state.sites;
		const environments = sites.flatMap(getEnvironments);
		const production = environments.filter(env => env.environment === 'Production').length;
		const staging = environments.length - production;
		const visits = environments.reduce((sum, env) => sum + (Number(env.visits) || 0), 0);
		const storage = environments.reduce((sum, env) => sum + (Number(env.storage) || 0), 0);
		return { sites: sites.length, environments: environments.length, production, staging, visits, storage };
	}

	function renderChrome() {
		const name = CC.configurations?.name || 'CaptainCore';
		const logo = CC.configurations?.logo || '';
		const logoWidth = CC.configurations?.logo_width || '';
		const logoOnly = CC.configurations?.logo_only === true;
		const logoEl = document.querySelector('[data-brand-logo]');
		const nameEl = document.querySelector('[data-brand-name]');
		if (logo) {
			logoEl.hidden = false;
			logoEl.src = logo;
			logoEl.style.maxWidth = logoWidth ? logoWidth + 'px' : '';
			logoEl.alt = name;
		} else {
			logoEl.hidden = true;
		}
		nameEl.hidden = !!logo && logoOnly;
		nameEl.textContent = name;
		document.querySelector('[data-user-avatar]').textContent = initials(CC.user?.display_name || CC.user?.login);
		document.querySelector('[data-user-name]').textContent = CC.user?.display_name || CC.user?.login || '';

		const links = [
			['/sites', 'Sites', true],
			['/domains', 'Domains', true],
			['/accounts', 'Accounts', true],
			['/billing', 'Billing', !!CC.modules?.billing],
		].filter(item => item[2]);
		navRegion.innerHTML = links.map(([route, label]) => {
			const active = state.route === route || state.route.startsWith(route + '/');
			return '<a href="' + attr(routeUrl(route)) + '" data-route="' + attr(route) + '" class="' + (active ? 'active' : '') + '">' + esc(label) + '</a>';
		}).join('');
	}

	function render() {
		renderChrome();
		app.classList.toggle('is-login', state.route === '/login' || !state.nonce);
		document.body.classList.toggle('cc-v2-lock', !!state.drawer || !!state.modal || (state.terminal.open && state.terminal.fullscreen));
		if (state.route === '/login' || !state.nonce) {
			main.className = '';
			main.innerHTML = renderLogin();
		} else {
			main.className = 'app-shell';
			if (state.route.startsWith('/sites/')) main.innerHTML = renderSiteDetail();
			else if (state.route.startsWith('/sites')) main.innerHTML = renderSites();
			else if (state.route.startsWith('/domains/')) main.innerHTML = renderDomainDetail();
			else if (state.route.startsWith('/domains')) main.innerHTML = renderDirectory('Domains', 'domains');
			else if (state.route.startsWith('/accounts/')) main.innerHTML = renderAccountDetail();
			else if (state.route.startsWith('/accounts')) main.innerHTML = renderDirectory('Accounts', 'accounts');
			else if (state.route.startsWith('/billing')) main.innerHTML = renderDirectory('Billing', 'subscriptions');
			else main.innerHTML = renderSites();
		}
		renderBulkBar();
		renderDrawer();
		renderModal();
		renderTerminal();
	}

	function renderLogin() {
		return `
			<div class="login-shell">
				<form class="login-panel" data-action="sign-in">
					<h1>${esc(CC.configurations?.name || 'CaptainCore')}</h1>
					<p class="sub">Account access</p>
					<div class="form-grid">
						${state.login.errors ? '<div class="alert error">' + esc(state.login.errors) + '</div>' : ''}
						${state.login.info ? '<div class="alert info">' + esc(state.login.info) + '</div>' : ''}
						<div class="form-field">
							<label for="cc-login-user">Username</label>
							<input id="cc-login-user" name="user_login" autocomplete="username" value="${attr(state.login.user_login)}">
						</div>
						<div class="form-field">
							<label for="cc-login-password">Password</label>
							<input id="cc-login-password" name="user_password" type="password" autocomplete="current-password" value="${attr(state.login.user_password)}">
						</div>
						${state.login.info ? '<div class="form-field"><label for="cc-login-tfa">Two-factor code</label><input id="cc-login-tfa" name="tfa_code" inputmode="numeric" value="' + attr(state.login.tfa_code) + '"></div>' : ''}
						<button class="primary-btn" type="submit">${state.login.loading ? 'Signing in' : 'Sign In'}</button>
					</div>
				</form>
			</div>
		`;
	}

		function renderPageHeader(title, sub, pills = '') {
			return `
				<div class="summary">
					<div class="summary__title">
						<h1>${esc(title)}</h1>
					<span class="summary__sub">${esc(sub)}</span>
				</div>
				<div class="summary__pills">${pills}</div>
				</div>
			`;
		}

		function tabCount(value) {
			const n = Number(value);
			return Number.isFinite(n) && n > 0 ? formatNumber(n) : '';
		}

		function renderSiteTabs(tabs, active) {
			return `
				<nav class="site-detail-tabs" aria-label="Site sections">
					${tabs.map(tab => `
						<button type="button" class="${tab.key === active ? 'active' : ''}" data-action="detail-tab" data-detail-type="site" data-tab="${attr(tab.key)}">
							${tab.icon || ''}
							<span>${esc(tab.label)}</span>
							${tab.count ? '<span class="site-tab-count">' + esc(tab.count) + '</span>' : ''}
						</button>
					`).join('')}
				</nav>
			`;
		}

		function renderSiteKv(label, value, action = '') {
			const display = value == null || value === '' ? '-' : String(value);
			return `
				<div class="site-kv">
					<div>
						<div class="site-kv__label">${esc(label)}</div>
						<div class="site-kv__value">${esc(display)}</div>
					</div>
					${action}
				</div>
			`;
		}

		function maskedSecret(value) {
			const length = Math.min(16, Math.max(8, String(value || '').length));
			return '*'.repeat(length);
		}

		function renderSiteSecretSection(key, title, icon, rows) {
			const cleanRows = rows.filter(row => row && row.value !== undefined && row.value !== null && row.value !== '');
			if (!cleanRows.length) return '';
			const revealed = !!state.revealedSecrets[key];
			return `
				<div class="site-secret-section">
					<div class="site-secret-section__head">
						<div class="site-secret-title">${icon || ''}<span>${esc(title)}</span></div>
						<button class="site-secret-toggle" type="button" data-action="toggle-site-secret" data-secret-key="${attr(key)}">${icons.eye}<span>${revealed ? 'Hide' : 'Reveal'}</span></button>
					</div>
					${cleanRows.map(row => {
						const value = String(row.value);
						const display = row.secret && !revealed ? maskedSecret(value) : value;
						const copy = row.copy === false ? '' : '<button class="site-icon-btn" type="button" data-action="copy-value" data-value="' + attr(value) + '" title="Copy" aria-label="Copy">' + icons.copy + '</button>';
						return `
							<div class="site-secret-row">
								<div class="site-secret-row__label">${esc(row.label)}</div>
								<div class="site-secret-row__value ${row.secret && !revealed ? 'masked' : ''}">${esc(display)}</div>
								${copy}
							</div>
						`;
					}).join('')}
				</div>
			`;
		}

		function renderSharedWith(items) {
			const accounts = asArray(items);
			if (!accounts.length) return '';
			return `
				<div>
					<div class="site-subhead">Shared With</div>
					<div class="site-shared-grid">
						${accounts.map(account => `
							<div class="site-shared-pill">
								<div>
									<strong>${esc(account.name || 'Account')}</strong>
									<span>${account.account_id ? 'Account #' + esc(account.account_id) : 'Account'}</span>
								</div>
								${icons.users}
							</div>
						`).join('')}
					</div>
				</div>
			`;
		}

		function copyValue(value) {
			const text = String(value || '');
			if (!text) return;
			if (navigator.clipboard) {
				navigator.clipboard.writeText(text).then(() => showToast('Copied.'));
			}
		}

		function renderSites() {
		const stats = summaryStats();
		const filtered = visibleSites();
		const start = (state.page - 1) * state.perPage;
		const paginated = filtered.slice(start, start + state.perPage);
		const pages = Math.max(1, Math.ceil(filtered.length / state.perPage));
		if (state.page > pages) state.page = pages;
		const pills = `
			<span class="pill"><span class="dot ok"></span><b>${formatNumber(stats.production)}</b> production</span>
			<span class="pill"><span class="dot warn"></span><b>${formatNumber(stats.staging)}</b> staging</span>
			<span class="pill"><b>${formatStorage(stats.storage)}</b> storage</span>
			<span class="pill"><b>${formatNumber(stats.visits)}</b> visits</span>
		`;
		return `
			${renderPageHeader('Sites', `${formatNumber(stats.sites)} sites - ${formatNumber(stats.environments)} environments`, pills)}
			${renderToolbar('Search sites, URLs, accounts...', true)}
			${renderFilters()}
			${state.error.sites ? renderError(state.error.sites, 'refresh-sites') : ''}
			${state.loading.sites ? renderLoading('Loading sites') : ''}
			${!state.loading.sites && !state.error.sites ? (state.viewMode === 'grid' ? renderSiteGrid(paginated, filtered.length, pages) : renderSiteList(paginated, filtered.length, pages)) : ''}
		`;
	}

	function renderToolbar(placeholder, allowAdd) {
		return `
			<div class="toolbar">
				<div class="search-input">
					${icons.search}
					<input id="cc-v2-search" type="search" value="${attr(state.search)}" placeholder="${attr(placeholder)}" data-input="search">
					<span class="scope">${state.envFilter === 'all' ? 'all environments' : state.envFilter}</span>
				</div>
				<div class="segmented" role="tablist" aria-label="View">
					<button type="button" class="${state.viewMode === 'list' ? 'active' : ''}" data-action="set-view" data-view="list" title="List" aria-label="List">${icons.list}</button>
					<button type="button" class="${state.viewMode === 'grid' ? 'active' : ''}" data-action="set-view" data-view="grid" title="Grid" aria-label="Grid">${icons.grid}</button>
				</div>
				${allowAdd && CC.user?.role === 'administrator' ? '<button class="primary-btn" type="button" data-action="open-new-site">' + icons.plus + ' New Site</button>' : ''}
			</div>
		`;
	}

	function renderFilters() {
		return `
			<div class="chips">
				<button class="chip-filter ${state.envFilter === 'all' ? 'active' : ''}" type="button" data-action="env-filter" data-filter="all">All <span class="count">${formatNumber(state.sites.length)}</span></button>
				<button class="chip-filter ${state.envFilter === 'production' ? 'active' : ''}" type="button" data-action="env-filter" data-filter="production">Production</button>
				<button class="chip-filter ${state.envFilter === 'staging' ? 'active' : ''}" type="button" data-action="env-filter" data-filter="staging">Staging</button>
				${renderFilterPopover('core', 'Core', state.selectedCore, CC.site_filters_core || [])}
				${renderFilterPopover('themes', 'Theme', state.selectedThemes, (CC.site_filters || []).filter(f => f.type === 'themes'))}
				${renderFilterPopover('plugins', 'Plugin', state.selectedPlugins, (CC.site_filters || []).filter(f => f.type === 'plugins'))}
				${activeFilters() ? '<button class="chip-filter" type="button" data-action="clear-filters">Clear</button>' : ''}
			</div>
		`;
	}

	function renderFilterPopover(type, label, selected, options) {
		const selectedNames = new Set(selected.map(item => item.name));
		const q = normalizeText(state.filterSearch[type]);
		const filtered = options.filter(item => !q || normalizeText(item.search || item.title || item.name).includes(q)).slice(0, 80);
		return `
			<div class="chip-wrap">
				<button class="chip-filter ${selected.length ? 'active' : ''}" type="button" data-action="toggle-popover" data-popover="${attr(type)}">
					${esc(label)} ${selected.length ? '<span class="count">' + selected.length + '</span>' : ''}
				</button>
				<div class="filter-pop ${state.popover === type ? 'open' : ''}" data-popover-panel="${attr(type)}">
					<div class="filter-pop__head">
						<div class="meta-label">${esc(label)}</div>
						<div class="filter-search">${icons.search}<input type="search" value="${attr(state.filterSearch[type])}" data-input="filter-search" data-filter-type="${attr(type)}" placeholder="Search ${attr(label.toLowerCase())}"></div>
					</div>
					<div class="filter-list">
						${filtered.length ? filtered.map((item, index) => `
							<button type="button" class="filter-option ${selectedNames.has(item.name) ? 'active' : ''}" data-action="toggle-filter" data-filter-type="${attr(type)}" data-filter-index="${index}">
								<span class="name">${esc(item.title || item.name)}</span>
								<span class="muted">${esc(item.count || item.name || '')}</span>
							</button>
						`).join('') : '<div class="empty-mini">No matches</div>'}
					</div>
				</div>
			</div>
		`;
	}

	function renderSiteList(sites, total, pages) {
		if (!sites.length) return renderEmpty('No sites match the current view.');
		return `
			<div class="sites">
				<div class="sites__head">
					<div><input type="checkbox" aria-label="Select all" data-action="select-all" ${allVisibleSelected(sites) ? 'checked' : ''}></div>
					<div>Site</div>
					<div class="col-wp">WP / PHP</div>
					<div class="col-visits">Visits</div>
					<div class="col-storage">Storage</div>
					<div class="col-backup">Subsites</div>
					<div>Status</div>
					<div></div>
				</div>
				${sites.map(renderSiteGroup).join('')}
				<div class="sites__foot">
					<span>Showing ${formatNumber(sites.length)} of ${formatNumber(total)} sites</span>
					<div class="pager">
						<button type="button" data-action="prev-page" ${state.page <= 1 ? 'disabled' : ''} aria-label="Previous">Prev</button>
						<button type="button" disabled>${state.page} / ${pages}</button>
						<button type="button" data-action="next-page" ${state.page >= pages ? 'disabled' : ''} aria-label="Next">Next</button>
					</div>
				</div>
			</div>
		`;
	}

	function renderSiteGroup(site) {
		const envs = visibleEnvironments(site);
		const account = site.customer_name || site.account || site.customer || '';
		return `
			<div class="site-group">
				<div class="site-group__bar">
					<span class="domain">${esc(site.name || site.site || 'Untitled site')}</span>
					<span class="meta">${envs.length} environment${envs.length === 1 ? '' : 's'}${account ? ' - ' + esc(account) : ''}</span>
				</div>
				${envs.map(env => renderEnvironmentRow(site, env)).join('')}
			</div>
		`;
	}

	function renderEnvironmentRow(site, env) {
		const key = envKey(site, env);
		const selected = state.selection.has(key);
		const screenshot = screenshotUrl(site, env, 100);
		const envName = env.environment || 'Production';
		const production = envName === 'Production';
		const status = environmentStatus(env);
		return `
			<div role="button" tabindex="0" class="site-row ${selected ? 'selected' : ''}" data-action="open-drawer" data-site-id="${attr(site.site_id)}" data-env-id="${attr(env.environment_id || envName)}">
				<div><input type="checkbox" aria-label="Select" ${selected ? 'checked' : ''} data-action="toggle-select" data-site-id="${attr(site.site_id)}" data-env-id="${attr(env.environment_id || envName)}"></div>
				<div class="site-row__main">
					${screenshot ? '<img class="site-thumb" src="' + attr(screenshot) + '" alt="" loading="lazy">' : '<div class="site-thumb placeholder">' + icons.monitor + '</div>'}
					<div class="site-info">
						<div class="url">${esc(formatUrl(env.home_url || site.site || site.name))}</div>
						<div class="badges">
							<span class="badge ${production ? 'production' : 'staging'}">${esc(envName)}</span>
							${env.core ? '<span class="badge">WP ' + esc(env.core) + '</span>' : ''}
							${env.php_version ? '<span>PHP ' + esc(env.php_version) + '</span>' : ''}
						</div>
					</div>
				</div>
				<div class="metric col-wp"><span class="label">Core</span><span class="val">${esc(env.core || '-')}</span></div>
				<div class="metric col-visits"><span class="label">Visits</span><span class="val">${formatNumber(env.visits)}</span></div>
				<div class="metric col-storage"><span class="label">Storage</span><span class="val">${formatStorage(env.storage)}</span></div>
				<div class="metric col-backup"><span class="label">Subsites</span><span class="val">${formatNumber(env.subsite_count || 0)}</span></div>
				<div class="status-cell"><span class="dot ${status.dot}"></span>${esc(status.label)}</div>
				<div class="row-actions">
					<button type="button" class="row-action" title="WP Admin" aria-label="WP Admin" data-action="magic-login" data-site-id="${attr(site.site_id)}" data-env-id="${attr(env.environment_id || envName)}">${icons.login}</button>
					${env.home_url ? '<button type="button" class="row-action" title="Open" aria-label="Open" data-action="open-url" data-url="' + attr(env.home_url) + '">' + icons.external + '</button>' : ''}
				</div>
			</div>
		`;
	}

	function environmentStatus(env) {
		if (String(env?.status || '').toLowerCase() === 'inactive') return { label: 'Inactive', dot: 'bad' };
		if (visibleConsoleErrorsForEnvironment(env).length) return { label: 'Errors', dot: 'warn' };
		if (Number(env?.updates) > 0 || Number(env?.plugin_updates) > 0 || Number(env?.theme_updates) > 0) return { label: 'Updates', dot: 'warn' };
		return { label: 'Healthy', dot: 'ok' };
	}

	function renderSiteGrid(sites, total, pages) {
		if (!sites.length) return renderEmpty('No sites match the current view.');
		return `<div class="grid-sites">${sites.map(site => {
			const env = primaryEnv(site);
			const shot = screenshotUrl(site, env, 800);
			return `
				<button type="button" class="grid-card" data-action="open-drawer" data-site-id="${attr(site.site_id)}" data-env-id="${attr(env.environment_id || env.environment || 'Production')}">
					<div class="grid-preview">
						${shot ? '<img src="' + attr(shot) + '" alt="" loading="lazy">' : '<div class="placeholder">' + icons.monitor + '</div>'}
					</div>
					<div class="grid-card__body">
						<div class="grid-card__title">${esc(site.name || site.site || 'Untitled site')}</div>
						<div class="badges">
							<span class="badge ${env.environment === 'Production' ? 'production' : 'staging'}">${esc(env.environment || 'Production')}</span>
							${env.core ? '<span class="badge">WP ' + esc(env.core) + '</span>' : ''}
						</div>
					</div>
				</button>
			`;
		}).join('')}</div>
		<div class="pagination-foot">
			<span>Showing ${formatNumber(sites.length)} of ${formatNumber(total)} sites</span>
			<div class="pager">
				<button type="button" data-action="prev-page" ${state.page <= 1 ? 'disabled' : ''} aria-label="Previous">Prev</button>
				<button type="button" disabled>${state.page} / ${pages}</button>
				<button type="button" data-action="next-page" ${state.page >= pages ? 'disabled' : ''} aria-label="Next">Next</button>
			</div>
		</div>`;
	}

	function renderSiteDetail() {
		const id = routeId('sites');
		const key = detailKey('site', id);
		const data = state.siteDetails[id];
		if (state.loading[key] && !data) return renderLoading('Loading site');
		if (state.error[key] && !data) return renderError(state.error[key], 'refresh-site-detail');
			if (!data) return renderEmpty('Site not found.');

			const listSite = state.sites.find(item => String(item.site_id) === String(id));
			const site = Object.assign({}, listSite || {}, data.site || {});
			const details = Object.assign({}, data.details || {}, {
				account: data.account,
				domains: data.domains,
				shared_with: data.shared_with,
			});
			const envs = getEnvironments({ environments: data.environments || site.environments || [] });
			let selected = state.detailEnvironments[id];
			let env = envs.find(item => String(item.environment_id) === String(selected)) || envs.find(item => String(item.environment) === String(selected));
			if (!env && envs.length) {
				env = envs.find(item => item.environment === 'Production') || envs[0];
				state.detailEnvironments[id] = env.environment_id || env.environment || 'Production';
			}
			const envDetails = asObject(env?.details);
			const addonCount = asArray(env?.plugins).length + asArray(env?.themes).length;
			const featureBucket = siteFeature(id, env || {});
			const userCount = featureBucket.usersLoaded ? featureBucket.users.length : asArray(env?.users).length;
			const consoleErrorCount = visibleConsoleErrorsForEnvironment(env).length;
			const backupCount = Number(envDetails.backup_count || 0);
			const quicksaveCount = Number(envDetails.quicksave_count || envDetails.quicksave_usage?.count || 0);
			const tabs = [
				{ key: 'overview', label: 'Info', icon: icons.info },
				{ key: 'stats', label: 'Stats', icon: icons.chart },
				{ key: 'logs', label: 'Logs', icon: icons.file, count: tabCount(consoleErrorCount) },
				{ key: 'addons', label: 'Addons', icon: icons.plug, count: tabCount(addonCount) },
				{ key: 'users', label: 'Users', icon: icons.users, count: tabCount(userCount) },
				{ key: 'scripts', label: 'Scripts', icon: icons.terminal },
				{ key: 'backups', label: 'Backups', icon: icons.refresh, count: tabCount(backupCount) },
				{ key: 'quicksaves', label: 'Quicksaves', icon: icons.save, count: tabCount(quicksaveCount) },
				{ key: 'timeline', label: 'Timeline', icon: icons.clock },
				{ key: 'domains', label: 'Domains', icon: icons.globe, count: tabCount(asArray(details.domains).length) },
			];
			const routeTab = siteTabFromRoute();
			if (routeTab) state.detailTabs.site = routeTab;
			const active = tabs.some(tab => tab.key === state.detailTabs.site) ? state.detailTabs.site : 'overview';
			state.detailTabs.site = active;
			ensureSiteFeature(active, id, env);
			const panels = {
				overview: renderSiteOverview(site, env, details),
			stats: renderSiteStats(site, env),
			backups: renderSiteBackups(site, env),
			quicksaves: renderSiteQuicksaves(site, env),
			timeline: renderSiteTimeline(site, env),
			addons: renderSiteAddons(env),
			domains: renderSiteDomains(details, env),
			users: renderSiteUsers(site, env),
			logs: renderSiteLogs(details, env),
				scripts: renderSiteScripts(site, env),
			};
			const account = site.account || site.customer_name || site.customer || details.account?.name || '';
			const heroShot = env ? screenshotUrl(site, env, 100) : '';
			const siteTitle = site.name || site.site || 'Site';
			const siteUrl = env?.home_url || env?.link || '';
			const created = firstDefined(env?.created_at, site.created_at);
			const envLabel = env?.environment || 'Environment';
			const envClass = envLabel === 'Production' ? 'production' : 'staging';
			return `
				<div class="site-detail-shell">
					<div class="site-crumb">
						<button type="button" data-route="/sites">Sites</button>
						<span class="site-crumb__sep">/</span>
						<span>${esc(siteTitle)}</span>
					</div>
					<section class="site-hero">
						<div class="site-hero__thumb">${heroShot ? '<img src="' + attr(heroShot) + '" alt="" loading="lazy">' : icons.monitor}</div>
						<div class="site-hero__main">
							<div class="site-hero__title">
								<h1>${esc(siteTitle)}</h1>
								${env ? '<span class="site-env-badge ' + envClass + '">' + esc(envLabel) + '</span>' : ''}
							</div>
							<div class="site-hero__meta">
								${icons.globe}
								${siteUrl ? '<button type="button" data-action="open-url" data-url="' + attr(siteUrl) + '">' + esc(siteUrl) + '</button>' : '<span>No primary URL</span>'}
								${created ? '<span>-</span><span>created ' + esc(formatDate(created)) + '</span>' : ''}
								${account ? '<span>-</span><span>' + esc(account) + '</span>' : ''}
							</div>
						</div>
						<div class="site-hero__actions">
							${siteUrl ? '<button class="site-action-btn" type="button" data-action="open-url" data-url="' + attr(siteUrl) + '">' + icons.external + '<span>Open</span></button>' : ''}
							${env ? '<button class="site-action-btn primary" type="button" data-action="magic-login" data-site-id="' + attr(site.site_id || id) + '" data-env-id="' + attr(env.environment_id || env.environment) + '"><span>Login to WordPress</span>' + icons.external + '</button>' : ''}
						</div>
					</section>
					<div class="site-detail-toolbar">
						${envs.length ? '<div class="site-env-switch" role="tablist" aria-label="Environment">' + envs.map(item => '<button type="button" class="' + (String(item.environment_id || item.environment) === String(state.detailEnvironments[id]) ? 'active' : '') + '" data-action="select-detail-env" data-site-id="' + attr(id) + '" data-env-id="' + attr(item.environment_id || item.environment) + '">' + esc(item.environment || 'Environment') + '</button>').join('') + '</div>' : '<span class="summary__sub">No environments</span>'}
						<div class="site-toolbar-actions">
							<button class="site-action-btn" type="button" data-action="refresh-site-detail">${icons.refresh}<span>Refresh</span></button>
							${env ? '<button class="site-action-btn" type="button" data-action="open-terminal-current">' + icons.terminal + '<span>Terminal</span></button>' : ''}
							<button class="site-action-btn" type="button" data-action="detail-tab" data-detail-type="site" data-tab="timeline">${icons.clock}<span>Timeline</span></button>
						</div>
					</div>
					<div class="site-detail-body">
						${renderSiteTabs(tabs, active)}
						<div class="site-detail-panel">${panels[active] || panels.overview}</div>
					</div>
				</div>
			`;
		}

		function renderSiteOverview(site, env, details) {
			if (!env) return renderEmpty('No environment data available.');
			const status = environmentStatus(env);
			const envDetails = asObject(env.details);
			const created = firstDefined(site.created_at, env.created_at, details.created_at);
			const updated = firstDefined(site.updated_at, env.updated_at, details.updated_at);
			const shot = screenshotUrl(site, env, 800);
			const storageLimit = details.account?.plan?.limits?.storage ? Number(details.account.plan.limits.storage) * 1073741824 : 0;
			const storageLabel = formatStorage(env.storage) + (storageLimit ? ' used / ' + formatStorage(storageLimit) + ' plan' : ' used');
			const phpVersion = firstDefined(env.php_version, envDetails.php_version);
			const memoryLimit = firstDefined(env.php_memory, envDetails.php_memory);
			const captures = firstDefined(env.captures, envDetails.captures, envDetails.capture_count);
			const dbSize = firstDefined(envDetails.db_size, env.database_size);
			const sftpKey = 'sftp:' + (env.environment_id || env.environment || routeId('sites'));
			const dbKey = 'database:' + (env.environment_id || env.environment || routeId('sites'));
			const captureButtonAttrs = 'data-action="open-captures" data-site-id="' + attr(site.site_id || routeId('sites')) + '" data-env-id="' + attr(env.environment_id || env.environment) + '"';
			return `
				<section class="site-section">
					<div class="site-section__head">
						<div>
							<div class="site-section__sub">/ Info</div>
							<h2>Site overview</h2>
						</div>
						${statusBadge(status.label)}
					</div>
					<div class="site-overview-grid">
						<div class="site-overview-col">
							<button class="site-preview-frame" type="button" ${captureButtonAttrs} aria-label="Open visual captures">
								${shot ? '<img src="' + attr(shot) + '" alt="" loading="lazy">' : '<div class="site-preview-frame__empty">' + icons.image + '<span>No visual capture yet.</span></div>'}
								<span class="site-preview-frame__overlay">${icons.image}<span>Visual captures</span></span>
							</button>
							<div>
								<div class="site-subhead">Site Details</div>
								<div class="site-kv-list">
									${renderSiteKv('Link', env.home_url || env.link || '-', env.home_url ? '<button class="site-icon-btn" type="button" data-action="open-url" data-url="' + attr(env.home_url) + '" title="Visit site" aria-label="Visit site">' + icons.external + '</button>' : '')}
									${renderSiteKv('Created', formatDate(created), '')}
									${renderSiteKv('WordPress version', env.core || '-', '<button class="site-icon-btn" type="button" data-action="copy-value" data-value="' + attr(env.core || '') + '" title="Copy" aria-label="Copy">' + icons.copy + '</button>')}
									${renderSiteKv('PHP / memory', [phpVersion ? 'PHP ' + phpVersion : '', memoryLimit ? memoryLimit + ' memory' : ''].filter(Boolean).join(' / ') || '-')}
									${renderSiteKv('Storage', storageLabel)}
									${renderSiteKv('Visits', formatNumber(env.visits))}
									${renderSiteKv('Visual captures', captures ? formatNumber(captures) + ' captures' : '-', '<button class="site-icon-btn" type="button" ' + captureButtonAttrs + ' title="View visual captures" aria-label="View visual captures">' + icons.image + '</button>')}
									${renderSiteKv('Updated', formatDate(updated))}
								</div>
							</div>
						</div>
						<div class="site-overview-col">
							<div>
								<div class="site-subhead">Connection & Credentials</div>
								${renderSiteSecretSection(sftpKey, 'SFTP', icons.lock, [
									{ label: 'Address', value: env.address, secret: true },
									{ label: 'Username', value: env.username, secret: true },
									{ label: 'Password', value: env.password, secret: true },
									{ label: 'Protocol / Port', value: [env.protocol, env.port].filter(Boolean).join(' / '), copy: false },
									{ label: 'Home directory', value: env.home_directory },
									{ label: 'SSH', value: env.ssh },
								])}
								${renderSiteSecretSection(dbKey, 'Database', icons.database, [
									{ label: 'Manager', value: env.database ? 'PHPMyAdmin' : '', copy: false },
									{ label: 'DB name', value: env.database_name, secret: true },
									{ label: 'DB user', value: env.database_username, secret: true },
									{ label: 'DB password', value: env.database_password, secret: true },
									{ label: 'DB host', value: env.database_host },
									{ label: 'DB size', value: dbSize ? formatStorage(dbSize) : '', copy: false },
								])}
							</div>
							${renderSharedWith(details.shared_with)}
						</div>
					</div>
				</section>
				<section class="site-section">
					<div class="site-section__sub">/ Site options</div>
					<h2>Environment actions</h2>
					<div class="site-action-strip" style="margin-top:14px">
						<button class="site-action-btn" type="button" data-action="detail-tab" data-detail-type="site" data-tab="domains">${icons.globe}<span>Configure domains</span></button>
						<button class="site-action-btn" type="button" data-action="detail-tab" data-detail-type="site" data-tab="backups">${icons.refresh}<span>Manage backups</span></button>
						<button class="site-action-btn" type="button" data-action="detail-tab" data-detail-type="site" data-tab="scripts">${icons.terminal}<span>Run scripts</span></button>
						<button class="site-action-btn" type="button" data-action="open-terminal-current">${icons.terminal}<span>Open terminal</span></button>
					</div>
				</section>
			`;
		}

	function isoDate(date) {
		if (!(date instanceof Date) || Number.isNaN(date.getTime())) return dateOffsetIso(0);
		return date.toISOString().slice(0, 10);
	}

	function dateFromIso(value) {
		const date = new Date(String(value || dateOffsetIso(0)) + 'T00:00:00');
		return Number.isNaN(date.getTime()) ? new Date() : date;
	}

	function applyStatsTimeframe(range) {
		const to = dateFromIso(state.siteStats.to_at);
		const from = new Date(to);
		if (range === '7d') {
			state.siteStats.grouping = 'Day';
			from.setDate(from.getDate() - 7);
		} else if (range === '30d') {
			state.siteStats.grouping = 'Day';
			from.setDate(from.getDate() - 30);
		} else if (range === '5y') {
			state.siteStats.grouping = 'Year';
			from.setFullYear(from.getFullYear() - 5);
		} else {
			state.siteStats.grouping = 'Month';
			from.setFullYear(from.getFullYear() - 1);
			range = '12m';
		}
		state.siteStats.from_at = isoDate(from);
		state.siteStats.to_at = isoDate(to);
		state.siteStats.timeframe = range;
		refreshStatsForCurrent();
	}

	function adjustStatsRangeForGrouping(grouping) {
		const to = dateFromIso(state.siteStats.to_at);
		const from = new Date(to);
		if (grouping === 'Hour') from.setDate(from.getDate() - 7);
		else if (grouping === 'Day') from.setMonth(from.getMonth() - 1);
		else if (grouping === 'Year') from.setFullYear(from.getFullYear() - 5);
		else from.setFullYear(from.getFullYear() - 1);
		state.siteStats.from_at = isoDate(from);
	}

	function refreshStatsForCurrent() {
		const { id, env } = currentSiteContext();
		if (!id || !env) return;
		const bucket = siteFeature(id, env);
		bucket.statsLoaded = false;
		loadSiteFeature('stats', id, env, true);
	}

	function statsTrackerValue(tracker) {
		return String(firstDefined(tracker?.code, tracker?.id, tracker?.fathom_id, tracker?.value));
	}

	function statsTrackerLabel(tracker) {
		return firstDefined(tracker?.domain, tracker?.name, tracker?.site, tracker?.code, tracker?.id, tracker?.fathom_id, 'Tracker');
	}

	function statsSharingValue(site) {
		const value = String(site?.sharing || 'none').toLowerCase();
		return ['public', 'private'].includes(value) ? value : 'none';
	}

	function statsShareUrl(site) {
		const id = String(site?.id || '').toLowerCase();
		const name = String(site?.name || '').trim();
		if (!id || !name) return '';
		return 'https://app.usefathom.com/share/' + encodeURIComponent(id) + '/' + encodeURIComponent(name);
	}

	function renderStatsChart(items) {
		const rows = asArray(items).slice(-80);
		if (!rows.length) return '';
		const width = 760;
		const height = 315;
		const left = 54;
		const right = 18;
		const top = 16;
		const bottom = 280;
		const plotWidth = width - left - right;
		const plotHeight = bottom - top;
		const max = Math.max(1, ...rows.flatMap(item => [Number(item.visits) || 0, Number(item.pageviews) || 0]));
		const point = (item, index, field) => {
			const x = left + (rows.length === 1 ? plotWidth / 2 : index * plotWidth / Math.max(1, rows.length - 1));
			const y = bottom - ((Number(item[field]) || 0) / max) * plotHeight;
			return { x, y };
		};
		const pageviewPoints = rows.map((item, index) => point(item, index, 'pageviews'));
		const visitPoints = rows.map((item, index) => point(item, index, 'visits'));
		const pointString = points => points.map(p => p.x.toFixed(1) + ',' + p.y.toFixed(1)).join(' ');
		const areaPath = points => {
			if (!points.length) return '';
			return 'M ' + points[0].x.toFixed(1) + ' ' + bottom + ' L ' + points.map(p => p.x.toFixed(1) + ' ' + p.y.toFixed(1)).join(' L ') + ' L ' + points[points.length - 1].x.toFixed(1) + ' ' + bottom + ' Z';
		};
		const ticks = [0, .25, .5, .75, 1].map(scale => {
			const value = Math.round(max * scale);
			const y = bottom - scale * plotHeight;
			return { value, y };
		});
		const labelIndexes = rows.length <= 8
			? rows.map((row, index) => index)
			: [0, Math.floor((rows.length - 1) / 2), rows.length - 1];
		const hoverPoints = rows.map((item, index) => {
			const pagePoint = pageviewPoints[index];
			const visitPoint = visitPoints[index];
			const previousPoint = pageviewPoints[index - 1];
			const nextPoint = pageviewPoints[index + 1];
			const zoneLeft = previousPoint ? (previousPoint.x + pagePoint.x) / 2 : 0;
			const zoneRight = nextPoint ? (pagePoint.x + nextPoint.x) / 2 : width;
			const zoneWidth = Math.max(1, zoneRight - zoneLeft);
			const zoneLeftPct = zoneLeft / width * 100;
			const zoneWidthPct = zoneWidth / width * 100;
			const pointLeftPct = (pagePoint.x - zoneLeft) / zoneWidth * 100;
			const chartXPct = pagePoint.x / width * 100;
			const yPct = Math.min(pagePoint.y, visitPoint.y) / height * 100;
			const edge = chartXPct < 18 ? ' edge-left' : (chartXPct > 82 ? ' edge-right' : '');
			return `
				<button class="stats-hover-point${edge}" type="button" style="left:${zoneLeftPct.toFixed(4)}%;width:${zoneWidthPct.toFixed(4)}%;--point-left:${pointLeftPct.toFixed(4)}%;--point-top:${yPct.toFixed(4)}%" aria-label="${attr((item.date || 'Period') + ' stats')}">
					<span class="stats-tooltip">
						<strong>${esc(item.date || 'Period')}</strong>
						<span class="pageviews">Pageviews: ${formatNumber(item.pageviews)}</span>
						<span class="visits">Visitors: ${formatNumber(item.visits)}</span>
					</span>
				</button>
			`;
		}).join('');
		return `
			<div class="stats-chart">
				<div class="stats-chart__legend"><span class="stats-key pageviews">Pageviews</span><span class="stats-key">Visitors</span></div>
				<div class="stats-chart__plot">
					<svg viewBox="0 0 ${width} ${height}" role="img" aria-label="Pageviews and visitors chart">
						${ticks.map(tick => `
							<line x1="${left}" y1="${tick.y.toFixed(1)}" x2="${width - right}" y2="${tick.y.toFixed(1)}" stroke="var(--rule)" />
							<text class="stats-axis-label" x="${left - 8}" y="${(tick.y + 4).toFixed(1)}" text-anchor="end">${esc(formatCompactNumber(tick.value))}</text>
						`).join('')}
						<path d="${attr(areaPath(pageviewPoints))}" fill="color-mix(in oklab, var(--ink) 13%, transparent)" />
						<path d="${attr(areaPath(visitPoints))}" fill="color-mix(in oklab, var(--brand) 18%, transparent)" />
						<polyline points="${attr(pointString(pageviewPoints))}" fill="none" stroke="color-mix(in oklab, var(--ink) 72%, transparent)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
						<polyline points="${attr(pointString(visitPoints))}" fill="none" stroke="var(--brand)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
						${labelIndexes.map(index => {
							const p = pageviewPoints[index] || { x: left };
							const anchor = index === 0 ? 'start' : (index === rows.length - 1 ? 'end' : 'middle');
							return '<text class="stats-axis-label" x="' + p.x.toFixed(1) + '" y="' + (height - 4) + '" text-anchor="' + anchor + '">' + esc(rows[index]?.date || '') + '</text>';
						}).join('')}
					</svg>
					${hoverPoints}
				</div>
			</div>
		`;
	}

	function renderStatsSharing(siteId, env, stats) {
		const fathomSite = asObject(stats.site);
		if (!fathomSite.id) return '';
		const sharing = statsSharingValue(fathomSite);
		const shareUrl = statsShareUrl(fathomSite);
		const saving = state.siteStats.shareSaving;
		const disabled = saving ? ' disabled' : '';
		const chip = (value, label) => '<button class="stats-share-chip ' + (sharing === value ? 'active' : '') + '" type="button" data-action="stats-share" data-sharing="' + attr(value) + '"' + disabled + '>' + esc(label) + '</button>';
		return `
			<div class="stats-sharing">
				<div>
					<h3>Sharing</h3>
					<p>Stats are powered by <a href="https://usefathom.com" target="_blank" rel="noopener noreferrer">Fathom Analytics</a>. To view the stats dashboard directly, enable a public or private sharing option.</p>
					<div class="stats-share-chips" role="group" aria-label="Stats sharing">
						${chip('none', 'Off')}
						${chip('private', 'Private')}
						${chip('public', 'Public')}
					</div>
					${state.siteStats.shareError ? '<div class="stats-share-error">' + esc(state.siteStats.shareError) + '</div>' : ''}
				</div>
				<div class="stats-share-panel">
					${sharing === 'none' ? '<div><strong>Sharing is off</strong><p>No public dashboard link is exposed.</p></div>' : `
						<div>
							<strong>Share URL</strong><br>
							<a href="${attr(shareUrl)}" target="_blank" rel="noopener noreferrer">${esc(shareUrl)}</a>
						</div>
						${sharing === 'private' ? `
							<div class="stats-share-password">
								<label>Share Password
									<input type="text" value="${attr(state.siteStats.sharePassword)}" data-input="stats-share-password" autocomplete="off" spellcheck="false">
								</label>
								<button class="secondary-btn" type="button" data-action="stats-share-save"${disabled}>${saving ? 'Saving' : 'Save'}</button>
							</div>
						` : ''}
					`}
				</div>
			</div>
		`;
	}

	async function shareStats(sharing) {
		const { id, env } = currentSiteContext();
		if (!id || !env) return;
		const bucket = siteFeature(id, env);
		const stats = asObject(bucket.stats);
		const fathomSite = asObject(stats.site);
		const fathomId = firstDefined(stats.fathom_id, state.siteStats.fathom_id, fathomSite.id);
		if (!fathomId) {
			state.siteStats.shareError = 'No Fathom tracker is available for this environment.';
			render();
			return;
		}
		const nextSharing = ['none', 'private', 'public'].includes(sharing) ? sharing : 'none';
		if (nextSharing === 'private' && !state.siteStats.sharePassword) {
			state.siteStats.sharePassword = 'changeme';
		}
		state.siteStats.shareSaving = true;
		state.siteStats.shareError = '';
		render();
		try {
			const payload = { fathom_id: fathomId, sharing: nextSharing };
			if (nextSharing === 'private' && state.siteStats.sharePassword) {
				payload.share_password = state.siteStats.sharePassword;
			}
			await apiFetch('/wp-json/captaincore/v1/sites/' + encodeURIComponent(id) + '/stats/share', {
				method: 'POST',
				body: JSON.stringify(payload),
			});
			const nextSite = Object.assign({}, fathomSite, { id: fathomSite.id || fathomId, sharing: nextSharing });
			bucket.stats = Object.assign({}, stats, { fathom_id: fathomId, site: nextSite });
			showToast('Stats sharing is ' + (nextSharing === 'none' ? 'off' : nextSharing) + '.');
		} catch (error) {
			state.siteStats.shareError = error.message || 'Unable to update stats sharing.';
			showToast('Stats sharing update failed.');
		} finally {
			state.siteStats.shareSaving = false;
			render();
		}
	}

	function renderSiteStats(site, env) {
		if (!env) return renderEmpty('No environment data available.');
		const siteId = site.site_id || routeId('sites');
		const bucket = siteFeature(siteId, env);
		const stats = asObject(bucket.stats);
		const summary = asObject(stats.summary);
		const trackers = asArray(env.fathom_analytics);
		const timeframeButtons = [
			{ key: '7d', label: '7D' },
			{ key: '30d', label: '30D' },
			{ key: '12m', label: '12M' },
			{ key: '5y', label: '5Y' },
		];
		const trackerSelect = trackers.length > 1 ? `
			<div class="stats-control">
				<label>Tracker</label>
				<select data-input="stats-filter" data-field="fathom_id" aria-label="Fathom tracker">
					<option value="">Default tracker</option>
					${trackers.map(tracker => {
						const value = statsTrackerValue(tracker);
						return '<option value="' + attr(value) + '" ' + (state.siteStats.fathom_id === value ? 'selected' : '') + '>' + esc(statsTrackerLabel(tracker)) + '</option>';
					}).join('')}
				</select>
			</div>
		` : '';
		const rows = asArray(stats.items);
		return `
			<div class="feature-bar stats-toolbar">
				<div class="stats-controls">
					<div class="stats-timeframes" role="group" aria-label="Stats timeframes">
						${timeframeButtons.map(item => '<button type="button" class="' + (state.siteStats.timeframe === item.key ? 'active' : '') + '" data-action="stats-timeframe" data-range="' + attr(item.key) + '">' + esc(item.label) + '</button>').join('')}
					</div>
					<div class="stats-control">
						<label>Date Grouping</label>
						<select data-input="stats-filter" data-field="grouping" aria-label="Stats grouping">
							${['Hour', 'Day', 'Month', 'Year'].map(group => '<option value="' + group + '" ' + (state.siteStats.grouping === group ? 'selected' : '') + '>' + group + '</option>').join('')}
						</select>
					</div>
					<div class="stats-control">
						<label>From</label>
						<input type="date" value="${attr(state.siteStats.from_at)}" data-input="stats-filter" data-field="from_at" aria-label="Stats from date">
					</div>
					<div class="stats-control">
						<label>To</label>
						<input type="date" value="${attr(state.siteStats.to_at)}" data-input="stats-filter" data-field="to_at" aria-label="Stats to date">
					</div>
					${trackerSelect}
				</div>
				<div class="detail-actions">
					<button class="secondary-btn" type="button" data-action="refresh-site-feature" data-feature="stats">${bucket.statsLoaded ? 'Refresh' : 'Load'} Stats</button>
				</div>
			</div>
			${bucket.statsLoading ? renderLoading('Loading stats') : ''}
			${bucket.statsError ? '<div class="error-state"><div>' + esc(bucket.statsError) + '</div><button class="secondary-btn" type="button" data-action="refresh-site-feature" data-feature="stats">Retry</button></div>' : ''}
			${!bucket.statsLoading && !bucket.statsError && bucket.statsLoaded && bucket.stats ? `
				<div class="stats-kpi-grid">
					<div class="stats-kpi"><div class="k">Unique Visitors</div><div class="v">${formatCompactNumber(summary.visits)}</div></div>
					<div class="stats-kpi"><div class="k">Pageviews</div><div class="v">${formatCompactNumber(summary.pageviews)}</div></div>
					<div class="stats-kpi"><div class="k">Avg Time On Site</div><div class="v">${formatTime(summary.avg_duration)}</div></div>
					<div class="stats-kpi"><div class="k">Bounce Rate</div><div class="v">${formatPercent(summary.bounce_rate)}</div></div>
				</div>
				${renderStatsChart(stats.items)}
				<div class="stats-breakdown-head">
					<div class="section-title">Details breakdown</div>
					<span>${formatNumber(rows.length)} ${esc(state.siteStats.grouping.toLowerCase())} periods</span>
				</div>
				${renderDetailTable(['Date', 'Visitors', 'Pageviews', 'Avg Duration', 'Bounce'], rows.map(item => [
					esc(item.date || '-'),
					formatNumber(item.visits),
					formatNumber(item.pageviews),
					formatTime(item.avg_duration),
					formatPercent(item.bounce_rate),
				]), 'No stats items returned.', { pageKey: 'site-stats-' + siteFeatureKey(siteId, env), label: 'rows', perPage: 20 })}
				${renderStatsSharing(siteId, env, stats)}
			` : (!bucket.statsLoading && !bucket.statsLoaded ? renderEmpty('Stats have not been loaded yet.') : '')}
		`;
	}

	function renderSiteAddons(env) {
		if (!env) return renderEmpty('No environment data available.');
		const envId = env.environment_id || env.environment || 'environment';
		const plugins = asArray(env.plugins);
		const themes = asArray(env.themes);
		const pluginRows = plugins.map(plugin => [
			'<strong>' + esc(plugin.title || plugin.name || 'Plugin') + '</strong><small>' + esc(plugin.name || '') + '</small>',
			statusBadge(plugin.status || '-'),
			esc(plugin.version || '-'),
			esc(plugin.update_version || plugin.latest_version || plugin.new_version || '-'),
		]);
		const themeRows = themes.map(theme => [
			'<strong>' + esc(theme.title || theme.name || 'Theme') + '</strong><small>' + esc(theme.name || '') + '</small>',
			statusBadge(theme.status || '-'),
			esc(theme.version || '-'),
			esc(theme.update_version || theme.latest_version || theme.new_version || '-'),
		]);
		return `
			<div class="section-title">Plugins (${formatNumber(plugins.length)})</div>
			${renderDetailTable(['Name', 'Status', 'Version', 'Update'], pluginRows, 'No plugin data available.', { pageKey: 'site-addons-plugins-' + envId, label: 'plugins' })}
			<div class="section-title">Themes (${formatNumber(themes.length)})</div>
			${renderDetailTable(['Name', 'Status', 'Version', 'Update'], themeRows, 'No theme data available.', { pageKey: 'site-addons-themes-' + envId, label: 'themes' })}
		`;
	}

	function renderSiteDomains(details, env) {
		const linked = asArray(details?.domains).concat(asArray(env?.domains));
		const unique = [];
		const seen = new Set();
		linked.forEach(domain => {
			const key = domain.domain_id || domain.id || domain.name || domain.domain || JSON.stringify(domain);
			if (seen.has(key)) return;
			seen.add(key);
			unique.push(domain);
		});
		const domainRows = unique.map(domain => {
			const id = domain.domain_id || domain.id || '';
			const name = domain.name || domain.domain || domain.domain_name || domain.url || 'Domain';
			const link = id ? '<a href="' + attr(routeUrl('/domains/' + id)) + '" data-route="/domains/' + attr(id) + '">' + esc(name) + '</a>' : esc(name);
			return [link, esc(domain.type || domain.environment || '-'), statusBadge(domain.status || (domain.primary ? 'Primary' : '-'))];
		});
		const shared = asArray(details?.shared_with);
		const sharedRows = shared.map(account => [
			account.account_id ? '<a href="' + attr(routeUrl('/accounts/' + account.account_id)) + '" data-route="/accounts/' + attr(account.account_id) + '">' + esc(account.name || 'Account') + '</a>' : esc(account.name || 'Account'),
			esc(account.email || account.role || account.level || '-'),
		]);
		return `
			<div class="section-title">Linked Domains</div>
			${renderDetailTable(['Domain', 'Type', 'Status'], domainRows, 'No linked domains.', { pageKey: 'site-domains-' + routeId('sites'), label: 'domains' })}
			<div class="section-title">Shared With</div>
			${renderDetailTable(['Account', 'Details'], sharedRows, 'This site is not shared with other accounts.', { pageKey: 'site-shared-' + routeId('sites'), label: 'accounts' })}
		`;
	}

	function formatUserRoles(user) {
		const roles = user?.roles;
		if (Array.isArray(roles)) return roles.join(', ');
		if (typeof roles === 'string') return roles.split(',').map(role => role.trim()).filter(Boolean).join(', ');
		return String(user?.role || '-');
	}

	function renderSiteUsers(site, env) {
		if (!env) return renderEmpty('No environment data available.');
		const siteId = site.site_id || routeId('sites');
		const bucket = siteFeature(siteId, env);
		const users = bucket.usersLoaded ? bucket.users : asArray(env.users);
		const roleOptions = Array.from(new Set(users.flatMap(user => formatUserRoles(user).split(', ').filter(Boolean)))).sort();
		if (state.userRoleFilter !== 'all' && !roleOptions.includes(state.userRoleFilter)) state.userRoleFilter = 'all';
		const q = normalizeText(state.userSearch);
		const filtered = users.filter(user => {
			const roles = formatUserRoles(user);
			const matchesRole = state.userRoleFilter === 'all' || roles.split(', ').includes(state.userRoleFilter);
			const matchesSearch = !q || normalizeText([user.user_login, user.login, user.name, user.display_name, user.user_email, user.email, roles].join(' ')).includes(q);
			return matchesRole && matchesSearch;
		});
		const adminCount = users.filter(user => formatUserRoles(user).toLowerCase().includes('administrator')).length;
		const rows = filtered.map(user => {
			const userId = user.user_id || user.ID || '';
			const login = firstDefined(user.user_login, user.login, user.name, user.display_name, '-');
			const email = firstDefined(user.user_email, user.email, '');
			const roles = formatUserRoles(user);
			return [
				'<div class="user-name-cell"><strong>' + esc(login) + '</strong>' + (userId ? '<small>ID ' + esc(userId) + '</small>' : '') + '</div>',
				esc(email || '-'),
				'<div class="inline-list">' + roles.split(', ').filter(Boolean).map(role => statusBadge(role)).join('') + '</div>',
				userId ? '<button type="button" class="row-action" data-action="magic-login" data-site-id="' + attr(site.site_id) + '" data-env-id="' + attr(env.environment_id || env.environment) + '" data-user-id="' + attr(userId) + '" title="WP Admin" aria-label="WP Admin">' + icons.login + '</button>' : '-',
			];
		});
		return `
			<div class="site-users-toolbar">
				<div class="site-users-filters">
					<div class="search-input">
						${icons.search}
						<input type="search" value="${attr(state.userSearch)}" data-input="site-user-search" placeholder="Search users...">
					</div>
					<select data-input="site-user-role" aria-label="Filter users by role">
						<option value="all">All roles</option>
						${roleOptions.map(role => '<option value="' + attr(role) + '" ' + (state.userRoleFilter === role ? 'selected' : '') + '>' + esc(role) + '</option>').join('')}
					</select>
				</div>
				<button class="secondary-btn" type="button" data-action="refresh-site-feature" data-feature="users">${bucket.usersLoaded ? 'Refresh' : 'Load'} Users</button>
			</div>
			<div class="user-summary-grid">
				<div class="user-pill"><span>Total Users</span><strong>${formatNumber(users.length)}</strong></div>
				<div class="user-pill"><span>Administrators</span><strong>${formatNumber(adminCount)}</strong></div>
				<div class="user-pill"><span>Visible</span><strong>${formatNumber(filtered.length)}</strong></div>
			</div>
			${bucket.usersLoading ? renderLoading('Loading users') : ''}
			${bucket.usersError ? '<div class="error-state"><div>' + esc(bucket.usersError) + '</div><button class="secondary-btn" type="button" data-action="refresh-site-feature" data-feature="users">Retry</button></div>' : ''}
			${!bucket.usersLoading && !bucket.usersError ? renderDetailTable(['User', 'Email', 'Roles', ''], rows, bucket.usersLoaded ? 'No users match the current filters.' : 'Users have not been loaded yet.', { pageKey: 'site-users-' + siteFeatureKey(siteId, env), label: 'users' }) : ''}
		`;
	}

	function renderSiteBackups(site, env) {
		if (!env) return renderEmpty('No environment data available.');
		const siteId = site.site_id || routeId('sites');
		const bucket = siteFeature(siteId, env);
		const rows = bucket.backups.map(item => {
			const id = backupId(item);
			const size = firstDefined(item.size, item.bytes, item.file_size, item.total_size);
			const name = firstDefined(item.name, item.backup_name, item.backup_id, item.id, 'Backup');
			const created = firstDefined(item.created_at, item.date, item.time, item.timestamp);
			const sizeLabel = size ? (Number.isNaN(Number(size)) ? String(size) : formatStorage(size)) : '';
			const meta = [formatDate(created), sizeLabel, item.type || item.kind || ''].filter(Boolean).join(' - ');
			const downloadLoading = state.actionLoading['backup_download:' + id];
			const restoreLoading = state.actionLoading['backup_restore:' + id];
			return {
				title: esc(name),
				meta: esc(meta || 'Backup'),
				hint: id ? 'Click card to download backup' : '',
				action: id ? 'backup-download' : '',
				dataset: id ? { 'backup-id': id } : {},
				ariaLabel: id ? 'Download backup ' + name : 'Backup ' + name,
				aside: `
					<div class="row-button-group">
						${statusBadge(item.status || item.state || 'Backup')}
						${id ? '<button type="button" class="secondary-btn" data-action="backup-download" data-backup-id="' + attr(id) + '">' + (downloadLoading ? 'Starting' : 'Download') + '</button>' : ''}
						${id ? '<button type="button" class="danger-btn" data-action="backup-restore" data-backup-id="' + attr(id) + '">' + (restoreLoading ? 'Starting' : 'Restore') + '</button>' : ''}
					</div>
				`,
			};
		});
		return `
			<div class="feature-bar">
				<div class="summary__sub">${formatNumber(bucket.backups.length)} backups for ${esc(env.environment || 'environment')}</div>
				<button class="secondary-btn" type="button" data-action="refresh-site-feature" data-feature="backups">${bucket.backupsLoaded ? 'Refresh' : 'Load'} Backups</button>
			</div>
			${bucket.backupsLoading ? renderLoading('Loading backups') : ''}
			${bucket.backupsError ? '<div class="error-state"><div>' + esc(bucket.backupsError) + '</div><button class="secondary-btn" type="button" data-action="refresh-site-feature" data-feature="backups">Retry</button></div>' : ''}
			${!bucket.backupsLoading && !bucket.backupsError ? renderStackRows(rows, bucket.backupsLoaded ? 'No backups found for this environment.' : 'Backups have not been loaded yet.', { pageKey: 'site-backups-' + siteFeatureKey(siteId, env), label: 'backups' }) : ''}
		`;
	}

	function renderSiteQuicksaves(site, env) {
		if (!env) return renderEmpty('No environment data available.');
		const siteId = site.site_id || routeId('sites');
		const bucket = siteFeature(siteId, env);
		const q = normalizeText(state.quicksaveSearch);
		const quicksaves = bucket.quicksaves.filter(item => !q || normalizeText(JSON.stringify(item)).includes(q));
		const pageKey = 'site-quicksaves-' + siteFeatureKey(siteId, env);
		const pageInfo = paginateRows(pageKey, quicksaves, 25);
		return `
			<div class="feature-bar">
				<div class="feature-controls" style="flex:1">
					<div class="search-input" style="border:1px solid var(--rule);border-radius:var(--radius-sm);padding:7px 10px;max-width:360px;width:100%">
						${icons.search}
						<input type="search" value="${attr(state.quicksaveSearch)}" data-input="quicksave-search" placeholder="Search quicksaves...">
					</div>
				</div>
				<button class="secondary-btn" type="button" data-action="refresh-site-feature" data-feature="quicksaves">${bucket.quicksavesLoaded ? 'Refresh' : 'Load'} Quicksaves</button>
			</div>
			${bucket.quicksavesLoading ? renderLoading('Loading quicksaves') : ''}
			${bucket.quicksavesError ? '<div class="error-state"><div>' + esc(bucket.quicksavesError) + '</div><button class="secondary-btn" type="button" data-action="refresh-site-feature" data-feature="quicksaves">Retry</button></div>' : ''}
			${!bucket.quicksavesLoading && !bucket.quicksavesError ? renderQuicksaveRows(siteId, env, bucket, pageInfo) : ''}
		`;
	}

	function renderQuicksaveRows(siteId, env, bucket, pageInfo) {
		if (!bucket.quicksavesLoaded && !bucket.quicksaves.length) return renderEmpty('Quicksaves have not been loaded yet.');
		if (!pageInfo.total) return renderEmpty('No quicksaves found for this environment.');
		return `
			<div class="stack-list">
				${pageInfo.rows.map(item => {
					const hash = quicksaveHash(item);
					const created = firstDefined(item.created_at, item.date, item.time, item.timestamp);
					const changes = firstDefined(item.changes, item.git_status, item.summary, item.message);
					const meta = [formatDate(created), item.email || item.author || '', changes && typeof changes !== 'object' ? changes : ''].filter(Boolean).join(' - ');
					const files = bucket.quicksaveFiles[hash] || [];
					const filesLoading = bucket.quicksaveFilesLoading[hash];
					const filesError = bucket.quicksaveFilesError[hash];
					const rollbackLoading = state.actionLoading['quicksave-rollback:' + hash];
					const expanded = bucket.expandedQuicksave === hash;
					return `
						<div class="stack-row ${hash ? 'clickable' : ''} ${expanded ? 'expanded' : ''}"${hash ? ' role="button" tabindex="0" data-action="toggle-quicksave-files"' + renderDataAttrs({ hash }) + ' aria-label="' + attr((expanded ? 'Hide changed files for ' : 'Show changed files for ') + hash) + '"' : ''}>
							<div class="stack-row__primary">
								<div class="stack-row__title"><strong>${esc(hash || 'Quicksave')}</strong></div>
								${meta ? '<small>' + esc(meta) + '</small>' : ''}
								${hash ? '<div class="stack-row__hint">' + (expanded ? 'Click card to hide changed files' : 'Click card to view changed files') + '</div>' : ''}
							</div>
							<div class="row-button-group">
								${statusBadge(item.status || 'Quicksave')}
								${hash ? '<button type="button" class="secondary-btn" data-action="toggle-quicksave-files" data-hash="' + attr(hash) + '">' + (expanded ? 'Hide Files' : 'Files') + '</button>' : ''}
								${hash ? '<button type="button" class="danger-btn" data-action="quicksave-rollback" data-hash="' + attr(hash) + '">' + (rollbackLoading ? 'Starting' : 'Rollback') + '</button>' : ''}
							</div>
						</div>
						${expanded ? `
							<div class="changed-files">
								${filesLoading ? renderLoading('Loading changed files') : ''}
								${filesError ? '<div class="error-state"><div>' + esc(filesError) + '</div><button class="secondary-btn" type="button" data-action="toggle-quicksave-files" data-hash="' + attr(hash) + '">Retry</button></div>' : ''}
								${!filesLoading && !filesError && !files.length ? '<div class="empty-mini">No changed files found.</div>' : ''}
								${!filesLoading && !filesError ? files.slice(0, 100).map(file => `
									<div class="changed-file">
										<code>${esc(file)}</code>
										<button type="button" class="secondary-btn" data-action="quicksave-diff" data-hash="${attr(hash)}" data-file="${attr(file)}">Diff</button>
									</div>
								`).join('') : ''}
							</div>
						` : ''}
					`;
				}).join('')}
			</div>
			${renderPagination('site-quicksaves-' + siteFeatureKey(siteId, env), pageInfo, 'quicksaves')}
		`;
	}

	function renderSiteTimeline(site, env) {
		const siteId = site.site_id || routeId('sites');
		const bucket = siteFeature(siteId, env || {});
		const rows = bucket.timeline.map(item => {
			const description = firstDefined(item.description, item.title, item.action, item.message, 'Timeline entry');
			const author = firstDefined(item.author, item.user, item.user_name);
			const created = firstDefined(item.created_at, item.date, item.time, item.timestamp);
			const related = asArray(item.websites).map(website => website.name || website.site || website.domain).filter(Boolean).join(', ');
			return {
				html: '<div class="timeline-markdown">' + renderTimelineMarkdown(description) + '</div>',
				meta: esc([author, formatDate(created), related].filter(Boolean).join(' - ')),
				aside: statusBadge(item.type || item.entity || 'Timeline'),
			};
		});
		return `
			<div class="feature-bar">
				<div class="summary__sub">${formatNumber(bucket.timeline.length)} timeline entries</div>
				<button class="secondary-btn" type="button" data-action="refresh-site-feature" data-feature="timeline">${bucket.timelineLoaded ? 'Refresh' : 'Load'} Timeline</button>
			</div>
			${bucket.timelineLoading ? renderLoading('Loading timeline') : ''}
			${bucket.timelineError ? '<div class="error-state"><div>' + esc(bucket.timelineError) + '</div><button class="secondary-btn" type="button" data-action="refresh-site-feature" data-feature="timeline">Retry</button></div>' : ''}
			${!bucket.timelineLoading && !bucket.timelineError ? renderStackRows(rows, bucket.timelineLoaded ? 'No timeline activity.' : 'Timeline has not been loaded yet.', { pageKey: 'site-timeline-' + siteId, label: 'entries' }) : ''}
		`;
	}

	function renderSiteLogs(details, env) {
		if (!env) return renderEmpty('No environment data available.');
		const siteId = routeId('sites');
		const bucket = siteFeature(siteId, env);
		const consoleErrors = visibleConsoleErrorsForEnvironment(env);
		const audits = extractItems(details?.site_audits || details?.audits || details?.site?.site_audits);
		const selectedFile = bucket.logFiles.find(file => file.path === bucket.selectedLog) || bucket.logFiles[0] || {};
		const q = normalizeText(state.logSearch);
		const logLines = String(bucket.logContent || '').split('\n').filter(line => line !== '');
		const filteredLines = q ? logLines.filter(line => normalizeText(line).includes(q)) : logLines;
		const hiddenLines = Math.max(0, logLines.length - filteredLines.length);
		const auditCards = audits.map(audit => {
			const title = firstDefined(audit.title, audit.name, audit.report_title, 'Site audit');
			const status = firstDefined(audit.status, audit.filesystem_status, '-');
			const issues = firstDefined(audit.issues_count, audit.finding_counts?.open, audit.findings_count, '');
			return `
				<div class="audit-card">
					<div>
						<strong>${esc(title)}</strong>
						<small>${esc(formatDate(audit.created_at || audit.date))}${issues !== '' ? ' - ' + esc(issues) + ' issue' + (Number(issues) === 1 ? '' : 's') : ''}</small>
					</div>
					${statusBadge(status)}
				</div>
			`;
		}).join('');
		return `
			<div class="site-logs-toolbar">
				<div class="site-log-controls">
					<select data-input="site-log-file" aria-label="Server log file" ${bucket.logFiles.length ? '' : 'disabled'}>
						${bucket.logFiles.length ? bucket.logFiles.map(file => '<option value="' + attr(file.path) + '" ' + (file.path === bucket.selectedLog ? 'selected' : '') + '>' + esc(file.label || file.path) + '</option>').join('') : '<option>No server logs</option>'}
					</select>
					<input type="number" min="10" max="10000" step="10" value="${attr(bucket.logLimit)}" data-input="site-log-limit" aria-label="Log line limit">
					<button class="secondary-btn" type="button" data-action="load-selected-log-file" ${bucket.selectedLog ? '' : 'disabled'}>${bucket.logContentLoaded ? 'Reload File' : 'Load File'}</button>
				</div>
				<button class="secondary-btn" type="button" data-action="refresh-site-feature" data-feature="logs">${bucket.logsLoaded ? 'Refresh Logs' : 'Load Logs'}</button>
			</div>
			${bucket.logsLoading ? renderLoading('Loading server logs') : ''}
			${bucket.logsError ? '<div class="error-state"><div>' + esc(bucket.logsError) + '</div><button class="secondary-btn" type="button" data-action="refresh-site-feature" data-feature="logs">Retry</button></div>' : ''}
			${!bucket.logsLoading && !bucket.logsError ? `
				<div class="logs-grid">
					<div>
						<div class="section-title">Server Logs</div>
						${bucket.logFiles.length ? `
							<div class="log-file-list">
								${bucket.logFiles.map(file => `
									<button type="button" class="log-file ${file.path === bucket.selectedLog ? 'active' : ''}" data-action="load-log-file" data-file="${attr(file.path)}">
										<strong>${esc(file.label || file.path)}</strong>
										${file.meta ? '<small>' + esc(file.meta) + '</small>' : ''}
									</button>
								`).join('')}
							</div>
						` : renderEmpty(bucket.logsLoaded ? 'No server log files returned.' : 'Server logs have not been loaded yet.')}
						${consoleErrors.length ? '<div class="section-title">Console Errors</div><pre class="code-block">' + esc(consoleErrors.map(item => typeof item === 'string' ? item : JSON.stringify(item, null, 2)).join('\n\n')) + '</pre>' : ''}
						${audits.length ? '<div class="section-title">Site Audits</div><div class="audit-list">' + auditCards + '</div>' : ''}
					</div>
					<div class="log-panel">
						<div class="site-log-controls">
							<div class="search-input">
								${icons.search}
								<input type="search" value="${attr(state.logSearch)}" data-input="site-log-search" placeholder="Search loaded log...">
							</div>
							<div class="log-meta">${selectedFile.path ? esc(selectedFile.path) : 'No file selected'}${hiddenLines ? ' - ' + formatNumber(hiddenLines) + ' hidden by search' : ''}</div>
						</div>
						${bucket.logContentLoading ? renderLoading('Loading log file') : ''}
						${bucket.logContentError ? '<div class="error-state"><div>' + esc(bucket.logContentError) + '</div><button class="secondary-btn" type="button" data-action="load-selected-log-file">Retry</button></div>' : ''}
						${!bucket.logContentLoading && !bucket.logContentError ? (bucket.logContentLoaded ? '<pre class="log-output">' + esc(filteredLines.join('\n') || 'No lines match the current search.') + '</pre>' : renderEmpty(bucket.selectedLog ? 'Select Load File to view this log.' : 'Choose a server log file.')) : ''}
					</div>
				</div>
			` : ''}
		`;
	}

	function renderSiteScripts(site, env) {
		if (!env) return renderEmpty('No environment data available.');
		if (!state.fetched.recipes && !state.loading.recipes) {
			setTimeout(() => loadRecipes(), 0);
		}
		const scheduled = asArray(env.scheduled_scripts);
		const recipes = state.recipes.slice(0, 12);
		const scheduledRows = scheduled.map(script => [
			'<code>' + esc(String(script.code || '').slice(0, 120)) + (String(script.code || '').length > 120 ? '...' : '') + '</code>',
			esc(script.author || script.user || '-'),
			esc(formatDate(script.run_at || script.created_at)),
			statusBadge(script.status || 'scheduled'),
		]);
		return `
			<div class="feature-bar">
				<div>
					<div class="section-title" style="margin:0">Command Console</div>
					<div class="summary__sub">Run WP-CLI commands, Bash scripts, recipes, and system tools on ${esc(env.environment || 'this environment')}.</div>
				</div>
				<div class="detail-actions">
					<button class="primary-btn" type="button" data-action="open-terminal-current">${icons.terminal} Open Terminal</button>
					<button class="secondary-btn" type="button" data-action="refresh-recipes">Refresh Recipes</button>
				</div>
			</div>
			<div class="section-title">System Tools</div>
			<div class="script-grid">
				${state.systemTools.map(tool => `
					<button class="script-card" type="button" data-action="scripts-system-tool" data-tool="${attr(tool.key)}">
						<strong>${esc(tool.label)}</strong>
						<small>${esc(tool.description)}</small>
					</button>
				`).join('')}
			</div>
			<div class="section-title">Cookbook</div>
			${state.loading.recipes ? renderLoading('Loading recipes') : ''}
			${state.error.recipes ? '<div class="error-state"><div>' + esc(state.error.recipes) + '</div><button class="secondary-btn" type="button" data-action="refresh-recipes">Retry</button></div>' : ''}
			${!state.loading.recipes && !state.error.recipes ? `
				<div class="script-grid">
					${recipes.map(recipe => `
						<button class="script-card" type="button" data-action="scripts-preview-recipe" data-recipe-id="${attr(recipe.recipe_id)}">
							<strong>${esc(recipe.title || 'Recipe')}</strong>
							<small>${recipe.public == 1 ? 'Public recipe' : 'Private recipe'} - loads into terminal</small>
						</button>
					`).join('') || '<div class="empty-state">No recipes found.</div>'}
				</div>
			` : ''}
			<div class="section-title">Scheduled Scripts</div>
			${renderDetailTable(['Code', 'User', 'Run Date', 'Status'], scheduledRows, 'No scheduled scripts found for this environment.', { pageKey: 'site-scheduled-scripts-' + (env.environment_id || routeId('sites')), label: 'scripts', perPage: 20 })}
		`;
	}

	function renderDomainDetail() {
		const id = routeId('domains');
		const key = detailKey('domain', id);
		const data = state.domainDetails[id];
		if (state.loading[key] && !data) return renderLoading('Loading domain');
		if (state.error[key] && !data) return renderError(state.error[key], 'refresh-domain-detail');
		if (!data) return renderEmpty('Domain not found.');
		const domain = data.domain || {};
		const details = data.details || {};
		const provider = data.provider || {};
		const tabs = [
			{ key: 'dns', label: 'DNS' },
			{ key: 'management', label: 'Management' },
			{ key: 'email', label: 'Email' },
			{ key: 'mailgun', label: 'Mailgun' },
		];
		const active = tabs.some(tab => tab.key === state.detailTabs.domain) ? state.detailTabs.domain : 'dns';
		const panels = {
			dns: renderDomainDns(domain, data.records || []),
			management: renderDomainManagement(domain, provider, details),
			email: renderDomainEmail(details),
			mailgun: renderDomainMailgun(details),
		};
		const pills = `
			<span class="pill"><span class="dot ${domain.remote_id ? 'ok' : 'warn'}"></span>${domain.remote_id ? 'DNS active' : 'No DNS zone'}</span>
			${domain.provider_id ? '<span class="pill">Registered</span>' : ''}
			${domain.expires ? '<span class="pill">Expires ' + esc(formatDate(domain.expires)) + '</span>' : ''}
		`;
		return `
			<div class="detail-shell">
				<div class="detail-toolbar">
					<button class="secondary-btn" type="button" data-route="/domains">${icons.back} Domains</button>
					<div class="detail-actions"><button class="secondary-btn" type="button" data-action="refresh-domain-detail">Refresh</button></div>
				</div>
				${renderPageHeader(domain.name || domain.domain || 'Domain', domain.account || domain.customer || domain.status || 'Domain record', pills)}
				${renderDetailTabs('domain', tabs)}
				<div class="detail-panel">${panels[active] || panels.dns}</div>
			</div>
		`;
	}

	function renderDomainDns(domain, records) {
		const rows = records.map(record => [
			statusBadge(record.type || record.record_type || '-'),
			esc(record.name || record.record_name || '@'),
			esc(recordValue(record)),
			esc(record.ttl || record.record_ttl || '-'),
		]);
		return `
			<div class="detail-grid">
				<div class="detail-card"><div class="k">Zone</div><div class="v">${domain.remote_id ? 'Active' : 'Inactive'}</div><div class="sub">${esc(domain.remote_id || 'No remote zone')}</div></div>
				<div class="detail-card"><div class="k">Records</div><div class="v">${formatNumber(records.length)}</div><div class="sub">DNS entries</div></div>
			</div>
			<div class="section-title">DNS Records</div>
			${renderDetailTable(['Type', 'Name', 'Value', 'TTL'], rows, domain.remote_id ? 'No DNS records found.' : 'Activate DNS to manage records for this domain.', { pageKey: 'domain-dns-' + (domain.domain_id || routeId('domains')), label: 'records', perPage: 50 })}
		`;
	}

	function renderDomainManagement(domain, provider, details) {
		const nameservers = asArray(provider.nameservers).map(item => typeof item === 'object' ? firstDefined(item.value, item.name, JSON.stringify(item)) : item);
		const contacts = provider.contacts || {};
		const domainRows = [
			['Name', esc(domain.name || domain.domain || '-')],
			['Provider ID', esc(domain.provider_id || '-')],
			['Remote ID', esc(domain.remote_id || '-')],
			['Status', esc(domain.status || domain.state || '-')],
			['Expires', esc(formatDate(domain.expires || domain.expiration_date))],
			['Lock', esc(provider.locked || details.locked || '-')],
			['WHOIS Privacy', esc(provider.whois_privacy || details.whois_privacy || '-')],
		];
		const contactCards = Object.keys(contacts).map(type => {
			const contact = contacts[type] || {};
			return `
				<div class="detail-card">
					<div class="k">${esc(type.replace(/_/g, ' '))}</div>
					<div class="v">${esc([contact.first_name, contact.last_name].filter(Boolean).join(' ') || contact.organization || '-')}</div>
					<div class="sub">${esc([contact.email, contact.phone].filter(Boolean).join(' - ') || '-')}</div>
				</div>
			`;
		}).join('');
		return `
			<div class="section-title">Registration</div>
			${renderDetailTable(['Field', 'Value'], domainRows, 'No registration data.')}
			<div class="section-title">Nameservers</div>
			${renderStackRows(nameservers.map(ns => ({ title: esc(ns), meta: '', aside: '' })), 'No nameservers returned by the provider.', { pageKey: 'domain-nameservers-' + (domain.domain_id || routeId('domains')), label: 'nameservers' })}
			${contactCards ? '<div class="section-title">Contacts</div><div class="detail-grid">' + contactCards + '</div>' : ''}
		`;
	}

	function renderDomainEmail(details) {
		const rows = [
			['Forwarding ID', esc(details.mailgun_forwarding_id || '-')],
			['Forwarding Zone', esc(details.mailgun_forwarding_zone || details.mailgun_forwarding_domain || '-')],
			['Status', esc(details.mailgun_forwarding_status || '-')],
		];
		return `
			<div class="section-title">Email Forwarding</div>
			${renderDetailTable(['Field', 'Value'], rows, 'No email forwarding data.')}
		`;
	}

	function renderDomainMailgun(details) {
		const rows = [
			['Mailgun ID', esc(details.mailgun_id || '-')],
			['Zone', esc(details.mailgun_zone || '-')],
			['SMTP Host', details.mailgun_zone ? 'smtp.mailgun.org' : '-'],
			['SMTP Username', details.mailgun_zone ? 'postmaster@' + esc(details.mailgun_zone) : '-'],
			['SMTP Password', details.mailgun_smtp_password ? 'Stored' : '-'],
		];
		return `
			<div class="section-title">Mailgun</div>
			${renderDetailTable(['Field', 'Value'], rows, 'No Mailgun data.')}
		`;
	}

	function renderAccountDetail() {
		const id = routeId('accounts');
		const key = detailKey('account', id);
		const data = state.accountDetails[id];
		if (state.loading[key] && !data) return renderLoading('Loading account');
		if (state.error[key] && !data) return renderError(state.error[key], 'refresh-account-detail');
		if (!data) return renderEmpty('Account not found.');
		const account = data.account || data || {};
		const plan = account.plan || {};
		const tabs = [
			{ key: 'overview', label: 'Overview' },
			{ key: 'users', label: 'Users' },
			{ key: 'sites', label: 'Sites' },
			{ key: 'domains', label: 'Domains' },
			{ key: 'plan', label: 'Plan' },
			{ key: 'invoices', label: 'Invoices' },
			{ key: 'timeline', label: 'Timeline' },
		];
		const active = tabs.some(tab => tab.key === state.detailTabs.account) ? state.detailTabs.account : 'overview';
		const panels = {
			overview: renderAccountOverview(account, data),
			users: renderAccountUsers(data),
			sites: renderAccountSites(data),
			domains: renderAccountDomains(data),
			plan: renderAccountPlan(account, data),
			invoices: renderAccountInvoices(data),
			timeline: renderAccountTimeline(data),
		};
		const pills = `
			<span class="pill"><b>${formatNumber(asArray(data.sites).length)}</b> sites</span>
			<span class="pill"><b>${formatNumber(asArray(data.domains).length)}</b> domains</span>
			${plan.name ? '<span class="pill">' + esc(plan.name) + '</span>' : ''}
		`;
		return `
			<div class="detail-shell">
				<div class="detail-toolbar">
					<button class="secondary-btn" type="button" data-route="/accounts">${icons.back} Accounts</button>
					<div class="detail-actions"><button class="secondary-btn" type="button" data-action="refresh-account-detail">Refresh</button></div>
				</div>
				${renderPageHeader(account.name || 'Account', account.email || account.status || 'Account record', pills)}
				${renderDetailTabs('account', tabs)}
				<div class="detail-panel">${panels[active] || panels.overview}</div>
			</div>
		`;
	}

	function renderAccountOverview(account, data) {
		const plan = account.plan || {};
		const usage = plan.usage || {};
		const limits = plan.limits || {};
		return `
			<div class="detail-grid">
				<div class="detail-card"><div class="k">Users</div><div class="v">${formatNumber(asArray(data.users).length)}</div><div class="sub">${formatNumber(asArray(data.invites).length)} pending invites</div></div>
				<div class="detail-card"><div class="k">Sites</div><div class="v">${formatNumber(asArray(data.sites).length)}</div><div class="sub">Managed properties</div></div>
				<div class="detail-card"><div class="k">Domains</div><div class="v">${formatNumber(asArray(data.domains).length)}</div><div class="sub">DNS and registrations</div></div>
				<div class="detail-card"><div class="k">Plan</div><div class="v">${esc(plan.name || '-')}</div><div class="sub">${plan.price ? formatMoney(plan.price) : '-'} ${plan.interval ? '/ ' + esc(plan.interval) + ' months' : ''}</div></div>
				<div class="detail-card"><div class="k">Storage</div><div class="v">${formatStorage(usage.storage)}</div><div class="sub">${limits.storage ? 'Limit ' + esc(limits.storage) + ' GB' : 'No limit data'}</div></div>
				<div class="detail-card"><div class="k">Visits</div><div class="v">${formatNumber(usage.visits)}</div><div class="sub">${limits.visits ? 'Limit ' + formatNumber(limits.visits) : 'No limit data'}</div></div>
			</div>
		`;
	}

	function renderAccountUsers(data) {
		const users = asArray(data.users);
		const invites = asArray(data.invites);
		const userRows = users.map(user => [esc(user.name || user.login || '-'), esc(user.email || '-'), statusBadge(user.level || user.role || '-')]);
		const inviteRows = invites.map(invite => [esc(invite.email || '-'), esc(formatDate(invite.created_at)), statusBadge(invite.status || 'Pending')]);
		return `
			<div class="section-title">Users</div>
			${renderDetailTable(['Name', 'Email', 'Level'], userRows, 'No users.', { pageKey: 'account-users-' + routeId('accounts'), label: 'users' })}
			<div class="section-title">Pending Invites</div>
			${renderDetailTable(['Email', 'Sent', 'Status'], inviteRows, 'No pending invites.', { pageKey: 'account-invites-' + routeId('accounts'), label: 'invites' })}
		`;
	}

	function renderAccountSites(data) {
		const rows = asArray(data.sites).map(site => [
			site.site_id ? '<a href="' + attr(routeUrl('/sites/' + site.site_id)) + '" data-route="/sites/' + attr(site.site_id) + '">' + esc(site.name || site.site || 'Site') + '</a>' : esc(site.name || site.site || 'Site'),
			formatStorage(site.storage),
			formatNumber(site.visits),
			statusBadge(site.status || '-'),
		]);
		return renderDetailTable(['Site', 'Storage', 'Visits', 'Status'], rows, 'No sites for this account.', { pageKey: 'account-sites-' + routeId('accounts'), label: 'sites' });
	}

	function renderAccountDomains(data) {
		const rows = asArray(data.domains).map(domain => [
			domain.domain_id ? '<a href="' + attr(routeUrl('/domains/' + domain.domain_id)) + '" data-route="/domains/' + attr(domain.domain_id) + '">' + esc(domain.name || domain.domain || 'Domain') + '</a>' : esc(domain.name || domain.domain || 'Domain'),
			esc(domain.expires ? formatDate(domain.expires) : '-'),
			statusBadge(domain.status || (domain.remote_id ? 'DNS active' : '-')),
		]);
		return renderDetailTable(['Domain', 'Expires', 'Status'], rows, 'No domains for this account.', { pageKey: 'account-domains-' + routeId('accounts'), label: 'domains' });
	}

	function renderAccountPlan(account, data) {
		const plan = account.plan || {};
		const planRows = [
			['Name', esc(plan.name || '-')],
			['Price', plan.price ? formatMoney(plan.price) : '-'],
			['Interval', plan.interval ? esc(plan.interval) + ' months' : '-'],
			['Next Renewal', esc(plan.next_renewal || '-')],
		];
		const addons = asArray(plan.addons).map(item => [esc(item.name || '-'), esc(item.quantity || '-'), item.price ? formatMoney(item.price) : '-']);
		const charges = asArray(plan.charges).map(item => [esc(item.name || '-'), esc(item.quantity || '-'), item.price ? formatMoney(item.price) : '-']);
		const credits = asArray(plan.credits).map(item => [esc(item.name || '-'), esc(item.quantity || '-'), item.price ? formatMoney(item.price) : '-']);
		return `
			<div class="section-title">Plan</div>
			${renderDetailTable(['Field', 'Value'], planRows, 'No plan data.')}
			<div class="section-title">Addons</div>
			${renderDetailTable(['Name', 'Qty', 'Price'], addons, 'No addons.', { pageKey: 'account-plan-addons-' + routeId('accounts'), label: 'addons' })}
			<div class="section-title">Charges</div>
			${renderDetailTable(['Name', 'Qty', 'Price'], charges, 'No charges.', { pageKey: 'account-plan-charges-' + routeId('accounts'), label: 'charges' })}
			<div class="section-title">Credits</div>
			${renderDetailTable(['Name', 'Qty', 'Price'], credits, 'No credits.', { pageKey: 'account-plan-credits-' + routeId('accounts'), label: 'credits' })}
			<div class="section-title">Per-Site Breakdown</div>
			${renderAccountSites(data)}
		`;
	}

	function renderAccountInvoices(data) {
		const rows = asArray(data.invoices).map(invoice => [
			esc(invoice.order_id ? '#' + invoice.order_id : invoice.id || '-'),
			esc(formatDate(invoice.date || invoice.created_at)),
			statusBadge(invoice.status || '-'),
			invoice.total ? formatMoney(invoice.total) : '-',
		]);
		return renderDetailTable(['Order', 'Date', 'Status', 'Total'], rows, 'No invoices.', { pageKey: 'account-invoices-' + routeId('accounts'), label: 'invoices' });
	}

	function renderAccountTimeline(data) {
		const rows = asArray(data.timeline).map(item => ({
			html: '<div class="timeline-markdown">' + renderTimelineMarkdown(item.description || item.title || 'Timeline entry') + '</div>',
			meta: esc([item.author, formatDate(item.created_at || item.date)].filter(Boolean).join(' - ')),
			aside: item.type ? statusBadge(item.type) : '',
		}));
		return renderStackRows(rows, 'No timeline entries.', { pageKey: 'account-timeline-' + routeId('accounts'), label: 'entries' });
	}

	function renderDirectory(title, key) {
		const rows = state[key] || [];
		const sub = state.loading[key] ? 'Loading' : `${formatNumber(rows.length)} records`;
		return `
			${renderPageHeader(title, sub)}
			${renderToolbar('Search ' + title.toLowerCase() + '...', false)}
			${state.error[key] ? renderError(state.error[key], 'refresh-current') : ''}
			${state.loading[key] ? renderLoading('Loading ' + title.toLowerCase()) : ''}
			${!state.loading[key] && !state.error[key] ? renderDirectoryRows(rows, key) : ''}
		`;
	}

	function renderDirectoryRows(rows, key) {
		const q = normalizeText(state.search);
		const filtered = rows.filter(row => !q || normalizeText(JSON.stringify(row)).includes(q));
		if (!filtered.length) return renderEmpty('No records match the current view.');
		const pageKey = 'directory-' + key;
		const cards = filtered.map(row => {
			const name = row.name || row.title || row.domain || row.site || row.email || row.subscription_id || row.id || 'Record';
			const metrics = row.metrics || {};
			const metaParts = [];
			const addMeta = value => {
				const clean = String(value == null ? '' : value).trim();
				if (!clean || clean === '-' || metaParts.includes(clean)) return;
				metaParts.push(clean);
			};
			const addCount = (value, label) => {
				if (value == null || value === '') return;
				const number = Array.isArray(value) ? value.length : Number(value);
				if (!Number.isFinite(number)) return;
				addMeta(formatNumber(number) + ' ' + label + (number === 1 ? '' : 's'));
			};
			let status = row.status || row.state || row.type || '';
			let hint = 'Click card to open';
			if (key === 'domains') {
				addMeta(row.account || row.customer);
				addMeta(row.expires ? 'Expires ' + formatDate(row.expires) : '');
				addMeta(row.provider_id ? 'Registered' : '');
				addMeta(row.remote_id ? 'Managed DNS zone' : 'DNS zone not active');
				status = row.status || row.state || (row.remote_id ? 'DNS active' : 'No DNS zone');
				hint = 'Click card to manage DNS';
			} else if (key === 'accounts') {
				addMeta(row.email || row.customer);
				addMeta(row.plan?.name);
				addCount(metrics.sites ?? row.sites, 'site');
				addCount(metrics.domains ?? row.domains, 'domain');
				addCount(metrics.users ?? row.users, 'user');
				status = row.status || row.state || row.plan?.name || 'Account';
				hint = 'Click card to manage account';
			} else {
				addMeta(row.email || row.account || row.customer || row.plan?.name || row.expires || row.domain || row.status);
			}
			const id = row.domain_id || row.account_id || row.subscription_id || row.id || '';
			const route = key === 'domains' ? '/domains/' + id : (key === 'accounts' ? '/accounts/' + id : '/billing/' + id);
			return {
				title: esc(name),
				meta: esc(metaParts.join(' - ') || '-'),
				hint,
				aside: statusBadge(status),
				route,
				ariaLabel: 'Open ' + name,
			};
		});
		return renderStackRows(cards, 'No records match the current view.', { pageKey, label: 'records', perPage: 50 });
	}

	function renderLoading(label) {
		return `<div class="loading-state">${esc(label)}...</div>`;
	}

	function renderEmpty(label) {
		return `<div class="empty-state">${esc(label)}</div>`;
	}

	function renderError(message, action) {
		return `<div class="error-state"><div>${esc(message)}</div><button class="secondary-btn" type="button" data-action="${attr(action)}">Retry</button></div>`;
	}

	function allVisibleSelected(sites) {
		const keys = sites.flatMap(site => visibleEnvironments(site).map(env => envKey(site, env)));
		return keys.length > 0 && keys.every(key => state.selection.has(key));
	}

	function renderBulkBar() {
		if (!state.selection.size) {
			bulkRegion.classList.remove('visible');
			bulkRegion.innerHTML = '';
			return;
		}
		bulkRegion.classList.add('visible');
		bulkRegion.innerHTML = `
			<span class="count">${state.selection.size} selected</span>
			<button type="button" data-action="bulk-terminal">Terminal</button>
			<button type="button" data-action="bulk-open">Open</button>
			<button type="button" data-action="bulk-login">WP Admin</button>
			<button type="button" data-action="clear-selection">Clear</button>
		`;
	}

	function renderTerminalJob(job) {
		const running = job.status === 'running' || job.status === 'queued';
		const lines = job.stream.flatMap(chunk => String(chunk || '').split('\n')).filter(line => line.trim() !== 'Finished.');
		return `
			<div class="terminal-job">
				<div class="terminal-job__head">
					<span>-></span>
					<strong>${esc(job.description || 'Command')}</strong>
					${running ? '<button type="button" data-action="terminal-cancel-job" data-job-client-id="' + attr(job.clientId) + '">Cancel</button>' : '<button type="button" data-action="terminal-copy-job" data-job-client-id="' + attr(job.clientId) + '">Copy</button>'}
					<time>${esc(formatDate(job.created_at))}</time>
				</div>
				${lines.length ? lines.map(line => '<span class="terminal-line' + (job.status === 'error' ? ' error' : '') + '">' + esc(line || ' ') + '</span>').join('') : '<span class="terminal-line">Waiting for output...</span>'}
				${job.status === 'error' ? '<span class="terminal-line error">Process failed.</span>' : ''}
				${job.status === 'cancelled' ? '<span class="terminal-line error">Cancelled.</span>' : ''}
			</div>
		`;
	}

	function renderTerminal() {
		if (!terminalRegion) return;
		const active = terminalActiveJob();
		const running = runningJobCount();
		if (!state.terminal.open) {
			if (!state.terminal.show && !state.jobs.length) {
				terminalRegion.innerHTML = '';
				return;
			}
			terminalRegion.innerHTML = `
				<div class="terminal-island" role="button" tabindex="0" data-action="terminal-open">
					<span class="terminal-island__status ${running ? 'running' : ''}"></span>
					<span class="terminal-island__text">
						<strong>${esc(active?.description || 'Terminal Ready')}</strong>
						<small>${esc(lastJobLine(active) || (state.terminal.selectedTargets.length ? state.terminal.selectedTargets.length + ' target(s) selected' : 'No active output'))}</small>
					</span>
					<button class="icon-btn" type="button" data-action="terminal-hide" aria-label="Hide">${icons.close}</button>
				</div>
			`;
			return;
		}
		const selectedCount = state.terminal.selectedTargets.length;
		const targetLabel = selectedCount === 1 ? (state.terminal.selectedTargets[0].home_url || state.terminal.selectedTargets[0].name) : selectedCount + ' environments selected';
		const targets = filteredTerminalTargets().slice(0, 150);
		const selectedKeys = new Set(state.terminal.selectedTargets.map(targetKey));
		const tools = filteredTerminalTools().slice(0, 120);
		const jobs = state.jobs.length ? state.jobs.map(renderTerminalJob).join('') : '<div class="terminal-empty"><div>' + icons.terminal + '<br>Select targets and enter a command.</div></div>';
		terminalRegion.innerHTML = `
			<div class="terminal-window ${state.terminal.fullscreen ? 'fullscreen' : ''}">
				<div class="terminal-head">
					<span class="terminal-dots">
						<button class="terminal-dot close" type="button" data-action="terminal-close" aria-label="Close"></button>
						<button class="terminal-dot hide" type="button" data-action="terminal-minimize" aria-label="Minimize"></button>
						<button class="terminal-dot full" type="button" data-action="terminal-fullscreen" aria-label="Fullscreen"></button>
					</span>
					<div class="terminal-title">captaincore-cli - ${esc(targetLabel)}</div>
					<button type="button" data-action="terminal-close" aria-label="Close">Close</button>
				</div>
				<div class="terminal-body">
					<aside class="terminal-sidebar">
						<input class="terminal-search" type="search" value="${attr(state.terminal.targetSearch)}" data-input="terminal-target-search" placeholder="Search targets">
						<div>
							<div class="terminal-section-title">Targets</div>
							<div class="terminal-actions" style="justify-content:flex-start;margin-bottom:7px">
								<button type="button" data-action="terminal-add-filtered">Add Filtered</button>
								<button type="button" data-action="terminal-clear-targets">Clear</button>
							</div>
						</div>
						<div class="terminal-targets">
							${targets.map(target => `
								<button type="button" class="terminal-target ${selectedKeys.has(targetKey(target)) ? 'active' : ''}" data-action="terminal-toggle-target" data-env-id="${attr(target.environment_id)}">
									<strong>${selectedKeys.has(targetKey(target)) ? '[x] ' : '[ ] '}${esc(target.home_url || target.name)}</strong>
									<small>${esc(target.name)} - ${esc(target.environment)}</small>
								</button>
							`).join('') || '<div class="terminal-empty">No targets found.</div>'}
						</div>
						<div class="terminal-tools">
							<div class="terminal-section-title">Tools</div>
							<input class="terminal-search" type="search" value="${attr(state.terminal.toolSearch)}" data-input="terminal-tool-search" placeholder="Search tools and recipes">
							<div class="terminal-actions" style="justify-content:flex-start;margin:7px 0">
								<button type="button" class="${state.terminal.toolTab === 'system' ? 'primary' : ''}" data-action="terminal-tool-tab" data-tab="system">System</button>
								<button type="button" class="${state.terminal.toolTab === 'cookbook' ? 'primary' : ''}" data-action="terminal-tool-tab" data-tab="cookbook">Cookbook</button>
							</div>
							${state.terminal.toolTab === 'cookbook' && state.loading.recipes ? '<div class="terminal-line">Loading recipes...</div>' : ''}
							${state.terminal.toolTab === 'cookbook' && state.error.recipes ? '<div class="terminal-line error">' + esc(state.error.recipes) + '</div>' : ''}
							${tools.map(item => state.terminal.toolTab === 'cookbook' ? `
								<button type="button" class="terminal-tool" data-action="terminal-preview-recipe" data-recipe-id="${attr(item.recipe_id)}">
									<strong>${esc(item.title || 'Recipe')}</strong>
									<small>${item.public == 1 ? 'Public recipe' : 'Private recipe'}</small>
								</button>
							` : `
								<button type="button" class="terminal-tool" data-action="terminal-system-tool" data-tool="${attr(item.key)}">
									<strong>${esc(item.label)}</strong>
									<small>${esc(item.description)}</small>
								</button>
							`).join('') || '<div class="terminal-line">No matches.</div>'}
						</div>
					</aside>
					<main class="terminal-main">
						<div class="terminal-output" data-terminal-output>${jobs}</div>
						<div class="terminal-input">
							<div class="terminal-prompt">$</div>
							<textarea class="terminal-command" rows="1" data-input="terminal-command" spellcheck="false" placeholder="Enter WP-CLI command or Bash script. Cmd/Ctrl+Enter runs it.">${esc(state.terminal.command)}</textarea>
							<div class="terminal-actions">
								<button type="button" data-action="terminal-save-open">${icons.save} Save</button>
								<button type="button" data-action="terminal-schedule-open">${icons.clock} Schedule</button>
								<button type="button" class="primary" data-action="terminal-run">Run</button>
							</div>
						</div>
					</main>
				</div>
			</div>
		`;
		scrollTerminalToBottom();
	}

	function findSiteAndEnv(siteId, envId) {
		let site = state.sites.find(item => String(item.site_id) === String(siteId));
		const detail = state.siteDetails[siteId];
		if (!site && detail) site = Object.assign({}, detail.site || {}, { environments: detail.environments || [] });
		else if (site && detail) site = Object.assign({}, site, detail.site || {}, { environments: detail.environments || site.environments || [] });
		if (!site) return {};
		const envs = getEnvironments(site);
		const env = envs.find(item => String(item.environment_id) === String(envId)) || envs.find(item => String(item.environment) === String(envId)) || primaryEnv(site);
		return { site, env };
	}

	function renderDrawer() {
		const open = !!state.drawer;
		drawerBackdrop.classList.toggle('open', open);
		drawerRegion.classList.toggle('open', open);
		drawerRegion.setAttribute('aria-hidden', open ? 'false' : 'true');
		if (!open) {
			drawerRegion.innerHTML = '';
			return;
		}
		const { site, env } = findSiteAndEnv(state.drawer.siteId, state.drawer.envId);
		if (!site || !env) return;
		const shot = screenshotUrl(site, env, 800);
		const status = environmentStatus(env);
		drawerRegion.innerHTML = `
			<div class="drawer__head">
				${shot ? '<img class="drawer__preview" src="' + attr(shot) + '" alt="">' : '<div class="drawer__preview"></div>'}
				<div>
					<h2>${esc(site.name || site.site || 'Site')}</h2>
					<div class="summary__sub">${esc(env.environment || 'Production')} - ${esc(formatUrl(env.home_url))}</div>
				</div>
				<button class="drawer__close" type="button" data-action="close-drawer" aria-label="Close">${icons.close}</button>
			</div>
			<nav class="drawer__tabs">
				<button class="active" type="button">Overview</button>
				<button type="button">Backups</button>
				<button type="button">Updates</button>
				<button type="button">Logs</button>
			</nav>
			<div class="drawer__body">
				<div class="kv-grid">
					<div class="kv"><div class="k">Visits</div><div class="v">${formatNumber(env.visits)}</div></div>
					<div class="kv"><div class="k">Storage</div><div class="v">${formatStorage(env.storage)}</div></div>
					<div class="kv"><div class="k">WordPress</div><div class="v">${esc(env.core || '-')}</div></div>
					<div class="kv"><div class="k">PHP</div><div class="v">${esc(env.php_version || '-')}</div></div>
				</div>
				<div class="section-title">Status</div>
				<div class="activity">
					<div class="activity__row"><span class="dot ${status.dot}"></span><span>${esc(status.label)}</span><time>${esc(env.environment || '')}</time></div>
					<div class="activity__row"><span class="dot"></span><span>${esc(formatUrl(env.home_url))}</span><time>URL</time></div>
					<div class="activity__row"><span class="dot"></span><span>${formatNumber(env.subsite_count || 0)} subsites</span><time>Sites</time></div>
				</div>
			</div>
			<div class="drawer__cta">
				<a class="primary-btn" href="${attr(routeUrl('/sites/' + site.site_id))}" data-route="/sites/${attr(site.site_id)}">Manage</a>
				${env.home_url ? '<button class="secondary-btn" type="button" data-action="open-url" data-url="' + attr(env.home_url) + '">' + icons.external + ' Open</button>' : ''}
				<button class="secondary-btn" type="button" data-action="magic-login" data-site-id="${attr(site.site_id)}" data-env-id="${attr(env.environment_id || env.environment)}">${icons.login} WP Admin</button>
			</div>
		`;
	}

	function globalSearchScopes() {
		return [
			{ key: 'all', label: 'All' },
			{ key: 'sites', label: 'Sites' },
			{ key: 'domains', label: 'Domains' },
			{ key: 'accounts', label: 'Accounts' },
			{ key: 'billing', label: 'Billing' },
		].filter(scope => scope.key !== 'billing' || !!CC.modules?.billing);
	}

	function globalSearchItems() {
		const items = [];
		state.sites.forEach(site => {
			const siteId = site.site_id || site.id;
			const siteName = site.name || site.site || 'Untitled site';
			const account = site.customer_name || site.account || site.customer || '';
			items.push({
				type: 'sites',
				typeLabel: 'Site',
				icon: icons.monitor,
				label: siteName,
				sub: [account, site.site].filter(Boolean).join(' - '),
				route: siteId ? '/sites/' + siteId : '/sites',
				haystack: [siteName, site.site, account, site.customer, site.customer_name].join(' '),
			});
			getEnvironments(site).forEach(env => {
				const envName = env.environment || 'Environment';
				items.push({
					type: 'sites',
					typeLabel: envName,
					icon: icons.monitor,
					label: formatUrl(env.home_url || siteName),
					sub: [siteName, env.core ? 'WP ' + env.core : '', env.php_version ? 'PHP ' + env.php_version : ''].filter(Boolean).join(' - '),
					route: siteId ? '/sites/' + siteId : '/sites',
					haystack: [siteName, site.site, account, envName, env.home_url, env.core, env.php_version, env.address].join(' '),
				});
			});
		});
		state.domains.forEach(domain => {
			const id = domain.domain_id || domain.id;
			const name = domain.name || domain.domain || 'Domain';
			items.push({
				type: 'domains',
				typeLabel: 'Domain',
				icon: icons.globe,
				label: name,
				sub: [domain.account, domain.customer, domain.status, domain.expires ? 'expires ' + formatDate(domain.expires) : ''].filter(Boolean).join(' - '),
				route: id ? '/domains/' + id : '/domains',
				haystack: [name, domain.account, domain.customer, domain.status, domain.remote_id, domain.provider_id].join(' '),
			});
		});
		state.accounts.forEach(account => {
			const id = account.account_id || account.id;
			const name = account.name || account.title || account.email || 'Account';
			items.push({
				type: 'accounts',
				typeLabel: 'Account',
				icon: icons.users,
				label: name,
				sub: [account.email, account.plan?.name, account.status].filter(Boolean).join(' - '),
				route: id ? '/accounts/' + id : '/accounts',
				haystack: [name, account.email, account.plan?.name, account.status, account.customer].join(' '),
			});
		});
		state.subscriptions.forEach(subscription => {
			const name = subscription.name || subscription.title || subscription.account || subscription.email || 'Subscription';
			items.push({
				type: 'billing',
				typeLabel: 'Billing',
				icon: icons.credit,
				label: name,
				sub: [subscription.email, subscription.status, subscription.plan?.name, subscription.total ? formatMoney(subscription.total) : ''].filter(Boolean).join(' - '),
				route: '/billing',
				haystack: [name, subscription.email, subscription.status, subscription.plan?.name, subscription.total, subscription.subscription_id].join(' '),
			});
		});
		return items;
	}

	function globalSearchResults() {
		const q = normalizeText(state.globalSearch.query).trim();
		const scope = state.globalSearch.scope || 'all';
		const terms = q.split(/\s+/).filter(Boolean);
		let items = globalSearchItems().filter(item => scope === 'all' || item.type === scope);
		if (terms.length) {
			items = items.map(item => {
				const haystack = normalizeText([item.label, item.sub, item.typeLabel, item.haystack].join(' '));
				const matches = terms.every(term => haystack.includes(term));
				if (!matches) return null;
				let score = 0;
				const label = normalizeText(item.label);
				if (label === q) score += 20;
				if (label.startsWith(q)) score += 10;
				if (haystack.includes(q)) score += 3;
				score += item.type === 'sites' ? 1 : 0;
				return Object.assign({}, item, { score });
			}).filter(Boolean).sort((a, b) => b.score - a.score || a.label.localeCompare(b.label));
		} else {
			items = items.slice(0, 36);
		}
		return items.slice(0, 80);
	}

	function groupedGlobalSearchResults() {
		const groups = { sites: [], domains: [], accounts: [], billing: [] };
		globalSearchResults().forEach(item => {
			if (groups[item.type]) groups[item.type].push(item);
		});
		return groups;
	}

	function renderGlobalSearchModal() {
		const loading = ['sites', 'domains', 'accounts', 'subscriptions'].some(key => state.loading[key]);
		const scopes = globalSearchScopes();
		const groups = groupedGlobalSearchResults();
		const flat = Object.values(groups).flat();
		if (state.globalSearch.activeIndex >= flat.length) state.globalSearch.activeIndex = Math.max(0, flat.length - 1);
		const groupLabels = { sites: 'Sites & Environments', domains: 'Domains', accounts: 'Accounts', billing: 'Billing' };
		let index = -1;
		const sections = Object.keys(groups).map(type => {
			const rows = groups[type];
			if (!rows.length) return '';
			return `
				<div class="search-group">
					<div class="search-group__title">${groupLabels[type]}</div>
					${rows.map(item => {
						index++;
						return `
							<a class="search-result ${index === state.globalSearch.activeIndex ? 'active' : ''}" href="${attr(routeUrl(item.route))}" data-route="${attr(item.route)}" data-search-index="${index}">
								<span class="search-result__icon">${item.icon}</span>
								<span><strong>${esc(item.label)}</strong><small>${esc(item.sub || item.route)}</small></span>
								<span class="search-result__type">${esc(item.typeLabel)}</span>
							</a>
						`;
					}).join('')}
				</div>
			`;
		}).join('');
		const empty = !flat.length ? renderEmpty(state.globalSearch.query ? 'No results match that search.' : 'No searchable records loaded yet.') : '';
		return `
			<div class="modal-card search-modal" role="dialog" aria-modal="true" aria-label="Search">
				<div class="search-modal__bar">
					${icons.search}
					<input id="cc-global-search" type="search" value="${attr(state.globalSearch.query)}" data-input="global-search" aria-label="Global search" placeholder="Search sites, domains, accounts...">
					<kbd>esc</kbd>
				</div>
				<div class="search-modal__scopes">
					${scopes.map(scope => '<button type="button" class="' + (state.globalSearch.scope === scope.key ? 'active' : '') + '" data-action="search-scope" data-scope="' + attr(scope.key) + '">' + esc(scope.label) + '</button>').join('')}
				</div>
				<div class="search-modal__body">
					${loading ? renderLoading('Loading searchable records') : ''}
					${sections || empty}
				</div>
				<div class="search-modal__foot">
					<span>${formatNumber(flat.length)} result${flat.length === 1 ? '' : 's'}</span>
					<span>Press Enter to open the selected result.</span>
				</div>
			</div>
		`;
	}

	function ensureGlobalSearchData() {
		const loaders = [
			loadCollection('sites', '/wp-json/captaincore/v1/sites'),
			loadCollection('domains', '/wp-json/captaincore/v1/domains/'),
			loadCollection('accounts', '/wp-json/captaincore/v1/accounts/'),
		];
		if (CC.modules?.billing) loaders.push(loadCollection('subscriptions', '/wp-json/captaincore/v1/subscriptions/'));
		return Promise.allSettled(loaders);
	}

	function openGlobalSearch() {
		if (!state.nonce) return;
		state.modal = 'global-search';
		state.drawer = null;
		state.popover = '';
		state.globalSearch.activeIndex = 0;
		render();
		ensureGlobalSearchData();
	}

	function openActiveGlobalSearchResult() {
		if (state.modal !== 'global-search') return;
		const results = Object.values(groupedGlobalSearchResults()).flat();
		const result = results[state.globalSearch.activeIndex] || results[0];
		if (!result) return;
		state.modal = null;
		navTo(result.route);
	}

	function renderCapturesModal() {
		const captureState = state.captures;
		const capture = selectedCapture();
		const captureIndex = capture ? captureState.captures.indexOf(capture) : -1;
		const pages = normalizedCapturePages(capture);
		const page = selectedCapturePage(capture);
		const imageUrl = capturePageImageUrl(capture, page);
		const pageName = page ? firstDefined(page.name, page.page, page.path, '/') : '';
		const captureMeta = capture ? [captureLabel(capture, captureIndex), pageName].filter(Boolean).join(' - ') : '';
		const disabled = captureState.loading || captureState.saving || captureState.requesting ? ' disabled' : '';
		const captureOptions = captureState.captures.map((item, index) => {
			const id = captureId(item, index);
			return '<option value="' + attr(id) + '" ' + (id === captureState.selectedCaptureId ? 'selected' : '') + '>' + esc(captureLabel(item, index)) + '</option>';
		}).join('');
		const pageOptions = pages.map((item, index) => {
			const key = capturePageKey(item, index);
			const label = firstDefined(item.name, item.page, item.path, 'Page ' + (index + 1));
			return '<option value="' + attr(key) + '" ' + (key === captureState.selectedPageKey ? 'selected' : '') + '>' + esc(label) + '</option>';
		}).join('');
		return `
			<div class="modal-card captures-modal" role="dialog" aria-modal="true" aria-label="Visual captures">
				<div class="modal-head">
					<div>
						<h2>Visual Captures</h2>
						<div class="summary__sub">${esc(captureState.homeUrl || captureState.siteName || '')}</div>
					</div>
					<button class="icon-btn" type="button" data-action="close-modal" aria-label="Close">${icons.close}</button>
				</div>
				<div class="captures-content">
					<div class="captures-toolbar">
						<div class="captures-controls">
							<div class="captures-control">
								<label for="capture-select">Taken On</label>
								<select id="capture-select" data-input="capture-select" ${captureState.captures.length ? '' : 'disabled'}>
									${captureState.captures.length ? captureOptions : '<option>No captures</option>'}
								</select>
							</div>
							<div class="captures-control">
								<label for="capture-page-select">Page</label>
								<select id="capture-page-select" data-input="capture-page-select" ${pages.length ? '' : 'disabled'}>
									${pages.length ? pageOptions : '<option>No pages</option>'}
								</select>
							</div>
						</div>
						<div class="captures-actions">
							${imageUrl ? '<button class="secondary-btn" type="button" data-action="open-url" data-url="' + attr(imageUrl) + '">' + icons.external + ' Open Image</button>' : ''}
							<button class="secondary-btn" type="button" data-action="capture-config-toggle">${icons.settings} Configure</button>
							<button class="primary-btn" type="button" data-action="capture-check"${disabled}>${icons.refresh} ${captureState.requesting ? 'Requesting' : 'Check Now'}</button>
						</div>
					</div>
					<div class="captures-body">
						${captureState.error ? '<div class="error-state"><div>' + esc(captureState.error) + '</div></div>' : ''}
						${captureState.showConfig ? `
							<form class="capture-config-panel" data-action="save-capture-config" data-capture-config-form>
								<div>
									<div class="section-title" style="margin-top:0">Capture Pages</div>
									<div class="capture-pages-list">
										${captureState.pages.map((item, index) => `
											<div class="capture-page-row">
												<input type="text" name="capture_pages[]" value="${attr(item.page || '/')}" placeholder="/page-path">
												<button class="secondary-btn" type="button" data-action="capture-remove-page" data-index="${index}" ${captureState.pages.length <= 1 ? 'disabled' : ''}>${icons.close}</button>
											</div>
										`).join('')}
									</div>
								</div>
								<button class="secondary-btn" type="button" data-action="capture-add-page">${icons.plus} Add Page</button>
								<div class="capture-auth-grid">
									<label>Basic Auth Username
										<input type="text" name="auth_username" value="${attr(captureState.auth.username || '')}" autocomplete="off">
									</label>
									<label>Basic Auth Password
										<input type="password" name="auth_password" value="${attr(captureState.auth.password || '')}" autocomplete="off">
									</label>
								</div>
								<div style="display:flex;justify-content:flex-end;gap:8px">
									<button class="secondary-btn" type="button" data-action="capture-config-toggle">Cancel</button>
									<button class="primary-btn" type="submit"${captureState.saving ? ' disabled' : ''}>${captureState.saving ? 'Saving' : 'Save Configuration'}</button>
								</div>
							</form>
						` : ''}
						${captureState.loading ? renderLoading('Loading captures') : ''}
						${!captureState.loading && !captureState.error && !captureState.captures.length ? renderEmpty('No visual captures yet.') : ''}
						${!captureState.loading && !captureState.error && captureState.captures.length && !imageUrl ? renderEmpty('No image is available for the selected capture.') : ''}
						${!captureState.loading && !captureState.error && imageUrl ? `
							<div class="capture-viewer">
								<div class="capture-viewer__meta">${esc(captureMeta)}</div>
								<div class="capture-image-frame">
									<img src="${attr(imageUrl)}" alt="${attr(pageName || 'Visual capture')}" loading="lazy">
								</div>
							</div>
						` : ''}
					</div>
				</div>
			</div>
		`;
	}

	function renderModal() {
		const open = !!state.modal;
		modalBackdrop.classList.toggle('open', open);
		modalRegion.classList.toggle('open', open);
		if (!open) {
			modalRegion.innerHTML = '';
			return;
		}
		if (state.modal === 'global-search') {
			modalRegion.innerHTML = renderGlobalSearchModal();
			requestAnimationFrame(() => {
				const input = document.getElementById('cc-global-search');
				if (input && state.modal === 'global-search' && document.activeElement !== input) {
					const pos = input.value.length;
					input.focus();
					input.setSelectionRange(pos, pos);
				}
			});
			return;
		}
		if (state.modal === 'captures') {
			modalRegion.innerHTML = renderCapturesModal();
			return;
		}
		if (state.modal === 'terminal-schedule') {
			modalRegion.innerHTML = `
				<form class="modal-card" data-action="schedule-terminal-script">
					<div class="modal-head">
						<div>
							<h2>Schedule Script</h2>
							<div class="summary__sub">${formatNumber(state.terminal.selectedTargets.length)} target environment${state.terminal.selectedTargets.length === 1 ? '' : 's'}</div>
						</div>
						<button class="icon-btn" type="button" data-action="close-modal" aria-label="Close">${icons.close}</button>
					</div>
					<div class="form-grid">
						${state.terminal.schedule.error ? '<div class="alert error">' + esc(state.terminal.schedule.error) + '</div>' : ''}
						<div class="form-field"><label for="terminal-schedule-date">Date</label><input id="terminal-schedule-date" type="date" name="date" value="${attr(state.terminal.schedule.date)}" required></div>
						<div class="form-field"><label for="terminal-schedule-time">Time</label><input id="terminal-schedule-time" type="time" name="time" value="${attr(state.terminal.schedule.time)}" required></div>
						<pre class="code-block">${esc(state.terminal.command)}</pre>
						<div style="display:flex;justify-content:flex-end;gap:8px">
							<button class="secondary-btn" type="button" data-action="close-modal">Cancel</button>
							<button class="primary-btn" type="submit">${state.terminal.schedule.loading ? 'Scheduling' : 'Schedule'}</button>
						</div>
					</div>
				</form>
			`;
			return;
		}
		if (state.modal === 'terminal-save-recipe') {
			modalRegion.innerHTML = `
				<form class="modal-card" data-action="save-terminal-recipe">
					<div class="modal-head">
						<h2>Save Recipe</h2>
						<button class="icon-btn" type="button" data-action="close-modal" aria-label="Close">${icons.close}</button>
					</div>
					<div class="form-grid">
						${state.terminal.saveRecipe.error ? '<div class="alert error">' + esc(state.terminal.saveRecipe.error) + '</div>' : ''}
						<div class="form-field"><label for="terminal-recipe-title">Name</label><input id="terminal-recipe-title" name="title" value="${attr(state.terminal.saveRecipe.title)}" required></div>
						<div class="form-field"><label for="terminal-recipe-content">Content</label><textarea id="terminal-recipe-content" name="content" rows="9" spellcheck="false">${esc(state.terminal.command)}</textarea></div>
						${CC.user?.role === 'administrator' || CC.user?.role === 'owner' ? '<label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="public" value="1" ' + (state.terminal.saveRecipe.public ? 'checked' : '') + '> Public recipe</label>' : ''}
						<div style="display:flex;justify-content:flex-end;gap:8px">
							<button class="secondary-btn" type="button" data-action="close-modal">Cancel</button>
							<button class="primary-btn" type="submit">${state.terminal.saveRecipe.loading ? 'Saving' : 'Save Recipe'}</button>
						</div>
					</div>
				</form>
			`;
			return;
		}
		if (state.modal === 'quicksave-diff') {
			modalRegion.innerHTML = `
				<div class="modal-card" role="dialog" aria-modal="true" aria-label="Quicksave file diff">
					<div class="modal-head">
						<div>
							<h2>File Diff</h2>
							<div class="summary__sub">${esc(state.fileDiff.file || '')}</div>
						</div>
						<button class="icon-btn" type="button" data-action="close-modal" aria-label="Close">${icons.close}</button>
					</div>
					${state.fileDiff.loading ? renderLoading('Loading diff') : ''}
					${state.fileDiff.error ? '<div class="error-state"><div>' + esc(state.fileDiff.error) + '</div><button class="secondary-btn" type="button" data-action="quicksave-diff" data-hash="' + attr(state.fileDiff.hash) + '" data-file="' + attr(state.fileDiff.file) + '">Retry</button></div>' : ''}
					${!state.fileDiff.loading && !state.fileDiff.error ? '<pre class="code-block diff-output">' + renderDiffContent(state.fileDiff.content) + '</pre>' : ''}
				</div>
			`;
			return;
		}
		if (state.modal === 'new-site') {
			modalRegion.innerHTML = `
				<form class="modal-card" data-action="create-site">
					<div class="modal-head">
						<h2>New Site</h2>
						<button class="icon-btn" type="button" data-action="close-modal" aria-label="Close">${icons.close}</button>
					</div>
					<div class="form-grid">
						${state.newSite.errors ? '<div class="alert error">' + esc(state.newSite.errors) + '</div>' : ''}
						<div class="form-field"><label for="new-site-name">Name</label><input id="new-site-name" name="name" value="${attr(state.newSite.name)}" required></div>
						<div class="form-field"><label for="new-site-domain">Domain</label><input id="new-site-domain" name="domain" value="${attr(state.newSite.domain)}"></div>
						<div class="form-field"><label for="new-site-slug">Site Slug</label><input id="new-site-slug" name="site" value="${attr(state.newSite.site)}"></div>
						<div class="form-field"><label for="new-site-address">Server Address</label><input id="new-site-address" name="address" value="${attr(state.newSite.address)}"></div>
						<div class="form-field"><label for="new-site-username">Username</label><input id="new-site-username" name="username" value="${attr(state.newSite.username)}"></div>
						<div class="form-field"><label for="new-site-password">Password</label><input id="new-site-password" type="password" name="password" value="${attr(state.newSite.password)}"></div>
						<div style="display:flex;justify-content:flex-end;gap:8px;margin-top:8px">
							<button class="secondary-btn" type="button" data-action="close-modal">Cancel</button>
							<button class="primary-btn" type="submit">${state.newSite.loading ? 'Creating' : 'Create Site'}</button>
						</div>
					</div>
				</form>
			`;
		}
	}

	function showToast(message) {
		toastRegion.textContent = message;
		toastRegion.classList.add('visible');
		clearTimeout(showToast.timer);
		showToast.timer = setTimeout(() => toastRegion.classList.remove('visible'), 2600);
	}

	function getFilterOptions(type) {
		if (type === 'core') return CC.site_filters_core || [];
		return (CC.site_filters || []).filter(f => f.type === type);
	}

	async function applyFilters() {
		if (!activeFilters()) {
			state.filteredSiteIds = null;
			state.filteredEnvIds = null;
			render();
			return;
		}
		try {
			const payload = {
				logic: 'and',
				version_logic: 'and',
				status_logic: 'and',
				themes: state.selectedThemes.map(({ name, title, search, type }) => ({ name, title, search, type })),
				plugins: state.selectedPlugins.map(({ name, title, search, type }) => ({ name, title, search, type })),
				core: state.selectedCore.map(item => item.name),
				versions: [],
				statuses: [],
				backup_mode: null,
			};
			const data = await apiFetch('/wp-json/captaincore/v1/filters/sites', {
				method: 'POST',
				body: JSON.stringify(payload),
			});
			const results = Array.isArray(data.results) ? data.results : [];
			state.filteredSiteIds = new Set(results.map(item => item.site_id));
			state.filteredEnvIds = new Set(results.map(item => item.environment_id));
		} catch (error) {
			showToast('Filter request failed.');
			state.filteredSiteIds = null;
			state.filteredEnvIds = null;
		}
		render();
	}

	function toggleFilter(type, item) {
		const key = type === 'core' ? 'selectedCore' : (type === 'themes' ? 'selectedThemes' : 'selectedPlugins');
		const idx = state[key].findIndex(existing => existing.name === item.name);
		if (idx >= 0) state[key].splice(idx, 1);
		else state[key].push(item);
		state.page = 1;
		state.pages = {};
		applyFilters();
	}

	function selectedRows() {
		const rows = [];
		state.sites.forEach(site => {
			getEnvironments(site).forEach(env => {
				if (state.selection.has(envKey(site, env))) rows.push({ site, env });
			});
		});
		return rows;
	}

	async function magicLogin(siteId, envId, userId) {
		const { env } = findSiteAndEnv(siteId, envId);
		if (!env) return;
		const envName = String(env.environment || 'production').toLowerCase();
		try {
			let endpoint = '/wp-json/captaincore/v1/sites/' + encodeURIComponent(siteId) + '/' + encodeURIComponent(envName) + '/magiclogin';
			if (userId) endpoint += '?user_id=' + encodeURIComponent(userId);
			const url = await apiFetch(endpoint);
			if (typeof url === 'string') safeOpen(url);
			else showToast('Login failed.');
		} catch (error) {
			showToast('Login request failed.');
		}
	}

	async function signIn(form) {
		state.login.user_login = form.user_login.value;
		state.login.user_password = form.user_password.value;
		state.login.tfa_code = form.tfa_code ? form.tfa_code.value : '';
		state.login.loading = true;
		state.login.errors = '';
		state.login.info = '';
		render();
		try {
			const data = await apiFetch('/wp-json/captaincore/v1/login/', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ command: 'signIn', login: state.login }),
			});
			if (!data.errors && !data.info) {
				window.location = window.location.origin + routeUrl('/sites');
				return;
			}
			state.login.errors = data.errors || '';
			state.login.info = data.info || '';
		} catch (error) {
			state.login.errors = error.message || 'Sign in failed.';
		} finally {
			state.login.loading = false;
			render();
		}
	}

	async function signOut() {
		try {
			await apiFetch('/wp-json/captaincore/v1/login/', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ command: 'signOut' }),
			});
		} catch (error) {}
		window.location = window.location.origin + routeUrl('/login');
	}

	async function createSite(form) {
		state.newSite = Object.assign(state.newSite, {
			name: form.name.value,
			domain: form.domain.value,
			site: form.site.value,
			address: form.address.value,
			username: form.username.value,
			password: form.password.value,
			loading: true,
			errors: '',
		});
		render();
		const payload = {
			site: {
				name: state.newSite.name,
				site: state.newSite.site || state.newSite.name,
				environments: [{
					environment: 'Production',
					site: state.newSite.site || state.newSite.name,
					address: state.newSite.address,
					username: state.newSite.username,
					password: state.newSite.password,
					protocol: 'sftp',
					port: '2222',
					home_directory: '',
					updates_enabled: '1',
				}],
			},
		};
		try {
			await apiFetch('/wp-json/captaincore/v1/sites', {
				method: 'POST',
				body: JSON.stringify(payload),
			});
			state.modal = null;
			state.newSite = { name: '', domain: '', site: '', address: '', username: '', password: '', loading: false, errors: '' };
			state.fetched.sites = false;
			await loadCollection('sites', '/wp-json/captaincore/v1/sites', true);
			showToast('Site created.');
		} catch (error) {
			state.newSite.loading = false;
			state.newSite.errors = error.message || 'Unable to create site.';
			render();
		}
	}

	async function scheduleTerminalScript(form) {
		const code = state.terminal.command.trim();
		const targets = state.terminal.selectedTargets;
		if (!code || !targets.length) {
			state.terminal.schedule.error = 'A command and at least one target are required.';
			render();
			return;
		}
		state.terminal.schedule.date = form.date.value;
		state.terminal.schedule.time = form.time.value;
		state.terminal.schedule.loading = true;
		state.terminal.schedule.error = '';
		render();
		try {
			await Promise.all(targets.map(target => apiFetch('/wp-json/captaincore/v1/scripts/schedule', {
				method: 'POST',
				body: JSON.stringify({
					environment_id: target.environment_id,
					code,
					run_at: {
						date: state.terminal.schedule.date,
						time: state.terminal.schedule.time,
						timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
					},
				}),
			})));
			state.modal = null;
			state.terminal.command = '';
			showToast('Script scheduled.');
			const { id } = currentSiteContext();
			if (id) loadSiteDetail(id, true);
		} catch (error) {
			state.terminal.schedule.error = error.message || 'Unable to schedule script.';
		} finally {
			state.terminal.schedule.loading = false;
			render();
		}
	}

	async function saveTerminalRecipe(form) {
		const title = form.title.value.trim();
		const content = form.content.value;
		if (!title || !content.trim()) {
			state.terminal.saveRecipe.error = 'Name and content are required.';
			render();
			return;
		}
		state.terminal.saveRecipe.title = title;
		state.terminal.saveRecipe.public = form.public && form.public.checked ? 1 : 0;
		state.terminal.saveRecipe.loading = true;
		state.terminal.saveRecipe.error = '';
		render();
		try {
			const payload = await apiFetch('/wp-json/captaincore/v1/recipes', {
				method: 'POST',
				body: JSON.stringify({
					title,
					content,
					public: state.terminal.saveRecipe.public,
				}),
			});
			state.recipes = Array.isArray(payload) ? payload : state.recipes;
			state.fetched.recipes = true;
			state.modal = null;
			state.terminal.saveRecipe = { title: '', public: 1, loading: false, error: '' };
			showToast('Recipe saved.');
		} catch (error) {
			state.terminal.saveRecipe.error = error.message || 'Unable to save recipe.';
		} finally {
			state.terminal.saveRecipe.loading = false;
			render();
		}
	}

	document.addEventListener('click', function (event) {
		const route = event.target.closest('[data-route]');
		if (route) {
			event.preventDefault();
			state.modal = null;
			state.drawer = null;
			navTo(route.dataset.route);
			return;
		}
		const actionEl = event.target.closest('[data-action]');
		if (!actionEl) {
			if (state.popover && !event.target.closest('.chip-wrap')) {
				state.popover = '';
				render();
			}
			return;
		}
		const action = actionEl.dataset.action;
		if (action === 'focus-search') {
			openGlobalSearch();
		}
		if (action === 'search-scope') {
			state.globalSearch.scope = actionEl.dataset.scope || 'all';
			state.globalSearch.activeIndex = 0;
			render();
		}
		if (action === 'toggle-theme') {
			state.theme = state.theme === 'light' ? 'dark' : 'light';
			localStorage.setItem('captaincore-v2-theme', state.theme);
			document.documentElement.dataset.theme = state.theme;
		}
		if (action === 'sign-out') signOut();
		if (action === 'set-view') {
			state.viewMode = actionEl.dataset.view;
			localStorage.setItem('captaincore-v2-view', state.viewMode);
			render();
		}
		if (action === 'detail-tab') {
			state.detailTabs[actionEl.dataset.detailType] = actionEl.dataset.tab;
			render();
			if (actionEl.dataset.detailType === 'site' && isSiteFeatureTab(actionEl.dataset.tab)) {
				const { id, env } = currentSiteContext();
				loadSiteFeature(actionEl.dataset.tab, id, env);
			}
		}
		if (action === 'select-detail-env') {
			state.detailEnvironments[actionEl.dataset.siteId] = actionEl.dataset.envId;
			render();
			const active = state.detailTabs.site;
			if (isSiteFeatureTab(active)) {
				const { id, env } = currentSiteContext();
				loadSiteFeature(active, id, env);
			}
		}
		if (action === 'env-filter') {
			state.envFilter = actionEl.dataset.filter;
			state.page = 1;
			state.pages = {};
			render();
		}
		if (action === 'toggle-popover') {
			state.popover = state.popover === actionEl.dataset.popover ? '' : actionEl.dataset.popover;
			render();
		}
		if (action === 'toggle-filter') {
			const type = actionEl.dataset.filterType;
			const index = Number(actionEl.dataset.filterIndex);
			const options = getFilterOptions(type).filter(item => !state.filterSearch[type] || normalizeText(item.search || item.title || item.name).includes(normalizeText(state.filterSearch[type]))).slice(0, 80);
			if (options[index]) toggleFilter(type, options[index]);
		}
		if (action === 'clear-filters') {
			state.selectedCore = [];
			state.selectedThemes = [];
			state.selectedPlugins = [];
			state.filteredSiteIds = null;
			state.filteredEnvIds = null;
			state.popover = '';
			state.page = 1;
			state.pages = {};
			render();
		}
		if (action === 'toggle-select') {
			event.stopPropagation();
			const { site, env } = findSiteAndEnv(actionEl.dataset.siteId, actionEl.dataset.envId);
			const key = envKey(site, env);
			if (state.selection.has(key)) state.selection.delete(key);
			else state.selection.add(key);
			render();
		}
		if (action === 'select-all') {
			const sites = visibleSites().slice((state.page - 1) * state.perPage, state.page * state.perPage);
			const allSelected = allVisibleSelected(sites);
			sites.forEach(site => visibleEnvironments(site).forEach(env => {
				const key = envKey(site, env);
				if (allSelected) state.selection.delete(key);
				else state.selection.add(key);
			}));
			render();
		}
		if (action === 'open-drawer') {
			if (event.target.closest('.row-action') || event.target.matches('input')) return;
			state.drawer = { siteId: actionEl.dataset.siteId, envId: actionEl.dataset.envId };
			render();
		}
		if (action === 'close-drawer') {
			state.drawer = null;
			render();
		}
			if (action === 'open-url') {
				event.stopPropagation();
				safeOpen(actionEl.dataset.url);
			}
			if (action === 'copy-value') {
				event.stopPropagation();
				copyValue(actionEl.dataset.value);
			}
			if (action === 'toggle-site-secret') {
				event.stopPropagation();
				const key = actionEl.dataset.secretKey || '';
				if (key) state.revealedSecrets[key] = !state.revealedSecrets[key];
				render();
			}
			if (action === 'magic-login') {
				event.stopPropagation();
				magicLogin(actionEl.dataset.siteId, actionEl.dataset.envId, actionEl.dataset.userId);
			}
			if (action === 'open-captures') {
				event.stopPropagation();
				openCaptures(actionEl.dataset.siteId, actionEl.dataset.envId);
			}
		if (action === 'open-terminal-current') {
			openTerminalForCurrentEnv();
		}
		if (action === 'terminal-open') {
			state.terminal.open = true;
			state.terminal.show = true;
			loadRecipes();
			renderTerminal();
		}
		if (action === 'terminal-minimize') {
			state.terminal.open = false;
			state.terminal.show = true;
			renderTerminal();
		}
		if (action === 'terminal-hide') {
			event.stopPropagation();
			state.terminal.open = false;
			state.terminal.show = false;
			renderTerminal();
		}
		if (action === 'terminal-close') {
			state.terminal.open = false;
			state.terminal.show = false;
			renderTerminal();
		}
		if (action === 'terminal-fullscreen') {
			state.terminal.fullscreen = !state.terminal.fullscreen;
			localStorage.setItem('captaincore-v2-terminal-fullscreen', state.terminal.fullscreen ? 'true' : 'false');
			render();
		}
		if (action === 'terminal-toggle-target') {
			const target = terminalTargets().find(item => String(item.environment_id) === String(actionEl.dataset.envId));
			if (target) toggleTerminalTarget(target);
			renderTerminal();
		}
		if (action === 'terminal-add-filtered') {
			selectFilteredTargetsForTerminal();
		}
		if (action === 'terminal-clear-targets') {
			state.terminal.selectedTargets = [];
			renderTerminal();
		}
		if (action === 'terminal-tool-tab') {
			state.terminal.toolTab = actionEl.dataset.tab || 'system';
			if (state.terminal.toolTab === 'cookbook') loadRecipes();
			renderTerminal();
		}
		if (action === 'terminal-system-tool') {
			runTerminalSystemTool(actionEl.dataset.tool);
		}
		if (action === 'scripts-system-tool') {
			openTerminalForCurrentEnv(false);
			runTerminalSystemTool(actionEl.dataset.tool);
		}
		if (action === 'terminal-preview-recipe' || action === 'scripts-preview-recipe') {
			const recipe = state.recipes.find(item => String(item.recipe_id) === String(actionEl.dataset.recipeId));
			if (recipe) {
				if (action === 'scripts-preview-recipe') openTerminalForCurrentEnv(false);
				else openTerminal([], false);
				state.terminal.command = recipe.content || '';
				renderTerminal();
				requestAnimationFrame(() => {
					const input = document.querySelector('[data-input="terminal-command"]');
					if (input) input.focus();
				});
			}
		}
		if (action === 'terminal-run') {
			executeTerminalCommand();
		}
		if (action === 'terminal-cancel-job') {
			cancelTerminalJob(actionEl.dataset.jobClientId);
		}
		if (action === 'terminal-copy-job') {
			copyJobStream(actionEl.dataset.jobClientId);
		}
		if (action === 'terminal-schedule-open') {
			if (!state.terminal.command.trim()) return;
			const tomorrow = new Date();
			tomorrow.setDate(tomorrow.getDate() + 1);
			state.terminal.schedule.date = tomorrow.toISOString().slice(0, 10);
			state.terminal.schedule.time = '05:00';
			state.terminal.schedule.error = '';
			state.modal = 'terminal-schedule';
			render();
		}
		if (action === 'terminal-save-open') {
			if (!state.terminal.command.trim()) return;
			state.terminal.saveRecipe.error = '';
			state.modal = 'terminal-save-recipe';
			render();
		}
		if (action === 'backup-download') {
			runBackupAction('backup_download', actionEl.dataset.backupId);
		}
		if (action === 'backup-restore') {
			runBackupAction('backup_restore', actionEl.dataset.backupId);
		}
		if (action === 'toggle-quicksave-files') {
			toggleQuicksaveFiles(actionEl.dataset.hash);
		}
		if (action === 'quicksave-diff') {
			openQuicksaveDiff(actionEl.dataset.hash, actionEl.dataset.file);
		}
		if (action === 'quicksave-rollback') {
			rollbackQuicksave(actionEl.dataset.hash);
		}
		if (action === 'prev-page' && state.page > 1) {
			state.page--;
			render();
		}
		if (action === 'next-page') {
			state.page++;
			render();
		}
		if (action === 'set-page') {
			const pageKey = actionEl.dataset.pageKey;
			const page = Number(actionEl.dataset.page);
			if (pageKey && Number.isFinite(page)) {
				state.pages[pageKey] = Math.max(1, page);
				render();
			}
		}
		if (action === 'refresh-sites') loadCollection('sites', '/wp-json/captaincore/v1/sites', true);
		if (action === 'refresh-site-detail') loadSiteDetail(routeId('sites'), true);
		if (action === 'refresh-site-feature') {
			const { id, env } = currentSiteContext();
			loadSiteFeature(actionEl.dataset.feature || state.detailTabs.site, id, env, true);
		}
		if (action === 'load-log-file') {
			const { id, env } = currentSiteContext();
			fetchSiteLogFile(id, env, actionEl.dataset.file);
		}
		if (action === 'load-selected-log-file') {
			const { id, env } = currentSiteContext();
			const bucket = siteFeature(id, env || {});
			fetchSiteLogFile(id, env, bucket.selectedLog);
		}
		if (action === 'stats-timeframe') {
			applyStatsTimeframe(actionEl.dataset.range || '12m');
		}
		if (action === 'stats-share') {
			shareStats(actionEl.dataset.sharing || 'none');
		}
		if (action === 'stats-share-save') {
			shareStats('private');
		}
		if (action === 'capture-config-toggle') {
			syncCaptureFormState();
			state.captures.showConfig = !state.captures.showConfig;
			render();
		}
		if (action === 'capture-add-page') {
			syncCaptureFormState();
			state.captures.pages.push({ page: '' });
			render();
		}
		if (action === 'capture-remove-page') {
			syncCaptureFormState();
			const index = Number(actionEl.dataset.index);
			if (Number.isFinite(index) && state.captures.pages.length > 1) state.captures.pages.splice(index, 1);
			render();
		}
		if (action === 'capture-check') {
			requestNewCapture();
		}
		if (action === 'refresh-domain-detail') loadDomainDetail(routeId('domains'), true);
		if (action === 'refresh-account-detail') loadAccountDetail(routeId('accounts'), true);
		if (action === 'refresh-current') {
			state.fetched = {};
			loadForRoute();
		}
		if (action === 'clear-selection') {
			state.selection.clear();
			render();
		}
		if (action === 'bulk-terminal') {
			const targets = selectedRows().map(({ site, env }) => normalizeTarget(site, env)).filter(Boolean);
			openTerminal(targets);
		}
		if (action === 'bulk-open') selectedRows().forEach(({ env }) => safeOpen(env.home_url));
		if (action === 'bulk-login') selectedRows().forEach(({ site, env }) => magicLogin(site.site_id, env.environment_id || env.environment));
		if (action === 'open-new-site') {
			state.modal = 'new-site';
			render();
		}
		if (action === 'close-modal') {
			state.modal = null;
			render();
		}
		if (action === 'refresh-recipes') {
			loadRecipes(true);
		}
	});

	document.addEventListener('input', function (event) {
		const input = event.target.closest('[data-input]');
		if (!input) return;
		const cursor = input.selectionStart;
		if (input.dataset.input === 'search') {
			state.search = input.value;
			state.page = 1;
			state.pages = {};
			render();
			requestAnimationFrame(() => {
				const next = document.getElementById('cc-v2-search');
				if (next) {
					next.focus();
					next.setSelectionRange(cursor, cursor);
				}
			});
		}
		if (input.dataset.input === 'global-search') {
			state.globalSearch.query = input.value;
			state.globalSearch.activeIndex = 0;
			render();
			requestAnimationFrame(() => {
				const next = document.getElementById('cc-global-search');
				if (next) {
					next.focus();
					next.setSelectionRange(cursor, cursor);
				}
			});
		}
		if (input.dataset.input === 'stats-filter') {
			state.siteStats[input.dataset.field] = input.value;
			state.siteStats.timeframe = '';
			const { id, env } = currentSiteContext();
			const bucket = siteFeature(id, env || {});
			bucket.statsLoaded = false;
		}
		if (input.dataset.input === 'stats-share-password') {
			state.siteStats.sharePassword = input.value;
		}
		if (input.dataset.input === 'site-user-search') {
			state.userSearch = input.value;
			state.pages = {};
			render();
			requestAnimationFrame(() => {
				const next = document.querySelector('[data-input="site-user-search"]');
				if (next) {
					next.focus();
					next.setSelectionRange(cursor, cursor);
				}
			});
		}
		if (input.dataset.input === 'site-log-search') {
			state.logSearch = input.value;
			render();
			requestAnimationFrame(() => {
				const next = document.querySelector('[data-input="site-log-search"]');
				if (next) {
					next.focus();
					next.setSelectionRange(cursor, cursor);
				}
			});
		}
		if (input.dataset.input === 'site-log-limit') {
			const { id, env } = currentSiteContext();
			const bucket = siteFeature(id, env || {});
			bucket.logLimit = String(Math.max(10, Math.min(10000, Number(input.value) || 1000)));
		}
		if (input.dataset.input === 'quicksave-search') {
			state.quicksaveSearch = input.value;
			state.pages = {};
			render();
			requestAnimationFrame(() => {
				const next = document.querySelector('[data-input="quicksave-search"]');
				if (next) {
					next.focus();
					next.setSelectionRange(cursor, cursor);
				}
			});
		}
		if (input.dataset.input === 'filter-search') {
			const type = input.dataset.filterType;
			state.filterSearch[type] = input.value;
			render();
			requestAnimationFrame(() => {
				const next = document.querySelector('[data-input="filter-search"][data-filter-type="' + type + '"]');
				if (next) {
					next.focus();
					next.setSelectionRange(cursor, cursor);
				}
			});
		}
		if (input.dataset.input === 'terminal-command') {
			state.terminal.command = input.value;
		}
		if (input.dataset.input === 'terminal-target-search') {
			state.terminal.targetSearch = input.value;
			renderTerminal();
			requestAnimationFrame(() => {
				const next = document.querySelector('[data-input="terminal-target-search"]');
				if (next) {
					next.focus();
					next.setSelectionRange(cursor, cursor);
				}
			});
		}
		if (input.dataset.input === 'terminal-tool-search') {
			state.terminal.toolSearch = input.value;
			renderTerminal();
			requestAnimationFrame(() => {
				const next = document.querySelector('[data-input="terminal-tool-search"]');
				if (next) {
					next.focus();
					next.setSelectionRange(cursor, cursor);
				}
			});
		}
	});

	document.addEventListener('submit', function (event) {
		const form = event.target;
		const action = form.dataset.action;
		if (!action) return;
		event.preventDefault();
		if (action === 'sign-in') signIn(form);
		if (action === 'create-site') createSite(form);
		if (action === 'schedule-terminal-script') scheduleTerminalScript(form);
		if (action === 'save-terminal-recipe') saveTerminalRecipe(form);
		if (action === 'save-capture-config') saveCaptureConfig(form);
	});

	document.addEventListener('change', function (event) {
		const input = event.target.closest('[data-input]');
		if (!input) return;
		if (input.dataset.input === 'stats-filter') {
			state.siteStats[input.dataset.field] = input.value;
			state.siteStats.timeframe = '';
			if (input.dataset.field === 'grouping') {
				adjustStatsRangeForGrouping(input.value);
			}
			refreshStatsForCurrent();
		}
		if (input.dataset.input === 'site-user-role') {
			state.userRoleFilter = input.value || 'all';
			state.pages = {};
			render();
		}
		if (input.dataset.input === 'site-log-file') {
			const { id, env } = currentSiteContext();
			fetchSiteLogFile(id, env, input.value);
		}
		if (input.dataset.input === 'site-log-limit') {
			const { id, env } = currentSiteContext();
			const bucket = siteFeature(id, env || {});
			bucket.logLimit = String(Math.max(10, Math.min(10000, Number(input.value) || 1000)));
			if (bucket.selectedLog) fetchSiteLogFile(id, env, bucket.selectedLog);
		}
		if (input.dataset.input === 'capture-select') {
			state.captures.selectedCaptureId = input.value;
			const capture = selectedCapture();
			const pages = normalizedCapturePages(capture);
			state.captures.selectedPageKey = pages.length ? capturePageKey(pages[0], 0) : '';
			render();
		}
		if (input.dataset.input === 'capture-page-select') {
			state.captures.selectedPageKey = input.value;
			render();
		}
	});

	document.addEventListener('keydown', function (event) {
		if ((event.key === 'Enter' || event.key === ' ') && event.target.classList?.contains('site-row')) {
			event.preventDefault();
			event.target.click();
			return;
		}
		if ((event.key === 'Enter' || event.key === ' ') && event.target.classList?.contains('stack-row') && event.target.classList.contains('clickable')) {
			event.preventDefault();
			event.target.click();
			return;
		}
		if (event.key === 'Escape') {
			state.drawer = null;
			state.modal = null;
			state.popover = '';
			render();
		}
		if (state.modal === 'global-search') {
			const count = Object.values(groupedGlobalSearchResults()).flat().length;
			if (event.key === 'ArrowDown' && count) {
				event.preventDefault();
				state.globalSearch.activeIndex = Math.min(count - 1, state.globalSearch.activeIndex + 1);
				render();
			}
			if (event.key === 'ArrowUp' && count) {
				event.preventDefault();
				state.globalSearch.activeIndex = Math.max(0, state.globalSearch.activeIndex - 1);
				render();
			}
			if (event.key === 'Enter') {
				event.preventDefault();
				openActiveGlobalSearchResult();
			}
		}
		if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
			event.preventDefault();
			openGlobalSearch();
		}
		if ((event.metaKey || event.ctrlKey) && event.key === 'Enter' && event.target.closest('[data-input="terminal-command"]')) {
			event.preventDefault();
			executeTerminalCommand();
		}
		if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'j') {
			event.preventDefault();
			state.terminal.open = !state.terminal.open;
			state.terminal.show = true;
			if (state.terminal.open) loadRecipes();
			renderTerminal();
		}
	});

	window.addEventListener('popstate', function () {
		state.route = routeFromLocation();
		loadForRoute();
		render();
	});

	loadForRoute();
	render();
})();
</script>
</body>
</html>
