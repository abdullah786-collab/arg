<?php
/**
 * One guardrail finding.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Guardrails;

defined( 'ABSPATH' ) || exit;

/**
 * Code, severity, subject and integer/enum-like parameters. It never carries creative HTML, names or option values.
 */
final class Finding {

	public const SUBJECT_SITE      = 'site';
	public const SUBJECT_AD        = 'ad';
	public const SUBJECT_GROUP     = 'group';
	public const SUBJECT_PLACEMENT = 'placement';

	/**
	 * Constructor.
	 *
	 * @param GuardrailCode            $code       What was found.
	 * @param Severity                 $severity   Info or warning.
	 * @param string                   $subject    One of the SUBJECT_ constants.
	 * @param int|null                 $subject_id Entity id, null for the site.
	 * @param array<string,int|string> $params   Small fixed-vocabulary parameters.
	 */
	public function __construct(
		public readonly GuardrailCode $code,
		public readonly Severity $severity,
		public readonly string $subject,
		public readonly ?int $subject_id = null,
		public readonly array $params = array()
	) {
	}
}
