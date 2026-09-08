<?php

use PHPUnit\Framework\TestCase;
use BeaverDivi5Converter\Converter\ConverterEngine;

/**
 * Every module in Beaver Builder's own module reference
 * (docs.wpbeaverbuilder.com/beaver-builder/layouts/modules/, 2.11 list, checked
 * 2026-09-08) has a registered handler. The map records the module slug Beaver
 * Builder saves in `settings.type` for each documented name.
 */
final class DocumentedModulesTest extends TestCase {

    /** Documented name => saved module slug(s). */
    const DOCUMENTED = [
        'Accordion'            => [ 'accordion' ],
        'ACF Blocks'           => [ 'acf-block' ],
        'Audio'                => [ 'audio' ],
        'BigCommerce Products' => [ 'bigcommerce-products' ],
        'Box'                  => [ 'box' ],
        'Button'               => [ 'button' ],
        'Button Group'         => [ 'button-group' ],
        'Callout'              => [ 'callout' ],
        'Contact Form'         => [ 'contact-form' ],
        'Content Slider'       => [ 'content-slider' ],
        'Countdown'            => [ 'countdown' ],
        'Call to Action'       => [ 'cta' ],
        'Gallery'              => [ 'gallery' ],
        'Heading'              => [ 'heading' ],
        'HTML'                 => [ 'html' ],
        'Icon'                 => [ 'icon' ],
        'Icon Group'           => [ 'icon-group' ],
        'List'                 => [ 'list' ],
        'Login Form'           => [ 'login-form' ],
        'Loop'                 => [ 'loop' ],
        'Map'                  => [ 'map' ],
        'Menu'                 => [ 'menu' ],
        'North Commerce'       => [ 'north-commerce' ],
        'Number Counter'       => [ 'numbers' ],
        'Photo'                => [ 'photo' ],
        'Popup'                => [ 'popup' ],
        'Posts Carousel'       => [ 'post-carousel' ],
        'Posts'                => [ 'post-grid' ],
        'Posts Slider'         => [ 'post-slider' ],
        'Pricing Table'        => [ 'pricing-table' ],
        'Rich Text Editor'     => [ 'rich-text' ],
        'Search'               => [ 'search' ],
        'Separator'            => [ 'separator' ],
        'Sidebar'              => [ 'sidebar' ],
        'Slideshow'            => [ 'slideshow' ],
        'Social Buttons'       => [ 'social-buttons' ],
        'Star Rating'          => [ 'star-rating' ],
        'Subscribe Form'       => [ 'subscribe-form' ],
        'Tabs'                 => [ 'tabs' ],
        'Testimonials'         => [ 'testimonials' ],
        'Video'                => [ 'video' ],
        'Widgets'              => [ 'widget' ],
        'WooCommerce'          => [ 'woocommerce' ],
        'WordPress Patterns'   => [ 'reusable-block' ],
    ];

    public function test_every_documented_module_has_a_handler(): void {
        $known   = ( new ConverterEngine() )->registry()->knownModuleSlugs();
        $missing = [];
        foreach ( self::DOCUMENTED as $name => $slugs ) {
            foreach ( $slugs as $slug ) {
                if ( ! in_array( $slug, $known, true ) ) {
                    $missing[] = "{$name} ({$slug})";
                }
            }
        }
        $this->assertSame( [], $missing, 'documented modules without a handler' );
        $this->assertCount( 44, self::DOCUMENTED );
    }

    public function test_only_acf_blocks_are_left_to_a_placeholder(): void {
        $engine      = new ConverterEngine();
        $registry    = $engine->registry();
        $placeholder = [];
        foreach ( $registry->knownModuleSlugs() as $slug ) {
            if ( $registry->converterName( [ 'type' => 'module', 'settings' => [ 'type' => $slug ] ] ) === 'closure' ) {
                $placeholder[] = $slug;
            }
        }
        $this->assertSame( [ 'acf-block' ], $placeholder );
    }
}
