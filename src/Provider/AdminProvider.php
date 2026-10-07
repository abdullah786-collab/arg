<?php
/**
 * Registers the admin screens.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Provider;

use RevGuardPro\Admin\Gateway;
use RevGuardPro\Admin\Menu;
use RevGuardPro\Admin\Screen\AdsScreen;
use RevGuardPro\Admin\Screen\AnalyticsScreen;
use RevGuardPro\Admin\Screen\DashboardScreen;
use RevGuardPro\Admin\Screen\GroupsScreen;
use RevGuardPro\Admin\Screen\PlacementsScreen;
use RevGuardPro\Admin\Screen\ScreenContext;
use RevGuardPro\Admin\Screen\ScreenInterface;
use RevGuardPro\Admin\Screen\SettingsScreen;
use RevGuardPro\Admin\ScreenId;
use RevGuardPro\Admin\Support\Urls;
use RevGuardPro\Admin\View\ViewRenderer;
use RevGuardPro\Analytics\ReportBuilder;
use RevGuardPro\Analytics\StatsReader;
use RevGuardPro\Container;
use RevGuardPro\Contracts\ServiceProviderInterface;
use RevGuardPro\Domain\Repository\AdGroupRepositoryInterface;
use RevGuardPro\Domain\Repository\AdRepositoryInterface;
use RevGuardPro\Domain\Repository\PlacementRepositoryInterface;
use RevGuardPro\Guardrails\GuardrailService;
use RevGuardPro\Security\AdminRequestGuard;
use RevGuardPro\Settings\SettingsRepository;
use RevGuardPro\Support\ClockInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Admin only. On a visitor request this provider adds nothing: `boot()` returns before registering any hook. In the
 * admin it adds two hooks (`admin_menu` and `admin_enqueue_scripts`), no AJAX action, no REST route, no front-end asset.
 */
final class AdminProvider implements ServiceProviderInterface {

	public const SERVICE_CONTEXT = 'admin_context';
	public const SERVICE_MENU    = 'admin_menu_service';

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Service container.
	 */
	public function register( Container $container ): void {
		$container->set(
			self::SERVICE_CONTEXT,
			static function () use ( $container ): ScreenContext {
				$ads        = $container->get( PersistenceProvider::SERVICE_ADS );
				$groups     = $container->get( PersistenceProvider::SERVICE_GROUPS );
				$placements = $container->get( PersistenceProvider::SERVICE_PLACEMENTS );
				$clock      = $container->get( PersistenceProvider::SERVICE_CLOCK );
				$settings   = $container->get( LifecycleProvider::SERVICE_SETTINGS );
				$guardrails = $container->get( GuardrailsProvider::SERVICE_GUARDRAILS );
				$reader     = $container->get( AnalyticsProvider::SERVICE_READER );
				$reports    = $container->get( AnalyticsProvider::SERVICE_REPORTS );
				assert( $ads instanceof AdRepositoryInterface && $groups instanceof AdGroupRepositoryInterface && $placements instanceof PlacementRepositoryInterface );
				assert( $clock instanceof ClockInterface && $settings instanceof SettingsRepository );
				assert( $guardrails instanceof GuardrailService && $reader instanceof StatsReader && $reports instanceof ReportBuilder );
				return new ScreenContext(
					AdminRequestGuard::forWordPress(),
					static fn( string $capability ): bool => current_user_can( $capability ),
					static function (): void {
						wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'revguard-pro' ), '', array( 'response' => 403 ) );
					},
					new Urls( admin_url( 'admin.php' ) ),
					new ViewRenderer( plugin_dir_path( REVGUARD_PRO_FILE ) . 'views/admin' ),
					Gateway::forRepository( $ads ),
					Gateway::forRepository( $groups ),
					Gateway::forRepository( $placements ),
					$settings,
					static fn(): \DateTimeZone => wp_timezone(),
					$clock,
					$guardrails,
					$reader,
					$reports
				);
			}
		);
		$container->set(
			self::SERVICE_MENU,
			static function () use ( $container ): Menu {
				return new Menu(
					static function ( ScreenId $id ) use ( $container ): ScreenInterface {
						$ctx = $container->get( self::SERVICE_CONTEXT );
						assert( $ctx instanceof ScreenContext );
						return match ( $id ) {
							ScreenId::Dashboard  => new DashboardScreen( $ctx ),
							ScreenId::Ads        => new AdsScreen( $ctx ),
							ScreenId::Groups     => new GroupsScreen( $ctx ),
							ScreenId::Placements => new PlacementsScreen( $ctx ),
							ScreenId::Analytics  => new AnalyticsScreen( $ctx ),
							ScreenId::Settings   => new SettingsScreen( $ctx ),
						};
					},
					static fn( string $capability ): bool => current_user_can( $capability )
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
		if ( ! is_admin() ) {
			return;
		}
		add_action(
			'admin_menu',
			static function () use ( $container ): void {
				$menu = $container->get( self::SERVICE_MENU );
				assert( $menu instanceof Menu );
				$menu->register();
			}
		);
		add_action(
			'admin_enqueue_scripts',
			static function ( string $suffix ) use ( $container ): void {
				$menu = $container->get( self::SERVICE_MENU );
				assert( $menu instanceof Menu );
				if ( $menu->owns( $suffix ) ) {
					wp_enqueue_style( 'revguard-pro-admin', plugin_dir_url( REVGUARD_PRO_FILE ) . 'assets/admin.css', array(), REVGUARD_PRO_VERSION );
				}
			}
		);
	}
}
