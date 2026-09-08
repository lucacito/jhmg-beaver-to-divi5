<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;
use BeaverDivi5Converter\Helpers\Size;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Beaver Builder Pro Map → divi/code holding a Google Maps embed of the same
 * address. Divi's map module needs coordinates and an API key the Beaver
 * Builder module never stored.
 */
class MapConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bdc_map_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style   = $this->mapStyle( 'generic', $node );
        $address = trim( $this->text( $settings, 'address' ) );
        $height  = Size::fromSettings( $settings, 'height', 'height' ) ?: '400px';

        if ( $address !== '' ) {
            $html = '<iframe src="https://maps.google.com/maps?q=' . rawurlencode( $address ) . '&amp;output=embed" width="100%" height="' . esc_attr( $height ) . '" style="border:0;" allowfullscreen="" loading="lazy" title="' . esc_attr( $address ) . '"></iframe>';
        } else {
            $html = '<!-- beaver builder map module: no address set -->';
            $this->engine->logWarning( "Map {$id}: no address set." );
        }

        $this->engine->logConverted( 'code' );
        $this->logUnmappedSettings( $id, $settings, array_merge( [ 'address', 'height', 'height_unit', 'zoom', 'map_type' ], $style['handled_keys'] ) );

        return $this->block( $id, 'divi/code', $this->deepMergeSettings( $style['divi_attrs'], [ 'content' => [ 'innerContent' => [ 'desktop' => [ 'value' => $html ] ] ] ] ) );
    }
}
