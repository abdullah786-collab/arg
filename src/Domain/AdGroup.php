<?php
/**
 * Ad group entity.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Domain;

use DateTimeImmutable;
use RevGuardPro\Domain\Exception\InvalidEntityException;

defined( 'ABSPATH' ) || exit;

/**
 * An ordered set of ads. Member order is the list order and is preserved exactly; weights are stored per member.
 *
 * Whether the referenced ads exist is checked by the repository, not here.
 */
final class AdGroup {

	public const MAX_MEMBERS = 500;

	/**
	 * Display name (trimmed).
	 *
	 * @var string
	 */
	public readonly string $name;

	/**
	 * Members in order.
	 *
	 * @var list<GroupMember>
	 */
	public readonly array $members;

	/**
	 * Constructor. Use create() or hydrate().
	 *
	 * @param int|null               $id            Id, null until stored.
	 * @param string                 $name          Name.
	 * @param Status                 $status        Status.
	 * @param RotationMode           $rotation_mode Rotation mode.
	 * @param array<mixed>           $members       Members in order.
	 * @param DateTimeImmutable|null $created_at    Creation time (UTC).
	 * @param DateTimeImmutable|null $updated_at    Update time (UTC).
	 * @throws InvalidEntityException When an invariant is violated.
	 */
	private function __construct(
		public readonly ?int $id,
		string $name,
		public readonly Status $status,
		public readonly RotationMode $rotation_mode,
		array $members,
		public readonly ?DateTimeImmutable $created_at,
		public readonly ?DateTimeImmutable $updated_at
	) {
		Rules::persistedState( $id, $created_at, $updated_at );
		$this->name = Rules::name( $name );

		if ( count( $members ) > self::MAX_MEMBERS ) {
			throw new InvalidEntityException( 'members', InvalidEntityException::REASON_TOO_MANY );
		}
		$seen   = array();
		$sorted = array();
		foreach ( $members as $member ) {
			if ( ! $member instanceof GroupMember ) {
				throw new InvalidEntityException( 'members', InvalidEntityException::REASON_INVALID_VALUE );
			}
			if ( isset( $seen[ $member->ad_id ] ) ) {
				throw new InvalidEntityException( 'members', InvalidEntityException::REASON_DUPLICATE );
			}
			$seen[ $member->ad_id ] = true;
			$sorted[]               = $member;
		}
		$this->members = $sorted;
	}

	/**
	 * Builds a new, not yet stored group. New groups default to inactive.
	 *
	 * @param string       $name          Name.
	 * @param RotationMode $rotation_mode Rotation mode.
	 * @param array<mixed> $members       Members (GroupMember list, in order).
	 * @param Status       $status        Status.
	 */
	public static function create( string $name, RotationMode $rotation_mode = RotationMode::Weighted, array $members = array(), Status $status = Status::Inactive ): self {
		return new self( null, $name, $status, $rotation_mode, $members, null, null );
	}

	/**
	 * Rebuilds a stored group.
	 *
	 * @param int               $id            Id.
	 * @param string            $name          Name.
	 * @param Status            $status        Status.
	 * @param RotationMode      $rotation_mode Rotation mode.
	 * @param array<mixed>      $members       Members in order.
	 * @param DateTimeImmutable $created_at    Creation time.
	 * @param DateTimeImmutable $updated_at    Update time.
	 */
	public static function hydrate( int $id, string $name, Status $status, RotationMode $rotation_mode, array $members, DateTimeImmutable $created_at, DateTimeImmutable $updated_at ): self {
		return new self( $id, $name, $status, $rotation_mode, $members, $created_at, $updated_at );
	}

	/**
	 * Copy with a new name.
	 *
	 * @param string $name New name.
	 */
	public function withName( string $name ): self {
		return new self( $this->id, $name, $this->status, $this->rotation_mode, $this->members, $this->created_at, $this->updated_at );
	}

	/**
	 * Copy with a new status.
	 *
	 * @param Status $status New status.
	 */
	public function withStatus( Status $status ): self {
		return new self( $this->id, $this->name, $status, $this->rotation_mode, $this->members, $this->created_at, $this->updated_at );
	}

	/**
	 * Copy with a new rotation mode.
	 *
	 * @param RotationMode $rotation_mode New mode.
	 */
	public function withRotationMode( RotationMode $rotation_mode ): self {
		return new self( $this->id, $this->name, $this->status, $rotation_mode, $this->members, $this->created_at, $this->updated_at );
	}

	/**
	 * Copy with a new ordered member list.
	 *
	 * @param array<mixed> $members GroupMember list, in order.
	 */
	public function withMembers( array $members ): self {
		return new self( $this->id, $this->name, $this->status, $this->rotation_mode, $members, $this->created_at, $this->updated_at );
	}

	/**
	 * Copy marked as stored with the given identity and timestamps.
	 *
	 * @param int               $id         Assigned id.
	 * @param DateTimeImmutable $created_at Creation time.
	 * @param DateTimeImmutable $updated_at Update time.
	 */
	public function persisted( int $id, DateTimeImmutable $created_at, DateTimeImmutable $updated_at ): self {
		return new self( $id, $this->name, $this->status, $this->rotation_mode, $this->members, $created_at, $updated_at );
	}

	/**
	 * Member ad ids in order.
	 *
	 * @return list<int>
	 */
	public function memberAdIds(): array {
		return array_map( static fn( GroupMember $m ): int => $m->ad_id, $this->members );
	}
}
