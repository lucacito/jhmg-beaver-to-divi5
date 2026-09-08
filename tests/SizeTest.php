<?php

use PHPUnit\Framework\TestCase;
use BeaverDivi5Converter\Helpers\Size;

final class SizeTest extends TestCase {

    public function test_number_plus_unit(): void {
        $this->assertSame( '20%', Size::withUnit( '20', '%' ) );
        $this->assertSame( '15px', Size::withUnit( 15, null ) );
        $this->assertSame( '1.5em', Size::withUnit( '1.5', 'em' ) );
        $this->assertSame( '70vh', Size::withUnit( '70', 'vh' ) );
    }

    public function test_empty_unit_means_unitless(): void {
        $this->assertSame( '1.4', Size::withUnit( '1.4', '' ) );
    }

    public function test_values_that_already_carry_a_unit_are_kept(): void {
        $this->assertSame( '20px', Size::withUnit( '20px', '%' ) );
        $this->assertSame( 'auto', Size::withUnit( 'auto', 'px' ) );
        $this->assertSame( 'calc(100% - 20px)', Size::withUnit( 'calc(100% - 20px)', 'px' ) );
    }

    public function test_empty_and_garbage_are_empty_strings(): void {
        $this->assertSame( '', Size::withUnit( '', 'px' ) );
        $this->assertSame( '', Size::withUnit( null, 'px' ) );
        $this->assertSame( '', Size::withUnit( 'big', 'px' ) );
        $this->assertSame( '', Size::withUnit( [ 'x' ], 'px' ) );
    }

    public function test_from_settings_reads_the_unit_sibling_per_breakpoint(): void {
        $settings = [
            'padding_top'           => '6',
            'padding_unit'          => '%',
            'padding_top_medium'    => '30',
            'padding_medium_unit'   => 'px',
            'min_height'            => '70',
            'min_height_unit'       => 'vh',
        ];

        $this->assertSame( '6%', Size::fromSettings( $settings, 'padding_top', 'padding' ) );
        $this->assertSame( '30px', Size::fromSettings( $settings, 'padding_top', 'padding', '_medium' ) );
        $this->assertSame( '', Size::fromSettings( $settings, 'padding_top', 'padding', '_responsive' ) );
        $this->assertSame( '70vh', Size::fromSettings( $settings, 'min_height', 'min_height' ) );
    }

    public function test_from_length_reads_typography_style_values(): void {
        $this->assertSame( '1.1em', Size::fromLength( [ 'length' => '1.1', 'unit' => 'em' ] ) );
        $this->assertSame( '', Size::fromLength( [ 'length' => '', 'unit' => 'px' ] ) );
        $this->assertSame( '12px', Size::fromLength( [ 'length' => '12' ] ) );
        $this->assertSame( '', Size::fromLength( 'nope' ) );
    }

    public function test_number_extracts_the_magnitude(): void {
        $this->assertSame( 20.0, Size::number( '20px' ) );
        $this->assertSame( -1.5, Size::number( '-1.5em' ) );
        $this->assertNull( Size::number( '' ) );
    }
}
