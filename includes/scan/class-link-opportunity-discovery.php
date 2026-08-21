<?php
/**
 * Discover legitimate internal-link opportunities from live article state.
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deterministic related-engine / cluster / topic linking with irrelevant suppression.
 */
class RevIt_Publisher_Link_Opportunity_Discovery {

	private RevIt_Publisher_Natural_Anchor_Finder $anchors;

	public function __construct( ?RevIt_Publisher_Natural_Anchor_Finder $anchors = null ) {
		$this->anchors = $anchors ?? new RevIt_Publisher_Natural_Anchor_Finder();
	}

	/**
	 * @param array<int, array<string, mixed>> $articles Article records.
	 * @return array<int, array<string, mixed>>
	 */
	public function discover( array $articles ): array {
		$opportunities = array();
		$count         = count( $articles );

		for ( $i = 0; $i < $count; $i++ ) {
			for ( $j = 0; $j < $count; $j++ ) {
				if ( $i === $j ) {
					continue;
				}
				$found = $this->evaluate_pair( $articles[ $i ], $articles[ $j ] );
				if ( null !== $found ) {
					$opportunities[] = $found;
				}
			}
		}

		return $opportunities;
	}

	/**
	 * @param array<string, mixed> $source Source article.
	 * @param array<string, mixed> $target Target article.
	 * @return array<string, mixed>|null
	 */
	public function evaluate_pair( array $source, array $target ): ?array {
		$source_id = (int) ( $source['post_id'] ?? 0 );
		$target_id = (int) ( $target['post_id'] ?? 0 );
		if ( $source_id === $target_id ) {
			return null;
		}

		$linked = (array) ( $source['linked_post_ids'] ?? array() );
		if ( in_array( $target_id, $linked, true ) ) {
			return null;
		}

		$relationship = $this->classify( $source, $target );
		if ( null === $relationship ) {
			return null;
		}

		$body     = (string) ( $source['content_text'] ?? $source['content'] ?? '' );
		$found    = $this->anchors->find( $body, $relationship['candidates'] );
		$anchor   = is_array( $found ) ? (string) $found['anchor'] : '';
		$matched  = is_array( $found );
		$safe     = $matched && 'high' === $relationship['confidence'] && $this->anchors->is_natural( $anchor );

		if ( ! $matched ) {
			return null;
		}

		return array(
			'source_post_id'     => $source_id,
			'source_title'       => (string) ( $source['title'] ?? '' ),
			'target_post_id'     => $target_id,
			'target_title'       => (string) ( $target['title'] ?? '' ),
			'anchor'             => $anchor,
			'reason'             => $relationship['reason'],
			'confidence'         => $relationship['confidence'],
			'safe_to_auto_apply' => $safe,
			'relationship'       => $relationship['type'],
			'direction'          => $relationship['direction'],
		);
	}

