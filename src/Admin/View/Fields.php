<?php
/**
 * Accessible, escaped form controls.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\View;

use RevGuardPro\Admin\Form\ErrorMessages;

defined( 'ABSPATH' ) || exit;

/**
 * Every control is printed with a real `<label for>`, hint and error text linked through `aria-describedby`, and
 * `aria-invalid` on error. Values are escaped here, once, so templates never echo a stored value themselves.
 */
final class Fields {

	private const ALLOWED_ATTRIBUTES = array( 'maxlength', 'min', 'max', 'step', 'rows', 'autocomplete', 'placeholder', 'required', 'disabled', 'inputmode', 'spellcheck' );

	/**
	 * Prints a single-line text input.
	 *
	 * @param string              $id    Field id (without prefix).
	 * @param string              $name  Field name.
	 * @param string              $label Visible label.
	 * @param string              $value Current value.
	 * @param string|null         $error Error code, or null.
	 * @param string              $hint  Help text.
	 * @param array<string,mixed> $attrs Extra attributes from the allow-list.
	 */
	public static function text( string $id, string $name, string $label, string $value, ?string $error = null, string $hint = '', array $attrs = array() ): void {
		self::open( $id, $label, $error, $attrs );
		printf(
			'<input type="text" id="%1$s" name="%2$s" value="%3$s"%4$s>',
			esc_attr( self::dom( $id ) ),
			esc_attr( $name ),
			esc_attr( $value ),
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by describe(), which escapes every part.
			self::describe( $id, $hint, $error, $attrs )
		);
		self::close( $id, $hint, $error );
	}

	/**
	 * Prints a number input.
	 *
	 * @param string              $id    Field id.
	 * @param string              $name  Field name.
	 * @param string              $label Visible label.
	 * @param string              $value Current value.
	 * @param string|null         $error Error code.
	 * @param string              $hint  Help text.
	 * @param array<string,mixed> $attrs Extra attributes (min, max, step...).
	 */
	public static function number( string $id, string $name, string $label, string $value, ?string $error = null, string $hint = '', array $attrs = array() ): void {
		self::open( $id, $label, $error, $attrs );
		printf(
			'<input type="number" id="%1$s" name="%2$s" value="%3$s"%4$s>',
			esc_attr( self::dom( $id ) ),
			esc_attr( $name ),
			esc_attr( $value ),
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by describe(), which escapes every part.
			self::describe( $id, $hint, $error, $attrs )
		);
		self::close( $id, $hint, $error );
	}

	/**
	 * Prints a date-and-time input (the browser's native control).
	 *
	 * @param string      $id    Field id.
	 * @param string      $name  Field name.
	 * @param string      $label Visible label.
	 * @param string      $value Current value (`YYYY-MM-DDTHH:MM`).
	 * @param string|null $error Error code.
	 * @param string      $hint  Help text.
	 */
	public static function datetime( string $id, string $name, string $label, string $value, ?string $error = null, string $hint = '' ): void {
		self::open( $id, $label, $error, array() );
		printf(
			'<input type="datetime-local" id="%1$s" name="%2$s" value="%3$s"%4$s>',
			esc_attr( self::dom( $id ) ),
			esc_attr( $name ),
			esc_attr( $value ),
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by describe(), which escapes every part.
			self::describe( $id, $hint, $error, array() )
		);
		self::close( $id, $hint, $error );
	}

	/**
	 * Prints a multi-line input. The value is escaped for a textarea, so stored ad code can never break out of it.
	 *
	 * @param string              $id    Field id.
	 * @param string              $name  Field name.
	 * @param string              $label Visible label.
	 * @param string              $value Current value.
	 * @param string|null         $error Error code.
	 * @param string              $hint  Help text.
	 * @param array<string,mixed> $attrs Extra attributes.
	 */
	public static function textarea( string $id, string $name, string $label, string $value, ?string $error = null, string $hint = '', array $attrs = array() ): void {
		self::open( $id, $label, $error, $attrs );
		printf(
			'<textarea id="%1$s" name="%2$s"%4$s>%3$s</textarea>',
			esc_attr( self::dom( $id ) ),
			esc_attr( $name ),
			esc_textarea( $value ),
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by describe(), which escapes every part.
			self::describe( $id, $hint, $error, $attrs )
		);
		self::close( $id, $hint, $error );
	}

