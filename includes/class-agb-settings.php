<?php
/**
 * Settings page for Auto Generate Blog by ideaBoss.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AGB_Settings {

	public function __construct() {
		add_action( 'admin_menu',                                          array( $this, 'add_settings_page' ) );
		add_action( 'admin_init',                                          array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . AGB_PLUGIN_BASENAME,         array( $this, 'add_settings_link' ) );
	}

	/* -----------------------------------------------------------------------
	 * Admin menu
	 * -------------------------------------------------------------------- */

	public function add_settings_page() {
		add_options_page(
			'Auto Generate Blog Settings',
			'Auto Generate Blog',
			'manage_options',
			'agb-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/* -----------------------------------------------------------------------
	 * Register all settings
	 * -------------------------------------------------------------------- */

	public function register_settings() {
		$group = 'agb_settings_group';

		// --- API & Model ---------------------------------------------------
		register_setting( $group, 'agb_claude_api_key',    array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
		register_setting( $group, 'agb_claude_model',      array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'claude-sonnet-4-6' ) );
		register_setting( $group, 'agb_max_tokens_ai',     array( 'sanitize_callback' => 'absint',              'default' => 4096 ) );
		register_setting( $group, 'agb_max_tokens_url',    array( 'sanitize_callback' => 'absint',              'default' => 4096 ) );

		// --- Post Defaults -------------------------------------------------
		register_setting( $group, 'agb_post_status',       array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'draft' ) );
		register_setting( $group, 'agb_default_author',    array( 'sanitize_callback' => 'absint',              'default' => 0 ) );
		register_setting( $group, 'agb_default_category',  array( 'sanitize_callback' => 'absint',              'default' => 0 ) );
		register_setting( $group, 'agb_auto_tag',          array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );

		// --- AI Generate --------------------------------------------------
		register_setting( $group, 'agb_default_website',   array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
		register_setting( $group, 'agb_homepage_url',      array( 'sanitize_callback' => 'esc_url_raw',         'default' => get_site_url() ) );

		// --- URL Import ---------------------------------------------------
		register_setting( $group, 'agb_word_limit',          array( 'sanitize_callback' => 'absint',              'default' => 800 ) );
		register_setting( $group, 'agb_max_article_words',   array( 'sanitize_callback' => 'absint',              'default' => 2500 ) );
		register_setting( $group, 'agb_attribution_template',array( 'sanitize_callback' => 'wp_kses_post',        'default' => '<em>Original <a href="{article_url}">article</a> published on <a href="{domain_url}">{domain}</a></em>' ) );
		register_setting( $group, 'agb_fetch_timeout',       array( 'sanitize_callback' => 'absint',              'default' => 30 ) );
		register_setting( $group, 'agb_fetch_user_agent',    array( 'sanitize_callback' => 'sanitize_textarea_field', 'default' => '' ) );

		// --- SEO Integration ----------------------------------------------
		register_setting( $group, 'agb_seo_plugin',         array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'auto' ) );
		register_setting( $group, 'agb_enable_seo',         array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '1' ) );

		// --- Theme Integration --------------------------------------------
		register_setting( $group, 'agb_hide_featured_image_key', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '_hide_featured_image' ) );
		register_setting( $group, 'agb_auto_hide_featured',      array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '1' ) );

		// --- Advanced -----------------------------------------------------
		register_setting( $group, 'agb_debug_mode', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '0' ) );
	}

	/* -----------------------------------------------------------------------
	 * Render settings page
	 * -------------------------------------------------------------------- */

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$api_key = get_option( 'agb_claude_api_key', '' );

		// Get all users for author dropdown
		$users = get_users( array( 'role__in' => array( 'administrator', 'editor', 'author' ), 'orderby' => 'display_name' ) );

		// Get all categories for default category dropdown
		$categories = get_categories( array( 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ) );
		?>
		<div class="wrap">
			<h1 style="display:flex;align-items:center;gap:10px;">
				✨ Auto Generate Blog
				<span style="font-size:13px;font-weight:400;color:#666;">by <a href="https://ideaboss.io" target="_blank">ideaBoss</a> &mdash; v<?php echo esc_html( AGB_VERSION ); ?></span>
			</h1>

			<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><strong>Settings saved.</strong></p></div>
			<?php endif; ?>

			<?php if ( empty( $api_key ) ) : ?>
				<div class="notice notice-warning">
					<p>⚠️ <strong>Claude API key required.</strong> Add your key below to start generating posts.
					Get one at <a href="https://console.anthropic.com" target="_blank">console.anthropic.com</a>.</p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'agb_settings_group' ); ?>

				<?php $this->section_header( '🔑 API &amp; Model', 'api' ); ?>
				<table class="form-table" role="presentation">

					<tr>
						<th scope="row"><label for="agb_claude_api_key">Claude API Key</label></th>
						<td>
							<input type="password" id="agb_claude_api_key" name="agb_claude_api_key"
								value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" autocomplete="off" />
							<p class="description">Your Anthropic API key. Get one at <a href="https://console.anthropic.com" target="_blank">console.anthropic.com</a>.</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="agb_claude_model">Claude Model</label></th>
						<td>
							<select id="agb_claude_model" name="agb_claude_model">
								<option value="claude-sonnet-4-6" <?php selected( get_option( 'agb_claude_model', 'claude-sonnet-4-6' ), 'claude-sonnet-4-6' ); ?>>
									Claude Sonnet 4.6 — Recommended (fast + high quality)
								</option>
								<option value="claude-opus-4-6" <?php selected( get_option( 'agb_claude_model', 'claude-sonnet-4-6' ), 'claude-opus-4-6' ); ?>>
									Claude Opus 4.6 — Most powerful (slower, higher cost)
								</option>
								<option value="claude-haiku-4-5-20251001" <?php selected( get_option( 'agb_claude_model', 'claude-sonnet-4-6' ), 'claude-haiku-4-5-20251001' ); ?>>
									Claude Haiku 4.5 — Fastest (lower cost, good for simple posts)
								</option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="agb_max_tokens_ai">AI Generate — Max Tokens</label></th>
						<td>
							<input type="number" id="agb_max_tokens_ai" name="agb_max_tokens_ai"
								value="<?php echo esc_attr( get_option( 'agb_max_tokens_ai', 4096 ) ); ?>"
								min="1000" max="8192" step="256" style="width:100px;" /> tokens
							<p class="description">Maximum length of Claude's response when generating AI posts. Higher = longer posts; lower = faster. Default: 4096.</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="agb_max_tokens_url">URL Import — Max Tokens</label></th>
						<td>
							<input type="number" id="agb_max_tokens_url" name="agb_max_tokens_url"
								value="<?php echo esc_attr( get_option( 'agb_max_tokens_url', 4096 ) ); ?>"
								min="1000" max="8192" step="256" style="width:100px;" /> tokens
							<p class="description">Maximum length of Claude's response when importing URLs. Default: 4096.</p>
						</td>
					</tr>

				</table>

				<?php $this->section_header( '📝 Post Defaults', 'post-defaults' ); ?>
				<table class="form-table" role="presentation">

					<tr>
						<th scope="row">Default Post Status</th>
						<td>
							<fieldset>
								<label style="display:block;margin-bottom:6px;">
									<input type="radio" name="agb_post_status" value="draft"
										<?php checked( get_option( 'agb_post_status', 'draft' ), 'draft' ); ?> />
									&nbsp;Save as Draft <em style="color:#666;">(recommended — review before publishing)</em>
								</label>
								<label style="display:block;">
									<input type="radio" name="agb_post_status" value="publish"
										<?php checked( get_option( 'agb_post_status', 'draft' ), 'publish' ); ?> />
									&nbsp;Publish Immediately
								</label>
							</fieldset>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="agb_default_author">Default Post Author</label></th>
						<td>
							<select id="agb_default_author" name="agb_default_author">
								<option value="0">— Use current user —</option>
								<?php foreach ( $users as $user ) : ?>
									<option value="<?php echo esc_attr( $user->ID ); ?>"
										<?php selected( get_option( 'agb_default_author', 0 ), $user->ID ); ?>>
										<?php echo esc_html( $user->display_name ); ?> (<?php echo esc_html( $user->user_login ); ?>)
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">All generated posts will be assigned to this author. Leave on "current user" to use whoever is logged in.</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="agb_default_category">Default Category</label></th>
						<td>
							<select id="agb_default_category" name="agb_default_category">
								<option value="0">— No default (select per post) —</option>
								<?php foreach ( $categories as $cat ) : ?>
									<option value="<?php echo esc_attr( $cat->term_id ); ?>"
										<?php selected( get_option( 'agb_default_category', 0 ), $cat->term_id ); ?>>
										<?php echo esc_html( $cat->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">This category will be pre-selected in the category dropdown when writing a new post. You can still override it per post.</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="agb_auto_tag">Auto-Tag Generated Posts</label></th>
						<td>
							<input type="text" id="agb_auto_tag" name="agb_auto_tag"
								value="<?php echo esc_attr( get_option( 'agb_auto_tag', '' ) ); ?>"
								class="regular-text" placeholder="e.g. ai-generated" />
							<p class="description">Automatically add this tag to every generated post. Leave blank to skip tagging. Multiple tags: separate with commas.</p>
						</td>
					</tr>

				</table>

				<?php $this->section_header( '🤖 AI Generate', 'ai-generate' ); ?>
				<table class="form-table" role="presentation">

					<tr>
						<th scope="row"><label for="agb_default_website">Default Website Name</label></th>
						<td>
							<input type="text" id="agb_default_website" name="agb_default_website"
								value="<?php echo esc_attr( get_option( 'agb_default_website', '' ) ); ?>"
								class="regular-text" placeholder="e.g. ideaboss.io" autocomplete="off" />
							<p class="description">Pre-fills the "Website" field in the AI Generate tab on every post. Saves you retyping it each time.</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="agb_homepage_url">Your Homepage URL</label></th>
						<td>
							<input type="url" id="agb_homepage_url" name="agb_homepage_url"
								value="<?php echo esc_attr( get_option( 'agb_homepage_url', get_site_url() ) ); ?>"
								class="regular-text" placeholder="https://ideaboss.io" />
							<p class="description">Used in attribution footers and contextual references.</p>
						</td>
					</tr>

				</table>

				<?php $this->section_header( '🔗 URL Import', 'url-import' ); ?>
				<table class="form-table" role="presentation">

					<tr>
						<th scope="row"><label for="agb_word_limit">Article Word Limit</label></th>
						<td>
							<input type="number" id="agb_word_limit" name="agb_word_limit"
								value="<?php echo esc_attr( get_option( 'agb_word_limit', 800 ) ); ?>"
								min="200" max="5000" step="100" style="width:100px;" /> words
							<p class="description">Imported articles longer than this will be truncated and a "Click here to continue reading" link appended. Default: 800.</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="agb_max_article_words">Max Words Sent to Claude</label></th>
						<td>
							<input type="number" id="agb_max_article_words" name="agb_max_article_words"
								value="<?php echo esc_attr( get_option( 'agb_max_article_words', 2500 ) ); ?>"
								min="500" max="10000" step="100" style="width:100px;" /> words
							<p class="description">How much of the fetched article is sent to the Claude API. Larger = more context; smaller = faster, lower cost. Default: 2500.</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="agb_attribution_template">Attribution Footer Template</label></th>
						<td>
							<input type="text" id="agb_attribution_template" name="agb_attribution_template"
								value="<?php echo esc_attr( get_option( 'agb_attribution_template', '<em>Original <a href="{article_url}">article</a> published on <a href="{domain_url}">{domain}</a></em>' ) ); ?>"
								class="large-text" />
							<p class="description">
								Template for the footer appended to every imported article. Available placeholders:<br>
								<code>{article_url}</code> — full URL to the original article &nbsp;|&nbsp;
								<code>{domain_url}</code> — source site homepage URL &nbsp;|&nbsp;
								<code>{domain}</code> — source domain name (e.g. <em>reuters.com</em>)
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="agb_fetch_timeout">Fetch Timeout</label></th>
						<td>
							<input type="number" id="agb_fetch_timeout" name="agb_fetch_timeout"
								value="<?php echo esc_attr( get_option( 'agb_fetch_timeout', 30 ) ); ?>"
								min="5" max="120" step="5" style="width:80px;" /> seconds
							<p class="description">How long to wait when fetching an article URL. Increase this for slow sites. Default: 30.</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="agb_fetch_user_agent">Fetch User Agent</label></th>
						<td>
							<textarea id="agb_fetch_user_agent" name="agb_fetch_user_agent"
								rows="2" class="large-text"
								placeholder="Leave blank to use the default Chrome user agent"><?php echo esc_textarea( get_option( 'agb_fetch_user_agent', '' ) ); ?></textarea>
							<p class="description">The browser user-agent string sent when fetching article URLs. Leave blank for the built-in default (Chrome on Windows). Some sites block requests with non-browser user agents.</p>
						</td>
					</tr>

				</table>

				<?php $this->section_header( '🔍 SEO Integration', 'seo' ); ?>
				<table class="form-table" role="presentation">

					<tr>
						<th scope="row">Auto-Fill SEO Fields</th>
						<td>
							<label>
								<input type="checkbox" name="agb_enable_seo" value="1"
									<?php checked( get_option( 'agb_enable_seo', '1' ), '1' ); ?> />
								Automatically fill in focus keyphrase and meta description after generating a post
							</label>
							<p class="description">Requires Yoast SEO, Rank Math, or AIOSEO to be active.</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="agb_seo_plugin">SEO Plugin</label></th>
						<td>
							<select id="agb_seo_plugin" name="agb_seo_plugin">
								<option value="auto"       <?php selected( get_option( 'agb_seo_plugin', 'auto' ), 'auto' ); ?>>Auto-detect (try all)</option>
								<option value="yoast"      <?php selected( get_option( 'agb_seo_plugin', 'auto' ), 'yoast' ); ?>>Yoast SEO</option>
								<option value="rankmath"   <?php selected( get_option( 'agb_seo_plugin', 'auto' ), 'rankmath' ); ?>>Rank Math</option>
								<option value="aioseo"     <?php selected( get_option( 'agb_seo_plugin', 'auto' ), 'aioseo' ); ?>>AIOSEO</option>
								<option value="none"       <?php selected( get_option( 'agb_seo_plugin', 'auto' ), 'none' ); ?>>None / Disabled</option>
							</select>
							<p class="description">Select your SEO plugin to ensure the keyphrase and meta description are saved to the correct database fields. "Auto-detect" writes to all common meta keys simultaneously.</p>
						</td>
					</tr>

				</table>

				<?php $this->section_header( '🎨 Theme Integration', 'theme' ); ?>
				<table class="form-table" role="presentation">

					<tr>
						<th scope="row">Auto-Hide Featured Image</th>
						<td>
							<label>
								<input type="checkbox" name="agb_auto_hide_featured" value="1"
									<?php checked( get_option( 'agb_auto_hide_featured', '1' ), '1' ); ?> />
								Automatically hide the featured image on generated posts
							</label>
							<p class="description">Since generated posts use AI content without a matching featured image, this hides the featured image placeholder on the post page.</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="agb_hide_featured_image_key">Hide Featured Image — Meta Key</label></th>
						<td>
							<input type="text" id="agb_hide_featured_image_key" name="agb_hide_featured_image_key"
								value="<?php echo esc_attr( get_option( 'agb_hide_featured_image_key', '_hide_featured_image' ) ); ?>"
								class="regular-text" />
							<p class="description">
								The primary post meta key your theme uses to hide the featured image. Default: <code>_hide_featured_image</code>.
								The plugin also tries common alternatives automatically (<code>_hidden_featured_image</code>, <code>_genesis_hide_image</code>, <code>_kadence_blocks_hide_featured_image</code>, <code>astra_featured_img_enabled</code>).
								Check your theme documentation if hiding still doesn't work.
							</p>
						</td>
					</tr>

				</table>

				<?php $this->section_header( '⚙️ Advanced', 'advanced' ); ?>
				<table class="form-table" role="presentation">

					<tr>
						<th scope="row">Debug Mode</th>
						<td>
							<label>
								<input type="checkbox" name="agb_debug_mode" value="1"
									<?php checked( get_option( 'agb_debug_mode', '0' ), '1' ); ?> />
								Log Claude API request details to the WordPress debug log
							</label>
							<p class="description">
								Requires <code>WP_DEBUG</code> and <code>WP_DEBUG_LOG</code> to be enabled in <code>wp-config.php</code>.
								Logs are written to <code>wp-content/debug.log</code>. Disable when not troubleshooting.
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">Plugin Version</th>
						<td>
							<code><?php echo esc_html( AGB_VERSION ); ?></code>
							&nbsp;
							<a href="https://github.com/dylanfostercoxgp/auto-generate-blog-ideaboss/releases" target="_blank">View changelog on GitHub →</a>
							<p class="description">Updates are delivered automatically via WordPress when a new version is released.</p>
						</td>
					</tr>

				</table>

				<?php submit_button( 'Save Settings' ); ?>
			</form>

			<hr style="margin-top:30px;" />
			<p style="color:#999;font-size:12px;">
				Auto Generate Blog by <a href="https://ideaboss.io" target="_blank" style="color:#999;">ideaBoss</a> &mdash; v<?php echo esc_html( AGB_VERSION ); ?>
			</p>
		</div>

		<style>
		.agb-settings-section-header {
			display: flex;
			align-items: center;
			gap: 8px;
			margin: 28px 0 0;
			padding: 10px 0 6px;
			border-bottom: 2px solid #e0e0e0;
			font-size: 15px;
			font-weight: 600;
			color: #1d2327;
		}
		.agb-settings-section-header + .form-table {
			margin-top: 4px;
		}
		</style>
		<?php
	}

	/* -----------------------------------------------------------------------
	 * Helper: render a section heading between tables
	 * -------------------------------------------------------------------- */

	private function section_header( $label, $id = '' ) {
		echo '<h2 class="agb-settings-section-header"' . ( $id ? ' id="agb-section-' . esc_attr( $id ) . '"' : '' ) . '>'
		     . wp_kses_post( $label ) . '</h2>';
	}

	/* -----------------------------------------------------------------------
	 * Helper: add Settings link on plugin list page
	 * -------------------------------------------------------------------- */

	public function add_settings_link( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=agb-settings' ) ) . '">Settings</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/* -----------------------------------------------------------------------
	 * Static helper to get a setting value
	 * -------------------------------------------------------------------- */

	public static function get( $key, $default = '' ) {
		return get_option( 'agb_' . $key, $default );
	}
}
