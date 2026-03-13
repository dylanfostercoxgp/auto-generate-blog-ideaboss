<?php
/**
 * Article fetcher — retrieves and extracts article content from a URL.
 * Used by Auto Generate Blog by ideaBoss.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AGB_Article_Fetcher {

	/* -----------------------------------------------------------------------
	 * Public: fetch an article from a URL
	 * -------------------------------------------------------------------- */

	public function fetch( $url ) {
		// Basic trim without over-sanitising (esc_url_raw can mangle some valid URLs)
		$url = trim( $url );

		if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error( 'invalid_url', 'Please enter a valid URL (starting with http:// or https://).' );
		}

		// Respect configured timeout; fall back to 30 s
		$timeout    = absint( get_option( 'agb_fetch_timeout', 30 ) );
		$user_agent = get_option( 'agb_fetch_user_agent', '' );
		if ( empty( $user_agent ) ) {
			$user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => $timeout,
				'redirection' => 10,
				'sslverify'   => false,
				'user-agent'  => $user_agent,
				'headers'     => array(
					'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
					'Accept-Language' => 'en-US,en;q=0.9',
					// Do NOT send Accept-Encoding — let cURL negotiate only what it can decompress.
					// Explicitly requesting 'br' (Brotli) causes cURL error 61 on most PHP builds.
					'Cache-Control'   => 'no-cache',
					'Pragma'          => 'no-cache',
					'Referer'         => 'https://www.google.com/',
					'Sec-Fetch-Dest'  => 'document',
					'Sec-Fetch-Mode'  => 'navigate',
					'Sec-Fetch-Site'  => 'cross-site',
					'Upgrade-Insecure-Requests' => '1',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'fetch_failed',
				'Could not fetch the URL: ' . $response->get_error_message() . '. The site may be blocking automated access.'
			);
		}

		$status = wp_remote_retrieve_response_code( $response );

		// Some sites send back 403/429/503 for bots — give a clear message
		if ( $status === 403 ) {
			return new WP_Error( 'fetch_error', 'The site returned HTTP 403 (Forbidden). It is blocking automated access. Try copying and pasting the article text into a Google Doc first, then use AI Generate mode.' );
		}
		if ( $status === 429 ) {
			return new WP_Error( 'fetch_error', 'The site returned HTTP 429 (Too Many Requests). Please wait a minute and try again.' );
		}
		if ( $status !== 200 ) {
			return new WP_Error( 'fetch_error', 'The page returned HTTP ' . $status . '. The site may require login or be blocking automated access.' );
		}

		$html = wp_remote_retrieve_body( $response );
		if ( empty( $html ) ) {
			return new WP_Error( 'empty_body', 'The URL returned empty content.' );
		}

		// Use the final URL after redirects (WordPress stores it in response headers)
		$final_url = wp_remote_retrieve_header( $response, 'x-final-location' );
		if ( empty( $final_url ) ) {
			$final_url = $url;
		}

		return $this->parse_article( $html, $final_url );
	}

	/* -----------------------------------------------------------------------
	 * Parse HTML and extract article content
	 * -------------------------------------------------------------------- */

	private function parse_article( $html, $url ) {
		libxml_use_internal_errors( true );
		$doc = new DOMDocument( '1.0', 'UTF-8' );
		$doc->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();

		$xpath = new DOMXPath( $doc );

		// Title
		$title = $this->extract_title( $xpath );

		// Meta description (helpful hint for Claude)
		$og_desc = '';
		$desc_nodes = $xpath->query( '//meta[@property="og:description"]/@content | //meta[@name="description"]/@content' );
		if ( $desc_nodes && $desc_nodes->length > 0 ) {
			$og_desc = trim( $desc_nodes->item(0)->nodeValue );
		}

		// Remove only the tags we KNOW are noise — no class/id filtering
		$this->remove_noise_tags( $doc );

		// Try JSON-LD first (most reliable on modern news sites)
		$content_text = $this->extract_json_ld( $xpath );

		// Try CSS selectors to find the article body
		if ( empty( $content_text ) || str_word_count( $content_text ) < 50 ) {
			$content_html = $this->find_content_node( $doc, $xpath );
			if ( $content_html ) {
				$content_text = $this->html_to_text( $content_html );
			}
		}

		// Fallback: collect all paragraphs
		if ( empty( $content_text ) || str_word_count( $content_text ) < 30 ) {
			$content_text = $this->collect_paragraphs( $xpath );
		}

		// Last resort: strip the whole body
		if ( empty( $content_text ) || str_word_count( $content_text ) < 20 ) {
			$body = $xpath->query( '//body' );
			if ( $body && $body->length > 0 ) {
				$content_text = $this->html_to_text( $doc->saveHTML( $body->item(0) ) );
			}
		}

		// Parse URL parts
		$parsed   = wp_parse_url( $url );
		$base_url = ( isset( $parsed['scheme'] ) ? $parsed['scheme'] : 'https' ) . '://' . ( isset( $parsed['host'] ) ? $parsed['host'] : '' );
		$domain   = preg_replace( '/^www\./i', '', isset( $parsed['host'] ) ? $parsed['host'] : '' );

		return array(
			'title'    => $title,
			'content'  => $content_text,
			'url'      => $url,
			'base_url' => $base_url,
			'domain'   => $domain,
			'og_desc'  => $og_desc,
		);
	}

	/* -----------------------------------------------------------------------
	 * Extract title
	 * -------------------------------------------------------------------- */

	private function extract_title( DOMXPath $xpath ) {
		// og:title is usually the cleanest
		$nodes = $xpath->query( '//meta[@property="og:title"]/@content' );
		if ( $nodes && $nodes->length > 0 ) {
			$title = trim( $nodes->item(0)->nodeValue );
			// Still strip "| Site" suffixes from og:title in case they exist
			$title = preg_replace( '/\s*[\|\-\–\—]\s*.{1,80}$/', '', $title );
			return trim( $title );
		}
		$nodes = $xpath->query( '//title' );
		if ( $nodes && $nodes->length > 0 ) {
			$title = trim( $nodes->item(0)->textContent );
			// Strip "| Site Name" or "- Site Name" suffix
			$title = preg_replace( '/\s*[\|\-\–\—]\s*.{1,80}$/', '', $title );
			return trim( $title );
		}
		return '';
	}

	/* -----------------------------------------------------------------------
	 * Remove ONLY known noise tags by tag name (safe — no class/id removal)
	 * -------------------------------------------------------------------- */

	private function remove_noise_tags( DOMDocument $doc ) {
		$noise_tags = array(
			'script', 'style', 'noscript', 'iframe', 'button',
			'svg', 'canvas', 'video', 'audio', 'form',
			'nav', 'header', 'footer', 'aside',
		);

		foreach ( $noise_tags as $tag ) {
			$nodes  = $doc->getElementsByTagName( $tag );
			$remove = array();
			foreach ( $nodes as $node ) {
				$remove[] = $node;
			}
			foreach ( $remove as $node ) {
				if ( $node->parentNode ) {
					$node->parentNode->removeChild( $node );
				}
			}
		}
	}

	/* -----------------------------------------------------------------------
	 * Try JSON-LD structured data first (works on many modern news sites)
	 * -------------------------------------------------------------------- */

	private function extract_json_ld( DOMXPath $xpath ) {
		$scripts = $xpath->query( '//script[@type="application/ld+json"]' );
		if ( ! $scripts ) return '';

		foreach ( $scripts as $script ) {
			$raw  = $script->textContent;
			$json = json_decode( $raw, true );
			if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $json ) ) {
				continue;
			}

			// Unwrap @graph if present
			if ( isset( $json['@graph'] ) && is_array( $json['@graph'] ) ) {
				foreach ( $json['@graph'] as $item ) {
					$text = $this->json_ld_extract_text( $item );
					if ( $text ) return $text;
				}
			} else {
				$text = $this->json_ld_extract_text( $json );
				if ( $text ) return $text;
			}
		}
		return '';
	}

	private function json_ld_extract_text( $json ) {
		$types = array( 'Article', 'NewsArticle', 'BlogPosting', 'WebPage', 'TechArticle', 'MedicalWebPage', 'ReportageNewsArticle' );
		$type  = isset( $json['@type'] ) ? $json['@type'] : '';
		if ( is_array( $type ) ) {
			$type = implode( ',', $type );
		}

		foreach ( $types as $t ) {
			if ( stripos( $type, $t ) !== false ) {
				$body = isset( $json['articleBody'] ) ? $json['articleBody'] : '';
				if ( empty( $body ) ) {
					$body = isset( $json['description'] ) ? $json['description'] : '';
				}
				if ( ! empty( $body ) && str_word_count( $body ) > 50 ) {
					return $body;
				}
			}
		}
		return '';
	}

	/* -----------------------------------------------------------------------
	 * Find the main content node via CSS-like selectors.
	 * Evaluates ALL selectors and picks the one with the highest word count
	 * (no early break — ensures we always get the densest content region).
	 * -------------------------------------------------------------------- */

	private function find_content_node( DOMDocument $doc, DOMXPath $xpath ) {
		$selectors = array(
			// Semantic tag first
			'//article',
			// Common class-based selectors (case-insensitive)
			'//*[@class and contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"article-body")]',
			'//*[@class and contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"article__body")]',
			'//*[@class and contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"article-content")]',
			'//*[@class and contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"articlebody")]',
			'//*[@class and contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"post-content")]',
			'//*[@class and contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"post-body")]',
			'//*[@class and contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"post__body")]',
			'//*[@class and contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"entry-content")]',
			'//*[@class and contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"entry__content")]',
			'//*[@class and contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"story-body")]',
			'//*[@class and contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"story-content")]',
			'//*[@class and contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"content-body")]',
			'//*[@class and contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"main-content")]',
			'//*[@class and contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"page-content")]',
			'//*[@class and contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"body-content")]',
			'//*[@class and contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"rich-text")]',
			'//*[@class and contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"wysiwyg")]',
			// ID-based selectors
			'//*[@id="article-body"]',
			'//*[@id="article-content"]',
			'//*[@id="main-content"]',
			'//*[@id="content"]',
			'//*[@id="post-content"]',
			'//*[@id="entry-content"]',
			// ARIA roles
			'//main',
			'//*[@role="main"]',
			'//*[@role="article"]',
		);

		$best_html       = '';
		$best_word_count = 0;

		foreach ( $selectors as $selector ) {
			$nodes = @$xpath->query( $selector );
			if ( ! $nodes || $nodes->length === 0 ) {
				continue;
			}
			$html       = $doc->saveHTML( $nodes->item(0) );
			$word_count = str_word_count( strip_tags( $html ) );

			if ( $word_count > $best_word_count ) {
				$best_word_count = $word_count;
				$best_html       = $html;
			}
		}

		return $best_word_count >= 30 ? $best_html : '';
	}

	/* -----------------------------------------------------------------------
	 * Fallback: collect all paragraph text
	 * -------------------------------------------------------------------- */

	private function collect_paragraphs( DOMXPath $xpath ) {
		$paragraphs = $xpath->query( '//p' );
		if ( ! $paragraphs ) return '';

		$parts = array();
		foreach ( $paragraphs as $p ) {
			$text = trim( $p->textContent );
			if ( strlen( $text ) > 40 ) {
				$parts[] = $text;
			}
		}
		return implode( "\n\n", $parts );
	}

	/* -----------------------------------------------------------------------
	 * Convert HTML to clean readable text
	 * -------------------------------------------------------------------- */

	private function html_to_text( $html ) {
		// Headings
		$html = preg_replace( '/<h[1-6][^>]*>/i',    "\n\n## ",  $html );
		$html = preg_replace( '/<\/h[1-6]>/i',        " ##\n\n",  $html );
		// Paragraphs and block elements
		$html = preg_replace( '/<(p|div|section|blockquote)[^>]*>/i',   "\n",  $html );
		$html = preg_replace( '/<\/(p|div|section|blockquote)>/i',       "\n",  $html );
		// List items
		$html = preg_replace( '/<li[^>]*>/i',          "\n• ",    $html );
		// Line breaks
		$html = preg_replace( '/<br\s*\/?>/i',         "\n",      $html );
		// Strip all remaining tags
		$text = wp_strip_all_tags( $html );
		// Decode entities
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		// Normalise whitespace
		$text = preg_replace( '/[ \t]+/',   ' ',    $text );
		$text = preg_replace( '/\n[ \t]+/', "\n",   $text );
		$text = preg_replace( '/\n{3,}/',   "\n\n", $text );

		return trim( $text );
	}
}
