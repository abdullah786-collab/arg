<?php
/**
 * Pure targeting evaluation.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Targeting;

defined( 'ABSPATH' ) || exit;

/**
 * Decides whether a definition matches a context. Pure and deterministic: no queries, no remote calls, no
 * superglobals, no WordPress functions, no clock, no randomness.
 *
 * Fail closed: a corrupt definition never matches, and input that is unavailable (an untrustworthy URL path)
 * makes its rule false regardless of negation.
 */
final class TargetingEvaluator {

	/**
	 * Whether the definition matches the context.
	 *
	 * @param TargetingDefinition $definition Definition.
	 * @param TargetingContext    $context    Context.
	 */
	public function matches( TargetingDefinition $definition, TargetingContext $context ): bool {
		if ( $definition->isCorrupt() ) {
			return false;
		}
		if ( array() === $definition->rules ) {
			return true;
		}

		$all = Mode::All === $definition->mode;
		foreach ( $definition->rules as $rule ) {
			$base   = $this->evaluate( $rule, $context );
			$result = null === $base ? false : ( $rule->negate ? ! $base : $base );
			if ( $all && ! $result ) {
				return false;
			}
			if ( ! $all && $result ) {
				return true;
			}
		}
		return $all;
	}

	/**
	 * A rule's result before negation, or null when its input is unavailable.
	 *
	 * @param Rule             $rule    Rule.
	 * @param TargetingContext $context Context.
	 */
	private function evaluate( Rule $rule, TargetingContext $context ): ?bool {
		return match ( $rule->type ) {
			RuleType::Device     => in_array( $context->device->value, $rule->values, true ),
			RuleType::PostType   => '' !== $context->post_type && in_array( $context->post_type, $rule->values, true ),
			RuleType::Taxonomy   => array() !== array_intersect( $context->terms[ (string) $rule->key ] ?? array(), $rule->values ),
			RuleType::UserState  => in_array( $context->logged_in ? 'logged_in' : 'logged_out', $rule->values, true ),
			RuleType::UserRole   => array() !== array_intersect( $context->roles, $rule->values ),
			RuleType::Url        => null === $context->path ? null : $this->url( $rule, $context->path ),
			RuleType::Referrer   => $this->referrer( $rule, $context ),
			RuleType::QueryParam => $this->queryParam( $rule, $context ),
		};
	}

	/**
	 * URL rule on a normalized path.
	 *
	 * @param Rule   $rule Rule.
	 * @param string $path Normalized path.
	 */
	private function url( Rule $rule, string $path ): bool {
		foreach ( $rule->values as $value ) {
			$hit = match ( $rule->operator ) {
				Operator::Equals     => $path === $value,
				Operator::StartsWith => '/' === $value || $path === $value || str_starts_with( $path, $value . '/' ),
				Operator::EndsWith   => str_ends_with( $path, $value ),
				Operator::Contains   => str_contains( $path, $value ),
				default              => false,
			};
			if ( $hit ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Referrer rule.
	 *
	 * @param Rule             $rule    Rule.
	 * @param TargetingContext $context Context.
	 */
	private function referrer( Rule $rule, TargetingContext $context ): bool {
		$host = $context->referrer_host;
		if ( Operator::Direct === $rule->operator ) {
			return null === $host;
		}
		if ( null === $host ) {
			return false;
		}
		if ( Operator::Internal === $rule->operator ) {
			return '' !== $context->site_host && $host === $context->site_host;
		}
		foreach ( $rule->values as $domain ) {
			if ( $host === $domain || str_ends_with( $host, '.' . $domain ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Query parameter rule.
	 *
	 * @param Rule             $rule    Rule.
	 * @param TargetingContext $context Context.
	 */
	private function queryParam( Rule $rule, TargetingContext $context ): bool {
		$name = (string) $rule->key;
		if ( ! array_key_exists( $name, $context->query_params ) ) {
			return false;
		}
		return Operator::Exists === $rule->operator || in_array( $context->query_params[ $name ], $rule->values, true );
	}
}
