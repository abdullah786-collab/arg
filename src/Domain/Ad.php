<?php
/**
 * Ad entity.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Domain;

use DateTimeImmutable;
use RevGuardPro\Domain\Exception\InvalidEntityException;
use RevGuardPro\Scheduling\Schedule;
use RevGuardPro\Targeting\TargetingDefinition;

defined( 'ABSPATH' ) || exit;

/**
 * An ad: a named creative. Immutable; every change goes through a with*() method that re-validates.
 *
 * The creative is stored as provided (never HTML-escaped here). Any markup policy (KSES) is applied
 * through Domain\Repository\CreativePolicyInterface at the repository boundary, and escaping at render time.
 */
final class Ad {

	public const MAX_CREATIVE_BYTES = 100000;

	/**
	 * Display name (trimmed).
	 *
	 * @var string
	 */
	public readonly string $name;

	/**
	 * Constructor. Use create() or hydrate().
	 *
	 * @param int|null               $id            Id, null until stored.
	 * @param string                 $name          Name.
	 * @param Status                 $status        Status.
	 * @param CreativeType           $creative_type Creative type.
	 * @param string                 $creative      Creative payload.
	 * @param Schedule               $schedule      Schedule (always active when it has no bounds).
	 * @param TargetingDefinition    $targeting     Targeting (unrestricted when it has no rules).
	 * @param DateTimeImmutable|null $created_at    Creation time (UTC), null until stored.
	 * @param DateTimeImmutable|null $updated_at    Last update time (UTC), null until stored.
	 * @throws InvalidEntityException When an invariant is violated.
	 */
	private function __construct(
		public readonly ?int $id,
		string $name,
		public readonly Status $status,
		public readonly CreativeType $creative_type,
		public readonly string $creative,
		public readonly Schedule $schedule,
		public readonly TargetingDefinition $targeting,
		public readonly ?DateTimeImmutable $created_at,
		public readonly ?DateTimeImmutable $updated_at
	) {
		Rules::persistedState( $id, $created_at, $updated_at );
		$this->name = Rules::name( $name );
		Rules::payload( $creative, self::MAX_CREATIVE_BYTES, 'creative' );
		if ( Status::Active === $status && '' === trim( $creative ) ) {
			throw new InvalidEntityException( 'creative', InvalidEntityException::REASON_EMPTY );
		}
	}

	/**
	 * Builds a new, not yet stored ad. New ads default to inactive.
	 *
	 * @param string                   $name          Name.
	 * @param CreativeType             $creative_type Creative type.
	 * @param string                   $creative      Creative payload.
	 * @param Status                   $status        Status.
	 * @param Schedule|null            $schedule     Schedule; null means always active.
	 * @param TargetingDefinition|null $targeting Targeting; null means unrestricted.
	 */
	public static function create( string $name, CreativeType $creative_type, string $creative, Status $status = Status::Inactive, ?Schedule $schedule = null, ?TargetingDefinition $targeting = null ): self {
		return new self( null, $name, $status, $creative_type, $creative, $schedule ?? Schedule::always(), $targeting ?? TargetingDefinition::none(), null, null );
	}

	/**
	 * Rebuilds a stored ad.
	 *
	 * @param int                      $id            Id.
	 * @param string                   $name          Name.
	 * @param Status                   $status        Status.
	 * @param CreativeType             $creative_type Creative type.
	 * @param string                   $creative      Creative payload.
	 * @param DateTimeImmutable        $created_at    Creation time.
	 * @param DateTimeImmutable        $updated_at    Update time.
	 * @param Schedule|null            $schedule      Schedule; null means always active.
	 * @param TargetingDefinition|null $targeting Targeting; null means unrestricted.
	 */
	public static function hydrate( int $id, string $name, Status $status, CreativeType $creative_type, string $creative, DateTimeImmutable $created_at, DateTimeImmutable $updated_at, ?Schedule $schedule = null, ?TargetingDefinition $targeting = null ): self {
		return new self( $id, $name, $status, $creative_type, $creative, $schedule ?? Schedule::always(), $targeting ?? TargetingDefinition::none(), $created_at, $updated_at );
	}

	/**
	 * Copy with a new name.
	 *
	 * @param string $name New name.
	 */
	public function withName( string $name ): self {
		return new self( $this->id, $name, $this->status, $this->creative_type, $this->creative, $this->schedule, $this->targeting, $this->created_at, $this->updated_at );
	}

	/**
	 * Copy with a new status.
	 *
	 * @param Status $status New status.
	 */
	public function withStatus( Status $status ): self {
		return new self( $this->id, $this->name, $status, $this->creative_type, $this->creative, $this->schedule, $this->targeting, $this->created_at, $this->updated_at );
	}

	/**
	 * Copy with a new creative (type and payload change together).
	 *
	 * @param CreativeType $creative_type New type.
	 * @param string       $creative      New payload.
	 */
	public function withCreative( CreativeType $creative_type, string $creative ): self {
		return new self( $this->id, $this->name, $this->status, $creative_type, $creative, $this->schedule, $this->targeting, $this->created_at, $this->updated_at );
	}

	/**
	 * Copy with a new schedule.
	 *
	 * @param Schedule $schedule New schedule.
	 */
	public function withSchedule( Schedule $schedule ): self {
		return new self( $this->id, $this->name, $this->status, $this->creative_type, $this->creative, $schedule, $this->targeting, $this->created_at, $this->updated_at );
	}

	/**
	 * Copy with new targeting.
	 *
	 * @param TargetingDefinition $targeting New targeting.
	 */
	public function withTargeting( TargetingDefinition $targeting ): self {
		return new self( $this->id, $this->name, $this->status, $this->creative_type, $this->creative, $this->schedule, $targeting, $this->created_at, $this->updated_at );
	}

	/**
	 * Copy marked as stored with the given identity and timestamps.
	 *
	 * @param int               $id         Assigned id.
	 * @param DateTimeImmutable $created_at Creation time.
	 * @param DateTimeImmutable $updated_at Update time.
	 */
	public function persisted( int $id, DateTimeImmutable $created_at, DateTimeImmutable $updated_at ): self {
		return new self( $id, $this->name, $this->status, $this->creative_type, $this->creative, $this->schedule, $this->targeting, $created_at, $updated_at );
	}
}
