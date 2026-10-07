<?php
/**
 * Loads a group and selects an ad from it.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Rotation;

use Closure;
use RevGuardPro\Domain\Ad;
use RevGuardPro\Domain\Exception\CorruptRecordException;
use RevGuardPro\Domain\Repository\AdGroupRepositoryInterface;
use RevGuardPro\Domain\Repository\AdRepositoryInterface;
use RevGuardPro\Domain\Status;
use RevGuardPro\Support\ClockInterface;

defined( 'ABSPATH' ) || exit;

/**
 * The only M2 class that touches storage: 3 queries per selection (group row, its members, one batch of ads),
 * no per-ad lookups and no writes. A missing, inactive, empty or corrupt group yields no selection.
 */
final class GroupAdSelector {

	/**
	 * Constructor.
	 *
	 * @param AdGroupRepositoryInterface $groups   Group repository.
	 * @param AdRepositoryInterface      $ads      Ad repository.
	 * @param GroupSelector              $selector Pure selector.
	 * @param ClockInterface             $clock    Time source.
	 */
	public function __construct(
		private readonly AdGroupRepositoryInterface $groups,
		private readonly AdRepositoryInterface $ads,
		private readonly GroupSelector $selector,
		private readonly ClockInterface $clock
	) {
	}

	/**
	 * Selects an ad from a group, or null.
	 *
	 * @param int          $group_id Group id.
	 * @param Closure|null $narrow   Optional candidate filter (see GroupSelector::select()).
	 */
	public function select( int $group_id, ?Closure $narrow = null ): ?Ad {
		try {
			$group = $this->groups->find( $group_id );
		} catch ( CorruptRecordException ) {
			return null;
		}
		if ( null === $group || Status::Active !== $group->status || array() === $group->members ) {
			return null;
		}

		return $this->selector->select( $group, $this->ads->findMany( $group->memberAdIds() ), $this->clock->now(), $narrow );
	}
}
