<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;
use BeaverDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Beaver Builder column group → divi/row.
 *
 * Columns whose widths are all standard Divi fractions become divi/column
 * blocks with a columnStructure on the row. When any width has no fraction
 * (37% / 63%, say), Divi's column structure could not describe the layout, so
 * the row gets one full-width column laid out as a flex container holding
 * one divi/group per Beaver Builder column, each with its percentage width.
 */
class ColumnGroupConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bdc_row_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $column_nodes = [];
        $loose        = [];
        foreach ( $node['children'] ?? [] as $child ) {
            if ( ! is_array( $child ) ) {
                continue;
            }
            if ( ( $child['type'] ?? '' ) === 'column' ) {
                $column_nodes[] = $child;
            } else {
                $loose[] = $child;
            }
        }

        $this->engine->logConverted( 'row' );
        $this->logUnmappedSettings( $id, $settings );

        if ( count( $column_nodes ) > 1 && ! $this->allFractionsClean( $column_nodes ) ) {
            return $this->flexGroupRow( $id, $column_nodes, $loose );
        }

        $columns = [];
        foreach ( $column_nodes as $column_node ) {
            foreach ( \BeaverDivi5Converter\Converter\ConverterEngine::asList( $this->engine->convertNode( $column_node ) ) as $block ) {
                $columns[] = $block;
            }
        }

        if ( ! empty( $loose ) ) {
            $blocks = $this->convertStructureChildren( $loose );
            if ( ! empty( $blocks ) ) {
                $columns[] = $this->block( $id . '-col', 'divi/column', [], $blocks );
            }
        }

        $columns = $this->ensureColumnChildren( $id, $columns );

        return $this->block( $id, 'divi/row', $this->rowSettingsFromColumns( $columns ), $columns );
    }

    private function allFractionsClean( array $column_nodes ): bool {
        foreach ( $column_nodes as $column ) {
            $size = $column['settings']['size'] ?? null;
            if ( $size === null || $size === '' ) {
                continue; // Beaver Builder treats a missing size as an equal share.
            }
            if ( ! is_numeric( $size ) || StyleMapper::columnSizeToFraction( (float) $size ) === null ) {
                return false;
            }
        }
        return true;
    }

    private function flexGroupRow( string $id, array $column_nodes, array $loose ): array {
        $sizes = array_map( static fn( array $c ) => (string) ( $c['settings']['size'] ?? '?' ), $column_nodes );
        $this->engine->logWarning( "Row {$id}: column widths " . implode( '/', $sizes ) . "% have no Divi column fraction; laid out as flex groups inside one column." );

        $groups = [];
        foreach ( $column_nodes as $column_node ) {
            $groups[] = ( new ColumnConverter( $this->engine ) )->convertAsGroup( $column_node );
        }
        foreach ( $this->convertStructureChildren( $loose ) as $block ) {
            $groups[] = $block;
        }

        $column = $this->block( $id . '-col', 'divi/column', [
            'module' => [
                'advanced'   => [ 'type' => [ 'desktop' => [ 'value' => '4_4' ] ] ],
                'decoration' => [ 'layout' => [ 'desktop' => [ 'value' => [ 'display' => 'flex', 'flexDirection' => 'row', 'flexWrap' => 'wrap', 'alignItems' => 'stretch' ] ] ] ],
            ],
        ], $groups );

        return $this->block( $id, 'divi/row', [ 'module' => [ 'advanced' => [ 'columnStructure' => [ 'desktop' => [ 'value' => '4_4' ] ] ] ] ], [ $column ] );
    }
}
