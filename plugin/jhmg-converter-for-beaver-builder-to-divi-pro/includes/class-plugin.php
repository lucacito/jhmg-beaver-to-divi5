<?php

namespace BeaverDivi5Converter\Pro;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {
    private static ?Plugin $instance = null;

    public static function instance(): Plugin {
        return self::$instance ??= new self();
    }

    public function init(): void {
        // Priority 20: after the free plugin's own plugins_loaded hook (10).
        add_action( 'plugins_loaded', [ $this, 'register_hooks' ], 20 );
    }

    public function register_hooks(): void {
        if ( function_exists( 'load_plugin_textdomain' ) ) {
            load_plugin_textdomain( 'jhmg-converter-for-beaver-builder-to-divi-pro', false, dirname( plugin_basename( BDCP_PLUGIN_FILE ) ) . '/languages' );
        }

        if ( ! class_exists( \BeaverDivi5Converter\Plugin::class ) ) {
            add_action( 'admin_notices', [ $this, 'render_missing_free_notice' ] );
            return;
        }

        add_filter( 'bbdc_pro_active', '__return_true' );

        // Free converts one page per run; Pro converts as many as selected. A
        // quantity boundary, not a feature flag: the whole loop lives in free.
        add_filter( 'bbdc_direct_conversion_limit', static fn( $v ) => PHP_INT_MAX );

        add_filter( 'bbdc_theme_builder_exporter', static function ( $v ) {
            return $v ?? new Exporters\DiviThemeBuilderExporter( new \BeaverDivi5Converter\Exporters\DiviExporter() );
        } );

        $license = $this->license();
        add_filter( 'pre_set_site_transient_update_plugins', [ $license, 'inject_update' ] );

        if ( is_admin() ) {
            ( new Admin\ProPage( $license ) )->init();
            add_action( 'admin_init', static function () use ( $license ) { $license->refresh(); } );
            add_action( 'admin_notices', [ new Licensing\LicensePage( $license ), 'maybe_render_notice' ] );
        }
    }

    public function license(): Licensing\LicenseClient {
        return new Licensing\LicenseClient(
            BDCP_PRODUCT_SLUG,
            BDCP_PLUGIN_VERSION,
            BDCP_API_BASE,
            plugin_basename( BDCP_PLUGIN_FILE ),
            Admin\ProPage::MENU_SLUG,
            'https://divi5lab.com/plugins/beaver-builder-to-divi-5',
            'bdcp'
        );
    }

    public function render_missing_free_notice(): void {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__( 'JHMG Converter Pro requires the free "JHMG Converter For Beaver Builder to Divi 5" plugin. Please install and activate it.', 'jhmg-converter-for-beaver-builder-to-divi-pro' );
        echo '</p></div>';
    }
}
