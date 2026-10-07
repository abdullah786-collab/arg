<?php
/**
 * What request data a set of definitions needs.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Targeting;

defined( 'ABSPATH' ) || exit;

/**
 * Lets the context adapter collect only what the rules in play need (no UA parsing, term lookups or parameter
 * reads for rule types nobody uses).
 */
final class TargetingRequirements {

	/**
	 * Constructor.
	 *
	 * @param bool         $device     A device rule is present.
	 * @param bool         $url        A url rule is present.
	 * @param bool         $referrer   A referrer rule is present.
	 * @param bool         $user_state A user_state rule is present.
	 * @param bool         $user_roles A user_role rule is present.
	 * @param bool         $post_type  A post_type rule is present.
	 * @param array<mixed> $taxonomies Taxonomy slugs needed.
	 * @param array<mixed> $query_keys Query parameter names needed.
	 */
	public function __construct(
		public readonly bool $device = false,
		public readonly bool $url = false,
		public readonly bool $referrer = false,
		public readonly bool $user_state = false,
		public readonly bool $user_roles = false,
		public readonly bool $post_type = false,
		public readonly array $taxonomies = array(),
		public readonly array $query_keys = array()
	) {
	}

	/**
	 * Requirements of a list of rules.
	 *
	 * @param array<Rule> $rules Rules.
	 */
	public static function fromRules( array $rules ): self {
		$flags      = array_fill_keys( array( 'device', 'url', 'referrer', 'user_state', 'user_role', 'post_type' ), false );
		$taxonomies = array();
		$keys       = array();
		foreach ( $rules as $rule ) {
			match ( $rule->type ) {
				RuleType::Taxonomy   => $taxonomies[ (string) $rule->key ] = (string) $rule->key,
				RuleType::QueryParam => $keys[ (string) $rule->key ]       = (string) $rule->key,
				default              => $flags[ $rule->type->value ]       = true,
			};
		}
		ksort( $taxonomies );
		ksort( $keys );
		return new self( $flags['device'], $flags['url'], $flags['referrer'], $flags['user_state'], $flags['user_role'], $flags['post_type'], array_values( $taxonomies ), array_values( $keys ) );
	}

	/**
	 * Union of several requirements.
	 *
	 * @param TargetingRequirements ...$all Requirements.
	 */
	public static function merge( TargetingRequirements ...$all ): self {
		$flags      = array(
			'device'     => false,
			'url'        => false,
			'referrer'   => false,
			'user_state' => false,
			'user_roles' => false,
			'post_type'  => false,
		);
		$taxonomies = array();
		$keys       = array();
		foreach ( $all as $requirements ) {
			foreach ( array_keys( $flags ) as $flag ) {
				$flags[ $flag ] = $flags[ $flag ] || $requirements->$flag;
			}
			$taxonomies = array_merge( $taxonomies, $requirements->taxonomies );
			$keys       = array_merge( $keys, $requirements->query_keys );
		}
		$taxonomies = array_values( array_unique( $taxonomies ) );
		$keys       = array_values( array_unique( $keys ) );
		sort( $taxonomies );
		sort( $keys );
		return new self( $flags['device'], $flags['url'], $flags['referrer'], $flags['user_state'], $flags['user_roles'], $flags['post_type'], $taxonomies, $keys );
	}
}
