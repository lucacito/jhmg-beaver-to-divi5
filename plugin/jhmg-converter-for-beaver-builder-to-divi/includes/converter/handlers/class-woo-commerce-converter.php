<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;
use BeaverDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Beaver Builder Pro WooCommerce module. Beaver Builder itself renders a
 * WooCommerce shortcode for every layout; here product grids become Divi's own
 * Shop module (divi/shop) and every other layout keeps the very shortcode Beaver
 * Builder emitted, inside a divi/code module, so WooCommerce renders it exactly
 * as before.
 *
 * Pro module: field names come from the documentation (docs.wpbeaverbuilder.com
 * → Modules → WooCommerce); several spellings are read for each field and the
 * conversion is registered approximate.
 */
class WooCommerceConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bdc_woo_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style   = $this->mapStyle( 'generic', $node );
        $module  = $style['divi_attrs']['module'] ?? [];
        $handled = $style['handled_keys'];

        $layout = strtolower( $this->firstText( $settings, [ 'layout' ] ) ) ?: 'products';
        $pid    = trim( $this->firstText( $settings, [ 'product_id', 'product', 'id' ] ) );

        $block = null;
        switch ( $layout ) {
            case 'single_product':
            case 'product':
                $block = $this->shortcode( $id, 'product', [ 'id' => $pid ], $settings );
                break;
            case 'product_page':
                $block = $this->shortcode( $id, 'product_page', [ 'id' => $pid ], $settings );
                break;
            case 'add_to_cart':
            case 'add_to_cart_button':
                $block = $this->shortcode( $id, 'add_to_cart', [ 'id' => $pid ], $settings );
                break;
            case 'categories':
            case 'product_categories':
                $block = $this->categories( $id, $settings );
                break;
            case 'cart':
                $block = $this->shortcode( $id, 'woocommerce_cart', [], $settings );
                break;
            case 'checkout':
                $block = $this->shortcode( $id, 'woocommerce_checkout', [], $settings );
                break;
            case 'order_tracking':
                $block = $this->shortcode( $id, 'woocommerce_order_tracking', [], $settings );
                break;
            case 'my_account':
            case 'account':
                $block = $this->shortcode( $id, 'woocommerce_my_account', [], $settings );
                break;
            default:
                $block = $this->products( $id, $settings, $handled );
        }

        if ( $pid === '' && in_array( $layout, [ 'single_product', 'product', 'product_page', 'add_to_cart', 'add_to_cart_button' ], true ) ) {
            $this->engine->logWarning( "WooCommerce {$id}: no product ID set in Beaver Builder; the shortcode was written without one." );
        }

        $block['settings'] = $this->deepMergeSettings( $block['settings'], [ 'module' => $module ] );
        $this->engine->logWarning( "WooCommerce {$id}: layout '{$layout}' converted; WooCommerce must be active on the Divi site." );
        $this->logUnmappedSettings( $id, $settings, array_merge( $handled, self::CONSUMED ) );

        return $block;
    }

    /** Multiple products → divi/shop, or the [products] shortcode for sources Divi's Shop cannot query. */
    private function products( string $id, array $settings, array &$handled ): array {
        $source  = strtolower( $this->firstText( $settings, [ 'products_source', 'source', 'products_type' ] ) ) ?: 'recent';
        $count   = $this->firstText( $settings, [ 'products_per_page', 'per_page', 'number_of_products', 'num_products', 'limit' ] );
        $columns = $this->firstText( $settings, [ 'products_columns', 'columns' ] );
        $orderby = strtolower( $this->firstText( $settings, [ 'products_order_by', 'products_orderby', 'order_by', 'orderby' ] ) );
        $order   = strtolower( $this->firstText( $settings, [ 'products_order', 'order', 'sort_direction' ] ) );

        $shop_type = [
            'recent'       => 'latest',
            'featured'     => 'featured',
            'sale'         => 'sale',
            'best_selling' => 'best_selling',
            'best_sellers' => 'best_selling',
            'top_rated'    => 'top_rated',
            'category'     => 'product_category',
            'product_category' => 'product_category',
        ][ $source ] ?? null;

        $category_ids = [];
        if ( $shop_type === 'product_category' ) {
            $slugs = $this->list( $this->firstText( $settings, [ 'products_category', 'product_category', 'category', 'category_slug', 'categories' ] ) );
            foreach ( $slugs as $slug ) {
                if ( is_numeric( $slug ) ) {
                    $category_ids[] = (string) (int) $slug;
                } elseif ( function_exists( 'get_term_by' ) ) {
                    $term = get_term_by( 'slug', $slug, 'product_cat' );
                    if ( is_object( $term ) && isset( $term->term_id ) ) {
                        $category_ids[] = (string) $term->term_id;
                    }
                }
            }
            if ( $category_ids === [] ) {
                // Divi's Shop needs category IDs; without a site to resolve the slugs the shortcode keeps them.
                $shop_type = null;
            }
        }

        if ( $shop_type === null ) {
            $atts = [ 'limit' => $count, 'columns' => $columns, 'orderby' => $orderby !== 'default' ? $orderby : '', 'order' => $order ];
            switch ( $source ) {
                case 'ids':
                case 'products_ids':
                case 'product_ids':
                    $atts['ids'] = implode( ',', $this->list( $this->firstText( $settings, [ 'products_ids', 'product_ids', 'ids' ] ) ) );
                    break;
                case 'tag':
                case 'tags':
                case 'product_tag':
                    $atts['tag'] = implode( ',', $this->list( $this->firstText( $settings, [ 'products_tag', 'product_tag', 'tag', 'tags', 'tag_slug' ] ) ) );
                    break;
                case 'category':
                case 'product_category':
                    $atts['category'] = implode( ',', $this->list( $this->firstText( $settings, [ 'products_category', 'product_category', 'category', 'category_slug', 'categories' ] ) ) );
                    break;
                case 'featured':
                    $atts['visibility'] = 'featured';
                    break;
                case 'sale':
                    $atts['on_sale'] = 'true';
                    break;
                case 'best_selling':
                case 'best_sellers':
                    $atts['best_selling'] = 'true';
                    break;
                case 'top_rated':
                    $atts['top_rated'] = 'true';
                    break;
            }
            return $this->shortcode( $id, 'products', $atts, $settings );
        }

        $attrs = [ 'content' => [ 'advanced' => [ 'type' => [ 'desktop' => [ 'value' => $shop_type ] ] ] ] ];
        if ( is_numeric( $count ) ) {
            $attrs['content']['advanced']['postsNumber']['desktop']['value'] = (string) (int) $count;
        }
        if ( is_numeric( $columns ) && (int) $columns >= 1 && (int) $columns <= 6 ) {
            $attrs['content']['advanced']['columnsNumber']['desktop']['value'] = (string) (int) $columns;
        }
        $divi_orderby = [
            'default'    => 'default',
            'menu_order' => 'menu_order',
            'popularity' => 'popularity',
            'rating'     => 'rating',
            'date'       => $order === 'asc' ? 'date' : 'date-desc',
            'price'      => $order === 'desc' ? 'price-desc' : 'price',
        ][ $orderby ] ?? null;
        if ( $divi_orderby !== null ) {
            $attrs['content']['advanced']['orderby']['desktop']['value'] = $divi_orderby;
        } elseif ( $orderby !== '' ) {
            $this->engine->logWarning( "WooCommerce {$id}: sort order '{$orderby}' has no Divi Shop equivalent; Divi's default order is used." );
        }
        if ( $category_ids !== [] ) {
            $attrs['content']['advanced']['includeCategories']['desktop']['value'] = $category_ids;
        }

        $this->engine->logConverted( 'shop' );
        return $this->block( $id, 'divi/shop', $attrs );
    }

    private function categories( string $id, array $settings ): array {
        $atts = [
            'columns' => $this->firstText( $settings, [ 'categories_columns', 'cat_columns', 'columns' ] ),
            'orderby' => strtolower( $this->firstText( $settings, [ 'categories_order_by', 'categories_orderby', 'cat_orderby', 'sort_product_category_by' ] ) ),
            'order'   => strtolower( $this->firstText( $settings, [ 'categories_order', 'cat_order', 'product_category_sort_direction' ] ) ),
            'ids'     => implode( ',', $this->list( $this->firstText( $settings, [ 'categories_ids', 'category_ids', 'product_category_ids', 'ids' ] ) ) ),
        ];
        $autoselect = strtolower( $this->firstText( $settings, [ 'categories_autoselect_parent', 'autoselect_parent', 'auto_select_parent' ] ) );
        if ( in_array( $autoselect, [ 'yes', 'true', '1' ], true ) ) {
            $atts['parent'] = '';
        } else {
            $atts['parent'] = $this->firstText( $settings, [ 'categories_parent_id', 'parent_category_id', 'parent_id', 'parent' ] );
        }
        return $this->shortcode( $id, 'product_categories', $atts, $settings );
    }

    /** A WooCommerce shortcode in a divi/code module, attributes without a value omitted. */
    private function shortcode( string $id, string $tag, array $atts, array $settings ): array {
        $parts = [];
        foreach ( $atts as $key => $value ) {
            if ( is_string( $value ) && trim( $value ) !== '' ) {
                $parts[] = $key . '="' . str_replace( '"', '', trim( $value ) ) . '"';
            }
        }
        $this->engine->logConverted( 'code' );
        return $this->codeBlock( $id, '[' . $tag . ( $parts !== [] ? ' ' . implode( ' ', $parts ) : '' ) . ']' );
    }

    /** @return string[] Comma-separated ids/slugs as a clean list. */
    private function list( string $raw ): array {
        return array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ), static fn( string $v ) => $v !== '' ) );
    }

    const CONSUMED = [
        'layout', 'product_id', 'product', 'products_source', 'source', 'products_type', 'products_ids', 'product_ids', 'ids',
        'products_category', 'product_category', 'category', 'category_slug', 'categories', 'products_tag', 'product_tag', 'tag',
        'tags', 'tag_slug', 'products_per_page', 'per_page', 'number_of_products', 'num_products', 'limit', 'products_columns',
        'columns', 'products_order_by', 'products_orderby', 'order_by', 'orderby', 'products_order', 'order', 'sort_direction',
        'categories_columns', 'cat_columns', 'categories_order_by', 'categories_orderby', 'cat_orderby', 'sort_product_category_by',
        'categories_order', 'cat_order', 'product_category_sort_direction', 'categories_ids', 'category_ids', 'product_category_ids',
        'categories_autoselect_parent', 'autoselect_parent', 'auto_select_parent', 'categories_parent_id', 'parent_category_id',
        'parent_id', 'parent',
    ];
}
