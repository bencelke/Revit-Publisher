<?php
/**
 * Content plan JSON Schema validation.
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
 * Validates revit-content-plan-v1 documents.
 */
class RevIt_Publisher_Content_Plan_Validator {

	public const SCHEMA_VERSION = 'revit-content-plan-v1';

	private ?object $schema = null;

	/**
	 * Validate content plan payload.
	 *
	 * @return array{valid: bool, plan_key?: string, errors: array<int, array{path: string, message: string}>}
	 */
	public function validate( mixed $data ): array {
		$errors = array();

		if ( ! is_object( $data ) && ! is_array( $data ) ) {
			return array(
				'valid'  => false,
				'errors' => array(
					array(
						'path'    => '',
						'message' => __( 'Content plan must be a JSON object.', 'revit-publisher' ),
					),
				),
			);
		}

		$payload = is_array( $data ) ? json_decode( wp_json_encode( $data ), false ) : $data;

		if ( self::SCHEMA_VERSION !== (string) ( $payload->schema_version ?? '' ) ) {
			return array(
				'valid'  => false,
				'errors' => array(
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
			return array( 'valid' => false, 'errors' => $schema_errors );
		}

		$business_errors = $this->validate_business_rules( $payload );
		if ( ! empty( $business_errors ) ) {
			return array( 'valid' => false, 'errors' => $business_errors );
		}

		return array(
			'valid'    => true,
			'plan_key' => (string) $payload->plan_key,
			'errors'   => array(),
		);
	}

	/**
	 * @return array<int, array{path: string, message: string}>
	 */
	private function validate_against_schema( object $payload ): array {
		$validator = new Validator();
		$validator->validate( $payload, $this->get_schema(), Constraint::CHECK_MODE_APPLY_DEFAULTS );

		if ( $validator->isValid() ) {
			return array();
		}

		$errors = array();
		foreach ( $validator->getErrors() as $error ) {
			$errors[] = array(
				'path'    => (string) ( $error['property'] ?? '' ),
				'message' => (string) ( $error['message'] ?? __( 'Schema validation failed.', 'revit-publisher' ) ),
			);
		}

		return $errors;
	}

	/**
	 * @return array<int, array{path: string, message: string}>
	 */
	private function validate_business_rules( object $payload ): array {
		$errors       = array();
		$cluster_keys = array();
		$article_keys = array();

		foreach ( $payload->clusters as $index => $cluster ) {
			$key = (string) ( $cluster->cluster_key ?? '' );
			if ( in_array( $key, $cluster_keys, true ) ) {
				$errors[] = array(
					'path'    => "clusters[{$index}].cluster_key",
					'message' => __( 'Duplicate cluster_key.', 'revit-publisher' ),
				);
			}
			$cluster_keys[] = $key;
		}

		foreach ( $payload->articles as $index => $article ) {
			$key = (string) ( $article->article_key ?? '' );
			if ( in_array( $key, $article_keys, true ) ) {
				$errors[] = array(
					'path'    => "articles[{$index}].article_key",
					'message' => __( 'Duplicate article_key.', 'revit-publisher' ),
				);
			}
			$article_keys[] = $key;

			$cluster_key = (string) ( $article->cluster_key ?? '' );
			if ( ! in_array( $cluster_key, $cluster_keys, true ) ) {
				$errors[] = array(
					'path'    => "articles[{$index}].cluster_key",
					'message' => __( 'Referenced cluster_key does not exist in clusters.', 'revit-publisher' ),
				);
			}
		}

		foreach ( $payload->clusters as $index => $cluster ) {
			$pillar_key = (string) ( $cluster->pillar_article_key ?? '' );
			if ( ! in_array( $pillar_key, $article_keys, true ) ) {
				$errors[] = array(
					'path'    => "clusters[{$index}].pillar_article_key",
					'message' => __( 'pillar_article_key must reference a declared article.', 'revit-publisher' ),
				);
			}

			foreach ( (array) ( $cluster->articles ?? array() ) as $article_key ) {
				if ( ! in_array( (string) $article_key, $article_keys, true ) ) {
					$errors[] = array(
						'path'    => "clusters[{$index}].articles",
						'message' => sprintf(
							/* translators: %s: article key */
							__( 'Cluster references undeclared article_key "%s".', 'revit-publisher' ),
							(string) $article_key
						),
					);
				}
			}
		}

		if ( ! empty( $payload->relationships ) && is_array( $payload->relationships ) ) {
			foreach ( $payload->relationships as $index => $relationship ) {
				$source = (string) ( $relationship->source_article_key ?? '' );
				$target = (string) ( $relationship->target_article_key ?? '' );
				if ( ! in_array( $source, $article_keys, true ) ) {
					$errors[] = array(
						'path'    => "relationships[{$index}].source_article_key",
						'message' => __( 'Relationship source must reference a declared article.', 'revit-publisher' ),
					);
				}
				if ( ! in_array( $target, $article_keys, true ) ) {
					$errors[] = array(
						'path'    => "relationships[{$index}].target_article_key",
						'message' => __( 'Relationship target must reference a declared article.', 'revit-publisher' ),
					);
				}
			}
		}

		return $errors;
	}

	private function get_schema(): object {
		if ( null === $this->schema ) {
			$path = REVIT_PUBLISHER_PLUGIN_DIR . 'schemas/revit-content-plan-v1.schema.json';
			$raw  = file_get_contents( $path );
			$this->schema = json_decode( (string) $raw, false );
		}

		return $this->schema;
	}
}
