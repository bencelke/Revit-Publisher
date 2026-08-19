<?php
/**
 * Article package JSON Schema validation.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;

/**
 * Validates revit-article-v1 packages against the JSON Schema contract.
 */
class RevIt_Publisher_Article_Package_Validator {

	public const SCHEMA_VERSION = 'revit-article-v1';

	/**
	 * Cached schema object.
	 *
	 * @var object|null
	 */
	private ?object $schema = null;

	/**
	 * Validate decoded article package data.
	 *
	 * @param mixed $data Decoded JSON (object or array).
	 * @return array{valid: bool, schema_version?: string, article_key?: string, warnings: array<int, array{path: string, message: string}>, errors: array<int, array{path: string, message: string}>}
	 */
	public function validate( mixed $data ): array {
		$warnings = array();
		$errors   = array();

		if ( ! is_object( $data ) && ! is_array( $data ) ) {
			return array(
				'valid'    => false,
				'warnings' => $warnings,
				'errors'   => array(
					array(
						'path'    => '',
						'message' => __( 'Article package must be a JSON object.', 'revit-publisher' ),
					),
				),
			);
		}

		$payload = is_array( $data ) ? json_decode( wp_json_encode( $data ), false ) : $data;

		$schema_version = isset( $payload->schema_version ) ? (string) $payload->schema_version : '';
		if ( self::SCHEMA_VERSION !== $schema_version ) {
			return array(
				'valid'    => false,
				'warnings' => $warnings,
				'errors'   => array(
					array(
						'path'    => 'schema_version',
						'message' => sprintf(
							/* translators: %s: expected schema version */
							__( 'Expected schema_version "%s".', 'revit-publisher' ),
							self::SCHEMA_VERSION
						),
					),
				),
			);
		}

		$schema_errors = $this->validate_against_schema( $payload );
		if ( ! empty( $schema_errors ) ) {
			return array(
				'valid'    => false,
				'warnings' => $warnings,
				'errors'   => $schema_errors,
			);
		}

		$business_errors = $this->validate_business_rules( $payload );
		if ( ! empty( $business_errors ) ) {
			return array(
				'valid'    => false,
				'warnings' => $warnings,
				'errors'   => $business_errors,
			);
		}

		$article_key = isset( $payload->article->article_key )
			? (string) $payload->article->article_key
			: '';

		return array(
			'valid'          => true,
			'schema_version' => self::SCHEMA_VERSION,
			'article_key'    => $article_key,
			'warnings'       => $warnings,
			'errors'         => array(),
		);
	}

	/**
	 * Validate JSON string input.
	 *
	 * @param string $json Raw JSON string.
	 * @return array<string, mixed>
	 */
	public function validate_json( string $json ): array {
		if ( '' === trim( $json ) ) {
			return array(
				'valid'    => false,
				'warnings' => array(),
				'errors'   => array(
					array(
						'path'    => '',
						'message' => __( 'JSON input is empty.', 'revit-publisher' ),
					),
				),
			);
		}

		$decoded = json_decode( $json, false );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return array(
				'valid'    => false,
				'warnings' => array(),
				'errors'   => array(
					array(
						'path'    => '',
						'message' => sprintf(
							/* translators: %s: JSON parse error message */
							__( 'Invalid JSON: %s', 'revit-publisher' ),
							json_last_error_msg()
						),
					),
				),
			);
		}

