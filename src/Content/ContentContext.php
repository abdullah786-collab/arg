<?php
/**
 * Whether the content engine may run for this output.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Pure flags describing the output being produced. Insertion is allowed only for the main query's main content,
 * never for feeds, REST responses, admin screens, embeds or excerpts.
 */
final class ContentContext {

	/**
	 * Constructor.
	 *
	 * @param bool $main    The main query's main content (in the loop).
	 * @param bool $feed    A feed is being produced.
	 * @param bool $rest    A REST response is being produced.
	 * @param bool $admin   An admin screen is being produced.
	 * @param bool $embed   An embed is being produced.
	 * @param bool $excerpt An excerpt is being produced.
	 */
	public function __construct(
		public readonly bool $main = true,
		public readonly bool $feed = false,
		public readonly bool $rest = false,
		public readonly bool $admin = false,
		public readonly bool $embed = false,
		public readonly bool $excerpt = false
	) {
	}

	/**
	 * Whether insertion is allowed.
	 */
	public function allowsInsertion(): bool {
		return $this->main && ! $this->feed && ! $this->rest && ! $this->admin && ! $this->embed && ! $this->excerpt;
	}

	/**
	 * Whether shortcodes and blocks may render here. True on any normal front-end output (widgets included);
	 * false only for feeds, REST responses, admin screens, embeds and excerpts.
	 *
	 * Shortcodes and blocks may render in widgets and any normal front-end output.
	 */
	public function allowsEmbeddedSurface(): bool {
		return ! $this->feed && ! $this->rest && ! $this->admin && ! $this->embed && ! $this->excerpt;
	}
}
