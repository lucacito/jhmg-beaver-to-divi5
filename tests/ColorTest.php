<?php

use PHPUnit\Framework\TestCase;
use BeaverDivi5Converter\Helpers\Color;

final class ColorTest extends TestCase {

    protected function setUp(): void {
        bbdc_test_reset_hooks();
    }

    public function test_bare_hex_gains_a_hash(): void {
        $this->assertSame( '#64A6BD', Color::normalize( '64A6BD' ) );
        $this->assertSame( '#fff', Color::normalize( 'fff' ) );
        $this->assertSame( '#0c7489', Color::normalize( ' 0c7489 ' ) );
    }

    public function test_hashed_hex_and_rgba_pass_through(): void {
        $this->assertSame( '#102a43', Color::normalize( '#102a43' ) );
        $this->assertSame( 'rgba(16,42,67,0.8)', Color::normalize( 'rgba(16,42,67,0.8)' ) );
        $this->assertSame( 'transparent', Color::normalize( 'transparent' ) );
    }

    public function test_empty_and_garbage_are_null(): void {
        $this->assertNull( Color::normalize( '' ) );
        $this->assertNull( Color::normalize( null ) );
        $this->assertNull( Color::normalize( [ 'x' ] ) );
        $this->assertNull( Color::normalize( '12345' ) );
        $this->assertNull( Color::normalize( 'url(x)' ) );
    }

    public function test_global_references_resolve_through_the_filter(): void {
        add_filter( Color::GLOBALS_FILTER, fn() => [ 'brand' => '2b6cb0' ] );

        $this->assertSame( '#2b6cb0', Color::normalize( 'var(--fl-global-brand)' ) );
        $this->assertSame( '#2b6cb0', Color::normalize( 'fl-global-brand' ) );
    }

    public function test_unknown_global_references_are_null_not_invented(): void {
        $this->assertTrue( Color::isGlobalRef( 'var(--fl-global-accent)' ) );
        $this->assertNull( Color::normalize( 'var(--fl-global-accent)' ) );
        $this->assertSame( 'accent', Color::globalSlug( 'var(--fl-global-accent)' ) );
    }

    public function test_with_opacity_builds_rgba(): void {
        $this->assertSame( 'rgba(255,255,255,0.5)', Color::withOpacity( '#ffffff', 0.5 ) );
        $this->assertSame( 'rgba(0,0,0,1)', Color::withOpacity( '#000000', 1 ) );
        $this->assertSame( 'rgba(1,2,3,0.4)', Color::withOpacity( 'rgba(1,2,3,0.4)', 0.2 ), 'non-hex input is returned untouched' );
    }
}
