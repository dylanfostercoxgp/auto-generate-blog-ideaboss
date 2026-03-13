<?php
/**
 * Post Generator — metabox UI, AJAX handlers, and post-save meta hook.
 * Part of Auto Generate Blog by ideaBoss.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AGB_Post_Generator {

	public function __construct() {
		add_action( 'add_meta_boxes',        array( $this, 'add_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'save_post',             array( $this, 'save_post_meta' ), 10, 2 );

		// AJAX handlers
		add_action( 'wp_ajax_agb_generate_ai', array( $this, 'ajax_generate_ai' ) );
		add_action( 'wp_ajax_agb_import_url',  array( $this, 'ajax_import_url' ) );
	}

	/* -----------------------------------------------------------------------
	 * Register the metabox
	 * -------------------------------------------------------------------- */

	public function add_meta_box( $post_type ) {
		if ( 'post' !== $post_type ) return;

		add_meta_box(
			'agb-generator',
			'✨ Generate New Post &nbsp;<span style="font-size:11px;font-weight:400;color:#999;">Auto Generate Blog by ideaBoss</span>',
			array( $this, 'render_meta_box' ),
			'post',
			'normal',
			'high'
		);
	}

	/* -----------------------------------------------------------------------
	 * Enqueue scripts & styles
	 * -------------------------------------------------------------------- */

	public function enqueue_scripts( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) return;

		global $post;
		if ( isset( $post ) && 'post' !== $post->post_type ) return;

		wp_enqueue_style(
			'agb-admin-css',
			AGB_PLUGIN_URL . 'admin/css/agb-admin.css',
			array(),
			AGB_VERSION
		);

		wp_enqueue_script(
			'agb-admin-js',
			AGB_PLUGIN_URL . 'admin/js/agb-admin.js',
			array( 'jquery' ),
			AGB_VERSION,
			true
		);

		wp_localize_script( 'agb-admin-js', 'agb_data', array(
			'ajax_url'           => admin_url( 'admin-ajax.php' ),
			'nonce'              => wp_create_nonce( 'agb_nonce' ),
			'is_configured'      => (bool) get_option( 'agb_claude_api_key' ),
			'settings_url'       => admin_url( 'options-general.php?page=agb-settings' ),
			'default_website'    => get_option( 'agb_default_website', '' ),
		) );
	}

	/* -----------------------------------------------------------------------
	 * Render the metabox HTML
	 * -------------------------------------------------------------------- */

	public function render_meta_box( $post ) {
		$api_configured      = ! empty( get_option( 'agb_claude_api_key' ) );
		$default_website     = get_option( 'agb_default_website', '' );
		$default_category_id = absint( get_option( 'agb_default_category', 0 ) );

		// Build category list
		$categories = get_categories( array(
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		) );

		// Current category on existing post — prefers the metabox default if new post
		$current_cats   = wp_get_post_categories( $post->ID );
		$current_cat_id = ! empty( $current_cats ) ? intval( $current_cats[0] ) : $default_category_id;

		wp_nonce_field( 'agb_save_post_meta', 'agb_meta_nonce' );
		?>
		<div id="agb-container">

			<?php if ( ! $api_configured ) : ?>
				<div class="agb-notice agb-notice-warning">
					⚠️ <strong>Claude API key not set.</strong>
					<a href="<?php echo esc_url( admin_url( 'options-general.php?page=agb-settings' ) ); ?>">Configure it in Settings →</a>
				</div>
			<?php endif; ?>

			<!-- Category selector -->
			<div class="agb-row">
				<label class="agb-label" for="agb-category">📁 Category</label>
				<select id="agb-category" name="agb_category_id" class="agb-select">
					<option value="0">— Select a category —</option>
					<?php foreach ( $categories as $cat ) : ?>
						<option value="<?php echo esc_attr( $cat->term_id ); ?>"
							<?php selected( $current_cat_id, $cat->term_id ); ?>>
							<?php echo esc_html( $cat->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<!-- Tabs -->
			<div class="agb-tabs">
				<button type="button" class="agb-tab agb-tab-active" data-tab="ai-generate">🤖 AI Generate</button>
				<button type="button" class="agb-tab" data-tab="url-import">🔗 Import from URL</button>
			</div>

			<!-- AI Generate tab -->
			<div id="agb-tab-ai-generate" class="agb-tab-content agb-tab-content-active">

				<div class="agb-field-group">
					<label class="agb-field-label" for="agb-website">Website</label>
					<input
						type="text"
						id="agb-website"
						placeholder="e.g. ideaboss.io"
						value="<?php echo esc_attr( $default_website ); ?>"
						autocomplete="off"
					/>
				</div>

				<div class="agb-field-group">
					<label class="agb-field-label" for="agb-topic">Topic</label>
					<input
						type="text"
						id="agb-topic"
						placeholder="e.g. Benefits of standing desks for office workers"
						autocomplete="off"
					/>
				</div>

				<div class="agb-field-group">
					<label class="agb-field-label" for="agb-overview">Overview</label>
					<textarea
						id="agb-overview"
						rows="3"
						placeholder="Brief overview or key points you want covered in the post…"
					></textarea>
				</div>

				<div class="agb-actions">
					<button type="button" id="agb-btn-generate" class="button button-primary agb-btn"
						<?php echo $api_configured ? '' : 'disabled'; ?>>
						✨ Generate Post
					</button>
				</div>
			</div>

			<!-- URL Import tab -->
			<div id="agb-tab-url-import" class="agb-tab-content">
				<p class="agb-description">
					Paste an article URL. Claude will fetch it, write a 4–5 sentence italic intro, format the body, and add attribution.
				</p>
				<input type="url" id="agb-url" placeholder="https://www.example.com/article-title" autocomplete="off" />
				<div class="agb-actions">
					<button type="button" id="agb-btn-import" class="button button-primary agb-btn"
						<?php echo $api_configured ? '' : 'disabled'; ?>>
						🔗 Import &amp; Format
					</button>
				</div>
			</div>

			<!-- Progress -->
			<div id="agb-status" style="display:none;">
				<div class="agb-spinner"></div>
				<span id="agb-status-text">Processing…</span>
			</div>

			<!-- Error -->
			<div id="agb-error" class="agb-notice agb-notice-error" style="display:none;"></div>

			<!-- Success -->
			<div id="agb-success" class="agb-notice agb-notice-success" style="display:none;">
				✅ <strong>Post generated!</strong> Review the content above, add your featured image, then save or publish.
			</div>

			<!-- Hidden fields written by JS, read by save_post hook -->
			<input type="hidden" id="agb-hide-featured"    name="agb_hide_featured"    value="0" />
			<input type="hidden" id="agb-yoast-keyphrase"  name="agb_yoast_keyphrase"  value="" />
			<input type="hidden" id="agb-yoast-metadesc"   name="agb_yoast_metadesc"   value="" />
			<input type="hidden" id="agb-generated-flag"   name="agb_generated_flag"   value="0" />

		</div>
		<?php
	}

	/* -----------------------------------------------------------------------
	 * save_post — write all generated data to post meta
	 * -------------------------------------------------------------------- */

	public function save_post_meta( $post_id, $post ) {
		// Nonce check
		if ( ! isset( $_POST['agb_meta_nonce'] ) ||
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['agb_meta_nonce'] ) ), 'agb_save_post_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( wp_is_post_revision( $post_id ) )               return;
		if ( ! current_user_can( 'edit_post', $post_id ) )   return;
		if ( 'post' !== $post->post_type )                   return;

		// --- Category --------------------------------------------------------
		$category_id = isset( $_POST['agb_category_id'] ) ? intval( $_POST['agb_category_id'] ) : 0;
		if ( $category_id > 0 ) {
			wp_set_post_categories( $post_id, array( $category_id ) );
		}

		// Only apply the rest if generated by AGB
		$generated = isset( $_POST['agb_generated_flag'] ) && '1' === $_POST['agb_generated_flag'];

		if ( $generated ) {

			// --- Yoast SEO: focus keyphrase ----------------------------------
			$keyphrase = isset( $_POST['agb_yoast_keyphrase'] )
				? sanitize_text_field( wp_unslash( $_POST['agb_yoast_keyphrase'] ) )
				: '';
			if ( ! empty( $keyphrase ) ) {
				update_post_meta( $post_id, '_yoast_wpseo_focuskw',    $keyphrase );
				update_post_meta( $post_id, 'rank_math_focus_keyword', $keyphrase ); // Rank Math
				update_post_meta( $post_id, '_aioseo_keywords',        $keyphrase ); // AIOSEO
			}

			// --- Yoast SEO: meta description ---------------------------------
			$metadesc = isset( $_POST['agb_yoast_metadesc'] )
				? sanitize_text_field( wp_unslash( $_POST['agb_yoast_metadesc'] ) )
				: '';
			if ( ! empty( $metadesc ) ) {
				update_post_meta( $post_id, '_yoast_wpseo_metadesc',       $metadesc );
				update_post_meta( $post_id, 'rank_math_description',        $metadesc ); // Rank Math
				update_post_meta( $post_id, '_aioseo_description',          $metadesc ); // AIOSEO
			}

			// --- Hide featured image -----------------------------------------
			$auto_hide = get_option( 'agb_auto_hide_featured', '1' );
			if ( $auto_hide && isset( $_POST['agb_hide_featured'] ) && '1' === $_POST['agb_hide_featured'] ) {
				$primary_key = get_option( 'agb_hide_featured_image_key', '_hide_featured_image' );
				$all_keys    = array_unique( array(
					$primary_key,
					'_hide_featured_image',
					'hide_featured_image',
					'_hidden_featured_image',
					'hidden_featured_image',
					'_genesis_hide_image',
					'_genesis_hide_thumbnail',
					'_kadence_blocks_hide_featured_image',
					'astra_featured_img_enabled',
				) );
				foreach ( $all_keys as $key ) {
					$value = ( $key === 'astra_featured_img_enabled' ) ? 'disabled' : '1';
					update_post_meta( $post_id, $key, $value );
				}
			}

			// --- Auto-tag for generated posts --------------------------------
			$auto_tag = sanitize_text_field( get_option( 'agb_auto_tag', '' ) );
			if ( ! empty( $auto_tag ) ) {
				wp_add_post_tags( $post_id, $auto_tag );
			}

			// --- Set default author if configured ----------------------------
			$default_author = absint( get_option( 'agb_default_author', 0 ) );
			if ( $default_author > 0 ) {
				wp_update_post( array( 'ID' => $post_id, 'post_author' => $default_author ) );
			}
		}
	}

	/* -----------------------------------------------------------------------
	 * AJAX: AI Generate
	 * -------------------------------------------------------------------- */

	public function ajax_generate_ai() {
		check_ajax_referer( 'agb_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		$website  = isset( $_POST['website'] )  ? sanitize_text_field( wp_unslash( $_POST['website'] ) )      : '';
		$topic    = isset( $_POST['topic'] )    ? sanitize_text_field( wp_unslash( $_POST['topic'] ) )        : '';
		$overview = isset( $_POST['overview'] ) ? sanitize_textarea_field( wp_unslash( $_POST['overview'] ) ) : '';

		if ( empty( $topic ) ) {
			wp_send_json_error( array( 'message' => 'Please enter a Topic before generating.' ) );
		}

		$max_tokens = absint( get_option( 'agb_max_tokens_ai', 4096 ) );
		$claude     = new AGB_Claude_API();

		// ----------------------------------------------------------------
		// Fixed prompt template — exactly as specified.
		// Uses <agb_content> tags to avoid JSON/HTML encoding issues.
		// ----------------------------------------------------------------
		$system_prompt = 'You are a professional blog content writer. Format all output as HTML for WordPress Classic Editor. Follow the response format EXACTLY as instructed.';

		$user_prompt =
			"I want you to be a great and professional writer. Write a blog post with a title (4-10 words, no special characters).\n" .
			"The post should start with a 5-sentence introductory paragraph, followed by 4-6 body paragraphs, each with a relevant header. " .
			"Each body paragraph should contain 5-7 sentences. " .
			"The post should end with a 3-4 sentence conclusion. " .
			"This should have its own header, not loosely named conclusion, and make it relevant. " .
			"Give me a 2-4 keyword phrase for SEO purposes. " .
			"Make sure this phrase is in the copy at least twice but no more than four times. " .
			"And make sure the phrase is part of the title as well.\n\n" .
			"Ensure the tone is factual and straightforward, aimed at the target audience. " .
			"Avoid exaggeration or overly flowery language. " .
			"Each body paragraph should have a clear, descriptive header (3-5 words) that introduces the content of that section. " .
			"Never use an em dash.\n\n" .
			"Website: " . $website . "\n\n" .
			"Topic: " . $topic . "\n\n" .
			"Overview: " . $overview . "\n\n" .
			"Format the entire post as clean HTML for WordPress Classic Editor: use <h2> for all section headers, <p> for paragraphs. Do not use <h1>.\n\n" .
			"Return the response in EXACTLY this format — nothing before or after it:\n" .
			"{\"title\":\"4-10 word title containing the SEO keyphrase\",\"keyphrase\":\"2-4 word SEO keyphrase\",\"meta_description\":\"150-160 character meta description\"}\n" .
			"<agb_content>\n" .
			"[Full HTML content of the blog post here]\n" .
			"</agb_content>";

		$result = $claude->generate( $system_prompt, $user_prompt, $max_tokens );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$this->maybe_debug_log( 'AI Generate response', $result );

		$data = $this->parse_claude_response( $result );
		if ( ! $data ) {
			wp_send_json_error( array( 'message' => 'Could not parse Claude\'s response. Please try again.' ) );
		}

		wp_send_json_success( $data );
	}

	/* -----------------------------------------------------------------------
	 * AJAX: URL Import
	 * -------------------------------------------------------------------- */

	public function ajax_import_url() {
		check_ajax_referer( 'agb_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		$url = isset( $_POST['url'] ) ? trim( wp_unslash( $_POST['url'] ) ) : '';
		if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			wp_send_json_error( array( 'message' => 'Please enter a valid URL.' ) );
		}

		// 1. Fetch article
		$fetcher = new AGB_Article_Fetcher();
		$article = $fetcher->fetch( $url );

		if ( is_wp_error( $article ) ) {
			wp_send_json_error( array( 'message' => $article->get_error_message() ) );
		}

		$word_count = str_word_count( $article['content'] );
		if ( $word_count < 30 ) {
			wp_send_json_error( array(
				'message' => 'Could not extract article content from this URL (only ' . $word_count . ' words found). ' .
				             'The site may require a login, use heavy JavaScript rendering, or block bots. ' .
				             'Try copying the article text manually into the AI Generate tab as an overview.',
			) );
		}

		// 2. Trim to avoid huge API payloads
		$max_article_words = absint( get_option( 'agb_max_article_words', 2500 ) );
		$article_text      = $article['content'];
		$words             = explode( ' ', $article_text );
		if ( count( $words ) > $max_article_words ) {
			$article_text = implode( ' ', array_slice( $words, 0, $max_article_words ) );
		}

		$word_limit = absint( get_option( 'agb_word_limit', 800 ) );

		// 3. Build the attribution footer from the configurable template
		$footer_template = get_option(
			'agb_attribution_template',
			'<em>Original <a href="{article_url}">article</a> published on <a href="{domain_url}">{domain}</a></em>'
		);
		$attribution_footer = '<p>' . str_replace(
			array( '{article_url}', '{domain_url}', '{domain}' ),
			array( esc_url( $article['url'] ), esc_url( $article['base_url'] ), esc_html( $article['domain'] ) ),
			$footer_template
		) . '</p>';

		$continue_reading =
			'<p style="text-align:center"><a href="' . esc_url( $article['url'] ) . '">Click here to continue reading</a></p>';

		$max_tokens = absint( get_option( 'agb_max_tokens_url', 4096 ) );

		// 4. Build prompt — uses <agb_content> tag to avoid JSON/HTML encoding issues
		$system_prompt = 'You are a professional blog editor. You format articles for WordPress Classic Editor. Follow the response format EXACTLY as instructed.';

		$user_prompt =
			"Format the following article for a WordPress blog post.\n\n" .
			"Source URL: {$article['url']}\n" .
			"Source domain: {$article['domain']}\n" .
			( $article['title'] ? "Article title: {$article['title']}\n" : '' ) .
			"\nARTICLE CONTENT:\n{$article_text}\n\n" .
			"EXACT FORMATTING REQUIREMENTS:\n\n" .
			"1. TITLE: Use the article's headline exactly as-is. Remove any '| Site Name', '- Site Name', or brand suffix if present. Return just the plain article headline — no website name, no separators.\n\n" .
			"2. INTRO (very top of content): Write 4-5 original sentences summarizing this article IN YOUR OWN WORDS (not copied from the source). Wrap the ENTIRE intro block in <em> tags so it displays in italics.\n\n" .
			"3. BODY: Place the article content below the intro. Preserve the original article's structure and formatting as closely as possible — keep the same heading hierarchy, paragraph breaks, bullet points, and section order. Use <h2> for main headings, <h3> for subheadings, <p> for paragraphs, <ul>/<li> for lists, <strong> for bold text.\n\n" .
			"4. TRUNCATION: If the body content exceeds {$word_limit} words, stop at a natural paragraph or section break and append this exact line:\n" .
			$continue_reading . "\n\n" .
			"5. FOOTER: The very last line of the content must be exactly this (copy it verbatim, do not alter it):\n" .
			$attribution_footer . "\n\n" .
			"Return the response in EXACTLY this format — nothing before or after it:\n" .
			"{\"title\":\"plain article headline only\",\"keyphrase\":\"2-4 word SEO keyphrase\",\"meta_description\":\"150-160 char meta description\"}\n" .
			"<agb_content>\n" .
			"[Full HTML here: italic intro + formatted body + truncation link if needed + attribution footer]\n" .
			"</agb_content>";

		// 5. Call Claude
		$claude = new AGB_Claude_API();
		$result = $claude->generate( $system_prompt, $user_prompt, $max_tokens );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$this->maybe_debug_log( 'URL Import response', $result );

		$data = $this->parse_claude_response( $result );
		if ( ! $data ) {
			wp_send_json_error( array( 'message' => 'Could not parse Claude\'s response. Please try again.' ) );
		}

		wp_send_json_success( $data );
	}

	/* -----------------------------------------------------------------------
	 * Parse Claude's response.
	 *
	 * Expected format:
	 *   {"title":"...","keyphrase":"...","meta_description":"..."}
	 *   <agb_content>
	 *   [HTML content]
	 *   </agb_content>
	 *
	 * The <agb_content> wrapper completely avoids JSON/HTML encoding issues
	 * with quoted HTML attribute values (href="...", etc.).
	 * -------------------------------------------------------------------- */

	private function parse_claude_response( $text ) {
		// Strip any accidental markdown fences
		$text = preg_replace( '/^```(?:json)?\s*/im', '', $text );
		$text = preg_replace( '/\s*```\s*$/im',        '', $text );
		$text = trim( $text );

		// ---- Extract HTML content from <agb_content> block -----------------
		$content = '';
		if ( preg_match( '/<agb_content>(.*?)<\/agb_content>/is', $text, $match ) ) {
			$content = trim( $match[1] );
			// Remove content block so we can parse the JSON cleanly
			$json_text = trim( preg_replace( '/<agb_content>.*?<\/agb_content>/is', '', $text ) );
		} else {
			// Fallback: content was probably returned inside the JSON (old format)
			$json_text = $text;
		}

		// ---- Parse the JSON for title, keyphrase, meta_description ---------
		$start = strpos( $json_text, '{' );
		$end   = strrpos( $json_text, '}' );

		$title            = '';
		$keyphrase        = '';
		$meta_description = '';

		if ( $start !== false && $end !== false ) {
			$json_str = substr( $json_text, $start, $end - $start + 1 );
			$data     = json_decode( $json_str, true );

			if ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) ) {
				$title            = isset( $data['title'] )            ? $data['title']            : '';
				$keyphrase        = isset( $data['keyphrase'] )        ? $data['keyphrase']        : '';
				$meta_description = isset( $data['meta_description'] ) ? $data['meta_description'] : '';

				// If content wasn't in <agb_content> tags, try the JSON field (old format fallback)
				if ( empty( $content ) && isset( $data['content'] ) ) {
					$content = $data['content'];
				}
			}
		}

		if ( empty( $content ) ) {
			return false;
		}

		return array(
			'title'            => wp_kses_post( $title ),
			'content'          => wp_kses_post( $content ),
			'keyphrase'        => sanitize_text_field( $keyphrase ),
			'meta_description' => sanitize_text_field( $meta_description ),
		);
	}

	/* -----------------------------------------------------------------------
	 * Optional debug logging
	 * -------------------------------------------------------------------- */

	private function maybe_debug_log( $label, $data ) {
		if ( get_option( 'agb_debug_mode', '0' ) !== '1' ) return;
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$snippet = is_string( $data ) ? substr( $data, 0, 500 ) : print_r( $data, true );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[AGB] ' . $label . ': ' . $snippet );
		}
	}
}
