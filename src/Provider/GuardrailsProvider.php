<?php
/**
 * Registers the guardrails service.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Provider;

use RevGuardPro\Container;
use RevGuardPro\Contracts\ServiceProviderInterface;
use RevGuardPro\Domain\Repository\AdGroupRepositoryInterface;
use RevGuardPro\Domain\Repository\AdRepositoryInterface;
use RevGuardPro\Domain\Repository\PlacementRepositoryInterface;
use RevGuardPro\Guardrails\EntityChecks;
use RevGuardPro\Guardrails\GuardrailAnalyzer;
use RevGuardPro\Guardrails\GuardrailService;
use RevGuardPro\Guardrails\PlacementChecks;
use RevGuardPro\Rendering\WpKsesHtmlSanitizer;
use RevGuardPro\Settings\SettingsRepository;
use RevGuardPro\Support\ClockInterface;

defined( 'ABSPATH' ) || exit;

/**
 * One lazy service. Nothing is hooked: guardrails never run on a visitor request and are only called by whoever
 * exposes them (M9), after a capability check.
 */
final class GuardrailsProvider implements ServiceProviderInterface {

	public const SERVICE_GUARDRAILS = 'guardrails';

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Service container.
	 */
	public function register( Container $container ): void {
		$container->set(
			self::SERVICE_GUARDRAILS,
			static function () use ( $container ): GuardrailService {
				$ads      = $container->get( PersistenceProvider::SERVICE_ADS );
				$groups   = $container->get( PersistenceProvider::SERVICE_GROUPS );
				$places   = $container->get( PersistenceProvider::SERVICE_PLACEMENTS );
				$clock    = $container->get( PersistenceProvider::SERVICE_CLOCK );
				$settings = $container->get( LifecycleProvider::SERVICE_SETTINGS );
				assert( $ads instanceof AdRepositoryInterface && $groups instanceof AdGroupRepositoryInterface && $places instanceof PlacementRepositoryInterface );
				assert( $clock instanceof ClockInterface && $settings instanceof SettingsRepository );
				return new GuardrailService(
					$ads,
					$groups,
					$places,
					new GuardrailAnalyzer( new EntityChecks( new WpKsesHtmlSanitizer() ), new PlacementChecks() ),
					$clock,
					static fn(): array => array(
						'allow_unfiltered' => $settings->bool( 'allow_unfiltered_html_creatives' ),
						'automatic'        => $settings->bool( 'automatic_insertion' ),
					),
					static function (): void {
						global $wpdb;
						$wpdb->last_error = '';
					},
					static function (): string {
						global $wpdb;
						return (string) $wpdb->last_error;
					}
				);
			}
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Service container.
	 */
	public function boot( Container $container ): void {
	}
}
