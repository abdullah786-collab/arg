<?php
/**
 * Schedule value object.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Scheduling;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use RevGuardPro\Domain\Exception\InvalidEntityException;

defined( 'ABSPATH' ) || exit;

/**
 * An optional start and end instant. Active iff `start <= now < end` (start inclusive, end exclusive; an absent
 * bound is unbounded). Instants are absolute: whole seconds, held in UTC, within 2000-01-01 to 2099-12-31.
 * No timezone enters evaluation; see docs/adr/002-scheduling-rotation.md.
 */
final class Schedule {

	public const MIN_TIMESTAMP = 946684800;
	public const MAX_TIMESTAMP = 4102444799;

	/**
	 * Start instant (UTC, whole seconds), or null for no lower bound.
	 *
	 * @var DateTimeImmutable|null
	 */
	public readonly ?DateTimeImmutable $start;

	/**
	 * End instant (UTC, whole seconds, exclusive), or null for no upper bound.
	 *
	 * @var DateTimeImmutable|null
	 */
	public readonly ?DateTimeImmutable $end;

	/**
	 * Constructor. Use always() or between().
	 *
	 * @param DateTimeInterface|null $start Start.
	 * @param DateTimeInterface|null $end   End.
	 * @throws InvalidEntityException When a bound is out of range or end is not after start.
	 */
	private function __construct( ?DateTimeInterface $start, ?DateTimeInterface $end ) {
		$this->start = null === $start ? null : self::normalize( $start, 'schedule_start' );
		$this->end   = null === $end ? null : self::normalize( $end, 'schedule_end' );

		if ( null !== $this->start && null !== $this->end && $this->end->getTimestamp() <= $this->start->getTimestamp() ) {
			throw new InvalidEntityException( 'schedule', InvalidEntityException::REASON_INVALID_INTERVAL );
		}
	}

	/**
	 * A schedule that is always active.
	 */
	public static function always(): self {
		return new self( null, null );
	}

	/**
	 * A schedule with optional bounds.
	 *
	 * @param DateTimeInterface|null $start Start (inclusive), or null.
	 * @param DateTimeInterface|null $end   End (exclusive), or null.
	 */
	public static function between( ?DateTimeInterface $start, ?DateTimeInterface $end ): self {
		return new self( $start, $end );
	}

	/**
	 * Whether the schedule has no bounds.
	 */
	public function isAlways(): bool {
		return null === $this->start && null === $this->end;
	}

	/**
	 * Whether the schedule permits serving at the given instant.
	 *
	 * @param DateTimeInterface $now Current instant (whole-second granularity).
	 */
	public function isActiveAt( DateTimeInterface $now ): bool {
		return ScheduleState::Active === $this->stateAt( $now );
	}

	/**
	 * State at the given instant.
	 *
	 * @param DateTimeInterface $now Current instant.
	 */
	public function stateAt( DateTimeInterface $now ): ScheduleState {
		$timestamp = $now->getTimestamp();
		if ( null !== $this->start && $timestamp < $this->start->getTimestamp() ) {
			return ScheduleState::Pending;
		}
		if ( null !== $this->end && $timestamp >= $this->end->getTimestamp() ) {
			return ScheduleState::Expired;
		}
		return ScheduleState::Active;
	}

	/**
	 * The next instant at which the state changes: the start while pending, the end while active and bounded.
	 * Null when the state can no longer change. Lets a later cache bound its lifetime.
	 *
	 * @param DateTimeInterface $now Current instant.
	 */
	public function nextTransitionAfter( DateTimeInterface $now ): ?DateTimeImmutable {
		return match ( $this->stateAt( $now ) ) {
			ScheduleState::Pending => $this->start,
			ScheduleState::Active  => $this->end,
			ScheduleState::Expired => null,
		};
	}

	/**
	 * Validates range and normalizes to a UTC, whole-second instant.
	 *
	 * @param DateTimeInterface $value Instant.
	 * @param string            $field Field code for errors.
	 * @throws InvalidEntityException When out of range.
	 */
	private static function normalize( DateTimeInterface $value, string $field ): DateTimeImmutable {
		$timestamp = $value->getTimestamp();
		if ( $timestamp < self::MIN_TIMESTAMP || $timestamp > self::MAX_TIMESTAMP ) {
			throw new InvalidEntityException( $field, InvalidEntityException::REASON_OUT_OF_RANGE );
		}
		return ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( new DateTimeZone( 'UTC' ) );
	}
}
