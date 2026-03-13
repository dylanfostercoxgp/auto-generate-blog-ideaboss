/**
 * Admin JavaScript for Auto Generate Blog by ideaBoss.
 * Handles tabs, AJAX, and populating Classic Editor + Yoast fields.
 */

jQuery(document).ready(function ($) {

	'use strict';

	/* -----------------------------------------------------------------------
	 * Tab switching
	 * -------------------------------------------------------------------- */

	$(document).on('click', '.agb-tab', function () {
		var tab = $(this).data('tab');
		$('.agb-tab').removeClass('agb-tab-active');
		$(this).addClass('agb-tab-active');
		$('.agb-tab-content').removeClass('agb-tab-content-active');
		$('#agb-tab-' + tab).addClass('agb-tab-content-active');
		hideMessages();
	});

	/* -----------------------------------------------------------------------
	 * Button: AI Generate
	 * -------------------------------------------------------------------- */

	$(document).on('click', '#agb-btn-generate', function () {
		var website  = $('#agb-website').val().trim();
		var topic    = $('#agb-topic').val().trim();
		var overview = $('#agb-overview').val().trim();

		if (!topic) {
			showError('Please enter a Topic before generating.');
			return;
		}

		runGeneration('ai', { website: website, topic: topic, overview: overview });
	});

	// Ctrl+Enter shortcut in overview textarea
	$(document).on('keydown', '#agb-overview', function (e) {
		if (e.ctrlKey && e.which === 13) {
			e.preventDefault();
			$('#agb-btn-generate').trigger('click');
		}
	});

	/* -----------------------------------------------------------------------
	 * Button: URL Import
	 * -------------------------------------------------------------------- */

	$(document).on('click', '#agb-btn-import', function () {
		var url = $('#agb-url').val().trim();
		if (!url) {
			showError('Please enter a URL to import.');
			return;
		}
		if (!isValidUrl(url)) {
			showError('Please enter a valid URL starting with http:// or https://');
			return;
		}
		runGeneration('url', { url: url });
	});

	$(document).on('keypress', '#agb-url', function (e) {
		if (e.which === 13) {
			e.preventDefault();
			$('#agb-btn-import').trigger('click');
		}
	});

	/* -----------------------------------------------------------------------
	 * Core generation handler
	 * -------------------------------------------------------------------- */

	function runGeneration(mode, params) {
		hideMessages();
		var msg = mode === 'ai'
			? 'Claude is writing your post… (15–30 seconds)'
			: 'Fetching article and formatting… (20–45 seconds)';
		setLoading(true, msg);

		$.ajax({
			url:     agb_data.ajax_url,
			type:    'POST',
			timeout: 120000,
			data:    $.extend({ action: mode === 'ai' ? 'agb_generate_ai' : 'agb_import_url', nonce: agb_data.nonce }, params),

			success: function (response) {
				setLoading(false);
				if (response.success) {
					populatePost(response.data);
				} else {
					var msg = (response.data && response.data.message) ? response.data.message : 'Something went wrong. Please try again.';
					showError(msg);
				}
			},

			error: function (xhr, status) {
				setLoading(false);
				if (status === 'timeout') {
					showError('Request timed out. Please try again.');
				} else if (xhr.status === 0) {
					showError('Connection error. Please check your internet and try again.');
				} else {
					showError('Server error (HTTP ' + xhr.status + '). Check your API key in Settings and try again.');
				}
			}
		});
	}

	/* -----------------------------------------------------------------------
	 * Populate the post editor with all generated data
	 * -------------------------------------------------------------------- */

	function populatePost(data) {

		// --- Post title ------------------------------------------------------
		if (data.title) {
			var $title = $('#title');
			if ($title.length) {
				$title.val(data.title).trigger('blur').trigger('change');
			}
		}

		// --- Content (TinyMCE Classic Editor) --------------------------------
		if (data.content) {
			setEditorContent(data.content);
		}

		// --- Yoast SEO via hidden fields (reliable PHP save on post save) ----
		// These hidden fields are read by the save_post hook in PHP.
		if (data.keyphrase) {
			$('#agb-yoast-keyphrase').val(data.keyphrase);
		}
		if (data.meta_description) {
			$('#agb-yoast-metadesc').val(data.meta_description);
		}

		// Also try to update the visible Yoast UI fields for instant feedback
		if (data.keyphrase)        { trySetYoastUI('keyphrase', data.keyphrase); }
		if (data.meta_description) { trySetYoastUI('metadesc',  data.meta_description); }

		// --- Mark as generated (triggers PHP hide-featured + Yoast save) ----
		$('#agb-generated-flag').val('1');
		$('#agb-hide-featured').val('1');

		// --- Category: check matching checkbox in native Categories metabox --
		var catId = parseInt($('#agb-category').val(), 10);
		if (catId > 0) {
			var $catCheckbox = $('#in-category-' + catId);
			if ($catCheckbox.length) {
				// Uncheck all first, then check the chosen one
				$('input[name="post_category[]"]').prop('checked', false);
				$catCheckbox.prop('checked', true).trigger('change');
			}
		}

		// --- Also try JS-based hide featured image (visual feedback) ---------
		tryHideFeaturedImageCheckbox();

		// --- Success ---------------------------------------------------------
		showSuccess();
		var $top = $('#titlediv, #postdivrich, #postdiv');
		if ($top.length) {
			$('html, body').animate({ scrollTop: $top.first().offset().top - 40 }, 500);
		}
	}

	/* -----------------------------------------------------------------------
	 * Set TinyMCE / Classic Editor content
	 * -------------------------------------------------------------------- */

	function setEditorContent(html) {
		// TinyMCE (visual tab)
		if (typeof tinyMCE !== 'undefined') {
			var editor = tinyMCE.get('content');
			if (editor && !editor.isHidden()) {
				editor.setContent(html);
				editor.fire('change');
				// Sync to textarea for save
				editor.save();
				return;
			}
		}
		// wp.editor API
		if (typeof wp !== 'undefined' && wp.editor && typeof wp.editor.setContent === 'function') {
			try {
				wp.editor.setContent('content', html);
				return;
			} catch (e) { /* fall through */ }
		}
		// Fallback: raw textarea
		$('#content').val(html).trigger('change');
	}

	/* -----------------------------------------------------------------------
	 * Try to update visible Yoast fields in the UI (best-effort, not critical —
	 * the real save happens via PHP hidden fields on post save).
	 * -------------------------------------------------------------------- */

	function trySetYoastUI(type, value) {
		var selectors = type === 'keyphrase'
			? [
				'#yoast_wpseo_focuskw',
				'input[name="yoast_wpseo_focuskw"]',
				'#focus-keyword-input-metabox',
				'input[placeholder*="eyphrase"]',
				'.wpseo-metabox-menu input[type="text"]',
			  ]
			: [
				'#yoast_wpseo_metadesc',
				'textarea[name="yoast_wpseo_metadesc"]',
				'textarea[placeholder*="escription"]',
				'.wpseo-meta-description-input',
			  ];

		for (var i = 0; i < selectors.length; i++) {
			var $el = $(selectors[i]);
			if ($el.length) {
				setNativeValue($el[0], value);
				return true;
			}
		}
		return false;
	}

	/**
	 * Set a form field value in a way that fires both native DOM
	 * and React synthetic events (needed for Gutenberg / React-based Yoast).
	 */
	function setNativeValue(el, value) {
		try {
			var isTA  = el.tagName === 'TEXTAREA';
			var proto = isTA ? window.HTMLTextAreaElement.prototype : window.HTMLInputElement.prototype;
			var desc  = Object.getOwnPropertyDescriptor(proto, 'value');
			if (desc && desc.set) {
				desc.set.call(el, value);
			} else {
				el.value = value;
			}
		} catch (e) {
			el.value = value;
		}
		el.dispatchEvent(new Event('input',  { bubbles: true }));
		el.dispatchEvent(new Event('change', { bubbles: true }));
	}

	/* -----------------------------------------------------------------------
	 * Try to check the hide-featured-image checkbox in the DOM (visual only —
	 * PHP save_post hook handles the reliable server-side save).
	 * -------------------------------------------------------------------- */

	function tryHideFeaturedImageCheckbox() {
		var selectors = [
			'input[name="_hide_featured_image"]',
			'input[id="_hide_featured_image"]',
			'input[name="hide_featured_image"]',
			'input[id="hide_featured_image"]',
			'input[name="_hidden_featured_image"]',
			'input[id="_hidden_featured_image"]',
			'input[name="_genesis_hide_image"]',
			'input[id="_genesis_hide_image"]',
			'input[type="checkbox"][name*="hide"][name*="featured"]',
			'input[type="checkbox"][id*="hide"][id*="featured"]',
			'.hide-featured-image input[type="checkbox"]',
			'label:contains("Hide featured") input[type="checkbox"]',
		];

		for (var i = 0; i < selectors.length; i++) {
			var $cb = $(selectors[i]);
			if ($cb.length && ($cb.attr('type') === 'checkbox' || !$cb.attr('type'))) {
				$cb.prop('checked', true).trigger('change');
				return;
			}
		}
		// If no matching checkbox found, PHP save_post will handle it.
	}

	/* -----------------------------------------------------------------------
	 * Loading state
	 * -------------------------------------------------------------------- */

	function setLoading(isLoading, message) {
		if (isLoading) {
			$('#agb-status-text').text(message || 'Processing…');
			$('#agb-status').show();
			$('#agb-btn-generate, #agb-btn-import').prop('disabled', true).addClass('agb-btn-loading');
		} else {
			$('#agb-status').hide();
			$('#agb-btn-generate, #agb-btn-import').prop('disabled', !agb_data.is_configured).removeClass('agb-btn-loading');
		}
	}

	/* -----------------------------------------------------------------------
	 * Messages
	 * -------------------------------------------------------------------- */

	function showError(message) {
		$('#agb-error').html('❌ ' + message).show();
		var $err = $('#agb-error');
		if ($err.offset()) {
			$('html, body').animate({ scrollTop: $err.offset().top - 30 }, 300);
		}
	}

	function showSuccess() { $('#agb-success').show(); }
	function hideMessages() { $('#agb-error, #agb-success').hide(); }

	/* -----------------------------------------------------------------------
	 * Utility
	 * -------------------------------------------------------------------- */

	function isValidUrl(str) {
		return /^https?:\/\/.+\..+/i.test(str);
	}

});
