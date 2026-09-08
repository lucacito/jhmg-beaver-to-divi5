<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;
use BeaverDivi5Converter\Helpers\Size;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Beaver Builder 2.11 Loop module (needs Beaver Themer) → Divi 5's own Loop.
 *
 * Beaver Builder repeats the module's child modules once per query result and
 * lays the items out in a grid. Divi 5 repeats any module carrying
 * `module.advanced.loop`, so the children go inside a divi/group with the loop
 * enabled, and that group sits in a flex-wrapping outer group that reproduces
 * the column count and gap. Field connections inside the children become Divi
 * dynamic content (see Helpers\FieldConnections), which Divi resolves against
 * each loop item.
 *
 * Documented but not yet shipped at the time of writing; field names are
 * read defensively and the conversion is registered approximate.
 */
class LoopConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bbdc_loop_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style   = $this->mapStyle( 'group', $node );
        $outer   = $style['divi_attrs'];
        $handled = $style['handled_keys'];

        // Query.
        $source     = strtolower( $this->firstText( $settings, [ 'source', 'query_source', 'data_source' ] ) ) ?: 'custom_query';
        $post_type  = $this->firstText( $settings, [ 'post_type', 'post_types' ] ) ?: 'post';
        $taxonomy   = $this->firstText( $settings, [ 'taxonomy' ] ) ?: 'category';
        $per_page   = $this->firstText( $settings, [ 'posts_per_page', 'per_page', 'posts_number' ] );
        $offset     = $this->firstText( $settings, [ 'offset', 'post_offset' ] );
        $order_by   = strtolower( $this->firstText( $settings, [ 'order_by', 'orderby' ] ) );
        $order      = strtoupper( $this->firstText( $settings, [ 'order' ] ) );
        $exclude    = strtolower( $this->firstText( $settings, [ 'exclude_current_post', 'exclude_self' ] ) );

        $loop = [ 'enable' => 'on' ];
        switch ( $source ) {
            case 'main_query':
                $loop['queryType'] = 'current_page';
                break;
            case 'taxonomy_query':
            case 'terms':
                $loop['queryType'] = 'terms';
                $loop['subTypes']  = [ [ 'value' => $taxonomy ] ];
                break;
            default:
                $loop['queryType'] = 'post_types';
                $loop['subTypes']  = array_map( static fn( string $t ) => [ 'value' => $t ], array_values( array_filter( array_map( 'trim', explode( ',', $post_type ) ) ) ) );
        }
        if ( is_numeric( $per_page ) ) {
            $loop['postPerPage'] = (string) (int) $per_page;
        }
        if ( is_numeric( $offset ) && (int) $offset > 0 ) {
            $loop['postOffset'] = (string) (int) $offset;
        }
        if ( $order_by !== '' ) {
            $loop['orderBy'] = [ 'name' => 'title', 'title' => 'title', 'date' => 'date', 'modified' => 'modified', 'menu_order' => 'menu_order', 'rand' => 'rand', 'comment_count' => 'comment_count', 'id' => 'ID', 'author' => 'author' ][ $order_by ] ?? $order_by;
        }
        if ( in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
            $loop['order'] = $order;
        }
        if ( in_array( $exclude, [ 'yes', 'true', '1' ], true ) ) {
            $loop['excludeCurrentPost'] = 'on';
        }

        // Grid: the outer group wraps its repeated children.
        $gap     = Size::fromSettings( $settings, 'gap', 'gap' ) ?: '20px';
        $columns = $this->firstText( $settings, [ 'columns', 'num_columns', 'number_of_columns', 'columns_large' ] );
        $columns = is_numeric( $columns ) && (int) $columns > 0 ? (int) $columns : 3;
        $outer['module']['decoration']['layout']['desktop']['value'] = [ 'display' => 'flex', 'flexDirection' => 'row', 'flexWrap' => 'wrap', 'columnGap' => $gap, 'rowGap' => $gap ];

        $sizing = strtolower( $this->firstText( $settings, [ 'item_sizing', 'sizing' ] ) );
        if ( str_contains( $sizing, 'size' ) || str_contains( $sizing, 'width' ) ) {
            $min  = Size::fromSettings( $settings, 'min_width', 'min_width' ) ?: '100px';
            $max  = Size::fromSettings( $settings, 'max_width', 'max_width' ) ?: '300px';
            $item = "selector { flex: 1 1 {$min}; max-width: {$max}; min-width: 0; }";
        } else {
            $item = "selector { flex: 0 0 calc((100% - {$gap} * " . ( $columns - 1 ) . ") / {$columns}); max-width: calc((100% - {$gap} * " . ( $columns - 1 ) . ") / {$columns}); min-width: 0; }";
        }

        $this->engine->pushInheritedColors( $this->containerColors( $settings, $id ) );
        try {
            $children = $this->convertStructureChildren( $node['children'] ?? [] );
        } finally {
            $this->engine->popInheritedColors();
        }
        if ( $children === [] ) {
            $this->engine->logWarning( "Loop {$id}: no child modules; the loop repeats an empty item." );
        }

        $inner = $this->block( $id . '-item', 'divi/group', [
            'module' => [ 'advanced' => [ 'loop' => [ 'desktop' => [ 'value' => $loop ] ] ] ],
            'css'    => [ 'desktop' => [ 'value' => [ 'freeForm' => $item ] ] ],
        ], $children );

        $pagination = strtolower( $this->firstText( $settings, [ 'pagination', 'pagination_type' ] ) );
        if ( $pagination !== '' && $pagination !== 'none' ) {
            $this->engine->logNotCarriedOver( 'interaction', $id, "loop pagination ({$pagination})" );
        }
        if ( $this->firstText( $settings, [ 'no_results_message', 'no_results_text' ] ) !== '' ) {
            $this->engine->logNotCarriedOver( 'interaction', $id, 'loop "no results" message' );
        }
        if ( $loop['queryType'] === 'terms' ) {
            $this->engine->logWarning( "Loop {$id}: taxonomy loops depend on Divi's term query options; check the terms it shows." );
        }

        $this->engine->logConverted( 'group' );
        $this->logUnmappedSettings( $id, $settings, array_merge( $handled, self::CONSUMED ) );

        return $this->block( $id, 'divi/group', $outer, [ $inner ] );
    }

    const CONSUMED = [
        'source', 'query_source', 'data_source', 'post_type', 'post_types', 'taxonomy', 'posts_per_page', 'per_page', 'posts_number',
        'offset', 'post_offset', 'order_by', 'orderby', 'order', 'exclude_current_post', 'exclude_self', 'authors', 'terms',
        'select_terms', 'hide_empty', 'gap', 'gap_unit', 'columns', 'num_columns', 'number_of_columns', 'columns_large',
        'columns_medium', 'columns_responsive', 'item_sizing', 'sizing', 'min_width', 'min_width_unit', 'max_width', 'max_width_unit',
        'pagination', 'pagination_type', 'auto_scroll', 'no_results_message', 'no_results_text', 'show_search', 'layout', 'template',
    ];
}
