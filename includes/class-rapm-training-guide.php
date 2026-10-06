<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A printable, plain-language training guide for someone using this plugin
 * for the very first time — distinct from Help & FAQ (short Q&A for people
 * who already know the tool and hit a specific snag). Every mockup below is
 * a hand-built illustration matching the real Add/Edit Asset screens field
 * for field, not a live screenshot, so it never goes stale silently if a
 * label changes elsewhere — update both places together.
 */
class RAPM_Training_Guide {

	public static function add_menu() {
		add_submenu_page(
			'edit.php?post_type=rapm_asset',
			__( 'Training Guide', 'rapm' ),
			__( 'Training Guide', 'rapm' ),
			'edit_posts',
			'rapm-training-guide',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * The guide's own type pairing (a headline serif + a body face literally
	 * designed for maximum reading clarity) only needs to load on this one
	 * screen — matches the existing pattern in RAPM_Upload_Handler for the
	 * hero CSS.
	 */
	public static function enqueue_admin_assets() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'rapm-training-guide' !== $page ) {
			return;
		}
		wp_enqueue_style(
			'rapm-training-guide-fonts',
			'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Atkinson+Hyperlegible:wght@400;700&family=JetBrains+Mono:wght@500;600&display=swap',
			array(),
			null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NoExplicitVersion -- versioned by Google Fonts itself.
		);
	}

	public static function render_page() {
		$help_url = admin_url( 'edit.php?post_type=rapm_asset&page=rapm-help' );
		?>
		<div class="wrap rapm-tg-page">
			<style>
				.rapm-tg-page, .rapm-tg-page * { box-sizing: border-box; }
				.rapm-tg-page {
					--rapm-tg-paper: #F5F6F4;
					--rapm-tg-surface: #FFFFFF;
					--rapm-tg-ink: #182421;
					--rapm-tg-ink-muted: #57655F;
					--rapm-tg-line: #DEE3DF;
					--rapm-tg-accent: #B5651D;
					--rapm-tg-accent-ink: #7A430F;
					--rapm-tg-accent-soft: #F7E9DA;
					--rapm-tg-good: #2F7D5A;
					--rapm-tg-good-soft: #E4F3EC;
					--rapm-tg-danger: #B3261E;
					--rapm-tg-danger-soft: #FBEAE8;
					--rapm-tg-shadow: 0 1px 2px rgba(20,30,25,.06), 0 6px 18px rgba(20,30,25,.06);
					background: var(--rapm-tg-paper);
					color: var(--rapm-tg-ink);
					font-family: 'Atkinson Hyperlegible', 'Segoe UI', Arial, sans-serif;
					font-size: 16px;
					line-height: 1.65;
					-webkit-font-smoothing: antialiased;
					max-width: 1180px;
					padding: 20px 24px 80px 0;
				}
				.rapm-tg-page h1, .rapm-tg-page h2, .rapm-tg-page h3 { font-family: 'Fraunces', Georgia, serif; font-weight: 600; text-wrap: balance; color: var(--rapm-tg-ink); }
				.rapm-tg-page code, .rapm-tg-page .rapm-tg-mono { font-family: 'JetBrains Mono', ui-monospace, monospace; }
				.rapm-tg-page a { color: var(--rapm-tg-accent-ink); }
				.rapm-tg-page a:focus-visible, .rapm-tg-page button:focus-visible { outline: 3px solid var(--rapm-tg-accent); outline-offset: 2px; }
				.rapm-tg-page p { margin: 0 0 14px; }

				.rapm-tg-grid { display: grid; grid-template-columns: 220px minmax(0,1fr); gap: 48px; align-items: start; margin-top: 18px; }
				@media (max-width: 880px) {
					.rapm-tg-grid { grid-template-columns: 1fr; }
					.rapm-tg-toc { display: none; }
				}

				.rapm-tg-cover { display: flex; flex-direction: column; gap: 12px; padding-bottom: 24px; border-bottom: 1px solid var(--rapm-tg-line); margin-bottom: 8px; }
				.rapm-tg-eyebrow { font-family: 'JetBrains Mono', monospace; font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: var(--rapm-tg-accent-ink); font-weight: 600; }
				.rapm-tg-cover h1 { font-size: clamp(1.7rem, 3vw, 2.3rem); margin: 0; }
				.rapm-tg-cover .rapm-tg-lede { max-width: 62ch; color: var(--rapm-tg-ink-muted); font-size: 1.05rem; margin: 0; }
				.rapm-tg-cover .rapm-tg-meta { display: flex; gap: 18px; flex-wrap: wrap; font-size: .92rem; color: var(--rapm-tg-ink-muted); margin-top: 4px; }
				.rapm-tg-cover .rapm-tg-meta strong { color: var(--rapm-tg-ink); font-weight: 700; }

				.rapm-tg-toc { position: sticky; top: 46px; align-self: start; }
				.rapm-tg-toc-label { font-family: 'JetBrains Mono', monospace; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: var(--rapm-tg-ink-muted); margin: 0 0 10px; }
				.rapm-tg-toc ol { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 2px; border-left: 2px solid var(--rapm-tg-line); }
				.rapm-tg-toc a { display: block; padding: 6px 0 6px 14px; margin-left: -2px; border-left: 2px solid transparent; color: var(--rapm-tg-ink-muted); text-decoration: none; font-size: .88rem; }
				.rapm-tg-toc a:hover { color: var(--rapm-tg-ink); border-left-color: var(--rapm-tg-line); }

				.rapm-tg-main { min-width: 0; display: flex; flex-direction: column; gap: 52px; }
				.rapm-tg-section { scroll-margin-top: 50px; max-width: 720px; }
				.rapm-tg-section h2 { font-size: 1.5rem; margin: 0 0 6px; }
				.rapm-tg-section h3 { font-size: 1.1rem; margin: 26px 0 10px; }
				.rapm-tg-kicker { font-family: 'JetBrains Mono', monospace; font-size: 11.5px; letter-spacing: .08em; text-transform: uppercase; color: var(--rapm-tg-accent-ink); font-weight: 600; margin: 0 0 8px; }
				.rapm-tg-section > p.rapm-tg-intro { color: var(--rapm-tg-ink-muted); font-size: 1rem; margin: 0 0 20px; max-width: 66ch; }

				.rapm-tg-plain { margin: 0 0 18px; padding-left: 0; list-style: none; display: flex; flex-direction: column; gap: 9px; }
				.rapm-tg-plain li { display: flex; gap: 10px; align-items: flex-start; }
				.rapm-tg-plain li::before { content: "\2013"; color: var(--rapm-tg-accent-ink); font-weight: 700; flex: none; }

				.rapm-tg-chip { flex: none; width: 24px; height: 24px; border-radius: 50%; background: var(--rapm-tg-accent); color: #fff; display: flex; align-items: center; justify-content: center; font-family: 'JetBrains Mono', monospace; font-size: 12px; font-weight: 700; margin-top: 1px; }

				.rapm-tg-callout { border-radius: 10px; padding: 14px 16px; margin: 4px 0 20px; border: 1px solid var(--rapm-tg-line); max-width: 66ch; }
				.rapm-tg-callout p:last-child { margin-bottom: 0; }
				.rapm-tg-callout-tip { background: var(--rapm-tg-accent-soft); border-color: #E4BE93; }
				.rapm-tg-callout-warn { background: var(--rapm-tg-danger-soft); border-color: #E7B3AE; }
				.rapm-tg-callout-title { font-weight: 700; display: block; margin-bottom: 4px; }
				.rapm-tg-callout-tip .rapm-tg-callout-title { color: var(--rapm-tg-accent-ink); }
				.rapm-tg-callout-warn .rapm-tg-callout-title { color: var(--rapm-tg-danger); }

				.rapm-tg-type-grid { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 14px; margin: 0 0 8px; }
				@media (max-width: 560px) { .rapm-tg-type-grid { grid-template-columns: 1fr; } }
				.rapm-tg-type-card { background: var(--rapm-tg-surface); border: 1px solid var(--rapm-tg-line); border-radius: 10px; padding: 16px; box-shadow: var(--rapm-tg-shadow); display: flex; flex-direction: column; gap: 8px; }
				.rapm-tg-type-shape-wrap { height: 70px; display: flex; align-items: center; }
				.rapm-tg-type-shape { background: repeating-linear-gradient(135deg, rgba(181,101,29,.16), rgba(181,101,29,.16) 6px, rgba(181,101,29,.08) 6px, rgba(181,101,29,.08) 12px); border: 1.5px dashed var(--rapm-tg-accent); border-radius: 5px; }
				.rapm-tg-type-card h3 { margin: 0; font-size: 1rem; }
				.rapm-tg-type-card .rapm-tg-dims { font-family: 'JetBrains Mono', monospace; font-size: 12px; color: var(--rapm-tg-ink-muted); }
				.rapm-tg-type-card p { margin: 0; color: var(--rapm-tg-ink-muted); font-size: .92rem; }

				.rapm-tg-figure { display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-start; margin: 6px 0 24px; }
				.rapm-tg-figure .rapm-tg-mockup-col { flex: 1 1 320px; min-width: 280px; }
				.rapm-tg-figure .rapm-tg-callouts-col { flex: 1 1 260px; min-width: 240px; }
				.rapm-tg-mockup-tag { font-family: 'JetBrains Mono', monospace; font-size: 10.5px; letter-spacing: .06em; text-transform: uppercase; color: var(--rapm-tg-ink-muted); margin: 0 0 6px; }
				.rapm-tg-mockup { background: #F0F0F1; border: 1px solid #DCDCDE; border-radius: 8px; padding: 14px; box-shadow: var(--rapm-tg-shadow); font-family: 'Segoe UI', Arial, sans-serif; color: #1D2327; font-size: 13px; }
				.rapm-tg-mockup .mk-card { background: #fff; border: 1px solid #DCDCDE; border-radius: 5px; padding: 11px 13px; margin-bottom: 9px; }
				.rapm-tg-mockup .mk-label { font-weight: 700; font-size: 12px; margin-bottom: 5px; display: flex; align-items: center; gap: 7px; }
				.rapm-tg-mockup .mk-input { background: #fff; border: 1px solid #8C8F94; border-radius: 3px; height: 26px; width: 100%; margin-bottom: 3px; }
				.rapm-tg-mockup .mk-desc { color: #646970; font-size: 11px; }
				.rapm-tg-mockup .mk-btn { display: inline-block; border-radius: 3px; padding: 5px 13px; font-size: 12px; font-weight: 600; }
				.rapm-tg-mockup .mk-btn-primary { background: #2271B1; color: #fff; }
				.rapm-tg-mockup .mk-btn-ghost { background: #F6F7F7; border: 1px solid #2271B1; color: #2271B1; }
				.rapm-tg-mockup .mk-steps { display: flex; gap: 4px; margin-bottom: 11px; }
				.rapm-tg-mockup .mk-step { flex: 1; background: #fff; border: 1px solid #DCDCDE; border-radius: 4px; padding: 6px 6px; font-size: 10px; font-weight: 700; color: #646970; text-align: center; }
				.rapm-tg-mockup .mk-step.is-on { border-color: #2271B1; background: #F0F6FC; color: #1D2327; }
				.rapm-tg-mockup .mk-radio-row { display: flex; gap: 14px; font-size: 11.5px; margin-bottom: 7px; }
				.rapm-tg-mockup .mk-radio-row span { display: flex; align-items: center; gap: 5px; }
				.rapm-tg-mockup .mk-dot { width: 11px; height: 11px; border-radius: 50%; border: 1.5px solid #8C8F94; flex: none; }
				.rapm-tg-mockup .mk-dot.on { border-color: #2271B1; background: radial-gradient(#2271B1 0 40%, #fff 42%); }
				.rapm-tg-mockup .mk-anchor-grid { display: grid; grid-template-columns: repeat(3, 20px); grid-template-rows: repeat(3, 20px); gap: 3px; margin-top: 6px; }
				.rapm-tg-mockup .mk-anchor-grid div { border: 1px solid #C3C4C7; border-radius: 3px; background: #fff; }
				.rapm-tg-mockup .mk-anchor-grid div.on { background: #2271B1; border-color: #2271B1; }
				.rapm-tg-mockup .mk-warn { background: #FCF0F1; border-left: 3px solid #D63638; padding: 7px 9px; font-size: 11px; margin-bottom: 8px; }
				.rapm-tg-mockup .mk-note { background: #F0F6FC; border-left: 3px solid #72AEE6; padding: 7px 9px; font-size: 11px; display: flex; align-items: center; gap: 7px; }
				.rapm-tg-mockup .mk-preview { background: #333; border-radius: 5px; aspect-ratio: 16/5; display: flex; align-items: flex-end; padding: 9px; margin-bottom: 8px; }
				.rapm-tg-mockup .mk-preview span { color: #fff; font-weight: 700; font-size: 12px; }

				.rapm-tg-callouts-col ol { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 11px; }
				.rapm-tg-callouts-col li { display: flex; gap: 10px; }
				.rapm-tg-callouts-col li .rapm-tg-txt strong { display: block; font-size: .92rem; }
				.rapm-tg-callouts-col li .rapm-tg-txt span { color: var(--rapm-tg-ink-muted); font-size: .88rem; }

				.rapm-tg-table-wrap { overflow-x: auto; margin: 4px 0 8px; border: 1px solid var(--rapm-tg-line); border-radius: 10px; }
				table.rapm-tg-sizes { border-collapse: collapse; width: 100%; min-width: 540px; background: var(--rapm-tg-surface); }
				table.rapm-tg-sizes th, table.rapm-tg-sizes td { text-align: left; padding: 10px 15px; border-bottom: 1px solid var(--rapm-tg-line); font-size: .92rem; }
				table.rapm-tg-sizes th { font-family: 'JetBrains Mono', monospace; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: var(--rapm-tg-ink-muted); background: #FBF3EB; }
				table.rapm-tg-sizes td:nth-child(2), table.rapm-tg-sizes td:nth-child(3), table.rapm-tg-sizes td:nth-child(4) { font-family: 'JetBrains Mono', monospace; font-variant-numeric: tabular-nums; font-size: .88rem; }
				table.rapm-tg-sizes tr:last-child td { border-bottom: none; }

				.rapm-tg-pair { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 6px 0 20px; }
				@media (max-width: 560px) { .rapm-tg-pair { grid-template-columns: 1fr; } }
				.rapm-tg-pair .rapm-tg-box { border-radius: 10px; padding: 13px 15px; border: 1px solid var(--rapm-tg-line); }
				.rapm-tg-pair .rapm-tg-box.rapm-tg-good { background: var(--rapm-tg-good-soft); border-color: #A9D6C1; }
				.rapm-tg-pair .rapm-tg-box.rapm-tg-bad { background: var(--rapm-tg-danger-soft); border-color: #E7B3AE; }
				.rapm-tg-pair .rapm-tg-box .rapm-tg-lbl { font-weight: 700; font-size: .88rem; display: flex; align-items: center; gap: 6px; margin-bottom: 6px; }
				.rapm-tg-pair .rapm-tg-box.rapm-tg-good .rapm-tg-lbl { color: var(--rapm-tg-good); }
				.rapm-tg-pair .rapm-tg-box.rapm-tg-bad .rapm-tg-lbl { color: var(--rapm-tg-danger); }
				.rapm-tg-pair .rapm-tg-box p { margin: 0; font-size: .9rem; color: var(--rapm-tg-ink); }

				.rapm-tg-problem { border: 1px solid var(--rapm-tg-line); background: var(--rapm-tg-surface); border-radius: 10px; padding: 14px 16px; margin-bottom: 12px; box-shadow: var(--rapm-tg-shadow); }
				.rapm-tg-problem .rapm-tg-q { font-weight: 700; margin-bottom: 6px; display: flex; gap: 10px; }
				.rapm-tg-problem .rapm-tg-q .rapm-tg-chip { background: var(--rapm-tg-danger); }
				.rapm-tg-problem .rapm-tg-a { color: var(--rapm-tg-ink-muted); margin: 0; padding-left: 34px; }

				.rapm-tg-checklist { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0; border: 1px solid var(--rapm-tg-line); border-radius: 10px; overflow: hidden; background: var(--rapm-tg-surface); max-width: 66ch; }
				.rapm-tg-checklist li { display: flex; align-items: flex-start; gap: 12px; padding: 12px 16px; border-bottom: 1px solid var(--rapm-tg-line); }
				.rapm-tg-checklist li:last-child { border-bottom: none; }
				.rapm-tg-checklist .rapm-tg-box2 { flex: none; width: 18px; height: 18px; border: 2px solid var(--rapm-tg-accent); border-radius: 4px; margin-top: 2px; }

				.rapm-tg-footer { grid-column: 1 / -1; border-top: 1px solid var(--rapm-tg-line); margin-top: 8px; padding-top: 22px; display: flex; justify-content: space-between; gap: 20px; flex-wrap: wrap; color: var(--rapm-tg-ink-muted); font-size: .9rem; }
				.rapm-tg-footer strong { color: var(--rapm-tg-ink); }
			</style>

			<div class="rapm-tg-cover">
				<span class="rapm-tg-eyebrow"><?php esc_html_e( 'Internal Training', 'rapm' ); ?></span>
				<h1><?php esc_html_e( 'Promo Manager Training Guide', 'rapm' ); ?></h1>
				<p class="rapm-tg-lede"><?php esc_html_e( 'How to add and manage sale banners on the website — written step by step, with no assumed experience. If you can fill out a form, you can do this.', 'rapm' ); ?></p>
				<div class="rapm-tg-meta">
					<span><strong><?php esc_html_e( "Who it's for:", 'rapm' ); ?></strong> <?php esc_html_e( 'anyone adding promotions for the first time', 'rapm' ); ?></span>
					<span><strong><?php esc_html_e( 'Time to learn:', 'rapm' ); ?></strong> <?php esc_html_e( 'about 20 minutes', 'rapm' ); ?></span>
				</div>
			</div>

			<div class="rapm-tg-grid">
				<nav class="rapm-tg-toc" aria-label="<?php esc_attr_e( 'Table of contents', 'rapm' ); ?>">
					<p class="rapm-tg-toc-label"><?php esc_html_e( 'On this page', 'rapm' ); ?></p>
					<ol>
						<li><a href="#rapm-tg-before"><?php esc_html_e( 'Before You Start', 'rapm' ); ?></a></li>
						<li><a href="#rapm-tg-types"><?php esc_html_e( 'The 5 Types of Promotions', 'rapm' ); ?></a></li>
						<li><a href="#rapm-tg-adding"><?php esc_html_e( 'Adding a New Promotion', 'rapm' ); ?></a></li>
						<li><a href="#rapm-tg-editing"><?php esc_html_e( 'Changing One Already Made', 'rapm' ); ?></a></li>
						<li><a href="#rapm-tg-calendar"><?php esc_html_e( 'Showing a Calendar', 'rapm' ); ?></a></li>
						<li><a href="#rapm-tg-sizes"><?php esc_html_e( 'Picture Size Cheat Sheet', 'rapm' ); ?></a></li>
						<li><a href="#rapm-tg-sliders"><?php esc_html_e( 'The Promotions Page', 'rapm' ); ?></a></li>
						<li><a href="#rapm-tg-problems"><?php esc_html_e( 'Common Problems', 'rapm' ); ?></a></li>
						<li><a href="#rapm-tg-checklist"><?php esc_html_e( 'Quick Checklist', 'rapm' ); ?></a></li>
						<li><a href="#rapm-tg-help"><?php esc_html_e( 'Getting Help', 'rapm' ); ?></a></li>
					</ol>
				</nav>

				<main class="rapm-tg-main">

					<section class="rapm-tg-section" id="rapm-tg-before">
						<p class="rapm-tg-kicker"><?php esc_html_e( 'Start here', 'rapm' ); ?></p>
						<h2><?php esc_html_e( "Before You Start: 4 Words You'll See a Lot", 'rapm' ); ?></h2>
						<p class="rapm-tg-intro"><?php esc_html_e( "You don't need to memorize these — just know what they mean when you see them.", 'rapm' ); ?></p>

						<ul class="rapm-tg-plain">
							<li><span><strong><?php esc_html_e( 'Promotion', 'rapm' ); ?></strong> — <?php esc_html_e( 'one sale banner, coupon, or offer. Each one is its own entry in the tool.', 'rapm' ); ?></span></li>
							<li><span><strong><?php esc_html_e( 'Type', 'rapm' ); ?></strong> — <?php esc_html_e( 'which kind of promotion it is: Slider, Feature banner, Coupon row or Tile row. Each type is a different shape and goes in a different spot on the page.', 'rapm' ); ?></span></li>
							<li><span><strong><?php esc_html_e( 'Where it shows', 'rapm' ); ?></strong> — <?php esc_html_e( 'the spot on your website it goes in, like "Home page slider." Promotions in the same spot take turns. Pick it from the list.', 'rapm' ); ?></span></li>
							<li><span><strong><?php esc_html_e( 'Desktop / Mobile picture', 'rapm' ); ?></strong> — <?php esc_html_e( 'every promotion needs two pictures, because a computer screen and a phone screen are different shapes. The tool shows the right one to each visitor automatically.', 'rapm' ); ?></span></li>
						</ul>

						<div class="rapm-tg-callout rapm-tg-callout-tip">
							<span class="rapm-tg-callout-title"><?php esc_html_e( 'Good news', 'rapm' ); ?></span>
							<p><?php esc_html_e( "You cannot break the website. If a picture is the wrong size, the tool stops you and tells you exactly what's wrong before anything goes live.", 'rapm' ); ?></p>
						</div>
					</section>

					<section class="rapm-tg-section" id="rapm-tg-types">
						<p class="rapm-tg-kicker"><?php esc_html_e( 'Know your shapes', 'rapm' ); ?></p>
						<h2><?php esc_html_e( 'The 5 Types of Promotions', 'rapm' ); ?></h2>
						<p class="rapm-tg-intro"><?php esc_html_e( "Pick the type before you upload any pictures — it decides what size picture you'll need.", 'rapm' ); ?></p>

						<div class="rapm-tg-type-grid">
							<div class="rapm-tg-type-card">
								<div class="rapm-tg-type-shape-wrap"><div class="rapm-tg-type-shape" style="width:100%;height:24px;"></div></div>
								<h3><?php esc_html_e( 'Slider', 'rapm' ); ?></h3>
								<p class="rapm-tg-dims">1920 &times; 600 &middot; <?php esc_html_e( 'wide banner', 'rapm' ); ?></p>
								<p><?php esc_html_e( 'The big, full-width slider at the top of a page. Usually the first thing a visitor sees.', 'rapm' ); ?></p>
							</div>
							<div class="rapm-tg-type-card">
								<div class="rapm-tg-type-shape-wrap"><div style="width:100%;"><div class="rapm-tg-type-shape" style="width:100%;height:20px;"></div><div class="rapm-tg-type-shape" style="width:100%;height:5px;margin-top:3px;"></div></div></div>
								<h3><?php esc_html_e( 'Shop the Look', 'rapm' ); ?></h3>
								<p class="rapm-tg-dims"><?php esc_html_e( 'any shape, 2000+ wide is best', 'rapm' ); ?> &middot; <?php esc_html_e( 'room photo', 'rapm' ); ?></p>
								<p><?php esc_html_e( 'A big room photo with a bar of tabs under it. Each tab is one room or collection, with its own photo and words. Numbered dots on the photo show the pieces you sell, with their prices.', 'rapm' ); ?></p>
							</div>
							<div class="rapm-tg-type-card">
								<div class="rapm-tg-type-shape-wrap"><div class="rapm-tg-type-shape" style="width:100%;height:12px;"></div></div>
								<h3><?php esc_html_e( 'Feature banner', 'rapm' ); ?></h3>
								<p class="rapm-tg-dims">1920 &times; 300 &middot; <?php esc_html_e( 'thin strip', 'rapm' ); ?></p>
								<p><?php esc_html_e( 'A wide strip that features one thing, usually lower on a page.', 'rapm' ); ?></p>
							</div>
							<div class="rapm-tg-type-card">
								<div class="rapm-tg-type-shape-wrap"><div class="rapm-tg-type-shape" style="width:54px;height:68px;"></div></div>
								<h3><?php esc_html_e( 'Coupon row', 'rapm' ); ?></h3>
								<p class="rapm-tg-dims">600 &times; 750 &middot; <?php esc_html_e( 'tall card', 'rapm' ); ?></p>
								<p><?php esc_html_e( 'A small card shown in a row of coupons that visitors scroll through sideways.', 'rapm' ); ?></p>
							</div>
							<div class="rapm-tg-type-card">
								<div class="rapm-tg-type-shape-wrap"><div class="rapm-tg-type-shape" style="width:58px;height:58px;"></div></div>
								<h3><?php esc_html_e( 'Tile row', 'rapm' ); ?></h3>
								<p class="rapm-tg-dims">1080 &times; 1080 &middot; <?php esc_html_e( 'square tile', 'rapm' ); ?></p>
								<p><?php esc_html_e( 'A small square tile in a compact row, like quick links (Financing, Visit Us, and so on).', 'rapm' ); ?></p>
							</div>
						</div>
					</section>

					<section class="rapm-tg-section" id="rapm-tg-adding">
						<p class="rapm-tg-kicker"><?php esc_html_e( 'The main task', 'rapm' ); ?></p>
						<h2><?php esc_html_e( 'Adding a New Promotion', 'rapm' ); ?></h2>
						<p class="rapm-tg-intro">
							<?php esc_html_e( 'Go to', 'rapm' ); ?> <strong><?php esc_html_e( 'Promotions', 'rapm' ); ?></strong> <?php esc_html_e( 'and click', 'rapm' ); ?> <strong><?php esc_html_e( 'Add a picture', 'rapm' ); ?></strong> <?php esc_html_e( 'in the section where it should show.', 'rapm' ); ?>
							<?php esc_html_e( 'The tool walks you through 4 steps, one at a time, with a bar at the top showing where you are. You cannot go to the next step until the current one is filled in correctly.', 'rapm' ); ?>
						</p>

						<h3><?php esc_html_e( 'Pick the Type first', 'rapm' ); ?></h3>
						<div class="rapm-tg-figure">
							<div class="rapm-tg-mockup-col">
								<p class="rapm-tg-mockup-tag"><?php esc_html_e( "What you'll see", 'rapm' ); ?></p>
								<div class="rapm-tg-mockup">
									<div class="mk-card">
										<div class="mk-label"><span class="rapm-tg-chip" style="width:18px;height:18px;font-size:10px;">i</span><?php esc_html_e( 'Type of Promotion', 'rapm' ); ?></div>
										<div class="mk-input" style="display:flex;align-items:center;padding:0 8px;color:#1D2327;">Slider &mdash; Desktop 1920x600, Mobile 1080x1920 &#9660;</div>
										<div class="mk-desc"><?php esc_html_e( 'Choose which one before uploading pictures below.', 'rapm' ); ?></div>
									</div>
								</div>
							</div>
							<div class="rapm-tg-callouts-col">
								<ol>
									<li><span class="rapm-tg-chip">i</span><span class="rapm-tg-txt"><strong><?php esc_html_e( 'One dropdown, at the very top.', 'rapm' ); ?></strong><span><?php esc_html_e( 'Changing it reloads the page so you always see the correct picture size for that type.', 'rapm' ); ?></span></span></li>
								</ol>
							</div>
						</div>

						<h3><?php esc_html_e( 'Step 1 of 4 — The Basics', 'rapm' ); ?></h3>
						<div class="rapm-tg-figure">
							<div class="rapm-tg-mockup-col">
								<p class="rapm-tg-mockup-tag"><?php esc_html_e( "What you'll see", 'rapm' ); ?></p>
								<div class="rapm-tg-mockup">
									<div class="mk-steps">
										<div class="mk-step is-on">&#9312; <?php esc_html_e( 'The Basics', 'rapm' ); ?></div>
										<div class="mk-step">&#9313; <?php esc_html_e( 'Your Message', 'rapm' ); ?></div>
										<div class="mk-step">&#9314; <?php esc_html_e( 'Where It Links', 'rapm' ); ?></div>
										<div class="mk-step">&#9315; <?php esc_html_e( 'Review & Schedule', 'rapm' ); ?></div>
									</div>
									<div class="mk-card">
										<div class="mk-label"><span class="rapm-tg-chip" style="width:18px;height:18px;font-size:10px;">1</span><?php esc_html_e( 'Internal Name', 'rapm' ); ?></div>
										<div class="mk-input"></div>
										<div class="mk-desc"><?php esc_html_e( 'For your own records — visitors never see this.', 'rapm' ); ?></div>
									</div>
									<div class="mk-card">
										<div class="mk-label"><span class="rapm-tg-chip" style="width:18px;height:18px;font-size:10px;">2</span><?php esc_html_e( 'Desktop Promotion', 'rapm' ); ?></div>
										<div class="mk-btn mk-btn-ghost" style="margin-bottom:6px;"><?php esc_html_e( 'Choose File', 'rapm' ); ?></div>
										<div class="mk-warn"><strong><?php esc_html_e( 'This picture is a different shape than needed.', 'rapm' ); ?></strong> <?php esc_html_e( 'Pick which part to keep.', 'rapm' ); ?>
											<div class="mk-anchor-grid">
												<div></div><div></div><div></div>
												<div></div><div class="on"></div><div></div>
												<div></div><div></div><div></div>
											</div>
										</div>
									</div>
									<div class="mk-btn mk-btn-primary"><?php esc_html_e( 'Next', 'rapm' ); ?></div>
								</div>
							</div>
							<div class="rapm-tg-callouts-col">
								<ol>
									<li><span class="rapm-tg-chip">1</span><span class="rapm-tg-txt"><strong><?php esc_html_e( 'Internal Name', 'rapm' ); ?></strong><span><?php esc_html_e( 'Any name that helps you remember what this is, like "Fall Sale Slider."', 'rapm' ); ?></span></span></li>
									<li><span class="rapm-tg-chip">2</span><span class="rapm-tg-txt"><strong><?php esc_html_e( 'Desktop Promotion picture', 'rapm' ); ?></strong><span><?php esc_html_e( "Required. Upload the picture, or paste a link to one. If it's the wrong shape, a small grid of buttons appears — click the part of the picture you want kept, and the rest is trimmed automatically.", 'rapm' ); ?></span></span></li>
								</ol>
								<div class="rapm-tg-callout rapm-tg-callout-warn" style="margin-top:12px;">
									<span class="rapm-tg-callout-title"><?php esc_html_e( "Can't click Next?", 'rapm' ); ?></span>
									<p><?php esc_html_e( "You're missing the Internal Name or the desktop picture. A message tells you exactly which one.", 'rapm' ); ?></p>
								</div>
							</div>
						</div>

						<h3><?php esc_html_e( 'Step 2 of 4 — Your Message', 'rapm' ); ?></h3>
						<div class="rapm-tg-figure">
							<div class="rapm-tg-mockup-col">
								<p class="rapm-tg-mockup-tag"><?php esc_html_e( "What you'll see", 'rapm' ); ?></p>
								<div class="rapm-tg-mockup">
									<div class="mk-steps">
										<div class="mk-step">&#10003; <?php esc_html_e( 'The Basics', 'rapm' ); ?></div>
										<div class="mk-step is-on">&#9313; <?php esc_html_e( 'Your Message', 'rapm' ); ?></div>
										<div class="mk-step">&#9314; <?php esc_html_e( 'Where It Links', 'rapm' ); ?></div>
										<div class="mk-step">&#9315; <?php esc_html_e( 'Review & Schedule', 'rapm' ); ?></div>
									</div>
									<div class="mk-card">
										<div class="mk-label"><?php esc_html_e( 'Does your picture already show the price or sale?', 'rapm' ); ?></div>
										<div class="mk-radio-row"><span><span class="mk-dot on"></span><?php esc_html_e( "No — I'll type it below", 'rapm' ); ?></span></div>
										<div class="mk-radio-row"><span><span class="mk-dot"></span><?php esc_html_e( "Yes — it's already on the picture", 'rapm' ); ?></span></div>
									</div>
									<div class="mk-card">
										<div class="mk-label"><span class="rapm-tg-chip" style="width:18px;height:18px;font-size:10px;">1</span><?php esc_html_e( 'Headline', 'rapm' ); ?></div>
										<div class="mk-input"></div>
										<div class="mk-label" style="margin-top:8px;"><span class="rapm-tg-chip" style="width:18px;height:18px;font-size:10px;">2</span><?php esc_html_e( 'Button Text', 'rapm' ); ?></div>
										<div class="mk-input"></div>
									</div>
								</div>
							</div>
							<div class="rapm-tg-callouts-col">
								<ol>
									<li><span class="rapm-tg-chip">1</span><span class="rapm-tg-txt"><strong><?php esc_html_e( "Only type text here if your picture doesn't already have it.", 'rapm' ); ?></strong><span><?php esc_html_e( 'Typing a headline AND having it already on the picture makes them overlap.', 'rapm' ); ?></span></span></li>
									<li><span class="rapm-tg-chip">2</span><span class="rapm-tg-txt"><strong><?php esc_html_e( 'Headline, smaller line, and Button Text', 'rapm' ); ?></strong><span><?php esc_html_e( 'Real, readable text — not part of the picture file. This is what search engines and screen readers can actually read.', 'rapm' ); ?></span></span></li>
								</ol>
							</div>
						</div>

						<h3><?php esc_html_e( 'Step 3 of 4 — Where It Links', 'rapm' ); ?></h3>
						<div class="rapm-tg-figure">
							<div class="rapm-tg-mockup-col">
								<p class="rapm-tg-mockup-tag"><?php esc_html_e( "What you'll see", 'rapm' ); ?></p>
								<div class="rapm-tg-mockup">
									<div class="mk-steps">
										<div class="mk-step">&#10003; <?php esc_html_e( 'The Basics', 'rapm' ); ?></div>
										<div class="mk-step">&#10003; <?php esc_html_e( 'Your Message', 'rapm' ); ?></div>
										<div class="mk-step is-on">&#9314; <?php esc_html_e( 'Where It Links', 'rapm' ); ?></div>
										<div class="mk-step">&#9315; <?php esc_html_e( 'Review & Schedule', 'rapm' ); ?></div>
									</div>
									<div class="mk-card">
										<div class="mk-label"><?php esc_html_e( 'Send visitors to...', 'rapm' ); ?></div>
										<div class="mk-input" style="display:flex;align-items:center;padding:0 8px;"><?php esc_html_e( 'A specific product', 'rapm' ); ?> &#9660;</div>
										<div class="mk-desc" style="margin-top:6px;"><?php esc_html_e( 'Start typing a name — no ID numbers needed.', 'rapm' ); ?></div>
										<div class="mk-input" style="margin-top:4px;display:flex;align-items:center;padding:0 8px;color:#1D2327;">leather sect|</div>
									</div>
								</div>
							</div>
							<div class="rapm-tg-callouts-col">
								<ol>
									<li><span class="rapm-tg-chip">?</span><span class="rapm-tg-txt"><strong><?php esc_html_e( 'Not sure which to pick?', 'rapm' ); ?></strong><span><?php esc_html_e( 'Choose "A specific link" and paste the web address — the same kind you see in a browser bar.', 'rapm' ); ?></span></span></li>
									<li><span class="rapm-tg-chip">?</span><span class="rapm-tg-txt"><strong><?php esc_html_e( 'Selling one product or a whole category?', 'rapm' ); ?></strong><span><?php esc_html_e( 'Pick that option, then start typing its name. A list of matches appears — click the right one.', 'rapm' ); ?></span></span></li>
								</ol>
							</div>
						</div>

						<h3><?php esc_html_e( 'Step 4 of 4 — Review & Schedule', 'rapm' ); ?></h3>
						<div class="rapm-tg-figure">
							<div class="rapm-tg-mockup-col">
								<p class="rapm-tg-mockup-tag"><?php esc_html_e( "What you'll see", 'rapm' ); ?></p>
								<div class="rapm-tg-mockup">
									<div class="mk-steps">
										<div class="mk-step">&#10003; <?php esc_html_e( 'The Basics', 'rapm' ); ?></div>
										<div class="mk-step">&#10003; <?php esc_html_e( 'Your Message', 'rapm' ); ?></div>
										<div class="mk-step">&#10003; <?php esc_html_e( 'Where It Links', 'rapm' ); ?></div>
										<div class="mk-step is-on">&#9315; <?php esc_html_e( 'Review & Schedule', 'rapm' ); ?></div>
									</div>
									<div class="mk-card">
										<div class="mk-btn mk-btn-ghost" style="margin-right:6px;"><?php esc_html_e( 'Desktop', 'rapm' ); ?></div>
										<div class="mk-btn mk-btn-ghost"><?php esc_html_e( 'Mobile', 'rapm' ); ?></div>
										<div class="mk-preview" style="margin-top:8px;"><span>FALL SALE &mdash; 30% OFF</span></div>
									</div>
									<div class="mk-card">
										<div class="mk-label"><span class="rapm-tg-chip" style="width:18px;height:18px;font-size:10px;">1</span><?php esc_html_e( 'Start showing on', 'rapm' ); ?></div>
										<div class="mk-input"></div>
										<div class="mk-label" style="margin-top:8px;"><?php esc_html_e( 'Stop showing on', 'rapm' ); ?></div>
										<div class="mk-input"></div>
										<div class="mk-note" style="margin-top:8px;background:#edfaef;border-left-color:#00a32a;color:#00450c;"><span class="rapm-tg-chip" style="width:18px;height:18px;font-size:10px;background:#00a32a;flex:none;">2</span><span><?php esc_html_e( 'This will show up on the Promotions Calendar...', 'rapm' ); ?></span></div>
									</div>
									<div class="mk-btn mk-btn-primary"><?php esc_html_e( 'Save Asset', 'rapm' ); ?></div>
								</div>
							</div>
							<div class="rapm-tg-callouts-col">
								<ol>
									<li><span class="rapm-tg-chip">&#10003;</span><span class="rapm-tg-txt"><strong><?php esc_html_e( 'Check the preview.', 'rapm' ); ?></strong><span><?php esc_html_e( 'Click the Desktop / Mobile buttons to see exactly what each visitor will see. If text overlaps a picture, fix it before saving.', 'rapm' ); ?></span></span></li>
									<li><span class="rapm-tg-chip">1</span><span class="rapm-tg-txt"><strong><?php esc_html_e( 'Start and stop dates are optional.', 'rapm' ); ?></strong><span><?php esc_html_e( 'Leave blank to start right away and run until you turn it off yourself.', 'rapm' ); ?></span></span></li>
									<li><span class="rapm-tg-chip">2</span><span class="rapm-tg-txt"><strong><?php esc_html_e( 'Watch this note.', 'rapm' ); ?></strong><span><?php esc_html_e( "It tells you right away whether this promotion will show up on the Promotions Calendar (see §14) or not, based on whether you've filled in both dates. If you expect to see it there and this note says it won't, that's your sign a date is missing.", 'rapm' ); ?></span></span></li>
								</ol>
								<div class="rapm-tg-callout rapm-tg-callout-tip" style="margin-top:12px;">
									<span class="rapm-tg-callout-title"><?php esc_html_e( "You'll know it worked", 'rapm' ); ?></span>
									<p><?php esc_html_e( 'After clicking Save, a green message confirms it — and the box below Step 1 always shows the exact code needed to put it on a page.', 'rapm' ); ?></p>
								</div>
							</div>
						</div>
					</section>

					<section class="rapm-tg-section" id="rapm-tg-editing">
						<p class="rapm-tg-kicker"><?php esc_html_e( 'Making a change', 'rapm' ); ?></p>
						<h2><?php esc_html_e( 'Changing a Promotion That Already Exists', 'rapm' ); ?></h2>
						<p class="rapm-tg-intro"><?php esc_html_e( "Editing works differently on purpose — you don't have to click through every step again to fix one small thing.", 'rapm' ); ?></p>
						<ul class="rapm-tg-plain">
							<li><span><?php esc_html_e( 'Find it on the', 'rapm' ); ?> <strong><?php esc_html_e( 'Promotions', 'rapm' ); ?></strong> <?php esc_html_e( 'page and click its picture.', 'rapm' ); ?></span></li>
							<li><span><?php esc_html_e( 'Every section shows on one page — nothing is hidden. Scroll to whatever you want to change.', 'rapm' ); ?></span></li>
							<li><span><?php esc_html_e( 'The 4 numbered boxes at the top still work — click one to jump straight down to that part of the page.', 'rapm' ); ?></span></li>
							<li><span><?php esc_html_e( 'Click', 'rapm' ); ?> <strong><?php esc_html_e( 'Save Asset', 'rapm' ); ?></strong> <?php esc_html_e( "when you're done.", 'rapm' ); ?></span></li>
						</ul>
					</section>

					<section class="rapm-tg-section" id="rapm-tg-calendar">
						<p class="rapm-tg-kicker"><?php esc_html_e( 'A second way to show promotions', 'rapm' ); ?></p>
						<h2><?php esc_html_e( 'Showing a Calendar of Your Promotions', 'rapm' ); ?></h2>
						<p class="rapm-tg-intro"><?php esc_html_e( 'Ask whoever manages the website to add [rapm_promotions_calendar] to a page. It automatically shows every promotion that has both a start date and an end date set — nothing else to configure.', 'rapm' ); ?></p>
						<ul class="rapm-tg-plain">
							<li><span><?php esc_html_e( "Each promotion shows as its own named bar, stretching across the days it runs — like a trip or event on a personal calendar app — so a visitor sees what's happening without clicking anything.", 'rapm' ); ?></span></li>
							<li><span><?php esc_html_e( "It's a good way to let potential customers see everything you have scheduled at a glance, in one place, without you maintaining a second list by hand.", 'rapm' ); ?></span></li>
							<li><span><?php esc_html_e( 'It updates itself automatically as you add, edit, or reschedule promotions elsewhere in the tool — nothing to keep in sync yourself.', 'rapm' ); ?></span></li>
							<li><span><?php esc_html_e( 'The bottom of the', 'rapm' ); ?> <strong><?php esc_html_e( 'Promotions', 'rapm' ); ?></strong> <?php esc_html_e( 'page lists the pages that already have the calendar, and how many promotions are on it right now.', 'rapm' ); ?></span></li>
							<li><span><?php esc_html_e( "Its color automatically matches this website's own brand color, if the site is built with Elementor. Whoever manages the website can set an exact color instead in Promo Manager's Settings, under Brand Color.", 'rapm' ); ?></span></li>
						</ul>
						<div class="rapm-tg-callout rapm-tg-callout-tip">
							<span class="rapm-tg-callout-title"><?php esc_html_e( 'How do I know if a promotion will be on it?', 'rapm' ); ?></span>
							<p><?php esc_html_e( 'On the last step of the Add/Edit form, right below the start/stop date fields, a note tells you directly whether that specific promotion will show up on the calendar or not — and updates the moment you add or remove a date. See §12, Step 4.', 'rapm' ); ?></p>
						</div>
					</section>

					<section class="rapm-tg-section" id="rapm-tg-sizes">
						<p class="rapm-tg-kicker"><?php esc_html_e( 'Reference', 'rapm' ); ?></p>
						<h2><?php esc_html_e( 'Picture Size Cheat Sheet', 'rapm' ); ?></h2>
						<p class="rapm-tg-intro"><?php esc_html_e( 'Have your picture ready in one of these sizes before you start, and uploading goes faster.', 'rapm' ); ?></p>
						<div class="rapm-tg-table-wrap">
							<table class="rapm-tg-sizes">
								<thead><tr><th><?php esc_html_e( 'Type', 'rapm' ); ?></th><th><?php esc_html_e( 'Desktop size', 'rapm' ); ?></th><th><?php esc_html_e( 'Mobile size', 'rapm' ); ?></th><th><?php esc_html_e( 'Max file size', 'rapm' ); ?></th></tr></thead>
								<tbody>
									<tr><td><?php esc_html_e( 'Slider', 'rapm' ); ?></td><td>1920 &times; 600 px</td><td>1080 &times; 1920 px</td><td>300 KB</td></tr>
									<tr><td><?php esc_html_e( 'Shop the Look', 'rapm' ); ?></td><td><?php esc_html_e( 'Any shape, at least 800 px wide (2000+ is best)', 'rapm' ); ?></td><td><?php esc_html_e( 'Same photo', 'rapm' ); ?></td><td>450 KB</td></tr>
									<tr><td><?php esc_html_e( 'Feature banner', 'rapm' ); ?></td><td>1920 &times; 300 px</td><td>1080 &times; 400 px</td><td>200 KB</td></tr>
									<tr><td><?php esc_html_e( 'Coupon row', 'rapm' ); ?></td><td>600 &times; 750 px</td><td>600 &times; 750 px</td><td>150 KB</td></tr>
									<tr><td><?php esc_html_e( 'Tile row', 'rapm' ); ?></td><td>1080 &times; 1080 px</td><td>1080 &times; 1080 px</td><td>200 KB</td></tr>
								</tbody>
							</table>
						</div>

						<h3><?php esc_html_e( "What if my picture doesn't match?", 'rapm' ); ?></h3>
						<div class="rapm-tg-pair">
							<div class="rapm-tg-box rapm-tg-good">
								<div class="rapm-tg-lbl">&#10003; <?php esc_html_e( 'Same shape, different size', 'rapm' ); ?></div>
								<p><?php esc_html_e( 'Example: your picture is 3840×1200 instead of 1920×600 — same proportions, just bigger. The tool resizes it for you automatically. Nothing to fix.', 'rapm' ); ?></p>
							</div>
							<div class="rapm-tg-box rapm-tg-bad">
								<div class="rapm-tg-lbl">&#10005; <?php esc_html_e( 'A genuinely different shape', 'rapm' ); ?></div>
								<p><?php esc_html_e( "Example: a square photo where a wide banner is needed. A small 9-button grid appears — click the part of the picture to keep, and it's cropped to fit.", 'rapm' ); ?></p>
							</div>
						</div>
					</section>

					<section class="rapm-tg-section" id="rapm-tg-sliders">
						<p class="rapm-tg-kicker"><?php esc_html_e( 'Seeing the big picture', 'rapm' ); ?></p>
						<h2><?php esc_html_e( 'The Promotions Page', 'rapm' ); ?></h2>
						<p class="rapm-tg-intro"><?php esc_html_e( 'Click', 'rapm' ); ?> <strong><?php esc_html_e( 'Promotions', 'rapm' ); ?></strong> <?php esc_html_e( 'on the left menu to see everything at a glance.', 'rapm' ); ?></p>
						<ul class="rapm-tg-plain">
							<li><span><?php esc_html_e( 'Each section is one spot on your website, like "Home page slider," and says which pages it shows on. Its promotions take turns showing there.', 'rapm' ); ?></span></li>
							<li><span><?php esc_html_e( 'Each picture says whether it is showing now, hidden, starting later, or ended, and when it ends.', 'rapm' ); ?></span></li>
							<li><span><?php esc_html_e( 'Click the switch on a picture to take it off the website without deleting it. Blue means showing, gray means hidden.', 'rapm' ); ?></span></li>
							<li><span><?php esc_html_e( 'Drag a picture to change the order it plays in, or use "Move earlier" and "Move later" in its three-dot menu. It saves by itself.', 'rapm' ); ?></span></li>
							<li><span><?php esc_html_e( 'In a Shop the Look section, each picture is one tab, and the gray line above the pictures shows the tabs on the website in order. Each card also shows its tab name.', 'rapm' ); ?></span></li>
							<li><span><?php esc_html_e( 'Use', 'rapm' ); ?> <strong><?php esc_html_e( 'Make a copy', 'rapm' ); ?></strong> <?php esc_html_e( "in the three-dot menu to start a new one that's almost the same, instead of typing everything from scratch. The copy starts hidden, so switch it on when it's ready.", 'rapm' ); ?></span></li>
						</ul>
					</section>

					<section class="rapm-tg-section" id="rapm-tg-problems">
						<p class="rapm-tg-kicker"><?php esc_html_e( 'If something looks wrong', 'rapm' ); ?></p>
						<h2><?php esc_html_e( 'Common Problems, Explained', 'rapm' ); ?></h2>

						<div class="rapm-tg-problem">
							<div class="rapm-tg-q"><span class="rapm-tg-chip">?</span><?php esc_html_e( 'My picture got rejected.', 'rapm' ); ?></div>
							<p class="rapm-tg-a"><?php esc_html_e( 'Either pick a different part of the picture using the grid that appears, or crop/re-export the picture to the size shown in the Cheat Sheet above.', 'rapm' ); ?></p>
						</div>
						<div class="rapm-tg-problem">
							<div class="rapm-tg-q"><span class="rapm-tg-chip">?</span><?php esc_html_e( "I saved it, but I don't see it on the website.", 'rapm' ); ?></div>
							<p class="rapm-tg-a"><?php esc_html_e( 'On the Promotions page, check that its switch is on (blue) and that its section says which pages it shows on. If the section says "Not on any page yet," ask whoever manages the website to place it.', 'rapm' ); ?></p>
						</div>
						<div class="rapm-tg-problem">
							<div class="rapm-tg-q"><span class="rapm-tg-chip">?</span><?php esc_html_e( 'I\'m not sure which spot to pick in "Where it shows."', 'rapm' ); ?></div>
							<p class="rapm-tg-a"><?php esc_html_e( 'Start from the Promotions page instead: find the section where it should show and click "Add a picture." The spot is picked for you.', 'rapm' ); ?></p>
						</div>
						<div class="rapm-tg-problem">
							<div class="rapm-tg-q"><span class="rapm-tg-chip">?</span><?php esc_html_e( "My headline and my picture's own text overlap.", 'rapm' ); ?></div>
							<p class="rapm-tg-a"><?php esc_html_e( 'Go back to Step 2 and answer "Yes" to "does your picture already show the price or sale" — that hides the typed text so only the picture\'s own text shows.', 'rapm' ); ?></p>
						</div>
						<div class="rapm-tg-problem">
							<div class="rapm-tg-q"><span class="rapm-tg-chip">?</span><?php esc_html_e( 'A dot on my Shop the Look photo doesn\'t show on phones.', 'rapm' ); ?></div>
							<p class="rapm-tg-a"><?php esc_html_e( 'Phones show a squarer part of the photo, so a dot near the edge can fall outside it. Open the look and go to "Place the Pieces." Its row says "Cut off on phones." Move the dot, or change "Which part to keep." The small Phone box shows the dots that fit.', 'rapm' ); ?></p>
						</div>
					</section>

					<section class="rapm-tg-section" id="rapm-tg-checklist">
						<p class="rapm-tg-kicker"><?php esc_html_e( 'Print this', 'rapm' ); ?></p>
						<h2><?php esc_html_e( 'Quick Checklist', 'rapm' ); ?></h2>
						<p class="rapm-tg-intro"><?php esc_html_e( "Once you've done this a few times, use this as a reminder instead of the full guide.", 'rapm' ); ?></p>
						<ul class="rapm-tg-checklist">
							<li><span class="rapm-tg-box2"></span><span><?php esc_html_e( 'Picked the correct Type before uploading anything', 'rapm' ); ?></span></li>
							<li><span class="rapm-tg-box2"></span><span><?php esc_html_e( "Typed an Internal Name I'll recognize later", 'rapm' ); ?></span></li>
							<li><span class="rapm-tg-box2"></span><span><?php esc_html_e( 'Uploaded a Desktop picture in the right size', 'rapm' ); ?></span></li>
							<li><span class="rapm-tg-box2"></span><span><?php esc_html_e( 'Uploaded a Mobile picture (or left it blank on purpose)', 'rapm' ); ?></span></li>
							<li><span class="rapm-tg-box2"></span><span><?php esc_html_e( 'Answered the sale-text question correctly', 'rapm' ); ?></span></li>
							<li><span class="rapm-tg-box2"></span><span><?php esc_html_e( 'Chosen where it links to', 'rapm' ); ?></span></li>
							<li><span class="rapm-tg-box2"></span><span><?php esc_html_e( 'Checked the Desktop and Mobile preview — no overlapping text', 'rapm' ); ?></span></li>
							<li><span class="rapm-tg-box2"></span><span><?php esc_html_e( 'Set a start/end date, if this is time-limited', 'rapm' ); ?></span></li>
							<li><span class="rapm-tg-box2"></span><span><?php esc_html_e( 'Clicked Save and saw the green confirmation', 'rapm' ); ?></span></li>
						</ul>
					</section>

				</main>
			</div>

			<div class="rapm-tg-footer" id="rapm-tg-help">
				<div>
					<strong><?php esc_html_e( 'Still stuck?', 'rapm' ); ?></strong>
					<?php
					printf(
						/* translators: %s: URL to the Help & FAQ admin page */
						esc_html__( 'Open %s inside WordPress — it has short answers to specific questions, or ask whoever manages the website.', 'rapm' ),
						'<a href="' . esc_url( $help_url ) . '"><strong>' . esc_html__( 'Help & FAQ', 'rapm' ) . '</strong></a>'
					);
					?>
				</div>
				<div><?php esc_html_e( 'Promo Manager Training Guide · RA Marketing', 'rapm' ); ?></div>
			</div>
		</div>
		<?php
	}
}
