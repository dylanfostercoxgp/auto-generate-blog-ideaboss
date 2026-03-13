<?php
/**
 * Claude API integration for Auto Generate Blog by ideaBoss.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AGB_Claude_API {

	private $api_key;
	private $model;
	private $api_url = 'https://api.anthropic.com/v1/messages';

	public function __construct() {
		$this->api_key = get_option( 'agb_claude_api_key', '' );
		$this->model   = get_option( 'agb_claude_model', 'claude-sonnet-4-6' );
	}

	/* -----------------------------------------------------------------------
	 * Send a message to the Claude API
	 *
	 * @param string $system_prompt  System-level instructions for Claude.
	 * @param string $user_prompt    The user message to send.
	 * @param int    $max_tokens     Maximum tokens in the response.
	 *
	 * @return string|WP_Error  Raw text response from Claude, or WP_Error on failure.
	 * -------------------------------------------------------------------- */

	public function generate( $system_prompt, $user_prompt, $max_tokens = 4096 ) {
		if ( empty( $this->api_key ) ) {
			return new WP_Error(
				'no_api_key',
				'Claude API key is not configured. Please add it under <strong>Settings → Auto Generate Blog</strong>.'
			);
		}

		$body = array(
			'model'      => $this->model,
			'max_tokens' => intval( $max_tokens ),
			'system'     => $system_prompt,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => $user_prompt,
				),
			),
		);

		$response = wp_remote_post(
			$this->api_url,
			array(
				'timeout'    => 90,
				'sslverify'  => true,
				'headers'    => array(
					'Content-Type'      => 'application/json',
					'x-api-key'         => $this->api_key,
					'anthropic-version' => '2023-06-01',
				),
				'body'       => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'request_failed',
				'Could not connect to Claude API: ' . $response->get_error_message()
			);
		}

		$status_code   = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code !== 200 ) {
			$error_msg = 'Claude API error (HTTP ' . $status_code . ')';
			if ( isset( $response_body['error']['message'] ) ) {
				$error_msg .= ': ' . $response_body['error']['message'];
			}
			// Friendly messages for common errors
			if ( $status_code === 401 ) {
				$error_msg = 'Invalid Claude API key. Please check your key in Settings → Auto Generate Blog.';
			} elseif ( $status_code === 429 ) {
				$error_msg = 'Claude API rate limit reached. Please wait a moment and try again.';
			} elseif ( $status_code === 529 ) {
				$error_msg = 'Claude API is temporarily overloaded. Please try again in a few seconds.';
			}
			return new WP_Error( 'api_error', $error_msg );
		}

		if ( ! isset( $response_body['content'][0]['text'] ) ) {
			return new WP_Error( 'invalid_response', 'Unexpected response format from Claude API. Please try again.' );
		}

		return $response_body['content'][0]['text'];
	}

	/* -----------------------------------------------------------------------
	 * Check if the API key is set
	 * -------------------------------------------------------------------- */

	public function is_configured() {
		return ! empty( $this->api_key );
	}
}
