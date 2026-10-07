<?php
/**
 * Request-scoped mutable render state registry.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Rendering;

use Closure;
use DateTimeImmutable;
use RevGuardPro\Targeting\CacheProfile;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Tracks which ads have been rendered, and accumulates
 * cache profiles and validity windows for the current request. Pure: no WordPress functions.
 */
final class RenderState {

	/**
	 * Rendered ad ids per scope. Keys are scope ints, values are ordered id lists.
	 *
	 * @var array<int,array<int,true>>
	 */
	private array $rendered = array();


	/**
	 * Observers of newly rendered ads.
	 *
	 * @var array<Closure>
	 */
	private array $listeners = array();

	/**
	 * Accumulated cache profiles.
	 *
	 * @var array<CacheProfile>
	 */
	private array $profiles = array();

	/**
	 * Earliest valid-until timestamp, or null when none has been added.
	 *
	 * @var DateTimeImmutable|null
	 */
	private ?DateTimeImmutable $valid_until = null;

	/**
	 * Returns whether an ad has been marked as rendered in a scope.
	 *
	 * @param int $scope  Scope identifier.
	 * @param int $ad_id  Ad id.
	 */
	public function rendered( int $scope, int $ad_id ): bool {
		return isset( $this->rendered[ $scope ][ $ad_id ] );
	}

	/**
	 * Marks an ad as rendered in a scope.
	 *
	 * @param int $scope  Scope identifier.
	 * @param int $ad_id  Ad id.
	 */
	public function markRendered( int $scope, int $ad_id ): void {
		if ( ! isset( $this->rendered[ $scope ] ) ) {
			$this->rendered[ $scope ] = array();
		}
		$is_new                             = ! isset( $this->rendered[ $scope ][ $ad_id ] );
		$this->rendered[ $scope ][ $ad_id ] = true;
		if ( $is_new ) {
			$this->notify( $scope, $ad_id );
		}
	}

	/**
	 * Registers an observer called once per newly rendered (scope, ad). Observers are never allowed to break rendering.
	 *
	 * @param Closure $listener Closure(int $scope, int $ad_id): void.
	 */
	public function onRendered( Closure $listener ): void {
		$this->listeners[] = $listener;
	}

	/**
	 * Calls every observer; a failing observer is ignored.
	 *
	 * @param int $scope Post scope.
	 * @param int $ad_id Ad id.
	 */
	private function notify( int $scope, int $ad_id ): void {
		foreach ( $this->listeners as $listener ) {
			try {
				$listener( $scope, $ad_id );
			} catch ( Throwable ) {
				continue;
			}
		}
	}

	/**
	 * Returns the list of ad ids rendered in a scope, in insertion order.
	 *
	 * @param int $scope Scope identifier.
	 * @return array<int>
	 */
	public function renderedIds( int $scope ): array {
		if ( ! isset( $this->rendered[ $scope ] ) ) {
			return array();
		}
		return array_keys( $this->rendered[ $scope ] );
	}



	/**
	 * Adds a cache profile to the accumulated set.
	 *
	 * @param CacheProfile $profile Cache profile to add.
	 */
	public function addProfile( CacheProfile $profile ): void {
		$this->profiles[] = $profile;
	}

	/**
	 * Returns the merged cache profile of all added profiles, or CacheProfile::none() when empty.
	 */
	public function cacheProfile(): CacheProfile {
		if ( array() === $this->profiles ) {
			return CacheProfile::none();
		}
		return CacheProfile::merge( ...$this->profiles );
	}

	/**
	 * Adds a valid-until timestamp. Null is ignored. The earliest timestamp wins.
	 *
	 * @param DateTimeImmutable|null $at Valid-until timestamp, or null to ignore.
	 */
	public function addValidUntil( ?DateTimeImmutable $at ): void {
		if ( null === $at ) {
			return;
		}
		if ( null === $this->valid_until || $at < $this->valid_until ) {
			$this->valid_until = $at;
		}
	}

	/**
	 * Returns the earliest valid-until timestamp added, or null when none has been added.
	 */
	public function validUntil(): ?DateTimeImmutable {
		return $this->valid_until;
	}

	/**
	 * Whether this request rendered or considered any ad: an ad rendered, a schedule bound was recorded, or a considered profile has targeting dimensions.
	 */
	public function touched(): bool {
		if ( array() !== $this->rendered || null !== $this->valid_until ) {
			return true;
		}
		foreach ( $this->profiles as $profile ) {
			if ( array() !== $profile->dimensions ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Resets all state.
	 */
	public function reset(): void {
		$this->rendered    = array();
		$this->profiles    = array();
		$this->valid_until = null;
	}
}
