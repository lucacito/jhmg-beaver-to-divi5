<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Beaver Builder Pro "BigCommerce Products" module → the `[bigcommerce_product]`
 * shortcode it renders (BigCommerce for WordPress), inside a divi/code module.
 * Divi has no BigCommerce module. Registered approximate (documentation-based).
 */
class BigCommerceProductsConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bbdc_bigcommerce_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style = $this->mapStyle( 'generic', $node );
        $yes   = fn( array $keys ): bool => in_array( strtolower( $this->firstText( $settings, $keys ) ), [ 'yes', 'true', '1', 'on' ], true );

        $atts = [];
        $per_page = $this->firstText( $settings, [ 'per_page', 'products_per_page', 'posts_per_page' ] );
        if ( is_numeric( $per_page ) ) {
            $atts[] = 'per_page="' . (int) $per_page . '"';
        }
        if ( $yes( [ 'pagination', 'use_pagination', 'paged' ] ) ) {
            $atts[] = 'paged="1"';
        }
        foreach ( [ 'featured', 'sale', 'recent' ] as $flag ) {
            if ( $yes( [ $flag, 'show_' . $flag, $flag . '_only', 'show_only_' . $flag ] ) ) {
                $atts[] = $flag . '="1"';
            }
        }

        $block = $this->codeBlock( $id, '[bigcommerce_product' . ( $atts !== [] ? ' ' . implode( ' ', $atts ) : '' ) . ']' );
        $block['settings'] = $this->deepMergeSettings( $block['settings'], [ 'module' => $style['divi_attrs']['module'] ?? [] ] );

        $this->engine->logWarning( "BigCommerce {$id}: kept as its shortcode; BigCommerce for WordPress must be active on the Divi site." );
        $this->engine->logConverted( 'code' );
        $this->logUnmappedSettings( $id, $settings, array_merge( $style['handled_keys'], [
            'per_page', 'products_per_page', 'posts_per_page', 'pagination', 'use_pagination', 'paged',
            'featured', 'show_featured', 'featured_only', 'show_only_featured', 'sale', 'show_sale', 'sale_only', 'show_only_sale',
            'recent', 'show_recent', 'recent_only', 'show_only_recent',
        ] ) );

        return $block;
    }
}
