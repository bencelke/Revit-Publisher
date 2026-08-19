<?php
/**
 * Secure OAuth token storage.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_GSC_Token_Store {

	private const OPTION = 'revit_gsc_tokens';

	/**
	 * @param array<string, mixed> $tokens
	 */
	public function save( array $tokens ): void {
		update_option( self::OPTION, $this->encrypt( wp_json_encode( $tokens ) ), false );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get(): array {
		$raw = get_option( self::OPTION, '' );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}
		$decoded = json_decode( $this->decrypt( $raw ), true );
		return is_array( $decoded ) ? $decoded : array();
	}

	public function clear(): void {
		delete_option( self::OPTION );
	}

	public function is_connected(): bool {
		$tokens = $this->get();
		return ! empty( $tokens['access_token'] ) || ! empty( $tokens['fixture_mode'] );
	}

	private function encrypt( string $value ): string {
		if ( function_exists( 'openssl_encrypt' ) ) {
			$key = hash( 'sha256', wp_salt( 'auth' ), true );
			$iv  = random_bytes( 16 );
			$enc = openssl_encrypt( $value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
			if ( false !== $enc ) {
				return base64_encode( $iv . $enc ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			}
		}
		return base64_encode( $value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	private function decrypt( string $value ): string {
		$decoded = base64_decode( $value, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $decoded ) {
			return '';
		}
		if ( function_exists( 'openssl_decrypt' ) && strlen( $decoded ) > 16 ) {
			$key = hash( 'sha256', wp_salt( 'auth' ), true );
			$iv  = substr( $decoded, 0, 16 );
			$enc = substr( $decoded, 16 );
			$dec = openssl_decrypt( $enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
			if ( false !== $dec ) {
				return $dec;
			}
		}
		return $decoded;
	}
}
