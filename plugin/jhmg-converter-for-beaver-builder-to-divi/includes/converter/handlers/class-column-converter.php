<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;
use BeaverDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Beaver Builder column → divi/column.
 *
 * A column's children are modules, or a nested column group (which becomes a
 * divi/row inside the column — Divi 5 allows column → row → column).
 */
class ColumnConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bdc_column_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style = $this->mapStyle( 'column', $node );
        $attrs = $style['divi_attrs'];

        $fraction = $this->fraction( $settings );
        if ( $fraction !== null ) {
            $attrs = $this->deepMergeSettings( [ 'module' => [ 'advanced' => [ 'type' => [ 'desktop' => [ 'value' => $fraction ] ] ] ] ], $attrs );
        }

        $children = $this->convertStructureChildren( $node['children'] ?? [] );

        if ( empty( $children ) ) {
            $this->engine->logWarning( "Empty column after conversion: {$id}" );
        }

        $this->engine->logConverted( 'column' );
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [ 'size', 'size_medium', 'size_responsive', 'size_large', 'equal_height', 'content_alignment', 'responsive_order' ],
            $style['handled_keys']
        ) );

        return $this->block( $id, 'divi/column', $attrs, $children );
    }

    /**
     * The same column as a divi/group carrying its percentage width, for rows
     * whose column widths have no Divi fraction (see ColumnGroupConverter).
     */
    public function convertAsGroup( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bdc_group_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style = $this->mapStyle( 'group', $node );
        $attrs = $style['divi_attrs'];

        $size = $settings['size'] ?? null;
        if ( is_numeric( $size ) ) {
            $attrs = $this->deepMergeSettings( $attrs, [
                'css' => [ 'desktop' => [ 'value' => [ 'freeForm' => 'selector { width: ' . (float) $size . '%; box-sizing: border-box; }' ] ] ],
            ] );
        }

        $children = $this->convertStructureChildren( $node['children'] ?? [] );

        $this->engine->logConverted( 'group' );
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [ 'size', 'size_medium', 'size_responsive', 'size_large', 'equal_height', 'content_alignment', 'responsive_order' ],
            $style['handled_keys']
        ) );

        return $this->block( $id, 'divi/group', $attrs, $children );
    }

    private function fraction( array $settings ): ?string {
        $size = $settings['size'] ?? null;
        if ( $size === null || $size === '' || ! is_numeric( $size ) ) {
            return null;
        }
        return StyleMapper::columnSizeToFraction( (float) $size );
    }
}
