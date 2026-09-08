<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;
use BeaverDivi5Converter\Helpers\Size;
use BeaverDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Beaver Builder Box (container module) → divi/group with its children.
 * Flex layouts keep their direction and gap; grid and z-stack layouts are
 * approximated as a wrapping flex row and noted.
 */
class BoxConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bbdc_group_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style = $this->mapStyle( 'group', $node );
        $attrs = $style['divi_attrs'];

        $layout = $this->text( $settings, 'layout' ) ?: 'flex';
        $flex   = [];
        foreach ( StyleMapper::BREAKPOINTS as $suffix => $bp ) {
            $value = [];
            $mode  = $suffix === '' ? $layout : $this->text( $settings, 'layout' . $suffix );
            if ( $mode !== '' ) {
                $value['display'] = $mode === 'flex' || $mode === '' ? 'flex' : 'flex';
            }
            $direction = $this->text( $settings, 'flex_direction' . $suffix );
            if ( $direction !== '' ) {
                $value['flexDirection'] = $direction;
            }
            $gap = Size::fromSettings( $settings, 'gap', 'gap', $suffix );
            if ( $gap !== '' ) {
                $value['columnGap'] = $gap;
                $value['rowGap']    = $gap;
            }
            if ( ! empty( $value ) ) {
                $flex[ $bp ] = [ 'value' => $value ];
            }
        }
        if ( in_array( $layout, [ 'grid', 'z_stack' ], true ) ) {
            $flex['desktop']['value']['flexWrap'] = 'wrap';
            $this->engine->logWarning( "Box {$id}: the '{$layout}' layout is approximated as a wrapping flex row." );
        }
        if ( ! empty( $flex ) ) {
            $attrs = $this->deepMergeSettings( $attrs, [ 'module' => [ 'decoration' => [ 'layout' => $flex ] ] ] );
        }

        $children = $this->convertStructureChildren( $node['children'] ?? [] );

        $this->engine->logConverted( 'group' );
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [ 'layout', 'layout_medium', 'layout_responsive', 'flex_direction', 'flex_direction_medium', 'flex_direction_responsive', 'gap', 'gap_unit', 'gap_medium', 'gap_responsive', 'grid_tracks', 'grid_auto_flow', 'place_content', 'place_items', 'flex_wrap', 'link' ],
            $this->linkKeys( 'link' ),
            $style['handled_keys']
        ) );

        return $this->block( $id, 'divi/group', $attrs, $children );
    }
}
