<?php
/**
 * Cache signal adapter.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Cache;

use Closure;
use RevGuardPro\Rendering\RenderState;

defined( 'ABSPATH' ) || exit;

/**
 * Turns the request's render aggregate into the cache compatibility signal (ADR 010).
 *
 * Best effort: a cache that already decided, headers already sent, or an existing DONOTCACHEPAGE definition cannot be
 * overridden. Reads no input, runs no query and writes nothing.
 */
final class CacheSignal {

	public const CONSTANT = 'DONOTCACHEPAGE';
	public const ACTION   = 'revguard_pro_cache_decision';

	/**
	 * Whether the signal was already applied in this request.
	 *
	 * @var bool
	 */
	private bool $done = false;

	/**
	 * Builds the adapter from injectable side effects.
	 *
	 * @param RenderState $state        Request render aggregate.
	 * @param CachePolicy $policy       Policy.
	 * @param Closure     $is_defined   Closure( string $name ): bool.
	 * @param Closure     $define       Closure( string $name ): void; defines the constant as true.
	 * @param Closure     $headers_sent Closure(): bool.
	 * @param Closure     $nocache      Closure(): void; sends the no-cache headers.
	 * @param Closure     $emit         Closure( string $action, CacheDecision $decision ): void.
	 */
	public function __construct(
		private readonly RenderState $state,
		private readonly CachePolicy $policy,
		private readonly Closure $is_defined,
		private readonly Closure $define,
		private readonly Closure $headers_sent,
		private readonly Closure $nocache,
		private readonly Closure $emit
	) {
	}

	/**
	 * Wires the real WordPress and PHP functions.
	 *
	 * @param RenderState $state  Request render aggregate.
	 * @param CachePolicy $policy Policy.
	 */
	public static function forWordPress( RenderState $state, CachePolicy $policy ): self {
		return new self(
			$state,
			$policy,
			static fn( string $name ): bool => defined( $name ),
			static function ( string $name ): void {
				define( $name, true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.VariableConstantNameFound -- Cache plugins' documented constant name, passed in.
			},
			static fn(): bool => headers_sent(),
			static function (): void {
				nocache_headers();
			},
			static function ( string $action, CacheDecision $decision ): void {
				do_action( $action, $decision ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- The action name is the revguard_pro_ constant passed in.
			}
		);
	}

	/**
	 * Applies the signal once per request; does nothing when the request touched no ad.
	 */
	public function apply(): void {
		if ( $this->done || ! $this->state->touched() ) {
			return;
		}
		$this->done = true;

		$decision = $this->policy->decide( $this->state->cacheProfile(), $this->state->validUntil() );
		if ( $decision->bypass ) {
			if ( ! ( $this->is_defined )( self::CONSTANT ) ) {
				( $this->define )( self::CONSTANT );
			}
			if ( ! ( $this->headers_sent )() ) {
				( $this->nocache )();
			}
		}
		( $this->emit )( self::ACTION, $decision );
	}
}