	/**
	 * Prints a select.
	 *
	 * @param string                                    $id       Field id.
	 * @param string                                    $name     Field name.
	 * @param string                                    $label    Visible label.
	 * @param array<string,string|array<string,string>> $options  Value to label, or label-group to (value to label).
	 * @param string                                    $selected Selected value.
	 * @param string|null                               $error    Error code.
	 * @param string                                    $hint     Help text.
	 */
	public static function select( string $id, string $name, string $label, array $options, string $selected, ?string $error = null, string $hint = '' ): void {
		self::open( $id, $label, $error, array() );
		printf(
			'<select id="%1$s" name="%2$s"%3$s>',
			esc_attr( self::dom( $id ) ),
			esc_attr( $name ),
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by describe(), which escapes every part.
			self::describe( $id, $hint, $error, array() )
		);
		foreach ( $options as $value => $text ) {
			if ( is_array( $text ) ) {
				printf( '<optgroup label="%s">', esc_attr( (string) $value ) );
				foreach ( $text as $inner_value => $inner_text ) {
					self::option( (string) $inner_value, (string) $inner_text, $selected );
				}
				echo '</optgroup>';
				continue;
			}
			self::option( (string) $value, $text, $selected );
		}
		echo '</select>';
		self::close( $id, $hint, $error );
	}

	/**
	 * Prints a checkbox with its label to the right.
	 *
	 * @param string      $id       Field id.
	 * @param string      $name     Field name.
	 * @param string      $label    Visible label.
	 * @param bool        $checked  Whether it is checked.
	 * @param string|null $error    Error code.
	 * @param string      $hint     Help text.
	 * @param bool        $disabled Whether it is disabled.
	 */
	public static function checkbox( string $id, string $name, string $label, bool $checked, ?string $error = null, string $hint = '', bool $disabled = false ): void {
		printf( '<div class="rgp-field rgp-field--check%s">', null === $error ? '' : ' rgp-field--error' );
		printf(
			'<input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s%4$s%5$s>',
			esc_attr( self::dom( $id ) ),
			esc_attr( $name ),
			$checked ? ' checked' : '',
			$disabled ? ' disabled' : '',
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by describe(), which escapes every part.
			self::describe( $id, $hint, $error, array() )
		);
		printf( '<label for="%1$s">%2$s</label>', esc_attr( self::dom( $id ) ), esc_html( $label ) );
		self::close( $id, $hint, $error );
	}

	/**
	 * Prints the error summary shown above a form that failed validation.
	 *
	 * @param array<string,string>                   $errors Field to error code.
	 * @param array<string,array{0:string,1:string}> $fields Field to [dom id, label].
	 */
	public static function errorSummary( array $errors, array $fields ): void {
		if ( array() === $errors ) {
			return;
		}
		echo '<div class="notice notice-error rgp-error-summary" role="alert" tabindex="-1">';
		echo '<p><strong>' . esc_html__( 'Nothing was saved. Please fix the following:', 'revguard-pro' ) . '</strong></p><ul>';
		foreach ( $errors as $field => $code ) {
			$known = $fields[ $field ] ?? null;
			if ( null === $known ) {
				echo '<li>' . esc_html( ErrorMessages::text( $code ) ) . '</li>';
				continue;
			}
			printf( '<li><a href="#%1$s">%2$s</a>: %3$s</li>', esc_attr( self::dom( $known[0] ) ), esc_html( $known[1] ), esc_html( ErrorMessages::text( $code ) ) );
		}
		echo '</ul></div>';
	}

