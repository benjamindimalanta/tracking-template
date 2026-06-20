<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADCT_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( 'ADCT_Settings', 'maybe_save_access_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_export_csv' ) );
		add_action( 'admin_head', array( __CLASS__, 'admin_styles' ) );
		add_action( 'admin_footer', array( __CLASS__, 'admin_scripts' ) );
	}

	const UTM_TEMPLATE = 'utm_source=google&utm_medium=cpc&utm_campaign={campaignname}&utm_id={campaignid}&utm_term={keyword}&utm_content={creative}';

	const GITHUB_REPO = 'https://github.com/benjamindimalanta/tracking-template';

	public static function admin_styles() {
		if ( empty( $_GET['page'] ) || 'tracking-template' !== $_GET['page'] ) {
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
			.adct-badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 999px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; background: #2271b1; color: #fff; line-height: 1.4; }
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

	public static function render_session_card( $session, array $clicks ) {
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
		<details class="adct-session-card <?php echo esc_attr( $source_class ); ?>">
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
		if ( empty( $_GET['page'] ) || 'tracking-template' !== $_GET['page'] ) {
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
		add_menu_page(
			'Tracking Template',
			'Tracking Template',
			ADCT_Settings::CAPABILITY,
			'tracking-template',
			array( __CLASS__, 'render_reports_page' ),
			'dashicons-chart-bar',
			58
		);
	}

	public static function maybe_export_csv() {
		if ( ! is_admin() || ! ADCT_Settings::user_can_view() ) {
			return;
		}

		if ( empty( $_GET['page'] ) || 'tracking-template' !== $_GET['page'] ) {
			return;
		}

		if ( empty( $_GET['adct_export'] ) || 'csv' !== $_GET['adct_export'] ) {
			return;
		}

		check_admin_referer( 'adct_export_csv' );

		$filters = ADCT_Database::get_filters_from_request();
		$rows    = ADCT_Database::get_clicks( $filters, 5000 );
		$filename = 'tracking-template-inquiries.csv';
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

		$base_url   = admin_url( 'admin.php?page=tracking-template' );
		$query_args = array_merge(
			$filters,
			array(
				'page'     => 'tracking-template',
				'per_page' => $per_page,
			)
		);
		$from_item  = $list_total ? ( $offset + 1 ) : 0;
		$to_item    = min( $offset + $per_page, $list_total );
		$snapshot     = ADCT_Database::get_live_snapshot();
		$version_info = ADCT_Updater::get_version_info();
		$export_url   = wp_nonce_url( add_query_arg( array_merge( $_GET, array( 'adct_export' => 'csv' ) ), $base_url ), 'adct_export_csv' );
		$show_setup   = (int) $snapshot['total_all_time'] === 0;

		if ( ADCT_Settings::user_can_manage() && ! empty( $_GET['adct_check_update'] ) ) {
			check_admin_referer( 'adct_check_update' );
			delete_transient( ADCT_Updater::CACHE_KEY );
			$version_info = ADCT_Updater::get_version_info( true );
		}
		?>
		<div class="wrap adct-wrap">
			<div class="adct-layout">
				<header class="adct-layout-header">
					<h1>Tracking Template</h1>
					<p class="adct-page-intro">Tracks contact clicks with marketing attribution, groups them by visitor session, and reports everything in one admin dashboard. Expand any session to see every click, full landing URL, campaign, and page detail. Data refreshes each time you open or reload this page.</p>
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
				<input type="hidden" name="page" value="tracking-template" />

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

			<?php if ( empty( $session_rows ) ) : ?>
				<div class="adct-empty">No inquiry sessions recorded yet.</div>
			<?php else : ?>
				<div class="adct-card-list">
					<?php foreach ( $session_rows as $session ) : ?>
						<?php
						$session_clicks = $clicks_by_session[ $session->session_key ] ?? array();
						self::render_session_card( $session, $session_clicks );
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
