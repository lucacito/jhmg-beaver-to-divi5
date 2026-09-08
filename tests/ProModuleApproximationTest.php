<?php

use PHPUnit\Framework\TestCase;
use BeaverDivi5Converter\Converter\ConverterEngine;

/**
 * Handlers for Beaver Builder Pro modules were written from Beaver Builder's
 * documentation, not its source. They must survive any settings shape and be
 * reported as approximate rather than counted as clean conversions.
 */
final class ProModuleApproximationTest extends TestCase {

    protected function setUp(): void {
        bbdc_test_reset_hooks();
    }

    private function convertModule( array $settings ): array {
        return ( new ConverterEngine() )->convert( [ 'nodes' => [
            'row1' => [ 'node' => 'row1', 'type' => 'row', 'parent' => null, 'position' => 0, 'settings' => [] ],
            'grp1' => [ 'node' => 'grp1', 'type' => 'column-group', 'parent' => 'row1', 'position' => 0, 'settings' => '' ],
            'col1' => [ 'node' => 'col1', 'type' => 'column', 'parent' => 'grp1', 'position' => 0, 'settings' => [ 'size' => 100 ] ],
            'mod1' => [ 'node' => 'mod1', 'type' => 'module', 'parent' => 'col1', 'position' => 0, 'settings' => $settings ],
        ] ] );
    }

    public static function proSlugs(): array {
        $engine = new ConverterEngine();
        $cases  = [];
        foreach ( $engine->registry()->approximateModuleSlugs() as $slug ) {
            $cases[ $slug ] = [ $slug ];
        }
        return $cases;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider( 'proSlugs' )]
    public function test_every_pro_handler_survives_empty_and_hostile_settings( string $slug ): void {
        $empty = $this->convertModule( [ 'type' => $slug ] );
        $this->assertNotEmpty( $empty['divi']['elements'], "{$slug}: empty settings produced no output" );

        $hostile = $this->convertModule( [
            'type' => $slug, 'items' => 'not-an-array', 'slides' => [ 'x', 5, null ], 'testimonials' => [ [ 'testimonial' => [ 'nested' ] ] ],
            'pricing_columns' => [ [ 'features' => [ 'a', [ 'b' ] ] ] ], 'photos' => 'abc', 'icons' => [ 'nope' ], 'color' => [ 'r' => 1 ],
            'address' => 12, 'date' => [ 'y' => 2026 ], 'height' => [ 'n' ], 'number' => [ '1' ], 'link' => [ 'u' ],
        ] );
        $this->assertNotEmpty( $hostile['divi']['elements'], "{$slug}: hostile settings produced no output" );
    }

    public function test_pro_conversions_are_reported_as_approximate_not_clean(): void {
        $result = $this->convertModule( [ 'type' => 'accordion', 'items' => [ [ 'label' => 'Q', 'content' => 'A' ] ] ] );

        $this->assertSame( [ 'accordion' => 1 ], $result['report']['approximate'] );
        $this->assertArrayNotHasKey( 'accordion', $result['report']['converted'] );
        $this->assertSame( [ [ 'node_id' => 'mod1', 'module' => 'accordion', 'matched_to' => 'AccordionConverter' ], ], $result['report']['approximate_matches'] );
        $this->assertSame( 75, $result['report']['quality']['module_coverage'], 'approximate conversions sit in the denominator only' );
    }

    public function test_lite_modules_are_not_flagged_approximate(): void {
        $engine = new ConverterEngine();
        foreach ( [ 'heading', 'rich-text', 'photo', 'button', 'html', 'video', 'audio', 'sidebar', 'icon', 'callout', 'cta', 'numbers', 'star-rating', 'menu', 'box', 'button-group' ] as $slug ) {
            $this->assertNotContains( $slug, $engine->registry()->approximateModuleSlugs(), $slug );
            $this->assertContains( $slug, $engine->registry()->knownModuleSlugs(), $slug );
        }
    }
}
