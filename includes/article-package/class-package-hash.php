<?php
/**
 * Package hash utility.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Computes deterministic SHA-256 hashes for article packages.
 */
class RevIt_Publisher_Package_Hash {

	/**
	 * Compute SHA-256 hash from package data.
	 *
	 * @param mixed $data Package object or array.
	 */
	public function compute( mixed $data ): string {
		$normalized = $this->normalize( $data );
		$json       = wp_json_encode( $normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return hash( 'sha256', (string) $json );
	}

	/**
	 * Recursively sort array/object keys for canonical representation.
	 *
	 * @return array<string, mixed>|mixed
	 */
	private function normalize( mixed $data ): mixed {
		if ( is_object( $data ) ) {
			$data = json_decode( wp_json_encode( $data ), true );
		}

		if ( ! is_array( $data ) ) {
			return $data;
		}

		ksort( $data );

		foreach ( $data as $key => $value ) {
			$data[ $key ] = $this->normalize( $value );
		}

		return $data;
	}
}
