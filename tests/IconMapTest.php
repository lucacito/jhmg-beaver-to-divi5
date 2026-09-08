<?php

use PHPUnit\Framework\TestCase;
use BeaverDivi5Converter\Helpers\IconMap;

final class IconMapTest extends TestCase {

    public function test_solid_font_awesome_classes_map_to_the_same_glyph(): void {
        $result = IconMap::fromClass( 'fas fa-chevron-circle-right' );

        $this->assertTrue( $result['exact'] );
        $this->assertSame( [ 'unicode' => '&#xf138;', 'type' => 'fa', 'weight' => '900' ], $result['icon'] );
        $this->assertSame( 'chevron-circle-right', $result['name'] );
    }

    public function test_regular_and_brand_prefixes_pick_the_400_weight_when_divi_has_it(): void {
        $this->assertSame( '400', IconMap::fromClass( 'far fa-envelope' )['icon']['weight'] );
        $this->assertSame( '400', IconMap::fromClass( 'fab fa-facebook-f' )['icon']['weight'] );
        $this->assertSame( '900', IconMap::fromClass( 'fas fa-envelope' )['icon']['weight'] );
    }

    public function test_a_weight_divi_lacks_falls_back_to_one_it_has(): void {
        // "check" exists only as solid in Divi's list.
        $this->assertSame( '900', IconMap::fromClass( 'far fa-check' )['icon']['weight'] );
    }

    public function test_unknown_icons_fall_back_to_a_star_and_say_so(): void {
        $dashicon = IconMap::fromClass( 'dashicons dashicons-wordpress-alt' );
        $this->assertFalse( $dashicon['exact'] );
        $this->assertSame( IconMap::FALLBACK, $dashicon['icon'] );

        $missing = IconMap::fromClass( 'fas fa-not-a-real-icon' );
        $this->assertFalse( $missing['exact'] );
        $this->assertSame( 'not-a-real-icon', $missing['name'] );
    }

    public function test_is_font_awesome(): void {
        $this->assertTrue( IconMap::isFontAwesome( 'fas fa-check' ) );
        $this->assertFalse( IconMap::isFontAwesome( 'dashicons dashicons-star' ) );
        $this->assertFalse( IconMap::isFontAwesome( '' ) );
    }
}
