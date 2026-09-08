<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;
use BeaverDivi5Converter\Helpers\Size;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Beaver Builder Pro Separator → divi/divider. Field names from Beaver Builder's documentation. */
class SeparatorConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bdc_divider_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style = $this->mapStyle( 'generic', $node );
        $attrs = $style['divi_attrs'];

        $line = [ 'show' => 'on' ];
        $color = $this->color( $settings, 'color', $id );
        if ( $color !== null ) {
            $line['color'] = $color;
        }
        $line_style = $this->text( $settings, 'style' );
        $line['style'] = in_array( $line_style, [ 'solid', 'dashed', 'dotted', 'double' ], true ) ? $line_style : 'solid';
        $height = Size::fromSettings( $settings, 'height', 'height' );
        $line['weight'] = $height !== '' ? $height : '1px';
        $attrs['divider']['advanced']['line']['desktop']['value'] = $line;

        $width = Size::fromSettings( $settings, 'width', 'width', '', '%' );
        if ( $width !== '' ) {
            $attrs['module']['decoration']['sizing']['desktop']['value']['width'] = $width;
        }
        // Beaver Builder stores the alignment as `align`; `auto` is its centred default.
        $alignment = $this->text( $settings, 'align' ) ?: $this->text( $settings, 'alignment' );
        if ( $width !== '' && $width !== '100%' ) {
            $this->alignSizedModule( $attrs, $alignment === 'auto' || $alignment === '' ? 'center' : $alignment );
        }

        $this->engine->logConverted( 'divider' );
        $this->logUnmappedSettings( $id, $settings, array_merge( [ 'color', 'style', 'height', 'height_unit', 'width', 'width_unit', 'align', 'alignment' ], $style['handled_keys'] ) );

        return $this->block( $id, 'divi/divider', $attrs );
    }
}
