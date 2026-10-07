<?php
/**
 * The outcome of parsing a form.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\Form;

defined( 'ABSPATH' ) || exit;

/**
 * Either a valid entity (or settings array) or per-field error codes, plus the sticky values to show again.
 */
final class FormResult {

	/**
	 * Constructor.
	 *
	 * @param mixed                $entity Validated entity or settings array, null when invalid.
	 * @param array<string,string> $errors Field to error code.
	 * @param array<string,mixed>  $values Sticky field values for re-display.
	 */
	public function __construct(
		public readonly mixed $entity,
		public readonly array $errors,
		public readonly array $values
	) {
	}

	/**
	 * Whether the input was valid.
	 */
	public function ok(): bool {
		return array() === $this->errors && null !== $this->entity;
	}
}
