<?php
/**
 * Registers the analytics services and hooks.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Provider;

use RevGuardPro\Analytics\CountingPolicy;
use RevGuardPro\Analytics\ImpressionCollector;
use RevGuardPro\Analytics\ReportBuilder;
use RevGuardPro\Analytics\StatsReader;
use RevGuardPro\Analytics\StatsRetention;
use RevGuardPro\Analytics\StatsStoreInterface;
use RevGuardPro\Analytics\WpdbStatsStore;
use RevGuardPro\Container;
use RevGuardPro\Contracts\ServiceProviderInterface;
use RevGuardPro\Domain\Repository\AdRepositoryInterface;
use RevGuardPro\Rendering\RenderState;
use RevGuardPro\Security\Capabilities;
use RevGuardPro\Settings\SettingsRepository;
use RevGuardPro\Support\ClockInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Services are lazy. At boot only three cheap registrations happen: the render observer, the purge handler and the
 * `init` check that schedules the purge. Nothing is queried, and no front-end asset is added.
 */
final class AnalyticsProvider implements ServiceProviderInterface {

	public const SERVICE_STORE     = 'stats_store';
	public const SERVICE_COLLECTOR = 'impression_collector';
	public const SERVICE_RETENTION = 'stats_retention';
	public const SERVICE_READER    = 'stats_reader';
	public const SERVICE_REPORTS   = 'report_builder';

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Service container.
	 */
	public function register( Container $container ): void {
		$container->set(
			self::SERVICE_STORE,
			static function (): StatsStoreInterface {
				global $wpdb;
				return new WpdbStatsStore( $wpdb );
			}
		);
		$container->set(
			self::SERVICE_COLLECTOR,
			static function () use ( $container ): ImpressionCollector {
				$store    = $container->get( self::SERVICE_STORE );
				$clock    = $container->get( PersistenceProvider::SERVICE_CLOCK );
				$settings = $container->get( LifecycleProvider::SERVICE_SETTINGS );
				assert( $store instanceof StatsStoreInterface && $clock instanceof ClockInterface && $settings instanceof SettingsRepository );
				return new ImpressionCollector(
					$store,
					$clock,
					static fn(): bool => self::requestCounts( $settings ),
					static function () use ( $container ): void {
						add_action(
							'shutdown',
							static function () use ( $container ): void {
								$collector = $container->get( self::SERVICE_COLLECTOR );
								assert( $collector instanceof ImpressionCollector );
								$collector->flush();
							}
						);
					},
					static fn(): int => get_current_blog_id(),
					static function ( int $site, \Closure $work ): void {
						$switch = is_multisite() && get_current_blog_id() !== $site;
						if ( $switch ) {
							switch_to_blog( $site );
						}
						try {
							$work();
						} finally {
							if ( $switch ) {
								restore_current_blog();
							}
						}
					}
				);
			}
		);
		$container->set(
			self::SERVICE_RETENTION,
			static function () use ( $container ): StatsRetention {
				$store    = $container->get( self::SERVICE_STORE );
				$clock    = $container->get( PersistenceProvider::SERVICE_CLOCK );
				$settings = $container->get( LifecycleProvider::SERVICE_SETTINGS );
				assert( $store instanceof StatsStoreInterface && $clock instanceof ClockInterface && $settings instanceof SettingsRepository );
				return new StatsRetention( $store, $clock, static fn(): int => $settings->int( 'analytics_retention_days' ) );
			}
		);
		$container->set(
			self::SERVICE_READER,
			static function () use ( $container ): StatsReader {
				$store = $container->get( self::SERVICE_STORE );
				assert( $store instanceof StatsStoreInterface );
				return new StatsReader( $store );
			}
		);
		$container->set(
			self::SERVICE_REPORTS,
			static function () use ( $container ): ReportBuilder {
				$reader = $container->get( self::SERVICE_READER );
				$ads    = $container->get( PersistenceProvider::SERVICE_ADS );
				assert( $reader instanceof StatsReader && $ads instanceof AdRepositoryInterface );
				return new ReportBuilder( $reader, $ads );
			}
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Service container.
	 */
	public function boot( Container $container ): void {
		$state = $container->get( RenderingProvider::SERVICE_STATE );
		assert( $state instanceof RenderState );
		$state->onRendered(
			static function ( int $scope, int $ad_id ) use ( $container ): void {
				$collector = $container->get( self::SERVICE_COLLECTOR );
				assert( $collector instanceof ImpressionCollector );
				$collector->record( $scope, $ad_id );
			}
		);

		add_action(
			StatsRetention::CRON_HOOK,
			static function () use ( $container ): void {
				$retention = $container->get( self::SERVICE_RETENTION );
				assert( $retention instanceof StatsRetention );
				$retention->purge();
			}
		);
		add_action(
			'init',
			static function (): void {
				if ( ! wp_installing() && ! wp_next_scheduled( StatsRetention::CRON_HOOK ) ) {
					wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', StatsRetention::CRON_HOOK );
				}
			}
		);
	}

	/**
	 * Whether the current request counts, from WordPress facts. Nothing here is stored.
	 *
	 * @param SettingsRepository $settings Plugin settings.
	 */
	private static function requestCounts( SettingsRepository $settings ): bool {
		if ( ! $settings->bool( 'analytics_enabled' ) ) {
			return false;
		}
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- Values are only matched against fixed patterns and never stored or output.
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) ) : '';
		$agent  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput
		return CountingPolicy::allows( $method, $agent, is_preview(), current_user_can( Capabilities::MANAGE_ADS ) );
	}
}
