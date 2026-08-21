<?php
/**
 * Deterministic engine-family matching for internal-link relevance.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps engine codes to families (B58, FA24, etc.).
 */
final class RevIt_Publisher_Engine_Family {

	/**
	 * Known families and aliases.
	 *
	 * @var array<string, string[]>
	 */
	private const FAMILIES = array(
		'b58'    => array( 'b58', 'b58b30', 'b58tu', 'b58b30o1' ),
		's58'    => array( 's58' ),
		'b48'    => array( 'b48' ),
		'fa24'   => array( 'fa24', 'fa24dit', 'fa24d', 'fa24dbi' ),
		'fa20'   => array( 'fa20', 'fa20dit' ),
		'ea888'  => array( 'ea888', 'ea888.4', 'ea888 gen4', 'ea888 gen 4' ),
		'k20c1'  => array( 'k20c1' ),
		'coyote' => array( 'coyote', '5.0 coyote', '5.0l coyote', 'ford 5.0' ),
		'theta2' => array( 'g4kh', 'theta ii', 'theta 2' ),
		'9a2'    => array( '9a2', '9a2.ma1' ),
	);

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}

	/**
	 * Normalize an engine string to a family key, or empty.
	 */
	public static function family_key( string $engine ): string {
		$normalized = self::normalize( $engine );
		if ( '' === $normalized ) {
			return '';
		}

		foreach ( self::FAMILIES as $family => $aliases ) {
			foreach ( $aliases as $alias ) {
				if ( $normalized === $alias || str_contains( $normalized, $alias ) ) {
					return $family;
				}
			}
		}

		return $normalized;
	}

	/**
	 * Unique family keys from a list of engine strings.
	 *
	 * @param string[] $engines Engine labels.
	 * @return string[]
	 */
	public static function families_from_list( array $engines ): array {
		$out = array();
		foreach ( $engines as $engine ) {
			$key = self::family_key( (string) $engine );
			if ( '' !== $key ) {
				$out[ $key ] = true;
			}
		}

		return array_keys( $out );
	}

	/**
	 * Shared engine families between two lists.
	 *
	 * @param string[] $left Left engines.
	 * @param string[] $right Right engines.
	 * @return string[]
	 */
	public static function shared_families( array $left, array $right ): array {
		return array_values(
			array_intersect(
				self::families_from_list( $left ),
				self::families_from_list( $right )
			)
		);
	}

	/**
	 * Display label for a family key.
	 */
	public static function label( string $family ): string {
		$map = array(
			'b58'    => 'B58',
			's58'    => 'S58',
			'b48'    => 'B48',
			'fa24'   => 'FA24',
			'fa20'   => 'FA20',
			'ea888'  => 'EA888',
			'k20c1'  => 'K20C1',
			'coyote' => 'Coyote 5.0',
			'theta2' => 'Theta II 2.0T',
			'9a2'    => '9A2',
		);

		return $map[ $family ] ?? strtoupper( $family );
	}

	/**
	 * Lowercase compact form.
	 */
	public static function normalize( string $value ): string {
		$value = strtolower( trim( $value ) );
		$value = preg_replace( '/[^a-z0-9.]+/', ' ', $value ) ?? $value;

		return trim( preg_replace( '/\s+/', ' ', $value ) ?? $value );
	}
}
