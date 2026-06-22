<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADCT_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_export_csv' ) );
		add_action( 'admin_head', array( __CLASS__, 'admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_action( 'admin_footer', array( __CLASS__, 'admin_scripts' ) );
	}

	public static function is_admin_page( $page = '' ) {
		$current = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$pages   = array( 'tracking-template', 'tracking-template-sessions', 'tracking-template-leads', 'tracking-template-license' );

		if ( $page ) {
			return $current === $page;
		}

		return in_array( $current, $pages, true );
	}

	public static function admin_assets( $hook ) {
		if ( ! self::is_admin_page( 'tracking-template' ) ) {
			return;
		}

		$chart_path    = ADCT_PLUGIN_DIR . 'assets/chart.umd.min.js';
		$overview_path = ADCT_PLUGIN_DIR . 'assets/overview.js';

		wp_enqueue_script(
			'adct-chart',
			ADCT_PLUGIN_URL . 'assets/chart.umd.min.js',
			array(),
			'4.4.7',
			true
		);

		wp_enqueue_script(
			'adct-overview',
			ADCT_PLUGIN_URL . 'assets/overview.js',
			array( 'adct-chart' ),
			ADCT_VERSION . '.' . ( file_exists( $overview_path ) ? filemtime( $overview_path ) : ADCT_VERSION ),
			true
		);
	}

	const UTM_TEMPLATE = 'utm_source=google&utm_medium=cpc&utm_campaign={campaignname}&utm_id={campaignid}&utm_term={keyword}&utm_content={creative}';

	const GITHUB_REPO = 'https://github.com/benjamindimalanta/tracking-template';

	public static function admin_styles() {
		if ( ! self::is_admin_page() ) {
			return;
		}
		?>
		<style>
			.adct-wrap { max-width: none; }
			.adct-layout { display: grid; grid-template-columns: minmax(0, 1fr) minmax(280px, 320px); gap: 24px; align-items: start; }
			.adct-layout-header { grid-column: 1 / -1; margin-bottom: 4px; }
			.adct-layout-header h1 { margin: 0 0 10px; padding: 0; font-size: 23px; font-weight: 600; line-height: 1.3; }
			.adct-page-intro { color: #50575e; font-size: 14px; line-height: 1.65; margin: 0; max-width: none; }
			.adct-main { min-width: 0; }
			.adct-sidebar { display: flex; flex-direction: column; gap: 14px; position: sticky; top: 40px; }
			.adct-side-panel { background: #fff; border: 1px solid #e2e5ea; border-radius: 12px; padding: 16px; box-shadow: 0 2px 8px rgba(0,0,0,.04); }
			.adct-side-panel h3 { margin: 0 0 12px; font-size: 13px; font-weight: 700; color: #1a2332; letter-spacing: -.01em; }
			.adct-side-panel p { margin: 0 0 10px; font-size: 12px; line-height: 1.5; color: #646970; }
			.adct-side-panel p:last-child { margin-bottom: 0; }
			.adct-plugin-brand { background: linear-gradient(135deg, #1a2332 0%, #2c3e55 100%); border: 0; color: #fff; }
			.adct-plugin-brand h3 { color: #fff; font-size: 17px; margin: 0 0 8px; font-weight: 700; letter-spacing: -.02em; }
			.adct-plugin-brand .adct-plugin-desc { color: rgba(255,255,255,.88); font-size: 12px; line-height: 1.5; margin: 0 0 14px; }
			.adct-plugin-brand .adct-author { color: rgba(255,255,255,.45); font-size: 9px; margin: 12px 0 0; letter-spacing: .02em; text-transform: uppercase; }
			.adct-version-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px; }
			.adct-version-item { background: rgba(255,255,255,.1); border-radius: 8px; padding: 8px 10px; }
			.adct-version-item span { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: .06em; color: rgba(255,255,255,.6); }
			.adct-version-item strong { display: block; font-size: 13px; color: #fff; margin-top: 2px; }
			.adct-side-links { display: flex; flex-wrap: wrap; gap: 8px; }
			.adct-side-links a { font-size: 12px; color: #c9a227; text-decoration: none; font-weight: 600; }
			.adct-side-links a:hover { text-decoration: underline; color: #e0bc4a; }
			.adct-snapshot-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
			.adct-snapshot-item { background: #f6f8fa; border-radius: 8px; padding: 10px; border: 1px solid #eceff3; }
			.adct-snapshot-item.is-wide { grid-column: 1 / -1; }
			.adct-snapshot-item span { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: .05em; color: #8c8f94; font-weight: 600; }
			.adct-snapshot-item strong { display: block; font-size: 18px; color: #1a2332; margin-top: 2px; line-height: 1.2; }
			.adct-snapshot-item em { display: block; font-size: 12px; font-style: normal; color: #1a2332; font-weight: 600; margin-top: 2px; word-break: break-word; }
			.adct-feature-list { margin: 0; padding: 0; list-style: none; }
			.adct-feature-list li { position: relative; padding: 6px 0 6px 22px; font-size: 12px; color: #3c434a; line-height: 1.4; border-bottom: 1px solid #f0f0f1; }
			.adct-feature-list li:last-child { border-bottom: 0; }
			.adct-feature-list li::before { content: '✓'; position: absolute; left: 0; color: #c9a227; font-weight: 700; }
			.adct-side-actions { display: flex; flex-direction: column; gap: 8px; }
			.adct-side-actions .button { width: 100%; text-align: center; justify-content: center; }
			.adct-side-actions .button-link { width: 100%; text-align: center; }
			.adct-utm-box { font-family: Consolas, Monaco, monospace; font-size: 11px; background: #f6f8fa; border: 1px solid #dce1e8; border-radius: 8px; padding: 10px; word-break: break-all; color: #3c434a; margin-bottom: 10px; line-height: 1.45; }
			.adct-copy-toast { display: none; font-size: 11px; color: #1a7f37; font-weight: 600; margin-top: 6px; }
			.adct-copy-toast.is-visible { display: block; }
			.adct-status-list { margin: 0; padding: 0; list-style: none; }
			.adct-status-list li { display: flex; align-items: center; gap: 8px; padding: 5px 0; font-size: 12px; color: #3c434a; }
			.adct-status-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; background: #dcdcde; }
			.adct-status-dot.is-ok { background: #34a853; box-shadow: 0 0 0 2px rgba(52,168,83,.2); }
			.adct-status-dot.is-warn { background: #f9ab00; box-shadow: 0 0 0 2px rgba(249,171,0,.2); }
			.adct-update-panel .adct-version-grid { margin-bottom: 12px; }
			.adct-update-badge { display: inline-block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; padding: 4px 8px; border-radius: 999px; margin-bottom: 10px; }
			.adct-update-badge.is-current { background: #e6f4ea; color: #1e7e34; }
			.adct-update-badge.is-available { background: #fce8e6; color: #c5221f; }
			.adct-update-note { font-size: 11px; color: #646970; margin: 0 0 10px; line-height: 1.45; }
			.adct-role-list { margin: 0; padding: 0; list-style: none; max-height: 180px; overflow-y: auto; }
			.adct-role-list li { padding: 6px 0; border-bottom: 1px solid #f0f0f1; }
			.adct-role-list li:last-child { border-bottom: 0; }
			.adct-role-list label { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #3c434a; cursor: pointer; }
			.adct-role-list input { margin: 0; }
			.adct-access-note { font-size: 11px; color: #646970; margin: 0 0 10px; line-height: 1.45; }
			.adct-notice-inline { background: #edf7ed; border: 1px solid #b7dfc0; color: #1e4620; border-radius: 8px; padding: 8px 10px; font-size: 11px; margin-bottom: 10px; }
			.adct-setup-steps { margin: 0; padding: 0 0 0 18px; font-size: 12px; color: #3c434a; line-height: 1.55; }
			.adct-setup-steps li { margin-bottom: 8px; }
			.adct-setup-steps li:last-child { margin-bottom: 0; }
			.adct-license-locked { position: relative; min-height: 420px; }
			.adct-license-locked-content { filter: blur(5px); opacity: .45; pointer-events: none; user-select: none; }
			.adct-license-glass { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; padding: 24px; z-index: 20; }
			.adct-license-card { width: min(100%, 460px); background: rgba(255,255,255,.94); border: 1px solid #dce1e8; border-radius: 16px; padding: 28px 30px; box-shadow: 0 18px 48px rgba(26,35,50,.18); text-align: center; backdrop-filter: blur(8px); }
			.adct-license-card h2 { margin: 0 0 10px; font-size: 22px; color: #1a2332; }
			.adct-license-card p { margin: 0 0 16px; color: #50575e; font-size: 14px; line-height: 1.6; }
			.adct-license-card .adct-license-key-field { width: 100%; margin: 0 0 12px; text-align: center; font-family: Consolas, Monaco, monospace; letter-spacing: .04em; }
			.adct-license-card .button-primary { min-width: 160px; }
			.adct-license-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 18px 0 0; text-align: left; }
			.adct-license-meta-item { background: #f6f8fa; border: 1px solid #eceff3; border-radius: 10px; padding: 12px; }
			.adct-license-meta-item span { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: .05em; color: #8c8f94; font-weight: 600; }
			.adct-license-meta-item strong { display: block; margin-top: 4px; font-size: 13px; color: #1a2332; word-break: break-word; }
			.adct-license-status-badge { display: inline-block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; padding: 5px 10px; border-radius: 999px; margin-bottom: 12px; }
			.adct-license-status-badge.is-active { background: #e6f4ea; color: #1e7e34; }
			.adct-license-status-badge.is-warn { background: #fef7e0; color: #9a6700; }
			.adct-license-status-badge.is-error { background: #fce8e6; color: #c5221f; }
			.adct-license-page .adct-license-panel { max-width: 640px; }
			.adct-license-activated { margin-bottom: 18px; }
			.adct-license-activated-key { display: inline-block; font-family: Consolas, Monaco, monospace; font-size: 15px; letter-spacing: .04em; padding: 8px 12px; background: #f0f6fc; border: 1px solid #dce1e8; border-radius: 8px; color: #1a2332; }
			.adct-license-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
			.adct-license-change-form { margin-top: 16px; padding-top: 16px; border-top: 1px solid #eceff3; }
			@media screen and (max-width: 1280px) {
				.adct-layout { grid-template-columns: 1fr; }
				.adct-sidebar { position: static; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
			}
			@media screen and (max-width: 782px) {
				.adct-sidebar { grid-template-columns: 1fr; }
				.adct-stats-bar { grid-template-columns: repeat(2, minmax(0, 1fr)); }
				.adct-filters label { flex: 1 1 calc(50% - 8px); min-width: 140px; }
				.adct-session-card > summary { grid-template-columns: auto 1fr; }
				.adct-session-pills { grid-column: 1 / -1; flex-direction: row; flex-wrap: wrap; justify-content: flex-start; margin-top: 4px; }
			}
			@media screen and (max-width: 480px) {
				.adct-filters label { flex: 1 1 100%; }
				.adct-stats-bar { grid-template-columns: 1fr; }
				.adct-snapshot-grid { grid-template-columns: 1fr; }
				.adct-version-grid { grid-template-columns: 1fr; }
			}
			.adct-wrap > p { color: #50575e; font-size: 14px; line-height: 1.5; }
			.adct-filters { display: flex; flex-wrap: wrap; gap: 10px 14px; align-items: end; margin: 16px 0 20px; padding: 18px; background: linear-gradient(180deg, #fff 0%, #f8f9fb 100%); border: 1px solid #dcdcde; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
			.adct-filters label { display: flex; flex-direction: column; gap: 4px; font-weight: 600; font-size: 12px; color: #3c434a; }
			.adct-stats-bar { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin: 0 0 18px; }
			.adct-stat-card { background: linear-gradient(135deg, #1a2332 0%, #2c3e55 100%); border: 0; border-radius: 12px; padding: 16px 18px; min-width: 0; box-shadow: 0 4px 14px rgba(26,35,50,.18); }
			.adct-stat-card:nth-child(2) { background: linear-gradient(135deg, #8b6914 0%, #c9a227 100%); box-shadow: 0 4px 14px rgba(139,105,20,.22); }
			.adct-stat-card:nth-child(3) { background: linear-gradient(135deg, #174ea6 0%, #4285f4 100%); box-shadow: 0 4px 14px rgba(23,78,166,.2); }
			.adct-stat-card:nth-child(4) { background: linear-gradient(135deg, #2e6b4a 0%, #34a853 100%); box-shadow: 0 4px 14px rgba(46,107,74,.2); }
			.adct-stat-card strong { display: block; font-size: 26px; line-height: 1.1; color: #fff; font-weight: 700; word-break: break-word; }
			.adct-stat-card span { color: rgba(255,255,255,.78); font-size: 11px; margin-top: 6px; display: block; line-height: 1.35; }
			.adct-table-toolbar { margin: 12px 0 16px; color: #646970; font-size: 13px; }
			.adct-pagination { margin: 20px 0 0; }
			.adct-pagination .page-numbers { margin-right: 4px; }
			.adct-card-list { display: grid; gap: 16px; }
			.adct-badge { display: inline-flex; align-items: center; justify-content: center; gap: 4px; padding: 4px 10px; border-radius: 999px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; background: #2271b1; color: #fff; line-height: 1.4; white-space: nowrap; }
			.adct-badge.is-whatsapp { background: linear-gradient(135deg, #1ebe57, #25d366); }
			.adct-badge.is-phone { background: linear-gradient(135deg, #2f4fd8, #3858e9); }
			.adct-badge.is-showroom { background: linear-gradient(135deg, #563a82, #6b4e9b); }
			.adct-badge.is-floating { background: linear-gradient(135deg, #9a4e00, #b26200); }
			.adct-badge.is-elfsight { background: linear-gradient(135deg, #3d4349, #50575e); }
			.adct-badge.is-mini { font-size: 9px; padding: 3px 8px; }
			.adct-thumb { width: 72px; height: 72px; object-fit: cover; border-radius: 10px; border: 1px solid #e2e4e7; flex: 0 0 72px; box-shadow: 0 2px 6px rgba(0,0,0,.06); }
			.adct-thumb-empty { width: 72px; height: 72px; border-radius: 10px; border: 1px dashed #c3c4c7; display: flex; align-items: center; justify-content: center; color: #a7aaad; font-size: 10px; flex: 0 0 72px; background: #f6f7f7; }
			.adct-product-row { display: flex; gap: 12px; align-items: flex-start; margin-bottom: 0; }
			.adct-product-main { min-width: 0; flex: 1; }
			.adct-product-main h3 { margin: 0 0 4px; font-size: 14px; line-height: 1.35; color: #1d2327; }
			.adct-product-main a { word-break: break-all; font-size: 12px; color: #2271b1; }
			.adct-product-meta { display: flex; flex-wrap: wrap; gap: 8px 14px; margin-top: 6px; color: #50575e; font-size: 12px; }
			.adct-meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
			.adct-meta-box { background: #fff; border: 1px solid #e8eaed; border-radius: 10px; padding: 14px; box-shadow: 0 1px 2px rgba(0,0,0,.03); }
			.adct-meta-box h4 { margin: 0 0 10px; font-size: 10px; text-transform: uppercase; letter-spacing: .08em; color: #8c8f94; font-weight: 700; }
			.adct-meta-item { display: flex; justify-content: space-between; gap: 10px; padding: 6px 0; border-bottom: 1px solid #f0f0f1; font-size: 13px; }
			.adct-meta-item:last-child { border-bottom: 0; padding-bottom: 0; }
			.adct-meta-item span { color: #646970; flex: 0 0 42%; }
			.adct-meta-item strong { text-align: right; font-weight: 600; color: #1d2327; word-break: break-word; flex: 1; }
			.adct-meta-item a { color: #1a5fb4; text-decoration: none; word-break: break-all; }
			.adct-meta-item a:hover { text-decoration: underline; }
			.adct-empty { padding: 40px 28px; text-align: center; background: #fff; border: 1px dashed #c3c4c7; border-radius: 12px; color: #646970; }
			.adct-overview-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin: 0 0 18px; }
			.adct-overview-toolbar p { margin: 0; color: #646970; font-size: 13px; }
			.adct-period-form { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
			.adct-period-form label { display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 600; color: #3c434a; margin: 0; }
			.adct-period-form select { min-width: 140px; }
			.adct-overview-card { background: #fff; border: 1px solid #e2e5ea; border-radius: 14px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,.04); margin-bottom: 18px; }
			.adct-overview-card-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 18px; }
			.adct-overview-card-head h2 { margin: 0; font-size: 16px; font-weight: 700; color: #1a2332; }
			.adct-overview-card-head a { font-size: 12px; font-weight: 600; color: #2271b1; text-decoration: none; }
			.adct-overview-card-head a:hover { text-decoration: underline; }
			.adct-summary-grid { display: grid; grid-template-columns: minmax(220px, 280px) minmax(0, 1fr); gap: 24px; align-items: center; }
			.adct-chart-shell { position: relative; width: 100%; max-width: 280px; margin: 0 auto; height: 240px; }
			.adct-chart-shell.is-wide { max-width: none; height: 280px; }
			.adct-legend-list { margin: 0; padding: 0; list-style: none; }
			.adct-legend-item + .adct-legend-item { border-top: 1px solid #f0f0f1; }
			.adct-legend-link { display: grid; grid-template-columns: 14px minmax(0, 1fr) auto auto auto; gap: 12px; align-items: center; padding: 12px 4px; color: inherit; text-decoration: none; }
			.adct-legend-link:hover { background: #f8f9fb; border-radius: 8px; }
			.adct-legend-swatch { width: 14px; height: 14px; border-radius: 4px; flex-shrink: 0; }
			.adct-legend-label { font-size: 13px; font-weight: 600; color: #1a2332; min-width: 0; }
			.adct-legend-percent { font-size: 13px; color: #646970; white-space: nowrap; }
			.adct-legend-count { font-size: 13px; font-weight: 700; color: #1a2332; white-space: nowrap; }
			.adct-legend-chevron { color: #a7aaad; font-size: 18px; line-height: 1; }
			.adct-panel-tabs { display: flex; flex-wrap: wrap; gap: 18px; border-bottom: 1px solid #eceff3; margin: 0 0 18px; }
			.adct-panel-tab { appearance: none; background: none; border: 0; padding: 0 0 12px; margin: 0; font-size: 14px; font-weight: 600; color: #646970; cursor: pointer; border-bottom: 2px solid transparent; }
			.adct-panel-tab.is-active { color: #1a2332; border-bottom-color: #4285f4; }
			.adct-panel-section { display: none; }
			.adct-panel-section.is-active { display: block; }
			.adct-metric-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 18px; }
			.adct-metric-tile { appearance: none; background: #f6f8fa; border: 1px solid #e2e5ea; border-radius: 12px; padding: 14px 16px; text-align: left; cursor: pointer; transition: border-color .2s ease, box-shadow .2s ease, background .2s ease; }
			.adct-metric-tile.is-active { background: #eef4fd; border-color: #4285f4; box-shadow: inset 0 0 0 1px rgba(66,133,244,.15); }
			.adct-metric-tile[data-adct-metric="sessions"].is-active { background: #fbf6ea; border-color: #c9a227; box-shadow: inset 0 0 0 1px rgba(201,162,39,.18); }
			.adct-metric-tile.is-static { cursor: default; }
			.adct-metric-tile span { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #8c8f94; font-weight: 700; margin-bottom: 6px; }
			.adct-metric-tile strong { display: block; font-size: 24px; line-height: 1.1; color: #1a2332; }
			.adct-rate-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-top: 18px; }
			.adct-rate-tile { background: linear-gradient(180deg, #fff 0%, #f8fafc 100%); border: 1px solid #e2e5ea; border-radius: 12px; padding: 14px 16px; box-shadow: 0 1px 4px rgba(26,35,50,.05); transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease; }
			.adct-rate-tile:hover { transform: translateY(-2px); box-shadow: 0 8px 18px rgba(26,35,50,.08); border-color: #cfd6df; }
			.adct-rate-tile span { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #8c8f94; font-weight: 700; margin-bottom: 6px; }
			.adct-rate-tile strong { display: block; font-size: 22px; color: #1a2332; }
			.adct-insights-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin-top: 24px; padding-top: 24px; border-top: 1px solid #eceff3; }
			.adct-insight-panel { position: relative; overflow: hidden; background: linear-gradient(180deg, #fff 0%, #f9fafb 100%); border: 1px solid #e2e5ea; border-radius: 14px; padding: 18px 16px 16px; min-width: 0; box-shadow: 0 2px 10px rgba(26,35,50,.05); transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease; }
			.adct-insight-panel::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: #4285f4; opacity: .85; }
			.adct-insight-panel.is-cars::before { background: linear-gradient(90deg, #4285f4, #6ea6ff); }
			.adct-insight-panel.is-agents::before { background: linear-gradient(90deg, #c9a227, #e0bc5a); }
			.adct-insight-panel.is-devices::before { background: linear-gradient(90deg, #34a853, #5bc47a); }
			.adct-insight-panel:hover { transform: translateY(-2px); box-shadow: 0 10px 22px rgba(26,35,50,.08); border-color: #cfd6df; }
			.adct-insight-panel h3 { margin: 0 0 12px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #646970; }
			.adct-insight-list { margin: 0; padding: 0; list-style: none; }
			.adct-insight-item { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 10px; align-items: center; padding: 9px 10px; margin: 0 -6px; font-size: 13px; border-radius: 8px; transition: background .18s ease, transform .18s ease; }
			.adct-insight-item + .adct-insight-item { border-top: 1px solid #eef1f4; margin-top: 2px; }
			.adct-insight-item:hover { background: rgba(66,133,244,.06); transform: translateX(3px); }
			.adct-insight-label { font-weight: 600; color: #1a2332; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
			.adct-insight-count { font-weight: 700; color: #1a2332; white-space: nowrap; background: #eef2f7; padding: 3px 9px; border-radius: 999px; font-size: 12px; transition: background .18s ease, color .18s ease; }
			.adct-insight-item:hover .adct-insight-count { background: #e3ecfb; color: #1a4b91; }
			.adct-insight-item.is-rank-1 .adct-insight-count { background: #fff4dd; color: #8a6a12; }
			.adct-insight-item.is-rank-2 .adct-insight-count { background: #f0f2f5; color: #50575e; }
			.adct-insight-item.is-rank-3 .adct-insight-count { background: #f7efe8; color: #7a4f2e; }
			.adct-insight-device { grid-template-columns: 14px minmax(0, 1fr) auto; }
			.adct-insight-swatch { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; box-shadow: 0 0 0 2px rgba(255,255,255,.9); }
			.adct-insight-empty { margin: 0; font-size: 12px; color: #646970; font-style: italic; }
			.adct-traffic-section { margin-top: 24px; padding-top: 24px; border-top: 1px solid #eceff3; }
			.adct-traffic-section h3 { margin: 0 0 6px; font-size: 13px; font-weight: 700; color: #1a2332; }
			.adct-traffic-note { margin: 0 0 14px; font-size: 12px; color: #646970; line-height: 1.45; }
			.adct-traffic-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
			.adct-traffic-tile { position: relative; background: #fff; border: 1px solid #e2e5ea; border-radius: 14px; padding: 18px 16px 16px; box-shadow: 0 2px 10px rgba(26,35,50,.05); border-left: 3px solid transparent; transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease; }
			.adct-traffic-tile.is-channel-organic { background: linear-gradient(135deg, #fff 0%, #f3fbf5 100%); border-left-color: #34a853; }
			.adct-traffic-tile.is-channel-google_campaign { background: linear-gradient(135deg, #fff 0%, #f3f7ff 100%); border-left-color: #4285f4; }
			.adct-traffic-tile.is-channel-referrers { background: linear-gradient(135deg, #fff 0%, #f7f4fc 100%); border-left-color: #7c5cbf; }
			.adct-traffic-tile:hover { transform: translateY(-3px); box-shadow: 0 12px 26px rgba(26,35,50,.1); border-color: #cfd6df; }
			.adct-traffic-tile-head { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
			.adct-traffic-swatch { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; box-shadow: 0 0 0 3px rgba(255,255,255,.85); transition: transform .22s ease; }
			.adct-traffic-tile:hover .adct-traffic-swatch { transform: scale(1.15); }
			.adct-traffic-tile-head span:last-child { font-size: 12px; font-weight: 700; color: #50575e; }
			.adct-traffic-stats { display: flex; align-items: baseline; gap: 8px; margin-bottom: 12px; }
			.adct-traffic-stats strong { font-size: 28px; line-height: 1; color: #1a2332; letter-spacing: -.02em; }
			.adct-traffic-stats em { font-style: normal; font-size: 14px; font-weight: 700; color: #646970; background: rgba(255,255,255,.7); padding: 2px 8px; border-radius: 999px; }
			.adct-traffic-bar { height: 7px; border-radius: 999px; background: rgba(26,35,50,.08); overflow: hidden; }
			.adct-traffic-bar span { display: block; height: 100%; border-radius: inherit; min-width: 0; transition: width .65s cubic-bezier(.22,1,.36,1); box-shadow: inset 0 -1px 0 rgba(255,255,255,.25); }
			.adct-traffic-link { display: inline-block; margin: 12px 0 0; font-size: 11px; font-weight: 600; color: #2271b1; text-decoration: none; opacity: .85; transition: opacity .18s ease, transform .18s ease, color .18s ease; }
			.adct-traffic-link:hover { opacity: 1; color: #135e96; transform: translateX(2px); }
			.adct-recent-leads { margin-top: 24px; padding-top: 24px; border-top: 1px solid #eceff3; }
			.adct-recent-leads-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
			.adct-recent-leads-head h3 { margin: 0; font-size: 13px; font-weight: 700; color: #1a2332; }
			.adct-recent-leads-head a { font-size: 12px; font-weight: 600; color: #2271b1; text-decoration: none; padding: 4px 10px; border-radius: 999px; background: #eef4fd; transition: background .18s ease, color .18s ease, transform .18s ease; }
			.adct-recent-leads-head a:hover { background: #dce9fb; color: #135e96; transform: translateY(-1px); text-decoration: none; }
			.adct-recent-leads-list { margin: 0; padding: 0; list-style: none; border: 1px solid #e2e5ea; border-radius: 14px; overflow: hidden; background: #fff; box-shadow: 0 2px 10px rgba(26,35,50,.05); }
			.adct-recent-lead-item { display: grid; grid-template-columns: minmax(120px, auto) minmax(0, 1fr) auto; gap: 12px; align-items: center; padding: 13px 16px; font-size: 12px; transition: background .18s ease; }
			.adct-recent-lead-item + .adct-recent-lead-item { border-top: 1px solid #f0f0f1; }
			.adct-recent-lead-item:hover { background: linear-gradient(90deg, rgba(238,244,253,.55) 0%, #fff 100%); }
			.adct-recent-lead-date { font-weight: 600; color: #1a2332; white-space: nowrap; }
			.adct-recent-lead-main { min-width: 0; }
			.adct-recent-lead-main strong { display: block; font-size: 13px; color: #1a2332; margin-bottom: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; transition: color .18s ease; }
			.adct-recent-lead-item:hover .adct-recent-lead-main strong { color: #135e96; }
			.adct-recent-lead-main span { color: #646970; }
			@media (prefers-reduced-motion: reduce) {
				.adct-rate-tile, .adct-insight-panel, .adct-insight-item, .adct-traffic-tile, .adct-traffic-swatch, .adct-traffic-bar span, .adct-traffic-link, .adct-recent-leads-head a, .adct-recent-lead-item { transition: none; }
				.adct-rate-tile:hover, .adct-insight-panel:hover, .adct-traffic-tile:hover { transform: none; }
				.adct-insight-item:hover { transform: none; }
			}
			.adct-overview-note { margin: 0 0 16px; color: #646970; font-size: 12px; line-height: 1.5; }
			.adct-marketing-section + .adct-marketing-section { margin-top: 24px; padding-top: 24px; border-top: 1px solid #eceff3; }
			.adct-marketing-section h3 { margin: 0 0 14px; font-size: 13px; font-weight: 700; color: #1a2332; }
			.adct-leads-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 16px; }
			.adct-leads-tab { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 999px; border: 1px solid #dcdcde; background: #fff; color: #1a2332; text-decoration: none; font-size: 13px; font-weight: 600; transition: border-color .2s ease, background .2s ease, box-shadow .2s ease; }
			.adct-leads-tab:hover { border-color: #4285f4; color: #1a2332; }
			.adct-leads-tab.is-active { background: #1a2332; border-color: #1a2332; color: #fff; box-shadow: 0 4px 12px rgba(26,35,50,.18); }
			.adct-leads-tab-count { display: inline-flex; align-items: center; justify-content: center; min-width: 24px; padding: 2px 8px; border-radius: 999px; background: rgba(0,0,0,.08); font-size: 11px; font-weight: 700; }
			.adct-leads-tab.is-active .adct-leads-tab-count { background: rgba(255,255,255,.16); color: #fff; }
			.adct-leads-table-wrap { overflow-x: auto; border: 1px solid #e2e5ea; border-radius: 14px; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,.04); }
			.adct-leads-table { width: 100%; border-collapse: collapse; min-width: 880px; }
			.adct-leads-table th { text-align: left; padding: 12px 16px; font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: #646970; background: #f6f8fa; border-bottom: 1px solid #eceff3; }
			.adct-leads-table td { padding: 14px 16px; vertical-align: top; border-bottom: 1px solid #f0f0f1; font-size: 13px; color: #1d2327; }
			.adct-leads-table tr:last-child td { border-bottom: 0; }
			.adct-leads-table tbody tr:hover { background: #fafbfc; }
			.adct-lead-date { font-weight: 600; color: #1a2332; white-space: nowrap; display: grid; gap: 2px; }
			.adct-lead-date-day { font-size: 12px; line-height: 1.2; }
			.adct-lead-date-time { font-size: 11px; line-height: 1.2; color: #646970; font-weight: 500; }
			.adct-lead-product { display: flex; gap: 12px; align-items: flex-start; min-width: 240px; }
			.adct-lead-product .adct-thumb, .adct-lead-product .adct-thumb-empty { width: 56px; height: 56px; flex: 0 0 56px; }
			.adct-lead-product h4 { margin: 0 0 4px; font-size: 13px; line-height: 1.35; color: #1a2332; }
			.adct-lead-product p { margin: 0; font-size: 12px; color: #646970; line-height: 1.45; }
			.adct-lead-meta { display: grid; gap: 4px; font-size: 12px; color: #50575e; }
			.adct-lead-meta strong { color: #1a2332; font-weight: 600; }
			.adct-lead-empty-product { color: #646970; font-size: 12px; font-style: italic; }
			.adct-leads-table .adct-badge { min-height: 22px; min-width: 116px; padding-left: 10px; padding-right: 10px; }
			.adct-lead-session { position: relative; display: inline-block; min-width: 88px; }
			.adct-lead-session-link { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 999px; background: #eef2f7; color: #1a4b91; font-size: 12px; font-weight: 700; text-decoration: none; transition: background .18s ease, color .18s ease, box-shadow .18s ease; }
			.adct-lead-session-link:hover { background: #dce9fb; color: #135e96; box-shadow: 0 2px 8px rgba(26,75,145,.12); }
			.adct-lead-session-meta { display: block; margin-top: 4px; font-size: 11px; color: #646970; }
			.adct-session-popover { display: none; position: absolute; left: 0; top: calc(100% + 8px); z-index: 30; width: 260px; padding: 12px 14px; border-radius: 12px; border: 1px solid #dce1e8; background: #fff; box-shadow: 0 12px 28px rgba(26,35,50,.14); text-align: left; }
			.adct-session-popover::before { content: ''; position: absolute; top: -6px; left: 18px; width: 10px; height: 10px; background: #fff; border-left: 1px solid #dce1e8; border-top: 1px solid #dce1e8; transform: rotate(45deg); }
			.adct-lead-session:hover .adct-session-popover, .adct-lead-session:focus-within .adct-session-popover { display: block; }
			.adct-session-popover h4 { margin: 0 0 8px; font-size: 12px; font-weight: 700; color: #1a2332; }
			.adct-session-popover dl { margin: 0; display: grid; gap: 6px; }
			.adct-session-popover dt { font-size: 10px; text-transform: uppercase; letter-spacing: .05em; color: #8c8f94; font-weight: 700; }
			.adct-session-popover dd { margin: 0 0 2px; font-size: 12px; color: #1a2332; line-height: 1.4; }
			.adct-session-popover-foot { margin-top: 10px; padding-top: 10px; border-top: 1px solid #eceff3; font-size: 11px; }
			.adct-session-popover-foot a { color: #2271b1; font-weight: 600; text-decoration: none; }
			.adct-session-popover-foot a:hover { text-decoration: underline; }
			.adct-session-focus-banner { margin: 0 0 16px; padding: 12px 14px; border-radius: 12px; border: 1px solid #c9daf8; background: #eef4fd; color: #1a4b91; font-size: 13px; }
			.adct-session-focus-banner a { color: #135e96; font-weight: 600; }
			.adct-intent-note { margin: 0 0 16px; padding: 12px 14px; border-radius: 12px; border: 1px solid #e8eaed; background: #f8f9fb; color: #50575e; font-size: 12px; line-height: 1.5; }
			.adct-intent-note strong { color: #1a2332; }
			@media screen and (max-width: 960px) {
				.adct-summary-grid { grid-template-columns: 1fr; }
				.adct-chart-shell { max-width: 240px; }
				.adct-metric-grid { grid-template-columns: 1fr; }
				.adct-rate-grid { grid-template-columns: 1fr; }
				.adct-insights-grid, .adct-traffic-grid { grid-template-columns: 1fr; }
			}

			/* Session cards */
			.adct-session-card { background: #fff; border: 1px solid #e2e5ea; border-radius: 14px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.05); transition: box-shadow .2s ease, border-color .2s ease; }
			.adct-session-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,.08); border-color: #cfd6df; }
			.adct-session-card[open] { box-shadow: 0 8px 24px rgba(0,0,0,.1); border-color: #c9a227; }
			.adct-session-card.is-source-google_cpc { border-left: 4px solid #4285f4; }
			.adct-session-card.is-source-direct { border-left: 4px solid #9aa0a6; }
			.adct-session-card.is-source-google_organic { border-left: 4px solid #34a853; }
			.adct-session-card.is-source-referral { border-left: 4px solid #7c5cbf; }
			.adct-session-card > summary { list-style: none; cursor: pointer; display: grid; grid-template-columns: auto 1fr auto; gap: 14px; align-items: center; padding: 16px 18px; background: linear-gradient(180deg, #fafbfc 0%, #f4f6f8 100%); border-bottom: 1px transparent; user-select: none; }
			.adct-session-card[open] > summary { border-bottom: 1px solid #eceff3; background: linear-gradient(180deg, #f0f4f8 0%, #e8edf3 100%); }
			.adct-session-card > summary::-webkit-details-marker { display: none; }
			.adct-session-chevron { width: 32px; height: 32px; border-radius: 50%; background: #fff; border: 1px solid #dce1e8; display: flex; align-items: center; justify-content: center; color: #50575e; font-size: 18px; line-height: 1; transition: transform .2s ease, background .2s ease; flex-shrink: 0; }
			.adct-session-card[open] .adct-session-chevron { transform: rotate(90deg); background: #1a2332; color: #c9a227; border-color: #1a2332; }
			.adct-session-summary-main { min-width: 0; }
			.adct-session-time { display: block; font-size: 14px; font-weight: 700; color: #1a2332; margin-bottom: 6px; letter-spacing: -.01em; }
			.adct-session-meta-line { display: flex; flex-wrap: wrap; gap: 6px 14px; color: #646970; font-size: 12px; line-height: 1.4; }
			.adct-session-meta-line span { display: inline-flex; align-items: center; gap: 4px; }
			.adct-session-meta-line .dashicons { font-size: 14px; width: 14px; height: 14px; color: #8c8f94; }
			.adct-session-types { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 8px; }
			.adct-session-pills { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; flex-shrink: 0; }
			.adct-session-pill { display: inline-flex; align-items: center; gap: 5px; border-radius: 999px; padding: 6px 12px; font-size: 11px; font-weight: 700; white-space: nowrap; }
			.adct-session-pill.is-clicks { background: #1a2332; color: #fff; font-size: 13px; padding: 8px 14px; }
			.adct-session-pill.is-duration { background: #eef2f7; color: #3c4a5c; border: 1px solid #dce3ec; }
			.adct-session-pill.is-source { background: linear-gradient(135deg, #e8f0fe, #d2e3fc); color: #174ea6; border: 1px solid #aecbfa; }
			.adct-session-pill.is-source.is-direct { background: #f1f3f4; color: #5f6368; border-color: #dadce0; }
			.adct-session-body { padding: 18px; display: grid; gap: 16px; background: linear-gradient(180deg, #f8f9fb 0%, #fff 100%); }
			.adct-session-section-title { margin: 0; font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: #8c8f94; font-weight: 700; }
			.adct-session-clicks { display: grid; gap: 12px; position: relative; padding-left: 20px; }
			.adct-session-clicks::before { content: ''; position: absolute; left: 5px; top: 8px; bottom: 8px; width: 2px; background: linear-gradient(180deg, #c9a227, #e2e8f0); border-radius: 2px; }
			.adct-session-click { position: relative; border: 1px solid #e8eaed; border-radius: 12px; padding: 14px; background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
			.adct-session-click::before { content: ''; position: absolute; left: -18px; top: 18px; width: 10px; height: 10px; border-radius: 50%; background: #c9a227; border: 2px solid #fff; box-shadow: 0 0 0 2px #c9a227; }
			.adct-session-click-header { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid #f0f0f1; }
			.adct-session-click-header time { font-size: 12px; color: #646970; font-weight: 600; }
			.adct-session-click .adct-meta-grid { margin-top: 12px; }
		</style>
		<?php
	}

	public static function render_location( $country, $region ) {
		$parts = array_filter(
			array(
				trim( (string) $country ),
				trim( (string) $region ),
			)
		);

		if ( empty( $parts ) ) {
			echo '<span aria-hidden="true">—</span>';
			return;
		}

		echo esc_html( implode( ', ', $parts ) );
	}

	public static function format_device_type( $device_type ) {
		$device_type = sanitize_key( (string) $device_type );

		if ( '' === $device_type ) {
			return '—';
		}

		return ucfirst( $device_type );
	}

	public static function format_entry_source( $entry_source ) {
		$entry_source = sanitize_key( (string) $entry_source );

		if ( '' === $entry_source ) {
			return '—';
		}

		$labels = array(
			'google_cpc'     => 'Google Ads',
			'google_organic' => 'Google Organic',
			'direct'         => 'Direct',
			'referral'       => 'Referral',
			'youtube'        => 'YouTube',
			'facebook'       => 'Facebook',
			'instagram'      => 'Instagram',
		);

		return $labels[ $entry_source ] ?? ucwords( str_replace( '_', ' ', $entry_source ) );
	}

	public static function format_landing_path_short( $landing_path, $max_length = 42 ) {
		$landing_path = trim( (string) $landing_path );

		if ( '' === $landing_path ) {
			return '';
		}

		if ( preg_match( '#^https?://#i', $landing_path ) ) {
			$parsed = wp_parse_url( $landing_path );

			if ( ! empty( $parsed['path'] ) ) {
				$landing_path = $parsed['path'];
			}

			if ( ! empty( $parsed['query'] ) ) {
				$landing_path .= '?' . $parsed['query'];
			}
		} elseif ( false !== strpos( $landing_path, '/' ) && '/' !== $landing_path[0] ) {
			$first_slash = strpos( $landing_path, '/' );
			$landing_path = substr( $landing_path, $first_slash );
		}

		$landing_path = preg_replace( '#/+#', '/', $landing_path );

		if ( strlen( $landing_path ) <= $max_length ) {
			return $landing_path;
		}

		return substr( $landing_path, 0, $max_length - 1 ) . '…';
	}

	public static function format_duration( $seconds ) {
		$seconds = absint( $seconds );

		if ( 0 === $seconds ) {
			return '—';
		}

		if ( $seconds < 60 ) {
			return sprintf( '%ds', $seconds );
		}

		$minutes = (int) floor( $seconds / 60 );
		$remain  = $seconds % 60;

		if ( $minutes < 60 ) {
			return $remain ? sprintf( '%dm %ds', $minutes, $remain ) : sprintf( '%dm', $minutes );
		}

		$hours = (int) floor( $minutes / 60 );
		$mins  = $minutes % 60;

		return $mins ? sprintf( '%dh %dm', $hours, $mins ) : sprintf( '%dh', $hours );
	}

	public static function has_attribution_data( $row ) {
		$fields = array(
			'entry_source',
			'landing_url',
			'landing_path',
			'utm_campaign',
			'utm_source',
			'utm_medium',
			'gclid',
			'landing_referrer',
		);

		foreach ( $fields as $field ) {
			if ( ! empty( $row->$field ) ) {
				return true;
			}
		}

		return ! empty( $row->duration_seconds );
	}

	public static function render_product_image( $url, $title ) {
		if ( empty( $url ) ) {
			echo '<span aria-hidden="true">—</span>';
			return;
		}

		printf(
			'<img class="adct-thumb" src="%s" alt="%s" loading="lazy" />',
			esc_url( $url ),
			esc_attr( $title )
		);
	}

	public static function render_pagination( $total_items, $per_page, $paged, $query_args ) {
		$total_pages = max( 1, (int) ceil( $total_items / $per_page ) );

		if ( $total_pages <= 1 ) {
			return;
		}

		$query_args['per_page'] = $per_page;
		unset( $query_args['paged'] );

		$links = paginate_links(
			array(
				'base'      => add_query_arg( array_merge( $query_args, array( 'paged' => '%#%' ) ), admin_url( 'admin.php' ) ),
				'format'    => '',
				'current'   => $paged,
				'total'     => $total_pages,
				'prev_text' => '&laquo; Previous',
				'next_text' => 'Next &raquo;',
				'type'      => 'plain',
			)
		);

		if ( ! $links ) {
			return;
		}

		echo '<div class="adct-pagination tablenav"><div class="tablenav-pages">' . wp_kses_post( $links ) . '</div></div>';
	}

	public static function format_contact_type_label( $contact_type ) {
		$labels = array(
			'whatsapp'           => 'WhatsApp',
			'phone'              => 'Phone',
			'showroom_landline'  => 'Showroom',
			'floating_whatsapp'  => 'Floating WhatsApp',
			'floating_phone'     => 'Floating Call',
			'elfsight_call'      => 'Elfsight Call',
			'footer_landline'    => 'Footer Call',
		);

		return $labels[ $contact_type ] ?? ucwords( str_replace( '_', ' ', (string) $contact_type ) );
	}

	public static function contact_type_badge_class( $contact_type ) {
		$map = array(
			'whatsapp'          => 'is-whatsapp',
			'phone'             => 'is-phone',
			'showroom_landline' => 'is-showroom',
			'floating_whatsapp' => 'is-floating',
			'floating_phone'    => 'is-floating',
			'elfsight_call'     => 'is-elfsight',
			'footer_landline'   => 'is-showroom',
		);

		return $map[ $contact_type ] ?? '';
	}

	public static function render_meta_box( $title, $items ) {
		?>
		<div class="adct-meta-box">
			<h4><?php echo esc_html( $title ); ?></h4>
			<?php foreach ( $items as $item ) : ?>
				<div class="adct-meta-item">
					<span><?php echo esc_html( $item['label'] ); ?></span>
					<strong>
						<?php if ( ! empty( $item['url'] ) ) : ?>
							<a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) $item['value'] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( (string) $item['value'] ); ?>
						<?php endif; ?>
					</strong>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	public static function build_marketing_items( $row ) {
		$items = array();

		if ( ! empty( $row->entry_source ) ) {
			$items[] = array(
				'label' => 'Entry source',
				'value' => self::format_entry_source( $row->entry_source ),
			);
		}

		if ( ! empty( $row->landing_url ) ) {
			$items[] = array(
				'label' => 'Landing URL',
				'value' => $row->landing_url,
				'url'   => $row->landing_url,
			);
		} elseif ( ! empty( $row->landing_path ) ) {
			$items[] = array(
				'label' => 'Landing page',
				'value' => $row->landing_path,
			);
		}

		if ( ! empty( $row->session_duration ) || ! empty( $row->duration_seconds ) ) {
			$items[] = array(
				'label' => 'Session time',
				'value' => self::format_duration( $row->session_duration ?? $row->duration_seconds ?? 0 ),
			);
		}

		if ( ! empty( $row->utm_campaign ) ) {
			$items[] = array(
				'label' => 'Campaign name',
				'value' => $row->utm_campaign,
			);
		}

		if ( ! empty( $row->utm_id ) && $row->utm_id !== ( $row->utm_campaign ?? '' ) ) {
			$items[] = array(
				'label' => 'Campaign ID',
				'value' => $row->utm_id,
			);
		}

		if ( ! empty( $row->utm_source ) || ! empty( $row->utm_medium ) ) {
			$items[] = array(
				'label' => 'UTM source / medium',
				'value' => trim( ( $row->utm_source ?? '' ) . ' / ' . ( $row->utm_medium ?? '' ), ' /' ),
			);
		}

		if ( ! empty( $row->utm_term ) ) {
			$items[] = array(
				'label' => 'Keyword',
				'value' => $row->utm_term,
			);
		}

		if ( ! empty( $row->landing_referrer ) ) {
			$items[] = array(
				'label' => 'Referrer',
				'value' => $row->landing_referrer,
			);
		}

		return $items;
	}

	public static function render_product_block( $row ) {
		$title = $row->product_title ?? '';
		$url   = $row->product_url ?? '';
		$image = $row->product_image_url ?? '';
		?>
		<div class="adct-product-row">
			<?php if ( $image ) : ?>
				<img class="adct-thumb" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
			<?php else : ?>
				<div class="adct-thumb-empty">No image</div>
			<?php endif; ?>
			<div class="adct-product-main">
				<h3><?php echo esc_html( $title ); ?></h3>
				<?php if ( $url ) : ?>
					<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $url ); ?></a>
				<?php endif; ?>
				<div class="adct-product-meta">
					<?php if ( ! empty( $row->product_price ) ) : ?>
						<span><strong>Price:</strong> <?php echo esc_html( $row->product_price ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $row->product_mileage ) ) : ?>
						<span><strong>Mileage:</strong> <?php echo esc_html( $row->product_mileage ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	public static function render_inquiry_card( $row ) {
		$location = trim( implode( ', ', array_filter( array( $row->visitor_country ?? '', $row->visitor_region ?? '' ) ) ) );
		$badge    = self::contact_type_badge_class( $row->contact_type ?? '' );
		?>
		<article class="adct-inquiry-card">
			<div class="adct-card-header">
				<time datetime="<?php echo esc_attr( $row->clicked_at ); ?>"><?php echo esc_html( $row->clicked_at ); ?></time>
				<span class="adct-badge <?php echo esc_attr( $badge ); ?>"><?php echo esc_html( self::format_contact_type_label( $row->contact_type ?? '' ) ); ?></span>
			</div>
			<div class="adct-card-body">
				<?php self::render_product_block( $row ); ?>
				<div class="adct-meta-grid">
					<?php
					self::render_meta_box(
						'Contact',
						array(
							array(
								'label' => 'Salesman',
								'value' => $row->agent_name ?: '—',
							),
							array(
								'label' => 'Clicked',
								'value' => $row->clicked_value ?: '—',
							),
							array(
								'label' => 'Source',
								'value' => $row->source ?: '—',
							),
						)
					);
					self::render_meta_box(
						'Visitor',
						array(
							array(
								'label' => 'Device',
								'value' => self::format_device_type( $row->device_type ?? '' ),
							),
							array(
								'label' => 'Browser',
								'value' => $row->browser_name ?: '—',
							),
							array(
								'label' => 'Location',
								'value' => $location ?: '—',
							),
						)
					);
					if ( self::has_attribution_data( $row ) ) {
						self::render_meta_box( 'Marketing', self::build_marketing_items( $row ) );
					}
					?>
				</div>
			</div>
		</article>
		<?php
	}

	public static function render_session_click_item( $row ) {
		$badge = self::contact_type_badge_class( $row->contact_type ?? '' );
		?>
		<div class="adct-session-click">
			<div class="adct-session-click-header">
				<time datetime="<?php echo esc_attr( $row->clicked_at ); ?>"><?php echo esc_html( $row->clicked_at ); ?></time>
				<span class="adct-badge <?php echo esc_attr( $badge ); ?>"><?php echo esc_html( self::format_contact_type_label( $row->contact_type ?? '' ) ); ?></span>
			</div>
			<?php self::render_product_block( $row ); ?>
			<div class="adct-meta-grid">
				<?php
				self::render_meta_box(
					'Contact',
					array(
						array(
							'label' => 'Salesman',
							'value' => $row->agent_name ?: '—',
						),
						array(
							'label' => 'Clicked',
							'value' => $row->clicked_value ?: '—',
						),
						array(
							'label' => 'Page clicked',
							'value' => $row->product_url ?: '—',
							'url'   => $row->product_url ?: '',
						),
					)
				);
				?>
			</div>
		</div>
		<?php
	}

	public static function format_session_time( $datetime ) {
		if ( empty( $datetime ) ) {
			return '—';
		}

		$timestamp = strtotime( (string) $datetime );

		if ( ! $timestamp ) {
			return (string) $datetime;
		}

		return wp_date( 'M j, Y · g:i A', $timestamp );
	}

	public static function render_session_card( $session, array $clicks, $is_focused = false ) {
		$location     = trim( implode( ', ', array_filter( array( $session->visitor_country ?? '', $session->visitor_region ?? '' ) ) ) );
		$entry_source = sanitize_key( (string) ( $session->entry_source ?? '' ) );
		$source_class = $entry_source ? 'is-source-' . $entry_source : 'is-source-direct';
		$click_types  = array();

		foreach ( $clicks as $click ) {
			$type = $click->contact_type ?? '';

			if ( $type && ! in_array( $type, $click_types, true ) ) {
				$click_types[] = $type;
			}
		}
		?>
		<details class="adct-session-card <?php echo esc_attr( $source_class ); ?>" <?php echo $is_focused ? 'id="adct-session-focus" open' : ''; ?>>
			<summary>
				<span class="adct-session-chevron" aria-hidden="true">›</span>
				<div class="adct-session-summary-main">
					<span class="adct-session-time">
						<?php echo esc_html( self::format_session_time( $session->session_started ) ); ?>
						→
						<?php echo esc_html( self::format_session_time( $session->session_ended ) ); ?>
					</span>
					<div class="adct-session-meta-line">
						<?php if ( ! empty( $session->utm_campaign ) ) : ?>
							<span><span class="dashicons dashicons-megaphone"></span><?php echo esc_html( $session->utm_campaign ); ?></span>
						<?php endif; ?>
						<?php if ( ! empty( $session->landing_path ) ) : ?>
							<span><span class="dashicons dashicons-admin-links"></span><?php echo esc_html( $session->landing_path ); ?></span>
						<?php endif; ?>
						<?php if ( $location ) : ?>
							<span><span class="dashicons dashicons-location"></span><?php echo esc_html( $location ); ?></span>
						<?php endif; ?>
					</div>
					<?php if ( ! empty( $click_types ) ) : ?>
						<div class="adct-session-types">
							<?php foreach ( $click_types as $type ) : ?>
								<span class="adct-badge is-mini <?php echo esc_attr( self::contact_type_badge_class( $type ) ); ?>">
									<?php echo esc_html( self::format_contact_type_label( $type ) ); ?>
								</span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
				<div class="adct-session-pills">
					<span class="adct-session-pill is-clicks">
						<span class="dashicons dashicons-phone" style="font-size:14px;width:14px;height:14px;"></span>
						<?php echo esc_html( number_format_i18n( (int) $session->click_count ) ); ?> clicks
					</span>
					<span class="adct-session-pill is-duration">
						<span class="dashicons dashicons-clock" style="font-size:14px;width:14px;height:14px;"></span>
						<?php echo esc_html( self::format_duration( $session->session_duration ?? 0 ) ); ?>
					</span>
					<?php if ( ! empty( $session->entry_source ) ) : ?>
						<span class="adct-session-pill is-source <?php echo esc_attr( 'direct' === $entry_source ? 'is-direct' : '' ); ?>">
							<?php echo esc_html( self::format_entry_source( $session->entry_source ) ); ?>
						</span>
					<?php endif; ?>
				</div>
			</summary>
			<div class="adct-session-body">
				<?php
				$marketing_items = self::build_marketing_items( $session );
				if ( ! empty( $marketing_items ) ) {
					echo '<div class="adct-meta-grid">';
					self::render_meta_box(
						'Visitor',
						array(
							array(
								'label' => 'Device',
								'value' => self::format_device_type( $session->device_type ?? '' ),
							),
							array(
								'label' => 'Browser',
								'value' => $session->browser_name ?: '—',
							),
							array(
								'label' => 'Location',
								'value' => $location ?: '—',
							),
						)
					);
					self::render_meta_box( 'Marketing', $marketing_items );
					echo '</div>';
				}
				?>
				<div>
					<h3 class="adct-session-section-title">Clicks in this session</h3>
					<div class="adct-session-clicks">
						<?php foreach ( $clicks as $click ) : ?>
							<?php self::render_session_click_item( $click ); ?>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</details>
		<?php
	}

	public static function render_summary_card( $row ) {
		$badge = self::contact_type_badge_class( $row->contact_type ?? '' );
		?>
		<article class="adct-summary-card">
			<div class="adct-card-header">
				<strong><?php echo esc_html( $row->agent_name ?: 'No salesman' ); ?></strong>
				<span class="adct-badge <?php echo esc_attr( $badge ); ?>"><?php echo esc_html( self::format_contact_type_label( $row->contact_type ?? '' ) ); ?></span>
			</div>
			<div class="adct-card-body">
				<?php self::render_product_block( $row ); ?>
				<div class="adct-summary-stats">
					<span class="adct-summary-pill"><?php echo esc_html( number_format_i18n( (int) $row->clicks ) ); ?> clicks</span>
					<?php if ( ! empty( $row->last_clicked_at ) ) : ?>
						<span class="adct-summary-pill">Last: <?php echo esc_html( $row->last_clicked_at ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		</article>
		<?php
	}

	public static function format_relative_time( $datetime ) {
		if ( empty( $datetime ) ) {
			return '—';
		}

		$timestamp = strtotime( (string) $datetime );

		if ( ! $timestamp ) {
			return (string) $datetime;
		}

		return human_time_diff( $timestamp, current_time( 'timestamp' ) ) . ' ago';
	}

	public static function get_system_status() {
		$tracker_path = ADCT_PLUGIN_DIR . 'assets/tracker.js';
		$entry_path   = ADCT_PLUGIN_DIR . 'assets/entry-capture.js';

		return array(
			'woocommerce' => class_exists( 'WooCommerce' ),
			'tracker_js'  => file_exists( $tracker_path ),
			'entry_js'    => file_exists( $entry_path ),
			'wp_rocket'   => defined( 'WP_ROCKET_VERSION' ),
		);
	}

	public static function admin_scripts() {
		if ( ! self::is_admin_page() ) {
			return;
		}
		?>
		<script>
		(function () {
			var button = document.getElementById('adct-copy-utm');
			var toast = document.getElementById('adct-copy-toast');
			var source = document.getElementById('adct-utm-template');

			if (!button || !toast || !source) {
				return;
			}

			button.addEventListener('click', function () {
				var text = source.textContent || '';

				function showToast() {
					toast.classList.add('is-visible');
					window.setTimeout(function () {
						toast.classList.remove('is-visible');
					}, 2200);
				}

				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(text).then(showToast).catch(function () {
						window.prompt('Copy UTM suffix:', text);
					});
					return;
				}

				window.prompt('Copy UTM suffix:', text);
			});
		})();
		</script>
		<?php
	}

	public static function render_sidebar( array $snapshot, $export_url, $show_setup, array $version_info ) {
		$status         = self::get_system_status();
		$can_manage     = ADCT_Settings::user_can_manage();
		$allowed_roles  = ADCT_Settings::get_allowed_roles();
		$available_roles = ADCT_Settings::get_available_roles();
		$access_saved   = isset( $_GET['adct_access'] ) && 'saved' === $_GET['adct_access'];
		?>
		<aside class="adct-sidebar" aria-label="Plugin sidebar">
			<div class="adct-side-panel adct-plugin-brand">
				<h3>Tracking Template</h3>
				<p class="adct-plugin-desc">Tracks contact clicks with marketing attribution, groups them by visitor session, and reports everything in one admin dashboard.</p>
				<div class="adct-version-grid">
					<div class="adct-version-item">
						<span>Current</span>
						<strong>v<?php echo esc_html( $version_info['current'] ); ?></strong>
					</div>
					<div class="adct-version-item">
						<span>Latest</span>
						<strong>v<?php echo esc_html( $version_info['latest'] ); ?></strong>
					</div>
				</div>
				<div class="adct-side-links">
					<a href="<?php echo esc_url( self::GITHUB_REPO ); ?>" target="_blank" rel="noopener noreferrer">GitHub</a>
					<a href="<?php echo esc_url( self::GITHUB_REPO . '/releases' ); ?>" target="_blank" rel="noopener noreferrer">Changelog</a>
				</div>
				<p class="adct-author">Created by Benjamin Clar</p>
			</div>

			<?php if ( $can_manage ) : ?>
				<div class="adct-side-panel adct-update-panel">
					<h3>Plugin update</h3>
					<?php if ( ! empty( $version_info['has_update'] ) ) : ?>
						<span class="adct-update-badge is-available">Update available</span>
						<p class="adct-update-note">A new version is on GitHub. Update in one click — no zip download needed.</p>
						<?php if ( ! empty( $version_info['update_url'] ) ) : ?>
							<div class="adct-side-actions">
								<a class="button button-primary" href="<?php echo esc_url( $version_info['update_url'] ); ?>">Update to v<?php echo esc_html( $version_info['latest'] ); ?></a>
							</div>
						<?php endif; ?>
					<?php else : ?>
						<span class="adct-update-badge is-current">Up to date</span>
						<p class="adct-update-note">You are running the latest release from GitHub.</p>
					<?php endif; ?>
					<?php if ( ! empty( $version_info['error'] ) ) : ?>
						<p class="adct-update-note">Could not check GitHub: <?php echo esc_html( $version_info['error'] ); ?></p>
					<?php endif; ?>
					<p style="margin-top:10px;">
						<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'adct_check_update', '1', admin_url( 'admin.php?page=tracking-template' ) ), 'adct_check_update' ) ); ?>">Check for updates</a>
					</p>
				</div>

				<div class="adct-side-panel">
					<h3>Access control</h3>
					<?php if ( $access_saved ) : ?>
						<div class="adct-notice-inline">Access settings saved.</div>
					<?php endif; ?>
					<p class="adct-access-note">Administrators always have access. Only roles that currently have users on this site are listed below.</p>
					<?php if ( empty( $available_roles ) ) : ?>
						<p class="adct-access-note">No other active roles right now. When you add users (e.g. Shop manager), they will appear here.</p>
					<?php else : ?>
						<form method="post" action="">
							<?php wp_nonce_field( 'adct_save_access' ); ?>
							<input type="hidden" name="adct_save_access" value="1" />
							<ul class="adct-role-list">
								<?php foreach ( $available_roles as $slug => $label ) : ?>
									<li>
										<label>
											<input type="checkbox" name="adct_allowed_roles[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $allowed_roles, true ) ); ?> />
											<?php echo esc_html( $label ); ?>
										</label>
									</li>
								<?php endforeach; ?>
							</ul>
							<?php submit_button( 'Save access', 'secondary', 'submit', false ); ?>
						</form>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $show_setup ) : ?>
				<div class="adct-side-panel">
					<h3>Quick setup</h3>
					<ol class="adct-setup-steps">
						<li>Open a landing page with UTMs (e.g. your Google Ads test link).</li>
						<li>Click any contact button — WhatsApp, phone, Elfsight, or footer call.</li>
						<li>Refresh this page to see your first session.</li>
					</ol>
				</div>
			<?php endif; ?>

			<div class="adct-side-panel">
				<h3>Live snapshot</h3>
				<div class="adct-snapshot-grid">
					<div class="adct-snapshot-item">
						<span>Clicks today</span>
						<strong><?php echo esc_html( number_format_i18n( $snapshot['clicks_today'] ) ); ?></strong>
					</div>
					<div class="adct-snapshot-item">
						<span>Sessions (7d)</span>
						<strong><?php echo esc_html( number_format_i18n( $snapshot['sessions_week'] ) ); ?></strong>
					</div>
					<div class="adct-snapshot-item is-wide">
						<span>Top campaign (7d)</span>
						<em><?php echo esc_html( $snapshot['top_campaign'] ?: '—' ); ?></em>
					</div>
					<div class="adct-snapshot-item is-wide">
						<span>Top landing (7d)</span>
						<em><?php echo esc_html( $snapshot['top_landing'] ?: '—' ); ?></em>
					</div>
					<div class="adct-snapshot-item is-wide">
						<span>Last click</span>
						<em><?php echo esc_html( self::format_relative_time( $snapshot['last_click'] ) ); ?></em>
					</div>
				</div>
			</div>

			<div class="adct-side-panel">
				<h3>What&rsquo;s tracked</h3>
				<ul class="adct-feature-list">
					<li>WhatsApp, phone &amp; showroom on product pages</li>
					<li>Elfsight &amp; footer calls site-wide</li>
					<li>Google Ads UTMs &amp; full landing URL</li>
					<li>One session card per browser tab</li>
				</ul>
				<p class="adct-access-note" style="margin-top:10px;">Counts are contact click intents unless marked engaged in a future update. A click does not confirm a message was sent or a call was completed.</p>
			</div>

			<div class="adct-side-panel">
				<h3>Quick actions</h3>
				<p>Google Ads final URL suffix:</p>
				<div class="adct-utm-box" id="adct-utm-template"><?php echo esc_html( self::UTM_TEMPLATE ); ?></div>
				<div class="adct-side-actions">
					<button type="button" class="button button-secondary" id="adct-copy-utm">Copy UTM suffix</button>
					<span class="adct-copy-toast" id="adct-copy-toast">Copied to clipboard</span>
					<a class="button button-secondary" href="<?php echo esc_url( $export_url ); ?>">Export CSV</a>
					<a class="button button-link" href="<?php echo esc_url( self::GITHUB_REPO ); ?>" target="_blank" rel="noopener noreferrer">View on GitHub</a>
				</div>
			</div>

			<div class="adct-side-panel">
				<h3>System status</h3>
				<ul class="adct-status-list">
					<li>
						<span class="adct-status-dot <?php echo ADCT_License::is_active() ? 'is-ok' : 'is-warn'; ?>"></span>
						License <?php echo ADCT_License::is_active() ? 'active' : 'inactive'; ?>
					</li>
					<li>
						<span class="adct-status-dot is-ok"></span>
						Database v<?php echo esc_html( ADCT_Database::DB_VERSION ); ?>
					</li>
					<li>
						<span class="adct-status-dot <?php echo $status['woocommerce'] ? 'is-ok' : 'is-warn'; ?>"></span>
						WooCommerce <?php echo $status['woocommerce'] ? 'detected' : 'not detected'; ?>
					</li>
					<li>
						<span class="adct-status-dot <?php echo ( $status['tracker_js'] && $status['entry_js'] ) ? 'is-ok' : 'is-warn'; ?>"></span>
						Tracking scripts ready
					</li>
					<li>
						<span class="adct-status-dot <?php echo $status['wp_rocket'] ? 'is-warn' : 'is-ok'; ?>"></span>
						<?php echo $status['wp_rocket'] ? 'WP Rocket — clear cache after updates' : 'No WP Rocket detected'; ?>
					</li>
				</ul>
			</div>
		</aside>
		<?php
	}

	public static function register_menu() {
		$capability = ADCT_Settings::CAPABILITY;

		add_menu_page(
			'Tracking Template',
			'Tracking Template',
			$capability,
			'tracking-template',
			array( __CLASS__, 'render_overview_page' ),
			'dashicons-chart-bar',
			58
		);

		add_submenu_page(
			'tracking-template',
			'Overview',
			'Overview',
			$capability,
			'tracking-template',
			array( __CLASS__, 'render_overview_page' )
		);

		add_submenu_page(
			'tracking-template',
			'Leads',
			'Leads',
			$capability,
			'tracking-template-leads',
			array( __CLASS__, 'render_leads_page' )
		);

		add_submenu_page(
			'tracking-template',
			'Sessions',
			'Sessions',
			$capability,
			'tracking-template-sessions',
			array( __CLASS__, 'render_reports_page' )
		);

		add_submenu_page(
			'tracking-template',
			'License',
			'License',
			'manage_options',
			'tracking-template-license',
			array( __CLASS__, 'render_license_page' )
		);
	}

	public static function render_locked_page() {
		$can_manage  = ADCT_Settings::user_can_manage();
		$summary     = ADCT_License::get_status_summary();
		$license_url = admin_url( 'admin.php?page=tracking-template-license' );
		$page        = self::get_page_context();
		?>
		<div class="wrap adct-wrap">
			<div class="adct-layout">
				<div class="adct-layout-header">
					<h1>Tracking Template</h1>
					<p class="adct-page-intro">Reporting is locked until a valid license is active.</p>
				</div>
				<div class="adct-main adct-license-locked">
					<div class="adct-license-locked-content" aria-hidden="true">
						<div class="adct-panel" style="min-height:280px;background:linear-gradient(135deg,#f6f8fa,#eceff3);border-radius:12px;border:1px solid #dce1e8;"></div>
					</div>
					<div class="adct-license-glass">
						<div class="adct-license-card">
							<span class="adct-license-status-badge is-error"><?php echo esc_html( $summary['label'] ); ?></span>
							<h2>License required</h2>
							<?php if ( $can_manage ) : ?>
								<p><?php echo esc_html( $summary['message'] ?: 'Enter your license key to unlock tracking and reporting.' ); ?></p>
								<a class="button button-primary" href="<?php echo esc_url( $license_url ); ?>">Activate license</a>
							<?php else : ?>
								<p>Contact a site administrator to activate the Tracking Template license.</p>
							<?php endif; ?>
						</div>
					</div>
				</div>
				<?php self::render_sidebar( $page['snapshot'], '#', false, $page['version_info'] ); ?>
			</div>
		</div>
		<?php
	}

	public static function render_license_page() {
		if ( ! ADCT_Settings::user_can_manage() ) {
			return;
		}

		$summary     = ADCT_License::get_status_summary();
		$saved_key   = ADCT_License::get_key();
		$site_host   = ADCT_License::get_site_host();
		$checked_at  = get_option( ADCT_License::OPTION_CHECKED_AT, '' );
		$last_valid  = get_option( ADCT_License::OPTION_LAST_VALID_AT, '' );
		$is_active   = ! empty( $summary['active'] ) && 'active' === $summary['status'];
		$show_form   = ! $is_active || ! empty( $_GET['adct_change_license'] );
		$badge_class = ! empty( $summary['active'] ) ? 'is-active' : ( in_array( $summary['status'], array( 'grace_install', 'grace_remote' ), true ) ? 'is-warn' : 'is-error' );
		?>
		<div class="wrap adct-wrap adct-license-page">
			<div class="adct-layout">
				<div class="adct-layout-header">
					<h1>License</h1>
					<p class="adct-page-intro">Activate Tracking Template with your license key. Tracking and reporting stay off when the license is inactive or revoked.</p>
				</div>
				<div class="adct-main">
					<div class="adct-panel adct-license-panel">
						<span class="adct-license-status-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $summary['label'] ); ?></span>

						<?php if ( $is_active && ! $show_form ) : ?>
							<div class="adct-license-activated">
								<p style="margin:0 0 8px;"><strong>License activated</strong></p>
								<span class="adct-license-activated-key"><?php echo esc_html( ADCT_License::mask_key( $saved_key ) ); ?></span>
								<p class="description" style="margin:10px 0 0;">Licensed to this site: <code><?php echo esc_html( $site_host ); ?></code></p>
								<div class="adct-license-actions">
									<a class="button" href="<?php echo esc_url( add_query_arg( 'adct_change_license', '1' ) ); ?>">Change license key</a>
									<form method="post" action="" style="display:inline;">
										<?php wp_nonce_field( 'adct_deactivate_license' ); ?>
										<input type="hidden" name="adct_deactivate_license" value="1" />
										<?php submit_button( 'Deactivate', 'delete', 'submit', false, array( 'onclick' => "return confirm('Deactivate license on this site? Tracking will stop.');" ) ); ?>
									</form>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( $show_form ) : ?>
							<?php if ( $is_active ) : ?>
								<p style="margin:0 0 12px;"><a href="<?php echo esc_url( remove_query_arg( 'adct_change_license' ) ); ?>">&larr; Back</a></p>
							<?php endif; ?>
							<form method="post" action="" class="<?php echo $is_active ? 'adct-license-change-form' : ''; ?>">
								<?php wp_nonce_field( 'adct_activate_license' ); ?>
								<input type="hidden" name="adct_activate_license" value="1" />
								<p>
									<label for="adct-license-key"><strong><?php echo $is_active ? 'New license key' : 'License key'; ?></strong></label>
								</p>
								<p>
									<input
										type="text"
										class="regular-text adct-license-key-field"
										id="adct-license-key"
										name="adct_license_key"
										value=""
										placeholder="ADCT-XXXX-XXXX-XXXX"
										autocomplete="off"
									/>
								</p>
								<p class="description">Licensed to this site: <code><?php echo esc_html( $site_host ); ?></code></p>
								<?php submit_button( $is_active ? 'Update license' : 'Activate license', 'primary', 'submit', false ); ?>
							</form>
						<?php endif; ?>

						<div class="adct-license-meta">
							<div class="adct-license-meta-item">
								<span>Status</span>
								<strong><?php echo esc_html( $summary['label'] ); ?></strong>
							</div>
							<div class="adct-license-meta-item">
								<span>Plan</span>
								<strong><?php echo esc_html( $summary['plan'] ? ucfirst( $summary['plan'] ) : '—' ); ?></strong>
							</div>
							<div class="adct-license-meta-item">
								<span>Expires</span>
								<strong><?php echo esc_html( $summary['expires'] ?: '—' ); ?></strong>
							</div>
							<div class="adct-license-meta-item">
								<span>Last checked</span>
								<strong><?php echo esc_html( $checked_at ? self::format_relative_time( $checked_at ) : '—' ); ?></strong>
							</div>
							<div class="adct-license-meta-item">
								<span>Last valid</span>
								<strong><?php echo esc_html( $last_valid ? self::format_relative_time( $last_valid ) : '—' ); ?></strong>
							</div>
							<div class="adct-license-meta-item">
								<span>Tracking</span>
								<strong><?php echo ADCT_License::is_active() ? 'Active' : 'Stopped'; ?></strong>
							</div>
						</div>
						<?php if ( ! empty( $summary['message'] ) && ( ! $is_active || $show_form ) ) : ?>
							<p class="adct-access-note" style="margin-top:16px;"><?php echo esc_html( $summary['message'] ); ?></p>
						<?php elseif ( $is_active && ! $show_form ) : ?>
							<p class="adct-access-note" style="margin-top:16px;">License is active. Tracking validates daily against the license server.</p>
						<?php endif; ?>
						<p class="adct-access-note" style="margin-top:16px;">
							Need a license? <a href="<?php echo esc_url( ADCT_License::PURCHASE_URL ); ?>" target="_blank" rel="noopener noreferrer">Contact Benjamin Clar</a>.
							Developers can set <code>ADCT_LICENSE_BYPASS</code> in <code>wp-config.php</code> for local testing.
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	private static function get_page_context() {
		$snapshot     = ADCT_Database::get_live_snapshot();
		$version_info = ADCT_Updater::get_version_info();
		$show_setup   = (int) $snapshot['total_all_time'] === 0;

		if ( ADCT_Settings::user_can_manage() && ! empty( $_GET['adct_check_update'] ) ) {
			check_admin_referer( 'adct_check_update' );
			delete_transient( ADCT_Updater::CACHE_KEY );
			$version_info = ADCT_Updater::get_version_info( true );
		}

		return array(
			'snapshot'     => $snapshot,
			'version_info' => $version_info,
			'show_setup'   => $show_setup,
		);
	}

	public static function render_overview_page() {
		if ( ! ADCT_Settings::user_can_view() ) {
			return;
		}

		if ( ! ADCT_License::is_active() ) {
			self::render_locked_page();
			return;
		}

		$period      = ADCT_Analytics::get_period_from_request();
		$overview    = ADCT_Analytics::get_overview_data( $period );
		$page        = self::get_page_context();
		$snapshot    = $page['snapshot'];
		$version_info = $page['version_info'];
		$show_setup  = $page['show_setup'];
		$sessions_url = admin_url( 'admin.php?page=tracking-template-sessions' );
		$leads_url    = admin_url( 'admin.php?page=tracking-template-leads' );
		$has_data    = ! empty( $overview['totals']['clicks'] );
		$has_marketing = ! empty( $overview['source_breakdown'] ) || ! empty( $overview['campaign_breakdown'] ) || ! empty( $overview['landing_breakdown'] );
		$top_kpi_label = 'Top campaign';
		$top_kpi_value = $overview['totals']['top_campaign'];

		if ( '—' === $top_kpi_value ) {
			$top_kpi_label = 'Top source';
			$top_kpi_value = $overview['totals']['top_source'];
		}

		wp_localize_script(
			'adct-overview',
			'adctOverviewConfig',
			array(
				'contactLabels'   => $overview['charts']['contact_labels'],
				'contactCounts'   => $overview['charts']['contact_counts'],
				'contactColors'   => $overview['charts']['contact_colors'],
				'sourceLabels'    => $overview['charts']['source_labels'],
				'sourceCounts'    => $overview['charts']['source_counts'],
				'sourceColors'    => $overview['charts']['source_colors'],
				'campaignLabels'  => $overview['charts']['campaign_labels'],
				'campaignCounts'  => $overview['charts']['campaign_counts'],
				'campaignColors'  => $overview['charts']['campaign_colors'],
				'dayLabels'       => $overview['charts']['labels'],
				'clickSeries'     => $overview['charts']['clicks'],
				'sessionSeries'   => $overview['charts']['sessions'],
			)
		);
		?>
		<div class="wrap adct-wrap">
			<div class="adct-layout">
				<header class="adct-layout-header">
					<h1>Overview</h1>
					<p class="adct-page-intro">A visual summary of contact clicks and visitor sessions. See which click types and marketing campaigns drive the most inquiries, and how activity trends over time.</p>
				</header>

				<div class="adct-main">
					<div class="adct-overview-toolbar">
						<p>Showing results for <?php echo esc_html( $overview['period']['label'] ); ?>.</p>
						<form method="get" class="adct-period-form">
							<input type="hidden" name="page" value="tracking-template" />
							<label>
								Period
								<select name="period" onchange="this.form.submit()">
									<?php foreach ( ADCT_Analytics::PERIOD_OPTIONS as $option ) : ?>
										<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $period, $option ); ?>>
											<?php
											echo esc_html(
												sprintf(
													/* translators: %d: number of days */
													_n( 'Last %d day', 'Last %d days', $option, 'tracking-template' ),
													$option
												)
											);
											?>
										</option>
									<?php endforeach; ?>
								</select>
							</label>
						</form>
					</div>

					<section class="adct-overview-card">
						<div class="adct-overview-card-head">
							<h2>Clicks summary</h2>
							<a href="<?php echo esc_url( $sessions_url ); ?>">View sessions</a>
						</div>

						<?php if ( ! $has_data ) : ?>
							<div class="adct-empty">No contact clicks recorded in this period yet. Once visitors start clicking WhatsApp, phone, or other contact buttons, this summary will populate automatically.</div>
						<?php else : ?>
							<div class="adct-summary-grid">
								<div class="adct-chart-shell">
									<canvas id="adct-summary-chart" aria-label="Contact clicks by type"></canvas>
								</div>
								<ul class="adct-legend-list">
									<?php foreach ( $overview['contact_breakdown'] as $item ) : ?>
										<li class="adct-legend-item">
											<a class="adct-legend-link" href="<?php echo esc_url( $item['filter_url'] ); ?>">
												<span class="adct-legend-swatch" style="background: <?php echo esc_attr( $item['color'] ); ?>;"></span>
												<span class="adct-legend-label"><?php echo esc_html( $item['label'] ); ?></span>
												<span class="adct-legend-percent"><?php echo esc_html( $item['percent'] ); ?></span>
												<span class="adct-legend-count"><?php echo esc_html( number_format_i18n( $item['count'] ) ); ?></span>
												<span class="adct-legend-chevron" aria-hidden="true">›</span>
											</a>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>
					</section>

					<section class="adct-overview-card">
						<div class="adct-panel-tabs" role="tablist" aria-label="Performance views">
							<button type="button" class="adct-panel-tab is-active" data-adct-panel-tab="performance">Click performance</button>
							<button type="button" class="adct-panel-tab" data-adct-panel-tab="marketing">Marketing</button>
						</div>

						<div class="adct-panel-section is-active" data-adct-panel="performance">
							<div class="adct-metric-grid">
								<button type="button" class="adct-metric-tile is-active" data-adct-metric="clicks">
									<span>Contact clicks</span>
									<strong><?php echo esc_html( number_format_i18n( $overview['totals']['clicks'] ) ); ?></strong>
								</button>
								<button type="button" class="adct-metric-tile" data-adct-metric="sessions">
									<span>Visitor sessions</span>
									<strong><?php echo esc_html( number_format_i18n( $overview['totals']['sessions'] ) ); ?></strong>
								</button>
								<div class="adct-metric-tile is-static">
									<span><?php echo esc_html( $top_kpi_label ); ?></span>
									<strong><?php echo esc_html( $top_kpi_value ); ?></strong>
								</div>
							</div>

							<?php if ( $has_data ) : ?>
								<div class="adct-chart-shell is-wide">
									<canvas id="adct-trend-chart" aria-label="Daily performance trend"></canvas>
								</div>

								<div class="adct-rate-grid">
									<div class="adct-rate-tile">
										<span>WhatsApp share</span>
										<strong><?php echo esc_html( $overview['totals']['whatsapp_rate'] ); ?></strong>
									</div>
									<div class="adct-rate-tile">
										<span>Google Ads sessions</span>
										<strong><?php echo esc_html( $overview['totals']['paid_session_rate'] ); ?></strong>
									</div>
								</div>

								<div class="adct-insights-grid">
									<div class="adct-insight-panel is-cars">
										<h3>Top cars</h3>
										<?php self::render_overview_rank_list( $overview['top_products'], 'product_title', 'total' ); ?>
									</div>
									<div class="adct-insight-panel is-agents">
										<h3>Top salesmen</h3>
										<?php self::render_overview_rank_list( $overview['top_agents'], 'agent_name', 'total' ); ?>
									</div>
									<div class="adct-insight-panel is-devices">
										<h3>Devices</h3>
										<?php if ( ! empty( $overview['device_breakdown'] ) ) : ?>
											<ul class="adct-insight-list">
												<?php foreach ( $overview['device_breakdown'] as $item ) : ?>
													<li class="adct-insight-item adct-insight-device">
														<span class="adct-insight-swatch" style="background: <?php echo esc_attr( $item['color'] ); ?>;"></span>
														<span class="adct-insight-label"><?php echo esc_html( $item['label'] ); ?></span>
														<span class="adct-insight-count"><?php echo esc_html( number_format_i18n( $item['count'] ) ); ?> · <?php echo esc_html( $item['percent'] ); ?></span>
													</li>
												<?php endforeach; ?>
											</ul>
										<?php else : ?>
											<p class="adct-insight-empty">No device data yet.</p>
										<?php endif; ?>
									</div>
								</div>

								<div class="adct-traffic-section">
									<h3>Where traffic comes from</h3>
									<p class="adct-traffic-note">Attributed contact clicks grouped by channel. Count and share of all clicks with a known source in this period.</p>
									<?php self::render_overview_traffic_channels( $overview['traffic_channels'] ); ?>
								</div>

								<div class="adct-recent-leads">
									<div class="adct-recent-leads-head">
										<h3>Recent contact intents</h3>
										<a href="<?php echo esc_url( $leads_url ); ?>">View all intents</a>
									</div>
									<?php if ( ! empty( $overview['recent_leads'] ) ) : ?>
										<ul class="adct-recent-leads-list">
											<?php foreach ( $overview['recent_leads'] as $lead ) : ?>
												<li class="adct-recent-lead-item">
													<span class="adct-recent-lead-date"><?php echo esc_html( ADCT_Leads::format_lead_datetime( $lead->clicked_at ?? '' ) ); ?></span>
													<div class="adct-recent-lead-main">
														<strong><?php echo esc_html( $lead->product_title ?: 'Site-wide contact' ); ?></strong>
														<span>
															<?php
															echo esc_html(
																trim(
																	implode(
																		' · ',
																		array_filter(
																			array(
																				ADCT_Leads::get_lead_status_label( $lead->contact_type ?? '' ),
																				! empty( $lead->entry_source ) ? self::format_entry_source( $lead->entry_source ) : '',
																				ADCT_Leads::is_salesman_attributed_click( $lead->contact_type ?? '', $lead->agent_name ?? '' ) ? $lead->agent_name : '',
																			)
																		)
																	)
																)
															);
															?>
														</span>
													</div>
													<span class="adct-badge <?php echo esc_attr( self::contact_type_badge_class( $lead->contact_type ?? '' ) ); ?>">
														<?php echo esc_html( self::format_contact_type_label( $lead->contact_type ?? '' ) ); ?>
													</span>
												</li>
											<?php endforeach; ?>
										</ul>
									<?php else : ?>
										<p class="adct-insight-empty">No contact intents in this period yet.</p>
									<?php endif; ?>
								</div>
							<?php else : ?>
								<div class="adct-empty">Performance trends will appear here once clicks are recorded.</div>
							<?php endif; ?>
						</div>

						<div class="adct-panel-section" data-adct-panel="marketing">
							<p class="adct-overview-note">Only clicks with marketing data are shown here — Google Ads, campaigns, landing pages, and traffic sources you can act on.</p>

							<?php if ( ! $has_marketing ) : ?>
								<div class="adct-empty">No marketing data for this period yet. Once visitors arrive from Google Ads or tagged campaigns and click a contact button, results will appear here.</div>
							<?php else : ?>
								<?php if ( ! empty( $overview['source_breakdown'] ) ) : ?>
									<div class="adct-marketing-section">
										<h3>Traffic sources</h3>
										<div class="adct-summary-grid">
											<div class="adct-chart-shell">
												<canvas id="adct-sources-chart" aria-label="Traffic sources"></canvas>
											</div>
											<?php self::render_overview_legend( $overview['source_breakdown'] ); ?>
										</div>
									</div>
								<?php endif; ?>

								<?php if ( ! empty( $overview['campaign_breakdown'] ) ) : ?>
									<div class="adct-marketing-section">
										<h3>Top campaigns</h3>
										<div class="adct-summary-grid">
											<div class="adct-chart-shell">
												<canvas id="adct-campaigns-chart" aria-label="Top campaigns"></canvas>
											</div>
											<?php self::render_overview_legend( $overview['campaign_breakdown'] ); ?>
										</div>
									</div>
								<?php endif; ?>

								<?php if ( ! empty( $overview['landing_breakdown'] ) ) : ?>
									<div class="adct-marketing-section">
										<h3>Top landing pages</h3>
										<?php self::render_overview_legend( $overview['landing_breakdown'] ); ?>
									</div>
								<?php endif; ?>
							<?php endif; ?>
						</div>
					</section>
				</div>

				<?php self::render_sidebar( $snapshot, '', $show_setup, $version_info ); ?>
			</div>
		</div>
		<?php
	}

	public static function render_overview_rank_list( $rows, $label_key, $count_key ) {
		if ( empty( $rows ) ) {
			echo '<p class="adct-insight-empty">No data yet.</p>';
			return;
		}
		?>
		<ul class="adct-insight-list">
			<?php foreach ( $rows as $index => $row ) : ?>
				<?php
				$rank_class = '';

				if ( $index < 3 ) {
					$rank_class = ' is-rank-' . ( $index + 1 );
				}
				?>
				<li class="adct-insight-item<?php echo esc_attr( $rank_class ); ?>">
					<span class="adct-insight-label"><?php echo esc_html( $row->{$label_key} ?? '' ); ?></span>
					<span class="adct-insight-count"><?php echo esc_html( number_format_i18n( (int) ( $row->{$count_key} ?? 0 ) ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	public static function render_overview_traffic_channels( array $traffic_channels ) {
		$items = $traffic_channels['items'] ?? array();

		if ( empty( $traffic_channels['total_attributed'] ) ) {
			echo '<p class="adct-insight-empty">No attributed traffic yet. Once visitors arrive from Google, ads, or referrals and click contact, channels will appear here.</p>';
			return;
		}
		?>
		<div class="adct-traffic-grid">
			<?php foreach ( $items as $item ) : ?>
				<div class="adct-traffic-tile is-channel-<?php echo esc_attr( $item['key'] ); ?>">
					<div class="adct-traffic-tile-head">
						<span class="adct-traffic-swatch" style="background: <?php echo esc_attr( $item['color'] ); ?>;"></span>
						<span><?php echo esc_html( $item['label'] ); ?></span>
					</div>
					<div class="adct-traffic-stats">
						<strong><?php echo esc_html( number_format_i18n( $item['count'] ) ); ?></strong>
						<em><?php echo esc_html( $item['percent'] ); ?></em>
					</div>
					<div class="adct-traffic-bar" aria-hidden="true">
						<span style="width: <?php echo esc_attr( min( 100, max( 0, (float) $item['percent_raw'] ) ) ); ?>%; background: <?php echo esc_attr( $item['color'] ); ?>;"></span>
					</div>
					<?php if ( ! empty( $item['filter_url'] ) && 'referrers' !== $item['key'] ) : ?>
						<a class="adct-traffic-link" href="<?php echo esc_url( $item['filter_url'] ); ?>">View intents →</a>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	public static function render_overview_legend( array $items ) {
		?>
		<ul class="adct-legend-list">
			<?php foreach ( $items as $item ) : ?>
				<li class="adct-legend-item">
					<?php if ( empty( $item['filter_url'] ) ) : ?>
						<div class="adct-legend-link">
							<span class="adct-legend-swatch" style="background: <?php echo esc_attr( $item['color'] ); ?>;"></span>
							<span class="adct-legend-label"><?php echo esc_html( $item['label'] ); ?></span>
							<span class="adct-legend-percent"><?php echo esc_html( $item['percent'] ); ?></span>
							<span class="adct-legend-count"><?php echo esc_html( number_format_i18n( $item['count'] ) ); ?></span>
							<span class="adct-legend-chevron" aria-hidden="true">›</span>
						</div>
					<?php else : ?>
						<a class="adct-legend-link" href="<?php echo esc_url( $item['filter_url'] ); ?>">
							<span class="adct-legend-swatch" style="background: <?php echo esc_attr( $item['color'] ); ?>;"></span>
							<span class="adct-legend-label"><?php echo esc_html( $item['label'] ); ?></span>
							<span class="adct-legend-percent"><?php echo esc_html( $item['percent'] ); ?></span>
							<span class="adct-legend-count"><?php echo esc_html( number_format_i18n( $item['count'] ) ); ?></span>
							<span class="adct-legend-chevron" aria-hidden="true">›</span>
						</a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	public static function render_leads_page() {
		if ( ! ADCT_Settings::user_can_view() ) {
			return;
		}

		if ( ! ADCT_License::is_active() ) {
			self::render_locked_page();
			return;
		}

		$channel      = ADCT_Leads::get_channel_from_request();
		$filters      = ADCT_Database::get_filters_from_request();
		$tab_filters  = $filters;
		$filters      = ADCT_Leads::apply_channel_filter( $filters, $channel );
		$pagination   = ADCT_Database::get_pagination_args();
		$per_page     = $pagination['per_page'];
		$paged        = $pagination['paged'];
		$offset       = $pagination['offset'];
		$list_total   = ADCT_Database::count_clicks( $filters );
		$total_pages  = max( 1, (int) ceil( $list_total / $per_page ) );

		if ( $paged > $total_pages ) {
			$paged  = $total_pages;
			$offset = ( $paged - 1 ) * $per_page;
		}

		$lead_rows      = ADCT_Database::get_clicks( $filters, $per_page, $offset );
		$session_context = ADCT_Database::get_lead_session_context( $lead_rows );
		$channel_counts = ADCT_Leads::get_channel_counts( $tab_filters );
		$agents         = ADCT_Database::get_distinct_values( 'agent_name' );
		$entry_sources  = ADCT_Database::get_distinct_values( 'entry_source' );
		$campaigns      = ADCT_Database::get_distinct_values( 'utm_campaign' );
		$page_context   = self::get_page_context();
		$snapshot       = $page_context['snapshot'];
		$version_info   = $page_context['version_info'];
		$show_setup     = $page_context['show_setup'];
		$base_url       = admin_url( 'admin.php?page=tracking-template-leads' );
		$query_args     = array_merge(
			$tab_filters,
			array(
				'page'         => 'tracking-template-leads',
				'lead_channel' => $channel,
				'per_page'     => $per_page,
			)
		);
		$tab_query      = array_merge(
			$tab_filters,
			array(
				'page'     => 'tracking-template-leads',
				'per_page' => $per_page,
			)
		);
		$from_item = $list_total ? ( $offset + 1 ) : 0;
		$to_item   = min( $offset + $per_page, $list_total );
		$export_url = wp_nonce_url(
			add_query_arg(
				array_merge(
					$_GET,
					array(
						'adct_export' => 'csv',
						'page'        => 'tracking-template-leads',
					)
				),
				$base_url
			),
			'adct_export_csv'
		);
		?>
		<div class="wrap adct-wrap">
			<div class="adct-layout">
				<header class="adct-layout-header">
					<h1>Leads</h1>
					<p class="adct-page-intro">Every contact button click listed here as a contact intent — WhatsApp, phone, showroom, and widget calls. These are clicks to enquire, not confirmed messages or completed calls. Filter by channel, campaign, or source, or open Sessions for the full visitor journey.</p>
				</header>

				<div class="adct-main">
					<div class="adct-leads-tabs" aria-label="Lead channels">
						<a class="adct-leads-tab <?php echo 'all' === $channel ? 'is-active' : ''; ?>" href="<?php echo esc_url( ADCT_Leads::build_channel_tab_url( 'all', $tab_query ) ); ?>">
							<span>All</span>
							<span class="adct-leads-tab-count"><?php echo esc_html( number_format_i18n( $channel_counts['all'] ) ); ?></span>
						</a>
						<a class="adct-leads-tab <?php echo 'phone' === $channel ? 'is-active' : ''; ?>" href="<?php echo esc_url( ADCT_Leads::build_channel_tab_url( 'phone', $tab_query ) ); ?>">
							<span>Phone</span>
							<span class="adct-leads-tab-count"><?php echo esc_html( number_format_i18n( $channel_counts['phone'] ) ); ?></span>
						</a>
						<a class="adct-leads-tab <?php echo 'whatsapp' === $channel ? 'is-active' : ''; ?>" href="<?php echo esc_url( ADCT_Leads::build_channel_tab_url( 'whatsapp', $tab_query ) ); ?>">
							<span>WhatsApp</span>
							<span class="adct-leads-tab-count"><?php echo esc_html( number_format_i18n( $channel_counts['whatsapp'] ) ); ?></span>
						</a>
					</div>

					<div class="adct-intent-note">
						<strong>Contact click intents.</strong>
						Counts here are button clicks only — someone tapped WhatsApp or phone. That does not confirm a message was sent or a call was completed.
						A <strong>Likely engaged</strong> filter is planned for a future update to separate stronger signals from quick bounces.
					</div>

					<form method="get" class="adct-filters">
						<input type="hidden" name="page" value="tracking-template-leads" />
						<input type="hidden" name="lead_channel" value="<?php echo esc_attr( $channel ); ?>" />

						<label>
							From
							<input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" />
						</label>

						<label>
							To
							<input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>" />
						</label>

						<label>
							Salesman
							<select name="agent_name">
								<option value="">All</option>
								<?php foreach ( $agents as $agent ) : ?>
									<option value="<?php echo esc_attr( $agent ); ?>" <?php selected( $filters['agent_name'], $agent ); ?>>
										<?php echo esc_html( $agent ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</label>

						<label>
							Source
							<select name="entry_source">
								<option value="">All</option>
								<?php foreach ( $entry_sources as $entry_source ) : ?>
									<option value="<?php echo esc_attr( $entry_source ); ?>" <?php selected( $filters['entry_source'] ?? '', $entry_source ); ?>>
										<?php echo esc_html( self::format_entry_source( $entry_source ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</label>

						<label>
							Campaign
							<select name="utm_campaign">
								<option value="">All</option>
								<?php foreach ( $campaigns as $campaign ) : ?>
									<option value="<?php echo esc_attr( $campaign ); ?>" <?php selected( $filters['utm_campaign'] ?? '', $campaign ); ?>>
										<?php echo esc_html( $campaign ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</label>

						<label>
							Rows per page
							<select name="per_page">
								<?php foreach ( ADCT_Database::PER_PAGE_OPTIONS as $option ) : ?>
									<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $per_page, $option ); ?>>
										<?php echo esc_html( number_format_i18n( $option ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</label>

						<label>
							Search
							<input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="Car, salesman, campaign..." />
						</label>

						<?php submit_button( 'Filter', 'secondary', '', false ); ?>
						<a class="button" href="<?php echo esc_url( add_query_arg( 'lead_channel', $channel, $base_url ) ); ?>">Reset</a>
						<a class="button" href="<?php echo esc_url( $export_url ); ?>">Export CSV</a>
					</form>

					<div class="adct-table-toolbar">
						<p>
							<?php if ( $list_total ) : ?>
								Showing <?php echo esc_html( number_format_i18n( $from_item ) ); ?>–<?php echo esc_html( number_format_i18n( $to_item ) ); ?>
								of <?php echo esc_html( number_format_i18n( $list_total ) ); ?> contact intents.
							<?php else : ?>
								No contact intents to display for the current filters.
							<?php endif; ?>
						</p>
					</div>

					<?php if ( empty( $lead_rows ) ) : ?>
						<div class="adct-empty">No contact intents recorded yet. When visitors click WhatsApp, phone, or other contact buttons, each click will appear here.</div>
					<?php else : ?>
						<div class="adct-leads-table-wrap">
							<table class="adct-leads-table">
								<thead>
									<tr>
										<th scope="col">Date</th>
										<th scope="col">Session</th>
										<th scope="col">Status</th>
										<th scope="col">Enquiry about</th>
										<th scope="col">Source</th>
										<th scope="col">Campaign</th>
										<th scope="col">Salesman</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $lead_rows as $row ) : ?>
										<?php self::render_lead_row( $row, $session_context ); ?>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>

					<?php self::render_pagination( $list_total, $per_page, $paged, $query_args ); ?>
				</div>

				<?php self::render_sidebar( $snapshot, $export_url, $show_setup, $version_info ); ?>
			</div>
		</div>
		<?php
	}

	public static function render_lead_row( $row, array $session_context = array() ) {
		$badge       = self::contact_type_badge_class( $row->contact_type ?? '' );
		$status      = ADCT_Leads::get_lead_status_label( $row->contact_type ?? '' );
		$has_product = ! empty( $row->product_title ) || ! empty( $row->product_id );
		$date_parts  = ADCT_Leads::format_lead_datetime_parts( $row->clicked_at ?? '' );
		$source_text = ! empty( $row->entry_source ) ? self::format_entry_source( $row->entry_source ) : '—';
		$source_hint = self::format_landing_path_short( $row->landing_path ?? '' );
		?>
		<tr>
			<td>
				<div class="adct-lead-date" title="<?php echo esc_attr( $row->clicked_at ?? '' ); ?>">
					<span class="adct-lead-date-day"><?php echo esc_html( $date_parts['date'] ?? '—' ); ?></span>
					<?php if ( ! empty( $date_parts['time'] ) ) : ?>
						<span class="adct-lead-date-time"><?php echo esc_html( $date_parts['time'] ); ?></span>
					<?php endif; ?>
				</div>
			</td>
			<td>
				<?php self::render_lead_session_cell( $row, $session_context ); ?>
			</td>
			<td>
				<span class="adct-badge <?php echo esc_attr( $badge ); ?>"><?php echo esc_html( $status ); ?></span>
				<div class="adct-lead-meta" style="margin-top:8px;">
					<span><?php echo esc_html( self::format_contact_type_label( $row->contact_type ?? '' ) ); ?></span>
				</div>
			</td>
			<td>
				<?php if ( $has_product ) : ?>
					<div class="adct-lead-product">
						<?php if ( ! empty( $row->product_image_url ) ) : ?>
							<img class="adct-thumb" src="<?php echo esc_url( $row->product_image_url ); ?>" alt="<?php echo esc_attr( $row->product_title ?? '' ); ?>" loading="lazy" />
						<?php else : ?>
							<div class="adct-thumb-empty">No image</div>
						<?php endif; ?>
						<div>
							<h4><?php echo esc_html( $row->product_title ?: 'Site-wide contact' ); ?></h4>
							<?php if ( ! empty( $row->product_price ) || ! empty( $row->product_mileage ) ) : ?>
								<p>
									<?php
									echo esc_html(
										trim(
											implode(
												' · ',
												array_filter(
													array(
														$row->product_price ?? '',
														$row->product_mileage ?? '',
													)
												)
											)
										)
									);
									?>
								</p>
							<?php endif; ?>
							<?php if ( ! empty( $row->product_url ) ) : ?>
								<p><a href="<?php echo esc_url( $row->product_url ); ?>" target="_blank" rel="noopener noreferrer">View page</a></p>
							<?php endif; ?>
						</div>
					</div>
				<?php else : ?>
					<span class="adct-lead-empty-product">Site-wide contact</span>
				<?php endif; ?>
			</td>
			<td>
				<div class="adct-lead-meta">
					<strong><?php echo esc_html( $source_text ); ?></strong>
					<?php if ( '' !== $source_hint ) : ?>
						<span title="<?php echo esc_attr( $row->landing_path ?? '' ); ?>"><?php echo esc_html( $source_hint ); ?></span>
					<?php endif; ?>
				</div>
			</td>
			<td><?php echo esc_html( $row->utm_campaign ?: '—' ); ?></td>
			<td><?php echo esc_html( ADCT_Leads::format_salesman_name( $row->contact_type ?? '', $row->agent_name ?? '' ) ); ?></td>
		</tr>
		<?php
	}

	public static function render_lead_session_cell( $row, array $session_context = array() ) {
		$session_key = ADCT_Leads::get_session_key_for_row( $row );
		$summaries   = $session_context['summaries'] ?? array();
		$positions   = $session_context['positions'] ?? array();
		$row_id      = absint( $row->id ?? 0 );
		$position    = $positions[ $row_id ] ?? null;
		$summary     = ( ! empty( $session_key ) && isset( $summaries[ $session_key ] ) ) ? $summaries[ $session_key ] : null;
		$session_url = ADCT_Leads::build_session_view_url( $session_key );

		if ( '' === $session_key || ! $session_url ) {
			echo '<span class="adct-lead-empty-product">No session</span>';
			return;
		}

		$session_code = ADCT_Leads::format_session_code( $session_key );
		$position_txt = $position
			? sprintf(
				/* translators: 1: click number in session, 2: total clicks in session */
				__( 'Click %1$d of %2$d', 'tracking-template' ),
				(int) $position['position'],
				(int) $position['total']
			)
			: '';
		?>
		<div class="adct-lead-session">
			<a class="adct-lead-session-link" href="<?php echo esc_url( $session_url ); ?>" title="<?php esc_attr_e( 'Open this visitor session', 'tracking-template' ); ?>">
				<?php echo esc_html( $session_code ); ?>
			</a>
			<?php if ( $position_txt ) : ?>
				<span class="adct-lead-session-meta"><?php echo esc_html( $position_txt ); ?></span>
			<?php endif; ?>
			<div class="adct-session-popover" role="tooltip">
				<h4><?php echo esc_html( sprintf( __( 'Session %s', 'tracking-template' ), $session_code ) ); ?></h4>
				<dl>
					<?php if ( $position_txt ) : ?>
						<div>
							<dt><?php esc_html_e( 'This click', 'tracking-template' ); ?></dt>
							<dd><?php echo esc_html( $position_txt . ' in this visit' ); ?></dd>
						</div>
					<?php endif; ?>
					<?php if ( $summary ) : ?>
						<div>
							<dt><?php esc_html_e( 'Session clicks', 'tracking-template' ); ?></dt>
							<dd><?php echo esc_html( number_format_i18n( (int) $summary->click_count ) ); ?></dd>
						</div>
						<div>
							<dt><?php esc_html_e( 'Visit started', 'tracking-template' ); ?></dt>
							<dd><?php echo esc_html( ADCT_Leads::format_lead_datetime( $summary->session_started ?? '' ) ); ?></dd>
						</div>
						<?php if ( ! empty( $summary->entry_source ) ) : ?>
							<div>
								<dt><?php esc_html_e( 'Source', 'tracking-template' ); ?></dt>
								<dd><?php echo esc_html( self::format_entry_source( $summary->entry_source ) ); ?></dd>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $summary->device_type ) ) : ?>
							<div>
								<dt><?php esc_html_e( 'Device', 'tracking-template' ); ?></dt>
								<dd><?php echo esc_html( self::format_device_type( $summary->device_type ) ); ?></dd>
							</div>
						<?php endif; ?>
					<?php endif; ?>
					<div>
						<dt><?php esc_html_e( 'Lead ID', 'tracking-template' ); ?></dt>
						<dd>#<?php echo esc_html( number_format_i18n( $row_id ) ); ?></dd>
					</div>
				</dl>
				<p class="adct-session-popover-foot">
					<a href="<?php echo esc_url( $session_url ); ?>"><?php esc_html_e( 'View full session →', 'tracking-template' ); ?></a>
				</p>
			</div>
		</div>
		<?php
	}

	public static function maybe_export_csv() {
		if ( ! is_admin() || ! ADCT_Settings::user_can_view() || ! ADCT_License::is_active() ) {
			return;
		}

		if ( empty( $_GET['page'] ) || ! in_array( $_GET['page'], array( 'tracking-template-sessions', 'tracking-template-leads' ), true ) ) {
			return;
		}

		if ( empty( $_GET['adct_export'] ) || 'csv' !== $_GET['adct_export'] ) {
			return;
		}

		check_admin_referer( 'adct_export_csv' );

		$filters = ADCT_Database::get_filters_from_request();

		if ( 'tracking-template-leads' === $_GET['page'] ) {
			$filters = ADCT_Leads::apply_channel_filter( $filters, ADCT_Leads::get_channel_from_request() );
		}

		$rows    = ADCT_Database::get_clicks( $filters, 5000 );
		$filename = 'tracking-template-leads' === $_GET['page'] ? 'tracking-template-leads.csv' : 'tracking-template-inquiries.csv';
		$headers  = array(
			'Session ID',
			'Clicked At',
			'Product Title',
			'Product URL',
			'Price',
			'Mileage',
			'Image URL',
			'Salesman',
			'Click Type',
			'Clicked Value',
			'Source',
			'Device',
			'Browser',
			'Country',
			'Region',
			'Entry Source',
			'Landing URL',
			'Landing Path',
			'Landing Referrer',
			'UTM Source',
			'UTM Medium',
			'UTM Campaign',
			'UTM Term',
			'UTM Content',
			'GCLID',
			'Session Seconds',
		);

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, $headers );

		foreach ( $rows as $row ) {
			fputcsv(
				$output,
				array(
					$row->session_id ?? '',
					$row->clicked_at ?? '',
					$row->product_title,
					$row->product_url,
					$row->product_price ?? '',
					$row->product_mileage ?? '',
					$row->product_image_url ?? '',
					$row->agent_name,
					$row->contact_type,
					$row->clicked_value ?? '',
					$row->source ?? '',
					$row->device_type ?? '',
					$row->browser_name ?? '',
					$row->visitor_country ?? '',
					$row->visitor_region ?? '',
					$row->entry_source ?? '',
					$row->landing_url ?? '',
					$row->landing_path ?? '',
					$row->landing_referrer ?? '',
					$row->utm_source ?? '',
					$row->utm_medium ?? '',
					$row->utm_campaign ?? '',
					$row->utm_term ?? '',
					$row->utm_content ?? '',
					$row->gclid ?? '',
					$row->duration_seconds ?? 0,
				)
			);
		}

		fclose( $output );
		exit;
	}

	public static function render_reports_page() {
		if ( ! ADCT_Settings::user_can_view() ) {
			return;
		}

		if ( ! ADCT_License::is_active() ) {
			self::render_locked_page();
			return;
		}

		$filters    = ADCT_Database::get_filters_from_request();
		$pagination = ADCT_Database::get_pagination_args();
		$per_page   = $pagination['per_page'];
		$paged      = $pagination['paged'];
		$offset     = $pagination['offset'];
		$list_total = ADCT_Database::count_sessions( $filters );

		$total_pages = max( 1, (int) ceil( $list_total / $per_page ) );
		if ( $paged > $total_pages ) {
			$paged  = $total_pages;
			$offset = ( $paged - 1 ) * $per_page;
		}

		$session_rows = ADCT_Database::get_sessions( $filters, $per_page, $offset );
		$session_keys = array_map(
			static function ( $session ) {
				return $session->session_key;
			},
			$session_rows
		);
		$clicks_by_session = ADCT_Database::get_clicks_for_session_keys( $session_keys, $filters );

		$total_clicks = ADCT_Database::count_clicks( $filters );
		$agents       = ADCT_Database::get_distinct_values( 'agent_name' );
		$types        = ADCT_Database::get_distinct_values( 'contact_type' );
		$devices       = ADCT_Database::get_distinct_values( 'device_type' );
		$countries     = ADCT_Database::get_distinct_values( 'visitor_country' );
		$entry_sources = ADCT_Database::get_distinct_values( 'entry_source' );
		$campaigns     = ADCT_Database::get_distinct_values( 'utm_campaign' );
		$landing_paths = ADCT_Database::get_distinct_values( 'landing_path' );

		$page_context = self::get_page_context();
		$snapshot     = $page_context['snapshot'];
		$version_info = $page_context['version_info'];
		$show_setup   = $page_context['show_setup'];
		$focus_session = ! empty( $filters['session_id'] ) ? sanitize_text_field( $filters['session_id'] ) : '';

		$base_url   = admin_url( 'admin.php?page=tracking-template-sessions' );
		$query_args = array_merge(
			$filters,
			array(
				'page'     => 'tracking-template-sessions',
				'per_page' => $per_page,
			)
		);
		$from_item  = $list_total ? ( $offset + 1 ) : 0;
		$to_item    = min( $offset + $per_page, $list_total );
		$export_url = wp_nonce_url( add_query_arg( array_merge( $_GET, array( 'adct_export' => 'csv' ) ), $base_url ), 'adct_export_csv' );
		?>
		<div class="wrap adct-wrap">
			<div class="adct-layout">
				<header class="adct-layout-header">
					<h1>Sessions</h1>
					<p class="adct-page-intro">Browse every visitor session in detail. Expand a session to see each contact click, landing URL, campaign attribution, and page context. Use filters to narrow results, or export the current view to CSV.</p>
				</header>

				<div class="adct-main">
			<div class="adct-stats-bar">
				<div class="adct-stat-card">
					<strong><?php echo esc_html( number_format_i18n( $total_clicks ) ); ?></strong>
					<span>Contact clicks<?php echo ( $filters['date_from'] || $filters['date_to'] || $filters['agent_name'] || $filters['contact_type'] || $filters['search'] ) ? ' (filtered)' : ''; ?></span>
				</div>
				<div class="adct-stat-card">
					<strong><?php echo esc_html( number_format_i18n( $list_total ) ); ?></strong>
					<span>Visitor sessions<?php echo ( $filters['date_from'] || $filters['date_to'] || $filters['agent_name'] || $filters['contact_type'] || $filters['search'] ) ? ' (filtered)' : ''; ?></span>
				</div>
				<div class="adct-stat-card">
					<strong><?php echo esc_html( number_format_i18n( $snapshot['clicks_today'] ) ); ?></strong>
					<span>Clicks today</span>
				</div>
				<div class="adct-stat-card">
					<strong><?php echo esc_html( self::format_relative_time( $snapshot['last_click'] ) ); ?></strong>
					<span>Last recorded click</span>
				</div>
			</div>

			<form method="get" class="adct-filters">
				<input type="hidden" name="page" value="tracking-template-sessions" />

				<label>
					From
					<input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" />
				</label>

				<label>
					To
					<input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>" />
				</label>

				<label>
					Salesman
					<select name="agent_name">
						<option value="">All</option>
						<?php foreach ( $agents as $agent ) : ?>
							<option value="<?php echo esc_attr( $agent ); ?>" <?php selected( $filters['agent_name'], $agent ); ?>>
								<?php echo esc_html( $agent ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>

				<label>
					Click type
					<select name="contact_type">
						<option value="">All</option>
						<?php foreach ( $types as $type ) : ?>
							<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $filters['contact_type'], $type ); ?>>
								<?php echo esc_html( $type ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>

				<label>
					Device
					<select name="device_type">
						<option value="">All</option>
						<?php foreach ( $devices as $device ) : ?>
							<option value="<?php echo esc_attr( $device ); ?>" <?php selected( $filters['device_type'] ?? '', $device ); ?>>
								<?php echo esc_html( ucfirst( $device ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>

				<label>
					Country
					<select name="visitor_country">
						<option value="">All</option>
						<?php foreach ( $countries as $country ) : ?>
							<option value="<?php echo esc_attr( $country ); ?>" <?php selected( $filters['visitor_country'] ?? '', $country ); ?>>
								<?php echo esc_html( $country ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>

				<label>
					Entry source
					<select name="entry_source">
						<option value="">All</option>
						<?php foreach ( $entry_sources as $entry_source ) : ?>
							<option value="<?php echo esc_attr( $entry_source ); ?>" <?php selected( $filters['entry_source'] ?? '', $entry_source ); ?>>
								<?php echo esc_html( self::format_entry_source( $entry_source ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>

				<label>
					Campaign
					<select name="utm_campaign">
						<option value="">All</option>
						<?php foreach ( $campaigns as $campaign ) : ?>
							<option value="<?php echo esc_attr( $campaign ); ?>" <?php selected( $filters['utm_campaign'] ?? '', $campaign ); ?>>
								<?php echo esc_html( $campaign ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>

				<label>
					Landing page
					<select name="landing_path">
						<option value="">All</option>
						<?php foreach ( $landing_paths as $landing_path ) : ?>
							<option value="<?php echo esc_attr( $landing_path ); ?>" <?php selected( $filters['landing_path'] ?? '', $landing_path ); ?>>
								<?php echo esc_html( $landing_path ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>

				<label>
					Rows per page
					<select name="per_page">
						<?php foreach ( ADCT_Database::PER_PAGE_OPTIONS as $option ) : ?>
							<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $per_page, $option ); ?>>
								<?php echo esc_html( number_format_i18n( $option ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>

				<label>
					Search
					<input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="Product, salesman, campaign, landing" />
				</label>

				<?php submit_button( 'Filter', 'secondary', '', false ); ?>

				<a class="button" href="<?php echo esc_url( $base_url ); ?>">Reset</a>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array_merge( $_GET, array( 'adct_export' => 'csv' ) ), $base_url ), 'adct_export_csv' ) ); ?>">
					Export CSV
				</a>
			</form>

			<div class="adct-table-toolbar">
				<p>
					<?php if ( $list_total ) : ?>
						Showing <?php echo esc_html( number_format_i18n( $from_item ) ); ?>–<?php echo esc_html( number_format_i18n( $to_item ) ); ?>
						of <?php echo esc_html( number_format_i18n( $list_total ) ); ?> sessions.
					<?php else : ?>
						No sessions to display for the current filters.
					<?php endif; ?>
				</p>
			</div>

			<?php if ( $focus_session ) : ?>
				<div class="adct-session-focus-banner">
					<?php
					printf(
						/* translators: %s: short session code */
						esc_html__( 'Showing visitor session %s from Leads. Expand the card below to see every click in this visit.', 'tracking-template' ),
						esc_html( ADCT_Leads::format_session_code( $focus_session ) )
					);
					?>
					<a href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Clear session filter', 'tracking-template' ); ?></a>
				</div>
			<?php endif; ?>

			<?php if ( empty( $session_rows ) ) : ?>
				<div class="adct-empty">No inquiry sessions recorded yet.</div>
			<?php else : ?>
				<div class="adct-card-list">
					<?php foreach ( $session_rows as $session ) : ?>
						<?php
						$session_clicks = $clicks_by_session[ $session->session_key ] ?? array();
						$is_focused     = $focus_session && $focus_session === (string) $session->session_key;
						self::render_session_card( $session, $session_clicks, $is_focused );
						?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php self::render_pagination( $list_total, $per_page, $paged, $query_args ); ?>
				</div>

				<?php self::render_sidebar( $snapshot, $export_url, $show_setup, $version_info ); ?>
			</div>
		</div>
		<?php
	}
}