	/**
	 * @param array<string, mixed> $source Source.
	 * @param array<string, mixed> $target Target.
	 * @return array<string, mixed>|null
	 */
	public function classify( array $source, array $target ): ?array {
		$source_engines = (array) ( $source['engines'] ?? array() );
		$target_engines = (array) ( $target['engines'] ?? array() );
		$shared         = RevIt_Publisher_Engine_Family::shared_families( $source_engines, $target_engines );
		$same_vehicle   = $this->same_vehicle( $source, $target );
		$same_cluster   = $this->same_cluster( $source, $target );
		$source_body    = (string) ( $source['content_text'] ?? $source['content'] ?? '' );
		$target_body    = (string) ( $target['content_text'] ?? $target['content'] ?? '' );

		if ( $this->is_generic_enthusiast_only( $source, $target, $shared, $same_vehicle, $same_cluster ) ) {
			return null;
		}

		if ( ! empty( $shared ) ) {
			$family = (string) $shared[0];
			$label  = RevIt_Publisher_Engine_Family::label( $family );
			$body_mentions_family = $this->mentions_family( $source_body, $family ) && $this->mentions_family( $target_body, $family );

			if ( 'fa24' === $family && ! $body_mentions_family ) {
				return null;
			}

			$candidates = $this->engine_candidates( $label, $source_body );
			$engine_hit = $this->anchors->find( $source_body, $candidates );
			if ( is_array( $engine_hit ) ) {
				$confidence = $body_mentions_family ? 'high' : 'medium';
				if ( 'fa24' === $family ) {
					$confidence = 'medium';
				}

				return array(
					'type'        => 'shared_engine',
					'direction'   => 'related',
					'confidence'  => $confidence,
					'reason'      => sprintf( 'Both articles discuss the %s engine family.', $label ),
					'candidates'  => $candidates,
				);
			}
		}

		if ( $same_cluster && $same_vehicle ) {
			$topic = $this->distinctive_topic( $target );
			if ( '' === $topic || ! $this->anchors->contains_phrase( $this->anchors->plain_text( $source_body ), $topic ) ) {
				$topic = $this->distinctive_topic( $source );
			}
			if ( '' === $topic ) {
				return null;
			}

			return array(
				'type'        => 'cluster_sibling',
				'direction'   => 'related',
				'confidence'  => 'high',
				'reason'      => 'Articles share vehicle and cluster context.',
				'candidates'  => array( $topic ),
			);
		}

		$inbound_topic = $this->distinctive_topic( $target );
		if ( '' !== $inbound_topic && $this->anchors->contains_phrase( $this->anchors->plain_text( $source_body ), $inbound_topic ) ) {
			$related_vehicle = $same_vehicle || $this->shares_manufacturer_model( $source, $target );
			if ( ! $related_vehicle && empty( $shared ) ) {
				return null;
			}

			return array(
				'type'        => 'inbound_natural_mention',
				'direction'   => 'inbound',
				'confidence'  => 'high',
				'reason'      => sprintf( 'Existing article naturally mentions “%s”.', $inbound_topic ),
				'candidates'  => array( $inbound_topic ),
			);
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $left Left.
	 * @param array<string, mixed> $right Right.
	 */
	private function same_vehicle( array $left, array $right ): bool {
		$l = strtolower( trim( (string) ( $left['vehicle_label'] ?? '' ) ) );
		$r = strtolower( trim( (string) ( $right['vehicle_label'] ?? '' ) ) );

		return '' !== $l && $l === $r;
	}

	/**
	 * @param array<string, mixed> $left Left.
	 * @param array<string, mixed> $right Right.
	 */
	private function same_cluster( array $left, array $right ): bool {
		$l = strtolower( trim( (string) ( $left['cluster'] ?? '' ) ) );
		$r = strtolower( trim( (string) ( $right['cluster'] ?? '' ) ) );

		return '' !== $l && $l === $r;
	}

	/**
	 * @param array<string, mixed> $left Left.
	 * @param array<string, mixed> $right Right.
	 */
	private function shares_manufacturer_model( array $left, array $right ): bool {
		$lm = strtolower( trim( (string) ( $left['manufacturer'] ?? '' ) . ' ' . (string) ( $left['model'] ?? '' ) ) );
		$rm = strtolower( trim( (string) ( $right['manufacturer'] ?? '' ) . ' ' . (string) ( $right['model'] ?? '' ) ) );

		return '' !== $lm && $lm === $rm;
	}

	/**
	 * Mustang GT ↔ Elantra N style: enthusiast cars with no mechanical relationship.
	 *
	 * @param array<string, mixed> $source Source.
	 * @param array<string, mixed> $target Target.
	 * @param string[]             $shared Shared families.
	 */
	private function is_generic_enthusiast_only( array $source, array $target, array $shared, bool $same_vehicle, bool $same_cluster ): bool {
		if ( $same_vehicle || $same_cluster || ! empty( $shared ) ) {
			return false;
		}

		$sm = strtolower( (string) ( $source['manufacturer'] ?? '' ) );
		$tm = strtolower( (string) ( $target['manufacturer'] ?? '' ) );

		return $sm !== $tm && '' !== $sm && '' !== $tm;
	}

	private function mentions_family( string $body, string $family ): bool {
		$text  = $this->anchors->plain_text( $body );
		$label = RevIt_Publisher_Engine_Family::label( $family );
		if ( $this->anchors->contains_phrase( $text, $label ) ) {
			return true;
		}
		if ( $this->anchors->contains_phrase( $text, $family ) ) {
			return true;
		}
		if ( 'fa24' === $family ) {
			return $this->anchors->contains_phrase( $text, 'boxer' );
		}

		return false;
	}

	/**
	 * @return string[]
	 */
	private function engine_candidates( string $label, string $body ): array {
		$candidates = array(
			'the ' . $label . ' engine',
			$label . ' engine',
			$label,
		);
		if ( 'FA24' === $label ) {
			$candidates[] = 'FA24-family';
			$candidates[] = 'boxer engine';
		}

		return $candidates;
	}

	/**
	 * @param array<string, mixed> $article Article.
	 */
	private function distinctive_topic( array $article ): string {
		$topic = trim( (string) ( $article['primary_topic'] ?? '' ) );
		$title = strtolower( (string) ( $article['title'] ?? '' ) );
		foreach ( array( 'water pump', 'coolant loss', 'cooling system', 'carbon buildup', 'oil consumption' ) as $phrase ) {
			if ( str_contains( strtolower( $topic . ' ' . $title ), $phrase ) ) {
				return $phrase;
			}
		}

		return $topic;
	}
}
