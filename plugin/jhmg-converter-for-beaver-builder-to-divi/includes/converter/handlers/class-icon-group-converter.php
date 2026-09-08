<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;
use BeaverDivi5Converter\Converter\ConverterEngine;
use BeaverDivi5Converter\Helpers\Size;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Beaver Builder Pro Icon Group → a nested divi/row whose single column is a
 * flex row holding one divi/icon per icon (the icons sit inline, as they do
 * in Beaver Builder). Each icon inherits the group's styling.
 */
class IconGroupConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bdc_icons_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $shared = $settings;
        unset( $shared['icons'], $shared['type'], $shared['spacing'], $shared['spacing_unit'], $shared['align'] );
        $shared['type'] = 'icon';

        $icons = [];
        foreach ( array_values( is_array( $settings['icons'] ?? null ) ? $settings['icons'] : [] ) as $index => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $item_settings = array_merge( $shared, array_filter( $item, static fn( $v ) => $v !== '' && $v !== null ) );
            $child         = [ 'id' => $id . '-' . ( $index + 1 ), 'type' => 'module', 'settings' => $item_settings, 'children' => [] ];
            foreach ( ConverterEngine::asList( ( new IconConverter( $this->engine ) )->convert( $child ) ) as $block ) {
                $icons[] = $block;
            }
        }

        if ( empty( $icons ) ) {
            $this->engine->logWarning( "Icon group {$id} has no icons." );
            return [];
        }
        $this->logUnmappedSettings( $id, [ 'spacing' => $settings['spacing'] ?? '' ], [ 'spacing' ] );

        if ( count( $icons ) === 1 ) {
            return $icons;
        }

        $align   = $this->text( $settings, 'align' );
        $justify = [ 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' ][ $align ] ?? 'flex-start';
        $gap     = Size::fromSettings( $settings, 'spacing', 'spacing' ) ?: '10px';

        $column = $this->block( $id . '-col', 'divi/column', [
            'module' => [
                'advanced'   => [ 'type' => [ 'desktop' => [ 'value' => '4_4' ] ] ],
                'decoration' => [ 'layout' => [ 'desktop' => [ 'value' => [ 'display' => 'flex', 'flexDirection' => 'row', 'flexWrap' => 'wrap', 'alignItems' => 'center', 'justifyContent' => $justify, 'columnGap' => $gap, 'rowGap' => $gap ] ] ] ],
            ],
        ], $icons );

        $this->engine->logConverted( 'row' );
        $this->engine->logConverted( 'column' );

        return $this->block( $id, 'divi/row', $this->deepMergeSettings( self::ROW_RESET, [ 'module' => [ 'advanced' => [ 'columnStructure' => [ 'desktop' => [ 'value' => '4_4' ] ] ] ] ] ), [ $column ] );
    }
}
