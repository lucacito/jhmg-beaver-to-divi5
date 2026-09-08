<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Beaver Builder Pro North Commerce module. It renders North Commerce's own
 * shortcodes; Divi has no North Commerce module and the shortcode names are not
 * documented, so a labelled placeholder names the layout (and product slug) so
 * the store element can be re-added with North Commerce's block or shortcode.
 * Registered approximate.
 */
class NorthCommerceConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bbdc_northcommerce_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style  = $this->mapStyle( 'generic', $node );
        $layout = strtolower( $this->firstText( $settings, [ 'layout' ] ) ) ?: 'none';
        $slug   = $this->firstText( $settings, [ 'product_slug', 'product', 'slug' ] );

        $label = ucwords( str_replace( '_', ' ', $layout ) ) . ( $slug !== '' ? ' (product: ' . $slug . ')' : '' );
        $html  = '<!-- beaver builder module: north-commerce ' . esc_html( $layout ) . ( $slug !== '' ? ' product=' . esc_html( $slug ) : '' ) . ' -->'
            . '<div class="bdc-unconverted-module" data-beaver-module="north-commerce">North Commerce ' . esc_html( $label ) . ' — add the North Commerce block or shortcode here.</div>';

        $block = $this->codeBlock( $id, $html );
        $block['settings'] = $this->deepMergeSettings( $block['settings'], [ 'module' => $style['divi_attrs']['module'] ?? [] ] );

        $this->engine->logNotCarriedOver( 'integration', $id, "North Commerce {$label}" );
        $this->engine->logWarning( "North Commerce {$id}: Divi has no North Commerce module; a labelled placeholder marks the '{$layout}' layout." );
        $this->engine->logConverted( 'code' );
        $this->logUnmappedSettings( $id, $settings, array_merge( $style['handled_keys'], [
            'layout', 'product_slug', 'product', 'slug', 'style', 'button_text_color', 'button_icon_color', 'button_bg_color',
            'button_text_hover_color', 'button_icon_hover_color', 'button_bg_hover_color', 'button_border', 'button_border_hover_color',
        ] ) );

        return $block;
    }
}