		return $this->validate( $decoded );
	}

	/**
	 * Get absolute path to the JSON Schema file.
	 */
	public function get_schema_path(): string {
		return REVIT_PUBLISHER_PLUGIN_DIR . 'schemas/revit-article-v1.schema.json';
	}

	/**
	 * Run JSON Schema validation.
	 *
	 * @param object $payload Article package object.
	 * @return array<int, array{path: string, message: string}>
	 */
	private function validate_against_schema( object $payload ): array {
		if ( ! class_exists( Validator::class ) ) {
			return array(
				array(
					'path'    => '',
					'message' => __( 'JSON Schema validator dependency is missing. Run composer install in the plugin directory.', 'revit-publisher' ),
				),
			);
		}

		$schema_path = $this->get_schema_path();
		if ( ! file_exists( $schema_path ) ) {
			return array(
				array(
					'path'    => '',
					'message' => __( 'Article package schema file is missing.', 'revit-publisher' ),
				),
			);
		}

		$schema_json = file_get_contents( $schema_path );
		if ( false === $schema_json ) {
			return array(
				array(
					'path'    => '',
					'message' => __( 'Unable to read article package schema file.', 'revit-publisher' ),
				),
			);
		}

		$schema = json_decode( $schema_json );
		if ( ! is_object( $schema ) ) {
			return array(
				array(
					'path'    => '',
					'message' => __( 'Article package schema file contains invalid JSON.', 'revit-publisher' ),
				),
			);
		}

		$this->schema = $schema;

		$validator = new Validator();
		$validator->validate(
			$payload,
			$schema,
			Constraint::CHECK_MODE_APPLY_DEFAULTS
		);

		return $this->normalize_schema_errors( $validator->getErrors() );
	}

	/**
	 * Additional deterministic rules not expressible cleanly in JSON Schema.
	 *
	 * @param object $payload Article package object.
	 * @return array<int, array{path: string, message: string}>
	 */
	private function validate_business_rules( object $payload ): array {
		$errors = array();

		if ( isset( $payload->vehicle->start_year, $payload->vehicle->end_year )
			&& is_int( $payload->vehicle->start_year )
			&& is_int( $payload->vehicle->end_year )
			&& $payload->vehicle->start_year > $payload->vehicle->end_year
		) {
			$errors[] = array(
				'path'    => 'vehicle.end_year',
				'message' => __( 'end_year must be greater than or equal to start_year.', 'revit-publisher' ),
			);
		}

		return $errors;
	}

	/**
	 * Convert json-schema library errors into stable API format.
	 *
	 * @param array<int, array<string, mixed>> $raw_errors Raw validator errors.
	 * @return array<int, array{path: string, message: string}>
	 */
	private function normalize_schema_errors( array $raw_errors ): array {
		$normalized = array();

		foreach ( $raw_errors as $error ) {
			$path = $this->format_error_path( (string) ( $error['property'] ?? '' ) );
			$normalized[] = array(
				'path'    => $path,
				'message' => $this->format_error_message( $error, $path ),
			);
		}

		return $normalized;
	}

	/**
	 * Format json-schema property path for API consumers.
	 */
	private function format_error_path( string $property ): string {
		$path = ltrim( $property, '.' );
		$path = (string) preg_replace( '/\[(\d+)\]/', '.$1', $path );
		return $path;
	}

	/**
	 * Build a readable validation message.
	 *
	 * @param array<string, mixed> $error Error payload.
	 */
	private function format_error_message( array $error, string $path ): string {
		$constraint = $this->stringify_error_value( $error['constraint'] ?? '' );
		$message    = $this->stringify_error_value( $error['message'] ?? __( 'Validation failed.', 'revit-publisher' ) );

		if ( '' !== $path && str_starts_with( $message, '[' ) ) {
			return $message;
		}

		if ( '' !== $path ) {
			if ( str_starts_with( $message, $path . ':' ) || str_starts_with( $message, $path . ' ' ) ) {
				return $message;
			}
			return sprintf(
				/* translators: 1: field path, 2: error message */
				__( '%1$s: %2$s', 'revit-publisher' ),
				$path,
				$message
			);
		}

		if ( 'const' === $constraint && isset( $error['expected'] ) ) {
			$expected = $error['expected'];
			if ( is_array( $expected ) ) {
				$expected = wp_json_encode( $expected );
			}
			return sprintf(
				/* translators: %s: expected value */
				__( 'Expected value %s.', 'revit-publisher' ),
				(string) $expected
			);
		}

		return $message;
	}

	/**
	 * Convert validator error fragment to string.
	 *
	 * @param mixed $value Raw value.
	 */
	private function stringify_error_value( mixed $value ): string {
		if ( is_array( $value ) ) {
			return (string) wp_json_encode( $value );
		}

		return (string) $value;
	}
}
