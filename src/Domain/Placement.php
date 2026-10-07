<?php
/**
 * Placement entity.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Domain;

use DateTimeImmutable;
use RevGuardPro\Domain\Exception\InvalidEntityException;
use RevGuardPro\Targeting\TargetingDefinition;

defined( 'ABSPATH' ) || exit;

/**
 * Where and how an ad or group is shown: a typed configuration plus an optional target reference.
 *
 * The placement type is derived from its configuration, so the two can never disagree.
 * Invariants: an active placement has a target; a target type and id are both set or both null.
 * Whether the target exists is checked by the repository.
 */
final class Placement {

	public const PRIORITY_MIN     = 0;
	public const PRIORITY_MAX     = 1000;
	public const PRIORITY_DEFAULT = 100;

	/**
	 * Display name (trimmed).
	 *
	 * @var string
	 */
	public readonly string $name;

	/**
	 * Constructor. Use create() or hydrate().
	 *
	 * @param int|null               $id         Id, null until stored.
	 * @param string                 $name       Name.
	 * @param Status                 $status     Status.
	 * @param PlacementConfig        $config     Typed configuration.
	 * @param TargetType|null        $target_type Target kind, or null when unassigned.
	 * @param int|null               $target_id  Target id, or null when unassigned.
	 * @param int                    $priority   Evaluation order: lower number first, ties by id (see PlacementOrdering).
	 * @param TargetingDefinition    $targeting  Targeting (unrestricted when it has no rules).
	 * @param DateTimeImmutable|null $created_at Creation time (UTC).
	 * @param DateTimeImmutable|null $updated_at Update time (UTC).
	 * @throws InvalidEntityException When an invariant is violated.
	 */
	private function __construct(
		public readonly ?int $id,
		string $name,
		public readonly Status $status,
		public readonly PlacementConfig $config,
		public readonly ?TargetType $target_type,
		public readonly ?int $target_id,
		public readonly int $priority,
		public readonly TargetingDefinition $targeting,
		public readonly ?DateTimeImmutable $created_at,
		public readonly ?DateTimeImmutable $updated_at
	) {
		Rules::persistedState( $id, $created_at, $updated_at );
		$this->name = Rules::name( $name );
		Rules::range( $priority, self::PRIORITY_MIN, self::PRIORITY_MAX, 'priority' );

		if ( ( null === $target_type ) !== ( null === $target_id ) ) {
			throw new InvalidEntityException( 'target', InvalidEntityException::REASON_INVALID_VALUE );
		}
		if ( null !== $target_id ) {
			Rules::id( $target_id, 'target' );
		}
		if ( Status::Active === $status && null === $target_type ) {
			throw new InvalidEntityException( 'target', InvalidEntityException::REASON_MISSING );
		}
	}

	/**
	 * Builds a new, not yet stored placement. New placements default to inactive.
	 *
	 * @param string                   $name        Name.
	 * @param PlacementConfig          $config      Typed configuration.
	 * @param TargetType|null          $target_type Target kind.
	 * @param int|null                 $target_id   Target id.
	 * @param int                      $priority    Priority.
	 * @param Status                   $status      Status.
	 * @param TargetingDefinition|null $targeting Targeting; null means unrestricted.
	 */
	public static function create( string $name, PlacementConfig $config, ?TargetType $target_type = null, ?int $target_id = null, int $priority = self::PRIORITY_DEFAULT, Status $status = Status::Inactive, ?TargetingDefinition $targeting = null ): self {
		return new self( null, $name, $status, $config, $target_type, $target_id, $priority, $targeting ?? TargetingDefinition::none(), null, null );
	}

	/**
	 * Rebuilds a stored placement.
	 *
	 * @param int                      $id          Id.
	 * @param string                   $name        Name.
	 * @param Status                   $status      Status.
	 * @param PlacementConfig          $config      Typed configuration.
	 * @param TargetType|null          $target_type Target kind.
	 * @param int|null                 $target_id   Target id.
	 * @param int                      $priority    Priority.
	 * @param DateTimeImmutable        $created_at  Creation time.
	 * @param DateTimeImmutable        $updated_at  Update time.
	 * @param TargetingDefinition|null $targeting Targeting; null means unrestricted.
	 */
	public static function hydrate( int $id, string $name, Status $status, PlacementConfig $config, ?TargetType $target_type, ?int $target_id, int $priority, DateTimeImmutable $created_at, DateTimeImmutable $updated_at, ?TargetingDefinition $targeting = null ): self {
		return new self( $id, $name, $status, $config, $target_type, $target_id, $priority, $targeting ?? TargetingDefinition::none(), $created_at, $updated_at );
	}

	/**
	 * The placement type (derived from the configuration).
	 */
	public function type(): PlacementType {
		return $this->config->type;
	}

	/**
	 * Copy with a new name.
	 *
	 * @param string $name New name.
	 */
	public function withName( string $name ): self {
		return new self( $this->id, $name, $this->status, $this->config, $this->target_type, $this->target_id, $this->priority, $this->targeting, $this->created_at, $this->updated_at );
	}

	/**
	 * Copy with a new status.
	 *
	 * @param Status $status New status.
	 */
	public function withStatus( Status $status ): self {
		return new self( $this->id, $this->name, $status, $this->config, $this->target_type, $this->target_id, $this->priority, $this->targeting, $this->created_at, $this->updated_at );
	}

	/**
	 * Copy with a new configuration (this may change the placement type).
	 *
	 * @param PlacementConfig $config New configuration.
	 */
	public function withConfig( PlacementConfig $config ): self {
		return new self( $this->id, $this->name, $this->status, $config, $this->target_type, $this->target_id, $this->priority, $this->targeting, $this->created_at, $this->updated_at );
	}

	/**
	 * Copy with a new target (both null to unassign).
	 *
	 * @param TargetType|null $target_type Target kind.
	 * @param int|null        $target_id   Target id.
	 */
	public function withTarget( ?TargetType $target_type, ?int $target_id ): self {
		return new self( $this->id, $this->name, $this->status, $this->config, $target_type, $target_id, $this->priority, $this->targeting, $this->created_at, $this->updated_at );
	}

	/**
	 * Copy with a new priority.
	 *
	 * @param int $priority New priority.
	 */
	public function withPriority( int $priority ): self {
		return new self( $this->id, $this->name, $this->status, $this->config, $this->target_type, $this->target_id, $priority, $this->targeting, $this->created_at, $this->updated_at );
	}

	/**
	 * Copy with new targeting.
	 *
	 * @param TargetingDefinition $targeting New targeting.
	 */
	public function withTargeting( TargetingDefinition $targeting ): self {
		return new self( $this->id, $this->name, $this->status, $this->config, $this->target_type, $this->target_id, $this->priority, $targeting, $this->created_at, $this->updated_at );
	}

	/**
	 * Copy marked as stored with the given identity and timestamps.
	 *
	 * @param int               $id         Assigned id.
	 * @param DateTimeImmutable $created_at Creation time.
	 * @param DateTimeImmutable $updated_at Update time.
	 */
	public function persisted( int $id, DateTimeImmutable $created_at, DateTimeImmutable $updated_at ): self {
		return new self( $id, $this->name, $this->status, $this->config, $this->target_type, $this->target_id, $this->priority, $this->targeting, $created_at, $updated_at );
	}
}
