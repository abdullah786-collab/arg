<?php
/**
 * The revguard-pro/ad dynamic block.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Rendering;

use RevGuardPro\Content\Wp\WpContentContextFactory;
use RevGuardPro\Domain\Exception\CorruptRecordException;
use RevGuardPro\Domain\Exception\PersistenceException;
use RevGuardPro\Domain\Repository\AdGroupRepositoryInterface;
use RevGuardPro\Domain\Repository\AdRepositoryInterface;
use RevGuardPro\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the dynamic block and renders it through the shared SurfaceRenderer. There is no JavaScript
 * selection, rotation or targeting: the editor script only stores two attributes.
 */
final class AdBlock {

	public const NAME = 'revguard-pro/ad';

	/**
	 * Constructor.
	 *
	 * @param SurfaceRenderer            $surface   Shared surface renderer.
	 * @param WpContentContextFactory    $contexts  Output context factory.
	 * @param AdRepositoryInterface      $ads       Ads (editor list only).
	 * @param AdGroupRepositoryInterface $groups   Groups (editor list only).
	 * @param string                     $block_dir Directory containing block.json.
	 */
	public function __construct(
		private readonly SurfaceRenderer $surface,
		private readonly WpContentContextFactory $contexts,
		private readonly AdRepositoryInterface $ads,
		private readonly AdGroupRepositoryInterface $groups,
		private readonly string $block_dir
	) {
	}

	/**
	 * Registers the block type (call on `init`).
	 */
	public function register(): void {
		register_block_type( $this->block_dir, array( 'render_callback' => array( $this, 'render' ) ) );
	}

	/**
	 * Server-side render callback.
	 *
	 * @param mixed $attributes Block attributes.
	 * @param mixed $content    Inner content (unused).
	 * @param mixed $block      Block instance (unused).
	 */
	public function render( $attributes = array(), $content = '', $block = null ): string {
		unset( $content, $block );
		$parsed = BlockAttributes::fromArray( is_array( $attributes ) ? $attributes : array() );
		if ( null === $parsed ) {
			return '';
		}
		try {
			return $this->surface->render( $parsed->type, $parsed->id, (int) get_the_ID(), $this->contexts->build() );
		} catch ( \Throwable ) {
			return '';
		}
	}

	/**
	 * Gives the editor script its target lists (ids and names), only to users who manage ads.
	 */
	public function enqueueEditorAssets(): void {
		$data = null;
		if ( current_user_can( Capabilities::MANAGE_ADS ) ) {
			try {
				$data = EditorTargets::build( $this->ads->list( null, 200 ), $this->groups->list( null, 200 ) );
			} catch ( CorruptRecordException | PersistenceException ) {
				$data = EditorTargets::build( array(), array() );
			}
		}
		wp_add_inline_script(
			generate_block_asset_handle( self::NAME, 'editorScript' ),
			'window.revguardProTargets = ' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';',
			'before'
		);
	}
}
