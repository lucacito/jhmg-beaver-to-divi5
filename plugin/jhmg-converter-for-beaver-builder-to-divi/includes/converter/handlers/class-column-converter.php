<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;
use BeaverDivi5Converter\StyleMapper\GlobalSettingsResolver;
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
            $flex  = StyleMapper::flexTypeFor( $fraction );
            if ( $flex !== null ) {
                $attrs['module']['decoration']['sizing']['desktop']['value']['flexType'] = $flex;
            }
        }

        // Beaver Builder stacks modules with their own margins only (its clearfix
        // stops margins collapsing); Divi's 30px column gap would add to them.
        $attrs['module']['decoration']['layout']['desktop']['value']['rowGap'] = '0px';

        // A side the column leaves blank takes the site's global column padding.
        $global_padding = GlobalSettingsResolver::columnPadding();
        if ( $global_padding !== null ) {
            $padding = $attrs['module']['decoration']['spacing']['desktop']['value']['padding'] ?? [];
            foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
                if ( ! isset( $padding[ $side ] ) || $padding[ $side ] === '' ) {
                    $padding[ $side ] = $global_padding[ $side ];
                }
            }
            $attrs['module']['decoration']['spacing']['desktop']['value']['padding'] = $padding + [ 'syncVertical' => 'off', 'syncHorizontal' => 'off' ];
        }

        $this->engine->pushInheritedColors( $this->containerColors( $settings, $id ) );
        try {
            $children = $this->convertStructureChildren( $node['children'] ?? [] );
        } finally {
            $this->engine->popInheritedColors();
        }

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
            // Beaver Builder puts a column's margins inside its width slot (they sit
            // on .fl-col-content); a flex item's margins add to its basis, so the
            // basis gives them back or the groups no longer fit on one line.
            $pct    = (float) $size;
            $margin = $attrs['module']['decoration']['spacing']['desktop']['value']['margin'] ?? [];
            $sides  = array_filter( [ $margin['left'] ?? '', $margin['right'] ?? '' ], static fn( $v ) => is_string( $v ) && $v !== '' && $v !== '0px' && $v !== '0' );
            $basis  = $sides === [] ? "{$pct}%" : "calc({$pct}% - " . implode( ' - ', $sides ) . ')';
            $reset  = $sides === [] ? ' margin-left: 0; margin-right: 0;' : '';
            $attrs  = $this->deepMergeSettings( $attrs, [
                'css' => [ 'desktop' => [ 'value' => [ 'freeForm' => "selector { flex: 0 0 {$basis}; max-width: {$basis}; min-width: 0; box-sizing: border-box;{$reset} }" ] ] ],
            ] );
        }

        $this->engine->pushInheritedColors( $this->containerColors( $settings, $id ) );
        try {
            $children = $this->convertStructureChildren( $node['children'] ?? [] );
        } finally {
            $this->engine->popInheritedColors();
        }

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
