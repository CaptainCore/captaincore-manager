<?php
if ( ! function_exists('is_plugin_active') ) {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

$plugin_url        = plugin_dir_url( __DIR__ );
$user              = ( new CaptainCore\User )->profile();
$configurations    = ( new CaptainCore\Configurations )->get();
$colors            = CaptainCore\Configurations::colors();
$footer            = captaincore_footer_content_extracted();
$socket            = captaincore_fetch_socket_address() . "/ws";
$site_filters      = ( new CaptainCore\Environments )->filters();
$site_filters_core = ( new CaptainCore\Environments )->filters_for_core();
$modules        = [
    'billing' => ! defined( 'CAPTAINCORE_CUSTOM_DOMAIN' ),
    'dns'     => defined( 'CONSTELLIX_API_KEY' ) && defined( 'CONSTELLIX_SECRET_KEY' ),
];
?><!DOCTYPE html>
<html lang="en" class="light">
<head>
  <title><?php echo esc_html( $configurations->name ); ?> - Account</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Manage your sites, billing, and account details.">
  <meta charset="utf-8">
  <?php captaincore_header_content_extracted(); ?>
  <link href="<?php echo esc_url( home_url() ); ?>/account/" rel="canonical">
  <link href="https://cdn.jsdelivr.net/npm/frappe-charts@1.6.1/dist/frappe-charts.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <style type="text/tailwindcss">
    @theme {
      --color-primary: <?php echo esc_attr( is_object($colors) ? $colors->primary : $colors['primary'] ); ?>;
      --color-accent: <?php echo esc_attr( is_object($colors) ? $colors->accent : $colors['accent'] ); ?>;
      --color-success: <?php echo esc_attr( is_object($colors) ? $colors->success : $colors['success'] ); ?>;
      --color-warning: <?php echo esc_attr( is_object($colors) ? $colors->warning : $colors['warning'] ); ?>;
      --color-error: <?php echo esc_attr( is_object($colors) ? $colors->error : $colors['error'] ); ?>;
      --color-info: <?php echo esc_attr( is_object($colors) ? $colors->info : $colors['info'] ); ?>;
    }
  </style>
  <style>
    [v-cloak] { display: none !important; }
    html { scroll-behavior: smooth; }
    body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }

    /* Color tokens from PHP */
    :root {
      --color-primary: <?php echo esc_attr( is_object($colors) ? $colors->primary : $colors['primary'] ); ?>;
      --color-accent: <?php echo esc_attr( is_object($colors) ? $colors->accent : $colors['accent'] ); ?>;
      --color-success: <?php echo esc_attr( is_object($colors) ? $colors->success : $colors['success'] ); ?>;
      --color-warning: <?php echo esc_attr( is_object($colors) ? $colors->warning : $colors['warning'] ); ?>;
      --color-error: <?php echo esc_attr( is_object($colors) ? $colors->error : $colors['error'] ); ?>;
      --color-info: <?php echo esc_attr( is_object($colors) ? $colors->info : $colors['info'] ); ?>;
    }

    /* Light theme defaults */
    html.light { --bg-page: #f8f9fb; --bg-surface: #ffffff; --bg-sidebar: #ffffff; --text-primary: #1a1a2e; --text-secondary: #6b7280; --border-color: #e5e7eb; --hover-bg: #f5f6f8; --active-bg: #eef0f4; }
    /* Dark theme — slate-blue tint */
    html.dark { --bg-page: #0d1117; --bg-surface: #161b22; --bg-sidebar: #0d1117; --text-primary: #e6edf3; --text-secondary: #8b949e; --border-color: #30363d; --hover-bg: #1c2128; --active-bg: #262c36; }

    body { background: var(--bg-page); color: var(--text-primary); }

    /* Sidebar */
    .sidebar { background: var(--bg-sidebar); border-right: 1px solid var(--border-color); }
    .sidebar a, .sidebar button { color: var(--text-secondary); }
    .sidebar .nav-item { padding: 9px 12px; transition: color 0.15s, background-color 0.15s; }
    .sidebar .nav-item:hover { background: var(--hover-bg); color: var(--text-primary); }
    .sidebar .nav-item.active { background: color-mix(in srgb, var(--color-primary) 8%, transparent); color: var(--color-primary); font-weight: 600; }
    html.dark .sidebar .nav-item.active { background: color-mix(in srgb, #3b82f6 12%, transparent); color: #93c5fd; }

    /* Surface cards */
    .surface { background: var(--bg-surface); border: 1px solid var(--border-color); }

    /* Data table */
    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th { text-align: left; padding: 12px 16px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); border-bottom: 2px solid var(--border-color); cursor: pointer; user-select: none; white-space: nowrap; }
    .data-table th:hover { color: var(--text-primary); }
    .data-table td { padding: 10px 16px; border-bottom: 1px solid var(--border-color); font-size: 0.875rem; }
    .data-table tbody tr { transition: background-color 0.15s; }
    .data-table tbody tr:hover { background: var(--hover-bg); }
    .data-table tbody tr.clickable { cursor: pointer; }

    /* Toast notification */
    .toast { position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); z-index: 9999; padding: 12px 24px; border-radius: 8px; font-size: 0.875rem; box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: opacity 0.3s, transform 0.3s; }
    .toast.success { background: var(--color-success); color: #fff; }
    .toast.error { background: var(--color-error); color: #fff; }
    .toast.info { background: var(--color-info); color: #fff; }

    /* Inputs */
    .input-field { width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-surface); color: var(--text-primary); font-size: 0.875rem; outline: none; transition: border-color 0.15s; }
    .input-field:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px rgba(44,62,80,0.1); }
    html.dark .input-field:focus { box-shadow: 0 0 0 3px rgba(130,177,255,0.2); }
    .search-wrapper { position: relative; display: inline-block; }
    .search-wrapper .search-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--text-secondary); }
    .search-wrapper .input-field { padding-left: 34px; }

    /* Buttons */
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 16px; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer; border: none; transition: background-color 0.15s, opacity 0.15s; }
    .btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .btn-primary { background: var(--color-primary); color: #fff; }
    .btn-primary:hover:not(:disabled) { opacity: 0.9; }
    .btn-ghost { background: transparent; color: var(--text-primary); }
    .btn-ghost:hover:not(:disabled) { background: var(--hover-bg); }

    /* Pagination */
    .pagination { display: flex; align-items: center; gap: 4px; }
    .pagination button { min-width: 32px; height: 32px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-surface); color: var(--text-primary); font-size: 0.8rem; cursor: pointer; }
    .pagination button:hover { background: var(--hover-bg); }
    .pagination button.active { background: var(--color-primary); color: #fff; border-color: var(--color-primary); }

    /* Scrollbar for sidebar */
    .sidebar::-webkit-scrollbar { width: 4px; }
    .sidebar::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 4px; }

    /* Mobile overlay */
    .sidebar-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 40; }

    /* Tab bar */
    .tab-bar { display: flex; gap: 0; border-bottom: 2px solid var(--border-color); margin-bottom: 16px; overflow-x: auto; }
    .tab-item { padding: 10px 16px; font-size: 0.8125rem; font-weight: 500; color: var(--text-secondary); cursor: pointer; border: none; background: none; border-bottom: 2px solid transparent; margin-bottom: -2px; white-space: nowrap; transition: color 0.15s, border-color 0.15s; }
    .tab-item:hover { color: var(--text-primary); }
    .tab-item.active { color: var(--color-primary); border-bottom-color: var(--color-primary); }
    html.dark .tab-item.active { color: #93c5fd; border-bottom-color: #93c5fd; }

    /* Badges */
    .badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; }
    .badge-success { background: color-mix(in srgb, var(--color-success) 15%, transparent); color: var(--color-success); }
    .badge-warning { background: color-mix(in srgb, var(--color-warning) 15%, transparent); color: var(--color-warning); }
    .badge-error { background: color-mix(in srgb, var(--color-error) 15%, transparent); color: var(--color-error); }
    .badge-info { background: color-mix(in srgb, var(--color-info) 15%, transparent); color: var(--color-info); }
    .badge-default { background: var(--hover-bg); color: var(--text-secondary); }

    /* Select & textarea */
    .select-field { width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-surface); color: var(--text-primary); font-size: 0.875rem; outline: none; cursor: pointer; }
    .select-field:focus { border-color: var(--color-primary); }
    .textarea-field { width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-surface); color: var(--text-primary); font-size: 0.875rem; outline: none; resize: vertical; min-height: 80px; font-family: inherit; }
    .textarea-field:focus { border-color: var(--color-primary); }

    /* Detail header */
    .detail-header { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-bottom: 1px solid var(--border-color); }
    .detail-header .back-btn { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 6px; border: none; background: none; color: var(--text-secondary); cursor: pointer; }
    .detail-header .back-btn:hover { background: var(--hover-bg); color: var(--text-primary); }

    /* Info grid */
    .info-grid { display: grid; grid-template-columns: 140px 1fr; gap: 6px 12px; font-size: 0.8125rem; }
    .info-label { color: var(--text-secondary); font-weight: 500; }
    .info-value { color: var(--text-primary); word-break: break-all; }

    /* Timeline */
    .timeline-item { display: flex; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--border-color); }
    .timeline-item:last-child { border-bottom: none; }

    /* Button variants */
    .btn-sm { padding: 4px 10px; font-size: 0.75rem; }
    .btn-outline { background: transparent; border: 1px solid var(--border-color); color: var(--text-primary); }
    .btn-outline:hover:not(:disabled) { background: var(--hover-bg); }
    .btn-danger { background: var(--color-error); color: #fff; }
    .btn-danger:hover:not(:disabled) { opacity: 0.9; }
    .btn-success { background: var(--color-success); color: #fff; }
    .btn-success:hover:not(:disabled) { opacity: 0.9; }

    /* Toggle switch */
    .toggle { position: relative; width: 40px; height: 22px; border-radius: 11px; background: var(--border-color); cursor: pointer; transition: background 0.2s; border: none; }
    .toggle.on { background: var(--color-success); }
    .toggle::after { content: ''; position: absolute; top: 2px; left: 2px; width: 18px; height: 18px; border-radius: 50%; background: #fff; transition: transform 0.2s; }
    .toggle.on::after { transform: translateX(18px); }

    /* Site rows (compact list) */
    .site-row { display: flex; align-items: center; gap: 12px; padding: 10px 16px; border-bottom: 1px solid var(--border-color); transition: background-color 0.12s; cursor: pointer; }
    .site-row:last-child { border-bottom: none; }
    .site-row:hover { background: var(--hover-bg); }
    .site-row-thumb { width: 36px; height: 36px; border-radius: 8px; object-fit: cover; background: var(--hover-bg); flex-shrink: 0; border: 1px solid var(--border-color); }
    .site-row-thumb-placeholder { width: 36px; height: 36px; border-radius: 8px; background: var(--hover-bg); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .status-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
    .status-dot.online { background: var(--color-success); box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-success) 20%, transparent); }
    .status-dot.offline { background: var(--color-error); box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-error) 20%, transparent); }
    .status-dot.unknown { background: var(--border-color); }
    .site-metric { display: inline-flex; align-items: center; gap: 5px; font-size: 0.75rem; color: var(--text-secondary); white-space: nowrap; }
    .site-metric-value { font-weight: 600; color: var(--text-primary); }
    .icon-btn { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 6px; border: none; background: transparent; color: var(--text-secondary); cursor: pointer; transition: background-color 0.12s, color 0.12s; }
    .icon-btn:hover { background: var(--hover-bg); color: var(--text-primary); }
    .view-toggle { display: inline-flex; border: 1px solid var(--border-color); border-radius: 6px; overflow: hidden; }
    .view-toggle button { padding: 6px 10px; background: transparent; border: none; color: var(--text-secondary); cursor: pointer; border-right: 1px solid var(--border-color); transition: background 0.15s, color 0.15s; }
    .view-toggle button:last-child { border-right: none; }
    .view-toggle button:hover { background: var(--hover-bg); }
    .view-toggle button.active { background: var(--color-primary); color: #fff; }
    html.dark .view-toggle button.active { background: #3b82f6; }

    /* Site cards (legacy card view) */
    .site-card { border: 1px solid var(--border-color); border-radius: 12px; background: var(--bg-surface); overflow: hidden; transition: border-color 0.15s; }
    .site-card:hover { border-color: color-mix(in srgb, var(--color-primary) 40%, var(--border-color)); }
    .site-card-header { display: flex; align-items: center; gap: 8px; padding: 8px 16px; border-bottom: 1px solid var(--border-color); background: var(--hover-bg); }
    .site-card-header a { color: var(--text-primary); text-decoration: none; font-weight: 600; font-size: 0.875rem; }
    .site-card-header a:hover { color: var(--color-primary); }
    .site-card-env { display: flex; flex-wrap: wrap; align-items: center; gap: 16px; padding: 12px 16px; }
    .site-card-env + .site-card-env { border-top: 1px solid var(--border-color); }
    .site-screenshot { width: 150px; height: 94px; border-radius: 8px; object-fit: cover; background: var(--hover-bg); flex-shrink: 0; border: 1px solid var(--border-color); }
    .site-screenshot-placeholder { width: 150px; height: 94px; border-radius: 8px; background: var(--hover-bg); display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid var(--border-color); }

    .grid-card { position: relative; border-radius: 12px; overflow: hidden; border: 1px solid var(--border-color); cursor: pointer; transition: border-color 0.15s; }
    .grid-card:hover { border-color: color-mix(in srgb, var(--color-primary) 40%, var(--border-color)); }
    .grid-card img { width: 100%; aspect-ratio: 16/10; object-fit: cover; display: block; }
    .grid-card-overlay { position: absolute; bottom: 0; left: 0; right: 0; padding: 8px 12px; background: linear-gradient(transparent, rgba(0,0,0,0.7)); color: #fff; }
    .grid-card-placeholder { width: 100%; aspect-ratio: 16/10; background: var(--hover-bg); display: flex; align-items: center; justify-content: center; }

    .fathom-chart { border-radius: 8px; padding: 12px; background: var(--hover-bg); }
    .fathom-chart .chart-container { font-family: inherit; }
    .fathom-chart .chart-label, .fathom-chart .chart-legend { fill: var(--text-secondary); }
    .fathom-chart .chart-container .axis line, .fathom-chart .chart-container .chart-label line { stroke: var(--border-color); }
    html.dark .fathom-chart text { fill: var(--text-secondary); }

    /* Frappe chart tooltip */
    .graph-svg-tip { background: var(--bg-surface) !important; border: 1px solid var(--border-color) !important; box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important; }
    .graph-svg-tip .title { color: var(--text-primary) !important; }
    .graph-svg-tip .data-point-list li strong { color: var(--text-primary) !important; }
    .graph-svg-tip .data-point-list li { color: var(--text-secondary) !important; }
    html.dark .graph-svg-tip { box-shadow: 0 4px 12px rgba(0,0,0,0.4) !important; }

    @media (max-width: 768px) {
      .site-row { flex-wrap: wrap; gap: 8px; }
      .site-row .site-metrics { width: 100%; padding-left: 56px; }
      .site-row .site-actions { width: 100%; padding-left: 56px; justify-content: flex-start; }
      .site-card-env { flex-direction: column; align-items: flex-start; }
      .site-screenshot, .site-screenshot-placeholder { width: 100%; height: auto; aspect-ratio: 16/10; }

      /* Tab bar: scrollable with touch momentum */
      .tab-bar { padding: 8px 12px; gap: 0; -webkit-overflow-scrolling: touch; }
      .tab-item { padding: 6px 12px; font-size: 0.75rem; min-height: 36px; flex-shrink: 0; }

      /* DNS table: stacked on mobile */
      .data-table th, .data-table td { padding: 8px 10px; font-size: 0.75rem; }
      .dns-edit-row { flex-direction: column !important; }
      .dns-edit-row > * { width: 100% !important; min-width: 0 !important; }

      /* Dialog cards: full-width on mobile */
      .dialog-card { max-width: 100%; border-radius: 8px; }
      .dialog-overlay { padding: 8px; }

      /* Terminal: taller on mobile, adjusted padding */
      .terminal-window { max-height: 75vh; }
      .terminal-header { padding: 6px 10px; flex-wrap: wrap; }
      .terminal-input-area { padding: 6px 10px; flex-wrap: wrap; }
      .terminal-output { padding: 8px 10px; font-size: 0.75rem; }

      /* Filter chips: wrap and scroll */
      .filter-chip { padding: 6px 12px; min-height: 32px; }

      /* Buttons: larger touch targets */
      .btn-sm { min-height: 36px; padding: 6px 14px; }
      .icon-btn { min-width: 36px; min-height: 36px; }
    }

    @media (max-width: 480px) {
      /* Extra small: stack more aggressively */
      .detail-header { flex-direction: column; align-items: flex-start !important; }
      .detail-header .flex-shrink-0 { width: 100%; justify-content: flex-start; }
      .terminal-window { max-height: 85vh; }
    }

    /* Terminal window */
    .terminal-window { position: fixed; bottom: 0; left: 0; right: 0; z-index: 60; background: #1e1e2e; color: #cdd6f4; display: flex; flex-direction: column; max-height: 60vh; border-top: 1px solid #45475a; transition: max-height 0.2s; }
    .terminal-window.terminal-fullscreen { max-height: 100vh; top: 0; }
    .terminal-header { display: flex; align-items: center; padding: 8px 16px; background: #181825; border-bottom: 1px solid #313244; flex-shrink: 0; gap: 8px; }
    .terminal-output { flex: 1; overflow-y: auto; padding: 12px 16px; font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, monospace; font-size: 0.8125rem; line-height: 1.6; }
    .terminal-output::-webkit-scrollbar { width: 6px; }
    .terminal-output::-webkit-scrollbar-thumb { background: #45475a; border-radius: 3px; }
    .terminal-input-area { display: flex; align-items: flex-start; gap: 8px; padding: 8px 16px; background: #181825; border-top: 1px solid #313244; flex-shrink: 0; }
    .window-dot { width: 12px; height: 12px; border-radius: 50%; cursor: pointer; transition: opacity 0.15s; }
    .window-dot:hover { opacity: 0.8; }
    .window-dot.dot-red { background: #f38ba8; }
    .window-dot.dot-yellow { background: #f9e2af; }
    .window-dot.dot-green { background: #a6e3a1; }

    /* Activity island */
    .activity-island { position: fixed; bottom: 16px; left: 50%; transform: translateX(-50%); z-index: 55; background: #1e1e2e; color: #cdd6f4; padding: 8px 20px; border-radius: 9999px; font-size: 0.8125rem; box-shadow: 0 4px 20px rgba(0,0,0,0.3); cursor: pointer; display: flex; align-items: center; gap: 8px; border: 1px solid #313244; transition: transform 0.15s, box-shadow 0.15s; }
    .activity-island:hover { transform: translateX(-50%) translateY(-2px); box-shadow: 0 6px 24px rgba(0,0,0,0.4); }

    /* Dialog overlay */
    .dialog-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 70; display: flex; align-items: center; justify-content: center; padding: 16px; }
    .dialog-card { background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 12px; width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
    .dialog-card-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid var(--border-color); }
    .dialog-card-body { padding: 20px; }
    .dialog-card-footer { display: flex; justify-content: flex-end; gap: 8px; padding: 12px 20px; border-top: 1px solid var(--border-color); }

    /* Filter chips */
    .filter-chip { display: inline-flex; align-items: center; gap: 4px; padding: 4px 12px; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; border: 1px solid var(--border-color); background: var(--bg-surface); color: var(--text-secondary); cursor: pointer; transition: all 0.15s; white-space: nowrap; }
    .filter-chip:hover { border-color: var(--color-primary); color: var(--text-primary); }
    .filter-chip.active { background: color-mix(in srgb, var(--color-primary) 12%, transparent); border-color: var(--color-primary); color: var(--color-primary); }

    /* Dropdown menu */
    .dropdown-menu { position: absolute; top: 100%; left: 0; z-index: 50; min-width: 220px; max-height: 320px; overflow-y: auto; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,0.12); padding: 4px; }
    html.dark .dropdown-menu { box-shadow: 0 8px 24px rgba(0,0,0,0.4); }
    .dropdown-menu-item { display: flex; align-items: center; gap: 8px; padding: 6px 10px; border-radius: 4px; font-size: 0.8125rem; cursor: pointer; color: var(--text-primary); }
    .dropdown-menu-item:hover { background: var(--hover-bg); }

    /* Diff viewer */
    .diff-view { font-family: 'SF Mono', 'Consolas', 'Liberation Mono', 'Menlo', monospace; font-size: 0.75rem; line-height: 1.5; padding: 0; overflow-x: auto; white-space: pre; }
    .diff-view > div { padding: 1px 16px; }
    .diff-removed { background: color-mix(in srgb, var(--color-error) 15%, transparent); }
    .diff-added { background: color-mix(in srgb, var(--color-success) 15%, transparent); }

    /* Terminal target button */
    .terminal-btn { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 500; border: 1px solid #45475a; background: transparent; color: #cdd6f4; cursor: pointer; transition: background 0.12s; }
    .terminal-btn:hover { background: #313244; }
    .terminal-btn.active { background: #313244; border-color: #89b4fa; color: #89b4fa; }
  </style>
</head>
<body>

<div id="app" v-cloak>
  <!-- App layout handled by router-view with layout components -->
  <router-view></router-view>

  <!-- Toast notification -->
  <transition name="fade">
    <div v-if="notify.show" :class="['toast', notify.type]">{{ notify.message }}</div>
  </transition>
</div>

<!-- CDN Scripts -->
<?php if ( substr( $_SERVER['SERVER_NAME'], -10) == '.localhost' ) { ?>
<script src="<?php echo $plugin_url; ?>public/js/vue.js"></script>
<?php } else { ?>
<script src="https://cdn.jsdelivr.net/npm/vue@3.5.20/dist/vue.global.js"></script>
<?php } ?>
<script src="https://cdn.jsdelivr.net/npm/vue-router@4.5.0/dist/vue-router.global.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios@1.7.9/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dayjs@1.11.13/dayjs.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dayjs@1.11.13/plugin/relativeTime.js"></script>
<script src="https://cdn.jsdelivr.net/npm/numeral@2.0.6/numeral.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/frappe-charts@1.6.1/dist/frappe-charts.min.umd.js"></script>
<script src="<?php echo $plugin_url; ?>public/js/core.js"></script>
<script src="<?php echo $plugin_url; ?>public/js/kjua.min.js"></script>

<script>
// ─── PHP Data Injection ──────────────────────────────────────────────────────
window.__CAPTAINCORE__ = {
  configurations: <?php echo json_encode( $configurations ); ?>,
  colors:         <?php echo json_encode( $colors ); ?>,
  modules:        <?php echo json_encode( $modules ); ?>,
  user: {
    id:               <?php echo (int) get_current_user_id(); ?>,
    email:            <?php echo json_encode( $user->email ); ?>,
    login:            <?php echo json_encode( $user->login ); ?>,
    registered:       <?php echo json_encode( $user->registered ); ?>,
    hash:             <?php echo json_encode( $user->hash ); ?>,
    display_name:     <?php echo json_encode( $user->display_name ); ?>,
    first_name:       <?php echo json_encode( $user->first_name ); ?>,
    last_name:        <?php echo json_encode( $user->last_name ); ?>,
    role:             <?php echo json_encode( $user->role ); ?>,
    tfa_enabled:      <?php echo (int) $user->tfa_enabled; ?>,
    email_subscriber: <?php echo $user->email_subscriber ? 'true' : 'false'; ?>,
  },
  footer:     <?php echo $footer; ?>,
  socket:     <?php echo json_encode( $socket ); ?>,
  home_link:  <?php echo json_encode( home_url() ); ?>,
  plugin_url: <?php echo json_encode( $plugin_url ); ?>,
  remote_upload_uri: <?php echo json_encode( get_option( 'options_remote_upload_uri' ) ); ?>,
  site_filters:      <?php echo json_encode( $site_filters ); ?>,
  site_filters_core: <?php echo json_encode( $site_filters_core ); ?>,
  wp_nonce:   typeof wpApiSettings !== 'undefined' ? wpApiSettings.nonce : '',
};

dayjs.extend(dayjs_plugin_relativeTime);

// ─── Shared Constants ────────────────────────────────────────────────────────
const { createApp, ref, computed, watch, reactive, onMounted, onUnmounted, nextTick, defineComponent, h } = Vue;
const { createRouter, createWebHistory, useRouter, useRoute } = VueRouter;

const CC = window.__CAPTAINCORE__;
const basePath = CC.configurations.path || '/account/';

// ─── SVG Icon Paths (Heroicons MIT) ──────────────────────────────────────────
const icons = {
  home:         'M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
  globe:        'M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5a17.92 17.92 0 01-8.716-2.247m0 0A9.015 9.015 0 003 12c0-1.605.42-3.113 1.157-4.418',
  users:        'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
  sites:        'M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0h.375a2.625 2.625 0 010 5.25H3.375a2.625 2.625 0 010-5.25H3.75',
  billing:      'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z',
  cookbook:      'M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5',
  health:       'M12 12.75c1.148 0 2.278.08 3.383.237 1.037.146 1.866.966 1.866 2.013 0 3.728-2.35 6.75-5.25 6.75S6.75 18.728 6.75 15c0-1.046.83-1.867 1.866-2.013A24.204 24.204 0 0112 12.75zm0 0c2.883 0 5.647.508 8.207 1.44a23.91 23.91 0 01-1.152 6.06M12 12.75c-2.883 0-5.647.508-8.208 1.44.125 2.104.52 4.136 1.153 6.06M12 12.75a2.25 2.25 0 002.248-2.354M12 12.75a2.25 2.25 0 01-2.248-2.354M12 8.25c.995 0 1.971-.08 2.922-.236.403-.066.74-.358.795-.762a3.778 3.778 0 00-.399-2.25M12 8.25c-.995 0-1.97-.08-2.922-.236a.946.946 0 01-.795-.762 3.734 3.734 0 01.4-2.25M12 8.25a2.25 2.25 0 00-2.248 2.146M12 8.25a2.25 2.25 0 012.248 2.146M8.683 5.006a3.75 3.75 0 116.634 0M8.683 5.006a3.748 3.748 0 01-.399 2.25M15.317 5.006a3.746 3.746 0 00.4 2.25m-1.469 4.89a.5.5 0 11-1 0 .5.5 0 011 0zm-4.5 0a.5.5 0 11-1 0 .5.5 0 011 0z',
  logs:         'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
  archive:      'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z',
  cog:          'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
  chart:        'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
  shield:       'M12 9v3.75m0-10.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.152c-3.196 0-6.1-1.249-8.25-3.286zm0 13.036h.008v.008H12v-.008z',
  handbook:     'M9 6.75V15m0-15v6.75m6.75 0V15m0 0v6.75m0-6.75h-6.75M12 3.75H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V4.875c0-.621-.504-1.125-1.125-1.125H12z',
  defaults:     'M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75',
  key:          'M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z',
  subscription: 'M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 00-3.7-3.7 48.678 48.678 0 00-7.324 0 4.006 4.006 0 00-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3l-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 003.7 3.7 48.656 48.656 0 007.324 0 4.006 4.006 0 003.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3l-3 3',
  usersGroup:   'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z',
  profile:      'M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z',
  logout:       'M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9',
  sun:          'M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z',
  moon:         'M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z',
  bell:         'M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0',
  menu:         'M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5',
  close:        'M6 18L18 6M6 6l12 12',
  search:       'M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z',
  chevronUp:    'M4.5 15.75l7.5-7.5 7.5 7.5',
  chevronDown:  'M19.5 8.25l-7.5 7.5-7.5-7.5',
  check:        'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
  switchUser:   'M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5',
  plus:         'M12 4.5v15m7.5-7.5h-15',
  trash:        'M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0',
  pencil:       'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10',
  arrowLeft:    'M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18',
  arrowRight:   'M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3',
  download:     'M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3',
  upload:       'M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5',
  copy:         'M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9.75a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184',
  externalLink: 'M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25',
  viewCards:    'M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12',
  viewTable:    'M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0112 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125M10.875 12h-7.5m8.625 0h7.5m-8.625 0c.621 0 1.125.504 1.125 1.125m-10.125 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m0-3.75h-7.5m0 0c-.621 0-1.125.504-1.125 1.125M12 13.125v-1.5m0 3.75v-2.25',
  viewGrid:     'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z',
  pin:          'M16.5 3v1.875M16.5 3C17.328 3 18 3.672 18 4.5M16.5 3H7.5M7.5 3v1.875M7.5 3C6.672 3 6 3.672 6 4.5m12 0v.75c0 .414-.336.75-.75.75h-1.5a.75.75 0 01-.75-.75V4.5m3 0c0 .828-.672 1.5-1.5 1.5h-9A1.5 1.5 0 016 4.5m0 0v.75c0 .414.336.75.75.75h1.5A.75.75 0 009 5.25V4.5M12 7.875v8.25M9 21l3-3 3 3',
  monitor:      'M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25A2.25 2.25 0 015.25 3h13.5A2.25 2.25 0 0121 5.25z',
  login:        'M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75',
  database:     'M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125',
  lock:         'M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z',
  lockOpen:     'M13.5 10.5V6.75a4.5 4.5 0 119 0v3.75M3.75 21.75h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H3.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z',
  mail:         'M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75',
  server:       'M21.75 17.25v-.228a4.5 4.5 0 00-.12-1.03l-2.268-9.64a3.375 3.375 0 00-3.285-2.602H7.923a3.375 3.375 0 00-3.285 2.602l-2.268 9.64a4.5 4.5 0 00-.12 1.03v.228m19.5 0a3 3 0 01-3 3H5.25a3 3 0 01-3-3m19.5 0a3 3 0 00-3-3H5.25a3 3 0 00-3 3m16.5 0h.008v.008h-.008v-.008zm-3 0h.008v.008h-.008v-.008z',
  terminal:     'M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z',
  play:         'M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 010 1.972l-11.54 6.347a1.125 1.125 0 01-1.667-.986V5.653z',
  stop:         'M5.25 7.5A2.25 2.25 0 017.5 5.25h9a2.25 2.25 0 012.25 2.25v9a2.25 2.25 0 01-2.25 2.25h-9a2.25 2.25 0 01-2.25-2.25v-9z',
  funnel:       'M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z',
  bolt:         'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z',
  refresh:      'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182M21.015 4.356v4.992',
  at:           'M16.5 12a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zm0 0c0 1.657 1.007 3 2.25 3S21 13.657 21 12a9 9 0 10-2.636 6.364M16.5 12V8.25',
  clock:        'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
  power:        'M5.636 5.636a9 9 0 1012.728 0M12 3v9',
  puzzle:       'M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 01-.657.643 48.422 48.422 0 01-4.163-.3c-.543-.06-.964-.527-.964-1.074V4.5c0-.621.504-1.125 1.125-1.125h16.5c.621 0 1.125.504 1.125 1.125v7.215c0 .547-.421 1.014-.964 1.074a48.67 48.67 0 01-4.163.3.64.64 0 01-.657-.643v0c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.035-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 01-.657.643 48.422 48.422 0 01-4.163-.3c-.543-.06-.964-.527-.964-1.074V14.25c0-.621.504-1.125 1.125-1.125h2.25',
  palette:      'M4.098 19.902a3.75 3.75 0 005.304 0l6.401-6.402M6.75 21A3.75 3.75 0 013 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 003.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008z',
  rocket:       'M15.59 14.37a48.475 48.475 0 00-6.05-6.05c.342-.441.713-.864 1.11-1.265a.75.75 0 01.57-.236h2.598a2.25 2.25 0 001.591-.659l1.591-1.591a.75.75 0 011.06 0l1.768 1.768a.75.75 0 010 1.06l-1.591 1.591a2.25 2.25 0 00-.659 1.591v2.598a.75.75 0 01-.236.57c-.401.397-.824.768-1.265 1.11zm-6.05-6.05c-.688.688-1.316 1.434-1.878 2.232a.75.75 0 01-.555.335 47.34 47.34 0 00-4.107.91.75.75 0 01-.812-.284l-1.414-1.414a.75.75 0 01.284-.812 47.34 47.34 0 00.91-4.107.75.75 0 01.335-.555c.798-.562 1.544-1.19 2.232-1.878M9.54 8.32a.75.75 0 010 1.06l-.553.553a.75.75 0 11-1.06-1.06l.553-.553a.75.75 0 011.06 0zm6.613 6.613a.75.75 0 010 1.06l-.553.553a.75.75 0 11-1.06-1.06l.553-.553a.75.75 0 011.06 0z',
  save:         'M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z',
  sync:         'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182M21.015 4.356v4.992',
  sparkle:      'M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z',
};

// ─── Composable: useAuth ─────────────────────────────────────────────────────
const wpNonce = ref(CC.wp_nonce);
const userRole = ref(CC.user.role || '');
const currentUser = reactive({
  id:               CC.user.id,
  email:            CC.user.email || '',
  login:            CC.user.login || '',
  registered:       CC.user.registered || '',
  hash:             CC.user.hash || '',
  display_name:     CC.user.display_name || '',
  first_name:       CC.user.first_name || '',
  last_name:        CC.user.last_name || '',
  tfa_enabled:      CC.user.tfa_enabled || 0,
  email_subscriber: CC.user.email_subscriber || false,
});

function useAuth() {
  const login = reactive({
    user_login: '', user_password: '', errors: '', info: '', loading: false,
    lost_password: false, message: '', tfa_code: '',
  });

  function signIn() {
    login.loading = true;
    if (!login.user_login || !login.user_password) {
      login.errors = 'Username and password are required.';
      login.loading = false;
      return;
    }
    axios.post('/wp-json/captaincore/v1/login/', { command: 'signIn', login })
      .then(response => {
        if (typeof response.data.errors === 'undefined' && typeof response.data.info === 'undefined') {
          window.location = window.location.origin + basePath;
          return;
        }
        login.errors = response.data.errors;
        login.info = response.data.info;
        login.loading = false;
      })
      .catch(() => { login.loading = false; });
  }

  function signOut() {
    axios.post('/wp-json/captaincore/v1/login/', { command: 'signOut' })
      .then(() => {
        window.location = window.location.origin + basePath + 'login';
      });
  }

  function resetPassword() {
    login.loading = true;
    if (!login.user_login) {
      login.errors = 'Username is required.';
      login.loading = false;
      return;
    }
    axios.post('/wp-json/captaincore/v1/login/', { command: 'reset', login })
      .then(() => {
        login.message = "A password reset email is on it's way.";
        login.loading = false;
      })
      .catch(() => { login.loading = false; });
  }

  return { wpNonce, userRole, currentUser, login, signIn, signOut, resetPassword };
}

// ─── Composable: useApi ──────────────────────────────────────────────────────
const api = axios.create({ headers: { 'X-WP-Nonce': CC.wp_nonce } });
let nonceRetrying = false;

api.interceptors.request.use(config => {
  config.headers['X-WP-Nonce'] = wpNonce.value;
  return config;
});

api.interceptors.response.use(
  response => { nonceRetrying = false; return response; },
  error => {
    // Network error (connection refused, timeout, offline) — auto-retry with backoff
    if (!error.response) {
      const config = error.config;
      config.__retryCount = config.__retryCount || 0;
      if (config.__retryCount < 3) {
        config.__retryCount++;
        const delay = config.__retryCount * 2000; // 2s, 4s, 6s
        return new Promise(resolve => setTimeout(resolve, delay)).then(() => axios(config));
      }
      return Promise.reject(error);
    }
    if (error.response.status === 403 && error.response.data && error.response.data.code === 'rest_cookie_invalid_nonce') {
      if (nonceRetrying) {
        nonceRetrying = false;
        const router = routerInstance;
        if (router) router.push('/login');
        return Promise.reject(error);
      }
      return axios.get('/').then(resp => {
        const match = resp.data.match(/var wpApiSettings.+"nonce":"(.+?)"/);
        if (match && match[1]) {
          nonceRetrying = true;
          wpNonce.value = match[1];
          error.config.headers['X-WP-Nonce'] = match[1];
          return axios(error.config);
        }
        return Promise.reject(error);
      }).catch(() => Promise.reject(error));
    }
    return Promise.reject(error);
  }
);

function useApi() {
  return { api };
}

// ─── Safe URL opener (prevents javascript: and data: protocol injection) ─────
function safeOpen(url) {
  if (typeof url !== 'string') return;
  const trimmed = url.trim();
  if (/^https?:\/\//i.test(trimmed)) window.open(trimmed, '_blank');
}

// ─── HTML sanitizer (strips scripts, event handlers, dangerous tags) ─────────
function sanitizeHtml(html) {
  if (!html) return '';
  const doc = new DOMParser().parseFromString(html, 'text/html');
  doc.querySelectorAll('script, iframe, object, embed, form, link[rel="import"]').forEach(el => el.remove());
  doc.querySelectorAll('*').forEach(el => {
    for (const attr of [...el.attributes]) {
      if (attr.name.startsWith('on') || (attr.name === 'href' && /^\s*javascript:/i.test(attr.value)) || attr.name === 'srcdoc') {
        el.removeAttribute(attr.name);
      }
    }
  });
  return doc.body.innerHTML;
}

// ─── Composable: useTheme ────────────────────────────────────────────────────
const theme = ref(localStorage.getItem('captaincore-theme') || 'light');

function applyTheme(t) {
  document.documentElement.classList.remove('light', 'dark');
  document.documentElement.classList.add(t);
}
applyTheme(theme.value);

function useTheme() {
  function toggleTheme() {
    theme.value = theme.value === 'light' ? 'dark' : 'light';
    applyTheme(theme.value);
    localStorage.setItem('captaincore-theme', theme.value);
  }
  return { theme, toggleTheme };
}

// ─── Composable: useNav ──────────────────────────────────────────────────────
const sidebarOpen = ref(false);

function useNav() {
  function toggleSidebar() { sidebarOpen.value = !sidebarOpen.value; }
  function closeSidebar()  { sidebarOpen.value = false; }
  return { sidebarOpen, toggleSidebar, closeSidebar };
}

// ─── Composable: useNotify ───────────────────────────────────────────────────
const notify = reactive({ show: false, message: '', type: 'info' });
let notifyTimer = null;

function useNotify() {
  function showNotify(message, type = 'info') {
    notify.message = message;
    notify.type = type;
    notify.show = true;
    clearTimeout(notifyTimer);
    notifyTimer = setTimeout(() => { notify.show = false; }, 3000);
  }
  return { notify, showNotify };
}

// ─── Composable: useSites ────────────────────────────────────────────────────
const sites = ref([]);
const sitesLoading = ref(false);
const siteSearch = ref('');
let sitesFetched = false;

function refreshSites() {
  sitesFetched = false;
  sites.value = [];
}

const sitesError = ref('');

function useSites() {
  function fetchSites() {
    if (sitesFetched && sites.value.length > 0) return;
    sitesLoading.value = true;
    sitesError.value = '';
    api.get('/wp-json/captaincore/v1/sites')
      .then(response => {
        sites.value = response.data;
        sitesFetched = true;
      })
      .catch(err => {
        console.error('fetchSites error:', err);
        sitesError.value = err.code === 'ERR_NETWORK' ? 'Network error — connection lost.' : 'Failed to load sites.';
      })
      .finally(() => { sitesLoading.value = false; });
  }

  function retrySites() {
    sitesFetched = false;
    sites.value = [];
    fetchSites();
  }

  const filteredSites = computed(() => {
    let result = sites.value;
    // Apply server-side filters if any are active
    if (appliedThemeFilters.value.length || appliedPluginFilters.value.length || appliedCoreFilters.value.length || backupModeFilter.value !== null) {
      result = result.filter(s => s.filtered !== false);
    }
    // Apply local search
    if (siteSearch.value) {
      const q = siteSearch.value.toLowerCase();
      result = result.filter(s => s.name && s.name.toLowerCase().includes(q));
    }
    return result;
  });

  return { sites, sitesLoading, sitesError, siteSearch, filteredSites, fetchSites, retrySites };
}

// ─── Composable: useFilters ─────────────────────────────────────────────────
const appliedThemeFilters = ref([]);
const appliedPluginFilters = ref([]);
const appliedCoreFilters = ref([]);
const filterLogic = ref('and');
const filterVersionLogic = ref('and');
const filterStatusLogic = ref('and');
const backupModeFilter = ref(null);
const filteredEnvironmentIds = ref([]);
const sitesFiltering = ref(false);

// Secondary filter data (versions/statuses for selected filters)
const filterVersions = ref({});   // { 'plugin-slug': [ { name, slug, type, count }, ... ] }
const filterStatuses = ref({});   // { 'plugin-slug': [ { name, slug, type, count }, ... ] }
const filterVersionsLoading = ref(false);
const filterStatusesLoading = ref(false);

function useFilters() {
  const combinedAppliedFilters = computed(() => [...appliedThemeFilters.value, ...appliedPluginFilters.value]);

  const isAnySiteFilterActive = computed(() => {
    return appliedThemeFilters.value.length > 0 ||
           appliedPluginFilters.value.length > 0 ||
           appliedCoreFilters.value.length > 0 ||
           backupModeFilter.value !== null;
  });

  const hasSecondaryFilters = computed(() => {
    return combinedAppliedFilters.value.some(f =>
      (f.selected_versions && f.selected_versions.length) ||
      (f.selected_statuses && f.selected_statuses.length)
    );
  });

  function fetchSecondaryFilters() {
    const names = combinedAppliedFilters.value.map(f => f.name).join(',');
    if (!names) { filterVersions.value = {}; filterStatuses.value = {}; return; }

    filterVersionsLoading.value = true;
    filterStatusesLoading.value = true;

    api.get('/wp-json/captaincore/v1/filters/' + encodeURIComponent(names) + '/versions/')
      .then(r => {
        const map = {};
        (r.data || []).forEach(item => { map[item.name] = item.versions || []; });
        filterVersions.value = map;
      })
      .catch(() => {})
      .finally(() => { filterVersionsLoading.value = false; });

    api.get('/wp-json/captaincore/v1/filters/' + encodeURIComponent(names) + '/statuses/')
      .then(r => {
        const map = {};
        (r.data || []).forEach(item => { map[item.name] = item.statuses || []; });
        filterStatuses.value = map;
      })
      .catch(() => {})
      .finally(() => { filterStatusesLoading.value = false; });
  }

  function filterSites() {
    if (!isAnySiteFilterActive.value) {
      sites.value.forEach(s => { s.filtered = true; });
      filteredEnvironmentIds.value = [];
      sitesFiltering.value = false;
      return;
    }

    sitesFiltering.value = true;

    // Collect all selected versions/statuses from filters
    const allVersions = combinedAppliedFilters.value.flatMap(f => f.selected_versions || []);
    const allStatuses = combinedAppliedFilters.value.flatMap(f => f.selected_statuses || []);

    const filters = {
      logic: filterLogic.value,
      version_logic: filterVersionLogic.value,
      status_logic: filterStatusLogic.value,
      themes: appliedThemeFilters.value.map(({ name, title, search, type }) => ({ name, title, search, type })),
      plugins: appliedPluginFilters.value.map(({ name, title, search, type }) => ({ name, title, search, type })),
      core: appliedCoreFilters.value.map(c => c.name),
      versions: allVersions,
      statuses: allStatuses,
      backup_mode: backupModeFilter.value,
    };

    api.post('/wp-json/captaincore/v1/filters/sites', filters)
      .then(response => {
        const results = response.data.results || [];
        filteredEnvironmentIds.value = results.map(r => r.environment_id);
        const matchingSiteIds = new Set(results.map(r => r.site_id));
        sites.value.forEach(s => {
          s.filtered = matchingSiteIds.has(s.site_id);
        });
      })
      .catch(() => {
        sites.value.forEach(s => { s.filtered = true; });
      })
      .finally(() => { sitesFiltering.value = false; });
  }

  function clearSiteFilters() {
    appliedThemeFilters.value = [];
    appliedPluginFilters.value = [];
    appliedCoreFilters.value = [];
    backupModeFilter.value = null;
    filteredEnvironmentIds.value = [];
    filterVersions.value = {};
    filterStatuses.value = {};
    sitesFiltering.value = false;
    sites.value.forEach(s => { s.filtered = true; });
  }

  function ensureSecondaryArrays(filter) {
    if (!filter.selected_versions) filter.selected_versions = [];
    if (!filter.selected_statuses) filter.selected_statuses = [];
  }

  function toggleThemeFilter(filter) {
    const idx = appliedThemeFilters.value.findIndex(f => f.name === filter.name);
    if (idx > -1) appliedThemeFilters.value.splice(idx, 1);
    else { ensureSecondaryArrays(filter); appliedThemeFilters.value.push(filter); }
    fetchSecondaryFilters();
    filterSites();
  }

  function togglePluginFilter(filter) {
    const idx = appliedPluginFilters.value.findIndex(f => f.name === filter.name);
    if (idx > -1) appliedPluginFilters.value.splice(idx, 1);
    else { ensureSecondaryArrays(filter); appliedPluginFilters.value.push(filter); }
    fetchSecondaryFilters();
    filterSites();
  }

  function toggleCoreFilter(filter) {
    const idx = appliedCoreFilters.value.findIndex(f => f.name === filter.name);
    if (idx > -1) appliedCoreFilters.value.splice(idx, 1);
    else appliedCoreFilters.value.push(filter);
    filterSites();
  }

  function setBackupMode(mode) {
    backupModeFilter.value = backupModeFilter.value === mode ? null : mode;
    filterSites();
  }

  function toggleFilterVersion(filter, version) {
    ensureSecondaryArrays(filter);
    const idx = filter.selected_versions.findIndex(v => v.name === version.name && v.slug === version.slug);
    if (idx > -1) filter.selected_versions.splice(idx, 1);
    else filter.selected_versions.push(version);
    filterSites();
  }

  function toggleFilterStatus(filter, status) {
    ensureSecondaryArrays(filter);
    const idx = filter.selected_statuses.findIndex(s => s.name === status.name && s.slug === status.slug);
    if (idx > -1) filter.selected_statuses.splice(idx, 1);
    else filter.selected_statuses.push(status);
    filterSites();
  }

  return {
    appliedThemeFilters, appliedPluginFilters, appliedCoreFilters,
    filterLogic, filterVersionLogic, filterStatusLogic,
    backupModeFilter, filteredEnvironmentIds, sitesFiltering,
    isAnySiteFilterActive, hasSecondaryFilters, combinedAppliedFilters,
    filterSites, clearSiteFilters,
    toggleThemeFilter, togglePluginFilter, toggleCoreFilter, setBackupMode,
    filterVersions, filterStatuses, filterVersionsLoading, filterStatusesLoading,
    toggleFilterVersion, toggleFilterStatus,
  };
}

// ─── Composable: useDomains ──────────────────────────────────────────────────
const domains = ref([]);
const domainsLoading = ref(false);
const domainSearch = ref('');
let domainsFetched = false;

function useDomains() {
  function fetchDomains() {
    if (domainsFetched && domains.value.length > 0) return;
    domainsLoading.value = true;
    api.get('/wp-json/captaincore/v1/domains')
      .then(response => {
        domains.value = response.data;
        domainsFetched = true;
      })
      .catch(err => console.error('fetchDomains error:', err))
      .finally(() => { domainsLoading.value = false; });
  }

  const filteredDomains = computed(() => {
    if (!domainSearch.value) return domains.value;
    const q = domainSearch.value.toLowerCase();
    return domains.value.filter(d => d.name && d.name.toLowerCase().includes(q));
  });

  return { domains, domainsLoading, domainSearch, filteredDomains, fetchDomains };
}

// ─── Composable: useAccounts ─────────────────────────────────────────────────
const accounts = ref([]);
const accountsLoading = ref(false);
const accountSearch = ref('');
let accountsFetched = false;

function useAccounts() {
  function fetchAccounts() {
    if (accountsFetched && accounts.value.length > 0) return;
    accountsLoading.value = true;
    api.get('/wp-json/captaincore/v1/accounts')
      .then(response => {
        accounts.value = response.data;
        accountsFetched = true;
      })
      .catch(err => console.error('fetchAccounts error:', err))
      .finally(() => { accountsLoading.value = false; });
  }

  const filteredAccounts = computed(() => {
    if (!accountSearch.value) return accounts.value;
    const q = accountSearch.value.toLowerCase();
    return accounts.value.filter(a => a.name && a.name.toLowerCase().includes(q));
  });

  return { accounts, accountsLoading, accountSearch, filteredAccounts, fetchAccounts };
}

// ─── Composable: useJobs (WebSocket + Command Execution) ────────────────────
const jobs = ref([]);
const socketUrl = CC.socket;

function useJobs() {
  const runningJobs = computed(() => jobs.value.filter(j => j.status === 'queued' || j.status === 'running').length);
  const hasJobs = computed(() => jobs.value.length > 0);

  function createJob({ description, command, siteId, environment, onFinish }) {
    const jobId = Math.round(Date.now());
    jobs.value.push({
      job_id: jobId,
      description: description || 'Running command',
      status: 'queued',
      stream: [],
      command: command || '',
      site_id: siteId || null,
      environment: environment || null,
      conn: null,
      created_at: new Date(),
      on_finish: onFinish || null,
    });
    return jobId;
  }

  function runCommand(jobId) {
    const job = jobs.value.find(j => j.job_id == jobId);
    if (!job) return;
    job.status = 'running';
    job.conn = new WebSocket(socketUrl);

    job.conn.onopen = () => {
      if (job.conn.readyState === WebSocket.OPEN) {
        job.conn.send(JSON.stringify({ token: String(job.job_id), action: 'start' }));
      }
    };

    job.conn.onmessage = (event) => {
      const msg = event.data;
      if (msg && msg.trim()) {
        job.stream.push(msg);
      }
    };

    job.conn.onclose = () => {
      const lastLine = job.stream.length ? job.stream[job.stream.length - 1] : '';
      if (lastLine && lastLine.includes('Finished.')) {
        job.status = 'done';
      } else if (job.status === 'running') {
        job.status = 'done';
      }
      job.conn = null;
      if (typeof job.on_finish === 'function') job.on_finish(job);
    };

    job.conn.onerror = () => {
      job.status = 'error';
      job.stream.push('WebSocket connection error.');
    };
  }

  function killCommand(jobId) {
    const job = jobs.value.find(j => j.job_id == jobId);
    if (!job || !job.conn) return;
    try {
      job.conn.send(JSON.stringify({ token: String(job.job_id), action: 'kill' }));
    } catch (e) { /* ignore */ }
    job.status = 'error';
    job.stream.push('Command killed.');
  }

  function clearJobs() {
    jobs.value = jobs.value.filter(j => j.status === 'running' || j.status === 'queued');
  }

  return { jobs, runningJobs, hasJobs, createJob, runCommand, killCommand, clearJobs };
}

// ─── Helper: Icon Component ──────────────────────────────────────────────────
const SvgIcon = defineComponent({
  props: {
    name: { type: String, required: true },
    size: { type: [String, Number], default: 20 },
  },
  setup(props) {
    return () => {
      const path = icons[props.name];
      if (!path) return h('span');
      return h('svg', {
        xmlns: 'http://www.w3.org/2000/svg',
        fill: 'none',
        viewBox: '0 0 24 24',
        'stroke-width': '1.5',
        stroke: 'currentColor',
        width: props.size,
        height: props.size,
        class: 'inline-block flex-shrink-0',
      }, [
        h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: path })
      ]);
    };
  },
});

// ─── Helper: pretty timestamps ───────────────────────────────────────────────
function prettyTimestamp(dateStr) {
  if (!dateStr) return '';
  return dayjs(dateStr).fromNow();
}
function prettyTimestampEpoch(epoch) {
  if (!epoch) return '';
  return dayjs.unix(epoch).fromNow();
}

function formatLargeNumbers(n) {
  if (n == null || n === '') return '—';
  return Number(n).toLocaleString();
}

function formatGBs(bytes) {
  if (!bytes) return '0';
  const n = Number(bytes);
  if (isNaN(n)) return '0';
  return (n / 1073741824).toFixed(2);
}

function getScreenshotUrl(site, env, size) {
  if (!env || !env.screenshot_base || !CC.remote_upload_uri) return '';
  const envName = (env.environment || 'production').toLowerCase();
  return `${CC.remote_upload_uri}${site.site}_${site.site_id}/${envName}/screenshots/${env.screenshot_base}_thumb-${size}.jpg`;
}

function getVisibleEnvironments(site) {
  if (!site || !site.environments) return [];
  const envs = Array.isArray(site.environments) ? [...site.environments] : [];
  envs.sort((a, b) => {
    if (a.environment === 'Production') return -1;
    if (b.environment === 'Production') return 1;
    return 0;
  });
  return envs;
}

// ─── Component: DataTable ────────────────────────────────────────────────────
const DataTable = defineComponent({
  props: {
    headers:      { type: Array, required: true },
    items:        { type: Array, required: true },
    search:       { type: String, default: '' },
    loading:      { type: Boolean, default: false },
    itemsPerPage: { type: Number, default: 100 },
    clickable:    { type: Boolean, default: false },
    itemsPerPageOptions: { type: Array, default: () => [50, 100, 250, -1] },
  },
  emits: ['click:row'],
  setup(props, { emit, slots }) {
    const sortKey = ref('');
    const sortAsc = ref(true);
    const currentPage = ref(1);
    const perPage = ref(props.itemsPerPage);

    const filtered = computed(() => {
      let data = [...props.items];
      if (props.search) {
        const q = props.search.toLowerCase();
        data = data.filter(item =>
          props.headers.some(h => {
            const val = getNestedValue(item, h.key || h.value);
            return val != null && String(val).toLowerCase().includes(q);
          })
        );
      }
      if (sortKey.value) {
        data.sort((a, b) => {
          const aVal = getNestedValue(a, sortKey.value);
          const bVal = getNestedValue(b, sortKey.value);
          if (aVal == null && bVal == null) return 0;
          if (aVal == null) return 1;
          if (bVal == null) return -1;
          if (typeof aVal === 'number' && typeof bVal === 'number') return sortAsc.value ? aVal - bVal : bVal - aVal;
          return sortAsc.value ? String(aVal).localeCompare(String(bVal)) : String(bVal).localeCompare(String(aVal));
        });
      }
      return data;
    });

    const totalPages = computed(() => {
      if (perPage.value === -1) return 1;
      return Math.max(1, Math.ceil(filtered.value.length / perPage.value));
    });

    const paginated = computed(() => {
      if (perPage.value === -1) return filtered.value;
      const start = (currentPage.value - 1) * perPage.value;
      return filtered.value.slice(start, start + perPage.value);
    });

    watch(() => props.search, () => { currentPage.value = 1; });
    watch(() => props.items, () => { currentPage.value = 1; });

    function toggleSort(key) {
      if (sortKey.value === key) {
        sortAsc.value = !sortAsc.value;
      } else {
        sortKey.value = key;
        sortAsc.value = true;
      }
    }

    function getNestedValue(obj, path) {
      if (!path) return undefined;
      return path.split('.').reduce((acc, key) => acc && acc[key], obj);
    }

    function onRowClick(item, event) {
      emit('click:row', event, { item });
    }

    return () => {
      // Loading state
      if (props.loading) {
        return h('div', { class: 'flex justify-center items-center py-16' }, [
          h('div', { class: 'animate-spin rounded-full h-8 w-8 border-b-2', style: 'border-color: var(--color-primary)' })
        ]);
      }

      // Empty state
      if (paginated.value.length === 0) {
        return h('div', { class: 'text-center py-12', style: 'color: var(--text-secondary)' }, 'No results found.');
      }

      const header = h('thead', {}, [
        h('tr', {}, props.headers.map(col =>
          h('th', {
            style: col.width ? `width:${col.width}` : '',
            onClick: () => col.sortable !== false && toggleSort(col.key || col.value),
          }, [
            h('span', { class: 'inline-flex items-center gap-1' }, [
              col.title,
              sortKey.value === (col.key || col.value) ? h(SvgIcon, { name: sortAsc.value ? 'chevronUp' : 'chevronDown', size: 14 }) : null,
            ])
          ])
        ))
      ]);

      const body = h('tbody', {}, paginated.value.map(item =>
        h('tr', {
          class: props.clickable ? 'clickable' : '',
          onClick: (e) => props.clickable && onRowClick(item, e),
        }, props.headers.map(col => {
          const key = col.key || col.value;
          const slotName = 'item.' + key;
          if (slots[slotName]) {
            return h('td', {}, slots[slotName]({ item, value: getNestedValue(item, key) }));
          }
          const val = getNestedValue(item, key);
          return h('td', {}, val != null ? String(val) : '');
        }))
      ));

      const table = h('table', { class: 'data-table' }, [header, body]);

      // Pagination footer
      let paginationEl = null;
      if (totalPages.value > 1) {
        const pageButtons = [];
        const tp = totalPages.value;
        const cp = currentPage.value;

        // Build page number list with ellipsis
        const pageNums = [];
        if (tp <= 7) {
          for (let i = 1; i <= tp; i++) pageNums.push(i);
        } else {
          pageNums.push(1);
          if (cp > 3) pageNums.push('...');
          for (let i = Math.max(2, cp - 1); i <= Math.min(tp - 1, cp + 1); i++) pageNums.push(i);
          if (cp < tp - 2) pageNums.push('...');
          pageNums.push(tp);
        }

        pageNums.forEach(p => {
          if (p === '...') {
            pageButtons.push(h('span', { class: 'px-1', style: 'color: var(--text-secondary)' }, '...'));
          } else {
            pageButtons.push(h('button', {
              class: p === cp ? 'active' : '',
              onClick: () => { currentPage.value = p; },
            }, String(p)));
          }
        });

        const perPageSelect = h('select', {
          class: 'input-field',
          style: 'width: auto; padding: 4px 8px; font-size: 0.8rem;',
          value: perPage.value,
          onChange: (e) => {
            perPage.value = parseInt(e.target.value);
            currentPage.value = 1;
          }
        }, props.itemsPerPageOptions.map(opt => {
          const val = typeof opt === 'object' ? opt.value : opt;
          const label = typeof opt === 'object' ? opt.title : (opt === -1 ? 'All' : String(opt));
          return h('option', { value: val }, label);
        }));

        paginationEl = h('div', { class: 'flex items-center justify-between px-4 py-3', style: 'border-top: 1px solid var(--border-color)' }, [
          h('div', { class: 'flex items-center gap-2', style: 'font-size: 0.8rem; color: var(--text-secondary)' }, [
            'Rows per page:',
            perPageSelect,
          ]),
          h('div', { class: 'flex items-center gap-2', style: 'font-size: 0.8rem; color: var(--text-secondary)' }, [
            `${(cp - 1) * perPage.value + 1}-${Math.min(cp * perPage.value, filtered.value.length)} of ${filtered.value.length}`,
          ]),
          h('div', { class: 'pagination' }, pageButtons),
        ]);
      }

      return h('div', {}, [table, paginationEl]);
    };
  },
});

// ─── Component: SidebarNav ───────────────────────────────────────────────────
const SidebarNav = defineComponent({
  setup() {
    const { theme, toggleTheme } = useTheme();
    const { sidebarOpen, closeSidebar } = useNav();
    const router = useRouter();
    const route = useRoute();

    const role = userRole;
    const configs = CC.configurations;
    const modules = CC.modules;
    const footer = CC.footer;

    const gravatar = computed(() => {
      return 'https://www.gravatar.com/avatar/' + md5(currentUser.email.trim().toLowerCase()) + '?s=80&d=mp';
    });

    function navTo(path) {
      router.push(path);
      closeSidebar();
    }

    function isActive(path) {
      return route.path === path || route.path.startsWith(path + '/');
    }

    function doSignOut() {
      const { signOut } = useAuth();
      signOut();
    }

    return { theme, toggleTheme, sidebarOpen, closeSidebar, router, route, role, configs, modules, footer, gravatar, navTo, isActive, doSignOut, currentUser };
  },
  template: `
    <!-- Mobile overlay -->
    <div v-if="sidebarOpen" class="sidebar-overlay lg:hidden" @click="closeSidebar"></div>

    <aside
      :class="[
        'sidebar fixed top-0 left-0 h-full z-50 flex flex-col transition-transform duration-200',
        'w-64',
        sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
      ]"
    >
      <!-- Brand -->
      <div class="flex items-center gap-3 px-4 h-14 flex-shrink-0" style="border-bottom: 1px solid var(--border-color)">
        <router-link to="/" class="flex items-center gap-2 no-underline" @click="closeSidebar" style="color: var(--text-primary); text-decoration: none;">
          <img v-if="configs.logo" :src="configs.logo" :style="{ maxWidth: (configs.logo_width || '32') + 'px' }" class="block" />
          <span v-show="configs.logo_only != true" class="font-semibold text-sm">{{ configs.name }}</span>
        </router-link>
      </div>

      <!-- Nav -->
      <nav class="flex-1 overflow-y-auto py-4 px-3" style="scrollbar-gutter: stable;">
        <a @click.prevent="navTo('/sites')" :href="configs.path + 'sites'" :class="['nav-item flex items-center gap-3 rounded-lg text-[13px] no-underline', isActive('/sites') && 'active']">
          <svg-icon name="sites" :size="18" /> Sites
        </a>
        <a @click.prevent="navTo('/domains')" :href="configs.path + 'domains'" :class="['nav-item flex items-center gap-3 rounded-lg text-[13px] no-underline', isActive('/domains') && 'active']">
          <svg-icon name="globe" :size="18" /> Domains
        </a>
        <a @click.prevent="navTo('/accounts')" :href="configs.path + 'accounts'" :class="['nav-item flex items-center gap-3 rounded-lg text-[13px] no-underline', isActive('/accounts') && 'active']">
          <svg-icon name="users" :size="18" /> Accounts
        </a>
        <a v-if="modules.billing" @click.prevent="navTo('/billing')" :href="configs.path + 'billing'" :class="['nav-item flex items-center gap-3 rounded-lg text-[13px] no-underline', isActive('/billing') && 'active']">
          <svg-icon name="billing" :size="18" /> Billing
        </a>

        <div class="my-3" style="border-top: 1px solid var(--border-color)"></div>

        <a @click.prevent="navTo('/cookbook')" :href="configs.path + 'cookbook'" :class="['nav-item flex items-center gap-3 rounded-lg text-[13px] no-underline', isActive('/cookbook') && 'active']">
          <svg-icon name="cookbook" :size="18" /> Cookbook
        </a>
        <a @click.prevent="navTo('/health')" :href="configs.path + 'health'" :class="['nav-item flex items-center gap-3 rounded-lg text-[13px] no-underline', isActive('/health') && 'active']">
          <svg-icon name="health" :size="18" /> Health
        </a>

        <template v-if="role === 'administrator' || role === 'owner'">
          <div class="my-3" style="border-top: 1px solid var(--border-color)"></div>

          <a v-if="role === 'administrator'" @click.prevent="navTo('/activity-logs')" :href="configs.path + 'activity-logs'" :class="['nav-item flex items-center gap-3 rounded-lg text-[13px] no-underline', isActive('/activity-logs') && 'active']">
            <svg-icon name="logs" :size="18" /> Activity Logs
          </a>
          <a @click.prevent="navTo('/archives')" :href="configs.path + 'archives'" :class="['nav-item flex items-center gap-3 rounded-lg text-[13px] no-underline', isActive('/archives') && 'active']">
            <svg-icon name="archive" :size="18" /> Archives
          </a>
          <a @click.prevent="navTo('/configurations')" :href="configs.path + 'configurations'" :class="['nav-item flex items-center gap-3 rounded-lg text-[13px] no-underline', isActive('/configurations') && 'active']">
            <svg-icon name="cog" :size="18" /> Configurations
          </a>
          <a v-if="role === 'administrator'" @click.prevent="navTo('/reports')" :href="configs.path + 'reports'" :class="['nav-item flex items-center gap-3 rounded-lg text-[13px] no-underline', isActive('/reports') && 'active']">
            <svg-icon name="chart" :size="18" /> Reports
          </a>
          <a v-if="role === 'administrator'" @click.prevent="navTo('/web-risk')" :href="configs.path + 'web-risk'" :class="['nav-item flex items-center gap-3 rounded-lg text-[13px] no-underline', isActive('/web-risk') && 'active']">
            <svg-icon name="shield" :size="18" /> Web Risk
          </a>
          <a @click.prevent="navTo('/handbook')" :href="configs.path + 'handbook'" :class="['nav-item flex items-center gap-3 rounded-lg text-[13px] no-underline', isActive('/handbook') && 'active']">
            <svg-icon name="handbook" :size="18" /> Handbook
          </a>
          <a @click.prevent="navTo('/defaults')" :href="configs.path + 'defaults'" :class="['nav-item flex items-center gap-3 rounded-lg text-[13px] no-underline', isActive('/defaults') && 'active']">
            <svg-icon name="defaults" :size="18" /> Site Defaults
          </a>
          <a @click.prevent="navTo('/keys')" :href="configs.path + 'keys'" :class="['nav-item flex items-center gap-3 rounded-lg text-[13px] no-underline', isActive('/keys') && 'active']">
            <svg-icon name="key" :size="18" /> SSH Keys
          </a>
          <a v-if="role === 'administrator' && configs.mode === 'hosting'" @click.prevent="navTo('/subscriptions')" :href="configs.path + 'subscriptions'" :class="['nav-item flex items-center gap-3 rounded-lg text-[13px] no-underline', isActive('/subscriptions') && 'active']">
            <svg-icon name="subscription" :size="18" /> Subscriptions
          </a>
          <a @click.prevent="navTo('/users')" :href="configs.path + 'users'" :class="['nav-item flex items-center gap-3 rounded-lg text-[13px] no-underline', isActive('/users') && 'active']">
            <svg-icon name="usersGroup" :size="18" /> Users
          </a>
        </template>
      </nav>

      <!-- User footer -->
      <div class="flex-shrink-0 px-2 py-3" style="border-top: 1px solid var(--border-color)">
        <a @click.prevent="navTo('/profile')" :href="configs.path + 'profile'" :class="['nav-item flex items-center gap-3 rounded-lg text-sm no-underline', isActive('/profile') && 'active']">
          <img :src="gravatar" class="w-7 h-7 rounded-md object-cover" />
          <span class="truncate">{{ currentUser.display_name }}</span>
        </a>
        <a v-if="footer.switch_to_link" :href="footer.switch_to_link" class="nav-item flex items-center gap-3 rounded-lg text-sm no-underline">
          <svg-icon name="switchUser" :size="18" /> {{ footer.switch_to_text }}
        </a>
        <a @click.prevent="doSignOut" href="#" class="nav-item flex items-center gap-3 rounded-lg text-sm no-underline">
          <svg-icon name="logout" :size="18" /> Log Out
        </a>
      </div>
    </aside>
  `,
});

// ─── Component: TopBar ───────────────────────────────────────────────────────
const TopBar = defineComponent({
  setup() {
    const { theme, toggleTheme } = useTheme();
    const { toggleSidebar } = useNav();
    const { runningJobs } = useJobs();

    function openTerminal() { terminalState.open = !terminalState.open; }

    return { theme, toggleTheme, toggleSidebar, openTerminal, runningJobs };
  },
  template: `
    <header class="sticky top-0 z-30 flex items-center h-14 px-4 gap-3" style="background: var(--bg-surface); border-bottom: 1px solid var(--border-color)">
      <button @click="toggleSidebar" class="btn-ghost p-2 rounded-lg lg:hidden">
        <svg-icon name="menu" :size="20" />
      </button>
      <div class="flex-1"></div>
      <button @click="openTerminal" class="btn-ghost p-2 rounded-lg relative" title="Terminal (Ctrl+K)">
        <svg-icon name="terminal" :size="18" />
        <span v-if="runningJobs > 0" class="absolute top-1 right-1 w-2 h-2 rounded-full animate-pulse" style="background: var(--color-success);"></span>
      </button>
      <button class="btn-ghost p-2 rounded-lg relative" title="Notifications">
        <svg-icon name="bell" :size="18" />
      </button>
    </header>
  `,
});

// ─── Component: TerminalWindow ───────────────────────────────────────────────
const terminalState = reactive({
  open: false,
  fullscreen: false,
  selectedTargets: [],
  targetSearch: '',
  targetMenuOpen: false,
  recipeMenuOpen: false,
  recipeMenuSearch: '',
  recipeMenuTab: 'system',
  code: '',
});

const systemTools = [
  { title: 'Apply HTTPS Urls', icon: 'rocket', method: 'applyHttpsUrls' },
  { title: 'Deploy Defaults', icon: 'refresh', method: 'deployDefaults' },
  { title: 'Toggle Site Status', icon: 'power', method: 'toggleSiteStatus' },
  { title: 'Launch Site', icon: 'globe', method: 'launchSite' },
  { title: 'Open in Browser', icon: 'externalLink', method: 'openInBrowser' },
];

function toggleConsoleTarget(env) {
  const idx = terminalState.selectedTargets.findIndex(t => t.environment_id === env.environment_id);
  if (idx > -1) {
    terminalState.selectedTargets.splice(idx, 1);
  } else {
    terminalState.selectedTargets.push({
      environment_id: env.environment_id,
      site_id: env.site_id,
      environment: env.environment,
      home_url: env.home_url,
      name: env.name || env.home_url,
    });
  }
}

function isConsoleTarget(envId) {
  return terminalState.selectedTargets.some(t => t.environment_id === envId);
}

const TerminalWindow = defineComponent({
  setup() {
    const terminalOutputRef = ref(null);
    const terminalInputRef = ref(null);
    const { showNotify } = useNotify();
    const { jobs: jobsList, createJob, runCommand, killCommand, clearJobs, runningJobs } = useJobs();
    const cookbookRecipes = ref([]);
    const cookbookLoaded = ref(false);

    // System tool dialogs
    const showHttpsDialog = ref(false);
    const showToggleStatusDialog = ref(false);
    const toggleStatusData = reactive({ business_name: '', business_link: '', heading: '', status_message: '', action_text: '' });
    const showLaunchDialog = ref(false);
    const launchDomain = ref('');

    // Save as Recipe
    const showSaveRecipeDialog = ref(false);
    const saveRecipeTitle = ref('');
    const saveRecipePublic = ref(true);

    // Schedule Script
    const showScheduleDialog = ref(false);
    const scheduleDate = ref('');
    const scheduleTime = ref('05:00');

    function scrollToBottom() {
      nextTick(() => {
        if (terminalOutputRef.value) {
          terminalOutputRef.value.scrollTop = terminalOutputRef.value.scrollHeight;
        }
      });
    }

    function executeCommand() {
      if (!terminalState.code || !terminalState.code.trim()) return;
      const targets = terminalState.selectedTargets;
      if (!targets.length) {
        terminalState.targetMenuOpen = true;
        showNotify('Select at least one target environment.', 'error');
        return;
      }

      const description = targets.length === 1
        ? `Running on ${targets[0].home_url || targets[0].name}`
        : `Running on ${targets.length} environments`;
      const cmd = terminalState.code;
      terminalState.code = '';

      const tempId = createJob({ description, command: cmd });

      api.post('/wp-json/captaincore/v1/run/code', {
        environments: targets.map(t => t.environment_id),
        code: cmd,
      }).then(response => {
        const job = jobsList.value.find(j => j.job_id === tempId);
        if (job && response.data) {
          job.job_id = response.data;
          runCommand(response.data);
        }
        scrollToBottom();
      }).catch(error => {
        const job = jobsList.value.find(j => j.job_id === tempId);
        if (job) {
          job.status = 'error';
          job.stream.push('Error: ' + (error.response?.data?.message || error.message));
        }
      });

      nextTick(() => { if (terminalInputRef.value) terminalInputRef.value.focus(); });
    }

    function handleKeydown(e) {
      if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
        executeCommand();
      }
    }

    function copyStream(job) {
      const text = job.stream.join('\n');
      if (navigator.clipboard) navigator.clipboard.writeText(text);
      showNotify('Copied to clipboard', 'success');
    }

    function loadCookbook() {
      if (cookbookLoaded.value) return;
      api.get('/wp-json/captaincore/v1/recipes')
        .then(r => { cookbookRecipes.value = r.data || []; cookbookLoaded.value = true; })
        .catch(() => {});
    }

    function useRecipe(recipe) {
      terminalState.code = recipe.content || '';
      terminalState.recipeMenuOpen = false;
    }

    // ── System Tools ──────────────────────────────────────────────────────────
    function runSystemTool(method) {
      const targets = terminalState.selectedTargets;
      if (!targets.length) {
        terminalState.targetMenuOpen = true;
        showNotify('Select at least one target environment.', 'error');
        return;
      }
      terminalState.recipeMenuOpen = false;
      if (method === 'applyHttpsUrls') { showHttpsDialog.value = true; return; }
      if (method === 'toggleSiteStatus') { showToggleStatusDialog.value = true; return; }
      if (method === 'launchSite') { showLaunchDialog.value = true; return; }
      if (method === 'deployDefaults') { deployDefaults(); return; }
      if (method === 'openInBrowser') { openInBrowser(); return; }
    }

    function applyHttpsUrls(useWww) {
      showHttpsDialog.value = false;
      const targets = terminalState.selectedTargets;
      const description = `Applying HTTPS ${useWww ? '(www)' : '(non-www)'} on ${targets.length} environment${targets.length > 1 ? 's' : ''}`;
      const tempId = createJob({ description });
      api.post('/wp-json/captaincore/v1/sites/bulk-tools', {
        tool: useWww ? 'apply-https-with-www' : 'apply-https',
        environments: targets.map(t => t.environment_id),
      }).then(r => {
        const job = jobsList.value.find(j => j.job_id === tempId);
        if (job && r.data) { job.job_id = r.data; runCommand(r.data); }
        scrollToBottom();
      }).catch(err => {
        const job = jobsList.value.find(j => j.job_id === tempId);
        if (job) { job.status = 'error'; job.stream.push('Error: ' + (err.response?.data?.message || err.message)); }
      });
    }

    function deployDefaults() {
      const targets = terminalState.selectedTargets;
      if (!confirm(`Deploy defaults on ${targets.length} environment${targets.length > 1 ? 's' : ''}?`)) return;
      const description = `Deploying defaults on ${targets.length} environment${targets.length > 1 ? 's' : ''}`;
      const tempId = createJob({ description });
      api.post('/wp-json/captaincore/v1/sites/cli', {
        command: 'deploy-defaults',
        environment: targets[0].environment,
        post_id: targets.map(t => t.site_id),
      }).then(r => {
        const job = jobsList.value.find(j => j.job_id === tempId);
        if (job && r.data) { job.job_id = r.data; runCommand(r.data); }
        scrollToBottom();
      }).catch(err => {
        const job = jobsList.value.find(j => j.job_id === tempId);
        if (job) { job.status = 'error'; job.stream.push('Error: ' + (err.response?.data?.message || err.message)); }
      });
    }

    function activateSites() {
      showToggleStatusDialog.value = false;
      const targets = terminalState.selectedTargets;
      const description = `Activating ${targets.length} site${targets.length > 1 ? 's' : ''}`;
      const tempId = createJob({ description });
      api.post('/wp-json/captaincore/v1/sites/cli', {
        command: 'activate',
        environment: targets[0].environment,
        post_id: targets.map(t => t.site_id),
      }).then(r => {
        const job = jobsList.value.find(j => j.job_id === tempId);
        if (job && r.data) { job.job_id = r.data; runCommand(r.data); }
        scrollToBottom();
      }).catch(err => {
        const job = jobsList.value.find(j => j.job_id === tempId);
        if (job) { job.status = 'error'; job.stream.push('Error: ' + (err.response?.data?.message || err.message)); }
      });
    }

    function deactivateSites() {
      showToggleStatusDialog.value = false;
      const targets = terminalState.selectedTargets;
      const description = `Deactivating ${targets.length} site${targets.length > 1 ? 's' : ''}`;
      const tempId = createJob({ description });
      api.post('/wp-json/captaincore/v1/sites/cli', {
        command: 'deactivate',
        environment: targets[0].environment,
        post_id: targets.map(t => t.site_id),
        name: toggleStatusData.business_name,
        link: toggleStatusData.business_link,
        subject: toggleStatusData.heading,
        status_msg: toggleStatusData.status_message,
        action_text: toggleStatusData.action_text,
      }).then(r => {
        const job = jobsList.value.find(j => j.job_id === tempId);
        if (job && r.data) { job.job_id = r.data; runCommand(r.data); }
        scrollToBottom();
      }).catch(err => {
        const job = jobsList.value.find(j => j.job_id === tempId);
        if (job) { job.status = 'error'; job.stream.push('Error: ' + (err.response?.data?.message || err.message)); }
      });
    }

    function launchSites() {
      if (!launchDomain.value.trim()) return;
      showLaunchDialog.value = false;
      const targets = terminalState.selectedTargets;
      const description = `Launching ${targets.length > 1 ? targets.length + ' sites' : (targets[0].name || targets[0].home_url)} to ${launchDomain.value}`;
      const tempId = createJob({ description });
      api.post('/wp-json/captaincore/v1/sites/bulk-tools', {
        tool: 'launch',
        environments: targets.map(t => t.environment_id),
        params: { domain: launchDomain.value },
      }).then(r => {
        const job = jobsList.value.find(j => j.job_id === tempId);
        if (job && r.data) { job.job_id = r.data; runCommand(r.data); }
        scrollToBottom();
      }).catch(err => {
        const job = jobsList.value.find(j => j.job_id === tempId);
        if (job) { job.status = 'error'; job.stream.push('Error: ' + (err.response?.data?.message || err.message)); }
      });
      launchDomain.value = '';
    }

    function openInBrowser() {
      terminalState.selectedTargets.forEach(t => {
        if (t.home_url) safeOpen(t.home_url);
      });
    }

    // ── Save as Recipe ────────────────────────────────────────────────────────
    function openSaveAsRecipe() {
      if (!terminalState.code.trim()) { showNotify('Enter code before saving.', 'error'); return; }
      saveRecipeTitle.value = '';
      saveRecipePublic.value = true;
      showSaveRecipeDialog.value = true;
    }

    function submitSaveRecipe() {
      if (!saveRecipeTitle.value.trim()) return;
      api.post('/wp-json/captaincore/v1/recipes', {
        title: saveRecipeTitle.value,
        content: terminalState.code,
        public: saveRecipePublic.value ? 1 : 0,
      }).then(() => {
        showSaveRecipeDialog.value = false;
        showNotify('Recipe saved.', 'success');
        cookbookLoaded.value = false;
      }).catch(() => {
        showNotify('Error saving recipe.', 'error');
      });
    }

    // ── Schedule Script ───────────────────────────────────────────────────────
    function openScheduleDialog() {
      if (!terminalState.code.trim()) { showNotify('Enter code before scheduling.', 'error'); return; }
      if (!terminalState.selectedTargets.length) { terminalState.targetMenuOpen = true; showNotify('Select at least one target.', 'error'); return; }
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      scheduleDate.value = tomorrow.toISOString().split('T')[0];
      scheduleTime.value = '05:00';
      showScheduleDialog.value = true;
    }

    function submitSchedule() {
      if (!scheduleDate.value || !scheduleTime.value) return;
      const targets = terminalState.selectedTargets;
      const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
      const promises = targets.map(t =>
        api.post('/wp-json/captaincore/v1/scripts/schedule', {
          environment_id: t.environment_id,
          code: terminalState.code,
          run_at: { time: scheduleTime.value, date: scheduleDate.value, timezone },
        })
      );
      Promise.all(promises).then(() => {
        showScheduleDialog.value = false;
        showNotify('Script scheduled for ' + targets.length + ' environment' + (targets.length > 1 ? 's' : '') + '.', 'success');
      }).catch(() => {
        showNotify('Error scheduling script.', 'error');
      });
    }

    // ── Add Filtered Targets ──────────────────────────────────────────────────
    function addFilteredTargets() {
      filteredEnvList.value.forEach(env => {
        if (!isConsoleTarget(env.environment_id)) {
          terminalState.selectedTargets.push({
            environment_id: env.environment_id,
            site_id: env.site_id,
            environment: env.environment,
            home_url: env.home_url,
            name: env.name || env.home_url,
          });
        }
      });
    }

    // ── Click-outside to close dropdowns ──────────────────────────────────────
    function handleTerminalClickOutside(e) {
      if (terminalState.targetMenuOpen && !e.target.closest('.target-dropdown-container')) {
        terminalState.targetMenuOpen = false;
      }
      if (terminalState.recipeMenuOpen && !e.target.closest('.recipe-dropdown-container')) {
        terminalState.recipeMenuOpen = false;
      }
    }

    onMounted(() => { document.addEventListener('click', handleTerminalClickOutside); });
    onUnmounted(() => { document.removeEventListener('click', handleTerminalClickOutside); });

    function getEnvList() {
      return sites.value.flatMap(s =>
        getVisibleEnvironments(s).map(e => ({
          ...e,
          site_id: s.site_id,
          name: s.name,
          site_name: s.name,
        }))
      );
    }

    const filteredEnvList = computed(() => {
      const all = getEnvList();
      if (!terminalState.targetSearch) return all;
      const q = terminalState.targetSearch.toLowerCase();
      return all.filter(e =>
        (e.name && e.name.toLowerCase().includes(q)) ||
        (e.home_url && e.home_url.toLowerCase().includes(q))
      );
    });

    const filteredCookbook = computed(() => {
      if (!terminalState.recipeMenuSearch) return cookbookRecipes.value;
      const q = terminalState.recipeMenuSearch.toLowerCase();
      return cookbookRecipes.value.filter(r => r.title && r.title.toLowerCase().includes(q));
    });

    watch(() => terminalState.recipeMenuOpen, (v) => { if (v) loadCookbook(); });

    // Global keyboard shortcut: Ctrl/Cmd + K to toggle terminal
    if (typeof window !== 'undefined') {
      window.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
          e.preventDefault();
          terminalState.open = !terminalState.open;
        }
      });
    }

    return {
      terminal: terminalState,
      jobs: jobsList,
      runningJobs,
      terminalOutputRef,
      terminalInputRef,
      executeCommand,
      handleKeydown,
      copyStream,
      killCommand,
      clearJobs,
      scrollToBottom,
      filteredEnvList,
      filteredCookbook,
      systemTools,
      useRecipe,
      toggleConsoleTarget,
      isConsoleTarget,
      // System tools
      runSystemTool,
      showHttpsDialog, applyHttpsUrls,
      showToggleStatusDialog, toggleStatusData, activateSites, deactivateSites,
      showLaunchDialog, launchDomain, launchSites,
      openInBrowser,
      // Save as Recipe
      showSaveRecipeDialog, saveRecipeTitle, saveRecipePublic, openSaveAsRecipe, submitSaveRecipe,
      // Schedule Script
      showScheduleDialog, scheduleDate, scheduleTime, openScheduleDialog, submitSchedule,
      // Add filtered
      addFilteredTargets,
    };
  },
  template: `
    <transition name="fade">
      <div v-if="terminal.open" class="terminal-window" :class="{ 'terminal-fullscreen': terminal.fullscreen }">
        <!-- Header -->
        <div class="terminal-header">
          <div class="flex items-center gap-2 mr-3">
            <div class="window-dot dot-red" @click="terminal.open = false" title="Close"></div>
            <div class="window-dot dot-yellow" @click="terminal.fullscreen = false; terminal.open = false" title="Minimize"></div>
            <div class="window-dot dot-green" @click="terminal.fullscreen = !terminal.fullscreen" title="Fullscreen"></div>
          </div>
          <span class="flex-1 text-xs text-center opacity-60 truncate" style="font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, monospace;">
            captaincore-cli —
            <span v-if="terminal.selectedTargets.length === 0">Select target</span>
            <span v-else-if="terminal.selectedTargets.length === 1">{{ terminal.selectedTargets[0].home_url || terminal.selectedTargets[0].name }}</span>
            <span v-else>{{ terminal.selectedTargets.length }} environments selected</span>
          </span>
          <button @click="clearJobs()" class="terminal-btn" v-if="jobs.length" title="Clear completed">Clear</button>
          <button @click="terminal.open = false" style="background: none; border: none; color: #6c7086; cursor: pointer; padding: 4px;">
            <svg-icon name="close" :size="16" />
          </button>
        </div>

        <!-- Output -->
        <div ref="terminalOutputRef" class="terminal-output" :style="!jobs.length ? 'display: flex; align-items: center; justify-content: center;' : ''">
          <div v-if="!jobs.length" class="text-center" style="color: #6c7086;">
            <svg-icon name="terminal" :size="32" style="opacity: 0.3;" /><br>
            <span class="text-xs mt-2 inline-block">No commands yet. Type a command below or use Ctrl+K to toggle.</span>
          </div>
          <div v-for="job in jobs" :key="job.job_id" class="mb-4">
            <div class="flex items-center gap-2 mb-1">
              <span v-if="job.status === 'running' || job.status === 'queued'" class="inline-block w-2 h-2 rounded-full animate-pulse" style="background: #a6e3a1;"></span>
              <span v-else-if="job.status === 'done'" class="inline-block w-2 h-2 rounded-full" style="background: #a6e3a1;"></span>
              <span v-else class="inline-block w-2 h-2 rounded-full" style="background: #f38ba8;"></span>
              <span class="text-xs" style="color: #89b4fa;">{{ job.description }}</span>
              <span class="text-xs" style="color: #6c7086;">{{ job.status }}</span>
              <button v-if="job.status === 'running'" @click="killCommand(job.job_id)" class="terminal-btn" style="font-size: 0.6875rem; padding: 2px 6px;">
                <svg-icon name="stop" :size="10" /> Kill
              </button>
              <button v-if="job.stream.length" @click="copyStream(job)" class="terminal-btn" style="font-size: 0.6875rem; padding: 2px 6px;">
                <svg-icon name="copy" :size="10" /> Copy
              </button>
            </div>
            <div v-if="job.command" class="text-xs mb-1" style="color: #cba6f7;">$ {{ job.command }}</div>
            <div v-for="(line, li) in job.stream" :key="li" class="text-xs" style="white-space: pre-wrap; word-break: break-all; color: #cdd6f4;">{{ line }}</div>
          </div>
        </div>

        <!-- Input Area -->
        <div class="terminal-input-area">
          <!-- Target button -->
          <div class="relative target-dropdown-container">
            <button @click.stop="terminal.targetMenuOpen = !terminal.targetMenuOpen" :class="['terminal-btn', terminal.selectedTargets.length && 'active']">
              <svg-icon name="at" :size="12" />
              <span v-if="terminal.selectedTargets.length">{{ terminal.selectedTargets.length }}</span>
              <span v-else>Target</span>
            </button>
            <!-- Target dropdown -->
            <div v-if="terminal.targetMenuOpen" @click.stop class="absolute bottom-full left-0 mb-2" style="z-index: 65; min-width: 320px; max-height: 360px; background: #1e1e2e; border: 1px solid #45475a; border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,0.4); overflow: hidden; display: flex; flex-direction: column;">
              <div class="flex items-center justify-between px-3 py-2" style="border-bottom: 1px solid #313244;">
                <span class="text-xs" style="color: #a6adc8;">{{ terminal.selectedTargets.length }} selected</span>
                <div class="flex items-center gap-2">
                  <button v-if="terminal.targetSearch && filteredEnvList.length" @click="addFilteredTargets()" class="text-xs" style="color: #a6e3a1; background: none; border: none; cursor: pointer;">Add Filtered</button>
                  <button @click="terminal.selectedTargets = []" class="text-xs" style="color: #89b4fa; background: none; border: none; cursor: pointer;">Clear All</button>
                </div>
              </div>
              <div class="px-3 py-2" style="border-bottom: 1px solid #313244;">
                <input v-model="terminal.targetSearch" type="text" placeholder="Search environments..." class="w-full px-2 py-1 text-xs rounded" style="background: #313244; border: 1px solid #45475a; color: #cdd6f4; outline: none;" />
              </div>
              <div style="overflow-y: auto; max-height: 260px; padding: 4px;">
                <div v-for="env in filteredEnvList" :key="env.environment_id"
                  @click="toggleConsoleTarget(env)"
                  class="flex items-center gap-2 px-3 py-2 rounded cursor-pointer text-xs"
                  :style="isConsoleTarget(env.environment_id) ? 'background: #313244; color: #cdd6f4;' : 'color: #a6adc8;'"
                  @mouseenter="$event.target.style.background='#313244'"
                  @mouseleave="$event.target.style.background = isConsoleTarget(env.environment_id) ? '#313244' : 'transparent'">
                  <input type="checkbox" :checked="isConsoleTarget(env.environment_id)" style="accent-color: #89b4fa;" @click.stop />
                  <span class="font-medium" style="color: #cdd6f4;">{{ env.site_name || env.name }}</span>
                  <span :class="env.environment === 'Production' ? '' : ''" style="color: #6c7086;">{{ env.environment }}</span>
                  <span class="truncate flex-1 text-right" style="color: #585b70;">{{ (env.home_url || '').replace(/^https?:\\/\\//, '') }}</span>
                </div>
                <div v-if="!filteredEnvList.length" class="px-3 py-4 text-center text-xs" style="color: #6c7086;">No environments found.</div>
              </div>
            </div>
          </div>

          <!-- Recipe button -->
          <div class="relative recipe-dropdown-container">
            <button @click.stop="terminal.recipeMenuOpen = !terminal.recipeMenuOpen" class="terminal-btn">
              <svg-icon name="cookbook" :size="12" /> Recipes
            </button>
            <!-- Recipe dropdown -->
            <div v-if="terminal.recipeMenuOpen" @click.stop class="absolute bottom-full left-0 mb-2" style="z-index: 65; min-width: 280px; max-height: 320px; background: #1e1e2e; border: 1px solid #45475a; border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,0.4); overflow: hidden; display: flex; flex-direction: column;">
              <div class="flex gap-1 px-3 py-2" style="border-bottom: 1px solid #313244;">
                <button @click="terminal.recipeMenuTab = 'system'" :class="['terminal-btn', terminal.recipeMenuTab === 'system' && 'active']" style="font-size: 0.6875rem;">System</button>
                <button @click="terminal.recipeMenuTab = 'cookbook'" :class="['terminal-btn', terminal.recipeMenuTab === 'cookbook' && 'active']" style="font-size: 0.6875rem;">Cookbook</button>
              </div>
              <div v-if="terminal.recipeMenuTab === 'cookbook'" class="px-3 py-2" style="border-bottom: 1px solid #313244;">
                <input v-model="terminal.recipeMenuSearch" type="text" placeholder="Search recipes..." class="w-full px-2 py-1 text-xs rounded" style="background: #313244; border: 1px solid #45475a; color: #cdd6f4; outline: none;" />
              </div>
              <div style="overflow-y: auto; max-height: 240px; padding: 4px;">
                <template v-if="terminal.recipeMenuTab === 'system'">
                  <div v-for="tool in systemTools" :key="tool.method"
                    @click="runSystemTool(tool.method)"
                    class="flex items-center gap-2 px-3 py-2 rounded cursor-pointer text-xs"
                    style="color: #a6adc8;"
                    @mouseenter="$event.target.style.background='#313244'"
                    @mouseleave="$event.target.style.background='transparent'">
                    <svg-icon :name="tool.icon" :size="14" />
                    <span>{{ tool.title }}</span>
                  </div>
                </template>
                <template v-else>
                  <div v-for="recipe in filteredCookbook" :key="recipe.recipe_id || recipe.id"
                    @click="useRecipe(recipe)"
                    class="flex items-center gap-2 px-3 py-2 rounded cursor-pointer text-xs"
                    style="color: #a6adc8;"
                    @mouseenter="$event.target.style.background='#313244'"
                    @mouseleave="$event.target.style.background='transparent'">
                    <svg-icon name="cookbook" :size="14" />
                    <span>{{ recipe.title }}</span>
                  </div>
                  <div v-if="!filteredCookbook.length" class="px-3 py-4 text-center text-xs" style="color: #6c7086;">No recipes found.</div>
                </template>
              </div>
            </div>
          </div>

          <!-- Prompt and input -->
          <span class="text-sm flex-shrink-0 pt-1" style="color: #a6e3a1; font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, monospace;">$</span>
          <textarea
            ref="terminalInputRef"
            v-model="terminal.code"
            @keydown="handleKeydown"
            placeholder="Enter WP-CLI or code... (Ctrl+Enter to run)"
            spellcheck="false"
            autocomplete="off"
            autocorrect="off"
            rows="1"
            class="flex-1 text-xs resize-none"
            style="background: transparent; border: none; color: #cdd6f4; outline: none; font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, monospace; line-height: 1.6; min-height: 24px; max-height: 120px; padding-top: 4px;"
          ></textarea>
          <button v-if="terminal.code.trim()" @click="openSaveAsRecipe()" class="terminal-btn" style="flex-shrink: 0;" title="Save as Recipe">
            <svg-icon name="save" :size="12" />
          </button>
          <button v-if="terminal.code.trim()" @click="openScheduleDialog()" class="terminal-btn" style="flex-shrink: 0;" title="Schedule Script">
            <svg-icon name="clock" :size="12" />
          </button>
          <button @click="executeCommand()" :disabled="!terminal.code || !terminal.selectedTargets.length" class="terminal-btn active" style="flex-shrink: 0;">
            <svg-icon name="play" :size="12" /> Run
          </button>
        </div>

        <!-- Apply HTTPS Dialog -->
        <div v-if="showHttpsDialog" class="dialog-overlay" style="position: fixed; z-index: 70;" @click.self="showHttpsDialog = false">
          <div style="background: #1e1e2e; border: 1px solid #45475a; border-radius: 12px; padding: 24px; max-width: 360px; width: 100%; box-shadow: 0 12px 40px rgba(0,0,0,0.5);">
            <h3 class="text-sm font-semibold mb-4" style="color: #cdd6f4;">Apply HTTPS URLs</h3>
            <p class="text-xs mb-4" style="color: #a6adc8;">Choose the URL format to apply:</p>
            <div class="flex gap-3">
              <button @click="applyHttpsUrls(false)" class="flex-1 px-4 py-2 rounded-lg text-xs font-medium" style="background: #313244; color: #89b4fa; border: 1px solid #45475a; cursor: pointer;">https://domain.tld</button>
              <button @click="applyHttpsUrls(true)" class="flex-1 px-4 py-2 rounded-lg text-xs font-medium" style="background: #313244; color: #89b4fa; border: 1px solid #45475a; cursor: pointer;">https://www.domain.tld</button>
            </div>
          </div>
        </div>

        <!-- Toggle Site Status Dialog -->
        <div v-if="showToggleStatusDialog" class="dialog-overlay" style="position: fixed; z-index: 70;" @click.self="showToggleStatusDialog = false">
          <div style="background: #1e1e2e; border: 1px solid #45475a; border-radius: 12px; padding: 24px; max-width: 520px; width: 100%; box-shadow: 0 12px 40px rgba(0,0,0,0.5);">
            <h3 class="text-sm font-semibold mb-4" style="color: #cdd6f4;">Toggle Site Status</h3>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <h4 class="text-xs font-medium mb-3" style="color: #f38ba8;">Deactivate Site</h4>
                <div class="space-y-2">
                  <input v-model="toggleStatusData.business_name" type="text" placeholder="Business Name" class="w-full px-2 py-1.5 text-xs rounded" style="background: #313244; border: 1px solid #45475a; color: #cdd6f4; outline: none;" />
                  <input v-model="toggleStatusData.business_link" type="text" placeholder="Business Link" class="w-full px-2 py-1.5 text-xs rounded" style="background: #313244; border: 1px solid #45475a; color: #cdd6f4; outline: none;" />
                  <input v-model="toggleStatusData.heading" type="text" placeholder="Heading/Subject" class="w-full px-2 py-1.5 text-xs rounded" style="background: #313244; border: 1px solid #45475a; color: #cdd6f4; outline: none;" />
                  <textarea v-model="toggleStatusData.status_message" placeholder="Status Message" rows="2" class="w-full px-2 py-1.5 text-xs rounded resize-none" style="background: #313244; border: 1px solid #45475a; color: #cdd6f4; outline: none;"></textarea>
                  <input v-model="toggleStatusData.action_text" type="text" placeholder="Action Text" class="w-full px-2 py-1.5 text-xs rounded" style="background: #313244; border: 1px solid #45475a; color: #cdd6f4; outline: none;" />
                  <button @click="deactivateSites()" class="w-full px-3 py-2 rounded-lg text-xs font-medium" style="background: #f38ba8; color: #1e1e2e; border: none; cursor: pointer;">Deactivate</button>
                </div>
              </div>
              <div>
                <h4 class="text-xs font-medium mb-3" style="color: #a6e3a1;">Activate Site</h4>
                <p class="text-xs mb-3" style="color: #a6adc8;">Re-activate the selected site(s) and restore normal operation.</p>
                <button @click="activateSites()" class="w-full px-3 py-2 rounded-lg text-xs font-medium" style="background: #a6e3a1; color: #1e1e2e; border: none; cursor: pointer;">Activate</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Launch Site Dialog -->
        <div v-if="showLaunchDialog" class="dialog-overlay" style="position: fixed; z-index: 70;" @click.self="showLaunchDialog = false">
          <div style="background: #1e1e2e; border: 1px solid #45475a; border-radius: 12px; padding: 24px; max-width: 400px; width: 100%; box-shadow: 0 12px 40px rgba(0,0,0,0.5);">
            <h3 class="text-sm font-semibold mb-2" style="color: #cdd6f4;">Launch Site</h3>
            <p class="text-xs mb-4" style="color: #a6adc8;">Update development URLs to the live domain. This will turn off search privacy.</p>
            <div class="flex items-center gap-2 mb-4">
              <span class="text-xs" style="color: #6c7086;">https://</span>
              <input v-model="launchDomain" type="text" placeholder="example.com" class="flex-1 px-2 py-1.5 text-xs rounded" style="background: #313244; border: 1px solid #45475a; color: #cdd6f4; outline: none;" @keydown.enter="launchSites()" />
            </div>
            <div class="flex justify-end gap-2">
              <button @click="showLaunchDialog = false" class="px-4 py-2 rounded-lg text-xs" style="background: #313244; color: #a6adc8; border: 1px solid #45475a; cursor: pointer;">Cancel</button>
              <button @click="launchSites()" :disabled="!launchDomain.trim()" class="px-4 py-2 rounded-lg text-xs font-medium" style="background: #89b4fa; color: #1e1e2e; border: none; cursor: pointer;">Launch</button>
            </div>
          </div>
        </div>

        <!-- Save as Recipe Dialog -->
        <div v-if="showSaveRecipeDialog" class="dialog-overlay" style="position: fixed; z-index: 70;" @click.self="showSaveRecipeDialog = false">
          <div style="background: #1e1e2e; border: 1px solid #45475a; border-radius: 12px; padding: 24px; max-width: 400px; width: 100%; box-shadow: 0 12px 40px rgba(0,0,0,0.5);">
            <h3 class="text-sm font-semibold mb-4" style="color: #cdd6f4;">Save as Recipe</h3>
            <div class="space-y-3">
              <input v-model="saveRecipeTitle" type="text" placeholder="Recipe name" class="w-full px-2 py-1.5 text-xs rounded" style="background: #313244; border: 1px solid #45475a; color: #cdd6f4; outline: none;" @keydown.enter="submitSaveRecipe()" />
              <div class="px-3 py-2 rounded text-xs" style="background: #313244; color: #cba6f7; font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, monospace; white-space: pre-wrap; max-height: 120px; overflow-y: auto;">{{ terminal.code }}</div>
              <label class="flex items-center gap-2 text-xs cursor-pointer" style="color: #a6adc8;">
                <input type="checkbox" v-model="saveRecipePublic" style="accent-color: #89b4fa;" /> Public recipe
              </label>
            </div>
            <div class="flex justify-end gap-2 mt-4">
              <button @click="showSaveRecipeDialog = false" class="px-4 py-2 rounded-lg text-xs" style="background: #313244; color: #a6adc8; border: 1px solid #45475a; cursor: pointer;">Cancel</button>
              <button @click="submitSaveRecipe()" :disabled="!saveRecipeTitle.trim()" class="px-4 py-2 rounded-lg text-xs font-medium" style="background: #89b4fa; color: #1e1e2e; border: none; cursor: pointer;">Save Recipe</button>
            </div>
          </div>
        </div>

        <!-- Schedule Script Dialog -->
        <div v-if="showScheduleDialog" class="dialog-overlay" style="position: fixed; z-index: 70;" @click.self="showScheduleDialog = false">
          <div style="background: #1e1e2e; border: 1px solid #45475a; border-radius: 12px; padding: 24px; max-width: 400px; width: 100%; box-shadow: 0 12px 40px rgba(0,0,0,0.5);">
            <h3 class="text-sm font-semibold mb-4" style="color: #cdd6f4;">Schedule Script</h3>
            <div class="space-y-3">
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs mb-1" style="color: #a6adc8;">Date</label>
                  <input v-model="scheduleDate" type="date" class="w-full px-2 py-1.5 text-xs rounded" style="background: #313244; border: 1px solid #45475a; color: #cdd6f4; outline: none; color-scheme: dark;" />
                </div>
                <div>
                  <label class="block text-xs mb-1" style="color: #a6adc8;">Time</label>
                  <input v-model="scheduleTime" type="time" class="w-full px-2 py-1.5 text-xs rounded" style="background: #313244; border: 1px solid #45475a; color: #cdd6f4; outline: none; color-scheme: dark;" />
                </div>
              </div>
              <div class="px-3 py-2 rounded text-xs" style="background: #313244; color: #cba6f7; font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, monospace; white-space: pre-wrap; max-height: 120px; overflow-y: auto;">{{ terminal.code }}</div>
              <p class="text-xs" style="color: #6c7086;">Running on {{ terminal.selectedTargets.length }} environment{{ terminal.selectedTargets.length > 1 ? 's' : '' }}</p>
            </div>
            <div class="flex justify-end gap-2 mt-4">
              <button @click="showScheduleDialog = false" class="px-4 py-2 rounded-lg text-xs" style="background: #313244; color: #a6adc8; border: 1px solid #45475a; cursor: pointer;">Cancel</button>
              <button @click="submitSchedule()" :disabled="!scheduleDate || !scheduleTime" class="px-4 py-2 rounded-lg text-xs font-medium" style="background: #89b4fa; color: #1e1e2e; border: none; cursor: pointer;">Schedule</button>
            </div>
          </div>
        </div>
      </div>
    </transition>
  `,
});

// ─── Component: ActivityIsland ───────────────────────────────────────────────
const ActivityIsland = defineComponent({
  setup() {
    const { jobs: jobsList, runningJobs, hasJobs } = useJobs();
    const latestJob = computed(() => jobsList.value.length ? jobsList.value[jobsList.value.length - 1] : null);

    function openTerminal() {
      terminalState.open = true;
    }

    return { hasJobs, runningJobs, latestJob, openTerminal, terminalState };
  },
  template: `
    <transition name="fade">
      <div v-if="hasJobs && !terminalState.open" class="activity-island" @click="openTerminal">
        <span v-if="runningJobs > 0" class="inline-block w-3 h-3 rounded-full animate-pulse" style="background: #a6e3a1;"></span>
        <svg-icon v-else name="check" :size="16" style="color: #a6e3a1;" />
        <span v-if="latestJob" class="truncate" style="max-width: 200px;">{{ latestJob.description }}</span>
        <span v-if="runningJobs > 0" class="text-xs" style="color: #6c7086;">{{ runningJobs }} running</span>
      </div>
    </transition>
  `,
});

// ─── Layout: AppLayout (sidebar + topbar + content) ──────────────────────────
const AppLayout = defineComponent({
  components: { SidebarNav, TopBar, TerminalWindow, ActivityIsland },
  setup() {
    return { terminalState };
  },
  template: `
    <div class="min-h-screen">
      <sidebar-nav />
      <div class="lg:pl-64 flex flex-col min-h-screen">
        <top-bar />
        <main class="flex-1 p-4 md:p-6" :style="terminalState.open && !terminalState.fullscreen ? 'padding-bottom: 45vh' : ''">
          <router-view />
        </main>
      </div>
      <terminal-window />
      <activity-island />
    </div>
  `,
});

// ─── Layout: BlankLayout (login, connect) ────────────────────────────────────
const BlankLayout = defineComponent({
  template: `
    <div class="min-h-screen flex items-center justify-center p-4">
      <router-view />
    </div>
  `,
});

// ─── View: LoginView ─────────────────────────────────────────────────────────
const LoginView = defineComponent({
  setup() {
    const { login, signIn, resetPassword } = useAuth();
    const configs = CC.configurations;
    return { login, signIn, resetPassword, configs };
  },
  template: `
    <div class="w-full" style="max-width: 380px;">
      <div class="text-center mb-6">
        <img v-if="configs.logo" :src="configs.logo" :style="{ maxWidth: (configs.logo_width || '32') + 'px' }" class="mx-auto mb-3" />
        <h1 class="text-lg font-semibold" style="color: var(--text-primary)">{{ configs.name }}</h1>
      </div>
      <div class="surface rounded-xl p-6">
        <h2 class="text-base font-semibold mb-4" style="color: var(--text-primary)">{{ login.lost_password ? 'Reset Password' : 'Login' }}</h2>

        <!-- Reset Password Form -->
        <form v-if="login.lost_password" @submit.prevent="resetPassword()">
          <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Username or Email</label>
          <input v-model="login.user_login" type="text" class="input-field mb-4" required :disabled="login.loading" />

          <div v-if="login.message" class="rounded-lg p-3 mb-4 text-sm" style="background: color-mix(in srgb, var(--color-success) 15%, transparent); color: var(--color-success);">{{ login.message }}</div>

          <div v-if="login.loading" class="mb-4"><div class="h-1 rounded-full overflow-hidden" style="background: var(--border-color)"><div class="h-full rounded-full animate-pulse" style="background: var(--color-primary); width: 70%"></div></div></div>

          <button type="submit" class="btn btn-primary w-full" :disabled="login.loading">Reset Password</button>
        </form>

        <!-- Login Form -->
        <form v-else @submit.prevent="signIn()">
          <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Username or Email</label>
          <input v-model="login.user_login" type="text" class="input-field mb-4" required :disabled="login.loading" />

          <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Password</label>
          <input v-model="login.user_password" type="password" class="input-field mb-4" required :disabled="login.loading" />

          <div v-if="login.info || login.errors === 'One time password is invalid.'" class="mb-4">
            <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">One Time Password</label>
            <input v-model="login.tfa_code" type="text" class="input-field" maxlength="6" placeholder="000000" :disabled="login.loading" />
          </div>

          <div v-if="login.errors" class="rounded-lg p-3 mb-4 text-sm" style="background: color-mix(in srgb, var(--color-error) 15%, transparent); color: var(--color-error);">{{ login.errors }}</div>
          <div v-if="login.info" class="rounded-lg p-3 mb-4 text-sm" style="background: color-mix(in srgb, var(--color-info) 15%, transparent); color: var(--color-info);">{{ login.info }}</div>

          <div v-if="login.loading" class="mb-4"><div class="h-1 rounded-full overflow-hidden" style="background: var(--border-color)"><div class="h-full rounded-full animate-pulse" style="background: var(--color-primary); width: 70%"></div></div></div>

          <button type="submit" class="btn btn-primary w-full" :disabled="login.loading">Login</button>
        </form>

        <div class="text-center mt-4">
          <a v-if="!login.lost_password" href="#" @click.prevent="login.lost_password = true" class="text-xs underline" style="color: var(--color-primary)">Lost your password?</a>
          <a v-else href="#" @click.prevent="login.lost_password = false" class="text-xs underline" style="color: var(--color-primary)">Back to login form.</a>
        </div>
      </div>
    </div>
  `,
});

// ─── View: SitesView ─────────────────────────────────────────────────────────
const SitesView = defineComponent({
  setup() {
    const { filteredSites, sitesLoading, sitesError, siteSearch, fetchSites, retrySites } = useSites();
    const {
      appliedThemeFilters, appliedPluginFilters, appliedCoreFilters,
      filterLogic, filterVersionLogic, filterStatusLogic,
      backupModeFilter, sitesFiltering,
      isAnySiteFilterActive, hasSecondaryFilters, combinedAppliedFilters,
      filterSites, clearSiteFilters,
      toggleThemeFilter, togglePluginFilter, toggleCoreFilter, setBackupMode,
      filterVersions, filterStatuses, filterVersionsLoading, filterStatusesLoading,
      toggleFilterVersion, toggleFilterStatus,
    } = useFilters();
    const router = useRouter();
    const role = userRole;
    const viewMode = ref(localStorage.getItem('captaincore-sites-view') || 'cards');

    // Filter dropdown state
    const showCoreDropdown = ref(false);
    const showThemeDropdown = ref(false);
    const showPluginDropdown = ref(false);
    const filterSearchCore = ref('');
    const filterSearchTheme = ref('');
    const filterSearchPlugin = ref('');

    // Get filter data from PHP injection
    const siteFilters = CC.site_filters || [];
    const siteFiltersCore = CC.site_filters_core || [];
    const themeFilters = computed(() => siteFilters.filter(f => f.type === 'themes'));
    const pluginFilters = computed(() => siteFilters.filter(f => f.type === 'plugins'));

    const filteredCoreOptions = computed(() => {
      if (!filterSearchCore.value) return siteFiltersCore;
      const q = filterSearchCore.value.toLowerCase();
      return siteFiltersCore.filter(c => c.name && c.name.toLowerCase().includes(q));
    });
    const filteredThemeOptions = computed(() => {
      if (!filterSearchTheme.value) return themeFilters.value;
      const q = filterSearchTheme.value.toLowerCase();
      return themeFilters.value.filter(f => f.search && f.search.toLowerCase().includes(q));
    });
    const filteredPluginOptions = computed(() => {
      if (!filterSearchPlugin.value) return pluginFilters.value;
      const q = filterSearchPlugin.value.toLowerCase();
      return pluginFilters.value.filter(f => f.search && f.search.toLowerCase().includes(q));
    });

    // Close dropdowns on outside click
    function closeFilterDropdowns() {
      showCoreDropdown.value = false;
      showThemeDropdown.value = false;
      showPluginDropdown.value = false;
    }

    watch(viewMode, v => localStorage.setItem('captaincore-sites-view', v));

    onMounted(() => { fetchSites(); });

    function goToSite(id) { router.push('/sites/' + id); }

    function getPrimaryEnv(site) {
      const envs = getVisibleEnvironments(site);
      return envs.find(e => e.environment === 'Production') || envs[0] || {};
    }

    function getStagingEnvs(site) {
      return getVisibleEnvironments(site).filter(e => e.environment !== 'Production');
    }

    // New Site dialog state
    const showNewSiteDialog = ref(false);
    const defaultEnv = () => ({
      environment: 'Production', site: '', address: '', username: '', password: '',
      protocol: 'sftp', port: '2222', home_directory: '',
      updates_enabled: '1',
      offload_enabled: false, offload_provider: '', offload_access_key: '', offload_secret_key: '', offload_bucket: '', offload_path: '',
    });
    const newSite = reactive({
      name: '', domain: '', provider_id: '', site: '',
      shared_with: [], key: null,
      environments: [defaultEnv()],
      environment_vars: [],
      errors: '', loading: false, showAdvanced: false,
    });
    const providers = ref([]);
    const providersLoaded = ref(false);
    const newSiteAccounts = ref([]);
    const newSiteAccountsLoaded = ref(false);
    const newSiteKeys = ref([]);
    const newSiteKeysLoaded = ref(false);

    function openNewSiteDialog() {
      showNewSiteDialog.value = true;
      if (!providersLoaded.value) {
        api.get('/wp-json/captaincore/v1/providers')
          .then(r => { providers.value = r.data || []; providersLoaded.value = true; })
          .catch(() => {});
      }
      if (!newSiteAccountsLoaded.value) {
        api.get('/wp-json/captaincore/v1/accounts')
          .then(r => { newSiteAccounts.value = r.data || []; newSiteAccountsLoaded.value = true; })
          .catch(() => {});
      }
      if (!newSiteKeysLoaded.value) {
        api.get('/wp-json/captaincore/v1/keys')
          .then(r => { newSiteKeys.value = r.data || []; newSiteKeysLoaded.value = true; })
          .catch(() => {});
      }
    }

    function addStagingEnv() {
      if (newSite.environments.length >= 2) return;
      newSite.environments.push({ ...defaultEnv(), environment: 'Staging', port: '2222', updates_enabled: '1' });
    }
    function removeStagingEnv() {
      newSite.environments = newSite.environments.filter(e => e.environment !== 'Staging');
    }
    function toggleNewSiteAccount(accountId) {
      const idx = newSite.shared_with.indexOf(accountId);
      if (idx > -1) newSite.shared_with.splice(idx, 1);
      else newSite.shared_with.push(accountId);
    }
    function addEnvVar() { newSite.environment_vars.push({ key: '', value: '' }); }
    function removeEnvVar(i) { newSite.environment_vars.splice(i, 1); }

    function createSite() {
      newSite.errors = '';
      newSite.loading = true;
      const payload = {
        site: {
          name: newSite.name, site: newSite.site || newSite.name,
          provider: newSite.provider_id || '',
          shared_with: newSite.shared_with,
          key: newSite.key,
          environments: newSite.environments,
          environment_vars: newSite.environment_vars.filter(v => v.key),
        },
      };
      api.post('/wp-json/captaincore/v1/sites', payload)
        .then(r => {
          showNewSiteDialog.value = false;
          showNotify('Site created successfully!', 'success');
          Object.assign(newSite, { name: '', domain: '', provider_id: '', site: '', shared_with: [], key: null, environments: [defaultEnv()], environment_vars: [], errors: '', showAdvanced: false });
          refreshSites();
          fetchSites();
        })
        .catch(err => {
          newSite.errors = err.response?.data?.message || 'Failed to create site.';
        })
        .finally(() => { newSite.loading = false; });
    }

    const { showNotify } = useNotify();
    function magicLogin(siteId, env, event) {
      if (event) event.stopPropagation();
      if (!env) return;
      const envName = (env.environment || 'production').toLowerCase();
      env.isLoggingIn = true;
      api.get('/wp-json/captaincore/v1/sites/' + siteId + '/' + envName + '/magiclogin')
        .then(r => {
          if (typeof r.data === 'string') {
            safeOpen(r.data);
          } else {
            showNotify('Login failed.', 'error');
          }
        })
        .catch(() => showNotify('Login request failed.', 'error'))
        .finally(() => { env.isLoggingIn = false; });
    }

    // ── Bulk Selection ──
    const selectedSites = ref([]);
    const bulkEnv = ref('Production');
    const showBulkActions = computed(() => selectedSites.value.length > 0);

    function toggleSiteSelection(site) {
      const idx = selectedSites.value.findIndex(s => s.site_id === site.site_id);
      if (idx > -1) {
        selectedSites.value.splice(idx, 1);
      } else {
        selectedSites.value.push(site);
      }
    }

    function isSiteSelected(siteId) {
      return selectedSites.value.some(s => s.site_id === siteId);
    }

    function selectAllSites() {
      selectedSites.value = [...filteredSites.value];
    }

    function clearSelection() {
      selectedSites.value = [];
    }

    function getSelectedEnvironmentIds() {
      const ids = [];
      selectedSites.value.forEach(site => {
        const envs = getVisibleEnvironments(site);
        const target = envs.find(e => e.environment === bulkEnv.value) || envs[0];
        if (target && target.environment_id) ids.push(target.environment_id);
      });
      return ids;
    }

    // Bulk action: Add selected to terminal
    function bulkAddToTerminal() {
      selectedSites.value.forEach(site => {
        const envs = getVisibleEnvironments(site);
        const target = envs.find(e => e.environment === bulkEnv.value) || envs[0];
        if (target && !isConsoleTarget(target.environment_id)) {
          toggleConsoleTarget({ ...target, site_id: site.site_id, name: site.name });
        }
      });
      terminalState.open = true;
    }

    // Bulk action: Open in browser
    function bulkOpenInBrowser() {
      selectedSites.value.forEach(site => {
        const envs = getVisibleEnvironments(site);
        const target = envs.find(e => e.environment === bulkEnv.value) || envs[0];
        if (target && target.home_url) safeOpen(target.home_url);
      });
    }

    // Bulk action: Sync data
    function bulkSyncSites() {
      const envIds = getSelectedEnvironmentIds();
      if (!envIds.length) { showNotify('No environments found for selection.', 'error'); return; }
      if (!confirm('Sync data for ' + selectedSites.value.length + ' sites?')) return;
      api.post('/wp-json/captaincore/v1/sites/bulk-tools', {
        tool: 'sync-data',
        environments: envIds,
        params: {},
      }).then(() => showNotify('Sync started for ' + envIds.length + ' environments.', 'success'))
        .catch(() => showNotify('Sync failed.', 'error'));
    }

    // Bulk action: Apply HTTPS
    const showBulkHttpsDialog = ref(false);
    function bulkApplyHttps(useWww) {
      const envIds = getSelectedEnvironmentIds();
      if (!envIds.length) { showNotify('No environments found.', 'error'); return; }
      const tool = useWww ? 'apply-https-with-www' : 'apply-https';
      api.post('/wp-json/captaincore/v1/sites/bulk-tools', {
        tool: tool,
        environments: envIds,
        params: {},
      }).then(() => { showNotify('HTTPS applied to ' + envIds.length + ' environments.', 'success'); showBulkHttpsDialog.value = false; })
        .catch(() => showNotify('Apply HTTPS failed.', 'error'));
    }

    // Bulk action: Deploy defaults
    function bulkDeployDefaults() {
      const siteIds = selectedSites.value.map(s => s.site_id);
      if (!confirm('Deploy defaults on ' + siteIds.length + ' sites?')) return;
      api.post('/wp-json/captaincore/v1/sites/cli', {
        post_id: siteIds,
        command: 'deploy-defaults',
        environment: bulkEnv.value,
      }).then(() => showNotify('Deploy defaults started for ' + siteIds.length + ' sites.', 'success'))
        .catch(() => showNotify('Deploy defaults failed.', 'error'));
    }

    // Bulk action: Toggle site status
    const showBulkToggleDialog = ref(false);
    function bulkToggleStatus(action) {
      const siteIds = selectedSites.value.map(s => s.site_id);
      if (!confirm(action + ' ' + siteIds.length + ' sites?')) return;
      api.post('/wp-json/captaincore/v1/sites/cli', {
        post_id: siteIds,
        command: action === 'Activate' ? 'activate' : 'deactivate',
        environment: bulkEnv.value,
      }).then(() => { showNotify(action + ' started for ' + siteIds.length + ' sites.', 'success'); showBulkToggleDialog.value = false; })
        .catch(() => showNotify(action + ' failed.', 'error'));
    }

    // Add filtered sites to terminal targets
    function addFilteredToTerminal() {
      filteredSites.value.forEach(site => {
        getVisibleEnvironments(site).forEach(env => {
          if (!isConsoleTarget(env.environment_id)) {
            toggleConsoleTarget({
              ...env,
              site_id: site.site_id,
              name: site.name,
            });
          }
        });
      });
      terminalState.open = true;
    }

    // ── Bulk Add Plugin/Theme ──
    const showBulkAddPluginDialog = ref(false);
    const showBulkAddThemeDialog = ref(false);
    const bulkPluginSearch = ref('');
    const bulkThemeSearch = ref('');
    const bulkPluginResults = ref([]);
    const bulkThemeResults = ref([]);
    const bulkPluginLoading = ref(false);
    const bulkThemeLoading = ref(false);
    const bulkInstalling = reactive({});

    function openBulkAddPlugin() {
      bulkPluginSearch.value = '';
      bulkPluginResults.value = [];
      showBulkAddPluginDialog.value = true;
    }
    function openBulkAddTheme() {
      bulkThemeSearch.value = '';
      bulkThemeResults.value = [];
      showBulkAddThemeDialog.value = true;
    }
    function searchBulkPlugins() {
      if (!bulkPluginSearch.value) return;
      bulkPluginLoading.value = true;
      api.get('/wp-json/captaincore/v1/wp-plugins', { params: { value: bulkPluginSearch.value, page: 1 } })
        .then(r => { bulkPluginResults.value = (r.data && r.data.plugins) || r.data || []; })
        .catch(() => showNotify('Plugin search failed', 'error'))
        .finally(() => { bulkPluginLoading.value = false; });
    }
    function searchBulkThemes() {
      if (!bulkThemeSearch.value) return;
      bulkThemeLoading.value = true;
      api.get('/wp-json/captaincore/v1/wp-themes', { params: { value: bulkThemeSearch.value, page: 1 } })
        .then(r => { bulkThemeResults.value = (r.data && r.data.themes) || r.data || []; })
        .catch(() => showNotify('Theme search failed', 'error'))
        .finally(() => { bulkThemeLoading.value = false; });
    }
    function bulkInstallPlugin(plugin) {
      const envIds = getSelectedEnvironmentIds();
      if (!envIds.length) { showNotify('No environments found', 'error'); return; }
      const slug = plugin.slug || plugin.name;
      bulkInstalling[slug] = true;
      const link = plugin.download_link || slug;
      api.post('/wp-json/captaincore/v1/run/code', { environments: envIds, code: "wp plugin install --force --skip-plugins --skip-themes '" + link + "'" })
        .then(() => showNotify('Installing ' + (plugin.name || slug) + ' on ' + selectedSites.value.length + ' sites', 'success'))
        .catch(() => showNotify('Install failed', 'error'))
        .finally(() => { bulkInstalling[slug] = false; });
    }
    function bulkInstallTheme(theme) {
      const envIds = getSelectedEnvironmentIds();
      if (!envIds.length) { showNotify('No environments found', 'error'); return; }
      const slug = theme.slug || theme.name;
      bulkInstalling[slug] = true;
      api.post('/wp-json/captaincore/v1/run/code', { environments: envIds, code: "wp theme install '" + slug + "' --force" })
        .then(() => showNotify('Installing ' + (theme.name || slug) + ' on ' + selectedSites.value.length + ' sites', 'success'))
        .catch(() => showNotify('Install failed', 'error'))
        .finally(() => { bulkInstalling[slug] = false; });
    }

    // ── Pagination ──
    const sitePage = ref(1);
    const sitesPerPage = 100;
    const totalSitePages = computed(() => Math.ceil(filteredSites.value.length / sitesPerPage));
    const paginatedSites = computed(() => {
      const start = (sitePage.value - 1) * sitesPerPage;
      return filteredSites.value.slice(start, start + sitesPerPage);
    });
    // Reset page when search or filters change
    watch([siteSearch, appliedThemeFilters, appliedPluginFilters, appliedCoreFilters, backupModeFilter], () => { sitePage.value = 1; });

    return {
      filteredSites, sitesLoading, sitesError, retrySites, siteSearch, viewMode, goToSite, getPrimaryEnv, getStagingEnvs, magicLogin,
      sitePage, totalSitePages, paginatedSites,
      getVisibleEnvironments, getScreenshotUrl, formatLargeNumbers, formatStorage, role,
      // Filters
      appliedThemeFilters, appliedPluginFilters, appliedCoreFilters,
      filterLogic, filterVersionLogic, filterStatusLogic,
      backupModeFilter, sitesFiltering, isAnySiteFilterActive, hasSecondaryFilters,
      combinedAppliedFilters, filterSites,
      clearSiteFilters, toggleThemeFilter, togglePluginFilter, toggleCoreFilter, setBackupMode,
      filterVersions, filterStatuses, filterVersionsLoading, filterStatusesLoading,
      toggleFilterVersion, toggleFilterStatus,
      showCoreDropdown, showThemeDropdown, showPluginDropdown,
      filterSearchCore, filterSearchTheme, filterSearchPlugin,
      filteredCoreOptions, filteredThemeOptions, filteredPluginOptions,
      closeFilterDropdowns,
      // New Site
      showNewSiteDialog, newSite, providers, openNewSiteDialog, createSite,
      newSiteAccounts, newSiteKeys, addStagingEnv, removeStagingEnv, toggleNewSiteAccount, addEnvVar, removeEnvVar,
      addFilteredToTerminal,
      // Bulk Actions
      selectedSites, bulkEnv, showBulkActions, toggleSiteSelection, isSiteSelected,
      selectAllSites, clearSelection, bulkAddToTerminal, bulkOpenInBrowser,
      bulkSyncSites, bulkDeployDefaults,
      showBulkHttpsDialog, bulkApplyHttps,
      showBulkToggleDialog, bulkToggleStatus,
      showBulkAddPluginDialog, showBulkAddThemeDialog,
      bulkPluginSearch, bulkThemeSearch, bulkPluginResults, bulkThemeResults,
      bulkPluginLoading, bulkThemeLoading, bulkInstalling,
      openBulkAddPlugin, openBulkAddTheme, searchBulkPlugins, searchBulkThemes,
      bulkInstallPlugin, bulkInstallTheme,
    };
  },
  template: `
    <div @click="closeFilterDropdowns()">
      <!-- Toolbar -->
      <div class="surface rounded-xl mb-4">
        <div class="flex items-center justify-between px-4 py-3 flex-wrap gap-3">
          <h2 class="text-sm font-semibold" style="color: var(--text-primary)">
            <span v-if="sitesLoading">Loading sites...</span>
            <span v-else>{{ filteredSites.length }} sites</span>
            <span v-if="sitesFiltering" class="ml-2 text-xs" style="color: var(--text-secondary)">Filtering...</span>
          </h2>
          <div class="flex items-center gap-3">
            <button v-if="role === 'administrator'" @click.stop="openNewSiteDialog()" class="btn btn-sm btn-primary">
              <svg-icon name="plus" :size="14" /> New Site
            </button>
            <div class="search-wrapper">
              <svg-icon name="search" :size="16" class="search-icon" />
              <input v-model="siteSearch" type="text" placeholder="Search sites..." class="input-field" style="width: 220px" />
            </div>
            <div class="view-toggle">
              <button :class="{ active: viewMode === 'cards' }" @click="viewMode = 'cards'" title="Card View"><svg-icon name="viewCards" :size="16" /></button>
              <button :class="{ active: viewMode === 'table' }" @click="viewMode = 'table'" title="Table View"><svg-icon name="viewTable" :size="16" /></button>
              <button :class="{ active: viewMode === 'grid' }" @click="viewMode = 'grid'" title="Grid View"><svg-icon name="viewGrid" :size="16" /></button>
            </div>
          </div>
        </div>

        <!-- Filter bar -->
        <div class="flex items-center gap-2 px-4 pb-3 flex-wrap">
          <!-- Core filter -->
          <div class="relative" @click.stop>
            <button :class="['filter-chip', appliedCoreFilters.length && 'active']" @click="showCoreDropdown = !showCoreDropdown; showThemeDropdown = false; showPluginDropdown = false;">
              <svg-icon name="sparkle" :size="12" /> Core
              <span v-if="appliedCoreFilters.length">({{ appliedCoreFilters.length }})</span>
            </button>
            <div v-if="showCoreDropdown" class="dropdown-menu" style="top: calc(100% + 4px);">
              <div class="p-2" style="border-bottom: 1px solid var(--border-color);">
                <input v-model="filterSearchCore" type="text" placeholder="Search versions..." class="input-field" style="padding: 4px 8px; font-size: 0.75rem;" />
              </div>
              <div style="max-height: 240px; overflow-y: auto; padding: 4px;">
                <div v-for="c in filteredCoreOptions" :key="c.name"
                  @click="toggleCoreFilter(c)"
                  class="dropdown-menu-item">
                  <input type="checkbox" :checked="appliedCoreFilters.some(f => f.name === c.name)" style="accent-color: var(--color-primary);" @click.stop />
                  <span>{{ c.name }}</span>
                  <span class="ml-auto text-xs" style="color: var(--text-secondary);">{{ c.count }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Theme filter -->
          <div class="relative" @click.stop>
            <button :class="['filter-chip', appliedThemeFilters.length && 'active']" @click="showThemeDropdown = !showThemeDropdown; showCoreDropdown = false; showPluginDropdown = false;">
              <svg-icon name="palette" :size="12" /> Themes
              <span v-if="appliedThemeFilters.length">({{ appliedThemeFilters.length }})</span>
            </button>
            <div v-if="showThemeDropdown" class="dropdown-menu" style="top: calc(100% + 4px);">
              <div class="p-2" style="border-bottom: 1px solid var(--border-color);">
                <input v-model="filterSearchTheme" type="text" placeholder="Search themes..." class="input-field" style="padding: 4px 8px; font-size: 0.75rem;" />
              </div>
              <div style="max-height: 240px; overflow-y: auto; padding: 4px;">
                <div v-for="f in filteredThemeOptions" :key="f.name"
                  @click="toggleThemeFilter(f)"
                  class="dropdown-menu-item">
                  <input type="checkbox" :checked="appliedThemeFilters.some(a => a.name === f.name)" style="accent-color: var(--color-primary);" @click.stop />
                  <span class="truncate">{{ f.title || f.name }}</span>
                </div>
                <div v-if="!filteredThemeOptions.length" class="px-3 py-2 text-xs" style="color: var(--text-secondary);">No themes found.</div>
              </div>
            </div>
          </div>

          <!-- Plugin filter -->
          <div class="relative" @click.stop>
            <button :class="['filter-chip', appliedPluginFilters.length && 'active']" @click="showPluginDropdown = !showPluginDropdown; showCoreDropdown = false; showThemeDropdown = false;">
              <svg-icon name="puzzle" :size="12" /> Plugins
              <span v-if="appliedPluginFilters.length">({{ appliedPluginFilters.length }})</span>
            </button>
            <div v-if="showPluginDropdown" class="dropdown-menu" style="top: calc(100% + 4px);">
              <div class="p-2" style="border-bottom: 1px solid var(--border-color);">
                <input v-model="filterSearchPlugin" type="text" placeholder="Search plugins..." class="input-field" style="padding: 4px 8px; font-size: 0.75rem;" />
              </div>
              <div style="max-height: 240px; overflow-y: auto; padding: 4px;">
                <div v-for="f in filteredPluginOptions" :key="f.name"
                  @click="togglePluginFilter(f)"
                  class="dropdown-menu-item">
                  <input type="checkbox" :checked="appliedPluginFilters.some(a => a.name === f.name)" style="accent-color: var(--color-primary);" @click.stop />
                  <span class="truncate">{{ f.title || f.name }}</span>
                </div>
                <div v-if="!filteredPluginOptions.length" class="px-3 py-2 text-xs" style="color: var(--text-secondary);">No plugins found.</div>
              </div>
            </div>
          </div>

          <!-- AND/OR toggle (primary) -->
          <button v-if="(appliedThemeFilters.length + appliedPluginFilters.length + appliedCoreFilters.length) > 1"
            @click="filterLogic = filterLogic === 'and' ? 'or' : 'and'; filterSites()"
            class="filter-chip" title="Logic between different filter types">
            {{ filterLogic.toUpperCase() }}
          </button>

          <!-- Clear filters -->
          <button v-if="isAnySiteFilterActive" @click="clearSiteFilters()" class="filter-chip" style="color: var(--color-error); border-color: var(--color-error);">
            <svg-icon name="close" :size="12" /> Clear
          </button>

          <!-- Add filtered to terminal -->
          <button v-if="isAnySiteFilterActive && filteredSites.length" @click.stop="addFilteredToTerminal()" class="filter-chip">
            <svg-icon name="terminal" :size="12" /> Add to Terminal
          </button>
        </div>

        <!-- Secondary filters: version/status drill-down -->
        <div v-if="combinedAppliedFilters.length" class="px-4 pb-3">
          <div v-for="f in combinedAppliedFilters" :key="f.name" class="mb-2 rounded-lg p-3" style="background: var(--hover-bg)">
            <div class="text-xs font-semibold mb-2" style="color: var(--text-primary)">{{ f.title || f.name }}</div>
            <div class="flex flex-wrap gap-4">
              <!-- Versions -->
              <div v-if="filterVersions[f.name] && filterVersions[f.name].length" class="flex-1" style="min-width: 200px">
                <div class="flex items-center gap-2 mb-1">
                  <span class="text-[10px] font-semibold uppercase" style="color: var(--text-secondary)">Version</span>
                  <button v-if="f.selected_versions && f.selected_versions.length > 1"
                    @click="filterVersionLogic = filterVersionLogic === 'and' ? 'or' : 'and'; filterSites()"
                    class="text-[10px] px-1.5 py-0.5 rounded font-semibold" style="background: var(--active-bg); color: var(--color-primary); cursor: pointer; border: none;">
                    {{ filterVersionLogic.toUpperCase() }}
                  </button>
                </div>
                <div class="flex flex-wrap gap-1">
                  <button v-for="v in filterVersions[f.name]" :key="v.name"
                    @click="toggleFilterVersion(f, v)"
                    :class="['text-[11px] px-2 py-0.5 rounded-full border transition-colors', f.selected_versions && f.selected_versions.some(sv => sv.name === v.name) ? 'font-semibold' : '']"
                    :style="{
                      borderColor: f.selected_versions && f.selected_versions.some(sv => sv.name === v.name) ? 'var(--color-primary)' : 'var(--border-color)',
                      background: f.selected_versions && f.selected_versions.some(sv => sv.name === v.name) ? 'var(--color-primary)' : 'transparent',
                      color: f.selected_versions && f.selected_versions.some(sv => sv.name === v.name) ? 'white' : 'var(--text-secondary)',
                    }">
                    {{ v.name }} <span class="opacity-60">({{ v.count }})</span>
                  </button>
                </div>
              </div>
              <!-- Statuses -->
              <div v-if="filterStatuses[f.name] && filterStatuses[f.name].length" class="flex-1" style="min-width: 200px">
                <div class="flex items-center gap-2 mb-1">
                  <span class="text-[10px] font-semibold uppercase" style="color: var(--text-secondary)">Status</span>
                  <button v-if="f.selected_statuses && f.selected_statuses.length > 1"
                    @click="filterStatusLogic = filterStatusLogic === 'and' ? 'or' : 'and'; filterSites()"
                    class="text-[10px] px-1.5 py-0.5 rounded font-semibold" style="background: var(--active-bg); color: var(--color-primary); cursor: pointer; border: none;">
                    {{ filterStatusLogic.toUpperCase() }}
                  </button>
                </div>
                <div class="flex flex-wrap gap-1">
                  <button v-for="s in filterStatuses[f.name]" :key="s.name"
                    @click="toggleFilterStatus(f, s)"
                    :class="['text-[11px] px-2 py-0.5 rounded-full border transition-colors', f.selected_statuses && f.selected_statuses.some(ss => ss.name === s.name) ? 'font-semibold' : '']"
                    :style="{
                      borderColor: f.selected_statuses && f.selected_statuses.some(ss => ss.name === s.name) ? 'var(--color-primary)' : 'var(--border-color)',
                      background: f.selected_statuses && f.selected_statuses.some(ss => ss.name === s.name) ? 'var(--color-primary)' : 'transparent',
                      color: f.selected_statuses && f.selected_statuses.some(ss => ss.name === s.name) ? 'white' : 'var(--text-secondary)',
                    }">
                    {{ s.name }} <span class="opacity-60">({{ s.count }})</span>
                  </button>
                </div>
              </div>
              <!-- Loading -->
              <div v-if="filterVersionsLoading || filterStatusesLoading" class="text-xs" style="color: var(--text-secondary)">Loading options...</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Bulk Actions Bar -->
      <div v-if="showBulkActions" class="surface rounded-xl mb-4 px-4 py-3" style="border: 1px solid var(--color-primary); position: sticky; top: 0; z-index: 20;">
        <div class="flex items-center gap-3 flex-wrap">
          <span class="text-xs font-semibold" style="color: var(--color-primary)">{{ selectedSites.length }} selected</span>
          <select v-model="bulkEnv" class="select-field" style="width: auto; padding: 4px 24px 4px 8px; font-size: 0.75rem;">
            <option value="Production">Production</option>
            <option value="Staging">Staging</option>
          </select>
          <div class="flex items-center gap-1 flex-wrap">
            <button @click="bulkAddToTerminal()" class="btn btn-sm btn-ghost" title="Add to Terminal"><svg-icon name="terminal" :size="14" /> Terminal</button>
            <button @click="bulkOpenInBrowser()" class="btn btn-sm btn-ghost" title="Open in Browser"><svg-icon name="externalLink" :size="14" /> Open</button>
            <button @click="bulkSyncSites()" class="btn btn-sm btn-ghost" title="Sync Data"><svg-icon name="sync" :size="14" /> Sync</button>
            <button @click="showBulkHttpsDialog = true" class="btn btn-sm btn-ghost" title="Apply HTTPS"><svg-icon name="lock" :size="14" /> HTTPS</button>
            <button v-if="role === 'administrator'" @click="bulkDeployDefaults()" class="btn btn-sm btn-ghost" title="Deploy Defaults"><svg-icon name="rocket" :size="14" /> Defaults</button>
            <button v-if="role === 'administrator'" @click="showBulkToggleDialog = true" class="btn btn-sm btn-ghost" title="Toggle Status"><svg-icon name="power" :size="14" /> Status</button>
            <button v-if="role === 'administrator'" @click="openBulkAddPlugin()" class="btn btn-sm btn-ghost" title="Add Plugin"><svg-icon name="puzzle" :size="14" /> + Plugin</button>
            <button v-if="role === 'administrator'" @click="openBulkAddTheme()" class="btn btn-sm btn-ghost" title="Add Theme"><svg-icon name="palette" :size="14" /> + Theme</button>
          </div>
          <div class="ml-auto flex items-center gap-2">
            <button @click="selectAllSites()" class="btn btn-sm btn-ghost text-xs">Select All ({{ filteredSites.length }})</button>
            <button @click="clearSelection()" class="btn btn-sm btn-ghost text-xs" style="color: var(--color-error)"><svg-icon name="close" :size="12" /> Clear</button>
          </div>
        </div>
      </div>

      <!-- Bulk HTTPS Dialog -->
      <div v-if="showBulkHttpsDialog" class="dialog-overlay" @click.self="showBulkHttpsDialog = false">
        <div class="dialog-card" style="max-width: 400px;">
          <div class="dialog-card-header">
            <h3 class="text-sm font-semibold" style="color: var(--text-primary)">Apply HTTPS - {{ selectedSites.length }} sites</h3>
            <button @click="showBulkHttpsDialog = false" class="btn-ghost p-1 rounded"><svg-icon name="close" :size="16" /></button>
          </div>
          <div class="dialog-card-body space-y-3">
            <p class="text-xs" style="color: var(--text-secondary)">Apply HTTPS URLs to the selected sites on {{ bulkEnv }} environments.</p>
            <div class="flex gap-2">
              <button @click="bulkApplyHttps(false)" class="btn btn-primary flex-1">https://</button>
              <button @click="bulkApplyHttps(true)" class="btn btn-outline flex-1">https://www.</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Bulk Toggle Status Dialog -->
      <div v-if="showBulkToggleDialog" class="dialog-overlay" @click.self="showBulkToggleDialog = false">
        <div class="dialog-card" style="max-width: 400px;">
          <div class="dialog-card-header">
            <h3 class="text-sm font-semibold" style="color: var(--text-primary)">Toggle Status - {{ selectedSites.length }} sites</h3>
            <button @click="showBulkToggleDialog = false" class="btn-ghost p-1 rounded"><svg-icon name="close" :size="16" /></button>
          </div>
          <div class="dialog-card-body space-y-3">
            <p class="text-xs" style="color: var(--text-secondary)">Change site status for the selected sites.</p>
            <div class="flex gap-2">
              <button @click="bulkToggleStatus('Activate')" class="btn btn-primary flex-1">Activate</button>
              <button @click="bulkToggleStatus('Deactivate')" class="btn btn-outline flex-1" style="color: var(--color-error); border-color: var(--color-error);">Deactivate</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Bulk Add Plugin Dialog -->
      <div v-if="showBulkAddPluginDialog" class="dialog-overlay" @mousedown.self="showBulkAddPluginDialog = false">
        <div class="dialog-card" style="width: 600px; max-height: 80vh; display: flex; flex-direction: column">
          <div class="dialog-header">
            <h3 class="dialog-title">Add Plugin to {{ selectedSites.length }} sites</h3>
            <button @click="showBulkAddPluginDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="x" :size="18" /></button>
          </div>
          <div class="px-4 py-3" style="border-bottom: 1px solid var(--border-color)">
            <div class="flex gap-2">
              <input v-model="bulkPluginSearch" @keyup.enter="searchBulkPlugins()" placeholder="Search WordPress.org plugins..." class="input flex-1" />
              <button @click="searchBulkPlugins()" :disabled="bulkPluginLoading" class="btn btn-sm btn-primary">Search</button>
            </div>
          </div>
          <div style="flex: 1; overflow-y: auto; padding: 0">
            <div v-if="bulkPluginLoading" class="flex justify-center py-8"><div class="animate-spin rounded-full h-6 w-6 border-b-2" style="border-color: var(--color-primary)"></div></div>
            <div v-else-if="!bulkPluginResults.length" class="p-8 text-center text-sm" style="color: var(--text-secondary)">Search for plugins to install.</div>
            <div v-else>
              <div v-for="p in bulkPluginResults" :key="p.slug" class="flex items-center justify-between px-4 py-3" style="border-bottom: 1px solid var(--border-color)">
                <div class="flex-1 min-w-0 mr-3">
                  <div class="text-sm font-medium" style="color: var(--text-primary)">{{ p.name }}</div>
                  <div class="text-xs truncate" style="color: var(--text-secondary)">{{ p.short_description || p.slug }}</div>
                </div>
                <button @click="bulkInstallPlugin(p)" :disabled="bulkInstalling[p.slug]" class="btn btn-sm btn-outline" style="flex-shrink: 0">
                  {{ bulkInstalling[p.slug] ? 'Installing...' : 'Install' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Bulk Add Theme Dialog -->
      <div v-if="showBulkAddThemeDialog" class="dialog-overlay" @mousedown.self="showBulkAddThemeDialog = false">
        <div class="dialog-card" style="width: 600px; max-height: 80vh; display: flex; flex-direction: column">
          <div class="dialog-header">
            <h3 class="dialog-title">Add Theme to {{ selectedSites.length }} sites</h3>
            <button @click="showBulkAddThemeDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="x" :size="18" /></button>
          </div>
          <div class="px-4 py-3" style="border-bottom: 1px solid var(--border-color)">
            <div class="flex gap-2">
              <input v-model="bulkThemeSearch" @keyup.enter="searchBulkThemes()" placeholder="Search WordPress.org themes..." class="input flex-1" />
              <button @click="searchBulkThemes()" :disabled="bulkThemeLoading" class="btn btn-sm btn-primary">Search</button>
            </div>
          </div>
          <div style="flex: 1; overflow-y: auto; padding: 0">
            <div v-if="bulkThemeLoading" class="flex justify-center py-8"><div class="animate-spin rounded-full h-6 w-6 border-b-2" style="border-color: var(--color-primary)"></div></div>
            <div v-else-if="!bulkThemeResults.length" class="p-8 text-center text-sm" style="color: var(--text-secondary)">Search for themes to install.</div>
            <div v-else>
              <div v-for="t in bulkThemeResults" :key="t.slug" class="flex items-center justify-between px-4 py-3" style="border-bottom: 1px solid var(--border-color)">
                <div class="flex items-center gap-3 flex-1 min-w-0 mr-3">
                  <img v-if="t.screenshot_url" :src="t.screenshot_url" class="rounded" style="width: 48px; height: 36px; object-fit: cover" />
                  <div class="min-w-0">
                    <div class="text-sm font-medium" style="color: var(--text-primary)">{{ t.name }}</div>
                    <div class="text-xs truncate" style="color: var(--text-secondary)">{{ t.slug }}</div>
                  </div>
                </div>
                <button @click="bulkInstallTheme(t)" :disabled="bulkInstalling[t.slug]" class="btn btn-sm btn-outline" style="flex-shrink: 0">
                  {{ bulkInstalling[t.slug] ? 'Installing...' : 'Install' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- New Site Dialog -->
      <div v-if="showNewSiteDialog" class="dialog-overlay" @click.self="showNewSiteDialog = false">
        <div class="dialog-card" style="max-width: 640px; max-height: 90vh; display: flex; flex-direction: column;">
          <div class="dialog-card-header">
            <h3 class="text-sm font-semibold" style="color: var(--text-primary)">New Site</h3>
            <button @click="showNewSiteDialog = false" class="btn-ghost p-1 rounded"><svg-icon name="close" :size="16" /></button>
          </div>
          <div class="dialog-card-body space-y-4" style="overflow-y: auto; flex: 1;">
            <!-- Basic fields -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Site Name</label>
                <input v-model="newSite.name" type="text" class="input-field" placeholder="My New Site" />
              </div>
              <div>
                <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Domain</label>
                <input v-model="newSite.domain" type="text" class="input-field" placeholder="example.com" />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div v-if="providers.length">
                <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Provider</label>
                <select v-model="newSite.provider_id" class="select-field">
                  <option value="">None</option>
                  <option v-for="p in providers" :key="p.provider_id || p.id" :value="p.provider_id || p.id">{{ p.name }}</option>
                </select>
              </div>
              <div v-if="newSiteKeys.length">
                <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">SSH Key Override</label>
                <select v-model="newSite.key" class="select-field">
                  <option :value="null">Default</option>
                  <option v-for="k in newSiteKeys" :key="k.key_id || k.id" :value="k.key_id || k.id">{{ k.title }}</option>
                </select>
              </div>
            </div>

            <!-- Account assignment (admin) -->
            <div v-if="role === 'administrator' && newSiteAccounts.length">
              <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Assign to Accounts</label>
              <div class="flex flex-wrap gap-1">
                <button v-for="a in newSiteAccounts" :key="a.account_id || a.id"
                  @click="toggleNewSiteAccount(a.account_id || a.id)"
                  :class="['filter-chip', newSite.shared_with.includes(a.account_id || a.id) && 'active']"
                  style="font-size: 0.6875rem;">
                  {{ a.name }}
                </button>
              </div>
            </div>

            <!-- Environments -->
            <div v-for="(env, ei) in newSite.environments" :key="ei" class="rounded-lg p-3" style="border: 1px solid var(--border-color);">
              <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold" style="color: var(--text-primary)">{{ env.environment }} Environment</span>
                <button v-if="env.environment === 'Staging'" @click="removeStagingEnv()" class="btn btn-sm btn-ghost" style="color: var(--color-error); font-size: 0.6875rem;"><svg-icon name="close" :size="12" /> Remove</button>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs mb-1" style="color: var(--text-secondary)">Address</label>
                  <input v-model="env.address" class="input-field" placeholder="IP or hostname" />
                </div>
                <div>
                  <label class="block text-xs mb-1" style="color: var(--text-secondary)">Username</label>
                  <input v-model="env.username" class="input-field" placeholder="SSH/SFTP user" />
                </div>
                <div>
                  <label class="block text-xs mb-1" style="color: var(--text-secondary)">Password</label>
                  <input v-model="env.password" type="password" class="input-field" placeholder="Optional" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs mb-1" style="color: var(--text-secondary)">Protocol</label>
                    <select v-model="env.protocol" class="select-field">
                      <option value="sftp">SFTP</option>
                      <option value="ssh">SSH</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-xs mb-1" style="color: var(--text-secondary)">Port</label>
                    <input v-model="env.port" type="text" class="input-field" placeholder="2222" />
                  </div>
                </div>
                <div class="col-span-2">
                  <label class="block text-xs mb-1" style="color: var(--text-secondary)">Home Directory</label>
                  <input v-model="env.home_directory" class="input-field" placeholder="/home/user/public" />
                </div>
              </div>
            </div>
            <button v-if="newSite.environments.length < 2" @click="addStagingEnv()" class="btn btn-sm btn-ghost"><svg-icon name="plus" :size="14" /> Add Staging Environment</button>

            <!-- Environment Variables (collapsible) -->
            <div>
              <button @click="newSite.showAdvanced = !newSite.showAdvanced" class="btn btn-sm btn-ghost text-xs">
                <svg-icon :name="newSite.showAdvanced ? 'chevronUp' : 'chevronDown'" :size="12" /> Environment Variables
              </button>
              <div v-if="newSite.showAdvanced" class="mt-2 space-y-2">
                <div v-for="(v, vi) in newSite.environment_vars" :key="vi" class="flex items-center gap-2">
                  <input v-model="v.key" class="input-field flex-1" placeholder="Key" style="font-size: 0.75rem;" />
                  <input v-model="v.value" class="input-field flex-1" placeholder="Value" style="font-size: 0.75rem;" />
                  <button @click="removeEnvVar(vi)" class="btn-ghost p-1"><svg-icon name="close" :size="14" style="color: var(--color-error);" /></button>
                </div>
                <button @click="addEnvVar()" class="btn btn-sm btn-ghost text-xs"><svg-icon name="plus" :size="12" /> Add Variable</button>
              </div>
            </div>

            <div v-if="newSite.errors" class="rounded-lg p-3 text-sm" style="background: color-mix(in srgb, var(--color-error) 15%, transparent); color: var(--color-error);">{{ newSite.errors }}</div>
          </div>
          <div class="dialog-card-footer">
            <button @click="showNewSiteDialog = false" class="btn btn-ghost">Cancel</button>
            <button @click="createSite()" :disabled="newSite.loading || !newSite.name" class="btn btn-primary">{{ newSite.loading ? 'Creating...' : 'Create Site' }}</button>
          </div>
        </div>
      </div>

      <!-- Error state -->
      <div v-if="sitesError" class="surface rounded-xl p-8 text-center">
        <p class="text-sm mb-3" style="color: var(--color-error)">{{ sitesError }}</p>
        <button @click="retrySites()" class="btn btn-sm btn-primary">Retry</button>
      </div>

      <!-- Loading -->
      <div v-else-if="sitesLoading" class="flex justify-center py-16"><div class="animate-spin rounded-full h-8 w-8 border-b-2" style="border-color: var(--color-primary)"></div></div>

      <!-- Cards View (all environments per site) -->
      <div v-else-if="viewMode === 'cards'" class="space-y-3">
        <div v-if="!filteredSites.length" class="surface rounded-xl p-8 text-center text-sm" style="color: var(--text-secondary)">No sites found.</div>
        <div v-for="site in paginatedSites" :key="site.site_id" class="site-card">
          <!-- Site header -->
          <div class="site-card-header">
            <input type="checkbox" :checked="isSiteSelected(site.site_id)" @click.stop="toggleSiteSelection(site)" style="accent-color: var(--color-primary); cursor: pointer;" />
            <svg-icon name="sites" :size="16" style="opacity: 0.5" />
            <router-link :to="'/sites/' + site.site_id">{{ site.name }}</router-link>
          </div>
          <!-- Environment rows -->
          <div v-for="(env, index) in getVisibleEnvironments(site)" :key="env.environment_id" class="site-card-env">
            <!-- Screenshot -->
            <router-link :to="'/sites/' + site.site_id" class="flex-shrink-0">
              <img v-if="env.screenshot_base" :src="getScreenshotUrl(site, env, 800)" class="site-screenshot" loading="lazy" @error="$event.target.style.display='none'" />
              <div v-else class="site-screenshot-placeholder"><svg-icon name="monitor" :size="24" style="color: var(--text-secondary); opacity: 0.4" /></div>
            </router-link>
            <!-- Info -->
            <div class="flex-1 min-w-0" style="min-width: 200px;">
              <div class="flex items-center gap-2 mb-1 flex-wrap">
                <span :class="['badge', env.environment === 'Production' ? 'badge-success' : 'badge-warning']" style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase;">{{ env.environment }}</span>
                <span v-if="env.core" class="badge badge-default" style="font-size: 0.6875rem;">WP {{ env.core }}</span>
              </div>
              <div class="mb-2">
                <a v-if="env.home_url" :href="env.home_url" target="_blank" class="text-xs inline-flex items-center gap-1" style="color: var(--text-secondary); text-decoration: none;">
                  {{ env.home_url }} <svg-icon name="externalLink" :size="11" />
                </a>
                <span v-else class="text-xs italic" style="color: var(--text-secondary)">No URL detected</span>
              </div>
              <div class="flex items-center gap-5 flex-wrap">
                <div class="site-metric" title="Yearly Visits">
                  <svg-icon name="chart" :size="14" />
                  <span class="site-metric-value">{{ formatLargeNumbers(env.visits) }}</span> visits
                </div>
                <div class="site-metric" title="Storage">
                  <svg-icon name="database" :size="14" />
                  <span class="site-metric-value">{{ formatStorage(env.storage) }}</span>
                </div>
                <div v-if="env.subsite_count > 0" class="site-metric" title="Subsites">
                  <svg-icon name="globe" :size="14" />
                  <span class="site-metric-value">{{ env.subsite_count }}</span> subsites
                </div>
              </div>
            </div>
            <!-- Actions -->
            <div class="flex items-center gap-2 flex-shrink-0">
              <router-link :to="'/sites/' + site.site_id" class="btn btn-sm btn-primary" style="text-decoration: none;">Manage</router-link>
              <button @click.stop="magicLogin(site.site_id, env)" class="btn btn-sm btn-outline" :disabled="env.isLoggingIn">
                <svg-icon name="login" :size="14" />{{ env.isLoggingIn ? '...' : 'WP Login' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Table View (compact rows, production env) -->
      <div v-else-if="viewMode === 'table'" class="surface rounded-xl">
        <div v-if="!filteredSites.length" class="p-8 text-center text-sm" style="color: var(--text-secondary)">No sites found.</div>
        <div v-for="site in paginatedSites" :key="site.site_id" class="site-row" @click="goToSite(site.site_id)">
          <!-- Selection checkbox -->
          <input type="checkbox" :checked="isSiteSelected(site.site_id)" @click.stop="toggleSiteSelection(site)" style="accent-color: var(--color-primary); cursor: pointer; flex-shrink: 0;" />
          <!-- Status dot -->
          <div class="status-dot online" title="Online"></div>
          <!-- Thumbnail -->
          <img v-if="getPrimaryEnv(site).screenshot_base" :src="getScreenshotUrl(site, getPrimaryEnv(site), 100)" class="site-row-thumb" loading="lazy" @error="$event.target.style.display='none'" />
          <div v-else class="site-row-thumb-placeholder"><svg-icon name="globe" :size="16" style="color: var(--text-secondary); opacity: 0.4" /></div>
          <!-- Name & URL -->
          <div class="flex-1 min-w-0" style="min-width: 140px;">
            <div class="text-[13px] font-semibold truncate" style="color: var(--text-primary)">{{ site.name }}</div>
            <a v-if="getPrimaryEnv(site).home_url" :href="getPrimaryEnv(site).home_url" target="_blank" @click.stop class="text-[11px] truncate block" style="color: var(--text-secondary); text-decoration: none;">
              {{ getPrimaryEnv(site).home_url.replace(/^https?:\\/\\//, '') }} <svg-icon name="externalLink" :size="9" style="display: inline; vertical-align: middle; opacity: 0.6;" />
            </a>
          </div>
          <!-- WP version -->
          <div class="hidden md:block" style="width: 64px; text-align: center;">
            <span v-if="getPrimaryEnv(site).core" class="badge badge-default" style="font-size: 0.6875rem;">{{ getPrimaryEnv(site).core }}</span>
          </div>
          <!-- Metrics -->
          <div class="site-metrics hidden lg:flex items-center gap-5" style="min-width: 180px;">
            <div class="site-metric" title="Yearly Visits">
              <svg-icon name="chart" :size="13" />
              <span class="site-metric-value">{{ formatLargeNumbers(getPrimaryEnv(site).visits) }}</span>
            </div>
            <div class="site-metric" title="Storage">
              <svg-icon name="database" :size="13" />
              <span class="site-metric-value">{{ formatStorage(getPrimaryEnv(site).storage) }}</span>
            </div>
          </div>
          <!-- Staging indicator -->
          <div class="hidden md:block" style="width: 70px; text-align: center;">
            <span v-if="getStagingEnvs(site).length" class="badge badge-warning" style="font-size: 0.625rem; text-transform: uppercase;">Staging</span>
          </div>
          <!-- Actions -->
          <div class="site-actions flex items-center gap-1 flex-shrink-0">
            <button @click.stop="magicLogin(site.site_id, getPrimaryEnv(site), $event)" class="icon-btn" :disabled="getPrimaryEnv(site).isLoggingIn" title="WP Admin">
              <svg-icon name="login" :size="16" />
            </button>
            <router-link :to="'/sites/' + site.site_id" @click.stop class="icon-btn" title="Manage" style="text-decoration: none; color: inherit;">
              <svg-icon name="cog" :size="16" />
            </router-link>
          </div>
        </div>
      </div>

      <!-- Grid View -->
      <div v-else-if="viewMode === 'grid'">
        <div v-if="!filteredSites.length" class="surface rounded-xl p-8 text-center text-sm" style="color: var(--text-secondary)">No sites found.</div>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
          <div v-for="site in paginatedSites" :key="site.site_id" class="grid-card" @click="goToSite(site.site_id)" style="position: relative;">
            <input type="checkbox" :checked="isSiteSelected(site.site_id)" @click.stop="toggleSiteSelection(site)" style="accent-color: var(--color-primary); cursor: pointer; position: absolute; top: 8px; left: 8px; z-index: 2;" />
            <img v-if="getVisibleEnvironments(site)[0] && getVisibleEnvironments(site)[0].screenshot_base" :src="getScreenshotUrl(site, getVisibleEnvironments(site)[0], 800)" loading="lazy" @error="$event.target.style.display='none'; $event.target.nextElementSibling && ($event.target.nextElementSibling.style.display='flex')" />
            <div class="grid-card-placeholder" v-if="!getVisibleEnvironments(site)[0] || !getVisibleEnvironments(site)[0].screenshot_base"><svg-icon name="monitor" :size="32" style="color: var(--text-secondary); opacity: 0.3" /></div>
            <div class="grid-card-overlay">
              <div class="text-sm font-semibold truncate">{{ site.name }}</div>
              <span v-if="getVisibleEnvironments(site)[0]" class="text-xs opacity-75">{{ getVisibleEnvironments(site)[0].environment }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="totalSitePages > 1" class="flex items-center justify-center gap-3 py-4">
        <button @click="sitePage--" :disabled="sitePage <= 1" class="btn btn-sm btn-outline">Previous</button>
        <span class="text-sm" style="color: var(--text-secondary)">Page {{ sitePage }} of {{ totalSitePages }} <span class="text-xs">({{ filteredSites.length }} sites)</span></span>
        <button @click="sitePage++" :disabled="sitePage >= totalSitePages" class="btn btn-sm btn-outline">Next</button>
      </div>
    </div>
  `,
});

// ─── View: DomainsView ───────────────────────────────────────────────────────
const DomainsView = defineComponent({
  components: { DataTable },
  setup() {
    const { filteredDomains, domainsLoading, domainSearch, fetchDomains } = useDomains();
    const router = useRouter();
    const { showNotify } = useNotify();
    const role = userRole;
    const showNewDomainDialog = ref(false);
    const newDomainName = ref('');
    const newDomainAccountId = ref('');
    const newDomainCreateZone = ref(false);
    const newDomainSaving = ref(false);

    onMounted(() => { fetchDomains(); });

    const headers = [
      { title: 'Name', value: 'name' },
      { title: 'DNS', value: 'remote_id', width: '88px' },
      { title: 'Registration', value: 'provider_id', width: '120px' },
    ];

    function onRowClick(event, { item }) {
      router.push('/domains/' + item.domain_id);
    }
    function createDomain() {
      if (!newDomainName.value) return;
      newDomainSaving.value = true;
      api.post('/wp-json/captaincore/v1/domains', { name: newDomainName.value, account_id: newDomainAccountId.value, create_dns_zone: newDomainCreateZone.value })
        .then(() => { showNotify('Domain created', 'success'); showNewDomainDialog.value = false; newDomainName.value = ''; domainsFetched = false; fetchDomains(); })
        .catch(() => showNotify('Failed to create domain', 'error'))
        .finally(() => { newDomainSaving.value = false; });
    }

    return { filteredDomains, domainsLoading, domainSearch, headers, onRowClick, role, showNewDomainDialog, newDomainName, newDomainAccountId, newDomainCreateZone, newDomainSaving, createDomain };
  },
  template: `
    <div class="surface rounded-xl">
      <div class="flex items-center justify-between px-4 py-3" style="border-bottom: 1px solid var(--border-color)">
        <h2 class="text-sm font-semibold" style="color: var(--text-primary)">
          <span v-if="domainsLoading">Loading domains...</span>
          <span v-else>{{ filteredDomains.length }} domains</span>
        </h2>
        <div class="flex items-center gap-2">
          <div class="search-wrapper">
            <svg-icon name="search" :size="16" class="search-icon" />
            <input v-model="domainSearch" type="text" placeholder="Search domains..." class="input-field" style="width: 220px" />
          </div>
          <button v-if="role === 'administrator'" @click="showNewDomainDialog = true" class="btn btn-sm btn-primary"><svg-icon name="plus" :size="14" /> New</button>
        </div>
      </div>
      <data-table
        :headers="headers"
        :items="filteredDomains"
        :loading="domainsLoading"
        :clickable="true"
        @click:row="onRowClick"
      >
        <template #item.remote_id="{ value }">
          <svg-icon v-if="value != null && value !== ''" name="check" :size="18" style="color: var(--color-success)" />
        </template>
        <template #item.provider_id="{ value }">
          <svg-icon v-if="value != null && value !== ''" name="check" :size="18" style="color: var(--color-success)" />
        </template>
      </data-table>
      <!-- New Domain Dialog -->
      <div v-if="showNewDomainDialog" class="dialog-overlay" @click.self="showNewDomainDialog = false">
        <div class="dialog-card" style="max-width: 480px;">
          <div class="dialog-card-header"><h3 class="text-sm font-semibold" style="color: var(--text-primary)">Add Domain</h3><button @click="showNewDomainDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="close" :size="16" /></button></div>
          <div class="dialog-card-body space-y-4">
            <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Domain Name</label><input v-model="newDomainName" class="input-field" placeholder="example.com" @keyup.enter="createDomain" /></div>
            <div class="flex items-center gap-2"><button :class="['toggle', newDomainCreateZone && 'on']" @click="newDomainCreateZone = !newDomainCreateZone"></button><span class="text-sm" style="color: var(--text-primary)">Create DNS Zone</span></div>
          </div>
          <div class="dialog-card-footer"><button @click="showNewDomainDialog = false" class="btn btn-ghost">Cancel</button><button @click="createDomain" :disabled="newDomainSaving || !newDomainName" class="btn btn-primary">{{ newDomainSaving ? 'Creating...' : 'Create' }}</button></div>
        </div>
      </div>
    </div>
  `,
});

// ─── View: AccountsView ──────────────────────────────────────────────────────
const AccountsView = defineComponent({
  components: { DataTable },
  setup() {
    const { filteredAccounts, accountsLoading, accountSearch, fetchAccounts } = useAccounts();
    const router = useRouter();
    const { showNotify } = useNotify();
    const role = userRole;
    const showNewAccountDialog = ref(false);
    const newAccountName = ref('');
    const newAccountSaving = ref(false);

    onMounted(() => { fetchAccounts(); });

    const headers = [
      { title: 'Name', value: 'name' },
      { title: 'Users', value: 'metrics.users', width: '100px' },
      { title: 'Sites', value: 'metrics.sites', width: '100px' },
      { title: 'Domains', value: 'metrics.domains', width: '100px' },
    ];

    function onRowClick(event, { item }) {
      router.push('/accounts/' + item.account_id);
    }
    function createAccount() {
      if (!newAccountName.value) return;
      newAccountSaving.value = true;
      api.post('/wp-json/captaincore/v1/accounts', { name: newAccountName.value })
        .then(r => {
          showNotify('Account created', 'success');
          showNewAccountDialog.value = false;
          newAccountName.value = '';
          accountsFetched = false;
          fetchAccounts();
        })
        .catch(() => showNotify('Failed to create account', 'error'))
        .finally(() => { newAccountSaving.value = false; });
    }

    return { filteredAccounts, accountsLoading, accountSearch, headers, onRowClick, role, showNewAccountDialog, newAccountName, newAccountSaving, createAccount };
  },
  template: `
    <div class="surface rounded-xl">
      <div class="flex items-center justify-between px-4 py-3" style="border-bottom: 1px solid var(--border-color)">
        <h2 class="text-sm font-semibold" style="color: var(--text-primary)">
          <span v-if="accountsLoading">Loading accounts...</span>
          <span v-else>{{ filteredAccounts.length }} accounts</span>
        </h2>
        <div class="flex items-center gap-2">
          <div class="search-wrapper">
            <svg-icon name="search" :size="16" class="search-icon" />
            <input v-model="accountSearch" type="text" placeholder="Search accounts..." class="input-field" style="width: 220px" />
          </div>
          <button v-if="role === 'administrator'" @click="showNewAccountDialog = true" class="btn btn-sm btn-primary"><svg-icon name="plus" :size="14" /> New</button>
        </div>
      </div>
      <data-table
        :headers="headers"
        :items="filteredAccounts"
        :loading="accountsLoading"
        :clickable="true"
        @click:row="onRowClick"
      >
        <template #item.metrics.users="{ value }">
          <span v-if="value != null && value !== ''">{{ value }}</span>
        </template>
        <template #item.metrics.sites="{ value }">
          <span v-if="value != null && value !== ''">{{ value }}</span>
        </template>
        <template #item.metrics.domains="{ value }">
          <span v-if="value != null && value !== ''">{{ value }}</span>
        </template>
      </data-table>
      <!-- New Account Dialog -->
      <div v-if="showNewAccountDialog" class="dialog-overlay" @click.self="showNewAccountDialog = false">
        <div class="dialog-card" style="max-width: 420px;">
          <div class="dialog-card-header">
            <h3 class="text-sm font-semibold" style="color: var(--text-primary)">New Account</h3>
            <button @click="showNewAccountDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="close" :size="16" /></button>
          </div>
          <div class="dialog-card-body">
            <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Account Name</label>
            <input v-model="newAccountName" class="input-field" placeholder="Enter account name..." @keyup.enter="createAccount" />
          </div>
          <div class="dialog-card-footer">
            <button @click="showNewAccountDialog = false" class="btn btn-ghost">Cancel</button>
            <button @click="createAccount" :disabled="newAccountSaving || !newAccountName" class="btn btn-primary">{{ newAccountSaving ? 'Creating...' : 'Create' }}</button>
          </div>
        </div>
      </div>
    </div>
  `,
});

// ─── View: ProfileView ───────────────────────────────────────────────────────
const ProfileView = defineComponent({
  setup() {
    const { showNotify } = useNotify();
    const profile = reactive({
      display_name: currentUser.display_name,
      first_name:   currentUser.first_name,
      last_name:    currentUser.last_name,
      email:        currentUser.email,
      login:        currentUser.login,
      new_password: '',
      errors:       [],
      success:      '',
      tfa_enabled:  currentUser.tfa_enabled,
      email_subscriber: currentUser.email_subscriber,
    });

    const gravatar = computed(() => {
      return 'https://www.gravatar.com/avatar/' + md5(profile.email.trim().toLowerCase()) + '?s=80&d=mp';
    });

    function updateProfile() {
      profile.errors = [];
      profile.success = '';
      api.put('/wp-json/captaincore/v1/me/profile', profile)
        .then(response => {
          if (response.data.errors) {
            profile.errors = response.data.errors;
            return;
          }
          profile.success = 'Account updated.';
          currentUser.display_name = response.data.profile.display_name;
          profile.new_password = '';
          showNotify('Account updated.', 'success');
        })
        .catch(err => {
          console.error(err);
          profile.errors = ['An error occurred.'];
        });
    }

    const { theme } = useTheme();
    function setTheme(val) {
      theme.value = val;
      applyTheme(val);
      localStorage.setItem('captaincore-theme', val);
    }

    // 2FA state
    const tfaActivating = ref(false);
    const tfaUri = ref('');
    const tfaToken = ref('');
    const tfaCode = ref('');
    const tfaLoading = ref(false);

    function enableTFA() {
      tfaLoading.value = true;
      api.get('/wp-json/captaincore/v1/me/tfa_activate')
        .then(r => {
          tfaUri.value = r.data;
          tfaToken.value = typeof r.data === 'string' ? r.data.split('=').pop() : '';
          tfaActivating.value = true;
          nextTick(() => {
            const el = document.getElementById('tfa_qr_code');
            if (el && typeof kjua !== 'undefined') {
              el.innerHTML = '';
              el.appendChild(kjua({ crisp: false, render: 'canvas', text: r.data, size: 150 }));
            }
          });
        })
        .catch(() => showNotify('Failed to start 2FA setup', 'error'))
        .finally(() => { tfaLoading.value = false; });
    }

    function activateTFA() {
      tfaLoading.value = true;
      api.post('/wp-json/captaincore/v1/me/tfa_validate', { token: tfaCode.value })
        .then(r => {
          if (r.data) {
            tfaActivating.value = false;
            profile.tfa_enabled = true;
            currentUser.tfa_enabled = 1;
            tfaCode.value = '';
            showNotify('Two-Factor Authentication enabled.', 'success');
          } else {
            showNotify('Invalid code. Please try again.', 'error');
          }
        })
        .catch(() => showNotify('Verification failed.', 'error'))
        .finally(() => { tfaLoading.value = false; });
    }

    function disableTFA() {
      if (!confirm('Disable Two-Factor Authentication?')) return;
      tfaLoading.value = true;
      api.get('/wp-json/captaincore/v1/me/tfa_deactivate')
        .then(r => {
          if (r.data) {
            profile.tfa_enabled = false;
            currentUser.tfa_enabled = 0;
            tfaActivating.value = false;
            showNotify('Two-Factor Authentication disabled.', 'success');
          }
        })
        .catch(() => showNotify('Failed to disable 2FA.', 'error'))
        .finally(() => { tfaLoading.value = false; });
    }

    function cancelTFA() {
      tfaActivating.value = false;
      tfaUri.value = '';
      tfaToken.value = '';
      tfaCode.value = '';
      const el = document.getElementById('tfa_qr_code');
      if (el) el.innerHTML = '';
    }

    function copyTfaToken() {
      navigator.clipboard.writeText(tfaToken.value).then(() => showNotify('Token copied', 'success'));
    }

    return { profile, gravatar, updateProfile, theme, setTheme, tfaActivating, tfaUri, tfaToken, tfaCode, tfaLoading, enableTFA, activateTFA, disableTFA, cancelTFA, copyTfaToken };
  },
  template: `
    <div class="surface rounded-xl mx-auto" style="max-width: 700px;">
      <div class="px-4 py-3" style="border-bottom: 1px solid var(--border-color)">
        <h2 class="text-sm font-semibold" style="color: var(--text-primary)">Edit Profile</h2>
      </div>
      <div class="p-6">
        <div class="flex items-center gap-3 mb-6 p-3 rounded-lg" style="background: var(--hover-bg)">
          <img :src="gravatar" class="w-10 h-10 rounded-md" />
          <div>
            <div class="text-sm font-medium" style="color: var(--text-primary)">{{ profile.display_name }}</div>
            <a href="https://gravatar.com" target="_blank" class="text-xs underline" style="color: var(--text-secondary)">Edit thumbnail with Gravatar</a>
          </div>
        </div>

        <div class="space-y-4">
          <div>
            <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Display Name</label>
            <input v-model="profile.display_name" type="text" class="input-field" />
          </div>
          <div>
            <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Email</label>
            <input v-model="profile.email" type="email" class="input-field" />
          </div>
          <div>
            <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">New Password</label>
            <input v-model="profile.new_password" type="password" class="input-field" placeholder="Leave empty to keep current password" />
          </div>
          <div>
            <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Theme</label>
            <select :value="theme" @change="setTheme($event.target.value)" class="select-field">
              <option value="light">Light</option>
              <option value="dark">Dark</option>
            </select>
          </div>
        </div>

        <div v-for="error in profile.errors" class="rounded-lg p-3 mt-4 text-sm" style="background: color-mix(in srgb, var(--color-error) 15%, transparent); color: var(--color-error);">{{ error }}</div>
        <div v-if="profile.success" class="rounded-lg p-3 mt-4 text-sm" style="background: color-mix(in srgb, var(--color-success) 15%, transparent); color: var(--color-success);">{{ profile.success }}</div>

        <div class="mt-6">
          <button @click="updateProfile" class="btn btn-primary">Save Account</button>
        </div>

        <!-- Two-Factor Authentication -->
        <div class="mt-8 pt-6" style="border-top: 1px solid var(--border-color);">
          <h3 class="text-sm font-semibold mb-4" style="color: var(--text-primary)">Two-Factor Authentication</h3>

          <div v-if="profile.tfa_enabled && !tfaActivating">
            <div class="flex items-center gap-2 mb-3">
              <span class="badge badge-success">Enabled</span>
              <span class="text-xs" style="color: var(--text-secondary)">Two-factor authentication is active on your account.</span>
            </div>
            <button @click="disableTFA()" :disabled="tfaLoading" class="btn btn-sm btn-outline" style="color: var(--color-error); border-color: var(--color-error);">
              {{ tfaLoading ? 'Disabling...' : 'Disable 2FA' }}
            </button>
          </div>

          <div v-else-if="!tfaActivating">
            <p class="text-xs mb-3" style="color: var(--text-secondary)">Add an extra layer of security to your account by enabling two-factor authentication.</p>
            <button @click="enableTFA()" :disabled="tfaLoading" class="btn btn-sm btn-primary">
              {{ tfaLoading ? 'Setting up...' : 'Enable 2FA' }}
            </button>
          </div>

          <!-- 2FA Setup -->
          <div v-if="tfaActivating" class="rounded-lg p-4 mt-3" style="border: 1px solid var(--border-color);">
            <div class="flex gap-4 items-start flex-wrap">
              <div class="flex-1" style="min-width: 200px;">
                <p class="text-xs mb-3" style="color: var(--text-secondary)">
                  Scan the QR code with your authenticator app and enter the 6-digit code to verify.
                </p>
                <p class="text-xs mb-3" style="color: var(--text-secondary)">
                  Or use this <a :href="tfaUri" target="_blank" class="underline" style="color: var(--color-primary);">setup link</a> or
                  <a href="#" @click.prevent="copyTfaToken()" class="underline" style="color: var(--color-primary);">copy token</a>.
                </p>
                <div>
                  <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Verification Code</label>
                  <input v-model="tfaCode" type="text" maxlength="6" placeholder="000000" class="input-field" style="max-width: 160px; letter-spacing: 4px; font-size: 1rem;" />
                </div>
                <div class="flex gap-2 mt-3">
                  <button @click="activateTFA()" :disabled="tfaLoading || tfaCode.length < 6" class="btn btn-sm btn-primary">{{ tfaLoading ? 'Verifying...' : 'Activate' }}</button>
                  <button @click="cancelTFA()" class="btn btn-sm btn-ghost">Cancel</button>
                </div>
              </div>
              <div id="tfa_qr_code" class="flex-shrink-0" style="min-width: 150px; min-height: 150px; display: flex; align-items: center; justify-content: center;"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  `,
});

// ─── Reusable: TabBar ────────────────────────────────────────────────────────
const TabBar = defineComponent({
  props: {
    tabs: { type: Array, required: true },
    modelValue: { type: String, required: true },
  },
  emits: ['update:modelValue'],
  template: `
    <div class="tab-bar">
      <button v-for="tab in tabs" :key="tab.key"
        :class="['tab-item', modelValue === tab.key && 'active']"
        @click="$emit('update:modelValue', tab.key)">{{ tab.label }}</button>
    </div>
  `,
});

// ─── Helpers ─────────────────────────────────────────────────────────────────
function formatStorage(bytes) {
  if (!bytes) return '—';
  const n = Number(bytes);
  if (isNaN(n)) return '—';
  if (n >= 1073741824) return (n / 1073741824).toFixed(2) + ' GB';
  if (n >= 1048576) return (n / 1048576).toFixed(0) + ' MB';
  return n + ' B';
}
function formatMoney(v) { return '$' + Number(v || 0).toFixed(2); }
function doCopy(text, notify) {
  if (navigator.clipboard) navigator.clipboard.writeText(text);
  if (notify) notify('Copied!', 'success');
}

// ─── View: SiteDetailView ────────────────────────────────────────────────────
const SiteDetailView = defineComponent({
  components: { TabBar, DataTable },
  setup() {
    const route = useRoute();
    const router = useRouter();
    const { showNotify } = useNotify();
    const site = ref(null);
    const environments = ref([]);
    const envIndex = ref(0);
    const selectedEnv = computed(() => environments.value[envIndex.value] || null);
    const details = ref({});
    const loading = ref(true);
    const activeTab = ref('info');
    const role = userRole;
    const syncing = ref(false);
    const deleting = ref(false);
    const showDeleteConfirm = ref(false);

    // Edit site state
    const showEditSiteDialog = ref(false);
    const editSiteData = reactive({
      site_id: '', name: '', site: '', provider: '',
      shared_with: [], key: null,
      environments: [],
      environment_vars: [],
      showAdvanced: false,
    });
    const editSiteSaving = ref(false);
    const editAccounts = ref([]);
    const editAccountsLoaded = ref(false);
    const editKeys = ref([]);
    const editKeysLoaded = ref(false);

    // PHPMyAdmin state
    const fetchingPhpmyadmin = ref(false);

    // Plugin/Theme management
    const showAddPluginDialog = ref(false);
    const showAddThemeDialog = ref(false);
    const pluginSearch = ref('');
    const themeSearch = ref('');
    const pluginSearchResults = ref([]);
    const themeSearchResults = ref([]);
    const pluginSearchLoading = ref(false);
    const themeSearchLoading = ref(false);
    const addonActionLoading = ref('');

    // Backup/Snapshot state
    const backups = ref([]);
    const backupsLoading = ref(false);
    const backupsLoaded = ref(false);
    const quicksaves = ref([]);
    const quicksavesLoading = ref(false);
    const quicksavesLoaded = ref(false);
    const quicksaveSearch = ref('');
    const expandedQuicksave = ref(null);
    const quicksaveFiles = ref([]);
    const quicksaveFilesFiltered = ref([]);
    const quicksaveFilesLoading = ref(false);
    const quicksaveFileSearch = ref('');
    const showFileDiffDialog = ref(false);
    const fileDiff = reactive({ fileName: '', response: '', loading: false, quicksave: null });
    const snapshots = ref([]);
    const snapshotsLoading = ref(false);
    const snapshotsLoaded = ref(false);
    const backupSubTab = ref('backups');

    // Timeline state (admin)
    const timelineLogs = ref([]);
    const timelineLoading = ref(false);
    const timelineLoaded = ref(false);

    const tabs = computed(() => {
      const t = [
        { key: 'info', label: 'Info' },
        { key: 'addons', label: 'Addons' },
        { key: 'users', label: 'Users' },
        { key: 'updates', label: 'Updates' },
        { key: 'backups', label: 'Backups' },
        { key: 'stats', label: 'Stats' },
        { key: 'logs', label: 'Logs' },
        { key: 'scripts', label: 'Scripts' },
      ];
      if (role.value === 'administrator') {
        t.push({ key: 'modules', label: 'Modules' });
        t.push({ key: 'timeline', label: 'Timeline' });
      }
      return t;
    });

    // Fathom Analytics state
    const fathomStats = ref(null);
    const fathomLoading = ref(false);
    const fathomError = ref('');
    const fathomGrouping = ref('Day');
    const fathomFromAt = ref(dayjs().subtract(30, 'day').format('YYYY-MM-DD'));
    const fathomToAt = ref(dayjs().format('YYYY-MM-DD'));
    const fathomId = ref('');

    const fathomTrackers = computed(() => {
      if (!selectedEnv.value || !selectedEnv.value.fathom_analytics) return [];
      const fa = selectedEnv.value.fathom_analytics;
      if (typeof fa === 'string') try { return JSON.parse(fa); } catch(e) { return []; }
      return Array.isArray(fa) ? fa : [];
    });

    function fetchFathomStats() {
      const id = route.params.id;
      if (!selectedEnv.value) return;
      fathomLoading.value = true;
      fathomError.value = '';
      fathomStats.value = null;
      const params = {
        from_at: fathomFromAt.value,
        to_at: fathomToAt.value,
        grouping: fathomGrouping.value,
        environment: selectedEnv.value.environment,
      };
      if (fathomId.value) params.fathom_id = fathomId.value;
      api.get('/wp-json/captaincore/v1/sites/' + id + '/stats', { params })
        .then(r => {
          if (r.data && r.data.Error) { fathomError.value = r.data.Error; return; }
          if (r.data && r.data.errors) { fathomError.value = typeof r.data.errors === 'string' ? r.data.errors : JSON.stringify(r.data.errors); return; }
          fathomStats.value = r.data;
        })
        .catch(() => { fathomError.value = 'Failed to fetch analytics.'; })
        .finally(() => { fathomLoading.value = false; });
    }

    const fathomChartEl = ref(null);
    let fathomChart = null;

    function renderFathomChart() {
      Vue.nextTick(() => {
        if (!fathomChartEl.value || !fathomStats.value || !fathomStats.value.items || !fathomStats.value.items.length) return;
        fathomChartEl.value.innerHTML = '';
        const items = fathomStats.value.items;
        const labels = items.map(i => i.date);
        const pageviews = items.map(i => i.pageviews || 0);
        const visitors = items.map(i => i.visits || 0);
        const colors = [
          getComputedStyle(document.documentElement).getPropertyValue('--color-accent').trim() || '#8b5cf6',
          getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim() || '#6366f1',
        ];
        fathomChart = new frappe.Chart(fathomChartEl.value, {
          data: {
            labels,
            datasets: [
              { name: 'Pageviews', values: pageviews },
              { name: 'Visitors', values: visitors },
            ],
          },
          type: 'line',
          height: 250,
          colors,
          axisOptions: { xAxisMode: 'tick', xIsSeries: true },
          lineOptions: { regionFill: 1, hideDots: 1 },
        });
      });
    }

    watch(fathomStats, (val) => {
      if (val && val.items && val.items.length) renderFathomChart();
    });

    function formatTime(seconds) {
      if (!seconds) return '0s';
      const s = Math.round(seconds);
      if (s < 60) return s + 's';
      const m = Math.floor(s / 60);
      const rem = s % 60;
      return m + 'm ' + rem + 's';
    }

    function formatPercent(val) {
      if (val == null) return '0%';
      return (Number(val) * 100).toFixed(1) + '%';
    }

    // Logs tab state
    const logFile = ref('error.log');
    const logContent = ref('');
    const logLoading = ref(false);
    function fetchLog() {
      const id = route.params.id;
      logLoading.value = true;
      logContent.value = '';
      const envParam = selectedEnv.value ? selectedEnv.value.environment : 'production';
      api.get('/wp-json/captaincore/v1/site/' + id + '/logs/' + logFile.value, { params: { environment: envParam } })
        .then(r => { logContent.value = typeof r.data === 'string' ? r.data : JSON.stringify(r.data, null, 2); })
        .catch(() => { logContent.value = 'Failed to load log file.'; })
        .finally(() => { logLoading.value = false; });
    }

    // Scripts tab state
    const recipes = ref([]);
    const selectedRecipe = ref('');
    const scriptCode = ref('');
    const scriptOutput = ref('');
    const scriptLoading = ref(false);
    const recipesLoaded = ref(false);
    function fetchRecipes() {
      if (recipesLoaded.value) return;
      api.get('/wp-json/captaincore/v1/recipes')
        .then(r => { recipes.value = r.data || []; recipesLoaded.value = true; })
        .catch(() => {});
    }
    function runScript() {
      const id = route.params.id;
      scriptLoading.value = true;
      scriptOutput.value = '';
      const envParam = selectedEnv.value ? selectedEnv.value.environment : 'production';
      api.post('/wp-json/captaincore/v1/run/code', { site_id: id, environment: envParam, code: scriptCode.value, recipe_id: selectedRecipe.value || null })
        .then(r => { scriptOutput.value = typeof r.data === 'string' ? r.data : JSON.stringify(r.data, null, 2); })
        .catch(() => { scriptOutput.value = 'Failed to run script.'; })
        .finally(() => { scriptLoading.value = false; });
    }
    watch(selectedRecipe, id => {
      if (!id) return;
      const recipe = recipes.value.find(r => r.recipe_id == id || r.id == id);
      if (recipe && recipe.content) scriptCode.value = recipe.content;
    });
    watch(activeTab, t => {
      if (t === 'scripts' && !recipesLoaded.value) fetchRecipes();
      if (t === 'stats' && !fathomStats.value && !fathomLoading.value) fetchFathomStats();
      if (t === 'timeline' && !timelineLoaded.value) fetchTimeline();
      if (t === 'updates' && !updateLogsLoaded.value) fetchUpdateLogs();
      if (t === 'backups' && !backupsLoaded.value) fetchBackups();
      if (t === 'backups' && !quicksavesLoaded.value) fetchQuicksaves();
      if (t === 'backups' && !snapshotsLoaded.value) fetchSnapshots();
    });

    // ── Site actions ──
    const { createJob, runCommand } = useJobs();

    function syncSite() {
      if (!selectedEnv.value) return;
      syncing.value = true;
      const id = route.params.id;
      const envName = (selectedEnv.value.environment || 'production').toLowerCase();
      api.post('/wp-json/captaincore/v1/sites/' + id + '/' + envName + '/sync')
        .then(r => {
          if (r.data) {
            const jobId = createJob({ description: `Syncing ${site.value.name} (${envName})` });
            const job = jobs.value.find(j => j.job_id === jobId);
            if (job) {
              job.job_id = r.data;
              runCommand(r.data);
            }
            terminalState.open = true;
          }
          showNotify('Sync started', 'success');
        })
        .catch(() => showNotify('Failed to start sync', 'error'))
        .finally(() => { syncing.value = false; });
    }

    function magicLogin(userId) {
      if (!selectedEnv.value) return;
      const id = route.params.id;
      const envName = (selectedEnv.value.environment || 'production').toLowerCase();
      const url = '/wp-json/captaincore/v1/sites/' + id + '/' + envName + '/magiclogin' + (userId ? '?user_id=' + encodeURIComponent(userId) : '');
      api.get(url)
        .then(r => {
          if (typeof r.data === 'string') {
            safeOpen(r.data);
          } else {
            showNotify('Login failed.', 'error');
          }
        })
        .catch(() => showNotify('Login request failed.', 'error'));
    }

    function deleteSite() {
      deleting.value = true;
      const id = route.params.id;
      api.delete('/wp-json/captaincore/v1/sites/' + id)
        .then(() => {
          showNotify('Site deleted.', 'success');
          showDeleteConfirm.value = false;
          refreshSites();
          router.push('/sites');
        })
        .catch(() => showNotify('Failed to delete site.', 'error'))
        .finally(() => { deleting.value = false; });
    }

    function addToTerminal() {
      if (!selectedEnv.value) return;
      toggleConsoleTarget({
        environment_id: selectedEnv.value.environment_id,
        site_id: site.value.site_id,
        environment: selectedEnv.value.environment,
        home_url: selectedEnv.value.home_url,
        name: site.value.name,
      });
      terminalState.open = true;
    }

    function fetchTimeline() {
      if (timelineLoaded.value) return;
      timelineLoading.value = true;
      const id = route.params.id;
      api.get('/wp-json/captaincore/v1/activity-logs?site_id=' + id + '&per_page=50')
        .then(r => { timelineLogs.value = r.data.items || r.data || []; timelineLoaded.value = true; })
        .catch(() => {})
        .finally(() => { timelineLoading.value = false; });
    }

    // Update logs
    const updateLogs = ref([]);
    const updateLogsLoading = ref(false);
    const updateLogsLoaded = ref(false);
    const expandedUpdateLog = ref(null);
    const updateLogDetail = reactive({ plugins: [], themes: [], plugins_deleted: [], themes_deleted: [], core: '', core_previous: '', loading: false });
    const showUpdateFileDiffDialog = ref(false);
    const updateFileDiff = reactive({ fileName: '', response: '', loading: false, hash: '' });
    const updateLogFiles = ref([]);
    const updateLogFilesLoading = ref(false);

    function fetchUpdateLogs() {
      if (updateLogsLoaded.value || !selectedEnv.value) return;
      updateLogsLoading.value = true;
      const id = route.params.id;
      const envName = (selectedEnv.value.environment || 'production').toLowerCase();
      api.get('/wp-json/captaincore/v1/update-logs', { params: { site_id: id, environment: envName } })
        .then(r => { updateLogs.value = r.data || []; updateLogsLoaded.value = true; })
        .catch(() => {})
        .finally(() => { updateLogsLoading.value = false; });
    }

    function toggleUpdateLog(log) {
      if (expandedUpdateLog.value === log) { expandedUpdateLog.value = null; return; }
      expandedUpdateLog.value = log;
      updateLogDetail.loading = true;
      updateLogDetail.plugins = []; updateLogDetail.themes = [];
      updateLogDetail.plugins_deleted = []; updateLogDetail.themes_deleted = [];
      updateLogFiles.value = [];
      const id = route.params.id;
      const envName = (selectedEnv.value.environment || 'production').toLowerCase();
      api.get('/wp-json/captaincore/v1/update-logs/' + log.hash_before + '_' + log.hash_after, { params: { site_id: id, environment: envName } })
        .then(r => {
          const d = r.data || {};
          updateLogDetail.plugins = d.plugins || [];
          updateLogDetail.themes = d.themes || [];
          updateLogDetail.plugins_deleted = d.plugins_deleted || [];
          updateLogDetail.themes_deleted = d.themes_deleted || [];
          updateLogDetail.core = d.core || '';
          updateLogDetail.core_previous = d.core_previous || '';
        })
        .catch(() => showNotify('Failed to load update details', 'error'))
        .finally(() => { updateLogDetail.loading = false; });
    }

    function viewUpdateLogFiles(log) {
      updateLogFilesLoading.value = true;
      const id = route.params.id;
      const envName = (selectedEnv.value.environment || 'production').toLowerCase();
      api.get('/wp-json/captaincore/v1/quicksaves/' + log.hash_after + '/changed', { params: { site_id: id, environment: envName } })
        .then(r => { updateLogFiles.value = r.data || []; })
        .catch(() => showNotify('Failed to load changed files', 'error'))
        .finally(() => { updateLogFilesLoading.value = false; });
    }

    function viewUpdateFileDiff(hash, fileName) {
      showUpdateFileDiffDialog.value = true;
      updateFileDiff.loading = true;
      updateFileDiff.fileName = fileName;
      updateFileDiff.response = '';
      updateFileDiff.hash = hash;
      const id = route.params.id;
      const envName = (selectedEnv.value.environment || 'production').toLowerCase();
      const cleanName = fileName.replace(/^[MADR]\t/, '');
      api.get('/wp-json/captaincore/v1/quicksaves/' + hash + '/filediff', { params: { site_id: id, environment: envName, file: cleanName } })
        .then(r => {
          const raw = typeof r.data === 'string' ? r.data : JSON.stringify(r.data);
          updateFileDiff.response = raw.split('\n').map(line => {
            const escaped = line.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            if (line.startsWith('-')) return '<div class="diff-removed">' + escaped + '</div>';
            if (line.startsWith('+')) return '<div class="diff-added">' + escaped + '</div>';
            return '<div>' + escaped + '</div>';
          }).join('');
        })
        .catch(() => { updateFileDiff.response = '<div>Failed to load diff.</div>'; })
        .finally(() => { updateFileDiff.loading = false; });
    }

    function editSite() {
      if (!site.value) return;
      const s = JSON.parse(JSON.stringify(site.value));
      editSiteData.site_id = s.site_id;
      editSiteData.name = s.name || '';
      editSiteData.site = s.site || '';
      editSiteData.provider = s.provider || '';
      editSiteData.key = s.key || null;
      editSiteData.shared_with = (details.value.shared_with || []).map(a => a.account_id || a.id);
      editSiteData.environment_vars = Array.isArray(s.environment_vars) ? JSON.parse(JSON.stringify(s.environment_vars)) : [];
      editSiteData.showAdvanced = false;
      // Deep copy environments
      editSiteData.environments = (environments.value || []).map(e => ({
        environment_id: e.environment_id, environment: e.environment,
        site: e.site || '', address: e.address || '', username: e.username || '', password: e.password || '',
        protocol: e.protocol || 'sftp', port: e.port || '2222', home_directory: e.home_directory || '',
        database_username: e.database_username || '', database_password: e.database_password || '',
        updates_enabled: e.updates_enabled || '1',
        updates_exclude_plugins: e.updates_exclude_plugins || '', updates_exclude_themes: e.updates_exclude_themes || '',
        offload_enabled: !!e.offload_enabled, offload_provider: e.offload_provider || '',
        offload_access_key: e.offload_access_key || '', offload_secret_key: e.offload_secret_key || '',
        offload_bucket: e.offload_bucket || '', offload_path: e.offload_path || '',
      }));
      showEditSiteDialog.value = true;
      if (!editAccountsLoaded.value) {
        api.get('/wp-json/captaincore/v1/accounts')
          .then(r => { editAccounts.value = r.data || []; editAccountsLoaded.value = true; })
          .catch(() => {});
      }
      if (!editKeysLoaded.value) {
        api.get('/wp-json/captaincore/v1/keys')
          .then(r => { editKeys.value = r.data || []; editKeysLoaded.value = true; })
          .catch(() => {});
      }
    }
    function toggleEditAccount(accountId) {
      const idx = editSiteData.shared_with.indexOf(accountId);
      if (idx > -1) editSiteData.shared_with.splice(idx, 1);
      else editSiteData.shared_with.push(accountId);
    }
    function addEditStagingEnv() {
      if (editSiteData.environments.some(e => e.environment === 'Staging')) return;
      editSiteData.environments.push({
        environment_id: '', environment: 'Staging', site: '', address: '', username: '', password: '',
        protocol: 'sftp', port: '2222', home_directory: '', database_username: '', database_password: '',
        updates_enabled: '1', updates_exclude_plugins: '', updates_exclude_themes: '',
        offload_enabled: false, offload_provider: '', offload_access_key: '', offload_secret_key: '', offload_bucket: '', offload_path: '',
      });
    }
    function removeEditStagingEnv() {
      editSiteData.environments = editSiteData.environments.filter(e => e.environment !== 'Staging');
    }
    function addEditEnvVar() { editSiteData.environment_vars.push({ key: '', value: '' }); }
    function removeEditEnvVar(i) { editSiteData.environment_vars.splice(i, 1); }
    function updateSite() {
      editSiteSaving.value = true;
      const payload = JSON.parse(JSON.stringify(editSiteData));
      payload.shared_with = payload.shared_with; // already array of IDs
      api.put('/wp-json/captaincore/v1/sites/update', { value: payload })
        .then(() => {
          site.value.name = editSiteData.name;
          site.value.provider = editSiteData.provider;
          showEditSiteDialog.value = false;
          showNotify('Site updated', 'success');
          refreshSites();
        })
        .catch(() => showNotify('Failed to update site', 'error'))
        .finally(() => { editSiteSaving.value = false; });
    }
    function fetchPhpmyadmin() {
      if (!selectedEnv.value) return;
      fetchingPhpmyadmin.value = true;
      const id = route.params.id;
      const envName = (selectedEnv.value.environment || 'production').toLowerCase();
      api.get('/wp-json/captaincore/v1/sites/' + id + '/' + envName + '/phpmyadmin')
        .then(r => { if (r.data) safeOpen(r.data); else showNotify('PHPMyAdmin not available', 'error'); })
        .catch(() => showNotify('Failed to open PHPMyAdmin', 'error'))
        .finally(() => { fetchingPhpmyadmin.value = false; });
    }
    function copySshCommand() {
      if (!selectedEnv.value) return;
      const e = selectedEnv.value;
      const cmd = `ssh ${e.username}@${e.address} -p ${e.port || 22}`;
      doCopy(cmd, showNotify);
    }
    function copySftpCommand() {
      if (!selectedEnv.value) return;
      const e = selectedEnv.value;
      const cmd = `sftp -P ${e.port || 22} ${e.username}@${e.address}`;
      doCopy(cmd, showNotify);
    }
    function copyDbInfo() {
      if (!selectedEnv.value) return;
      const e = selectedEnv.value;
      const info = `Host: ${e.address}\nDatabase: ${e.database_name}\nUsername: ${e.database_username}\nPassword: ${e.database_password}`;
      doCopy(info, showNotify);
    }
    function toggleSiteStatus() {
      if (!selectedEnv.value) return;
      const id = route.params.id;
      const envName = (selectedEnv.value.environment || 'production').toLowerCase();
      const isActive = selectedEnv.value.site_active !== false && selectedEnv.value.site_active !== 0;
      const command = isActive ? 'deactivate' : 'activate';
      if (!confirm(`Are you sure you want to ${command} this site?`)) return;
      api.post('/wp-json/captaincore/v1/sites/cli', { post_id: id, command, environment: envName })
        .then(r => {
          if (r.data) {
            const jobId = createJob({ description: `${command === 'activate' ? 'Activating' : 'Deactivating'} ${site.value.name}` });
            const job = jobs.value.find(j => j.job_id === jobId);
            if (job) { job.job_id = r.data; runCommand(r.data); }
            terminalState.open = true;
          }
          showNotify(`Site ${command} started`, 'success');
        })
        .catch(() => showNotify(`Failed to ${command} site`, 'error'));
    }

    function fetchBackups() {
      if (backupsLoaded.value || !selectedEnv.value) return;
      backupsLoading.value = true;
      const id = route.params.id;
      const envName = (selectedEnv.value.environment || 'production').toLowerCase();
      api.get('/wp-json/captaincore/v1/site/' + id + '/' + envName + '/backups')
        .then(r => { backups.value = r.data || []; backupsLoaded.value = true; })
        .catch(() => {})
        .finally(() => { backupsLoading.value = false; });
    }
    function fetchQuicksaves() {
      if (quicksavesLoaded.value || !selectedEnv.value) return;
      quicksavesLoading.value = true;
      const id = route.params.id;
      const envName = (selectedEnv.value.environment || 'production').toLowerCase();
      api.get('/wp-json/captaincore/v1/quicksaves', { params: { site_id: id, environment: envName } })
        .then(r => { quicksaves.value = r.data || []; quicksavesLoaded.value = true; })
        .catch(() => {})
        .finally(() => { quicksavesLoading.value = false; });
    }
    function fetchSnapshots() {
      if (snapshotsLoaded.value || !selectedEnv.value) return;
      snapshotsLoading.value = true;
      const id = route.params.id;
      const envName = (selectedEnv.value.environment || 'Production');
      const envKey = envName.charAt(0).toUpperCase() + envName.slice(1).toLowerCase();
      api.get('/wp-json/captaincore/v1/site/' + id + '/snapshots')
        .then(r => {
          const data = r.data || {};
          snapshots.value = data[envKey] || data['Production'] || (Array.isArray(data) ? data : []);
          snapshotsLoaded.value = true;
        })
        .catch(() => {})
        .finally(() => { snapshotsLoading.value = false; });
    }
    const filteredQuicksaves = computed(() => {
      if (!quicksaveSearch.value) return quicksaves.value;
      const q = quicksaveSearch.value.toLowerCase();
      return quicksaves.value.filter(qs => {
        const changes = qs.changes || qs.git_status || '';
        return (typeof changes === 'string' ? changes : JSON.stringify(changes)).toLowerCase().includes(q);
      });
    });
    function rollbackQuicksave(qs) {
      if (!selectedEnv.value || !qs) return;
      if (!confirm('Rollback to this quicksave? Changes will be applied to the live site.')) return;
      const id = route.params.id;
      const envName = (selectedEnv.value.environment || 'production').toLowerCase();
      api.post('/wp-json/captaincore/v1/quicksaves/' + (qs.git_hash || qs.hash) + '/rollback', { site_id: id, environment: envName })
        .then(r => {
          if (r.data) {
            const jobId = createJob({ description: 'Rolling back quicksave' });
            const job = jobs.value.find(j => j.job_id === jobId);
            if (job) { job.job_id = r.data; runCommand(r.data); }
            terminalState.open = true;
          }
          showNotify('Rollback started', 'success');
        })
        .catch(() => showNotify('Rollback failed', 'error'));
    }
    function toggleQuicksaveExpand(qs) {
      const hash = qs.git_hash || qs.hash;
      if (expandedQuicksave.value === hash) {
        expandedQuicksave.value = null;
        quicksaveFiles.value = [];
        quicksaveFilesFiltered.value = [];
        quicksaveFileSearch.value = '';
        return;
      }
      expandedQuicksave.value = hash;
      quicksaveFiles.value = [];
      quicksaveFilesFiltered.value = [];
      quicksaveFileSearch.value = '';
      quicksaveFilesLoading.value = true;
      const id = route.params.id;
      const envName = (selectedEnv.value.environment || 'production').toLowerCase();
      api.get('/wp-json/captaincore/v1/quicksaves/' + hash + '/changed', { params: { site_id: id, environment: envName } })
        .then(r => {
          const files = (typeof r.data === 'string' ? r.data.trim().split('\n') : r.data) || [];
          quicksaveFiles.value = files.filter(f => f);
          quicksaveFilesFiltered.value = [...quicksaveFiles.value];
        })
        .catch(() => { quicksaveFiles.value = []; quicksaveFilesFiltered.value = []; })
        .finally(() => { quicksaveFilesLoading.value = false; });
    }

    function filterQuicksaveFiles() {
      if (!quicksaveFileSearch.value) {
        quicksaveFilesFiltered.value = [...quicksaveFiles.value];
        return;
      }
      const q = quicksaveFileSearch.value.toLowerCase();
      quicksaveFilesFiltered.value = quicksaveFiles.value.filter(f => f.toLowerCase().includes(q));
    }

    function quicksaveFileDiff(hash, fileName) {
      // File names from git status have format like "M\tpath/to/file" - extract the path
      const cleanName = fileName.includes('\t') ? fileName.split('\t').pop() : fileName;
      fileDiff.fileName = cleanName;
      fileDiff.response = '';
      fileDiff.loading = true;
      fileDiff.quicksave = quicksaves.value.find(q => (q.git_hash || q.hash) === hash) || null;
      showFileDiffDialog.value = true;
      const id = route.params.id;
      const envName = (selectedEnv.value.environment || 'production').toLowerCase();
      api.get('/wp-json/captaincore/v1/quicksaves/' + hash + '/filediff', { params: { site_id: id, environment: envName, file: cleanName } })
        .then(r => {
          const raw = typeof r.data === 'string' ? r.data : JSON.stringify(r.data);
          const lines = raw.split('\n').map(line => {
            let cls = '';
            if (line[0] === '-') cls = ' class="diff-removed"';
            else if (line[0] === '+') cls = ' class="diff-added"';
            return '<div' + cls + '>' + line.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</div>';
          });
          fileDiff.response = lines.join('');
          fileDiff.loading = false;
        })
        .catch(() => { fileDiff.response = '<div style="color: var(--color-error);">Failed to load diff.</div>'; fileDiff.loading = false; });
    }

    function quicksaveFileRestore() {
      if (!fileDiff.quicksave || !fileDiff.fileName) return;
      const date = prettyTimestampEpoch(fileDiff.quicksave.created_at);
      if (!confirm('Restore file "' + fileDiff.fileName + '" as of ' + date + '?')) return;
      const hash = fileDiff.quicksave.git_hash || fileDiff.quicksave.hash || fileDiff.quicksave.hash_after;
      const id = route.params.id;
      const envName = selectedEnv.value.environment || 'Production';
      api.post('/wp-json/captaincore/v1/sites/cli', {
        post_id: id,
        environment: envName,
        hash: hash,
        command: 'quicksave_file_restore',
        value: fileDiff.fileName,
      }).then(r => {
        if (r.data) {
          const jobId = createJob({ description: 'Restoring file ' + fileDiff.fileName });
          const job = jobs.value.find(j => j.job_id === jobId);
          if (job) { job.job_id = r.data; runCommand(r.data); }
          terminalState.open = true;
        }
        showFileDiffDialog.value = false;
        showNotify('File restore started', 'success');
      }).catch(() => showNotify('File restore failed', 'error'));
    }

    function getFileStatus(fileName) {
      if (!fileName) return '';
      const prefix = fileName.split('\t')[0].trim();
      if (prefix === 'M') return 'Modified';
      if (prefix === 'A') return 'Added';
      if (prefix === 'D') return 'Deleted';
      if (prefix === 'R') return 'Renamed';
      return '';
    }

    function getFileStatusColor(fileName) {
      if (!fileName) return '';
      const prefix = fileName.split('\t')[0].trim();
      if (prefix === 'M') return 'var(--color-warning)';
      if (prefix === 'A') return 'var(--color-success)';
      if (prefix === 'D') return 'var(--color-error)';
      return 'var(--text-secondary)';
    }

    function getCleanFileName(fileName) {
      return fileName.includes('\t') ? fileName.split('\t').pop() : fileName;
    }

    function downloadBackup(b) {
      if (!selectedEnv.value) return;
      const id = route.params.id;
      const envName = (selectedEnv.value.environment || 'production').toLowerCase();
      api.post('/wp-json/captaincore/v1/sites/cli', { post_id: id, command: 'backup_download', value: { backup_id: b.backup_id || b.id }, environment: envName })
        .then(r => {
          if (r.data) {
            const jobId = createJob({ description: 'Preparing backup download' });
            const job = jobs.value.find(j => j.job_id === jobId);
            if (job) { job.job_id = r.data; runCommand(r.data); }
            terminalState.open = true;
          }
          showNotify('Download started - you will receive an email when ready', 'success');
        })
        .catch(() => showNotify('Download failed', 'error'));
    }
    function restoreBackup(b) {
      if (!selectedEnv.value) return;
      if (!confirm('Restore this backup? This will overwrite the current site data.')) return;
      const id = route.params.id;
      const envName = (selectedEnv.value.environment || 'production').toLowerCase();
      api.post('/wp-json/captaincore/v1/sites/cli', { post_id: id, command: 'backup_restore', value: { backup_id: b.backup_id || b.id }, environment: envName })
        .then(r => {
          if (r.data) {
            const jobId = createJob({ description: 'Restoring backup' });
            const job = jobs.value.find(j => j.job_id === jobId);
            if (job) { job.job_id = r.data; runCommand(r.data); }
            terminalState.open = true;
          }
          showNotify('Restore started', 'success');
        })
        .catch(() => showNotify('Restore failed', 'error'));
    }
    function createSnapshot() {
      if (!selectedEnv.value) return;
      const id = route.params.id;
      const envName = (selectedEnv.value.environment || 'production').toLowerCase();
      api.post('/wp-json/captaincore/v1/sites/cli', { post_id: id, command: 'snapshot', environment: envName })
        .then(r => {
          if (r.data) {
            const jobId = createJob({ description: 'Creating snapshot' });
            const job = jobs.value.find(j => j.job_id === jobId);
            if (job) { job.job_id = r.data; runCommand(r.data); }
            terminalState.open = true;
          }
          showNotify('Snapshot started', 'success');
        })
        .catch(() => showNotify('Snapshot failed', 'error'));
    }

    let pluginSearchTimer = null;
    watch(pluginSearch, val => {
      clearTimeout(pluginSearchTimer);
      if (!val || val.length < 2) { pluginSearchResults.value = []; return; }
      pluginSearchTimer = setTimeout(() => {
        pluginSearchLoading.value = true;
        api.get('/wp-json/captaincore/v1/plugins/search', { params: { q: val } })
          .then(r => { pluginSearchResults.value = r.data || []; })
          .catch(() => { pluginSearchResults.value = []; })
          .finally(() => { pluginSearchLoading.value = false; });
      }, 400);
    });
    let themeSearchTimer = null;
    watch(themeSearch, val => {
      clearTimeout(themeSearchTimer);
      if (!val || val.length < 2) { themeSearchResults.value = []; return; }
      themeSearchTimer = setTimeout(() => {
        themeSearchLoading.value = true;
        api.get('/wp-json/captaincore/v1/themes/search', { params: { q: val } })
          .then(r => { themeSearchResults.value = r.data || []; })
          .catch(() => { themeSearchResults.value = []; })
          .finally(() => { themeSearchLoading.value = false; });
      }, 400);
    });
    function runAddonCommand(cmd, description, onFinish) {
      if (!selectedEnv.value) return;
      addonActionLoading.value = description;
      api.post('/wp-json/captaincore/v1/run/code', { environments: [selectedEnv.value.environment_id], code: cmd })
        .then(r => {
          if (r.data) {
            const jobId = createJob({ description, onFinish });
            const job = jobs.value.find(j => j.job_id === jobId);
            if (job) { job.job_id = r.data; runCommand(r.data); }
            terminalState.open = true;
          }
          showNotify(description + ' started', 'success');
        })
        .catch(() => showNotify(description + ' failed', 'error'))
        .finally(() => { addonActionLoading.value = ''; });
    }
    function installPlugin(plugin) {
      const slug = plugin.slug || plugin.name;
      if (!confirm('Install plugin "' + (plugin.name || slug) + '"?')) return;
      const link = plugin.download_link || slug;
      runAddonCommand("wp plugin install --force --skip-plugins --skip-themes '" + link + "' --activate", 'Installing ' + slug);
      showAddPluginDialog.value = false;
    }
    function installTheme(theme) {
      const slug = theme.slug || theme.name;
      if (!confirm('Install theme "' + (theme.name || slug) + '"?')) return;
      const link = theme.download_link || slug;
      runAddonCommand("wp theme install --force --skip-plugins --skip-themes '" + link + "'", 'Installing ' + slug);
      showAddThemeDialog.value = false;
    }
    function togglePlugin(slug, action) {
      if (!confirm(action + ' plugin "' + slug + '"?')) return;
      runAddonCommand('wp plugin ' + action + ' ' + slug + ' --skip-themes --skip-plugins', action.charAt(0).toUpperCase() + action.slice(1) + ' ' + slug);
    }
    function deletePlugin(slug) {
      if (!confirm('Delete plugin "' + slug + '"? This cannot be undone.')) return;
      runAddonCommand('wp plugin delete ' + slug + ' --skip-themes --skip-plugins', 'Deleting ' + slug);
    }
    function activateTheme(slug) {
      if (!confirm('Activate theme "' + slug + '"?')) return;
      runAddonCommand('wp theme activate ' + slug + ' --skip-themes --skip-plugins', 'Activating ' + slug);
    }
    function deleteTheme(slug) {
      if (!confirm('Delete theme "' + slug + '"? This cannot be undone.')) return;
      runAddonCommand('wp theme delete ' + slug + ' --skip-themes --skip-plugins', 'Deleting ' + slug);
    }

    function fetchData() {
      const id = route.params.id;
      loading.value = true;
      Promise.all([
        api.get('/wp-json/captaincore/v1/sites/' + id + '/environments'),
        api.get('/wp-json/captaincore/v1/sites/' + id + '/details'),
      ]).then(([envR, detR]) => {
        environments.value = envR.data || [];
        details.value = detR.data || {};
        site.value = detR.data.site || {};
        envIndex.value = 0;
      }).catch(() => showNotify('Failed to load site', 'error'))
        .finally(() => { loading.value = false; });
    }
    onMounted(fetchData);
    watch(() => route.params.id, v => { if (v) fetchData(); });
    const plugins = computed(() => {
      if (!selectedEnv.value) return [];
      let p = selectedEnv.value.plugins;
      if (typeof p === 'string') try { p = JSON.parse(p); } catch(e) { return []; }
      return Array.isArray(p) ? p : [];
    });
    const themes = computed(() => {
      if (!selectedEnv.value) return [];
      let t = selectedEnv.value.themes;
      if (typeof t === 'string') try { t = JSON.parse(t); } catch(e) { return []; }
      return Array.isArray(t) ? t : [];
    });
    const sharedWith = computed(() => details.value.shared_with || []);
    const linkedDomains = computed(() => details.value.domains || []);
    function goBack() { router.push('/sites'); }
    function copy(t) { doCopy(t, showNotify); }
    // Site Launch
    const showLaunchDialog = ref(false);
    const launchData = reactive({ domain: '', apply_https: true });
    const launching = ref(false);
    function launchSite() {
      launching.value = true;
      const envIds = selectedEnv.value ? [selectedEnv.value.environment_id] : [];
      api.post('/wp-json/captaincore/v1/sites/bulk-tools', { tool: 'launch', environments: envIds, params: { domain: launchData.domain } })
        .then(r => { showNotify('Launch started', 'success'); showLaunchDialog.value = false; if (r.data && r.data.job_id) addToTerminal(r.data.job_id); })
        .catch(() => showNotify('Launch failed', 'error'))
        .finally(() => { launching.value = false; });
    }

    // Site Copy
    const showCopyDialog = ref(false);
    const copyData = reactive({ destination_id: '', environment: 'Production' });
    const copying = ref(false);
    function copySite() {
      copying.value = true;
      api.post('/wp-json/captaincore/v1/sites/cli', { post_id: site.value.site_id, command: 'copy', value: copyData.destination_id })
        .then(r => { showNotify('Copy started', 'success'); showCopyDialog.value = false; if (r.data && r.data.job_id) addToTerminal(r.data.job_id); })
        .catch(() => showNotify('Copy failed', 'error'))
        .finally(() => { copying.value = false; });
    }

    // Site Migration
    const showMigrateDialog = ref(false);
    const migrateData = reactive({ backup_url: '', update_urls: true });
    const migrating = ref(false);
    function migrateSite() {
      migrating.value = true;
      const env = selectedEnv.value ? (selectedEnv.value.environment === 'staging' ? 'Staging' : 'Production') : 'Production';
      api.post('/wp-json/captaincore/v1/sites/cli', { post_id: site.value.site_id, command: 'migrate', value: migrateData.backup_url, update_urls: migrateData.update_urls ? 'true' : 'false', environment: env })
        .then(r => { showNotify('Migration started', 'success'); showMigrateDialog.value = false; if (r.data && r.data.job_id) addToTerminal(r.data.job_id); })
        .catch(() => showNotify('Migration failed', 'error'))
        .finally(() => { migrating.value = false; });
    }

    // Push to Other Environment
    const showPushDialog = ref(false);
    const pushing = ref(false);
    function pushToOther() {
      if (!confirm('Push this environment to the other? This will overwrite the target environment.')) return;
      pushing.value = true;
      const sourceEnvId = selectedEnv.value ? selectedEnv.value.environment_id : '';
      const targetEnv = environments.value.find(e => e.environment_id !== sourceEnvId);
      const targetEnvId = targetEnv ? targetEnv.environment_id : '';
      api.post('/wp-json/captaincore/v1/sites/environments/push', { source_environment_id: sourceEnvId, target_environment_id: targetEnvId })
        .then(r => { showNotify('Push started', 'success'); if (r.data && r.data.job_id) addToTerminal(r.data.job_id); })
        .catch(() => showNotify('Push failed', 'error'))
        .finally(() => { pushing.value = false; });
    }

    // Domain Mappings
    const showDomainMappingsDialog = ref(false);
    const domainMappings = ref([]);
    const domainMappingsLoading = ref(false);
    const newDomainMapping = ref('');
    function fetchDomainMappings() {
      if (!site.value || !selectedEnv.value) return;
      domainMappingsLoading.value = true;
      const envName = selectedEnv.value.environment === 'staging' ? 'staging' : 'production';
      api.get('/wp-json/captaincore/v1/sites/' + site.value.site_id + '/' + envName + '/domains')
        .then(r => { domainMappings.value = r.data || []; showDomainMappingsDialog.value = true; })
        .catch(() => showNotify('Failed to load domain mappings', 'error'))
        .finally(() => { domainMappingsLoading.value = false; });
    }
    function addDomainMapping() {
      if (!newDomainMapping.value) return;
      const envName = selectedEnv.value.environment === 'staging' ? 'staging' : 'production';
      api.post('/wp-json/captaincore/v1/sites/' + site.value.site_id + '/' + envName + '/domains', { domain_name: newDomainMapping.value })
        .then(r => { domainMappings.value = r.data || domainMappings.value; newDomainMapping.value = ''; showNotify('Domain added', 'success'); })
        .catch(() => showNotify('Failed to add domain', 'error'));
    }
    function deleteDomainMapping(dm) {
      api.delete('/wp-json/captaincore/v1/sites/' + site.value.site_id + '/' + (selectedEnv.value.environment === 'staging' ? 'staging' : 'production') + '/domains', { data: { domain_ids: [dm.domain_id || dm.id] } })
        .then(() => { domainMappings.value = domainMappings.value.filter(d => (d.domain_id || d.id) !== (dm.domain_id || dm.id)); showNotify('Domain removed', 'success'); })
        .catch(() => showNotify('Failed to remove domain', 'error'));
    }
    function setPrimaryDomainMapping(dm) {
      const envName = selectedEnv.value.environment === 'staging' ? 'staging' : 'production';
      api.put('/wp-json/captaincore/v1/sites/' + site.value.site_id + '/' + envName + '/domains/primary', { domain_id: dm.domain_id || dm.id, run_search_and_replace: true })
        .then(() => { showNotify('Primary domain updated', 'success'); fetchDomainMappings(); })
        .catch(() => showNotify('Failed to set primary', 'error'));
    }

    // Visual Captures
    const showCapturesDialog = ref(false);
    const captures = ref([]);
    const capturesLoading = ref(false);
    const capturePages = ref([{ page: '/' }]);
    function showCaptures() {
      if (!site.value || !selectedEnv.value) return;
      capturesLoading.value = true;
      const envName = selectedEnv.value.environment === 'staging' ? 'staging' : 'production';
      api.get('/wp-json/captaincore/v1/site/' + site.value.site_id + '/' + envName + '/captures')
        .then(r => { captures.value = r.data || []; showCapturesDialog.value = true; })
        .catch(() => showNotify('Failed to load captures', 'error'))
        .finally(() => { capturesLoading.value = false; });
    }
    function captureCheck() {
      if (!site.value || !selectedEnv.value) return;
      const envName = selectedEnv.value.environment === 'staging' ? 'staging' : 'production';
      api.get('/wp-json/captaincore/v1/sites/' + site.value.site_id + '/' + envName + '/captures/new')
        .then(() => showNotify('Capture requested', 'success'))
        .catch(() => showNotify('Failed to request capture', 'error'));
    }
    function saveCaptureConfig() {
      if (!site.value || !selectedEnv.value) return;
      const envName = selectedEnv.value.environment === 'staging' ? 'staging' : 'production';
      api.post('/wp-json/captaincore/v1/sites/' + site.value.site_id + '/' + envName + '/captures', { pages: capturePages.value })
        .then(() => showNotify('Capture config saved', 'success'))
        .catch(() => showNotify('Failed to save', 'error'));
    }

    return { site, environments, envIndex, selectedEnv, details, loading, activeTab, tabs, plugins, themes, sharedWith, linkedDomains, goBack, copy, formatStorage, formatLargeNumbers, prettyTimestamp, prettyTimestampEpoch, logFile, logContent, logLoading, fetchLog, recipes, selectedRecipe, scriptCode, scriptOutput, scriptLoading, runScript, fathomStats, fathomLoading, fathomError, fathomGrouping, fathomFromAt, fathomToAt, fathomId, fathomTrackers, fetchFathomStats, formatTime, formatPercent, fathomChartEl, role, syncing, deleting, showDeleteConfirm, syncSite, magicLogin, deleteSite, addToTerminal, timelineLogs, timelineLoading, updateLogs, updateLogsLoading, expandedUpdateLog, updateLogDetail, toggleUpdateLog, viewUpdateLogFiles, updateLogFiles, updateLogFilesLoading, showUpdateFileDiffDialog, updateFileDiff, viewUpdateFileDiff, showEditSiteDialog, editSiteData, editSiteSaving, editSite, updateSite, editAccounts, editKeys, toggleEditAccount, addEditStagingEnv, removeEditStagingEnv, addEditEnvVar, removeEditEnvVar, fetchPhpmyadmin, fetchingPhpmyadmin, copySshCommand, copySftpCommand, copyDbInfo, toggleSiteStatus, showAddPluginDialog, showAddThemeDialog, pluginSearch, themeSearch, pluginSearchResults, themeSearchResults, pluginSearchLoading, themeSearchLoading, addonActionLoading, installPlugin, installTheme, togglePlugin, deletePlugin, activateTheme, deleteTheme, backups, backupsLoading, quicksaves, quicksavesLoading, quicksaveSearch, filteredQuicksaves, expandedQuicksave, quicksaveFiles, quicksaveFilesFiltered, quicksaveFilesLoading, quicksaveFileSearch, toggleQuicksaveExpand, filterQuicksaveFiles, quicksaveFileDiff, quicksaveFileRestore, showFileDiffDialog, fileDiff, getFileStatus, getFileStatusColor, getCleanFileName, snapshots, snapshotsLoading, backupSubTab, rollbackQuicksave, downloadBackup, restoreBackup, createSnapshot, showLaunchDialog, launchData, launching, launchSite, showCopyDialog, copyData, copying, copySite, showMigrateDialog, migrateData, migrating, migrateSite, pushing, pushToOther, showDomainMappingsDialog, domainMappings, domainMappingsLoading, newDomainMapping, fetchDomainMappings, addDomainMapping, deleteDomainMapping, setPrimaryDomainMapping, showCapturesDialog, captures, capturesLoading, capturePages, showCaptures, captureCheck, saveCaptureConfig, sites };
  },
  template: `
    <div v-if="loading" class="flex justify-center py-16"><div class="animate-spin rounded-full h-8 w-8 border-b-2" style="border-color: var(--color-primary)"></div></div>
    <div v-else-if="!site" class="surface rounded-xl p-8 text-center"><p style="color: var(--text-secondary)">Site not found.</p></div>
    <div v-else class="surface rounded-xl">
      <div class="detail-header flex-wrap gap-2">
        <button class="back-btn" @click="goBack"><svg-icon name="arrowLeft" :size="18" /></button>
        <div class="flex-1 min-w-0">
          <h2 class="text-sm font-semibold truncate" style="color: var(--text-primary)">{{ site.name }}</h2>
          <a v-if="selectedEnv && selectedEnv.home_url" :href="selectedEnv.home_url" target="_blank" class="text-xs truncate block" style="color: var(--text-secondary)">{{ selectedEnv.home_url }}</a>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
          <button @click="syncSite" :disabled="syncing" class="btn btn-sm btn-outline" title="Sync site data">
            <svg-icon name="sync" :size="14" /> {{ syncing ? 'Syncing...' : 'Sync' }}
          </button>
          <button @click="magicLogin()" class="btn btn-sm btn-outline" title="WP Admin Login">
            <svg-icon name="login" :size="14" /> WP Login
          </button>
          <a v-if="selectedEnv && selectedEnv.home_url" :href="selectedEnv.home_url" target="_blank" class="btn btn-sm btn-outline" style="text-decoration: none;">
            <svg-icon name="externalLink" :size="14" /> Visit
          </a>
          <button @click="addToTerminal()" class="btn btn-sm btn-outline" title="Add to terminal targets">
            <svg-icon name="terminal" :size="14" />
          </button>
          <button v-if="role === 'administrator'" @click="editSite()" class="btn btn-sm btn-ghost" title="Edit site">
            <svg-icon name="pencil" :size="14" />
          </button>
          <button v-if="role === 'administrator'" @click="showDeleteConfirm = true" class="btn btn-sm btn-ghost" title="Delete site" style="color: var(--color-error);">
            <svg-icon name="trash" :size="14" />
          </button>
        </div>
        <select v-if="environments.length > 1" v-model.number="envIndex" class="select-field" style="width: auto; max-width: 220px;">
          <option v-for="(env, i) in environments" :key="i" :value="i">{{ env.environment || 'Production' }} Environment</option>
        </select>
      </div>

      <!-- Delete confirmation dialog -->
      <div v-if="showDeleteConfirm" class="dialog-overlay" @click.self="showDeleteConfirm = false">
        <div class="dialog-card" style="max-width: 400px;">
          <div class="dialog-card-header">
            <h3 class="text-sm font-semibold" style="color: var(--color-error);">Delete Site</h3>
          </div>
          <div class="dialog-card-body">
            <p class="text-sm" style="color: var(--text-primary);">Are you sure you want to delete <strong>{{ site.name }}</strong>? This action cannot be undone.</p>
          </div>
          <div class="dialog-card-footer">
            <button @click="showDeleteConfirm = false" class="btn btn-ghost">Cancel</button>
            <button @click="deleteSite()" :disabled="deleting" class="btn btn-danger">{{ deleting ? 'Deleting...' : 'Delete Site' }}</button>
          </div>
        </div>
      </div>
      <!-- Edit site dialog -->
      <div v-if="showEditSiteDialog" class="dialog-overlay" @click.self="showEditSiteDialog = false">
        <div class="dialog-card" style="max-width: 640px; max-height: 90vh; display: flex; flex-direction: column;">
          <div class="dialog-card-header">
            <h3 class="text-sm font-semibold" style="color: var(--text-primary)">Edit Site</h3>
            <button @click="showEditSiteDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="close" :size="16" /></button>
          </div>
          <div class="dialog-card-body space-y-4" style="overflow-y: auto; flex: 1;">
            <!-- Basic fields -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Site Name</label>
                <input v-model="editSiteData.name" class="input-field" />
              </div>
              <div>
                <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Provider</label>
                <select v-model="editSiteData.provider" class="select-field">
                  <option value="">None</option>
                  <option value="kinsta">Kinsta</option>
                  <option value="gridpane">GridPane</option>
                  <option value="rocketdotnet">Rocket.net</option>
                  <option value="wpengine">WP Engine</option>
                </select>
              </div>
            </div>
            <div v-if="editKeys.length">
              <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">SSH Key Override</label>
              <select v-model="editSiteData.key" class="select-field">
                <option :value="null">Default</option>
                <option v-for="k in editKeys" :key="k.key_id || k.id" :value="k.key_id || k.id">{{ k.title }}</option>
              </select>
            </div>

            <!-- Account assignment (admin) -->
            <div v-if="role === 'administrator' && editAccounts.length">
              <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Assigned Accounts</label>
              <div class="flex flex-wrap gap-1">
                <button v-for="a in editAccounts" :key="a.account_id || a.id"
                  @click="toggleEditAccount(a.account_id || a.id)"
                  :class="['filter-chip', editSiteData.shared_with.includes(a.account_id || a.id) && 'active']"
                  style="font-size: 0.6875rem;">
                  {{ a.name }}
                </button>
              </div>
            </div>

            <!-- Environments -->
            <div v-for="(env, ei) in editSiteData.environments" :key="ei" class="rounded-lg p-3" style="border: 1px solid var(--border-color);">
              <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold" style="color: var(--text-primary)">{{ env.environment }} Environment</span>
                <button v-if="env.environment === 'Staging'" @click="removeEditStagingEnv()" class="btn btn-sm btn-ghost" style="color: var(--color-error); font-size: 0.6875rem;"><svg-icon name="close" :size="12" /> Remove</button>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs mb-1" style="color: var(--text-secondary)">Address</label>
                  <input v-model="env.address" class="input-field" placeholder="IP or hostname" />
                </div>
                <div>
                  <label class="block text-xs mb-1" style="color: var(--text-secondary)">Username</label>
                  <input v-model="env.username" class="input-field" />
                </div>
                <div>
                  <label class="block text-xs mb-1" style="color: var(--text-secondary)">Password</label>
                  <input v-model="env.password" type="password" class="input-field" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs mb-1" style="color: var(--text-secondary)">Protocol</label>
                    <select v-model="env.protocol" class="select-field">
                      <option value="sftp">SFTP</option>
                      <option value="ssh">SSH</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-xs mb-1" style="color: var(--text-secondary)">Port</label>
                    <input v-model="env.port" type="text" class="input-field" />
                  </div>
                </div>
                <div class="col-span-2">
                  <label class="block text-xs mb-1" style="color: var(--text-secondary)">Home Directory</label>
                  <input v-model="env.home_directory" class="input-field" />
                </div>
              </div>
            </div>
            <button v-if="!editSiteData.environments.some(e => e.environment === 'Staging')" @click="addEditStagingEnv()" class="btn btn-sm btn-ghost"><svg-icon name="plus" :size="14" /> Add Staging Environment</button>

            <!-- Environment Variables -->
            <div>
              <button @click="editSiteData.showAdvanced = !editSiteData.showAdvanced" class="btn btn-sm btn-ghost text-xs">
                <svg-icon :name="editSiteData.showAdvanced ? 'chevronUp' : 'chevronDown'" :size="12" /> Environment Variables
              </button>
              <div v-if="editSiteData.showAdvanced" class="mt-2 space-y-2">
                <div v-for="(v, vi) in editSiteData.environment_vars" :key="vi" class="flex items-center gap-2">
                  <input v-model="v.key" class="input-field flex-1" placeholder="Key" style="font-size: 0.75rem;" />
                  <input v-model="v.value" class="input-field flex-1" placeholder="Value" style="font-size: 0.75rem;" />
                  <button @click="removeEditEnvVar(vi)" class="btn-ghost p-1"><svg-icon name="close" :size="14" style="color: var(--color-error);" /></button>
                </div>
                <button @click="addEditEnvVar()" class="btn btn-sm btn-ghost text-xs"><svg-icon name="plus" :size="12" /> Add Variable</button>
              </div>
            </div>
          </div>
          <div class="dialog-card-footer">
            <button @click="showEditSiteDialog = false" class="btn btn-ghost">Cancel</button>
            <button @click="updateSite()" :disabled="editSiteSaving" class="btn btn-primary">{{ editSiteSaving ? 'Saving...' : 'Save' }}</button>
          </div>
        </div>
      </div>
      <!-- Launch Dialog -->
      <div v-if="showLaunchDialog" class="dialog-overlay" @click.self="showLaunchDialog = false">
        <div class="dialog-card" style="max-width: 480px;">
          <div class="dialog-card-header"><h3 class="text-sm font-semibold" style="color: var(--text-primary)">Launch Site</h3><button @click="showLaunchDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="close" :size="16" /></button></div>
          <div class="dialog-card-body space-y-4">
            <p class="text-sm" style="color: var(--text-secondary)">Launch this site to a new domain. This will update the site URL and configure DNS.</p>
            <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">New Domain</label><input v-model="launchData.domain" class="input-field" placeholder="example.com" /></div>
            <div class="flex items-center gap-2"><button :class="['toggle', launchData.apply_https && 'on']" @click="launchData.apply_https = !launchData.apply_https"></button><span class="text-sm" style="color: var(--text-primary)">Apply HTTPS</span></div>
          </div>
          <div class="dialog-card-footer"><button @click="showLaunchDialog = false" class="btn btn-ghost">Cancel</button><button @click="launchSite" :disabled="launching || !launchData.domain" class="btn btn-primary">{{ launching ? 'Launching...' : 'Launch' }}</button></div>
        </div>
      </div>
      <!-- Copy Dialog -->
      <div v-if="showCopyDialog" class="dialog-overlay" @click.self="showCopyDialog = false">
        <div class="dialog-card" style="max-width: 480px;">
          <div class="dialog-card-header"><h3 class="text-sm font-semibold" style="color: var(--text-primary)">Copy Site</h3><button @click="showCopyDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="close" :size="16" /></button></div>
          <div class="dialog-card-body space-y-4">
            <p class="text-sm" style="color: var(--text-secondary)">Copy this site to another existing site. The destination site will be overwritten.</p>
            <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Destination Site</label>
              <select v-model="copyData.destination_id" class="select-field">
                <option value="">Select site...</option>
                <option v-for="s in sites.filter(s => s.site_id != site.site_id)" :key="s.site_id" :value="s.site_id">{{ s.name }}</option>
              </select>
            </div>
          </div>
          <div class="dialog-card-footer"><button @click="showCopyDialog = false" class="btn btn-ghost">Cancel</button><button @click="copySite" :disabled="copying || !copyData.destination_id" class="btn btn-primary">{{ copying ? 'Copying...' : 'Copy Site' }}</button></div>
        </div>
      </div>
      <!-- Migrate Dialog -->
      <div v-if="showMigrateDialog" class="dialog-overlay" @click.self="showMigrateDialog = false">
        <div class="dialog-card" style="max-width: 520px;">
          <div class="dialog-card-header"><h3 class="text-sm font-semibold" style="color: var(--text-primary)">Migrate Site</h3><button @click="showMigrateDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="close" :size="16" /></button></div>
          <div class="dialog-card-body space-y-4">
            <p class="text-sm" style="color: var(--text-secondary)">Import a site from a backup URL. This will overwrite the current environment.</p>
            <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Backup URL</label><input v-model="migrateData.backup_url" class="input-field" placeholder="https://example.com/backup.zip" /></div>
            <div class="flex items-center gap-2"><button :class="['toggle', migrateData.update_urls && 'on']" @click="migrateData.update_urls = !migrateData.update_urls"></button><span class="text-sm" style="color: var(--text-primary)">Update URLs after migration</span></div>
          </div>
          <div class="dialog-card-footer"><button @click="showMigrateDialog = false" class="btn btn-ghost">Cancel</button><button @click="migrateSite" :disabled="migrating || !migrateData.backup_url" class="btn btn-primary">{{ migrating ? 'Migrating...' : 'Start Migration' }}</button></div>
        </div>
      </div>
      <!-- Domain Mappings Dialog -->
      <div v-if="showDomainMappingsDialog" class="dialog-overlay" @click.self="showDomainMappingsDialog = false">
        <div class="dialog-card" style="max-width: 560px;">
          <div class="dialog-card-header"><h3 class="text-sm font-semibold" style="color: var(--text-primary)">Domain Mappings</h3><button @click="showDomainMappingsDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="close" :size="16" /></button></div>
          <div class="dialog-card-body">
            <div class="flex gap-2 mb-4">
              <input v-model="newDomainMapping" class="input-field flex-1" placeholder="example.com" @keyup.enter="addDomainMapping" />
              <button @click="addDomainMapping" :disabled="!newDomainMapping" class="btn btn-sm btn-primary">Add</button>
            </div>
            <div v-if="!domainMappings.length" class="text-sm py-4 text-center" style="color: var(--text-secondary)">No domain mappings.</div>
            <div v-else class="space-y-2">
              <div v-for="dm in domainMappings" :key="dm.domain_id || dm.id" class="flex items-center justify-between p-3 rounded-lg" style="background: var(--hover-bg)">
                <div class="flex items-center gap-2">
                  <span class="text-sm" style="color: var(--text-primary)">{{ dm.domain || dm.name }}</span>
                  <span v-if="dm.is_primary" class="badge badge-success">Primary</span>
                </div>
                <div class="flex gap-1">
                  <button v-if="!dm.is_primary" @click="setPrimaryDomainMapping(dm)" class="btn btn-sm btn-outline">Set Primary</button>
                  <button @click="deleteDomainMapping(dm)" class="btn btn-sm btn-ghost" style="color: var(--color-error)"><svg-icon name="trash" :size="14" /></button>
                </div>
              </div>
            </div>
          </div>
          <div class="dialog-card-footer"><button @click="showDomainMappingsDialog = false" class="btn btn-ghost">Close</button></div>
        </div>
      </div>
      <!-- Captures Dialog -->
      <div v-if="showCapturesDialog" class="dialog-overlay" @click.self="showCapturesDialog = false">
        <div class="dialog-card" style="max-width: 700px;">
          <div class="dialog-card-header"><h3 class="text-sm font-semibold" style="color: var(--text-primary)">Visual Captures</h3><button @click="showCapturesDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="close" :size="16" /></button></div>
          <div class="dialog-card-body" style="max-height: 70vh; overflow-y: auto;">
            <div class="flex items-center justify-between mb-4">
              <span class="text-sm" style="color: var(--text-secondary)">{{ captures.length }} captures</span>
              <button @click="captureCheck" class="btn btn-sm btn-outline"><svg-icon name="eye" :size="14" /> New Capture</button>
            </div>
            <div v-if="!captures.length" class="text-sm py-4 text-center" style="color: var(--text-secondary)">No captures yet. Request one to get started.</div>
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div v-for="cap in captures" :key="cap.id || cap.created_at" class="rounded-lg overflow-hidden" style="border: 1px solid var(--border-color)">
                <img v-if="cap.image" :src="cap.image" :alt="cap.page || 'Capture'" class="w-full" style="max-height: 200px; object-fit: cover;" />
                <div class="p-2">
                  <div class="text-xs font-medium" style="color: var(--text-primary)">{{ cap.page || '/' }}</div>
                  <div v-if="cap.created_at" class="text-xs" style="color: var(--text-secondary)">{{ prettyTimestamp(cap.created_at) }}</div>
                </div>
              </div>
            </div>
            <div class="mt-4 pt-4" style="border-top: 1px solid var(--border-color)">
              <h4 class="text-xs font-semibold uppercase tracking-wider mb-3" style="color: var(--text-secondary)">Capture Pages</h4>
              <div v-for="(pg, i) in capturePages" :key="i" class="flex gap-2 mb-2">
                <input v-model="pg.page" class="input-field flex-1" placeholder="/page-path" />
                <button @click="capturePages.splice(i, 1)" class="btn btn-sm btn-ghost" style="color: var(--color-error)"><svg-icon name="trash" :size="14" /></button>
              </div>
              <div class="flex gap-2 mt-2">
                <button @click="capturePages.push({ page: '' })" class="btn btn-sm btn-outline"><svg-icon name="plus" :size="14" /> Add Page</button>
                <button @click="saveCaptureConfig" class="btn btn-sm btn-primary">Save Config</button>
              </div>
            </div>
          </div>
          <div class="dialog-card-footer"><button @click="showCapturesDialog = false" class="btn btn-ghost">Close</button></div>
        </div>
      </div>
      <div class="px-4 pt-3"><tab-bar :tabs="tabs" v-model="activeTab" /></div>
      <div class="p-4">
        <div v-if="activeTab === 'info'" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div class="space-y-4">
            <div class="rounded-lg p-4" style="background: var(--hover-bg)">
              <h3 class="text-xs font-semibold uppercase tracking-wider mb-3" style="color: var(--text-secondary)">Site Details</h3>
              <div class="info-grid">
                <span class="info-label">WordPress</span><span class="info-value">{{ selectedEnv ? selectedEnv.core || '—' : '—' }}</span>
                <span class="info-label">Storage</span><span class="info-value">{{ selectedEnv ? formatStorage(selectedEnv.storage) : '—' }}</span>
                <span class="info-label">PHP Memory</span><span class="info-value">{{ selectedEnv && selectedEnv.php_memory ? selectedEnv.php_memory : '—' }}</span>
                <span class="info-label">Created</span><span class="info-value">{{ site.created_at ? prettyTimestamp(site.created_at) : '—' }}</span>
                <span class="info-label">Updated</span><span class="info-value">{{ site.updated_at ? prettyTimestamp(site.updated_at) : '—' }}</span>
              </div>
            </div>
            <div v-if="sharedWith.length" class="rounded-lg p-4" style="background: var(--hover-bg)">
              <h3 class="text-xs font-semibold uppercase tracking-wider mb-3" style="color: var(--text-secondary)">Shared With</h3>
              <div v-for="a in sharedWith" :key="a.account_id" class="text-sm py-1" style="color: var(--text-primary)">{{ a.name }}</div>
            </div>
            <div v-if="linkedDomains.length" class="rounded-lg p-4" style="background: var(--hover-bg)">
              <h3 class="text-xs font-semibold uppercase tracking-wider mb-3" style="color: var(--text-secondary)">DNS Zones</h3>
              <router-link v-for="d in linkedDomains" :key="d.domain_id" :to="'/domains/' + d.domain_id" class="block text-sm py-1" style="color: var(--color-primary)">{{ d.name }}</router-link>
            </div>
          </div>
          <div v-if="selectedEnv" class="space-y-4">
            <div class="rounded-lg p-4" style="background: var(--hover-bg)">
              <h3 class="text-xs font-semibold uppercase tracking-wider mb-3" style="color: var(--text-secondary)">Server Connection</h3>
              <div class="info-grid">
                <span class="info-label">Address</span><span class="info-value">{{ selectedEnv.address || '—' }}</span>
                <span class="info-label">Username</span><span class="info-value">{{ selectedEnv.username || '—' }}</span>
                <span class="info-label">Password</span>
                <span class="info-value"><span v-if="selectedEnv.password">••••••••</span><button v-if="selectedEnv.password" @click="copy(selectedEnv.password)" class="btn btn-sm btn-ghost ml-1"><svg-icon name="copy" :size="14" /></button><span v-else>—</span></span>
                <span class="info-label">Port</span><span class="info-value">{{ selectedEnv.port || '—' }}</span>
                <span class="info-label">Home Dir</span><span class="info-value">{{ selectedEnv.home_directory || '—' }}</span>
              </div>
            </div>
            <div v-if="selectedEnv.database_username" class="rounded-lg p-4" style="background: var(--hover-bg)">
              <h3 class="text-xs font-semibold uppercase tracking-wider mb-3" style="color: var(--text-secondary)">Database</h3>
              <div class="info-grid">
                <span class="info-label">DB Name</span><span class="info-value">{{ selectedEnv.database_name || '—' }}</span>
                <span class="info-label">DB User</span><span class="info-value">{{ selectedEnv.database_username || '—' }}</span>
                <span class="info-label">DB Password</span>
                <span class="info-value">••••••••<button v-if="selectedEnv.database_password" @click="copy(selectedEnv.database_password)" class="btn btn-sm btn-ghost ml-1"><svg-icon name="copy" :size="14" /></button></span>
              </div>
            </div>
            <div v-if="selectedEnv.address" class="rounded-lg p-4" style="background: var(--hover-bg)">
              <h3 class="text-xs font-semibold uppercase tracking-wider mb-3" style="color: var(--text-secondary)">Quick Copy</h3>
              <div class="flex flex-wrap gap-2">
                <button @click="copySshCommand()" class="btn btn-sm btn-outline"><svg-icon name="copy" :size="14" /> SSH Command</button>
                <button @click="copySftpCommand()" class="btn btn-sm btn-outline"><svg-icon name="copy" :size="14" /> SFTP Command</button>
                <button v-if="selectedEnv.database_username" @click="copyDbInfo()" class="btn btn-sm btn-outline"><svg-icon name="copy" :size="14" /> DB Info</button>
                <button @click="fetchPhpmyadmin()" :disabled="fetchingPhpmyadmin" class="btn btn-sm btn-outline"><svg-icon name="database" :size="14" /> {{ fetchingPhpmyadmin ? 'Opening...' : 'PHPMyAdmin' }}</button>
              </div>
            </div>
            <div v-if="selectedEnv.ssh" class="rounded-lg p-4" style="background: var(--hover-bg)">
              <h3 class="text-xs font-semibold uppercase tracking-wider mb-3" style="color: var(--text-secondary)">SSH</h3>
              <div class="flex items-center gap-2">
                <code class="text-xs flex-1 p-2 rounded overflow-x-auto" style="background: var(--bg-page); color: var(--text-primary)">{{ selectedEnv.ssh }}</code>
                <button @click="copy(selectedEnv.ssh)" class="btn btn-sm btn-ghost"><svg-icon name="copy" :size="14" /></button>
              </div>
            </div>
            <div v-if="role === 'administrator'" class="rounded-lg p-4" style="background: var(--hover-bg)">
              <h3 class="text-xs font-semibold uppercase tracking-wider mb-3" style="color: var(--text-secondary)">Site Actions</h3>
              <div class="flex flex-wrap gap-2">
                <button @click="toggleSiteStatus()" class="btn btn-sm btn-outline">
                  <svg-icon name="power" :size="14" /> {{ selectedEnv && selectedEnv.site_active !== false && selectedEnv.site_active !== 0 ? 'Deactivate' : 'Activate' }}
                </button>
                <button @click="showLaunchDialog = true; launchData.domain = ''" class="btn btn-sm btn-outline"><svg-icon name="rocket" :size="14" /> Launch</button>
                <button @click="showCopyDialog = true; copyData.destination_id = ''" class="btn btn-sm btn-outline"><svg-icon name="copy" :size="14" /> Copy</button>
                <button @click="showMigrateDialog = true; migrateData.backup_url = ''" class="btn btn-sm btn-outline"><svg-icon name="download" :size="14" /> Migrate</button>
                <button @click="pushToOther" :disabled="pushing || environments.length < 2" class="btn btn-sm btn-outline"><svg-icon name="arrowRight" :size="14" /> {{ pushing ? 'Pushing...' : 'Push to Other' }}</button>
                <button @click="fetchDomainMappings" :disabled="domainMappingsLoading" class="btn btn-sm btn-outline"><svg-icon name="globe" :size="14" /> Domain Mappings</button>
                <button @click="showCaptures" :disabled="capturesLoading" class="btn btn-sm btn-outline"><svg-icon name="eye" :size="14" /> Captures</button>
              </div>
            </div>
          </div>
        </div>
        <div v-if="activeTab === 'addons'">
          <div class="mb-6">
            <div class="flex items-center justify-between mb-3">
              <h3 class="text-xs font-semibold uppercase tracking-wider" style="color: var(--text-secondary)">Plugins ({{ plugins.length }})</h3>
              <button @click="showAddPluginDialog = true" class="btn btn-sm btn-outline"><svg-icon name="plus" :size="14" /> Install Plugin</button>
            </div>
            <div v-if="!plugins.length" class="text-sm py-4" style="color: var(--text-secondary)">No plugin data available.</div>
            <table v-else class="data-table">
              <thead><tr><th>Name</th><th style="width:90px">Status</th><th style="width:90px">Version</th><th v-if="role === 'administrator'" style="width:120px">Actions</th></tr></thead>
              <tbody><tr v-for="p in plugins" :key="p.name">
                <td class="text-sm">{{ p.title || p.name }}</td>
                <td><span :class="['badge', p.status==='active'?'badge-success':'badge-default']">{{ p.status }}</span></td>
                <td class="text-sm" style="color:var(--text-secondary)">{{ p.version }}</td>
                <td v-if="role === 'administrator'">
                  <div class="flex gap-1">
                    <button v-if="p.status === 'active'" @click="togglePlugin(p.name, 'deactivate')" class="btn btn-sm btn-ghost" title="Deactivate"><svg-icon name="stop" :size="14" /></button>
                    <button v-else @click="togglePlugin(p.name, 'activate')" class="btn btn-sm btn-ghost" title="Activate"><svg-icon name="play" :size="14" /></button>
                    <button @click="deletePlugin(p.name)" class="btn btn-sm btn-ghost" title="Delete" style="color: var(--color-error)"><svg-icon name="trash" :size="14" /></button>
                  </div>
                </td>
              </tr></tbody>
            </table>
          </div>
          <div>
            <div class="flex items-center justify-between mb-3">
              <h3 class="text-xs font-semibold uppercase tracking-wider" style="color: var(--text-secondary)">Themes ({{ themes.length }})</h3>
              <button @click="showAddThemeDialog = true" class="btn btn-sm btn-outline"><svg-icon name="plus" :size="14" /> Install Theme</button>
            </div>
            <div v-if="!themes.length" class="text-sm py-4" style="color: var(--text-secondary)">No theme data available.</div>
            <table v-else class="data-table">
              <thead><tr><th>Name</th><th style="width:90px">Status</th><th style="width:90px">Version</th><th v-if="role === 'administrator'" style="width:120px">Actions</th></tr></thead>
              <tbody><tr v-for="t in themes" :key="t.name">
                <td class="text-sm">{{ t.title || t.name }}</td>
                <td><span :class="['badge', t.status==='active'?'badge-success':'badge-default']">{{ t.status }}</span></td>
                <td class="text-sm" style="color:var(--text-secondary)">{{ t.version }}</td>
                <td v-if="role === 'administrator'">
                  <div class="flex gap-1">
                    <button v-if="t.status !== 'active'" @click="activateTheme(t.name)" class="btn btn-sm btn-ghost" title="Activate"><svg-icon name="play" :size="14" /></button>
                    <button v-if="t.status !== 'active'" @click="deleteTheme(t.name)" class="btn btn-sm btn-ghost" title="Delete" style="color: var(--color-error)"><svg-icon name="trash" :size="14" /></button>
                  </div>
                </td>
              </tr></tbody>
            </table>
          </div>
          <!-- Install Plugin Dialog -->
          <div v-if="showAddPluginDialog" class="dialog-overlay" @click.self="showAddPluginDialog = false">
            <div class="dialog-card" style="max-width: 560px;">
              <div class="dialog-card-header">
                <h3 class="text-sm font-semibold" style="color: var(--text-primary)">Install Plugin</h3>
                <button @click="showAddPluginDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="close" :size="16" /></button>
              </div>
              <div class="dialog-card-body">
                <input v-model="pluginSearch" class="input-field mb-4" placeholder="Search WordPress.org plugins..." />
                <div v-if="pluginSearchLoading" class="flex justify-center py-4"><div class="animate-spin rounded-full h-6 w-6 border-b-2" style="border-color: var(--color-primary)"></div></div>
                <div v-else-if="pluginSearchResults.length" class="space-y-2" style="max-height: 300px; overflow-y: auto;">
                  <div v-for="p in pluginSearchResults" :key="p.slug" class="flex items-center justify-between p-3 rounded-lg" style="background: var(--hover-bg)">
                    <div class="flex-1 min-w-0 mr-3">
                      <div class="text-sm font-medium truncate" style="color: var(--text-primary)">{{ p.name }}</div>
                      <div class="text-xs truncate" style="color: var(--text-secondary)">{{ p.short_description || p.slug }}</div>
                    </div>
                    <button @click="installPlugin(p)" class="btn btn-sm btn-primary flex-shrink-0">Install</button>
                  </div>
                </div>
                <div v-else-if="pluginSearch.length >= 2" class="text-sm py-4 text-center" style="color: var(--text-secondary)">No results found.</div>
              </div>
            </div>
          </div>
          <!-- Install Theme Dialog -->
          <div v-if="showAddThemeDialog" class="dialog-overlay" @click.self="showAddThemeDialog = false">
            <div class="dialog-card" style="max-width: 560px;">
              <div class="dialog-card-header">
                <h3 class="text-sm font-semibold" style="color: var(--text-primary)">Install Theme</h3>
                <button @click="showAddThemeDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="close" :size="16" /></button>
              </div>
              <div class="dialog-card-body">
                <input v-model="themeSearch" class="input-field mb-4" placeholder="Search WordPress.org themes..." />
                <div v-if="themeSearchLoading" class="flex justify-center py-4"><div class="animate-spin rounded-full h-6 w-6 border-b-2" style="border-color: var(--color-primary)"></div></div>
                <div v-else-if="themeSearchResults.length" class="space-y-2" style="max-height: 300px; overflow-y: auto;">
                  <div v-for="t in themeSearchResults" :key="t.slug" class="flex items-center justify-between p-3 rounded-lg" style="background: var(--hover-bg)">
                    <div class="flex-1 min-w-0 mr-3">
                      <div class="text-sm font-medium truncate" style="color: var(--text-primary)">{{ t.name }}</div>
                      <div class="text-xs truncate" style="color: var(--text-secondary)">{{ t.slug }}</div>
                    </div>
                    <button @click="installTheme(t)" class="btn btn-sm btn-primary flex-shrink-0">Install</button>
                  </div>
                </div>
                <div v-else-if="themeSearch.length >= 2" class="text-sm py-4 text-center" style="color: var(--text-secondary)">No results found.</div>
              </div>
            </div>
          </div>
        </div>
        <div v-if="activeTab === 'users'">
          <div v-if="!selectedEnv || !selectedEnv.users || selectedEnv.users === 'Loading'" class="text-sm py-4" style="color: var(--text-secondary)">Users data not available. Sync the site to load users.</div>
          <table v-else class="data-table">
            <thead><tr><th>Username</th><th>Email</th><th style="width:100px">Role</th><th style="width:80px">Actions</th></tr></thead>
            <tbody>
              <tr v-for="u in (Array.isArray(selectedEnv.users) ? selectedEnv.users : [])" :key="u.user_login">
                <td class="text-sm">{{ u.user_login }}</td>
                <td class="text-sm">{{ u.user_email }}</td>
                <td><span class="badge badge-default">{{ u.roles }}</span></td>
                <td>
                  <button @click="magicLogin(u.user_id || u.ID)" class="btn btn-sm btn-ghost" title="Login as this user">
                    <svg-icon name="login" :size="14" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="activeTab === 'updates'">
          <div v-if="updateLogsLoading" class="flex justify-center py-8"><div class="animate-spin rounded-full h-6 w-6 border-b-2" style="border-color: var(--color-primary)"></div></div>
          <div v-else-if="!updateLogs.length" class="text-sm py-4" style="color: var(--text-secondary)">No update logs.</div>
          <div v-else class="space-y-2">
            <div v-for="log in updateLogs" :key="log.hash_before || log.update_log_id" class="rounded-lg" style="background: var(--hover-bg)">
              <div class="p-3 cursor-pointer" @click="toggleUpdateLog(log)">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <svg-icon :name="expandedUpdateLog === log ? 'chevronDown' : 'chevronRight'" :size="14" style="color: var(--text-secondary)" />
                    <div class="text-sm font-medium" style="color: var(--text-primary)">
                      <span v-if="log.core !== log.core_previous">Core {{ log.core_previous }} &rarr; {{ log.core }}</span>
                      <span v-else>WordPress {{ log.core }}</span>
                    </div>
                    <span v-if="log.status" :class="'badge badge-' + (log.status === 'success' ? 'success' : 'error')">{{ log.status }}</span>
                  </div>
                  <div class="flex items-center gap-3">
                    <span v-if="log.plugins_changed" class="text-xs" style="color: var(--text-secondary)">{{ log.plugins_changed }} plugin{{ log.plugins_changed > 1 ? 's' : '' }}</span>
                    <span v-if="log.themes_changed" class="text-xs" style="color: var(--text-secondary)">{{ log.themes_changed }} theme{{ log.themes_changed > 1 ? 's' : '' }}</span>
                    <span v-if="log.created_at" class="text-xs" style="color: var(--text-secondary)">{{ prettyTimestampEpoch(log.created_at) }}</span>
                  </div>
                </div>
              </div>
              <!-- Expanded detail -->
              <div v-if="expandedUpdateLog === log" class="px-3 pb-3" style="border-top: 1px solid var(--border-color)">
                <div v-if="updateLogDetail.loading" class="flex justify-center py-4"><div class="animate-spin rounded-full h-5 w-5 border-b-2" style="border-color: var(--color-primary)"></div></div>
                <div v-else>
                  <!-- Core change -->
                  <div v-if="updateLogDetail.core_previous && updateLogDetail.core && updateLogDetail.core !== updateLogDetail.core_previous" class="py-2 text-sm" style="border-bottom: 1px solid var(--border-color)">
                    <span class="font-medium">WordPress Core:</span> {{ updateLogDetail.core_previous }} &rarr; {{ updateLogDetail.core }}
                  </div>
                  <!-- Plugins -->
                  <div v-if="updateLogDetail.plugins.length" class="py-2">
                    <div class="text-xs font-semibold mb-2" style="color: var(--text-secondary); text-transform: uppercase">Plugins</div>
                    <table class="w-full text-sm">
                      <thead><tr style="border-bottom: 1px solid var(--border-color)"><th class="text-left py-1 font-medium text-xs" style="color: var(--text-secondary)">Name</th><th class="text-left py-1 font-medium text-xs" style="color: var(--text-secondary)">Version</th><th class="text-left py-1 font-medium text-xs" style="color: var(--text-secondary)">Status</th></tr></thead>
                      <tbody>
                        <tr v-for="p in updateLogDetail.plugins" :key="p.name" style="border-bottom: 1px solid var(--border-color)">
                          <td class="py-1.5">{{ p.title || p.name }}</td>
                          <td class="py-1.5">
                            <span v-if="p.changed_version && p.changed_version !== p.version"><span style="color: var(--text-secondary)">{{ p.changed_version }}</span> &rarr; </span>{{ p.version }}
                          </td>
                          <td class="py-1.5">
                            <span v-if="p.changed" class="badge badge-warning">updated</span>
                            <span v-else class="badge badge-default">{{ p.status }}</span>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <!-- Deleted plugins -->
                  <div v-if="updateLogDetail.plugins_deleted.length" class="py-2">
                    <div class="text-xs font-semibold mb-2" style="color: var(--color-error); text-transform: uppercase">Plugins Removed</div>
                    <div v-for="p in updateLogDetail.plugins_deleted" :key="p.name" class="text-sm py-1">{{ p.title || p.name }} <span class="badge badge-error">deleted</span></div>
                  </div>
                  <!-- Themes -->
                  <div v-if="updateLogDetail.themes.length" class="py-2">
                    <div class="text-xs font-semibold mb-2" style="color: var(--text-secondary); text-transform: uppercase">Themes</div>
                    <table class="w-full text-sm">
                      <thead><tr style="border-bottom: 1px solid var(--border-color)"><th class="text-left py-1 font-medium text-xs" style="color: var(--text-secondary)">Name</th><th class="text-left py-1 font-medium text-xs" style="color: var(--text-secondary)">Version</th><th class="text-left py-1 font-medium text-xs" style="color: var(--text-secondary)">Status</th></tr></thead>
                      <tbody>
                        <tr v-for="t in updateLogDetail.themes" :key="t.name" style="border-bottom: 1px solid var(--border-color)">
                          <td class="py-1.5">{{ t.title || t.name }}</td>
                          <td class="py-1.5">
                            <span v-if="t.changed_version && t.changed_version !== t.version"><span style="color: var(--text-secondary)">{{ t.changed_version }}</span> &rarr; </span>{{ t.version }}
                          </td>
                          <td class="py-1.5">
                            <span v-if="t.changed" class="badge badge-warning">updated</span>
                            <span v-else class="badge badge-default">{{ t.status }}</span>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <!-- Deleted themes -->
                  <div v-if="updateLogDetail.themes_deleted.length" class="py-2">
                    <div class="text-xs font-semibold mb-2" style="color: var(--color-error); text-transform: uppercase">Themes Removed</div>
                    <div v-for="t in updateLogDetail.themes_deleted" :key="t.name" class="text-sm py-1">{{ t.title || t.name }} <span class="badge badge-error">deleted</span></div>
                  </div>
                  <!-- View Changed Files -->
                  <div class="pt-2">
                    <button @click="viewUpdateLogFiles(log)" class="btn btn-sm btn-outline" :disabled="updateLogFilesLoading">
                      {{ updateLogFilesLoading ? 'Loading...' : 'View Changed Files' }}
                    </button>
                    <div v-if="updateLogFiles.length" class="mt-2" style="max-height: 300px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 6px">
                      <div v-for="f in updateLogFiles" :key="f" @click="viewUpdateFileDiff(log.hash_after, f)" class="px-3 py-1.5 text-xs cursor-pointer flex items-center gap-2" style="border-bottom: 1px solid var(--border-color); font-family: monospace" :style="{ background: 'transparent' }" @mouseenter="$event.target.style.background='var(--active-bg)'" @mouseleave="$event.target.style.background='transparent'">
                        <span :style="{ color: f.startsWith('M') ? 'var(--color-warning)' : f.startsWith('A') ? 'var(--color-success)' : f.startsWith('D') ? 'var(--color-error)' : 'var(--text-secondary)' }">{{ f.startsWith('M') ? 'Modified' : f.startsWith('A') ? 'Added' : f.startsWith('D') ? 'Deleted' : 'Changed' }}</span>
                        <span>{{ f.replace(/^[MADR]\t/, '') }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- Update File Diff Dialog -->
          <div v-if="showUpdateFileDiffDialog" class="dialog-overlay" @mousedown.self="showUpdateFileDiffDialog = false">
            <div class="dialog-card" style="width: 800px">
              <div class="dialog-header">
                <h3 class="dialog-title text-xs" style="font-family: monospace">{{ updateFileDiff.fileName.replace(/^[MADR]\t/, '') }}</h3>
                <button @click="showUpdateFileDiffDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="x" :size="18" /></button>
              </div>
              <div class="dialog-body" style="max-height: 70vh; overflow: auto; padding: 0">
                <div v-if="updateFileDiff.loading" class="flex justify-center py-8"><div class="animate-spin rounded-full h-6 w-6 border-b-2" style="border-color: var(--color-primary)"></div></div>
                <div v-else class="diff-view" v-html="updateFileDiff.response"></div>
              </div>
            </div>
          </div>
        </div>
        <div v-if="activeTab === 'backups'">
          <div class="flex gap-2 mb-4">
            <button :class="['btn btn-sm', backupSubTab === 'backups' ? 'btn-primary' : 'btn-outline']" @click="backupSubTab = 'backups'">Backups</button>
            <button :class="['btn btn-sm', backupSubTab === 'quicksaves' ? 'btn-primary' : 'btn-outline']" @click="backupSubTab = 'quicksaves'">Quicksaves</button>
            <button :class="['btn btn-sm', backupSubTab === 'snapshots' ? 'btn-primary' : 'btn-outline']" @click="backupSubTab = 'snapshots'">Snapshots</button>
          </div>
          <!-- Backups sub-tab -->
          <div v-if="backupSubTab === 'backups'">
            <div v-if="backupsLoading" class="flex justify-center py-8"><div class="animate-spin rounded-full h-6 w-6 border-b-2" style="border-color: var(--color-primary)"></div></div>
            <div v-else-if="!backups.length" class="text-sm py-4" style="color: var(--text-secondary)">No backups found.</div>
            <div v-else>
              <div v-for="b in backups" :key="b.backup_id || b.id" class="flex items-center justify-between p-3 mb-2 rounded-lg" style="background: var(--hover-bg)">
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-medium" style="color: var(--text-primary)">{{ b.name || 'Backup' }}</div>
                  <div class="text-xs mt-1" style="color: var(--text-secondary)">{{ b.created_at ? prettyTimestampEpoch(b.created_at) : '' }}<span v-if="b.size" class="ml-2">{{ formatStorage(b.size) }}</span></div>
                </div>
                <div v-if="role === 'administrator'" class="flex gap-2 flex-shrink-0">
                  <button @click="downloadBackup(b)" class="btn btn-sm btn-outline"><svg-icon name="download" :size="14" /> Download</button>
                  <button @click="restoreBackup(b)" class="btn btn-sm btn-outline"><svg-icon name="refresh" :size="14" /> Restore</button>
                </div>
              </div>
            </div>
          </div>
          <!-- Quicksaves sub-tab -->
          <div v-if="backupSubTab === 'quicksaves'">
            <div class="flex items-center gap-3 mb-4">
              <div class="search-wrapper flex-1"><svg-icon name="search" :size="16" class="search-icon" /><input v-model="quicksaveSearch" type="text" placeholder="Search quicksaves..." class="input-field" style="width: 100%" /></div>
            </div>
            <div v-if="quicksavesLoading" class="flex justify-center py-8"><div class="animate-spin rounded-full h-6 w-6 border-b-2" style="border-color: var(--color-primary)"></div></div>
            <div v-else-if="!filteredQuicksaves.length" class="text-sm py-4" style="color: var(--text-secondary)">No quicksaves found.</div>
            <div v-else>
              <div v-for="qs in filteredQuicksaves" :key="qs.git_hash || qs.hash" class="mb-2 rounded-lg" style="background: var(--hover-bg)">
                <div class="p-3">
                  <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-3">
                      <div class="text-xs" style="color: var(--text-secondary)">{{ prettyTimestampEpoch(qs.created_at) }}</div>
                      <span v-if="qs.core" class="badge badge-default" style="font-size: 0.625rem;">WP {{ qs.core }}</span>
                      <span v-if="qs.plugin_count" class="text-xs" style="color: var(--text-secondary)">{{ qs.plugin_count }} plugins</span>
                      <span v-if="qs.theme_count" class="text-xs" style="color: var(--text-secondary)">{{ qs.theme_count }} themes</span>
                    </div>
                    <div class="flex items-center gap-2">
                      <button @click.stop="toggleQuicksaveExpand(qs)" class="btn btn-sm btn-ghost" :style="expandedQuicksave === (qs.git_hash || qs.hash) ? 'color: var(--color-primary)' : ''">
                        <svg-icon name="funnel" :size="14" /> Files
                      </button>
                      <button v-if="role === 'administrator'" @click="rollbackQuicksave(qs)" class="btn btn-sm btn-outline"><svg-icon name="refresh" :size="14" /> Rollback</button>
                    </div>
                  </div>
                  <div v-if="qs.changes" class="text-xs" style="color: var(--text-primary); white-space: pre-wrap; font-family: monospace;">{{ typeof qs.changes === 'string' ? qs.changes : JSON.stringify(qs.changes, null, 2) }}</div>
                  <div v-else-if="qs.git_status" class="text-xs" style="color: var(--text-primary); white-space: pre-wrap; font-family: monospace;">{{ typeof qs.git_status === 'string' ? qs.git_status : JSON.stringify(qs.git_status, null, 2) }}</div>
                </div>
                <!-- Expanded file list -->
                <div v-if="expandedQuicksave === (qs.git_hash || qs.hash)" style="border-top: 1px solid var(--border-color);">
                  <div class="px-3 pt-3 pb-2 flex items-center gap-3">
                    <div class="search-wrapper flex-1"><svg-icon name="search" :size="14" class="search-icon" /><input v-model="quicksaveFileSearch" @input="filterQuicksaveFiles()" type="text" placeholder="Filter files..." class="input-field" style="width: 100%; font-size: 0.75rem; padding: 4px 8px 4px 28px;" /></div>
                    <span class="text-xs flex-shrink-0" style="color: var(--text-secondary)">{{ quicksaveFilesFiltered.length }} files</span>
                  </div>
                  <div v-if="quicksaveFilesLoading" class="flex justify-center py-4"><div class="animate-spin rounded-full h-5 w-5 border-b-2" style="border-color: var(--color-primary)"></div></div>
                  <div v-else-if="!quicksaveFilesFiltered.length" class="px-3 pb-3 text-xs" style="color: var(--text-secondary)">No changed files found.</div>
                  <div v-else style="max-height: 400px; overflow-y: auto;">
                    <div v-for="(file, fi) in quicksaveFilesFiltered" :key="fi"
                      @click="quicksaveFileDiff(qs.git_hash || qs.hash, file)"
                      class="flex items-center gap-2 px-3 py-1.5 cursor-pointer text-xs"
                      style="font-family: monospace;"
                      @mouseenter="$event.target.style.background='var(--hover-bg)'"
                      @mouseleave="$event.target.style.background='transparent'">
                      <span class="flex-shrink-0 font-semibold" :style="'color:' + getFileStatusColor(file)" style="width: 60px;">{{ getFileStatus(file) }}</span>
                      <span class="truncate" style="color: var(--text-primary)">{{ getCleanFileName(file) }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- File Diff Dialog -->
          <div v-if="showFileDiffDialog" class="dialog-overlay" @click.self="showFileDiffDialog = false" style="z-index: 100;">
            <div class="dialog-card" style="max-width: 900px; width: 95vw; max-height: 90vh; display: flex; flex-direction: column;">
              <div class="dialog-card-header">
                <div class="flex-1 min-w-0">
                  <h3 class="text-sm font-semibold truncate" style="color: var(--text-primary); font-family: monospace;">{{ fileDiff.fileName }}</h3>
                  <div v-if="fileDiff.quicksave" class="text-xs mt-1" style="color: var(--text-secondary)">{{ prettyTimestampEpoch(fileDiff.quicksave.created_at) }}</div>
                </div>
                <div class="flex items-center gap-2">
                  <button v-if="role === 'administrator'" @click="quicksaveFileRestore()" class="btn btn-sm btn-outline">Restore this file</button>
                  <button @click="showFileDiffDialog = false" class="btn-ghost p-1 rounded"><svg-icon name="close" :size="16" /></button>
                </div>
              </div>
              <div style="flex: 1; overflow-y: auto; padding: 0;">
                <div v-if="fileDiff.loading" class="flex justify-center py-8"><div class="animate-spin rounded-full h-6 w-6 border-b-2" style="border-color: var(--color-primary)"></div></div>
                <div v-else class="diff-view" v-html="fileDiff.response"></div>
              </div>
            </div>
          </div>
          <!-- Snapshots sub-tab -->
          <div v-if="backupSubTab === 'snapshots'">
            <div class="flex items-center justify-between mb-4">
              <span class="text-sm" style="color: var(--text-secondary)">{{ snapshots.length }} snapshots</span>
              <button v-if="role === 'administrator'" @click="createSnapshot()" class="btn btn-sm btn-primary"><svg-icon name="plus" :size="14" /> Create Snapshot</button>
            </div>
            <div v-if="snapshotsLoading" class="flex justify-center py-8"><div class="animate-spin rounded-full h-6 w-6 border-b-2" style="border-color: var(--color-primary)"></div></div>
            <div v-else-if="!snapshots.length" class="text-sm py-4" style="color: var(--text-secondary)">No snapshots found.</div>
            <div v-else>
              <div v-for="s in snapshots" :key="s.snapshot_id || s.id" class="flex items-center justify-between p-3 mb-2 rounded-lg" style="background: var(--hover-bg)">
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-medium" style="color: var(--text-primary)">{{ s.notes || s.name || ('Snapshot #' + s.snapshot_id) }}</div>
                  <div class="text-xs mt-1" style="color: var(--text-secondary)">{{ s.created_at ? prettyTimestampEpoch(s.created_at) : '' }}<span v-if="s.email" class="ml-2">{{ s.email }}</span><span v-if="s.storage" class="ml-2">{{ formatStorage(s.storage) }}</span></div>
                </div>
                <div v-if="role === 'administrator'" class="flex gap-2 flex-shrink-0">
                  <span v-if="s.token" class="badge badge-success mr-1">Download ready</span>
                  <button @click="downloadBackup(s)" class="btn btn-sm btn-outline"><svg-icon name="download" :size="14" /></button>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- Stats Tab -->
        <div v-if="activeTab === 'stats'">
          <div v-if="!selectedEnv" class="text-sm py-4" style="color: var(--text-secondary)">No environment data available.</div>
          <div v-else>
            <!-- Fathom Analytics Controls -->
            <div class="flex items-center gap-3 mb-4 flex-wrap">
              <select v-if="fathomTrackers.length > 1" v-model="fathomId" class="select-field" style="width: auto; max-width: 220px;">
                <option value="">All domains</option>
                <option v-for="t in fathomTrackers" :key="t.code" :value="t.code">{{ t.domain }}</option>
              </select>
              <select v-model="fathomGrouping" class="select-field" style="width: auto; max-width: 140px;">
                <option v-for="g in ['Hour', 'Day', 'Month', 'Year']" :key="g" :value="g">{{ g }}</option>
              </select>
              <input v-model="fathomFromAt" type="date" class="input-field" style="width: auto; max-width: 160px;" />
              <input v-model="fathomToAt" type="date" class="input-field" style="width: auto; max-width: 160px;" />
              <button @click="fetchFathomStats" :disabled="fathomLoading" class="btn btn-sm btn-primary">{{ fathomLoading ? 'Loading...' : 'Update' }}</button>
            </div>

            <!-- Fathom Loading / Error -->
            <div v-if="fathomLoading" class="flex justify-center py-8"><div class="animate-spin rounded-full h-6 w-6 border-b-2" style="border-color: var(--color-primary)"></div></div>
            <div v-else-if="fathomError" class="rounded-lg p-4 mb-4 text-sm" style="background: color-mix(in srgb, var(--color-error) 10%, transparent); color: var(--color-error);">{{ fathomError }}</div>

            <!-- Fathom Summary Cards -->
            <div v-if="fathomStats && fathomStats.summary" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
              <div class="rounded-lg p-4 text-center" style="background: var(--hover-bg)">
                <div class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-secondary)">Unique Visitors</div>
                <div class="text-2xl font-light" style="color: var(--text-primary)">{{ formatLargeNumbers(fathomStats.summary.visits) }}</div>
              </div>
              <div class="rounded-lg p-4 text-center" style="background: var(--hover-bg)">
                <div class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-secondary)">Pageviews</div>
                <div class="text-2xl font-light" style="color: var(--text-primary)">{{ formatLargeNumbers(fathomStats.summary.pageviews) }}</div>
              </div>
              <div class="rounded-lg p-4 text-center" style="background: var(--hover-bg)">
                <div class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-secondary)">Avg Time On Site</div>
                <div class="text-2xl font-light" style="color: var(--text-primary)">{{ formatTime(fathomStats.summary.avg_duration) }}</div>
              </div>
              <div class="rounded-lg p-4 text-center" style="background: var(--hover-bg)">
                <div class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-secondary)">Bounce Rate</div>
                <div class="text-2xl font-light" style="color: var(--text-primary)">{{ formatPercent(fathomStats.summary.bounce_rate) }}</div>
              </div>
            </div>

            <!-- Fathom Chart -->
            <div v-if="fathomStats && fathomStats.items && fathomStats.items.length" class="mb-6">
              <div ref="fathomChartEl" class="fathom-chart"></div>
            </div>

            <!-- Fathom Data Table -->
            <div v-if="fathomStats && fathomStats.items && fathomStats.items.length" class="mb-6">
              <h3 class="text-xs font-semibold uppercase tracking-wider mb-3" style="color: var(--text-secondary)">Traffic Data</h3>
              <div style="max-height: 400px; overflow-y: auto;">
                <table class="data-table">
                  <thead><tr><th>Date</th><th style="width:100px">Visitors</th><th style="width:100px">Pageviews</th><th style="width:100px">Avg Duration</th><th style="width:100px">Bounce Rate</th></tr></thead>
                  <tbody>
                    <tr v-for="item in fathomStats.items" :key="item.date">
                      <td class="text-sm">{{ item.date }}</td>
                      <td class="text-sm" style="color: var(--text-secondary)">{{ formatLargeNumbers(item.visits) }}</td>
                      <td class="text-sm" style="color: var(--text-secondary)">{{ formatLargeNumbers(item.pageviews) }}</td>
                      <td class="text-sm" style="color: var(--text-secondary)">{{ formatTime(item.avg_duration) }}</td>
                      <td class="text-sm" style="color: var(--text-secondary)">{{ formatPercent(item.bounce_rate) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Environment Metrics -->
            <h3 class="text-xs font-semibold uppercase tracking-wider mb-3" style="color: var(--text-secondary)">Environment Details</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div class="rounded-lg p-4 text-center" style="background: var(--hover-bg)">
                <div class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-secondary)">Visits</div>
                <div class="text-lg font-semibold" style="color: var(--text-primary)">{{ formatLargeNumbers(selectedEnv.visits) }}</div>
              </div>
              <div class="rounded-lg p-4 text-center" style="background: var(--hover-bg)">
                <div class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-secondary)">Storage</div>
                <div class="text-lg font-semibold" style="color: var(--text-primary)">{{ formatStorage(selectedEnv.storage) }}</div>
              </div>
              <div class="rounded-lg p-4 text-center" style="background: var(--hover-bg)">
                <div class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-secondary)">WordPress</div>
                <div class="text-lg font-semibold" style="color: var(--text-primary)">{{ selectedEnv.core || '—' }}</div>
              </div>
              <div class="rounded-lg p-4 text-center" style="background: var(--hover-bg)">
                <div class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-secondary)">PHP Memory</div>
                <div class="text-lg font-semibold" style="color: var(--text-primary)">{{ selectedEnv.php_memory || '—' }}</div>
              </div>
              <div v-if="selectedEnv.subsite_count" class="rounded-lg p-4 text-center" style="background: var(--hover-bg)">
                <div class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-secondary)">Subsites</div>
                <div class="text-lg font-semibold" style="color: var(--text-primary)">{{ selectedEnv.subsite_count }}</div>
              </div>
              <div v-if="selectedEnv.captures" class="rounded-lg p-4 text-center" style="background: var(--hover-bg)">
                <div class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-secondary)">Captures</div>
                <div class="text-lg font-semibold" style="color: var(--text-primary)">{{ selectedEnv.captures }}</div>
              </div>
            </div>
          </div>
        </div>
        <!-- Logs Tab -->
        <div v-if="activeTab === 'logs'">
          <div class="flex items-center gap-3 mb-4">
            <select v-model="logFile" class="select-field" style="width: auto; max-width: 200px;">
              <option value="error.log">error.log</option>
              <option value="access.log">access.log</option>
            </select>
            <button @click="fetchLog" :disabled="logLoading" class="btn btn-sm btn-primary">{{ logLoading ? 'Loading...' : 'Fetch Log' }}</button>
          </div>
          <div v-if="logLoading" class="flex justify-center py-8"><div class="animate-spin rounded-full h-6 w-6 border-b-2" style="border-color: var(--color-primary)"></div></div>
          <pre v-else-if="logContent" class="rounded-lg p-4 text-xs overflow-x-auto" style="background: var(--hover-bg); color: var(--text-primary); font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, monospace; max-height: 500px; overflow-y: auto; white-space: pre-wrap; word-break: break-all;">{{ logContent }}</pre>
          <div v-else class="text-sm py-4" style="color: var(--text-secondary)">Select a log file and click "Fetch Log" to view contents.</div>
        </div>
        <!-- Scripts Tab -->
        <div v-if="activeTab === 'scripts'">
          <div class="space-y-4">
            <div>
              <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Recipe</label>
              <select v-model="selectedRecipe" class="select-field" style="max-width: 400px;">
                <option value="">Custom code...</option>
                <option v-for="r in recipes" :key="r.recipe_id || r.id" :value="r.recipe_id || r.id">{{ r.title }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Code</label>
              <textarea v-model="scriptCode" class="textarea-field" style="font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, monospace; min-height: 160px; font-size: 0.8125rem;" placeholder="Enter PHP or WP-CLI code..."></textarea>
            </div>
            <button @click="runScript" :disabled="scriptLoading || !scriptCode" class="btn btn-primary">{{ scriptLoading ? 'Running...' : 'Run Script' }}</button>
            <div v-if="scriptLoading" class="flex justify-center py-4"><div class="animate-spin rounded-full h-6 w-6 border-b-2" style="border-color: var(--color-primary)"></div></div>
            <pre v-if="scriptOutput" class="rounded-lg p-4 text-xs overflow-x-auto" style="background: var(--hover-bg); color: var(--text-primary); font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, monospace; max-height: 400px; overflow-y: auto; white-space: pre-wrap; word-break: break-all;">{{ scriptOutput }}</pre>
          </div>
        </div>
        <!-- Modules Tab (admin) -->
        <div v-if="activeTab === 'modules'">
          <div v-if="!selectedEnv" class="text-sm py-4" style="color: var(--text-secondary)">No environment data available.</div>
          <div v-else class="space-y-4" style="max-width: 500px;">
            <div class="flex items-center justify-between p-4 rounded-lg" style="background: var(--hover-bg)">
              <div>
                <div class="text-sm font-medium" style="color: var(--text-primary)">Monitoring</div>
                <div class="text-xs" style="color: var(--text-secondary)">Track site uptime and performance</div>
              </div>
              <button :class="['toggle', selectedEnv.monitor && 'on']" @click="selectedEnv.monitor = !selectedEnv.monitor"></button>
            </div>
            <div class="flex items-center justify-between p-4 rounded-lg" style="background: var(--hover-bg)">
              <div>
                <div class="text-sm font-medium" style="color: var(--text-primary)">Managed Updates</div>
                <div class="text-xs" style="color: var(--text-secondary)">Automatically apply plugin and theme updates</div>
              </div>
              <button :class="['toggle', selectedEnv.updates_enabled && 'on']" @click="selectedEnv.updates_enabled = !selectedEnv.updates_enabled"></button>
            </div>
            <div v-if="selectedEnv.updates_exclude" class="rounded-lg p-4" style="background: var(--hover-bg)">
              <h3 class="text-xs font-semibold uppercase tracking-wider mb-2" style="color: var(--text-secondary)">Update Exclusions</h3>
              <div class="text-sm" style="color: var(--text-primary); white-space: pre-wrap;">{{ selectedEnv.updates_exclude }}</div>
            </div>
          </div>
        </div>
        <!-- Timeline Tab (admin) -->
        <div v-if="activeTab === 'timeline'">
          <div v-if="timelineLoading" class="flex justify-center py-8"><div class="animate-spin rounded-full h-6 w-6 border-b-2" style="border-color: var(--color-primary)"></div></div>
          <div v-else-if="!timelineLogs.length" class="text-sm py-4" style="color: var(--text-secondary)">No timeline activity.</div>
          <table v-else class="data-table">
            <thead><tr><th style="width:140px">Date</th><th>User</th><th style="width:100px">Action</th><th>Description</th></tr></thead>
            <tbody>
              <tr v-for="log in timelineLogs" :key="(log.created_at || '') + (log.action || '')">
                <td class="text-sm" style="color: var(--text-secondary)">{{ prettyTimestampEpoch(log.created_at) }}</td>
                <td class="text-sm">{{ log.user_name }}</td>
                <td><span class="badge badge-default">{{ log.action }}</span></td>
                <td class="text-sm" style="color: var(--text-secondary)">{{ log.description }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  `,
});

// ─── View: DomainDetailView ──────────────────────────────────────────────────
const DomainDetailView = defineComponent({
  components: { TabBar },
  setup() {
    const route = useRoute();
    const router = useRouter();
    const { showNotify } = useNotify();
    const domain = ref(null);
    const records = ref([]);
    const provider = ref({ contacts: { owner: {}, admin: {}, tech: {}, billing: {} }, nameservers: [], locked: 'off', whois_privacy: 'off' });
    const domainDetails = ref({});
    const loading = ref(true);
    const saving = ref(false);
    const activeTab = ref('dns');
    const contactTab = ref('owner');
    const updatingContacts = ref(false);
    const updatingNameservers = ref(false);
    const updatingLock = ref(false);
    const updatingPrivacy = ref(false);

    // Email forwarding state
    const forwards = ref([]);
    const forwardsLoading = ref(false);
    const forwardsDomainStatus = ref(null);
    const forwardsDomainLoading = ref(false);
    const forwardDialog = ref(false);
    const forwardEditIndex = ref(-1);
    const forwardEditItem = reactive({ name: '', recipients_string: '' });

    function fetchEmailForwards() {
      const id = route.params.id;
      forwardsLoading.value = true;
      forwardsDomainLoading.value = true;
      api.get('/wp-json/captaincore/v1/domain/' + id + '/email-forwards')
        .then(r => {
          forwards.value = (r.data || []).map(a => ({ ...a, recipients_string: Array.isArray(a.recipients) ? a.recipients.join(', ') : '' }));
        })
        .catch(() => showNotify('Error fetching email forwards', 'error'))
        .finally(() => { forwardsLoading.value = false; });
      api.get('/wp-json/captaincore/v1/domain/' + id + '/email-forwarding/status')
        .then(r => { forwardsDomainStatus.value = r.data; })
        .catch(() => {})
        .finally(() => { forwardsDomainLoading.value = false; });
    }
    function addForward() {
      forwardEditIndex.value = -1;
      forwardEditItem.name = '';
      forwardEditItem.recipients_string = '';
      forwardDialog.value = true;
    }
    function editForward(item, i) {
      forwardEditIndex.value = i;
      forwardEditItem.name = item.name;
      forwardEditItem.recipients_string = item.recipients_string;
      forwardEditItem.id = item.id;
      forwardDialog.value = true;
    }
    function saveForward() {
      const id = route.params.id;
      forwardsLoading.value = true;
      const payload = {
        name: forwardEditItem.name,
        recipients: forwardEditItem.recipients_string.split(',').map(e => e.trim()).filter(e => e),
      };
      const apiCall = forwardEditIndex.value > -1
        ? api.put('/wp-json/captaincore/v1/domain/' + id + '/email-forwards/' + forwardEditItem.id, payload)
        : api.post('/wp-json/captaincore/v1/domain/' + id + '/email-forwards', payload);
      apiCall
        .then(() => { showNotify('Email forward saved', 'success'); forwardDialog.value = false; fetchEmailForwards(); })
        .catch(() => showNotify('Error saving email forward', 'error'))
        .finally(() => { forwardsLoading.value = false; });
    }
    function deleteForward(item) {
      if (!confirm('Delete the alias "' + item.name + '@' + (domain.value ? domain.value.name : '') + '"?')) return;
      const id = route.params.id;
      forwardsLoading.value = true;
      api.delete('/wp-json/captaincore/v1/domain/' + id + '/email-forwards/' + item.id)
        .then(() => { showNotify('Email forward deleted', 'success'); fetchEmailForwards(); })
        .catch(() => showNotify('Error deleting email forward', 'error'))
        .finally(() => { forwardsLoading.value = false; });
    }
    function verifyDomainDns() {
      forwardsDomainLoading.value = true;
      api.get('/wp-json/captaincore/v1/domain/' + route.params.id + '/email-forwarding/status')
        .then(r => { forwardsDomainStatus.value = r.data; showNotify('DNS verification refreshed', 'info'); })
        .catch(() => showNotify('Error checking DNS', 'error'))
        .finally(() => { forwardsDomainLoading.value = false; });
    }

    const tabs = computed(() => {
      const t = [{ key: 'dns', label: 'DNS Records' }];
      if (domain.value && domain.value.provider_id) t.push({ key: 'management', label: 'Domain Management' });
      if (domainDetails.value && domainDetails.value.mailgun_forwarding_id) t.push({ key: 'email', label: 'Email Forwarding' });
      if (domainDetails.value && domainDetails.value.mailgun_id) t.push({ key: 'mailgun', label: 'Mailgun' });
      return t;
    });
    const contactTabs = [
      { key: 'owner', label: 'Owner' }, { key: 'admin', label: 'Admin' },
      { key: 'tech', label: 'Technical' }, { key: 'billing', label: 'Billing' },
    ];
    const activeContact = computed(() => provider.value.contacts[contactTab.value] || {});

    function fetchData() {
      const id = route.params.id;
      loading.value = true;
      const domainObj = domains.value.find(d => d.domain_id == id);
      if (domainObj) domain.value = { ...domainObj };

      api.get('/wp-json/captaincore/v1/domain/' + id)
        .then(domR => {
          if (domR.data) {
            if (domR.data.provider) provider.value = domR.data.provider;
            if (domR.data.details) domainDetails.value = domR.data.details;
            if (!domain.value) domain.value = {};
            Object.assign(domain.value, domR.data);
          }
          // Fetch DNS if domain has remote_id (from API response or cached list)
          const hasRemoteId = (domain.value && domain.value.remote_id) || (domainObj && domainObj.remote_id);
          if (hasRemoteId) {
            return api.get('/wp-json/captaincore/v1/dns/' + id);
          }
          return { data: [] };
        })
        .then(dnsR => {
          const dnsData = dnsR.data || {};
          const rawRecords = Array.isArray(dnsData) ? dnsData : (dnsData.records || []);
          if (Array.isArray(rawRecords)) {
            records.value = rawRecords.map(r => ({
              ...r, edit: false, deleted: false, isNew: false,
              update: { record_id: r.id, record_type: r.type, record_name: r.name || '', record_value: JSON.parse(JSON.stringify(r.value || [])), record_ttl: r.ttl || 3600 },
            }));
          }
        })
        .catch(() => showNotify('Failed to load domain', 'error'))
        .finally(() => { loading.value = false; });
    }
    onMounted(fetchData);
    watch(() => route.params.id, v => { if (v) fetchData(); });
    watch(activeTab, t => {
      if (t === 'email' && !forwards.value.length) fetchEmailForwards();
      if (t === 'mailgun' && !mailgunData.value) fetchMailgunDetails();
    });

    // Mailgun state
    const mailgunData = ref(null);
    const mailgunLoading = ref(false);
    const mailgunVerifying = ref(false);
    const mailgunEvents = ref([]);
    const mailgunEventsLoading = ref(false);
    const showMailgunEventsDialog = ref(false);
    const mailgunSetupDialog = ref(false);
    const mailgunSubdomain = ref('mg');
    const mailgunSettingUp = ref(false);
    const mailgunDeployDialog = ref(false);
    const mailgunDeployName = ref('');
    const mailgunDeploying = ref(false);
    const mailgunDeploySiteId = ref('');

    function fetchMailgunDetails() {
      mailgunLoading.value = true;
      api.get('/wp-json/captaincore/v1/domain/' + route.params.id + '/mailgun')
        .then(r => { mailgunData.value = r.data || null; })
        .catch(() => showNotify('Failed to load Mailgun details', 'error'))
        .finally(() => { mailgunLoading.value = false; });
    }

    function verifyMailgunDomain() {
      mailgunVerifying.value = true;
      api.post('/wp-json/captaincore/v1/domain/' + route.params.id + '/mailgun/verify')
        .then(r => {
          if (r.data) mailgunData.value = r.data;
          showNotify('DNS verification complete', 'success');
        })
        .catch(() => showNotify('Verification failed', 'error'))
        .finally(() => { mailgunVerifying.value = false; });
    }

    function fetchMailgunEvents() {
      mailgunEventsLoading.value = true;
      showMailgunEventsDialog.value = true;
      api.get('/wp-json/captaincore/v1/domain/' + route.params.id + '/mailgun/events')
        .then(r => { mailgunEvents.value = r.data || []; })
        .catch(() => showNotify('Failed to load events', 'error'))
        .finally(() => { mailgunEventsLoading.value = false; });
    }

    function setupMailgun() {
      mailgunSettingUp.value = true;
      api.post('/wp-json/captaincore/v1/domain/' + route.params.id + '/mailgun/setup', { subdomain: mailgunSubdomain.value })
        .then(r => {
          showNotify('Mailgun zone created', 'success');
          mailgunSetupDialog.value = false;
          if (r.data && r.data.details) domainDetails.value = r.data.details;
          fetchMailgunDetails();
          activeTab.value = 'mailgun';
        })
        .catch(() => showNotify('Setup failed', 'error'))
        .finally(() => { mailgunSettingUp.value = false; });
    }

    function deployMailgun() {
      if (!mailgunDeploySiteId.value) { showNotify('Select a site', 'error'); return; }
      mailgunDeploying.value = true;
      api.post('/wp-json/captaincore/v1/domain/' + route.params.id + '/mailgun/deploy', { site_id: mailgunDeploySiteId.value, from_name: mailgunDeployName.value })
        .then(() => { showNotify('Mailgun deployed', 'success'); mailgunDeployDialog.value = false; })
        .catch(() => showNotify('Deploy failed', 'error'))
        .finally(() => { mailgunDeploying.value = false; });
    }

    function deleteMailgunZone() {
      if (!confirm('Delete the Mailgun zone for this domain?')) return;
      api.delete('/wp-json/captaincore/v1/domain/' + route.params.id + '/mailgun')
        .then(() => {
          showNotify('Mailgun zone deleted', 'success');
          mailgunData.value = null;
          domainDetails.value.mailgun_id = '';
          domainDetails.value.mailgun_zone = '';
          domainDetails.value.mailgun_smtp_password = '';
          activeTab.value = 'dns';
        })
        .catch(() => showNotify('Delete failed', 'error'));
    }

    function dnsValidClass(valid) {
      if (valid === 'valid') return 'badge-success';
      if (valid === 'invalid') return 'badge-error';
      return 'badge-default';
    }

    const role = userRole;
    const deletingDomain = ref(false);
    const showDeleteDomainConfirm = ref(false);
    const activatingZone = ref(false);
    const deletingZone = ref(false);

    function deleteDomain() {
      deletingDomain.value = true;
      api.delete('/wp-json/captaincore/v1/domains/' + route.params.id)
        .then(() => { showNotify('Domain deleted', 'success'); domainsFetched = false; router.push('/domains'); })
        .catch(() => showNotify('Failed to delete domain', 'error'))
        .finally(() => { deletingDomain.value = false; });
    }
    function activateDnsZone() {
      activatingZone.value = true;
      api.post('/wp-json/captaincore/v1/domain/' + route.params.id + '/activate-dns-zone')
        .then(() => { showNotify('DNS zone activated', 'success'); fetchData(); })
        .catch(() => showNotify('Failed to activate DNS zone', 'error'))
        .finally(() => { activatingZone.value = false; });
    }
    function deleteDnsZone() {
      if (!confirm('Delete DNS zone? All DNS records will be removed.')) return;
      deletingZone.value = true;
      api.delete('/wp-json/captaincore/v1/domain/' + route.params.id + '/dns-zone')
        .then(() => { showNotify('DNS zone deleted', 'success'); records.value = []; fetchData(); })
        .catch(() => showNotify('Failed to delete DNS zone', 'error'))
        .finally(() => { deletingZone.value = false; });
    }
    function exportZone() {
      api.get('/wp-json/captaincore/v1/domains/' + route.params.id + '/zone')
        .then(r => {
          const blob = new Blob([typeof r.data === 'string' ? r.data : JSON.stringify(r.data)], { type: 'text/plain' });
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url; a.download = (domain.value ? domain.value.name : 'domain') + '.zone'; a.click();
          URL.revokeObjectURL(url);
        })
        .catch(() => showNotify('Failed to export zone', 'error'));
    }
    function retrieveAuthCode() {
      api.get('/wp-json/captaincore/v1/domain/' + route.params.id + '/auth_code')
        .then(r => { showNotify('Auth code: ' + (r.data || 'N/A'), 'info'); })
        .catch(() => showNotify('Failed to retrieve auth code', 'error'));
    }

    function goBack() { router.push('/domains'); }
    function addRecord() {
      records.value.push({
        id: 'new-' + Date.now(), type: 'A', name: '', value: [{ value: '', enabled: true }], ttl: 3600,
        edit: true, deleted: false, isNew: true,
        update: { record_id: null, record_type: 'A', record_name: '', record_value: [{ value: '', enabled: true }], record_ttl: 3600 },
      });
    }
    function editRecord(i) { records.value[i].edit = true; }
    function cancelEdit(i) {
      if (records.value[i].isNew) { records.value.splice(i, 1); return; }
      const r = records.value[i];
      r.edit = false;
      r.update = { record_id: r.id, record_type: r.type, record_name: r.name || '', record_value: JSON.parse(JSON.stringify(r.value || [])), record_ttl: r.ttl || 3600 };
    }
    function deleteRecord(i) { records.value[i].deleted = true; }
    function undeleteRecord(i) { records.value[i].deleted = false; }

    function addRecordValue(i) {
      const type = records.value[i].update.record_type;
      if (type === 'MX') records.value[i].update.record_value.push({ priority: '', server: '', enabled: true });
      else if (type === 'SRV') records.value[i].update.record_value.push({ priority: 100, weight: 1, port: 443, host: '', enabled: true });
      else records.value[i].update.record_value.push({ value: '', enabled: true });
    }
    function removeRecordValue(i, vi) {
      if (records.value[i].update.record_value.length > 1) records.value[i].update.record_value.splice(vi, 1);
    }
    function changeRecordType(i) {
      const type = records.value[i].update.record_type;
      if (type === 'MX') records.value[i].update.record_value = [{ priority: '', server: '', enabled: true }];
      else if (type === 'SRV') records.value[i].update.record_value = [{ priority: 100, weight: 1, port: 443, host: '', enabled: true }];
      else if (type === 'HTTP') records.value[i].update.record_value = [{ value: '', enabled: true }];
      else records.value[i].update.record_value = [{ value: '', enabled: true }];
    }

    // Import zone file
    const showImportDialog = ref(false);
    const importZoneText = ref('');
    function importZoneFile() {
      if (!importZoneText.value.trim()) return;
      saving.value = true;
      api.post('/wp-json/captaincore/v1/domains/import', { domain: domain.value.name, zone: importZoneText.value })
        .then(r => {
          const imported = r.data || [];
          if (Array.isArray(imported)) {
            imported.forEach(rec => {
              if (['SOA', 'NS'].includes(rec.type)) return;
              const vals = Array.isArray(rec.value) ? rec.value : [{ value: String(rec.value || ''), enabled: true }];
              records.value.push({
                id: 'new-' + Date.now() + '-' + Math.random().toString(36).slice(2, 6),
                type: rec.type, name: rec.name || '', value: vals, ttl: rec.ttl || 3600,
                edit: false, deleted: false, isNew: true,
                update: { record_id: null, record_type: rec.type, record_name: rec.name || '', record_value: JSON.parse(JSON.stringify(vals)), record_ttl: rec.ttl || 3600 },
              });
            });
          }
          showImportDialog.value = false;
          importZoneText.value = '';
          showNotify(imported.length + ' records imported', 'success');
        })
        .catch(() => showNotify('Failed to import zone file', 'error'))
        .finally(() => { saving.value = false; });
    }

    function getDisplayValue(r) {
      if (!r.value) return '';
      if (Array.isArray(r.value)) return r.value.map(v => v.server ? v.priority + ' ' + v.server : v.host ? v.priority + ' ' + v.weight + ' ' + v.port + ' ' + v.host : v.value || '').join(', ');
      if (r.value.url) return r.value.url;
      return String(r.value);
    }
    function saveDNS() {
      const updates = [];
      records.value.forEach(r => {
        if (r.deleted && !r.isNew) updates.push({ ...r.update, record_status: 'remove-record' });
        else if (r.isNew && !r.deleted) updates.push({ ...r.update, record_status: 'new-record' });
        else if (r.edit && !r.deleted) updates.push({ ...r.update, record_status: 'edit-record' });
      });
      if (!updates.length) { showNotify('No changes to save', 'info'); return; }
      saving.value = true;
      api.post('/wp-json/captaincore/v1/dns/' + route.params.id + '/bulk', { record_updates: updates })
        .then(() => { showNotify('DNS records saved', 'success'); fetchData(); })
        .catch(() => showNotify('Failed to save DNS records', 'error'))
        .finally(() => { saving.value = false; });
    }
    function updateContacts() {
      updatingContacts.value = true;
      api.post('/wp-json/captaincore/v1/domain/' + route.params.id + '/contacts', { contacts: provider.value.contacts })
        .then(() => showNotify('Contacts updated', 'success'))
        .catch(() => showNotify('Failed to update contacts', 'error'))
        .finally(() => { updatingContacts.value = false; });
    }
    function updateNameservers() {
      updatingNameservers.value = true;
      const ns = provider.value.nameservers.map(n => typeof n === 'object' ? n.value : n);
      api.post('/wp-json/captaincore/v1/domain/' + route.params.id + '/nameservers', { nameservers: ns })
        .then(() => showNotify('Nameservers updated', 'success'))
        .catch(() => showNotify('Failed to update nameservers', 'error'))
        .finally(() => { updatingNameservers.value = false; });
    }
    function toggleLock() {
      updatingLock.value = true;
      const action = provider.value.locked === 'on' ? 'lock_off' : 'lock_on';
      api.get('/wp-json/captaincore/v1/domain/' + route.params.id + '/' + action)
        .then(() => { provider.value.locked = action === 'lock_on' ? 'on' : 'off'; showNotify('Lock updated', 'success'); })
        .catch(() => showNotify('Failed to update lock', 'error'))
        .finally(() => { updatingLock.value = false; });
    }
    function togglePrivacy() {
      updatingPrivacy.value = true;
      const action = provider.value.whois_privacy === 'on' ? 'privacy_off' : 'privacy_on';
      api.get('/wp-json/captaincore/v1/domain/' + route.params.id + '/' + action)
        .then(() => { provider.value.whois_privacy = action === 'privacy_on' ? 'on' : 'off'; showNotify('Privacy updated', 'success'); })
        .catch(() => showNotify('Failed to update privacy', 'error'))
        .finally(() => { updatingPrivacy.value = false; });
    }

    return { domain, records, provider, domainDetails, loading, saving, activeTab, tabs, contactTab, contactTabs, activeContact, goBack, addRecord, editRecord, cancelEdit, deleteRecord, undeleteRecord, addRecordValue, removeRecordValue, changeRecordType, showImportDialog, importZoneText, importZoneFile, getDisplayValue, saveDNS, updateContacts, updateNameservers, toggleLock, togglePrivacy, updatingContacts, updatingNameservers, updatingLock, updatingPrivacy, forwards, forwardsLoading, forwardsDomainStatus, forwardsDomainLoading, forwardDialog, forwardEditIndex, forwardEditItem, fetchEmailForwards, addForward, editForward, saveForward, deleteForward, verifyDomainDns, role, deletingDomain, showDeleteDomainConfirm, deleteDomain, activatingZone, deletingZone, activateDnsZone, deleteDnsZone, exportZone, retrieveAuthCode, mailgunData, mailgunLoading, mailgunVerifying, fetchMailgunDetails, verifyMailgunDomain, fetchMailgunEvents, mailgunEvents, mailgunEventsLoading, showMailgunEventsDialog, mailgunSetupDialog, mailgunSubdomain, mailgunSettingUp, setupMailgun, mailgunDeployDialog, mailgunDeployName, mailgunDeploying, mailgunDeploySiteId, deployMailgun, deleteMailgunZone, dnsValidClass };
  },
  template: `
    <div v-if="loading" class="flex justify-center py-16"><div class="animate-spin rounded-full h-8 w-8 border-b-2" style="border-color: var(--color-primary)"></div></div>
    <div v-else class="surface rounded-xl">
      <div class="detail-header">
        <button class="back-btn" @click="goBack"><svg-icon name="arrowLeft" :size="18" /></button>
        <h2 class="text-sm font-semibold flex-1" style="color: var(--text-primary)">{{ domain ? domain.name : 'Domain' }}</h2>
        <div v-if="role === 'administrator'" class="flex items-center gap-2">
          <button v-if="domain && !domain.remote_id" @click="activateDnsZone()" :disabled="activatingZone" class="btn btn-sm btn-outline">{{ activatingZone ? 'Activating...' : 'Activate DNS' }}</button>
          <button v-if="domain && domain.remote_id" @click="exportZone()" class="btn btn-sm btn-outline"><svg-icon name="download" :size="14" /> Export</button>
          <button v-if="domain && domain.provider_id" @click="retrieveAuthCode()" class="btn btn-sm btn-outline"><svg-icon name="key" :size="14" /> Auth Code</button>
          <button v-if="domain && domain.remote_id" @click="deleteDnsZone()" :disabled="deletingZone" class="btn btn-sm btn-ghost" style="color: var(--color-error)" title="Delete DNS Zone"><svg-icon name="trash" :size="14" /></button>
          <button @click="showDeleteDomainConfirm = true" class="btn btn-sm btn-ghost" style="color: var(--color-error)" title="Delete Domain"><svg-icon name="close" :size="14" /></button>
        </div>
      </div>
      <!-- Delete Domain Confirm -->
      <div v-if="showDeleteDomainConfirm" class="dialog-overlay" @click.self="showDeleteDomainConfirm = false">
        <div class="dialog-card" style="max-width: 400px;">
          <div class="dialog-card-header"><h3 class="text-sm font-semibold" style="color: var(--color-error)">Delete Domain</h3></div>
          <div class="dialog-card-body"><p class="text-sm" style="color: var(--text-primary)">Are you sure you want to delete <strong>{{ domain ? domain.name : '' }}</strong>?</p></div>
          <div class="dialog-card-footer"><button @click="showDeleteDomainConfirm = false" class="btn btn-ghost">Cancel</button><button @click="deleteDomain()" :disabled="deletingDomain" class="btn btn-danger">{{ deletingDomain ? 'Deleting...' : 'Delete' }}</button></div>
        </div>
      </div>
      <div class="px-4 pt-3"><tab-bar :tabs="tabs" v-model="activeTab" /></div>
      <div class="p-4">
        <!-- DNS Records Tab -->
        <div v-if="activeTab === 'dns'">
          <div class="flex items-center justify-between mb-4">
            <span class="text-sm" style="color: var(--text-secondary)">{{ records.filter(r => !r.deleted).length }} records</span>
            <div class="flex gap-2">
              <button @click="showImportDialog = true" class="btn btn-sm btn-ghost"><svg-icon name="upload" :size="14" /> Import</button>
              <button @click="addRecord" class="btn btn-sm btn-outline"><svg-icon name="plus" :size="14" /> Add Record</button>
              <button @click="saveDNS" :disabled="saving" class="btn btn-sm btn-primary"><svg-icon name="check" :size="14" /> {{ saving ? 'Saving...' : 'Save Changes' }}</button>
            </div>
          </div>
          <!-- Import Zone Dialog -->
          <div v-if="showImportDialog" class="dialog-overlay" @click.self="showImportDialog = false">
            <div class="dialog-card" style="max-width: 560px;">
              <div class="dialog-card-header"><h3 class="text-sm font-semibold" style="color: var(--text-primary)">Import DNS Zone File</h3></div>
              <div class="dialog-card-body">
                <p class="text-xs mb-3" style="color: var(--text-secondary)">Paste a DNS zone file below. SOA and NS records will be skipped.</p>
                <textarea v-model="importZoneText" class="textarea-field" rows="10" placeholder="Paste zone file contents..." style="font-family: ui-monospace, SFMono-Regular, monospace; font-size: 0.75rem;"></textarea>
              </div>
              <div class="dialog-card-footer"><button @click="showImportDialog = false" class="btn btn-ghost">Cancel</button><button @click="importZoneFile" :disabled="saving || !importZoneText.trim()" class="btn btn-primary">{{ saving ? 'Importing...' : 'Import Records' }}</button></div>
            </div>
          </div>
          <table class="data-table">
            <thead><tr><th style="width:80px">Type</th><th style="width:180px">Name</th><th>Value</th><th style="width:70px">TTL</th><th style="width:80px">Actions</th></tr></thead>
            <tbody>
              <template v-for="(r, i) in records" :key="r.id">
                <!-- Deleted row (with undo) -->
                <tr v-if="r.deleted" style="opacity: 0.4; text-decoration: line-through;">
                  <td class="text-sm"><span class="badge badge-default">{{ r.type }}</span></td>
                  <td class="text-sm">{{ r.name }}</td>
                  <td class="text-sm">{{ getDisplayValue(r) }}</td>
                  <td class="text-sm">{{ r.ttl }}</td>
                  <td><button @click="undeleteRecord(i)" class="btn btn-sm btn-ghost" title="Undo delete"><svg-icon name="refresh" :size="14" /></button></td>
                </tr>
                <!-- Display mode -->
                <tr v-else-if="!r.edit" :style="r.isNew ? 'background: color-mix(in srgb, var(--color-success) 5%, transparent)' : ''">
                  <td class="text-sm"><span class="badge badge-info">{{ r.type }}</span></td>
                  <td class="text-sm">{{ r.name }}</td>
                  <td class="text-sm" style="word-break: break-all">{{ getDisplayValue(r) }}</td>
                  <td class="text-sm" style="color: var(--text-secondary)">{{ r.ttl }}</td>
                  <td>
                    <div class="flex gap-1">
                      <button @click="editRecord(i)" class="btn btn-sm btn-ghost" title="Edit"><svg-icon name="pencil" :size="14" /></button>
                      <button @click="deleteRecord(i)" class="btn btn-sm btn-ghost" title="Delete" style="color: var(--color-error)"><svg-icon name="trash" :size="14" /></button>
                    </div>
                  </td>
                </tr>
                <!-- Edit mode -->
                <tr v-else :style="r.isNew ? 'background: color-mix(in srgb, var(--color-success) 5%, transparent)' : 'background: color-mix(in srgb, var(--color-primary) 5%, transparent)'">
                  <td>
                    <select v-model="r.update.record_type" @change="changeRecordType(i)" class="select-field" style="width: 70px; padding: 4px 6px; font-size: 0.75rem;">
                      <option v-for="t in ['A','AAAA','ANAME','CNAME','MX','SRV','TXT','SPF','HTTP']" :key="t">{{ t }}</option>
                    </select>
                  </td>
                  <td><input v-model="r.update.record_name" class="input-field" style="padding: 4px 8px; font-size: 0.8rem;" placeholder="@ or subdomain" /></td>
                  <td>
                    <div v-for="(v, vi) in r.update.record_value" :key="vi" class="flex gap-1 mb-1 items-center">
                      <!-- MX fields -->
                      <template v-if="r.update.record_type === 'MX'">
                        <input v-model.number="v.priority" class="input-field" style="width:55px; padding:4px 6px; font-size:0.8rem;" placeholder="Pri" />
                        <input v-model="v.server" class="input-field flex-1" style="padding:4px 8px; font-size:0.8rem;" placeholder="mail.example.com." />
                      </template>
                      <!-- SRV fields -->
                      <template v-else-if="r.update.record_type === 'SRV'">
                        <input v-model.number="v.priority" class="input-field" style="width:50px; padding:4px 6px; font-size:0.8rem;" placeholder="Pri" />
                        <input v-model.number="v.weight" class="input-field" style="width:50px; padding:4px 6px; font-size:0.8rem;" placeholder="Wgt" />
                        <input v-model.number="v.port" class="input-field" style="width:55px; padding:4px 6px; font-size:0.8rem;" placeholder="Port" />
                        <input v-model="v.host" class="input-field flex-1" style="padding:4px 8px; font-size:0.8rem;" placeholder="host.example.com." />
                      </template>
                      <!-- All other types -->
                      <template v-else>
                        <input v-model="v.value" class="input-field flex-1" style="padding:4px 8px; font-size:0.8rem;" :placeholder="r.update.record_type === 'HTTP' ? 'https://example.com' : 'Value'" />
                      </template>
                      <button v-if="r.update.record_value.length > 1" @click="removeRecordValue(i, vi)" class="btn btn-sm btn-ghost" style="color: var(--color-error); padding: 2px;" title="Remove value"><svg-icon name="close" :size="12" /></button>
                    </div>
                    <button v-if="r.update.record_type !== 'CNAME' && r.update.record_type !== 'HTTP'" @click="addRecordValue(i)" class="text-xs mt-1" style="color: var(--color-primary); background: none; border: none; cursor: pointer;">+ Add value</button>
                  </td>
                  <td><input v-model.number="r.update.record_ttl" class="input-field" style="width:60px; padding:4px 6px; font-size:0.8rem;" /></td>
                  <td>
                    <button @click="cancelEdit(i)" class="btn btn-sm btn-ghost" title="Cancel"><svg-icon name="close" :size="14" /></button>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
          <div v-if="!records.length" class="text-sm py-8 text-center" style="color: var(--text-secondary)">No DNS records. Add a record or activate DNS for this domain.</div>
        </div>

        <!-- Domain Management Tab -->
        <div v-if="activeTab === 'management'">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div>
              <h3 class="text-xs font-semibold uppercase tracking-wider mb-3" style="color: var(--text-secondary)">Contacts</h3>
              <div class="flex gap-2 mb-3">
                <button v-for="ct in contactTabs" :key="ct.key" @click="contactTab = ct.key" :class="['btn btn-sm', contactTab === ct.key ? 'btn-primary' : 'btn-outline']">{{ ct.label }}</button>
              </div>
              <div class="space-y-3">
                <div v-for="field in ['first_name','last_name','organization','address1','address2','city','state','postal_code','country','phone','email']" :key="field">
                  <label class="block text-xs mb-1 font-medium capitalize" style="color: var(--text-secondary)">{{ field.replace(/_/g, ' ') }}</label>
                  <input v-model="activeContact[field]" class="input-field" />
                </div>
              </div>
              <button @click="updateContacts" :disabled="updatingContacts" class="btn btn-sm btn-primary mt-4">{{ updatingContacts ? 'Saving...' : 'Update Contacts' }}</button>
            </div>
            <div class="space-y-6">
              <div>
                <h3 class="text-xs font-semibold uppercase tracking-wider mb-3" style="color: var(--text-secondary)">Nameservers</h3>
                <div v-for="(ns, i) in provider.nameservers" :key="i" class="flex gap-2 mb-2">
                  <input v-model="provider.nameservers[i]" class="input-field flex-1" :placeholder="'Nameserver ' + (i + 1)" />
                </div>
                <button @click="updateNameservers" :disabled="updatingNameservers" class="btn btn-sm btn-primary mt-2">{{ updatingNameservers ? 'Saving...' : 'Update Nameservers' }}</button>
              </div>
              <div>
                <h3 class="text-xs font-semibold uppercase tracking-wider mb-3" style="color: var(--text-secondary)">Controls</h3>
                <div class="space-y-3">
                  <div class="flex items-center justify-between p-3 rounded-lg" style="background: var(--hover-bg)">
                    <span class="text-sm" style="color: var(--text-primary)">Domain Lock</span>
                    <button :class="['toggle', provider.locked === 'on' && 'on']" @click="toggleLock" :disabled="updatingLock"></button>
                  </div>
                  <div class="flex items-center justify-between p-3 rounded-lg" style="background: var(--hover-bg)">
                    <span class="text-sm" style="color: var(--text-primary)">WHOIS Privacy</span>
                    <button :class="['toggle', provider.whois_privacy === 'on' && 'on']" @click="togglePrivacy" :disabled="updatingPrivacy"></button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Email Forwarding Tab -->
        <div v-if="activeTab === 'email'">
          <!-- Domain verification status -->
          <div v-if="forwardsDomainLoading" class="flex justify-center py-8"><div class="animate-spin rounded-full h-6 w-6 border-b-2" style="border-color: var(--color-primary)"></div></div>
          <div v-else-if="forwardsDomainStatus && forwardsDomainStatus.state !== 'active'" class="mb-4">
            <div class="rounded-lg p-4 mb-4" style="background: color-mix(in srgb, var(--color-warning) 10%, transparent); border: 1px solid color-mix(in srgb, var(--color-warning) 30%, transparent);">
              <h3 class="text-sm font-semibold mb-2" style="color: var(--color-warning)">Domain Not Yet Verified</h3>
              <p class="text-xs mb-3" style="color: var(--text-primary)">Add the following DNS records to verify your domain for email forwarding.</p>
              <div v-if="forwardsDomainStatus.sending_dns_records && forwardsDomainStatus.sending_dns_records.length" class="mb-3">
                <h4 class="text-xs font-semibold uppercase tracking-wider mb-2" style="color: var(--text-secondary)">Sending/Verification Records</h4>
                <div v-for="rec in forwardsDomainStatus.sending_dns_records" :key="rec.name" class="text-xs p-2 mb-1 rounded" style="background: var(--bg-surface); font-family: monospace;">
                  <span class="font-semibold">{{ rec.record_type }}</span> {{ rec.name }} → {{ rec.value }}
                  <span :class="['badge ml-2', rec.valid === 'valid' ? 'badge-success' : 'badge-warning']">{{ rec.valid }}</span>
                </div>
              </div>
              <div v-if="forwardsDomainStatus.receiving_dns_records && forwardsDomainStatus.receiving_dns_records.length" class="mb-3">
                <h4 class="text-xs font-semibold uppercase tracking-wider mb-2" style="color: var(--text-secondary)">Receiving (MX) Records</h4>
                <div v-for="rec in forwardsDomainStatus.receiving_dns_records" :key="rec.value" class="text-xs p-2 mb-1 rounded" style="background: var(--bg-surface); font-family: monospace;">
                  <span class="font-semibold">{{ rec.record_type }}</span> {{ rec.priority || '' }} {{ rec.value }}
                  <span :class="['badge ml-2', rec.valid === 'valid' ? 'badge-success' : 'badge-warning']">{{ rec.valid }}</span>
                </div>
              </div>
              <button @click="verifyDomainDns" :disabled="forwardsDomainLoading" class="btn btn-sm btn-outline">Verify DNS Records</button>
            </div>
          </div>

          <!-- Forwards list -->
          <div class="flex items-center justify-between mb-4">
            <span class="text-sm" style="color: var(--text-secondary)">{{ forwards.length }} email forwards</span>
            <button @click="addForward" :disabled="forwardsLoading" class="btn btn-sm btn-outline"><svg-icon name="plus" :size="14" /> Add Forward</button>
          </div>
          <div v-if="forwardsLoading" class="flex justify-center py-8"><div class="animate-spin rounded-full h-6 w-6 border-b-2" style="border-color: var(--color-primary)"></div></div>
          <table v-else-if="forwards.length" class="data-table">
            <thead><tr><th style="width:200px">Alias (Prefix)</th><th>Forwarding To</th><th style="width:100px">Actions</th></tr></thead>
            <tbody>
              <tr v-for="(fwd, i) in forwards" :key="fwd.id">
                <td class="text-sm font-mono">{{ fwd.name }}</td>
                <td class="text-sm" style="color: var(--text-secondary)">{{ fwd.recipients_string }}</td>
                <td>
                  <div class="flex gap-1">
                    <button @click="editForward(fwd, i)" class="btn btn-sm btn-ghost"><svg-icon name="pencil" :size="14" /></button>
                    <button @click="deleteForward(fwd)" class="btn btn-sm btn-ghost" style="color: var(--color-error)"><svg-icon name="trash" :size="14" /></button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
          <div v-else class="text-sm py-4" style="color: var(--text-secondary)">No email forwards configured.</div>

          <!-- Add/Edit dialog (inline) -->
          <div v-if="forwardDialog" class="mt-4 p-4 rounded-lg" style="background: var(--hover-bg); border: 1px solid var(--border-color);">
            <h3 class="text-sm font-semibold mb-3" style="color: var(--text-primary)">{{ forwardEditIndex > -1 ? 'Edit' : 'Add' }} Email Forward</h3>
            <div class="space-y-3" style="max-width: 500px;">
              <div>
                <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Alias (prefix before @)</label>
                <input v-model="forwardEditItem.name" class="input-field" placeholder="info" />
              </div>
              <div>
                <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Forward to (comma-separated emails)</label>
                <textarea v-model="forwardEditItem.recipients_string" class="textarea-field" rows="3" placeholder="user@example.com, other@example.com"></textarea>
              </div>
              <div class="flex gap-2">
                <button @click="saveForward" :disabled="forwardsLoading || !forwardEditItem.name" class="btn btn-sm btn-primary">{{ forwardsLoading ? 'Saving...' : 'Save' }}</button>
                <button @click="forwardDialog = false" class="btn btn-sm btn-ghost">Cancel</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Mailgun Tab -->
        <div v-if="activeTab === 'mailgun'">
          <div v-if="mailgunLoading" class="flex justify-center py-8"><div class="animate-spin rounded-full h-6 w-6 border-b-2" style="border-color: var(--color-primary)"></div></div>
          <div v-else-if="!mailgunData" class="text-sm py-4" style="color: var(--text-secondary)">No Mailgun data available.</div>
          <div v-else>
            <!-- Domain status -->
            <div class="flex items-center justify-between mb-4">
              <div class="flex items-center gap-2">
                <span class="text-sm font-medium" style="color: var(--text-primary)">{{ mailgunData.domain ? mailgunData.domain.name : domainDetails.mailgun_zone }}</span>
                <span v-if="mailgunData.domain" :class="'badge ' + (mailgunData.domain.state === 'active' ? 'badge-success' : 'badge-warning')">{{ mailgunData.domain.state }}</span>
              </div>
              <div class="flex items-center gap-2">
                <button @click="verifyMailgunDomain" :disabled="mailgunVerifying" class="btn btn-sm btn-outline">
                  <svg-icon name="sync" :size="14" /> {{ mailgunVerifying ? 'Verifying...' : 'Verify DNS' }}
                </button>
                <button @click="fetchMailgunEvents" class="btn btn-sm btn-outline">Events</button>
                <button @click="mailgunDeployDialog = true" class="btn btn-sm btn-primary">Deploy</button>
                <button @click="deleteMailgunZone" class="btn btn-sm btn-ghost" style="color: var(--color-error)"><svg-icon name="trash" :size="14" /></button>
              </div>
            </div>

            <!-- Sending DNS Records -->
            <div v-if="mailgunData.sending_dns_records && mailgunData.sending_dns_records.length" class="mb-4">
              <h4 class="text-xs font-semibold uppercase tracking-wider mb-2" style="color: var(--text-secondary)">Sending DNS Records</h4>
              <div v-for="rec in mailgunData.sending_dns_records" :key="rec.name" class="text-xs p-2 mb-1 rounded flex items-center gap-2" style="background: var(--hover-bg); font-family: monospace;">
                <span class="font-semibold" style="min-width: 40px">{{ rec.record_type }}</span>
                <span class="truncate flex-1" style="color: var(--text-secondary)">{{ rec.name }}</span>
                <span class="truncate flex-1">{{ rec.value }}</span>
                <span :class="'badge ' + dnsValidClass(rec.valid)">{{ rec.valid }}</span>
              </div>
            </div>

            <!-- Receiving DNS Records -->
            <div v-if="mailgunData.receiving_dns_records && mailgunData.receiving_dns_records.length" class="mb-4">
              <h4 class="text-xs font-semibold uppercase tracking-wider mb-2" style="color: var(--text-secondary)">Receiving DNS Records</h4>
              <div v-for="rec in mailgunData.receiving_dns_records" :key="rec.value" class="text-xs p-2 mb-1 rounded flex items-center gap-2" style="background: var(--hover-bg); font-family: monospace;">
                <span class="font-semibold" style="min-width: 40px">{{ rec.record_type }}</span>
                <span v-if="rec.priority" style="min-width: 20px">{{ rec.priority }}</span>
                <span class="truncate flex-1">{{ rec.value }}</span>
                <span :class="'badge ' + dnsValidClass(rec.valid)">{{ rec.valid }}</span>
              </div>
            </div>

            <!-- SMTP Info -->
            <div v-if="domainDetails.mailgun_smtp_password" class="rounded-lg p-3" style="background: var(--hover-bg)">
              <h4 class="text-xs font-semibold uppercase tracking-wider mb-2" style="color: var(--text-secondary)">SMTP Credentials</h4>
              <div class="text-xs" style="font-family: monospace; color: var(--text-secondary)">
                <div>Host: <span style="color: var(--text-primary)">smtp.mailgun.org</span></div>
                <div>Port: <span style="color: var(--text-primary)">587</span></div>
                <div>Username: <span style="color: var(--text-primary)">postmaster@{{ domainDetails.mailgun_zone }}</span></div>
                <div>Password: <span style="color: var(--text-primary)">{{ domainDetails.mailgun_smtp_password }}</span></div>
              </div>
            </div>
          </div>

          <!-- Deploy Dialog -->
          <div v-if="mailgunDeployDialog" class="dialog-overlay" @mousedown.self="mailgunDeployDialog = false">
            <div class="dialog-card" style="width: 400px">
              <div class="dialog-header">
                <h3 class="dialog-title">Deploy Mailgun</h3>
                <button @click="mailgunDeployDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="x" :size="18" /></button>
              </div>
              <div class="dialog-body space-y-3">
                <div>
                  <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary)">Site ID</label>
                  <input v-model="mailgunDeploySiteId" class="input w-full" placeholder="Enter site ID" />
                </div>
                <div>
                  <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary)">From Name</label>
                  <input v-model="mailgunDeployName" class="input w-full" placeholder="e.g. My Website" />
                </div>
              </div>
              <div class="dialog-footer">
                <button @click="mailgunDeployDialog = false" class="btn btn-outline">Cancel</button>
                <button @click="deployMailgun" :disabled="mailgunDeploying" class="btn btn-primary">{{ mailgunDeploying ? 'Deploying...' : 'Deploy' }}</button>
              </div>
            </div>
          </div>

          <!-- Events Dialog -->
          <div v-if="showMailgunEventsDialog" class="dialog-overlay" @mousedown.self="showMailgunEventsDialog = false">
            <div class="dialog-card" style="width: 800px">
              <div class="dialog-header">
                <h3 class="dialog-title">Mailgun Events</h3>
                <button @click="showMailgunEventsDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="x" :size="18" /></button>
              </div>
              <div class="dialog-body" style="max-height: 70vh; overflow-y: auto; padding: 0">
                <div v-if="mailgunEventsLoading" class="flex justify-center py-8"><div class="animate-spin rounded-full h-6 w-6 border-b-2" style="border-color: var(--color-primary)"></div></div>
                <div v-else-if="!mailgunEvents.length" class="p-8 text-center text-sm" style="color: var(--text-secondary)">No events found.</div>
                <table v-else class="data-table">
                  <thead><tr><th style="width:140px">Time</th><th style="width:80px">Event</th><th>From</th><th>To</th><th>Subject</th></tr></thead>
                  <tbody>
                    <tr v-for="ev in mailgunEvents" :key="ev.id || ev.timestamp">
                      <td class="text-xs" style="color: var(--text-secondary)">{{ ev.timestamp ? new Date(ev.timestamp * 1000).toLocaleString() : '' }}</td>
                      <td><span :class="'badge ' + (ev.event === 'delivered' ? 'badge-success' : ev.event === 'failed' || ev.event === 'rejected' ? 'badge-error' : 'badge-default')">{{ ev.event }}</span></td>
                      <td class="text-xs truncate" style="max-width: 150px">{{ ev.message && ev.message.headers ? ev.message.headers.from : '' }}</td>
                      <td class="text-xs truncate" style="max-width: 150px">{{ ev.message && ev.message.headers ? ev.message.headers.to : '' }}</td>
                      <td class="text-xs truncate" style="max-width: 200px">{{ ev.message && ev.message.headers ? ev.message.headers.subject : '' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  `,
});

// ─── View: AccountDetailView ─────────────────────────────────────────────────
const AccountDetailView = defineComponent({
  components: { TabBar, DataTable },
  setup() {
    const route = useRoute();
    const router = useRouter();
    const { showNotify } = useNotify();
    const data = ref(null);
    const loading = ref(true);
    const activeTab = ref('users');
    const role = userRole;

    const tabs = computed(() => {
      const t = [
        { key: 'users', label: 'Users' },
        { key: 'sites', label: 'Sites' },
        { key: 'domains', label: 'Domains' },
        { key: 'timeline', label: 'Timeline' },
      ];
      if (role.value === 'administrator') t.push({ key: 'invoices', label: 'Invoices' });
      t.push({ key: 'plan', label: 'Plan' });
      t.push({ key: 'activity', label: 'Activity' });
      return t;
    });

    // Activity logs (server-side paginated)
    const activityLogs = ref([]);
    const activityTotal = ref(0);
    const activityPage = ref(1);
    const activityLoading = ref(false);

    function fetchData() {
      const id = route.params.id;
      loading.value = true;
      api.get('/wp-json/captaincore/v1/accounts/' + id)
        .then(r => { data.value = r.data; })
        .catch(() => showNotify('Failed to load account', 'error'))
        .finally(() => { loading.value = false; });
    }
    function fetchActivity() {
      const id = route.params.id;
      activityLoading.value = true;
      api.get('/wp-json/captaincore/v1/activity-logs?account_id=' + id + '&page=' + activityPage.value + '&per_page=50')
        .then(r => { activityLogs.value = r.data.items || []; activityTotal.value = r.data.total || 0; })
        .catch(() => {})
        .finally(() => { activityLoading.value = false; });
    }
    onMounted(fetchData);
    watch(() => route.params.id, v => { if (v) fetchData(); });
    watch(activeTab, t => { if (t === 'activity' && !activityLogs.value.length) fetchActivity(); });
    watch(activityPage, () => fetchActivity());

    function goBack() { router.push('/accounts'); }
    function goToSite(id) { router.push('/sites/' + id); }
    function goToDomain(id) { router.push('/domains/' + id); }

    // Edit/Delete account
    const showEditAccountDialog = ref(false);
    const editAccountName = ref('');
    const editAccountSaving = ref(false);
    const showDeleteAccountConfirm = ref(false);
    const deletingAccount = ref(false);

    function editAccount() {
      if (!data.value || !data.value.account) return;
      editAccountName.value = data.value.account.name || '';
      showEditAccountDialog.value = true;
    }
    function updateAccount() {
      editAccountSaving.value = true;
      api.put('/wp-json/captaincore/v1/accounts/' + route.params.id, { name: editAccountName.value })
        .then(() => { data.value.account.name = editAccountName.value; showEditAccountDialog.value = false; showNotify('Account updated', 'success'); })
        .catch(() => showNotify('Failed to update account', 'error'))
        .finally(() => { editAccountSaving.value = false; });
    }
    function deleteAccount() {
      deletingAccount.value = true;
      api.delete('/wp-json/captaincore/v1/accounts/' + route.params.id)
        .then(() => { showNotify('Account deleted', 'success'); accountsFetched = false; router.push('/accounts'); })
        .catch(() => showNotify('Failed to delete account', 'error'))
        .finally(() => { deletingAccount.value = false; });
    }

    // Invites
    const newInviteEmail = ref('');
    const inviting = ref(false);
    function sendInvite() {
      if (!newInviteEmail.value) return;
      inviting.value = true;
      api.post('/wp-json/captaincore/v1/accounts/' + route.params.id + '/invites', { invite: newInviteEmail.value })
        .then(r => { showNotify(r.data.message || 'Invite sent', 'success'); newInviteEmail.value = ''; fetchData(); })
        .catch(() => showNotify('Failed to send invite', 'error'))
        .finally(() => { inviting.value = false; });
    }

    // Plan modification (admin)
    const showModifyPlanDialog = ref(false);
    const planForm = reactive({ name: '', interval: '12', price: '', limits: { storage: '', visits: '', sites: '' }, addons: [], charges: [], credits: [] });
    const planSaving = ref(false);
    const cancellingPlan = ref(false);

    function modifyPlan() {
      if (!data.value || !data.value.account || !data.value.account.plan) return;
      const p = data.value.account.plan;
      planForm.name = p.name || '';
      planForm.interval = p.interval || '12';
      planForm.price = p.price || '';
      planForm.limits.storage = p.limits ? p.limits.storage || '' : '';
      planForm.limits.visits = p.limits ? p.limits.visits || '' : '';
      planForm.limits.sites = p.limits ? p.limits.sites || '' : '';
      planForm.addons = p.addons ? p.addons.map(a => ({ ...a })) : [];
      planForm.charges = p.charges ? p.charges.map(c => ({ ...c })) : [];
      planForm.credits = p.credits ? p.credits.map(c => ({ ...c })) : [];
      showModifyPlanDialog.value = true;
    }
    function addPlanItem(type) { planForm[type].push({ name: '', quantity: '1', price: '' }); }
    function removePlanItem(type, i) { planForm[type].splice(i, 1); }
    function updatePlan() {
      planSaving.value = true;
      api.put('/wp-json/captaincore/v1/accounts/' + route.params.id + '/plan', { plan: { ...planForm } })
        .then(() => { showNotify('Plan updated', 'success'); showModifyPlanDialog.value = false; fetchData(); })
        .catch(() => showNotify('Failed to update plan', 'error'))
        .finally(() => { planSaving.value = false; });
    }
    function requestPlanChanges() {
      planSaving.value = true;
      const sub = (data.value.account && data.value.account.plan) ? { name: data.value.account.name, plan: { name: planForm.name, interval: planForm.interval } } : {};
      api.post('/wp-json/captaincore/v1/billing/request-plan-changes', { subscription: sub })
        .then(() => { showNotify('Plan change requested', 'success'); showModifyPlanDialog.value = false; })
        .catch(() => showNotify('Failed to request changes', 'error'))
        .finally(() => { planSaving.value = false; });
    }
    function cancelPlan() {
      if (!confirm('Cancel the plan for this account? This cannot be undone.')) return;
      cancellingPlan.value = true;
      const sub = { name: data.value.account ? data.value.account.name : '' };
      api.post('/wp-json/captaincore/v1/billing/cancel-plan', { subscription: sub })
        .then(() => { showNotify('Plan cancelled', 'success'); fetchData(); })
        .catch(() => showNotify('Failed to cancel plan', 'error'))
        .finally(() => { cancellingPlan.value = false; });
    }

    return { data, loading, activeTab, tabs, role, goBack, goToSite, goToDomain, newInviteEmail, inviting, sendInvite, activityLogs, activityTotal, activityPage, activityLoading, formatStorage, formatMoney, prettyTimestampEpoch, prettyTimestamp, sanitizeHtml, showEditAccountDialog, editAccountName, editAccountSaving, editAccount, updateAccount, showDeleteAccountConfirm, deletingAccount, deleteAccount, showModifyPlanDialog, planForm, planSaving, cancellingPlan, modifyPlan, addPlanItem, removePlanItem, updatePlan, requestPlanChanges, cancelPlan };
  },
  template: `
    <div v-if="loading" class="flex justify-center py-16"><div class="animate-spin rounded-full h-8 w-8 border-b-2" style="border-color: var(--color-primary)"></div></div>
    <div v-else-if="!data" class="surface rounded-xl p-8 text-center"><p style="color: var(--text-secondary)">Account not found.</p></div>
    <div v-else class="surface rounded-xl">
      <div class="detail-header">
        <button class="back-btn" @click="goBack"><svg-icon name="arrowLeft" :size="18" /></button>
        <h2 class="text-sm font-semibold flex-1" style="color: var(--text-primary)">{{ data.account ? data.account.name : 'Account' }}</h2>
        <div v-if="role === 'administrator'" class="flex items-center gap-2">
          <button @click="editAccount()" class="btn btn-sm btn-ghost" title="Edit"><svg-icon name="pencil" :size="14" /></button>
          <button @click="showDeleteAccountConfirm = true" class="btn btn-sm btn-ghost" title="Delete" style="color: var(--color-error)"><svg-icon name="trash" :size="14" /></button>
        </div>
      </div>
      <!-- Edit Account Dialog -->
      <div v-if="showEditAccountDialog" class="dialog-overlay" @click.self="showEditAccountDialog = false">
        <div class="dialog-card" style="max-width: 420px;">
          <div class="dialog-card-header"><h3 class="text-sm font-semibold" style="color: var(--text-primary)">Edit Account</h3><button @click="showEditAccountDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="close" :size="16" /></button></div>
          <div class="dialog-card-body"><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Account Name</label><input v-model="editAccountName" class="input-field" @keyup.enter="updateAccount" /></div>
          <div class="dialog-card-footer"><button @click="showEditAccountDialog = false" class="btn btn-ghost">Cancel</button><button @click="updateAccount" :disabled="editAccountSaving" class="btn btn-primary">{{ editAccountSaving ? 'Saving...' : 'Save' }}</button></div>
        </div>
      </div>
      <!-- Delete Account Confirm -->
      <div v-if="showDeleteAccountConfirm" class="dialog-overlay" @click.self="showDeleteAccountConfirm = false">
        <div class="dialog-card" style="max-width: 400px;">
          <div class="dialog-card-header"><h3 class="text-sm font-semibold" style="color: var(--color-error)">Delete Account</h3></div>
          <div class="dialog-card-body"><p class="text-sm" style="color: var(--text-primary)">Are you sure you want to delete <strong>{{ data.account ? data.account.name : '' }}</strong>? This cannot be undone.</p></div>
          <div class="dialog-card-footer"><button @click="showDeleteAccountConfirm = false" class="btn btn-ghost">Cancel</button><button @click="deleteAccount()" :disabled="deletingAccount" class="btn btn-danger">{{ deletingAccount ? 'Deleting...' : 'Delete' }}</button></div>
        </div>
      </div>
      <div class="px-4 pt-3"><tab-bar :tabs="tabs" v-model="activeTab" /></div>
      <div class="p-4">
        <!-- Users Tab -->
        <div v-if="activeTab === 'users'">
          <div class="flex items-center gap-2 mb-4">
            <input v-model="newInviteEmail" type="email" class="input-field flex-1" placeholder="Email address to invite..." style="max-width: 320px" />
            <button @click="sendInvite" :disabled="inviting || !newInviteEmail" class="btn btn-sm btn-primary">{{ inviting ? 'Sending...' : 'Send Invite' }}</button>
          </div>
          <h3 class="text-xs font-semibold uppercase tracking-wider mb-2" style="color: var(--text-secondary)">Users</h3>
          <table v-if="data.users && data.users.length" class="data-table mb-6">
            <thead><tr><th>Name</th><th>Email</th><th style="width:80px">Level</th></tr></thead>
            <tbody><tr v-for="u in data.users" :key="u.user_id"><td class="text-sm">{{ u.name }}</td><td class="text-sm">{{ u.email }}</td><td><span class="badge badge-default">{{ u.level || '—' }}</span></td></tr></tbody>
          </table>
          <div v-else class="text-sm py-4" style="color: var(--text-secondary)">No users.</div>
          <div v-if="data.invites && data.invites.length">
            <h3 class="text-xs font-semibold uppercase tracking-wider mb-2" style="color: var(--text-secondary)">Pending Invites</h3>
            <table class="data-table">
              <thead><tr><th>Email</th><th style="width:120px">Sent</th></tr></thead>
              <tbody><tr v-for="inv in data.invites" :key="inv.invite_id"><td class="text-sm">{{ inv.email }}</td><td class="text-sm" style="color: var(--text-secondary)">{{ prettyTimestamp(inv.created_at) }}</td></tr></tbody>
            </table>
          </div>
        </div>

        <!-- Sites Tab -->
        <div v-if="activeTab === 'sites'">
          <table v-if="data.sites && data.sites.length" class="data-table">
            <thead><tr><th>Name</th><th style="width:100px">Storage</th><th style="width:100px">Visits</th><th style="width:60px"></th></tr></thead>
            <tbody><tr v-for="s in data.sites" :key="s.site_id"><td class="text-sm">{{ s.name }}</td><td class="text-sm" style="color: var(--text-secondary)">{{ formatStorage(s.storage) }}</td><td class="text-sm" style="color: var(--text-secondary)">{{ s.visits || '—' }}</td><td><button @click="goToSite(s.site_id)" class="btn btn-sm btn-ghost"><svg-icon name="externalLink" :size="14" /></button></td></tr></tbody>
          </table>
          <div v-else class="text-sm py-4" style="color: var(--text-secondary)">No sites.</div>
        </div>

        <!-- Domains Tab -->
        <div v-if="activeTab === 'domains'">
          <table v-if="data.domains && data.domains.length" class="data-table">
            <thead><tr><th>Domain</th><th style="width:60px"></th></tr></thead>
            <tbody><tr v-for="d in data.domains" :key="d.domain_id"><td class="text-sm">{{ d.name }}</td><td><button @click="goToDomain(d.domain_id)" class="btn btn-sm btn-ghost"><svg-icon name="externalLink" :size="14" /></button></td></tr></tbody>
          </table>
          <div v-else class="text-sm py-4" style="color: var(--text-secondary)">No domains.</div>
        </div>

        <!-- Timeline Tab -->
        <div v-if="activeTab === 'timeline'">
          <div v-if="data.timeline && data.timeline.length">
            <div v-for="entry in data.timeline" :key="entry.process_log_id" class="timeline-item">
              <img v-if="entry.author_avatar" :src="entry.author_avatar" class="w-8 h-8 rounded-md flex-shrink-0" />
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1">
                  <span class="text-sm font-medium" style="color: var(--text-primary)">{{ entry.author }}</span>
                  <span class="text-xs" style="color: var(--text-secondary)">{{ prettyTimestampEpoch(entry.created_at) }}</span>
                </div>
                <div class="text-sm" style="color: var(--text-primary)" v-html="sanitizeHtml(entry.description)"></div>
                <div v-if="entry.websites && entry.websites.length" class="flex gap-1 mt-1 flex-wrap">
                  <button v-for="w in entry.websites" :key="w.site_id" @click="goToSite(w.site_id)" class="badge badge-default" style="cursor:pointer">{{ w.name }}</button>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-sm py-4" style="color: var(--text-secondary)">No timeline entries.</div>
        </div>

        <!-- Invoices Tab -->
        <div v-if="activeTab === 'invoices'">
          <table v-if="data.invoices && data.invoices.length" class="data-table">
            <thead><tr><th>Order</th><th>Date</th><th style="width:100px">Status</th><th style="width:100px">Total</th></tr></thead>
            <tbody><tr v-for="inv in data.invoices" :key="inv.order_id"><td class="text-sm">#{{ inv.order_id }}</td><td class="text-sm">{{ inv.date }}</td><td><span class="badge badge-default">{{ inv.status }}</span></td><td class="text-sm">{{ formatMoney(inv.total) }}</td></tr></tbody>
          </table>
          <div v-else class="text-sm py-4" style="color: var(--text-secondary)">No invoices.</div>
        </div>

        <!-- Plan Tab -->
        <div v-if="activeTab === 'plan'">
          <div v-if="data.account && data.account.plan" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="rounded-lg p-4" style="background: var(--hover-bg)">
              <div class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-secondary)">Storage</div>
              <div class="text-lg font-semibold" style="color: var(--text-primary)">{{ formatStorage(data.account.plan.usage ? data.account.plan.usage.storage : 0) }}</div>
              <div class="text-xs mb-2" style="color: var(--text-secondary)">of {{ data.account.plan.limits ? data.account.plan.limits.storage : '—' }} GB</div>
              <div v-if="data.account.plan.limits && data.account.plan.limits.storage" class="w-full rounded-full h-2" style="background: var(--border-color)">
                <div class="h-2 rounded-full" :style="{ width: Math.min(100, ((data.account.plan.usage ? data.account.plan.usage.storage / 1073741824 : 0) / data.account.plan.limits.storage) * 100) + '%', background: 'var(--color-primary)' }"></div>
              </div>
            </div>
            <div class="rounded-lg p-4" style="background: var(--hover-bg)">
              <div class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-secondary)">Visits</div>
              <div class="text-lg font-semibold" style="color: var(--text-primary)">{{ data.account.plan.usage ? Number(data.account.plan.usage.visits).toLocaleString() : 0 }}</div>
              <div class="text-xs mb-2" style="color: var(--text-secondary)">of {{ data.account.plan.limits ? Number(data.account.plan.limits.visits).toLocaleString() : '—' }}</div>
              <div v-if="data.account.plan.limits && data.account.plan.limits.visits" class="w-full rounded-full h-2" style="background: var(--border-color)">
                <div class="h-2 rounded-full" :style="{ width: Math.min(100, ((data.account.plan.usage ? data.account.plan.usage.visits : 0) / data.account.plan.limits.visits) * 100) + '%', background: 'var(--color-primary)' }"></div>
              </div>
            </div>
            <div class="rounded-lg p-4" style="background: var(--hover-bg)">
              <div class="text-xs uppercase tracking-wider mb-1" style="color: var(--text-secondary)">Sites</div>
              <div class="text-lg font-semibold" style="color: var(--text-primary)">{{ data.account.plan.usage ? data.account.plan.usage.sites : 0 }}</div>
              <div class="text-xs mb-2" style="color: var(--text-secondary)">of {{ data.account.plan.limits ? data.account.plan.limits.sites : '—' }}</div>
              <div v-if="data.account.plan.limits && data.account.plan.limits.sites" class="w-full rounded-full h-2" style="background: var(--border-color)">
                <div class="h-2 rounded-full" :style="{ width: Math.min(100, ((data.account.plan.usage ? data.account.plan.usage.sites : 0) / data.account.plan.limits.sites) * 100) + '%', background: 'var(--color-primary)' }"></div>
              </div>
            </div>
          </div>
          <div v-if="data.account && data.account.plan" class="rounded-lg p-4 mb-6" style="background: var(--hover-bg)">
            <div class="info-grid">
              <span class="info-label">Plan</span><span class="info-value">{{ data.account.plan.name || '—' }}</span>
              <span class="info-label">Price</span><span class="info-value">{{ formatMoney(data.account.plan.price) }}</span>
              <span class="info-label">Interval</span><span class="info-value">{{ data.account.plan.interval ? data.account.plan.interval + ' months' : '—' }}</span>
              <span class="info-label">Next Renewal</span><span class="info-value">{{ data.account.plan.next_renewal || '—' }}</span>
            </div>
            <div v-if="role === 'administrator'" class="flex gap-2 mt-4">
              <button @click="modifyPlan" class="btn btn-sm btn-outline">Modify Plan</button>
              <button @click="cancelPlan" :disabled="cancellingPlan" class="btn btn-sm btn-ghost" style="color: var(--color-error)">{{ cancellingPlan ? 'Cancelling...' : 'Cancel Plan' }}</button>
            </div>
          </div>
          <!-- Plan addons/charges/credits -->
          <div v-if="data.account && data.account.plan && data.account.plan.addons && data.account.plan.addons.length" class="mb-6">
            <h3 class="text-xs font-semibold uppercase tracking-wider mb-3" style="color: var(--text-secondary)">Addons</h3>
            <table class="data-table">
              <thead><tr><th>Name</th><th style="width:80px">Qty</th><th style="width:100px">Price</th></tr></thead>
              <tbody><tr v-for="a in data.account.plan.addons" :key="a.name"><td class="text-sm">{{ a.name }}</td><td class="text-sm">{{ a.quantity }}</td><td class="text-sm">{{ formatMoney(a.price) }}</td></tr></tbody>
            </table>
          </div>
          <!-- Per-site breakdown -->
          <div v-if="data.sites && data.sites.length">
            <h3 class="text-xs font-semibold uppercase tracking-wider mb-3" style="color: var(--text-secondary)">Per-Site Breakdown</h3>
            <table class="data-table">
              <thead><tr><th>Site</th><th style="width:100px">Storage</th><th style="width:100px">Visits</th></tr></thead>
              <tbody>
                <tr v-for="s in data.sites" :key="s.site_id">
                  <td class="text-sm"><router-link :to="'/sites/' + s.site_id" style="color: var(--color-primary); text-decoration: none;">{{ s.name }}</router-link></td>
                  <td class="text-sm" style="color: var(--text-secondary)">{{ formatStorage(s.storage) }}</td>
                  <td class="text-sm" style="color: var(--text-secondary)">{{ s.visits ? Number(s.visits).toLocaleString() : '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-if="!data.account || !data.account.plan" class="text-sm py-4" style="color: var(--text-secondary)">No plan data available.</div>
          <!-- Modify Plan Dialog -->
          <div v-if="showModifyPlanDialog" class="dialog-overlay" @click.self="showModifyPlanDialog = false">
            <div class="dialog-card" style="max-width: 640px;">
              <div class="dialog-card-header"><h3 class="text-sm font-semibold" style="color: var(--text-primary)">Modify Plan</h3><button @click="showModifyPlanDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="close" :size="16" /></button></div>
              <div class="dialog-card-body space-y-4" style="max-height: 70vh; overflow-y: auto;">
                <div class="grid grid-cols-2 gap-4">
                  <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Plan Name</label><input v-model="planForm.name" class="input-field" /></div>
                  <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Price</label><input v-model="planForm.price" type="number" step="0.01" class="input-field" /></div>
                </div>
                <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Interval (months)</label>
                  <select v-model="planForm.interval" class="select-field"><option value="1">Monthly</option><option value="3">Quarterly</option><option value="6">Semi-Annual</option><option value="12">Annual</option></select>
                </div>
                <h4 class="text-xs font-semibold uppercase tracking-wider" style="color: var(--text-secondary)">Limits</h4>
                <div class="grid grid-cols-3 gap-4">
                  <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Storage (GB)</label><input v-model="planForm.limits.storage" type="number" class="input-field" /></div>
                  <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Visits</label><input v-model="planForm.limits.visits" type="number" class="input-field" /></div>
                  <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Sites</label><input v-model="planForm.limits.sites" type="number" class="input-field" /></div>
                </div>
                <template v-for="type in ['addons', 'charges', 'credits']" :key="type">
                  <div class="flex items-center justify-between mt-2">
                    <h4 class="text-xs font-semibold uppercase tracking-wider capitalize" style="color: var(--text-secondary)">{{ type }}</h4>
                    <button @click="addPlanItem(type)" class="btn btn-sm btn-ghost"><svg-icon name="plus" :size="14" /></button>
                  </div>
                  <div v-for="(item, i) in planForm[type]" :key="i" class="flex gap-2 items-center">
                    <input v-model="item.name" class="input-field flex-1" placeholder="Name" />
                    <input v-model="item.quantity" type="number" class="input-field" style="width: 70px;" placeholder="Qty" />
                    <input v-model="item.price" type="number" step="0.01" class="input-field" style="width: 100px;" placeholder="Price" />
                    <button @click="removePlanItem(type, i)" class="btn btn-sm btn-ghost" style="color: var(--color-error)"><svg-icon name="trash" :size="14" /></button>
                  </div>
                </template>
              </div>
              <div class="dialog-card-footer">
                <button @click="requestPlanChanges" :disabled="planSaving" class="btn btn-outline mr-auto">Request Changes</button>
                <button @click="showModifyPlanDialog = false" class="btn btn-ghost">Cancel</button>
                <button @click="updatePlan" :disabled="planSaving" class="btn btn-primary">{{ planSaving ? 'Saving...' : 'Update Plan' }}</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Activity Tab -->
        <div v-if="activeTab === 'activity'">
          <div v-if="activityLoading" class="flex justify-center py-8"><div class="animate-spin rounded-full h-6 w-6 border-b-2" style="border-color: var(--color-primary)"></div></div>
          <div v-else-if="!activityLogs.length" class="text-sm py-4" style="color: var(--text-secondary)">No activity.</div>
          <table v-else class="data-table">
            <thead><tr><th style="width:140px">Date</th><th>User</th><th style="width:100px">Action</th><th>Entity</th><th>Description</th></tr></thead>
            <tbody><tr v-for="log in activityLogs" :key="log.created_at + log.action"><td class="text-sm" style="color: var(--text-secondary)">{{ prettyTimestampEpoch(log.created_at) }}</td><td class="text-sm">{{ log.user_name }}</td><td><span class="badge badge-default">{{ log.action }}</span></td><td class="text-sm">{{ log.entity_name }}</td><td class="text-sm" style="color: var(--text-secondary)">{{ log.description }}</td></tr></tbody>
          </table>
          <div v-if="activityTotal > 50" class="flex justify-center gap-2 mt-4">
            <button @click="activityPage--" :disabled="activityPage <= 1" class="btn btn-sm btn-outline">Previous</button>
            <span class="text-sm py-1" style="color: var(--text-secondary)">Page {{ activityPage }} of {{ Math.ceil(activityTotal / 50) }}</span>
            <button @click="activityPage++" :disabled="activityPage >= Math.ceil(activityTotal / 50)" class="btn btn-sm btn-outline">Next</button>
          </div>
        </div>
      </div>
    </div>
  `,
});

// ─── View: ActivityLogsView (Admin) ──────────────────────────────────────────
const ActivityLogsView = defineComponent({
  setup() {
    const { showNotify } = useNotify();
    const { filteredSites, fetchSites } = useSites();
    const role = userRole.value;
    const logs = ref([]);
    const total = ref(0);
    const page = ref(1);
    const loading = ref(true);
    const filters = reactive({ action: '', entity_type: '', date_from: '', date_to: '' });
    const actionOptions = ['', 'created', 'updated', 'deleted', 'toggled', 'deployed', 'shared', 'unshared', 'invited', 'locked', 'unlocked', 'transferred', 'requested_removal', 'cancelled_removal'];
    const entityOptions = ['', 'site', 'domain', 'dns_record', 'environment', 'account', 'email_forward'];

    // Manual log entry
    const showLogEntryDialog = ref(false);
    const logEntry = reactive({ sites: [], siteSearch: '', process: '', description: '' });
    const processes = ref([]);
    const processesLoaded = ref(false);

    function fetchLogs() {
      loading.value = true;
      const params = new URLSearchParams();
      params.append('page', page.value);
      params.append('per_page', 50);
      if (filters.action) params.append('action', filters.action);
      if (filters.entity_type) params.append('entity_type', filters.entity_type);
      if (filters.date_from) params.append('date_from', filters.date_from);
      if (filters.date_to) params.append('date_to', filters.date_to);
      api.get('/wp-json/captaincore/v1/activity-logs?' + params.toString())
        .then(r => { logs.value = r.data.items || []; total.value = r.data.total || 0; })
        .catch(() => showNotify('Failed to load logs', 'error'))
        .finally(() => { loading.value = false; });
    }

    function applyFilter() { page.value = 1; fetchLogs(); }
    function clearFilters() { filters.action = ''; filters.entity_type = ''; filters.date_from = ''; filters.date_to = ''; applyFilter(); }
    const hasFilters = computed(() => filters.action || filters.entity_type || filters.date_from || filters.date_to);

    function actionColor(action) {
      const colors = { created: 'success', updated: 'info', deleted: 'error', toggled: 'warning', deployed: 'info', locked: 'warning', unlocked: 'success' };
      return colors[action] || 'default';
    }

    function openLogEntry() {
      logEntry.sites = [];
      logEntry.siteSearch = '';
      logEntry.process = '';
      logEntry.description = '';
      showLogEntryDialog.value = true;
      fetchSites();
      if (!processesLoaded.value) {
        api.get('/wp-json/captaincore/v1/processes')
          .then(r => { processes.value = r.data || []; processesLoaded.value = true; })
          .catch(() => {});
      }
    }

    const filteredLogSites = computed(() => {
      if (!logEntry.siteSearch) return filteredSites.value.slice(0, 20);
      const q = logEntry.siteSearch.toLowerCase();
      return filteredSites.value.filter(s => s.name && s.name.toLowerCase().includes(q)).slice(0, 20);
    });

    function toggleLogSite(site) {
      const idx = logEntry.sites.findIndex(s => s.site_id === site.site_id);
      if (idx >= 0) logEntry.sites.splice(idx, 1);
      else logEntry.sites.push(site);
    }

    function submitLogEntry() {
      if (!logEntry.description) { showNotify('Description is required', 'error'); return; }
      api.post('/wp-json/captaincore/v1/process-logs', {
        site_ids: logEntry.sites.map(s => s.site_id),
        process_id: logEntry.process,
        description: logEntry.description,
      })
        .then(() => { showNotify('Log entry added', 'success'); showLogEntryDialog.value = false; fetchLogs(); })
        .catch(() => showNotify('Failed to add log entry', 'error'));
    }

    function exportLogs() {
      const rows = [['Date', 'User', 'Action', 'Type', 'Entity', 'Description']];
      logs.value.forEach(l => {
        rows.push([
          l.created_at ? new Date(l.created_at * 1000).toISOString() : '',
          l.user_name || '', l.action || '',
          (l.entity_type || '').replace(/_/g, ' '),
          l.entity_name || '',
          (l.description || '').replace(/"/g, '""'),
        ]);
      });
      const csv = rows.map(r => r.map(c => '"' + c + '"').join(',')).join('\n');
      const blob = new Blob([csv], { type: 'text/csv' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'activity-logs.csv';
      a.click();
      URL.revokeObjectURL(a.href);
    }

    onMounted(fetchLogs);
    watch(page, fetchLogs);
    return { logs, total, page, loading, filters, actionOptions, entityOptions, applyFilter, clearFilters, hasFilters, actionColor, prettyTimestampEpoch, role, showLogEntryDialog, logEntry, processes, filteredLogSites, toggleLogSite, openLogEntry, submitLogEntry, exportLogs };
  },
  template: `
    <div class="surface rounded-xl">
      <div class="px-4 py-3 flex items-center justify-between" style="border-bottom: 1px solid var(--border-color)">
        <h2 class="text-sm font-semibold" style="color: var(--text-primary)">Activity Logs</h2>
        <div class="flex items-center gap-2">
          <button @click="exportLogs" :disabled="!logs.length" class="btn btn-sm btn-outline">Export CSV</button>
          <button @click="openLogEntry" class="btn btn-sm btn-primary">Add Log Entry</button>
        </div>
      </div>

      <!-- Filters -->
      <div class="px-4 py-3 flex flex-wrap items-end gap-3" style="border-bottom: 1px solid var(--border-color)">
        <div>
          <label class="block text-xs mb-1" style="color: var(--text-secondary)">Action</label>
          <select v-model="filters.action" @change="applyFilter" class="input text-sm" style="min-width: 140px">
            <option value="">All actions</option>
            <option v-for="a in actionOptions.slice(1)" :key="a" :value="a">{{ a }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs mb-1" style="color: var(--text-secondary)">Entity Type</label>
          <select v-model="filters.entity_type" @change="applyFilter" class="input text-sm" style="min-width: 140px">
            <option value="">All types</option>
            <option v-for="e in entityOptions.slice(1)" :key="e" :value="e">{{ e.replace(/_/g, ' ') }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs mb-1" style="color: var(--text-secondary)">From</label>
          <input type="date" v-model="filters.date_from" @change="applyFilter" class="input text-sm" />
        </div>
        <div>
          <label class="block text-xs mb-1" style="color: var(--text-secondary)">To</label>
          <input type="date" v-model="filters.date_to" @change="applyFilter" class="input text-sm" />
        </div>
        <button v-if="hasFilters" @click="clearFilters" class="btn btn-sm btn-outline">Clear</button>
      </div>

      <div v-if="loading" class="flex justify-center py-16"><div class="animate-spin rounded-full h-8 w-8 border-b-2" style="border-color: var(--color-primary)"></div></div>
      <div v-else>
        <table class="data-table">
          <thead><tr><th style="width:140px">Date</th><th>User</th><th style="width:100px">Action</th><th>Type</th><th>Entity</th><th>Description</th></tr></thead>
          <tbody><tr v-for="log in logs" :key="log.created_at + log.action + log.entity_name">
            <td class="text-sm" style="color: var(--text-secondary)">{{ prettyTimestampEpoch(log.created_at) }}</td>
            <td class="text-sm">{{ log.user_name }}</td>
            <td><span :class="'badge badge-' + actionColor(log.action)">{{ log.action }}</span></td>
            <td class="text-sm">{{ log.entity_type ? log.entity_type.replace(/_/g, ' ') : '' }}</td>
            <td class="text-sm">{{ log.entity_name }}</td>
            <td class="text-sm" style="color: var(--text-secondary)">{{ log.description }}</td>
          </tr></tbody>
        </table>
        <div v-if="total > 50" class="flex justify-center gap-2 p-4">
          <button @click="page--" :disabled="page <= 1" class="btn btn-sm btn-outline">Previous</button>
          <span class="text-sm py-1" style="color: var(--text-secondary)">Page {{ page }} of {{ Math.ceil(total / 50) }}</span>
          <button @click="page++" :disabled="page >= Math.ceil(total / 50)" class="btn btn-sm btn-outline">Next</button>
        </div>
      </div>
    </div>

    <!-- Add Log Entry Dialog -->
    <div v-if="showLogEntryDialog" class="dialog-overlay" @mousedown.self="showLogEntryDialog = false">
      <div class="dialog-card" style="width: 500px">
        <div class="dialog-header">
          <h3 class="dialog-title">Add Log Entry</h3>
          <button @click="showLogEntryDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="x" :size="18" /></button>
        </div>
        <div class="dialog-body" style="max-height: 60vh; overflow-y: auto">
          <!-- Process selector (admin) -->
          <div v-if="role === 'administrator'" class="mb-4">
            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary)">Process</label>
            <select v-model="logEntry.process" class="input w-full">
              <option value="">None</option>
              <option v-for="p in processes" :key="p.process_id || p.id" :value="p.process_id || p.id">{{ p.name || p.title }}</option>
            </select>
          </div>
          <!-- Sites multi-select -->
          <div class="mb-4">
            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary)">Sites</label>
            <div v-if="logEntry.sites.length" class="flex flex-wrap gap-1 mb-2">
              <span v-for="s in logEntry.sites" :key="s.site_id" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium" style="background: var(--color-primary); color: white">
                {{ s.name }}
                <button @click="toggleLogSite(s)" class="hover:opacity-70">&times;</button>
              </span>
            </div>
            <input v-model="logEntry.siteSearch" placeholder="Search sites..." class="input w-full mb-1" />
            <div style="max-height: 150px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 6px">
              <div v-for="s in filteredLogSites" :key="s.site_id" @click="toggleLogSite(s)" class="flex items-center gap-2 px-3 py-1.5 cursor-pointer text-sm" style="border-bottom: 1px solid var(--border-color)" :style="{ background: logEntry.sites.some(x => x.site_id === s.site_id) ? 'var(--active-bg)' : 'transparent' }">
                <span class="w-4 h-4 rounded border flex items-center justify-center" :style="{ borderColor: logEntry.sites.some(x => x.site_id === s.site_id) ? 'var(--color-primary)' : 'var(--border-color)', background: logEntry.sites.some(x => x.site_id === s.site_id) ? 'var(--color-primary)' : 'transparent' }">
                  <svg v-if="logEntry.sites.some(x => x.site_id === s.site_id)" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><path d="M5 13l4 4L19 7"/></svg>
                </span>
                {{ s.name }}
              </div>
            </div>
          </div>
          <!-- Description -->
          <div class="mb-4">
            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary)">Description</label>
            <textarea v-model="logEntry.description" rows="4" class="input w-full" placeholder="Describe the action taken..."></textarea>
          </div>
        </div>
        <div class="dialog-footer">
          <button @click="showLogEntryDialog = false" class="btn btn-outline">Cancel</button>
          <button @click="submitLogEntry" class="btn btn-primary">Add Log Entry</button>
        </div>
      </div>
    </div>
  `,
});

// ─── View: UsersView (Admin) ─────────────────────────────────────────────────
const UsersView = defineComponent({
  components: { DataTable },
  setup() {
    const { showNotify } = useNotify();
    const users = ref([]);
    const loading = ref(true);
    const search = ref('');
    const headers = [
      { title: 'Name', value: 'name' },
      { title: 'Username', value: 'user_login' },
      { title: 'Email', value: 'email' },
      { title: 'Actions', value: '_actions', width: '100px', sortable: false },
    ];

    const showUserDialog = ref(false);
    const editingUser = ref(null);
    const userForm = reactive({ first_name: '', last_name: '', email: '', login: '' });
    const userSaving = ref(false);

    function fetchUsers() {
      loading.value = true;
      api.get('/wp-json/captaincore/v1/users')
        .then(r => { users.value = r.data || []; })
        .finally(() => { loading.value = false; });
    }
    onMounted(fetchUsers);

    function newUser() {
      editingUser.value = null;
      userForm.first_name = '';
      userForm.last_name = '';
      userForm.email = '';
      userForm.login = '';
      showUserDialog.value = true;
    }
    function editUser(event, { item }) {
      editingUser.value = item;
      userForm.first_name = item.first_name || '';
      userForm.last_name = item.last_name || '';
      userForm.email = item.email || '';
      userForm.login = item.user_login || '';
      showUserDialog.value = true;
    }
    function saveUser() {
      userSaving.value = true;
      const payload = { first_name: userForm.first_name, last_name: userForm.last_name, email: userForm.email, login: userForm.login };
      const req = editingUser.value
        ? api.put('/wp-json/captaincore/v1/users/' + (editingUser.value.user_id || editingUser.value.ID), payload)
        : api.post('/wp-json/captaincore/v1/users', payload);
      req
        .then(() => { showNotify(editingUser.value ? 'User updated' : 'User created', 'success'); showUserDialog.value = false; fetchUsers(); })
        .catch(err => { showNotify(err.response && err.response.data && err.response.data.message ? err.response.data.message : 'Failed to save user', 'error'); })
        .finally(() => { userSaving.value = false; });
    }
    function deleteUser(user) {
      if (!confirm('Delete user "' + (user.name || user.user_login) + '"?')) return;
      api.delete('/wp-json/captaincore/v1/users/' + (user.user_id || user.ID))
        .then(() => { showNotify('User deleted', 'success'); fetchUsers(); })
        .catch(() => showNotify('Failed to delete user', 'error'));
    }

    return { users, loading, search, headers, showUserDialog, editingUser, userForm, userSaving, newUser, editUser, saveUser, deleteUser };
  },
  template: `
    <div class="surface rounded-xl">
      <div class="flex items-center justify-between px-4 py-3" style="border-bottom: 1px solid var(--border-color)">
        <h2 class="text-sm font-semibold" style="color: var(--text-primary)">Users</h2>
        <div class="flex items-center gap-2">
          <div class="search-wrapper"><svg-icon name="search" :size="16" class="search-icon" /><input v-model="search" type="text" placeholder="Search users..." class="input-field" style="width: 220px" /></div>
          <button @click="newUser" class="btn btn-sm btn-primary"><svg-icon name="plus" :size="14" /> New</button>
        </div>
      </div>
      <data-table :headers="headers" :items="users" :search="search" :loading="loading" :clickable="true" @click:row="editUser">
        <template #item._actions="{ item }">
          <button @click.stop="deleteUser(item)" class="btn btn-sm btn-ghost" style="color: var(--color-error)"><svg-icon name="trash" :size="14" /></button>
        </template>
      </data-table>
      <!-- User Dialog -->
      <div v-if="showUserDialog" class="dialog-overlay" @click.self="showUserDialog = false">
        <div class="dialog-card" style="max-width: 480px;">
          <div class="dialog-card-header"><h3 class="text-sm font-semibold" style="color: var(--text-primary)">{{ editingUser ? 'Edit User' : 'New User' }}</h3><button @click="showUserDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="close" :size="16" /></button></div>
          <div class="dialog-card-body space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">First Name</label><input v-model="userForm.first_name" class="input-field" /></div>
              <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Last Name</label><input v-model="userForm.last_name" class="input-field" /></div>
            </div>
            <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Email</label><input v-model="userForm.email" type="email" class="input-field" /></div>
            <div v-if="!editingUser"><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Username</label><input v-model="userForm.login" class="input-field" /></div>
          </div>
          <div class="dialog-card-footer"><button @click="showUserDialog = false" class="btn btn-ghost">Cancel</button><button @click="saveUser" :disabled="userSaving || !userForm.email" class="btn btn-primary">{{ userSaving ? 'Saving...' : 'Save' }}</button></div>
        </div>
      </div>
    </div>
  `,
});

// ─── View: CookbookView ──────────────────────────────────────────────────────
const CookbookView = defineComponent({
  components: { DataTable },
  setup() {
    const { showNotify } = useNotify();
    const role = userRole;
    const recipes = ref([]);
    const loading = ref(true);
    const search = ref('');
    const headers = [{ title: 'Title', value: 'title' }];

    // Recipe CRUD
    const showRecipeDialog = ref(false);
    const editingRecipe = ref(null);
    const recipeForm = reactive({ title: '', content: '', public: false });
    const recipeSaving = ref(false);

    function fetchRecipes() {
      loading.value = true;
      api.get('/wp-json/captaincore/v1/recipes')
        .then(r => { recipes.value = r.data || []; })
        .finally(() => { loading.value = false; });
    }
    onMounted(fetchRecipes);

    function newRecipe() {
      editingRecipe.value = null;
      recipeForm.title = '';
      recipeForm.content = '';
      recipeForm.public = false;
      showRecipeDialog.value = true;
    }
    function editRecipe(event, { item }) {
      editingRecipe.value = item;
      recipeForm.title = item.title || '';
      recipeForm.content = item.content || '';
      recipeForm.public = !!item.public;
      showRecipeDialog.value = true;
    }
    function saveRecipe() {
      recipeSaving.value = true;
      const payload = { title: recipeForm.title, content: recipeForm.content, public: recipeForm.public ? 1 : 0 };
      const req = editingRecipe.value
        ? api.put('/wp-json/captaincore/v1/recipes/' + (editingRecipe.value.recipe_id || editingRecipe.value.id), payload)
        : api.post('/wp-json/captaincore/v1/recipes', payload);
      req
        .then(() => { showNotify(editingRecipe.value ? 'Recipe updated' : 'Recipe created', 'success'); showRecipeDialog.value = false; fetchRecipes(); })
        .catch(() => showNotify('Failed to save recipe', 'error'))
        .finally(() => { recipeSaving.value = false; });
    }
    function deleteRecipe(recipe) {
      if (!confirm('Delete recipe "' + recipe.title + '"?')) return;
      api.delete('/wp-json/captaincore/v1/recipes/' + (recipe.recipe_id || recipe.id))
        .then(() => { showNotify('Recipe deleted', 'success'); fetchRecipes(); })
        .catch(() => showNotify('Failed to delete recipe', 'error'));
    }
    function loadIntoTerminal(recipe) {
      terminalState.code = recipe.content || '';
      terminalState.open = true;
    }

    return { recipes, loading, search, headers, role, showRecipeDialog, editingRecipe, recipeForm, recipeSaving, newRecipe, editRecipe, saveRecipe, deleteRecipe, loadIntoTerminal };
  },
  template: `
    <div class="surface rounded-xl">
      <div class="flex items-center justify-between px-4 py-3" style="border-bottom: 1px solid var(--border-color)">
        <h2 class="text-sm font-semibold" style="color: var(--text-primary)">Cookbook</h2>
        <div class="flex items-center gap-2">
          <div class="search-wrapper"><svg-icon name="search" :size="16" class="search-icon" /><input v-model="search" type="text" placeholder="Search recipes..." class="input-field" style="width: 220px" /></div>
          <button v-if="role === 'administrator'" @click="newRecipe" class="btn btn-sm btn-primary"><svg-icon name="plus" :size="14" /> New</button>
        </div>
      </div>
      <data-table :headers="headers" :items="recipes" :search="search" :loading="loading" :clickable="true" @click:row="editRecipe" />
      <!-- Recipe Dialog -->
      <div v-if="showRecipeDialog" class="dialog-overlay" @click.self="showRecipeDialog = false">
        <div class="dialog-card" style="max-width: 700px;">
          <div class="dialog-card-header">
            <h3 class="text-sm font-semibold" style="color: var(--text-primary)">{{ editingRecipe ? 'Edit Recipe' : 'New Recipe' }}</h3>
            <button @click="showRecipeDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="close" :size="16" /></button>
          </div>
          <div class="dialog-card-body space-y-4">
            <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Title</label><input v-model="recipeForm.title" class="input-field" /></div>
            <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Code</label><textarea v-model="recipeForm.content" class="textarea-field" style="font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, monospace; min-height: 200px; font-size: 0.8125rem;"></textarea></div>
            <div class="flex items-center gap-2"><button :class="['toggle', recipeForm.public && 'on']" @click="recipeForm.public = !recipeForm.public"></button><span class="text-sm" style="color: var(--text-primary)">Public</span></div>
          </div>
          <div class="dialog-card-footer">
            <button v-if="editingRecipe" @click="loadIntoTerminal(editingRecipe)" class="btn btn-outline mr-auto"><svg-icon name="terminal" :size="14" /> Load in Terminal</button>
            <button v-if="editingRecipe && role === 'administrator'" @click="deleteRecipe(editingRecipe); showRecipeDialog = false" class="btn btn-ghost" style="color: var(--color-error)">Delete</button>
            <button @click="showRecipeDialog = false" class="btn btn-ghost">Cancel</button>
            <button v-if="role === 'administrator'" @click="saveRecipe" :disabled="recipeSaving || !recipeForm.title" class="btn btn-primary">{{ recipeSaving ? 'Saving...' : 'Save' }}</button>
          </div>
        </div>
      </div>
    </div>
  `,
});

// ─── View: HealthView ────────────────────────────────────────────────────────
const HealthView = defineComponent({
  setup() {
    const { filteredSites, fetchSites } = useSites();
    const { showNotify } = useNotify();
    const router = useRouter();
    const scanning = reactive({});
    const expandedSite = ref(null);

    onMounted(fetchSites);

    const sitesWithErrors = computed(() => {
      return filteredSites.value.filter(s => s.console_errors && s.console_errors !== '' && (Array.isArray(s.console_errors) ? s.console_errors.length > 0 : true));
    });

    function scanErrors(site) {
      scanning[site.site_id] = true;
      api.post('/wp-json/captaincore/v1/sites/cli', { post_id: site.site_id, command: 'scan-errors' })
        .then(() => showNotify('Scan started for ' + site.name, 'success'))
        .catch(() => showNotify('Failed to start scan', 'error'))
        .finally(() => { scanning[site.site_id] = false; });
    }

    function toggleExpand(siteId) {
      expandedSite.value = expandedSite.value === siteId ? null : siteId;
    }

    function goToSite(id) { router.push('/sites/' + id); }

    return { sitesWithErrors, scanning, scanErrors, expandedSite, toggleExpand, goToSite };
  },
  template: `
    <div class="surface rounded-xl">
      <div class="px-4 py-3" style="border-bottom: 1px solid var(--border-color)">
        <h2 class="text-sm font-semibold" style="color: var(--text-primary)">Health</h2>
        <p class="text-xs mt-1" style="color: var(--text-secondary)">Results from daily scans of home pages. Web console errors are extracted from Google Chrome via Lighthouse CLI.</p>
      </div>
      <div v-if="!sitesWithErrors.length" class="p-8 text-center text-sm" style="color: var(--text-secondary)">No sites with issues found.</div>
      <div v-else>
        <div class="px-4 py-2 text-xs" style="color: var(--text-secondary); border-bottom: 1px solid var(--border-color)">{{ sitesWithErrors.length }} site{{ sitesWithErrors.length !== 1 ? 's' : '' }} with issues</div>
        <div v-for="site in sitesWithErrors" :key="site.site_id" style="border-bottom: 1px solid var(--border-color)">
          <div class="flex items-center justify-between px-4 py-3 cursor-pointer" @click="toggleExpand(site.site_id)" :style="{ background: expandedSite === site.site_id ? 'var(--active-bg)' : 'transparent' }">
            <div class="flex items-center gap-2">
              <svg-icon :name="expandedSite === site.site_id ? 'chevronDown' : 'chevronRight'" :size="14" style="color: var(--text-secondary)" />
              <span class="text-sm font-medium cursor-pointer" style="color: var(--color-primary)" @click.stop="goToSite(site.site_id)">{{ site.name }}</span>
              <span class="badge badge-error">{{ Array.isArray(site.console_errors) ? site.console_errors.length : '!' }} error{{ Array.isArray(site.console_errors) && site.console_errors.length !== 1 ? 's' : '' }}</span>
            </div>
            <button @click.stop="scanErrors(site)" :disabled="scanning[site.site_id]" class="btn btn-sm btn-outline">
              <svg-icon name="sync" :size="14" /> {{ scanning[site.site_id] ? 'Scanning...' : 'Scan' }}
            </button>
          </div>
          <div v-if="expandedSite === site.site_id && Array.isArray(site.console_errors)" class="px-4 pb-3 space-y-2">
            <div v-for="(error, i) in site.console_errors" :key="i" class="rounded-lg p-3" style="background: var(--hover-bg)">
              <div class="text-xs font-semibold mb-1" style="color: var(--text-primary)">{{ error.source }}</div>
              <a v-if="error.url" :href="error.url" target="_blank" class="text-xs block mb-1" style="color: var(--color-primary)">{{ error.url }}</a>
              <pre v-if="error.description" class="text-xs mt-1 whitespace-pre-wrap" style="color: var(--text-secondary); font-family: monospace; margin: 0">{{ error.description }}</pre>
            </div>
          </div>
        </div>
      </div>
    </div>
  `,
});

// ─── View: ArchivesView ──────────────────────────────────────────────────────
const ArchivesView = defineComponent({
  components: { DataTable },
  setup() {
    const { showNotify } = useNotify();
    const archives = ref([]);
    const loading = ref(true);
    const search = ref('');
    const headers = [
      { title: 'Name', value: 'name' },
      { title: 'Size', value: 'size', width: '100px' },
      { title: 'Modified', value: 'modified', width: '140px' },
      { title: '', value: '_actions', width: '80px', sortable: false },
    ];
    const showShareDialog = ref(false);
    const shareUrl = ref('');
    const shareLoading = ref(false);

    onMounted(() => {
      api.get('/wp-json/captaincore/v1/archives')
        .then(r => { archives.value = r.data || []; })
        .finally(() => { loading.value = false; });
    });

    function generateShareLink(item) {
      shareLoading.value = true;
      shareUrl.value = '';
      showShareDialog.value = true;
      api.post('/wp-json/captaincore/v1/archive/share', { file: item.path || item.name })
        .then(r => { shareUrl.value = typeof r.data === 'string' ? r.data : r.data.url || ''; })
        .catch(() => showNotify('Failed to generate link', 'error'))
        .finally(() => { shareLoading.value = false; });
    }

    function copyShareUrl() {
      navigator.clipboard.writeText(shareUrl.value);
      showNotify('Link copied to clipboard', 'success');
    }

    return { archives, loading, search, headers, showShareDialog, shareUrl, shareLoading, generateShareLink, copyShareUrl };
  },
  template: `
    <div class="surface rounded-xl">
      <div class="flex items-center justify-between px-4 py-3" style="border-bottom: 1px solid var(--border-color)">
        <h2 class="text-sm font-semibold" style="color: var(--text-primary)">Archives</h2>
        <div class="search-wrapper"><svg-icon name="search" :size="16" class="search-icon" /><input v-model="search" type="text" placeholder="Search archives..." class="input-field" style="width: 220px" /></div>
      </div>
      <data-table :headers="headers" :items="archives" :search="search" :loading="loading">
        <template #item._actions="{ item }">
          <button @click.stop="generateShareLink(item)" class="btn btn-sm btn-outline" title="Get shareable link">
            <svg-icon name="link" :size="14" />
          </button>
        </template>
      </data-table>
    </div>
    <!-- Share Link Dialog -->
    <div v-if="showShareDialog" class="dialog-overlay" @mousedown.self="showShareDialog = false">
      <div class="dialog-card" style="width: 500px">
        <div class="dialog-header">
          <h3 class="dialog-title">Download Link</h3>
          <button @click="showShareDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="x" :size="18" /></button>
        </div>
        <div class="dialog-body">
          <div v-if="shareLoading" class="flex justify-center py-4"><div class="animate-spin rounded-full h-6 w-6 border-b-2" style="border-color: var(--color-primary)"></div></div>
          <div v-else-if="shareUrl">
            <p class="text-xs mb-2" style="color: var(--text-secondary)">Public download link (valid for 7 days)</p>
            <div class="flex gap-2">
              <input :value="shareUrl" readonly class="input w-full text-sm" style="font-family: monospace" />
              <button @click="copyShareUrl" class="btn btn-sm btn-primary" style="flex-shrink: 0">Copy</button>
            </div>
          </div>
          <div v-else class="text-sm" style="color: var(--text-secondary)">Failed to generate link.</div>
        </div>
      </div>
    </div>
  `,
});

// ─── View: WebRiskView ───────────────────────────────────────────────────────
const WebRiskView = defineComponent({
  setup() {
    const logs = ref([]);
    const loading = ref(true);
    const selectedLog = ref(null);

    onMounted(() => {
      api.get('/wp-json/captaincore/v1/web-risk/logs')
        .then(r => { logs.value = r.data || []; })
        .finally(() => { loading.value = false; });
    });

    function getDetails(log) {
      if (!log.details) return { threats: [], errors: [] };
      const d = typeof log.details === 'string' ? JSON.parse(log.details) : log.details;
      return { threats: d.threats || [], errors: d.errors || [] };
    }

    function threatColor(type) {
      if (type === 'MALWARE') return 'error';
      if (type === 'SOCIAL_ENGINEERING' || type === 'UNWANTED_SOFTWARE') return 'warning';
      return 'default';
    }

    return { logs, loading, prettyTimestamp, selectedLog, getDetails, threatColor };
  },
  template: `
    <div class="surface rounded-xl">
      <div class="px-4 py-3" style="border-bottom: 1px solid var(--border-color)"><h2 class="text-sm font-semibold" style="color: var(--text-primary)">Web Risk</h2></div>
      <div v-if="loading" class="flex justify-center py-16"><div class="animate-spin rounded-full h-8 w-8 border-b-2" style="border-color: var(--color-primary)"></div></div>
      <div v-else-if="!logs.length" class="p-8 text-center text-sm" style="color: var(--text-secondary)">No web risk logs.</div>
      <table v-else class="data-table">
        <thead><tr><th>Date</th><th style="width:120px">Sites Checked</th><th style="width:100px">Threats</th><th style="width:100px">Errors</th><th style="width:60px"></th></tr></thead>
        <tbody>
          <tr v-for="log in logs" :key="log.web_risk_log_id" class="cursor-pointer" @click="selectedLog = log">
            <td class="text-sm">{{ prettyTimestamp(log.created_at) }}</td>
            <td class="text-sm text-center">{{ log.total_sites || log.sites_checked || 0 }}</td>
            <td class="text-sm text-center"><span v-if="(log.threats_found || log.threats || 0) > 0" class="badge badge-error">{{ log.threats_found || log.threats }}</span><span v-else class="badge badge-success">0</span></td>
            <td class="text-sm text-center"><span v-if="(log.errors_count || log.errors || 0) > 0" class="badge badge-warning">{{ log.errors_count || log.errors }}</span><span v-else>0</span></td>
            <td><svg-icon name="chevronRight" :size="14" style="color: var(--text-secondary)" /></td>
          </tr>
        </tbody>
      </table>
    </div>
    <!-- Detail Dialog -->
    <div v-if="selectedLog" class="dialog-overlay" @mousedown.self="selectedLog = null">
      <div class="dialog-card" style="width: 700px">
        <div class="dialog-header">
          <h3 class="dialog-title">Web Risk Check Details</h3>
          <button @click="selectedLog = null" class="btn btn-sm btn-ghost"><svg-icon name="x" :size="18" /></button>
        </div>
        <div class="dialog-body" style="max-height: 70vh; overflow-y: auto">
          <!-- Summary -->
          <div class="grid grid-cols-4 gap-4 mb-4">
            <div class="rounded-lg p-3 text-center" style="background: var(--hover-bg)">
              <div class="text-lg font-bold" style="color: var(--text-primary)">{{ (selectedLog.total_sites || selectedLog.sites_checked || 0).toLocaleString() }}</div>
              <div class="text-xs" style="color: var(--text-secondary)">Sites Checked</div>
            </div>
            <div class="rounded-lg p-3 text-center" style="background: var(--hover-bg)">
              <div class="text-lg font-bold" :style="{ color: (selectedLog.threats_found || selectedLog.threats || 0) > 0 ? 'var(--color-error)' : 'var(--color-success)' }">{{ (selectedLog.threats_found || selectedLog.threats || 0) }}</div>
              <div class="text-xs" style="color: var(--text-secondary)">Threats Found</div>
            </div>
            <div class="rounded-lg p-3 text-center" style="background: var(--hover-bg)">
              <div class="text-lg font-bold" :style="{ color: (selectedLog.errors_count || selectedLog.errors || 0) > 0 ? 'var(--color-warning)' : 'var(--text-primary)' }">{{ (selectedLog.errors_count || selectedLog.errors || 0) }}</div>
              <div class="text-xs" style="color: var(--text-secondary)">API Errors</div>
            </div>
            <div class="rounded-lg p-3 text-center" style="background: var(--hover-bg)">
              <div class="text-sm font-medium" style="color: var(--text-primary)">{{ prettyTimestamp(selectedLog.created_at) }}</div>
              <div class="text-xs" style="color: var(--text-secondary)">Check Date</div>
            </div>
          </div>
          <!-- Threats -->
          <div v-if="getDetails(selectedLog).threats.length" class="mb-4">
            <h4 class="text-xs font-semibold mb-2" style="color: var(--color-error); text-transform: uppercase">Threats Detected</h4>
            <div v-for="(t, i) in getDetails(selectedLog).threats" :key="i" class="flex items-center justify-between p-3 mb-2 rounded-lg" style="background: var(--hover-bg)">
              <div>
                <a v-if="t.home_url" :href="t.home_url" target="_blank" class="text-sm font-medium" style="color: var(--color-primary)">{{ t.site_name }}</a>
                <span v-else class="text-sm font-medium" style="color: var(--text-primary)">{{ t.site_name }}</span>
              </div>
              <div class="flex gap-1">
                <span v-for="tt in (t.threat_types || [])" :key="tt" :class="'badge badge-' + threatColor(tt)">{{ tt.replace(/_/g, ' ').toLowerCase() }}</span>
              </div>
            </div>
          </div>
          <!-- All Clear -->
          <div v-else-if="(selectedLog.threats_found || selectedLog.threats || 0) === 0" class="text-center py-6 mb-4">
            <div class="text-2xl mb-2" style="color: var(--color-success)">&#10003;</div>
            <div class="text-sm font-semibold" style="color: var(--text-primary)">All Clear</div>
            <div class="text-xs" style="color: var(--text-secondary)">No threats were detected during this check.</div>
          </div>
          <!-- Errors -->
          <div v-if="getDetails(selectedLog).errors.length">
            <h4 class="text-xs font-semibold mb-2" style="color: var(--color-warning); text-transform: uppercase">API Errors</h4>
            <div v-for="(e, i) in getDetails(selectedLog).errors" :key="i" class="flex items-center gap-2 p-2 text-sm" style="border-bottom: 1px solid var(--border-color)">
              <svg-icon name="alertTriangle" :size="14" style="color: var(--color-warning); flex-shrink: 0" />
              <span class="font-medium">{{ e.name }}</span>
              <span style="color: var(--text-secondary)">{{ e.error }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  `,
});

// ─── View: KeysView ──────────────────────────────────────────────────────────
const KeysView = defineComponent({
  setup() {
    const { showNotify } = useNotify();
    const keys = ref([]);
    const loading = ref(true);
    const showKeyDialog = ref(false);
    const editingKey = ref(null);
    const keyForm = reactive({ title: '', key: '' });
    const keySaving = ref(false);

    function fetchKeys() {
      loading.value = true;
      api.get('/wp-json/captaincore/v1/keys')
        .then(r => { keys.value = r.data || []; })
        .finally(() => { loading.value = false; });
    }
    onMounted(fetchKeys);

    function newKey() {
      editingKey.value = null;
      keyForm.title = '';
      keyForm.key = '';
      showKeyDialog.value = true;
    }
    function editKey(k) {
      editingKey.value = k;
      keyForm.title = k.title || '';
      keyForm.key = k.key || '';
      showKeyDialog.value = true;
    }
    function saveKey() {
      keySaving.value = true;
      const payload = { title: keyForm.title, key: keyForm.key };
      const req = editingKey.value
        ? api.put('/wp-json/captaincore/v1/keys/' + (editingKey.value.key_id || editingKey.value.id), payload)
        : api.post('/wp-json/captaincore/v1/keys', payload);
      req
        .then(() => { showNotify(editingKey.value ? 'Key updated' : 'Key created', 'success'); showKeyDialog.value = false; fetchKeys(); })
        .catch(() => showNotify('Failed to save key', 'error'))
        .finally(() => { keySaving.value = false; });
    }
    function deleteKey(k) {
      if (!confirm('Delete key "' + k.title + '"?')) return;
      api.delete('/wp-json/captaincore/v1/keys/' + (k.key_id || k.id))
        .then(() => { showNotify('Key deleted', 'success'); fetchKeys(); })
        .catch(() => showNotify('Failed to delete key', 'error'));
    }
    function setAsPrimary(k) {
      api.put('/wp-json/captaincore/v1/keys/' + (k.key_id || k.id), { primary: true })
        .then(() => { showNotify('Key set as primary', 'success'); fetchKeys(); })
        .catch(() => showNotify('Failed to set primary', 'error'));
    }

    return { keys, loading, showKeyDialog, editingKey, keyForm, keySaving, newKey, editKey, saveKey, deleteKey, setAsPrimary };
  },
  template: `
    <div class="surface rounded-xl">
      <div class="flex items-center justify-between px-4 py-3" style="border-bottom: 1px solid var(--border-color)">
        <h2 class="text-sm font-semibold" style="color: var(--text-primary)">SSH Keys</h2>
        <button @click="newKey" class="btn btn-sm btn-primary"><svg-icon name="plus" :size="14" /> Add Key</button>
      </div>
      <div v-if="loading" class="flex justify-center py-16"><div class="animate-spin rounded-full h-8 w-8 border-b-2" style="border-color: var(--color-primary)"></div></div>
      <div v-else-if="!keys.length" class="p-8 text-center text-sm" style="color: var(--text-secondary)">No SSH keys.</div>
      <div v-else class="p-4 space-y-3">
        <div v-for="k in keys" :key="k.key_id || k.id" class="flex items-center justify-between p-4 rounded-lg" style="background: var(--hover-bg)">
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium" style="color: var(--text-primary)">{{ k.title }} <span v-if="k.primary" class="badge badge-success ml-1">Primary</span></div>
            <div v-if="k.fingerprint" class="text-xs font-mono mt-1" style="color: var(--text-secondary)">{{ k.fingerprint }}</div>
          </div>
          <div class="flex gap-2 flex-shrink-0">
            <button v-if="!k.primary" @click="setAsPrimary(k)" class="btn btn-sm btn-outline">Set Primary</button>
            <button @click="editKey(k)" class="btn btn-sm btn-ghost"><svg-icon name="pencil" :size="14" /></button>
            <button @click="deleteKey(k)" class="btn btn-sm btn-ghost" style="color: var(--color-error)"><svg-icon name="trash" :size="14" /></button>
          </div>
        </div>
      </div>
      <!-- Key Dialog -->
      <div v-if="showKeyDialog" class="dialog-overlay" @click.self="showKeyDialog = false">
        <div class="dialog-card" style="max-width: 560px;">
          <div class="dialog-card-header"><h3 class="text-sm font-semibold" style="color: var(--text-primary)">{{ editingKey ? 'Edit Key' : 'Add Key' }}</h3><button @click="showKeyDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="close" :size="16" /></button></div>
          <div class="dialog-card-body space-y-4">
            <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Title</label><input v-model="keyForm.title" class="input-field" /></div>
            <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Public Key</label><textarea v-model="keyForm.key" class="textarea-field" style="font-family: monospace; font-size: 0.75rem; min-height: 120px;"></textarea></div>
          </div>
          <div class="dialog-card-footer"><button @click="showKeyDialog = false" class="btn btn-ghost">Cancel</button><button @click="saveKey" :disabled="keySaving || !keyForm.title" class="btn btn-primary">{{ keySaving ? 'Saving...' : 'Save' }}</button></div>
        </div>
      </div>
    </div>
  `,
});

// ─── View: SubscriptionsView ─────────────────────────────────────────────────
const SubscriptionsView = defineComponent({
  components: { DataTable },
  setup() {
    const subs = ref([]);
    const loading = ref(true);
    const search = ref('');
    const headers = [
      { title: 'Name', value: 'name' },
      { title: 'Status', value: 'status', width: '100px' },
      { title: 'Total', value: 'total', width: '100px' },
    ];
    onMounted(() => {
      api.get('/wp-json/captaincore/v1/subscriptions')
        .then(r => { subs.value = r.data || []; })
        .finally(() => { loading.value = false; });
    });
    return { subs, loading, search, headers };
  },
  template: `
    <div class="surface rounded-xl">
      <div class="flex items-center justify-between px-4 py-3" style="border-bottom: 1px solid var(--border-color)">
        <h2 class="text-sm font-semibold" style="color: var(--text-primary)">Subscriptions</h2>
        <div class="search-wrapper"><svg-icon name="search" :size="16" class="search-icon" /><input v-model="search" type="text" placeholder="Search..." class="input-field" style="width: 220px" /></div>
      </div>
      <data-table :headers="headers" :items="subs" :search="search" :loading="loading" />
    </div>
  `,
});

// ─── View: BillingView ───────────────────────────────────────────────────────
const BillingView = defineComponent({
  components: { TabBar, DataTable },
  setup() {
    const router = useRouter();
    const { showNotify } = useNotify();
    const role = userRole;
    const loading = ref(true);
    const billing = ref({});
    const activeTab = ref('invoices');
    const savingAddress = ref(false);

    const tabs = computed(() => {
      const t = [
        { key: 'invoices', label: 'Invoices' },
        { key: 'overview', label: 'My Plan' },
        { key: 'payment', label: 'Payment Methods' },
        { key: 'address', label: 'Billing Address' },
      ];
      if (role.value === 'administrator' && billing.value.pending_ach && billing.value.pending_ach.length) {
        t.push({ key: 'ach', label: 'Pending ACH' });
      }
      return t;
    });

    const invoiceHeaders = [
      { title: 'Order #', value: 'order_id', width: '100px' },
      { title: 'Date', value: 'date' },
      { title: 'Status', value: 'status', width: '120px' },
      { title: 'Total', value: 'total', width: '100px' },
    ];
    const planHeaders = [
      { title: 'Account', value: 'name' },
      { title: 'Plan', value: 'plan.name' },
      { title: 'Renewal', value: 'next_renewal', width: '120px' },
      { title: 'Price', value: 'price', width: '100px' },
      { title: 'Status', value: 'status', width: '100px' },
    ];

    onMounted(() => {
      api.get('/wp-json/captaincore/v1/billing')
        .then(r => { billing.value = r.data || {}; })
        .catch(() => showNotify('Failed to load billing data', 'error'))
        .finally(() => { loading.value = false; });
    });

    function onInvoiceClick(event, { item }) {
      router.push('/billing/' + item.order_id);
    }

    function saveAddress() {
      savingAddress.value = true;
      api.put('/wp-json/captaincore/v1/billing/update', { address: billing.value.address })
        .then(() => showNotify('Billing address saved', 'success'))
        .catch(() => showNotify('Failed to save address', 'error'))
        .finally(() => { savingAddress.value = false; });
    }

    function setDefaultPayment(token) {
      api.put('/wp-json/captaincore/v1/billing/payment-methods/' + token + '/primary')
        .then(() => { showNotify('Default payment updated', 'success'); })
        .catch(() => showNotify('Failed to update', 'error'));
    }

    function deletePayment(token) {
      if (!confirm('Remove this payment method?')) return;
      api.delete('/wp-json/captaincore/v1/billing/payment-methods/' + token)
        .then(() => {
          billing.value.payment_methods = (billing.value.payment_methods || []).filter(p => p.token !== token);
          showNotify('Payment method removed', 'success');
        })
        .catch(() => showNotify('Failed to remove', 'error'));
    }

    // Add payment method
    const showAddPaymentDialog = ref(false);
    const addingPayment = ref(false);
    const stripeError = ref('');
    function addPaymentMethod(sourceId) {
      addingPayment.value = true;
      api.post('/wp-json/captaincore/v1/billing/payment-methods', { source_id: sourceId })
        .then(() => {
          showNotify('Payment method added', 'success');
          showAddPaymentDialog.value = false;
          api.get('/wp-json/captaincore/v1/billing').then(r => { billing.value = r.data || {}; });
        })
        .catch(() => showNotify('Failed to add payment method', 'error'))
        .finally(() => { addingPayment.value = false; });
    }
    function submitStripeCard() {
      if (!window.Stripe) { stripeError.value = 'Stripe not loaded'; return; }
      stripeError.value = '';
      addingPayment.value = true;
      const stripe = window.Stripe(CC.stripe_publishable_key || '');
      const elements = stripe.elements();
      const existing = document.querySelector('#stripe-card-element iframe');
      let card;
      if (!existing) { card = elements.create('card'); card.mount('#stripe-card-element'); addingPayment.value = false; return; }
      stripe.createSource(existing ? { type: 'card' } : card, { type: 'card' }).then(result => {
        if (result.error) { stripeError.value = result.error.message; addingPayment.value = false; return; }
        addPaymentMethod(result.source.id);
      });
    }

    // Bank verification
    const showVerifyBankDialog = ref(false);
    const verifyAmounts = reactive({ amount1: '', amount2: '' });
    const verifyBankToken = ref('');
    const verifyingBank = ref(false);
    function startBankVerify(ach) {
      verifyBankToken.value = ach.id || ach.token;
      verifyAmounts.amount1 = '';
      verifyAmounts.amount2 = '';
      showVerifyBankDialog.value = true;
    }
    function verifyBankAccount() {
      verifyingBank.value = true;
      api.post('/wp-json/captaincore/v1/billing/ach/verify', { token_id: verifyBankToken.value, amounts: [Number(verifyAmounts.amount1), Number(verifyAmounts.amount2)] })
        .then(() => { showNotify('Bank verified', 'success'); showVerifyBankDialog.value = false; api.get('/wp-json/captaincore/v1/billing').then(r => { billing.value = r.data || {}; }); })
        .catch(() => showNotify('Verification failed', 'error'))
        .finally(() => { verifyingBank.value = false; });
    }

    return { billing, loading, activeTab, tabs, invoiceHeaders, planHeaders, savingAddress, onInvoiceClick, saveAddress, setDefaultPayment, deletePayment, formatMoney, showAddPaymentDialog, addingPayment, addPaymentMethod, stripeError, submitStripeCard, showVerifyBankDialog, verifyAmounts, verifyBankToken, verifyingBank, startBankVerify, verifyBankAccount };
  },
  template: `
    <div class="surface rounded-xl">
      <div class="px-4 py-3" style="border-bottom: 1px solid var(--border-color)">
        <h2 class="text-sm font-semibold" style="color: var(--text-primary)">Billing</h2>
      </div>
      <div v-if="loading" class="flex justify-center py-16"><div class="animate-spin rounded-full h-8 w-8 border-b-2" style="border-color: var(--color-primary)"></div></div>
      <div v-else>
        <div class="px-4 pt-3"><tab-bar :tabs="tabs" v-model="activeTab" /></div>
        <div class="p-4">
          <!-- Invoices -->
          <div v-if="activeTab === 'invoices'">
            <data-table :headers="invoiceHeaders" :items="billing.invoices || []" :clickable="true" @click:row="onInvoiceClick">
              <template #item.order_id="{ value }"><span class="text-sm font-medium">#{{ value }}</span></template>
              <template #item.status="{ value }"><span :class="['badge', value === 'completed' || value === 'processing' ? 'badge-success' : value === 'pending' ? 'badge-warning' : 'badge-default']">{{ value }}</span></template>
              <template #item.total="{ value }"><span class="text-sm">{{ formatMoney(value) }}</span></template>
            </data-table>
          </div>
          <!-- My Plan -->
          <div v-if="activeTab === 'overview'">
            <data-table :headers="planHeaders" :items="billing.subscriptions || []">
              <template #item.price="{ value }"><span class="text-sm">{{ formatMoney(value) }}</span></template>
              <template #item.status="{ value }"><span :class="['badge', value === 'active' ? 'badge-success' : 'badge-default']">{{ value }}</span></template>
            </data-table>
          </div>
          <!-- Payment Methods -->
          <div v-if="activeTab === 'payment'">
            <div class="flex items-center justify-between mb-4">
              <span class="text-sm" style="color: var(--text-secondary)">{{ (billing.payment_methods || []).length }} payment methods</span>
              <button @click="showAddPaymentDialog = true" class="btn btn-sm btn-primary"><svg-icon name="plus" :size="14" /> Add Payment Method</button>
            </div>
            <div v-if="!billing.payment_methods || !billing.payment_methods.length" class="text-sm py-4" style="color: var(--text-secondary)">No payment methods on file.</div>
            <div v-else class="space-y-3">
              <div v-for="pm in billing.payment_methods" :key="pm.token" class="flex items-center justify-between p-4 rounded-lg" style="background: var(--hover-bg)">
                <div class="flex items-center gap-3">
                  <svg-icon name="billing" :size="20" style="color: var(--text-secondary)" />
                  <div>
                    <div class="text-sm font-medium" style="color: var(--text-primary)">
                      {{ pm.method ? (pm.method.brand || pm.method.bank_name || 'Card') : 'Card' }}
                      <span v-if="pm.method && pm.method.last4"> ending in {{ pm.method.last4 }}</span>
                    </div>
                    <div class="text-xs" style="color: var(--text-secondary)">
                      <span v-if="pm.expires">Expires {{ pm.expires }}</span>
                      <span v-if="pm.is_default" class="badge badge-success ml-2">Default</span>
                    </div>
                  </div>
                </div>
                <div class="flex gap-2">
                  <button v-if="!pm.is_default" @click="setDefaultPayment(pm.token)" class="btn btn-sm btn-outline">Make Default</button>
                  <button @click="deletePayment(pm.token)" class="btn btn-sm btn-ghost" style="color: var(--color-error)"><svg-icon name="trash" :size="14" /></button>
                </div>
              </div>
            </div>
            <!-- Add Payment Method Dialog -->
            <div v-if="showAddPaymentDialog" class="dialog-overlay" @click.self="showAddPaymentDialog = false">
              <div class="dialog-card" style="max-width: 480px;">
                <div class="dialog-card-header"><h3 class="text-sm font-semibold" style="color: var(--text-primary)">Add Payment Method</h3><button @click="showAddPaymentDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="close" :size="16" /></button></div>
                <div class="dialog-card-body">
                  <p class="text-sm mb-4" style="color: var(--text-secondary)">Enter your card details below. Payment is processed securely via Stripe.</p>
                  <div id="stripe-card-element" class="p-3 rounded-lg" style="background: var(--hover-bg); border: 1px solid var(--border-color); min-height: 40px;"></div>
                  <p v-if="stripeError" class="text-xs mt-2" style="color: var(--color-error)">{{ stripeError }}</p>
                </div>
                <div class="dialog-card-footer"><button @click="showAddPaymentDialog = false" class="btn btn-ghost">Cancel</button><button @click="submitStripeCard" :disabled="addingPayment" class="btn btn-primary">{{ addingPayment ? 'Adding...' : 'Add Card' }}</button></div>
              </div>
            </div>
          </div>
          <!-- Billing Address -->
          <div v-if="activeTab === 'address'">
            <div class="space-y-4" style="max-width: 500px;">
              <div v-for="field in ['first_name','last_name','company','address_1','address_2','city','state','postcode','country','phone','email']" :key="field">
                <label class="block text-xs mb-1 font-medium capitalize" style="color: var(--text-secondary)">{{ field.replace(/_/g, ' ') }}</label>
                <input v-model="billing.address[field]" class="input-field" />
              </div>
              <button @click="saveAddress" :disabled="savingAddress" class="btn btn-primary">{{ savingAddress ? 'Saving...' : 'Save Address' }}</button>
            </div>
          </div>
          <!-- Pending ACH -->
          <div v-if="activeTab === 'ach'">
            <div v-if="!billing.pending_ach || !billing.pending_ach.length" class="text-sm py-4" style="color: var(--text-secondary)">No pending ACH verifications.</div>
            <div v-else class="space-y-3">
              <div v-for="ach in billing.pending_ach" :key="ach.id" class="flex items-center justify-between p-4 rounded-lg" style="background: var(--hover-bg)">
                <div>
                  <div class="text-sm font-medium" style="color: var(--text-primary)">{{ ach.bank_name || 'Bank Account' }} ending in {{ ach.last4 }}</div>
                  <div class="text-xs mt-1" style="color: var(--text-secondary)">Status: {{ ach.status }}</div>
                </div>
                <button @click="startBankVerify(ach)" class="btn btn-sm btn-outline">Verify</button>
              </div>
            </div>
            <!-- Bank Verification Dialog -->
            <div v-if="showVerifyBankDialog" class="dialog-overlay" @click.self="showVerifyBankDialog = false">
              <div class="dialog-card" style="max-width: 420px;">
                <div class="dialog-card-header"><h3 class="text-sm font-semibold" style="color: var(--text-primary)">Verify Bank Account</h3><button @click="showVerifyBankDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="close" :size="16" /></button></div>
                <div class="dialog-card-body space-y-4">
                  <p class="text-sm" style="color: var(--text-secondary)">Enter the two microdeposit amounts (in cents) sent to your bank account.</p>
                  <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Amount 1</label><input v-model="verifyAmounts.amount1" type="number" class="input-field" placeholder="32" /></div>
                    <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Amount 2</label><input v-model="verifyAmounts.amount2" type="number" class="input-field" placeholder="45" /></div>
                  </div>
                </div>
                <div class="dialog-card-footer"><button @click="showVerifyBankDialog = false" class="btn btn-ghost">Cancel</button><button @click="verifyBankAccount" :disabled="verifyingBank || !verifyAmounts.amount1 || !verifyAmounts.amount2" class="btn btn-primary">{{ verifyingBank ? 'Verifying...' : 'Verify' }}</button></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  `,
});

// ─── View: BillingDetailView ─────────────────────────────────────────────────
const BillingDetailView = defineComponent({
  setup() {
    const route = useRoute();
    const router = useRouter();
    const { showNotify } = useNotify();
    const order = ref(null);
    const loading = ref(true);
    const paying = ref(false);
    const downloading = ref(false);
    const billingData = ref({});

    onMounted(() => {
      api.get('/wp-json/captaincore/v1/billing')
        .then(r => {
          billingData.value = r.data || {};
          const invoices = r.data.invoices || [];
          order.value = invoices.find(i => i.order_id == route.params.id) || null;
        })
        .catch(() => showNotify('Failed to load order', 'error'))
        .finally(() => { loading.value = false; });
    });

    function goBack() { router.push('/billing'); }

    function payInvoice() {
      if (!order.value) return;
      paying.value = true;
      const pm = (billingData.value.payment_methods || []).find(p => p.is_default);
      const payload = pm ? { value: order.value.order_id, payment_id: pm.token } : { value: order.value.order_id };
      api.post('/wp-json/captaincore/v1/billing/pay-invoice', payload)
        .then(() => { showNotify('Payment submitted', 'success'); order.value.status = 'processing'; })
        .catch(() => showNotify('Payment failed', 'error'))
        .finally(() => { paying.value = false; });
    }

    function downloadPdf() {
      downloading.value = true;
      api.get('/wp-json/captaincore/v1/invoices/' + route.params.id + '/pdf', { responseType: 'blob' })
        .then(r => {
          const url = window.URL.createObjectURL(new Blob([r.data]));
          const a = document.createElement('a');
          a.href = url; a.download = 'invoice-' + route.params.id + '.pdf';
          document.body.appendChild(a); a.click(); a.remove();
          window.URL.revokeObjectURL(url);
        })
        .catch(() => showNotify('Failed to download PDF', 'error'))
        .finally(() => { downloading.value = false; });
    }

    return { order, loading, goBack, formatMoney, paying, payInvoice, downloading, downloadPdf };
  },
  template: `
    <div v-if="loading" class="flex justify-center py-16"><div class="animate-spin rounded-full h-8 w-8 border-b-2" style="border-color: var(--color-primary)"></div></div>
    <div v-else-if="!order" class="surface rounded-xl p-8 text-center"><p style="color: var(--text-secondary)">Order not found.</p></div>
    <div v-else class="surface rounded-xl">
      <div class="detail-header">
        <button class="back-btn" @click="goBack"><svg-icon name="arrowLeft" :size="18" /></button>
        <h2 class="text-sm font-semibold flex-1" style="color: var(--text-primary)">Order #{{ order.order_id }}</h2>
        <div class="flex items-center gap-2">
          <button @click="downloadPdf" :disabled="downloading" class="btn btn-sm btn-outline">{{ downloading ? 'Downloading...' : 'PDF' }}</button>
          <button v-if="order.status === 'pending' || order.status === 'on-hold'" @click="payInvoice" :disabled="paying" class="btn btn-sm btn-primary">{{ paying ? 'Paying...' : 'Pay Now' }}</button>
          <span :class="['badge', order.status === 'completed' || order.status === 'processing' ? 'badge-success' : order.status === 'pending' ? 'badge-warning' : 'badge-default']">{{ order.status }}</span>
        </div>
      </div>
      <div class="p-4">
        <div class="info-grid mb-6">
          <span class="info-label">Date</span><span class="info-value">{{ order.date }}</span>
          <span class="info-label">Total</span><span class="info-value">{{ formatMoney(order.total) }}</span>
          <span class="info-label">Status</span><span class="info-value">{{ order.status }}</span>
        </div>
        <div v-if="order.line_items && order.line_items.length">
          <h3 class="text-xs font-semibold uppercase tracking-wider mb-3" style="color: var(--text-secondary)">Line Items</h3>
          <table class="data-table">
            <thead><tr><th>Item</th><th style="width:80px">Qty</th><th style="width:100px">Total</th></tr></thead>
            <tbody><tr v-for="item in order.line_items" :key="item.id || item.name"><td class="text-sm">{{ item.name }}</td><td class="text-sm">{{ item.quantity }}</td><td class="text-sm">{{ formatMoney(item.total) }}</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  `,
});

// ─── View: ConfigurationsView ────────────────────────────────────────────────
const ConfigurationsView = defineComponent({
  components: { TabBar },
  setup() {
    const { showNotify } = useNotify();
    const role = userRole;
    const loading = ref(false);
    const saving = ref(false);
    const activeTab = ref('branding');
    const config = reactive({
      name: CC.configurations.name || '',
      logo: CC.configurations.logo || '',
      logo_width: CC.configurations.logo_width || '32',
      logo_only: CC.configurations.logo_only || false,
      dns_introduction: CC.configurations.dns_introduction || '',
      dns_nameservers: CC.configurations.dns_nameservers || '',
      theme: CC.configurations.theme || {},
    });
    const colors = reactive({
      primary: CC.colors.primary || '#1976D2',
      accent: CC.colors.accent || '#82B1FF',
      success: CC.colors.success || '#4CAF50',
      warning: CC.colors.warning || '#FB8C00',
      error: CC.colors.error || '#FF5252',
      info: CC.colors.info || '#2196F3',
    });

    const providers = ref([]);
    const providersLoading = ref(false);

    // Provider CRUD
    const showProviderDialog = ref(false);
    const editingProvider = ref(null);
    const providerForm = reactive({ name: '', provider: '', credentials: [{ name: '', value: '' }] });
    const providerSaving = ref(false);

    // Connection wizard
    const showConnectDialog = ref(false);
    const connectProviderId = ref(null);
    const connectStep = ref(1);
    const remoteSites = ref([]);
    const remoteSitesLoading = ref(false);
    const selectedRemoteSites = ref([]);
    const importAccountId = ref('');
    const importing = ref(false);

    // Billing config
    const billingConfig = reactive({ stripe_publishable_key: '', currency: 'usd' });
    const billingConfigSaving = ref(false);
    function saveBillingConfig() {
      billingConfigSaving.value = true;
      api.put('/wp-json/captaincore/v1/configurations/billing', billingConfig)
        .then(() => showNotify('Billing configuration saved', 'success'))
        .catch(() => showNotify('Failed to save', 'error'))
        .finally(() => { billingConfigSaving.value = false; });
    }

    const tabs = computed(() => {
      const t = [{ key: 'branding', label: 'Branding' }];
      if (role.value === 'administrator') t.push({ key: 'providers', label: 'Providers' });
      if (role.value === 'administrator') t.push({ key: 'billing', label: 'Billing' });
      return t;
    });

    function fetchProviders() {
      if (providers.value.length) return;
      providersLoading.value = true;
      api.get('/wp-json/captaincore/v1/providers')
        .then(r => { providers.value = r.data || []; })
        .catch(() => {})
        .finally(() => { providersLoading.value = false; });
    }

    watch(activeTab, t => { if (t === 'providers') fetchProviders(); });

    function saveConfig() {
      saving.value = true;
      const payload = { ...config, colors };
      api.put('/wp-json/captaincore/v1/configurations/global', payload)
        .then(() => showNotify('Configurations saved', 'success'))
        .catch(() => showNotify('Failed to save', 'error'))
        .finally(() => { saving.value = false; });
    }

    function newProvider() {
      editingProvider.value = null;
      providerForm.name = '';
      providerForm.provider = '';
      providerForm.credentials = [{ name: '', value: '' }];
      showProviderDialog.value = true;
    }
    function editProvider(p) {
      editingProvider.value = p;
      providerForm.name = p.name || '';
      providerForm.provider = p.provider || '';
      providerForm.credentials = (p.credentials && p.credentials.length) ? p.credentials.map(c => ({ ...c })) : [{ name: '', value: '' }];
      showProviderDialog.value = true;
    }
    function saveProvider() {
      providerSaving.value = true;
      const payload = { provider: { name: providerForm.name, provider: providerForm.provider, credentials: providerForm.credentials.filter(c => c.name && c.value) } };
      const req = editingProvider.value
        ? api.put('/wp-json/captaincore/v1/providers/' + (editingProvider.value.provider_id || editingProvider.value.id), payload)
        : api.post('/wp-json/captaincore/v1/providers', payload);
      req
        .then(() => { showNotify('Provider saved', 'success'); showProviderDialog.value = false; providers.value = []; fetchProviders(); })
        .catch(() => showNotify('Failed to save provider', 'error'))
        .finally(() => { providerSaving.value = false; });
    }
    function deleteProvider(p) {
      if (!confirm('Delete provider "' + (p.name || p.provider) + '"?')) return;
      api.delete('/wp-json/captaincore/v1/providers/' + (p.provider_id || p.id))
        .then(() => { showNotify('Provider deleted', 'success'); providers.value = []; fetchProviders(); })
        .catch(() => showNotify('Failed to delete provider', 'error'));
    }
    function addCredentialField() { providerForm.credentials.push({ name: '', value: '' }); }
    function removeCredentialField(i) { providerForm.credentials.splice(i, 1); }
    function startConnect(p) {
      connectProviderId.value = p.provider_id || p.id;
      connectStep.value = 1;
      remoteSites.value = [];
      selectedRemoteSites.value = [];
      showConnectDialog.value = true;
      fetchRemoteSites(p);
    }
    function fetchRemoteSites(p) {
      remoteSitesLoading.value = true;
      api.get('/wp-json/captaincore/v1/providers/' + (p.provider_id || p.id) + '/remote-sites')
        .then(r => { remoteSites.value = r.data || []; connectStep.value = 2; })
        .catch(() => showNotify('Failed to fetch remote sites', 'error'))
        .finally(() => { remoteSitesLoading.value = false; });
    }
    function toggleRemoteSite(site) {
      const idx = selectedRemoteSites.value.findIndex(s => s.id === site.id);
      if (idx > -1) selectedRemoteSites.value.splice(idx, 1);
      else selectedRemoteSites.value.push(site);
    }
    function importSites() {
      importing.value = true;
      api.post('/wp-json/captaincore/v1/providers/' + connectProviderId.value + '/import', { sites: selectedRemoteSites.value, account_id: importAccountId.value })
        .then(() => { showNotify('Import started', 'success'); showConnectDialog.value = false; refreshSites(); })
        .catch(() => showNotify('Import failed', 'error'))
        .finally(() => { importing.value = false; });
    }

    return { config, colors, loading, saving, activeTab, tabs, providers, providersLoading, saveConfig, showProviderDialog, editingProvider, providerForm, providerSaving, newProvider, editProvider, saveProvider, deleteProvider, addCredentialField, removeCredentialField, showConnectDialog, connectStep, remoteSites, remoteSitesLoading, selectedRemoteSites, importAccountId, importing, startConnect, toggleRemoteSite, importSites, billingConfig, billingConfigSaving, saveBillingConfig };
  },
  template: `
    <div class="surface rounded-xl">
      <div class="px-4 py-3" style="border-bottom: 1px solid var(--border-color)">
        <h2 class="text-sm font-semibold" style="color: var(--text-primary)">Configurations</h2>
      </div>
      <div class="px-4 pt-3"><tab-bar :tabs="tabs" v-model="activeTab" /></div>
      <div class="p-4">
        <!-- Branding -->
        <div v-if="activeTab === 'branding'" class="space-y-4" style="max-width: 600px;">
          <div>
            <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Name</label>
            <input v-model="config.name" class="input-field" />
          </div>
          <div>
            <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Logo URL</label>
            <input v-model="config.logo" class="input-field" placeholder="https://..." />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Logo Width (px)</label>
              <input v-model="config.logo_width" type="number" class="input-field" />
            </div>
            <div class="flex items-center gap-2 pt-4">
              <button :class="['toggle', config.logo_only && 'on']" @click="config.logo_only = !config.logo_only"></button>
              <span class="text-sm" style="color: var(--text-primary)">Logo only (hide name)</span>
            </div>
          </div>
          <h3 class="text-xs font-semibold uppercase tracking-wider mt-6 mb-3" style="color: var(--text-secondary)">Theme Colors</h3>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            <div v-for="key in ['primary','accent','success','warning','error','info']" :key="key">
              <label class="block text-xs mb-1 font-medium capitalize" style="color: var(--text-secondary)">{{ key }}</label>
              <div class="flex items-center gap-2">
                <input type="color" v-model="colors[key]" class="w-8 h-8 rounded border-0 cursor-pointer p-0" />
                <input v-model="colors[key]" class="input-field flex-1" style="font-family: monospace; font-size: 0.8rem;" />
              </div>
            </div>
          </div>
          <button @click="saveConfig" :disabled="saving" class="btn btn-primary mt-4">{{ saving ? 'Saving...' : 'Save Configurations' }}</button>
        </div>
        <!-- Providers -->
        <div v-if="activeTab === 'providers'">
          <div class="flex items-center justify-between mb-4">
            <span class="text-sm" style="color: var(--text-secondary)">{{ providers.length }} providers</span>
            <button @click="newProvider()" class="btn btn-sm btn-primary"><svg-icon name="plus" :size="14" /> Add Provider</button>
          </div>
          <div v-if="providersLoading" class="flex justify-center py-8"><div class="animate-spin rounded-full h-6 w-6 border-b-2" style="border-color: var(--color-primary)"></div></div>
          <div v-else-if="!providers.length" class="text-sm py-4" style="color: var(--text-secondary)">No providers configured.</div>
          <div v-else class="space-y-3">
            <div v-for="p in providers" :key="p.provider_id || p.id" class="flex items-center justify-between p-4 rounded-lg" style="background: var(--hover-bg)">
              <div>
                <div class="text-sm font-medium" style="color: var(--text-primary)">{{ p.name || p.provider }}</div>
                <div class="text-xs" style="color: var(--text-secondary)">{{ p.provider || '' }}</div>
              </div>
              <div class="flex items-center gap-2">
                <span :class="['badge', p.status === 'connected' || p.connected ? 'badge-success' : 'badge-default']">{{ p.status || (p.connected ? 'Connected' : 'Not connected') }}</span>
                <button @click="startConnect(p)" class="btn btn-sm btn-outline"><svg-icon name="download" :size="14" /> Import</button>
                <button @click="editProvider(p)" class="btn btn-sm btn-ghost"><svg-icon name="pencil" :size="14" /></button>
                <button @click="deleteProvider(p)" class="btn btn-sm btn-ghost" style="color: var(--color-error)"><svg-icon name="trash" :size="14" /></button>
              </div>
            </div>
          </div>
          <!-- Provider Dialog -->
          <div v-if="showProviderDialog" class="dialog-overlay" @click.self="showProviderDialog = false">
            <div class="dialog-card" style="max-width: 560px;">
              <div class="dialog-card-header"><h3 class="text-sm font-semibold" style="color: var(--text-primary)">{{ editingProvider ? 'Edit Provider' : 'Add Provider' }}</h3><button @click="showProviderDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="close" :size="16" /></button></div>
              <div class="dialog-card-body space-y-4">
                <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Name</label><input v-model="providerForm.name" class="input-field" /></div>
                <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Provider Type</label>
                  <select v-model="providerForm.provider" class="select-field"><option value="">Select...</option><option value="kinsta">Kinsta</option><option value="gridpane">GridPane</option><option value="rocketdotnet">Rocket.net</option><option value="wpengine">WP Engine</option><option value="cloudways">Cloudways</option><option value="other">Other</option></select>
                </div>
                <div>
                  <div class="flex items-center justify-between mb-2"><label class="text-xs font-medium" style="color: var(--text-secondary)">Credentials</label><button @click="addCredentialField" class="btn btn-sm btn-ghost"><svg-icon name="plus" :size="14" /></button></div>
                  <div v-for="(c, i) in providerForm.credentials" :key="i" class="flex gap-2 mb-2">
                    <input v-model="c.name" class="input-field" placeholder="Key" style="width: 40%;" />
                    <input v-model="c.value" class="input-field flex-1" placeholder="Value" />
                    <button @click="removeCredentialField(i)" class="btn btn-sm btn-ghost" style="color: var(--color-error)"><svg-icon name="trash" :size="14" /></button>
                  </div>
                </div>
              </div>
              <div class="dialog-card-footer"><button @click="showProviderDialog = false" class="btn btn-ghost">Cancel</button><button @click="saveProvider" :disabled="providerSaving" class="btn btn-primary">{{ providerSaving ? 'Saving...' : 'Save' }}</button></div>
            </div>
          </div>
          <!-- Connection Wizard Dialog -->
          <div v-if="showConnectDialog" class="dialog-overlay" @click.self="showConnectDialog = false">
            <div class="dialog-card" style="max-width: 600px;">
              <div class="dialog-card-header"><h3 class="text-sm font-semibold" style="color: var(--text-primary)">Import Sites from Provider</h3><button @click="showConnectDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="close" :size="16" /></button></div>
              <div class="dialog-card-body">
                <div v-if="remoteSitesLoading" class="flex justify-center py-8"><div class="animate-spin rounded-full h-6 w-6 border-b-2" style="border-color: var(--color-primary)"></div></div>
                <div v-else-if="connectStep === 2">
                  <p class="text-sm mb-3" style="color: var(--text-secondary)">Select sites to import ({{ selectedRemoteSites.length }} selected)</p>
                  <div style="max-height: 300px; overflow-y: auto;" class="space-y-1 mb-4">
                    <div v-for="rs in remoteSites" :key="rs.id" @click="toggleRemoteSite(rs)" class="flex items-center gap-3 p-2 rounded cursor-pointer" :style="selectedRemoteSites.find(s => s.id === rs.id) ? 'background: color-mix(in srgb, var(--color-primary) 10%, transparent)' : 'background: var(--hover-bg)'">
                      <input type="checkbox" :checked="!!selectedRemoteSites.find(s => s.id === rs.id)" />
                      <div class="text-sm" style="color: var(--text-primary)">{{ rs.name || rs.domain }}</div>
                    </div>
                  </div>
                  <div v-if="!remoteSites.length" class="text-sm py-4 text-center" style="color: var(--text-secondary)">No remote sites found.</div>
                </div>
              </div>
              <div class="dialog-card-footer">
                <button @click="showConnectDialog = false" class="btn btn-ghost">Cancel</button>
                <button v-if="connectStep === 2 && selectedRemoteSites.length" @click="importSites" :disabled="importing" class="btn btn-primary">{{ importing ? 'Importing...' : 'Import ' + selectedRemoteSites.length + ' Sites' }}</button>
              </div>
            </div>
          </div>
        </div>
        <!-- Billing Config -->
        <div v-if="activeTab === 'billing'" class="space-y-4" style="max-width: 500px;">
          <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Stripe Publishable Key</label><input v-model="billingConfig.stripe_publishable_key" class="input-field" placeholder="pk_..." /></div>
          <p class="text-xs" style="color: var(--text-tertiary)">Stripe secret key must be configured server-side (wp-config.php or environment variable).</p>
          <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Currency</label>
            <select v-model="billingConfig.currency" class="select-field"><option value="usd">USD</option><option value="eur">EUR</option><option value="gbp">GBP</option><option value="cad">CAD</option><option value="aud">AUD</option></select>
          </div>
          <button @click="saveBillingConfig" :disabled="billingConfigSaving" class="btn btn-primary">{{ billingConfigSaving ? 'Saving...' : 'Save Billing Config' }}</button>
        </div>
      </div>
    </div>
  `,
});

// ─── View: HandbookView ──────────────────────────────────────────────────────
const HandbookView = defineComponent({
  setup() {
    const { showNotify } = useNotify();
    const role = userRole;
    const processes = ref([]);
    const loading = ref(true);
    const selectedProcess = ref(null);
    const detailLoading = ref(false);

    const showProcessDialog = ref(false);
    const editingProcess = ref(null);
    const processForm = reactive({ name: '', time_estimate: '', repeat_interval: 'as-needed', repeat_quantity: '', roles: '', description: '' });
    const processSaving = ref(false);

    function fetchProcesses() {
      loading.value = true;
      api.get('/wp-json/captaincore/v1/processes')
        .then(r => { processes.value = r.data || []; })
        .catch(() => showNotify('Failed to load processes', 'error'))
        .finally(() => { loading.value = false; });
    }
    onMounted(fetchProcesses);

    function viewProcess(p) {
      detailLoading.value = true;
      selectedProcess.value = { ...p };
      api.get('/wp-json/captaincore/v1/processes/' + (p.process_id || p.id))
        .then(r => { selectedProcess.value = r.data || p; })
        .catch(() => {})
        .finally(() => { detailLoading.value = false; });
    }
    function closeDetail() { selectedProcess.value = null; }
    function newProcess() {
      editingProcess.value = null;
      processForm.name = '';
      processForm.time_estimate = '';
      processForm.repeat_interval = 'as-needed';
      processForm.repeat_quantity = '';
      processForm.roles = '';
      processForm.description = '';
      showProcessDialog.value = true;
    }
    function editProcess() {
      if (!selectedProcess.value) return;
      editingProcess.value = selectedProcess.value;
      processForm.name = selectedProcess.value.name || '';
      processForm.time_estimate = selectedProcess.value.time_estimate || '';
      processForm.repeat_interval = selectedProcess.value.repeat_interval || 'as-needed';
      processForm.repeat_quantity = selectedProcess.value.repeat_quantity || '';
      processForm.roles = selectedProcess.value.roles || '';
      processForm.description = selectedProcess.value.description || '';
      showProcessDialog.value = true;
    }
    function saveProcess() {
      processSaving.value = true;
      const payload = { ...processForm };
      const req = editingProcess.value
        ? api.put('/wp-json/captaincore/v1/processes/' + (editingProcess.value.process_id || editingProcess.value.id), payload)
        : api.post('/wp-json/captaincore/v1/processes', payload);
      req
        .then(() => { showNotify(editingProcess.value ? 'Process updated' : 'Process created', 'success'); showProcessDialog.value = false; selectedProcess.value = null; fetchProcesses(); })
        .catch(() => showNotify('Failed to save process', 'error'))
        .finally(() => { processSaving.value = false; });
    }
    function deleteProcess() {
      if (!selectedProcess.value) return;
      if (!confirm('Delete process "' + selectedProcess.value.name + '"?')) return;
      api.delete('/wp-json/captaincore/v1/processes/' + (selectedProcess.value.process_id || selectedProcess.value.id))
        .then(() => { showNotify('Process deleted', 'success'); selectedProcess.value = null; fetchProcesses(); })
        .catch(() => showNotify('Failed to delete process', 'error'));
    }

    return { processes, loading, selectedProcess, detailLoading, viewProcess, closeDetail, role, showProcessDialog, editingProcess, processForm, processSaving, newProcess, editProcess, saveProcess, deleteProcess, sanitizeHtml };
  },
  template: `
    <div class="surface rounded-xl">
      <div class="flex items-center justify-between px-4 py-3" style="border-bottom: 1px solid var(--border-color)">
        <h2 class="text-sm font-semibold" style="color: var(--text-primary)">Handbook</h2>
        <button v-if="role === 'administrator' && !selectedProcess" @click="newProcess" class="btn btn-sm btn-primary"><svg-icon name="plus" :size="14" /> New</button>
      </div>
      <div v-if="loading" class="flex justify-center py-16"><div class="animate-spin rounded-full h-8 w-8 border-b-2" style="border-color: var(--color-primary)"></div></div>
      <div v-else-if="selectedProcess" class="p-4">
        <div class="flex items-center gap-3 mb-4">
          <button @click="closeDetail" class="btn btn-sm btn-ghost"><svg-icon name="arrowLeft" :size="16" /> Back</button>
          <h3 class="text-sm font-semibold flex-1" style="color: var(--text-primary)">{{ selectedProcess.name }}</h3>
          <div v-if="role === 'administrator'" class="flex gap-2">
            <button @click="editProcess" class="btn btn-sm btn-ghost"><svg-icon name="pencil" :size="14" /></button>
            <button @click="deleteProcess" class="btn btn-sm btn-ghost" style="color: var(--color-error)"><svg-icon name="trash" :size="14" /></button>
          </div>
        </div>
        <div v-if="detailLoading" class="flex justify-center py-8"><div class="animate-spin rounded-full h-6 w-6 border-b-2" style="border-color: var(--color-primary)"></div></div>
        <div v-else>
          <div class="flex items-center gap-2 mb-4 flex-wrap">
            <span v-if="selectedProcess.roles" class="badge badge-info">{{ selectedProcess.roles }}</span>
            <span v-if="selectedProcess.time_estimate" class="badge badge-default">{{ selectedProcess.time_estimate }}</span>
            <span v-if="selectedProcess.repeat_interval" class="badge badge-default">{{ selectedProcess.repeat_quantity || '' }} {{ selectedProcess.repeat_interval }}</span>
          </div>
          <div v-if="selectedProcess.description" class="text-sm prose" style="color: var(--text-primary)" v-html="sanitizeHtml(selectedProcess.description)"></div>
          <div v-else class="text-sm" style="color: var(--text-secondary)">No description available.</div>
        </div>
      </div>
      <div v-else-if="!processes.length" class="p-8 text-center text-sm" style="color: var(--text-secondary)">No processes found.</div>
      <div v-else class="p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="p in processes" :key="p.process_id || p.id" @click="viewProcess(p)" class="rounded-lg p-4 cursor-pointer transition-colors" style="background: var(--hover-bg)" @mouseenter="$event.target.style.background='var(--active-bg)'" @mouseleave="$event.target.style.background='var(--hover-bg)'">
          <div class="text-sm font-medium mb-2" style="color: var(--text-primary)">{{ p.name }}</div>
          <div class="flex items-center gap-2 flex-wrap">
            <span v-if="p.roles" class="badge badge-info" style="font-size: 0.6875rem;">{{ p.roles }}</span>
            <span v-if="p.time_estimate" class="badge badge-default" style="font-size: 0.6875rem;">{{ p.time_estimate }}</span>
          </div>
        </div>
      </div>
      <!-- Process Dialog -->
      <div v-if="showProcessDialog" class="dialog-overlay" @click.self="showProcessDialog = false">
        <div class="dialog-card" style="max-width: 600px;">
          <div class="dialog-card-header"><h3 class="text-sm font-semibold" style="color: var(--text-primary)">{{ editingProcess ? 'Edit Process' : 'New Process' }}</h3><button @click="showProcessDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="close" :size="16" /></button></div>
          <div class="dialog-card-body space-y-4">
            <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Name</label><input v-model="processForm.name" class="input-field" /></div>
            <div class="grid grid-cols-2 gap-4">
              <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Time Estimate</label><input v-model="processForm.time_estimate" class="input-field" placeholder="e.g. 30 min" /></div>
              <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Roles</label><input v-model="processForm.roles" class="input-field" placeholder="e.g. administrator" /></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Repeat Interval</label>
                <select v-model="processForm.repeat_interval" class="select-field"><option value="as-needed">As Needed</option><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option><option value="yearly">Yearly</option></select>
              </div>
              <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Repeat Quantity</label><input v-model="processForm.repeat_quantity" class="input-field" /></div>
            </div>
            <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Description (HTML)</label><textarea v-model="processForm.description" class="textarea-field" style="min-height: 160px;"></textarea></div>
          </div>
          <div class="dialog-card-footer"><button @click="showProcessDialog = false" class="btn btn-ghost">Cancel</button><button @click="saveProcess" :disabled="processSaving || !processForm.name" class="btn btn-primary">{{ processSaving ? 'Saving...' : 'Save' }}</button></div>
        </div>
      </div>
    </div>
  `,
});

// ─── View: SiteDefaultsView ──────────────────────────────────────────────────
const SiteDefaultsView = defineComponent({
  setup() {
    const { showNotify } = useNotify();
    const loading = ref(true);
    const saving = ref(false);
    const defaults = ref({ email: '', timezone: '', recipes: [], users: [] });
    const allRecipes = ref([]);
    const timezones = ref([
      'UTC', 'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles',
      'America/Anchorage', 'Pacific/Honolulu', 'Europe/London', 'Europe/Berlin', 'Europe/Paris',
      'Asia/Tokyo', 'Asia/Shanghai', 'Asia/Kolkata', 'Australia/Sydney',
    ]);

    onMounted(() => {
      Promise.all([
        api.get('/wp-json/captaincore/v1/defaults'),
        api.get('/wp-json/captaincore/v1/recipes'),
      ]).then(([defR, recR]) => {
        if (defR.data) {
          defaults.value = Array.isArray(defR.data) && defR.data.length === 0 ? { email: '', timezone: '', recipes: [], users: [] } : defR.data;
        }
        allRecipes.value = recR.data || [];
      }).catch(() => showNotify('Failed to load defaults', 'error'))
        .finally(() => { loading.value = false; });
    });

    function addUser() {
      if (!defaults.value.users) defaults.value.users = [];
      defaults.value.users.push({ username: '', email: '', first_name: '', last_name: '', role: 'editor' });
    }
    function removeUser(i) { defaults.value.users.splice(i, 1); }

    function saveDefaults() {
      saving.value = true;
      api.put('/wp-json/captaincore/v1/defaults/global', defaults.value)
        .then(() => showNotify('Defaults saved', 'success'))
        .catch(() => showNotify('Failed to save', 'error'))
        .finally(() => { saving.value = false; });
    }

    return { defaults, loading, saving, allRecipes, timezones, addUser, removeUser, saveDefaults };
  },
  template: `
    <div class="surface rounded-xl">
      <div class="px-4 py-3" style="border-bottom: 1px solid var(--border-color)">
        <h2 class="text-sm font-semibold" style="color: var(--text-primary)">Site Defaults</h2>
      </div>
      <div v-if="loading" class="flex justify-center py-16"><div class="animate-spin rounded-full h-8 w-8 border-b-2" style="border-color: var(--color-primary)"></div></div>
      <div v-else class="p-4 space-y-6" style="max-width: 700px;">
        <div>
          <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Default Email</label>
          <input v-model="defaults.email" type="email" class="input-field" placeholder="admin@example.com" />
        </div>
        <div>
          <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Default Timezone</label>
          <select v-model="defaults.timezone" class="select-field">
            <option value="">Select timezone...</option>
            <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Default Recipes</label>
          <select v-model="defaults.recipes" multiple class="select-field" style="min-height: 100px;">
            <option v-for="r in allRecipes" :key="r.recipe_id || r.id" :value="r.recipe_id || r.id">{{ r.title }}</option>
          </select>
          <div class="text-xs mt-1" style="color: var(--text-secondary)">Hold Ctrl/Cmd to select multiple</div>
        </div>
        <div>
          <div class="flex items-center justify-between mb-3">
            <label class="text-xs font-medium" style="color: var(--text-secondary)">Default Users</label>
            <button @click="addUser" class="btn btn-sm btn-outline"><svg-icon name="plus" :size="14" /> Add User</button>
          </div>
          <div v-if="defaults.users && defaults.users.length">
            <table class="data-table">
              <thead><tr><th>Username</th><th>Email</th><th>First Name</th><th>Last Name</th><th style="width:100px">Role</th><th style="width:50px"></th></tr></thead>
              <tbody>
                <tr v-for="(u, i) in defaults.users" :key="i">
                  <td><input v-model="u.username" class="input-field" style="padding: 4px 8px; font-size: 0.8rem;" /></td>
                  <td><input v-model="u.email" class="input-field" style="padding: 4px 8px; font-size: 0.8rem;" /></td>
                  <td><input v-model="u.first_name" class="input-field" style="padding: 4px 8px; font-size: 0.8rem;" /></td>
                  <td><input v-model="u.last_name" class="input-field" style="padding: 4px 8px; font-size: 0.8rem;" /></td>
                  <td>
                    <select v-model="u.role" class="select-field" style="padding: 4px 6px; font-size: 0.8rem;">
                      <option value="administrator">Administrator</option>
                      <option value="editor">Editor</option>
                      <option value="author">Author</option>
                      <option value="subscriber">Subscriber</option>
                    </select>
                  </td>
                  <td><button @click="removeUser(i)" class="btn btn-sm btn-ghost" style="color: var(--color-error)"><svg-icon name="trash" :size="14" /></button></td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="text-sm py-2" style="color: var(--text-secondary)">No default users configured.</div>
        </div>
        <button @click="saveDefaults" :disabled="saving" class="btn btn-primary">{{ saving ? 'Saving...' : 'Save Defaults' }}</button>
      </div>
    </div>
  `,
});

// ─── View: ReportsView (Admin) ───────────────────────────────────────────────
const ReportsView = defineComponent({
  components: { DataTable },
  setup() {
    const { showNotify } = useNotify();
    const reports = ref([]);
    const loading = ref(true);
    const headers = [
      { title: 'Recipient', value: 'recipient' },
      { title: 'Interval', value: 'interval', width: '120px' },
      { title: 'Sites', value: 'site_count', width: '80px' },
      { title: 'Actions', value: '_actions', width: '140px', sortable: false },
    ];

    const showReportDialog = ref(false);
    const editingReport = ref(null);
    const reportForm = reactive({ recipient: '', interval: 'weekly', site_ids: [] });
    const reportSaving = ref(false);
    const previewing = ref(false);
    const previewHtml = ref('');
    const showPreviewDialog = ref(false);
    const sending = ref(false);

    function fetchReports() {
      loading.value = true;
      api.get('/wp-json/captaincore/v1/scheduled-reports')
        .then(r => { reports.value = (r.data || []).map(rpt => ({ ...rpt, site_count: rpt.site_ids ? rpt.site_ids.length : 0 })); })
        .catch(() => showNotify('Failed to load reports', 'error'))
        .finally(() => { loading.value = false; });
    }
    onMounted(fetchReports);

    function newReport() {
      editingReport.value = null;
      reportForm.recipient = '';
      reportForm.interval = 'weekly';
      reportForm.site_ids = [];
      showReportDialog.value = true;
    }
    function editReport(event, { item }) {
      editingReport.value = item;
      reportForm.recipient = item.recipient || '';
      reportForm.interval = item.interval || 'weekly';
      reportForm.site_ids = item.site_ids ? [...item.site_ids] : [];
      showReportDialog.value = true;
    }
    function saveReport() {
      reportSaving.value = true;
      const payload = { ...reportForm };
      const req = editingReport.value
        ? api.put('/wp-json/captaincore/v1/scheduled-reports/' + (editingReport.value.scheduled_report_id || editingReport.value.id), payload)
        : api.post('/wp-json/captaincore/v1/scheduled-reports', payload);
      req
        .then(() => { showNotify(editingReport.value ? 'Report updated' : 'Report scheduled', 'success'); showReportDialog.value = false; fetchReports(); })
        .catch(() => showNotify('Failed to save report', 'error'))
        .finally(() => { reportSaving.value = false; });
    }
    function deleteReport(rpt) {
      if (!confirm('Delete this scheduled report?')) return;
      api.delete('/wp-json/captaincore/v1/scheduled-reports/' + (rpt.scheduled_report_id || rpt.id))
        .then(() => { showNotify('Report deleted', 'success'); fetchReports(); })
        .catch(() => showNotify('Failed to delete', 'error'));
    }
    function previewReport(rpt) {
      previewing.value = true;
      api.post('/wp-json/captaincore/v1/report/preview', { site_ids: rpt.site_ids || [] })
        .then(r => { previewHtml.value = r.data.html || r.data || ''; showPreviewDialog.value = true; })
        .catch(() => showNotify('Failed to generate preview', 'error'))
        .finally(() => { previewing.value = false; });
    }
    function sendReport(rpt) {
      sending.value = true;
      api.post('/wp-json/captaincore/v1/report/send', { site_ids: rpt.site_ids || [], recipient: rpt.recipient })
        .then(() => showNotify('Report sent', 'success'))
        .catch(() => showNotify('Failed to send report', 'error'))
        .finally(() => { sending.value = false; });
    }

    return { reports, loading, headers, showReportDialog, editingReport, reportForm, reportSaving, newReport, editReport, saveReport, deleteReport, previewReport, sendReport, previewing, previewHtml, showPreviewDialog, sending, sites, sanitizeHtml };
  },
  template: `
    <div class="surface rounded-xl">
      <div class="flex items-center justify-between px-4 py-3" style="border-bottom: 1px solid var(--border-color)">
        <h2 class="text-sm font-semibold" style="color: var(--text-primary)">Scheduled Reports</h2>
        <button @click="newReport" class="btn btn-sm btn-primary"><svg-icon name="plus" :size="14" /> New Report</button>
      </div>
      <data-table :headers="headers" :items="reports" :loading="loading" :clickable="true" @click:row="editReport">
        <template #item._actions="{ item }">
          <div class="flex gap-1">
            <button @click.stop="previewReport(item)" :disabled="previewing" class="btn btn-sm btn-ghost" title="Preview"><svg-icon name="eye" :size="14" /></button>
            <button @click.stop="sendReport(item)" :disabled="sending" class="btn btn-sm btn-ghost" title="Send"><svg-icon name="mail" :size="14" /></button>
            <button @click.stop="deleteReport(item)" class="btn btn-sm btn-ghost" style="color: var(--color-error)" title="Delete"><svg-icon name="trash" :size="14" /></button>
          </div>
        </template>
      </data-table>
      <!-- Report Dialog -->
      <div v-if="showReportDialog" class="dialog-overlay" @click.self="showReportDialog = false">
        <div class="dialog-card" style="max-width: 560px;">
          <div class="dialog-card-header"><h3 class="text-sm font-semibold" style="color: var(--text-primary)">{{ editingReport ? 'Edit' : 'New' }} Scheduled Report</h3><button @click="showReportDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="close" :size="16" /></button></div>
          <div class="dialog-card-body space-y-4">
            <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Recipient Email</label><input v-model="reportForm.recipient" type="email" class="input-field" placeholder="email@example.com" /></div>
            <div><label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Interval</label>
              <select v-model="reportForm.interval" class="select-field"><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select>
            </div>
            <div>
              <label class="block text-xs mb-1 font-medium" style="color: var(--text-secondary)">Sites</label>
              <select v-model="reportForm.site_ids" multiple class="select-field" style="min-height: 120px;">
                <option v-for="s in sites" :key="s.site_id" :value="s.site_id">{{ s.name }}</option>
              </select>
              <div class="text-xs mt-1" style="color: var(--text-secondary)">Hold Ctrl/Cmd to select multiple ({{ reportForm.site_ids.length }} selected)</div>
            </div>
          </div>
          <div class="dialog-card-footer"><button @click="showReportDialog = false" class="btn btn-ghost">Cancel</button><button @click="saveReport" :disabled="reportSaving || !reportForm.recipient" class="btn btn-primary">{{ reportSaving ? 'Saving...' : 'Save' }}</button></div>
        </div>
      </div>
      <!-- Preview Dialog -->
      <div v-if="showPreviewDialog" class="dialog-overlay" @click.self="showPreviewDialog = false">
        <div class="dialog-card" style="max-width: 800px;">
          <div class="dialog-card-header"><h3 class="text-sm font-semibold" style="color: var(--text-primary)">Report Preview</h3><button @click="showPreviewDialog = false" class="btn btn-sm btn-ghost"><svg-icon name="close" :size="16" /></button></div>
          <div class="dialog-card-body" style="max-height: 70vh; overflow-y: auto;"><div v-html="sanitizeHtml(previewHtml)"></div></div>
          <div class="dialog-card-footer"><button @click="showPreviewDialog = false" class="btn btn-ghost">Close</button></div>
        </div>
      </div>
    </div>
  `,
});

// ─── View: StubView ──────────────────────────────────────────────────────────
const StubView = defineComponent({
  setup() {
    const route = useRoute();
    const routeName = computed(() => {
      const name = route.path.replace(new RegExp('^' + basePath.replace(/\/$/, '')), '').replace(/^\//, '');
      return name || 'home';
    });
    return { routeName };
  },
  template: `
    <div class="surface rounded-xl p-8 text-center mx-auto" style="max-width: 500px;">
      <div class="text-4xl mb-4" style="color: var(--text-secondary)">&#128679;</div>
      <h2 class="text-lg font-semibold mb-2" style="color: var(--text-primary)">{{ routeName }}</h2>
      <p class="text-sm" style="color: var(--text-secondary)">This view is not yet implemented in the Tailwind UI.</p>
    </div>
  `,
});

// ─── Router Setup ────────────────────────────────────────────────────────────
let routerInstance = null;

const routes = [
  {
    path: '/',
    component: AppLayout,
    children: [
      { path: '',                redirect: '/sites' },
      { path: 'sites',           component: SitesView },
      { path: 'sites/:id',       component: SiteDetailView },
      { path: 'domains',         component: DomainsView },
      { path: 'domains/:id',     component: DomainDetailView },
      { path: 'accounts',        component: AccountsView },
      { path: 'accounts/:id',    component: AccountDetailView },
      { path: 'billing',         component: BillingView },
      { path: 'billing/:id',     component: BillingDetailView },
      { path: 'cookbook',         component: CookbookView },
      { path: 'health',          component: HealthView },
      { path: 'activity-logs',   component: ActivityLogsView },
      { path: 'archives',        component: ArchivesView },
      { path: 'configurations',  component: ConfigurationsView },
      { path: 'reports',         component: ReportsView },
      { path: 'web-risk',        component: WebRiskView },
      { path: 'handbook',        component: HandbookView },
      { path: 'defaults',        component: SiteDefaultsView },
      { path: 'keys',            component: KeysView },
      { path: 'subscriptions',   component: SubscriptionsView },
      { path: 'subscription/:id',component: StubView },
      { path: 'users',           component: UsersView },
      { path: 'profile',         component: ProfileView },
      { path: ':pathMatch(.*)*',  component: StubView },
    ],
  },
  {
    path: '/login',
    component: BlankLayout,
    children: [
      { path: '', component: LoginView },
    ],
  },
  {
    path: '/connect',
    component: BlankLayout,
    children: [
      { path: '', component: StubView },
    ],
  },
];

const router = createRouter({
  history: createWebHistory(basePath),
  routes,
});

// Navigation guard: redirect unauthenticated users to login
router.beforeEach((to, from, next) => {
  if (!CC.wp_nonce && to.path !== '/login') {
    next('/login');
  } else {
    next();
  }
});

routerInstance = router;

// ─── App Creation & Mount ────────────────────────────────────────────────────
const app = createApp({
  setup() {
    return { notify };
  },
});

app.component('svg-icon', SvgIcon);
app.use(router);
app.mount('#app');

// ─── Intercom Widget Boot ────────────────────────────────────────────────────
if (CC.user.role !== 'administrator' && CC.configurations.intercom_embed_id && CC.user.email && CC.user.login && CC.user.registered) {
  window.Intercom && window.Intercom('boot', {
    app_id: CC.configurations.intercom_embed_id,
    name: CC.user.display_name,
    email: CC.user.email,
    created_at: CC.user.registered,
    user_hash: CC.user.hash,
  });
}
</script>
</body>
</html>
