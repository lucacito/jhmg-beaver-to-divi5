<?php

use PHPUnit\Framework\TestCase;
use BeaverDivi5Converter\StyleMapper\GlobalSettingsResolver;

final class GlobalSettingsResolverTest extends TestCase {

    protected function setUp(): void {
        bbdc_test_reset_hooks();
    }

    public function test_defaults_match_beaver_builder_when_nothing_is_stored(): void {
        $this->assertSame( '1100px', GlobalSettingsResolver::rowWidth() );
        $this->assertSame( 'fixed', GlobalSettingsResolver::rowWidthDefault() );
        $this->assertSame( 'fixed', GlobalSettingsResolver::rowContentWidthDefault() );
    }

    public function test_the_stored_option_wins(): void {
        update_option( GlobalSettingsResolver::OPTION, (object) [ 'row_width' => '1280', 'row_width_unit' => 'px', 'row_width_default' => 'full', 'row_padding' => '' ] );

        $this->assertSame( '1280px', GlobalSettingsResolver::rowWidth() );
        $this->assertSame( 'full', GlobalSettingsResolver::rowWidthDefault() );
        $this->assertSame( '20', GlobalSettingsResolver::all()['row_padding'], 'an empty stored value falls back to the default' );
    }

    public function test_the_filter_overrides_everything(): void {
        update_option( GlobalSettingsResolver::OPTION, [ 'row_width' => '1280' ] );
        add_filter( GlobalSettingsResolver::FILTER, fn( array $s ) => array_merge( $s, [ 'row_width' => '960', 'row_width_unit' => 'px' ] ) );

        $this->assertSame( '960px', GlobalSettingsResolver::rowWidth() );
    }
}
