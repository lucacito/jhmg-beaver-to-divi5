<?php

namespace BeaverDivi5Converter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {
    private static ?Plugin $instance = null;

    public static function instance(): Plugin {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function init(): void {
        add_action( 'plugins_loaded', [ $this, 'register_hooks' ] );
    }

    public function register_hooks(): void {
        if ( is_admin() ) {
            // Deliberately not gated on DiviRequirement here: this hook runs at
            // plugins_loaded, before the theme is loaded, so Divi's version is
            // not readable yet. Each screen and handler consults the requirement
            // when it actually runs, on an admin hook, by which time it is.
            add_action( 'admin_notices', [ \BeaverDivi5Converter\Helpers\DiviRequirement::class, 'render_notice' ] );

            foreach ( self::admin_components() as $class ) {
                if ( class_exists( $class ) ) {
                    ( new $class() )->init();
                }
            }
        }

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_styles' ] );

        // Extension point for the Pro add-on (and future companions).
        do_action( 'bdc_loaded', $this );
    }

    /**
     * Admin components booted in order. Listed by name so the bootstrap test can
     * assert the set without instantiating WordPress.
     *
     * @return string[]
     */
    public static function admin_components(): array {
        return [
            \BeaverDivi5Converter\Admin\AdminPage::class,
            \BeaverDivi5Converter\Admin\DirectConversionPage::class,
            \BeaverDivi5Converter\Admin\ReviewPrompt::class,
            \BeaverDivi5Converter\History\ImportRollback::class,
            \BeaverDivi5Converter\Telemetry\CoverageTelemetry::class,
        ];
    }

    public function enqueue_frontend_styles(): void {
        if ( ! is_singular() ) {
            return;
        }
        $post_id = get_the_ID();
        if ( ! $post_id || get_post_meta( $post_id, '_et_pb_use_builder', true ) !== 'on' ) {
            return;
        }
        wp_enqueue_style(
            'bdc-frontend',
            BDC_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            BDC_PLUGIN_VERSION
        );
    }
}
