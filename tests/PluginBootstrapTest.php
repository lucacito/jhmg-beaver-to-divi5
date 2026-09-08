<?php

use PHPUnit\Framework\TestCase;
use BeaverDivi5Converter\Plugin;

final class PluginBootstrapTest extends TestCase {

    public function test_constants_are_defined(): void {
        $this->assertTrue( defined( 'BDC_PLUGIN_DIR' ) );
        $this->assertSame( '1.0.0', BDC_PLUGIN_VERSION );
        $this->assertStringEndsWith( 'jhmg-converter-for-beaver-builder-to-divi/', BDC_PLUGIN_DIR );
    }

    public function test_plugin_is_a_singleton(): void {
        $this->assertSame( Plugin::instance(), Plugin::instance() );
    }

    public function test_autoloader_resolves_nested_namespaces_to_kebab_case_files(): void {
        $this->assertTrue( class_exists( \BeaverDivi5Converter\Helpers\DiviRequirement::class ) );
    }

    public function test_bdc_loaded_fires_after_hooks_are_registered(): void {
        bdc_test_reset_hooks();
        $fired = false;
        add_action( 'bdc_loaded', function () use ( &$fired ) { $fired = true; } );

        Plugin::instance()->register_hooks();

        $this->assertTrue( $fired );
    }
}
