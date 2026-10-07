<?php
/**
 * Severity of a guardrail finding.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Guardrails;

defined( 'ABSPATH' ) || exit;

/**
 * Warning sorts before info. There is deliberately no "ok" or "pass" level: an empty report is not a clean bill.
 */
enum Severity: string {

	case Warning = 'warning';
	case Info    = 'info';
}