	/**
	 * Prints a notice resolved from the fixed vocabulary.
	 *
	 * @param array{type:string,message:string}|null $notice Notice or null.
	 */
	public static function notice( ?array $notice ): void {
		if ( null === $notice ) {
			return;
		}
		$type = in_array( $notice['type'], array( 'success', 'error', 'warning', 'info' ), true ) ? $notice['type'] : 'info';
		printf(
			'<div class="notice notice-%1$s is-dismissible rgp-notice" role="%2$s"><p>%3$s</p></div>',
			esc_attr( $type ),
			'error' === $type ? 'alert' : 'status',
			esc_html( $notice['message'] )
		);
	}

	/**
	 * The DOM id for a field id.
	 *
	 * @param string $id Field id.
	 */
	public static function dom( string $id ): string {
		return 'rgp-' . preg_replace( '/[^a-z0-9_-]/i', '-', $id );
	}

	/**
	 * Opens the field wrapper and prints the label.
	 *
	 * @param string              $id    Field id.
	 * @param string              $label Label.
	 * @param string|null         $error Error code.
	 * @param array<string,mixed> $attrs Attributes (used to mark required fields).
	 */
	private static function open( string $id, string $label, ?string $error, array $attrs ): void {
		printf( '<div class="rgp-field%s">', null === $error ? '' : ' rgp-field--error' );
		printf(
			'<label for="%1$s">%2$s%3$s</label>',
			esc_attr( self::dom( $id ) ),
			esc_html( $label ),
			isset( $attrs['required'] ) ? ' <span class="rgp-required" aria-hidden="true">*</span>' : ''
		);
	}

	/**
	 * Prints the hint and error text and closes the wrapper.
	 *
	 * @param string      $id    Field id.
	 * @param string      $hint  Hint.
	 * @param string|null $error Error code.
	 */
	private static function close( string $id, string $hint, ?string $error ): void {
		if ( '' !== $hint ) {
			printf( '<p class="description" id="%1$s">%2$s</p>', esc_attr( self::dom( $id ) . '-hint' ), esc_html( $hint ) );
		}
		if ( null !== $error ) {
			printf( '<p class="rgp-error" id="%1$s"><span class="screen-reader-text">%2$s </span>%3$s</p>', esc_attr( self::dom( $id ) . '-error' ), esc_html__( 'Error:', 'revguard-pro' ), esc_html( ErrorMessages::text( $error ) ) );
		}
		echo '</div>';
	}

	/**
	 * The `aria-describedby`, `aria-invalid` and extra attributes, each escaped.
	 *
	 * @param string              $id    Field id.
	 * @param string              $hint  Hint.
	 * @param string|null         $error Error code.
	 * @param array<string,mixed> $attrs Extra attributes.
	 */
	private static function describe( string $id, string $hint, ?string $error, array $attrs ): string {
		$parts = array();
		if ( '' !== $hint ) {
			$parts[] = self::dom( $id ) . '-hint';
		}
		if ( null !== $error ) {
			$parts[] = self::dom( $id ) . '-error';
		}
		$out  = array() === $parts ? '' : ' aria-describedby="' . esc_attr( implode( ' ', $parts ) ) . '"';
		$out .= null === $error ? '' : ' aria-invalid="true"';
		foreach ( $attrs as $key => $value ) {
			if ( ! in_array( $key, self::ALLOWED_ATTRIBUTES, true ) ) {
				continue;
			}
			$out .= true === $value ? ' ' . $key : ' ' . $key . '="' . esc_attr( (string) $value ) . '"';
		}
		return $out;
	}

	/**
	 * Prints one select option.
	 *
	 * @param string $value    Value.
	 * @param string $text     Label.
	 * @param string $selected Selected value.
	 */
	private static function option( string $value, string $text, string $selected ): void {
		printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $value ), $value === $selected ? ' selected' : '', esc_html( $text ) );
	}
}
