<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Beaver Builder Menu → divi/menu (same navigation menu). */
class MenuConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bdc_menu_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style = $this->mapStyle( 'generic', $node );
        $attrs = $style['divi_attrs'];

        $menu    = trim( $this->text( $settings, 'menu' ) );
        $menu_id = $this->menuId( $menu );
        if ( $menu_id !== null ) {
            $attrs['menu']['advanced']['menuId']['desktop']['value'] = (string) $menu_id;
        } elseif ( $menu !== '' ) {
            $this->engine->logWarning( "Menu {$id}: no navigation menu with the slug '{$menu}' exists on this site; the Divi menu is left unassigned." );
        }

        $layout = $this->text( $settings, 'menu_layout' );
        if ( in_array( $layout, [ 'vertical', 'accordion', 'expanded' ], true ) ) {
            $this->engine->logWarning( "Menu {$id}: the '{$layout}' layout has no Divi menu equivalent; a horizontal menu is used." );
        }

        $this->engine->logConverted( 'menu' );
        $this->logUnmappedSettings( $id, $settings, array_merge( [ 'menu', 'menu_layout' ], array_filter( array_keys( $settings ), static fn( string $k ) => str_starts_with( $k, 'menu_' ) || str_starts_with( $k, 'mobile_' ) || str_starts_with( $k, 'submenu_' ) || str_starts_with( $k, 'search_' ) || str_starts_with( $k, 'link_' ) || str_starts_with( $k, 'collapse' ) ), $style['handled_keys'] ) );

        return $this->block( $id, 'divi/menu', $attrs );
    }

    private function menuId( string $menu ): ?int {
        if ( $menu === '' ) {
            return null;
        }
        if ( ctype_digit( $menu ) ) {
            return (int) $menu;
        }
        if ( ! function_exists( 'get_term_by' ) ) {
            return null;
        }
        $term = get_term_by( 'slug', $menu, 'nav_menu' );
        if ( ! $term ) {
            $term = get_term_by( 'name', $menu, 'nav_menu' );
        }
        return $term && isset( $term->term_id ) ? (int) $term->term_id : null;
    }
}
