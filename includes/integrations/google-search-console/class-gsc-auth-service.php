<?php
/**
 * OAuth and connection management for Search Console.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_GSC_Auth_Service {

	private RevIt_Publisher_GSC_Token_Store $tokens;
	private RevIt_Publisher_Settings $settings;

	public function __construct( RevIt_Publisher_GSC_Token_Store $tokens, RevIt_Publisher_Settings $settings ) {
		$this->tokens   = $tokens;
		$this->settings = $settings;
	}

	public function is_connected(): bool {
		return $this->tokens->is_connected();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_status(): array {
		$property = $this->settings->gsc_property();
		return array(
			'connected'      => $this->is_connected(),
			'property'       => $property,
			'fixture_mode'   => $this->is_fixture_mode(),
			'last_sync'      => get_option( 'revit_gsc_last_sync', '' ),
			'last_sync_stats'=> get_option( 'revit_gsc_last_sync_stats', array() ),
			'last_error'     => get_option( 'revit_gsc_last_error', '' ),
			'credentials'    => array(
				'client_id_configured' => '' !== $this->settings->gsc_client_id(),
			),
		);
	}

	public function connect_fixture(): void {
		$this->tokens->save(
			array(
				'fixture_mode' => true,
				'connected_at' => gmdate( 'c' ),
				'scopes'       => array( 'readonly' ),
			)
		);
		if ( '' === $this->settings->gsc_property() ) {
			update_option( RevIt_Publisher_Settings::GSC_PROPERTY, RevIt_Publisher_GSC_Fixture_Data::PROPERTY );
		}
	}

	public function disconnect(): void {
		$this->tokens->clear();
		delete_option( 'revit_gsc_last_error' );
	}

	public function is_fixture_mode(): bool {
		$tokens = $this->tokens->get();
		return ! empty( $tokens['fixture_mode'] );
	}

	/**
	 * Begin OAuth — returns redirect URL or empty if fixture/credentials missing.
	 */
	public function get_oauth_url(): string {
		$client_id = $this->settings->gsc_client_id();
		if ( '' === $client_id ) {
			return '';
		}
		$state = wp_generate_password( 32, false );
		set_transient( 'revit_gsc_oauth_state_' . get_current_user_id(), $state, 15 * MINUTE_IN_SECONDS );

		$redirect = admin_url( 'admin.php?page=revit-publisher-settings&revit_gsc_oauth=1' );
		$scope    = $this->settings->gsc_sitemap_write_enabled()
			? 'https://www.googleapis.com/auth/webmasters'
			: 'https://www.googleapis.com/auth/webmasters.readonly';

		return add_query_arg(
			array(
				'client_id'     => $client_id,
				'redirect_uri'  => rawurlencode( $redirect ),
				'response_type' => 'code',
				'scope'         => rawurlencode( $scope ),
				'access_type'   => 'offline',
				'prompt'        => 'consent',
				'state'         => $state,
			),
			'https://accounts.google.com/o/oauth2/v2/auth'
		);
	}

	public function handle_oauth_callback( string $code, string $state ): bool|WP_Error {
		$expected = get_transient( 'revit_gsc_oauth_state_' . get_current_user_id() );
		if ( ! is_string( $expected ) || ! hash_equals( $expected, $state ) ) {
			return new WP_Error( 'revit_gsc_state', __( 'Invalid OAuth state.', 'revit-publisher' ) );
		}
		delete_transient( 'revit_gsc_oauth_state_' . get_current_user_id() );

		$client_id     = $this->settings->gsc_client_id();
		$client_secret = $this->settings->gsc_client_secret();
		if ( '' === $client_id || '' === $client_secret ) {
			return new WP_Error( 'revit_gsc_credentials', __( 'Google OAuth credentials are not configured.', 'revit-publisher' ) );
		}

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'body' => array(
					'code'          => $code,
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'redirect_uri'  => admin_url( 'admin.php?page=revit-publisher-settings&revit_gsc_oauth=1' ),
					'grant_type'    => 'authorization_code',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['access_token'] ) ) {
			return new WP_Error( 'revit_gsc_token', __( 'Failed to obtain access token.', 'revit-publisher' ) );
		}

		$this->tokens->save(
			array(
				'access_token'  => (string) $data['access_token'],
				'refresh_token' => (string) ( $data['refresh_token'] ?? '' ),
				'expires_at'    => time() + (int) ( $data['expires_in'] ?? 3600 ),
				'scopes'        => explode( ' ', (string) ( $data['scope'] ?? '' ) ),
				'connected_at'  => gmdate( 'c' ),
			)
		);
		return true;
	}
}
