<?php

use PHPUnit\Framework\TestCase;
use BeaverDivi5Converter\Converter\ConverterEngine;

/**
 * The modules Beaver Builder's reference lists that wrap other systems
 * (WooCommerce, BigCommerce, North Commerce, WordPress patterns, widgets) or
 * repeat/hide content (Loop, Popup).
 */
final class CommerceAndContainerModulesTest extends TestCase {

    protected function setUp(): void {
        bbdc_test_reset_hooks();
    }

    private function convert( array $settings, array $children = [] ): array {
        $nodes = [
            'row1' => [ 'node' => 'row1', 'type' => 'row', 'parent' => null, 'position' => 0, 'settings' => [] ],
            'grp1' => [ 'node' => 'grp1', 'type' => 'column-group', 'parent' => 'row1', 'position' => 0, 'settings' => '' ],
            'col1' => [ 'node' => 'col1', 'type' => 'column', 'parent' => 'grp1', 'position' => 0, 'settings' => [ 'size' => 100 ] ],
            'mod1' => [ 'node' => 'mod1', 'type' => 'module', 'parent' => 'col1', 'position' => 0, 'settings' => $settings ],
        ];
        foreach ( $children as $i => $child ) {
            $nodes[ 'child' . $i ] = [ 'node' => 'child' . $i, 'type' => 'module', 'parent' => 'mod1', 'position' => $i, 'settings' => $child ];
        }
        return ( new ConverterEngine() )->convert( [ 'nodes' => $nodes ] );
    }

    private function firstModule( array $result ): array {
        return $result['divi']['elements'][0]['elements'][0]['elements'][0]['elements'][0];
    }

    public function test_woocommerce_product_grids_become_divi_shop(): void {
        $block = $this->firstModule( $this->convert( [ 'type' => 'woocommerce', 'layout' => 'products', 'products_source' => 'sale', 'products_per_page' => '6', 'products_columns' => '3', 'products_order_by' => 'date', 'products_order' => 'asc' ] ) );

        $this->assertSame( 'divi/shop', $block['name'] );
        $advanced = $block['settings']['content']['advanced'];
        $this->assertSame( 'sale', $advanced['type']['desktop']['value'] );
        $this->assertSame( '6', $advanced['postsNumber']['desktop']['value'] );
        $this->assertSame( '3', $advanced['columnsNumber']['desktop']['value'] );
        $this->assertSame( 'date', $advanced['orderby']['desktop']['value'] );
    }

    public function test_woocommerce_other_layouts_keep_their_shortcode(): void {
        $cases = [
            [ [ 'layout' => 'cart' ], '[woocommerce_cart]' ],
            [ [ 'layout' => 'checkout' ], '[woocommerce_checkout]' ],
            [ [ 'layout' => 'my_account' ], '[woocommerce_my_account]' ],
            [ [ 'layout' => 'order_tracking' ], '[woocommerce_order_tracking]' ],
            [ [ 'layout' => 'single_product', 'product_id' => '42' ], '[product id="42"]' ],
            [ [ 'layout' => 'product_page', 'product_id' => '42' ], '[product_page id="42"]' ],
            [ [ 'layout' => 'add_to_cart', 'product_id' => '42' ], '[add_to_cart id="42"]' ],
            [ [ 'layout' => 'products', 'products_source' => 'ids', 'products_ids' => '1, 2', 'products_columns' => '2' ], '[products columns="2" ids="1,2"]' ],
            [ [ 'layout' => 'categories', 'categories_parent_id' => '7', 'categories_columns' => '3' ], '[product_categories columns="3" parent="7"]' ],
        ];
        foreach ( $cases as [ $settings, $expected ] ) {
            $block = $this->firstModule( $this->convert( [ 'type' => 'woocommerce' ] + $settings ) );
            $this->assertSame( 'divi/code', $block['name'], $expected );
            $this->assertSame( $expected, $block['settings']['content']['innerContent']['desktop']['value'] );
        }
    }

    public function test_loop_becomes_a_divi_loop_group_inside_a_wrapping_grid(): void {
        $result = $this->convert(
            [ 'type' => 'loop', 'source' => 'custom_query', 'post_type' => 'post', 'posts_per_page' => '6', 'order_by' => 'date', 'order' => 'DESC', 'columns' => '3', 'gap' => '24', 'gap_unit' => 'px', 'pagination' => 'numbers' ],
            [ [ 'type' => 'heading', 'heading' => '[wpbb post:title]', 'tag' => 'h3' ] ]
        );
        $outer = $this->firstModule( $result );
        $this->assertSame( 'divi/group', $outer['name'] );
        $this->assertSame( 'wrap', $outer['settings']['module']['decoration']['layout']['desktop']['value']['flexWrap'] );
        $this->assertSame( '24px', $outer['settings']['module']['decoration']['layout']['desktop']['value']['columnGap'] );

        $inner = $outer['elements'][0];
        $loop  = $inner['settings']['module']['advanced']['loop']['desktop']['value'];
        $this->assertSame( 'on', $loop['enable'] );
        $this->assertSame( 'post_types', $loop['queryType'] );
        $this->assertSame( [ [ 'value' => 'post' ] ], $loop['subTypes'] );
        $this->assertSame( '6', $loop['postPerPage'] );
        $this->assertSame( 'date', $loop['orderBy'] );
        $this->assertSame( 'DESC', $loop['order'] );
        $this->assertStringContainsString( 'calc((100% - 24px * 2) / 3)', $inner['settings']['css']['desktop']['value']['freeForm'] );

        $heading = $inner['elements'][0];
        $this->assertSame( 'divi/heading', $heading['name'] );
        $this->assertStringContainsString( '"name":"post_title"', $heading['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertContains( [ 'kind' => 'interaction', 'node_id' => 'mod1', 'detail' => 'loop pagination (numbers)' ], $result['report']['not_carried_over'] );
    }

    public function test_popup_content_is_kept_in_a_group_hidden_everywhere(): void {
        $result = $this->convert( [ 'type' => 'popup', 'popup_id' => 'promo', 'show_on' => [ 'delay' ], 'show_delay' => '3' ], [ [ 'type' => 'heading', 'heading' => 'Hello', 'tag' => 'h2' ] ] );
        $group  = $this->firstModule( $result );

        $this->assertSame( 'divi/group', $group['name'] );
        foreach ( [ 'desktop', 'tablet', 'phone' ] as $bp ) {
            $this->assertSame( 'on', $group['settings']['module']['decoration']['disabledOn'][ $bp ]['value'] );
        }
        $this->assertSame( 'promo', $group['settings']['module']['advanced']['htmlAttributes']['desktop']['value']['id'] );
        $this->assertSame( 'divi/heading', $group['elements'][0]['name'] );
        $this->assertContains( [ 'kind' => 'interaction', 'node_id' => 'mod1', 'detail' => 'popup (shown on: delay) — content kept as a hidden group; needs a popup plugin in Divi' ], $result['report']['not_carried_over'] );
    }

    public function test_bigcommerce_products_keep_their_shortcode(): void {
        $block = $this->firstModule( $this->convert( [ 'type' => 'bigcommerce-products', 'pagination' => 'yes', 'per_page' => '12', 'featured' => 'yes', 'sale' => 'no' ] ) );
        $this->assertSame( '[bigcommerce_product per_page="12" paged="1" featured="1"]', $block['settings']['content']['innerContent']['desktop']['value'] );
    }

    public function test_north_commerce_leaves_a_labelled_placeholder(): void {
        $result = $this->convert( [ 'type' => 'north-commerce', 'layout' => 'product_page', 'product_slug' => 'blue-hoodie' ] );
        $block  = $this->firstModule( $result );
        $this->assertSame( 'divi/code', $block['name'] );
        $this->assertStringContainsString( 'North Commerce Product Page (product: blue-hoodie)', $block['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertContains( [ 'kind' => 'integration', 'node_id' => 'mod1', 'detail' => 'North Commerce Product Page (product: blue-hoodie)' ], $result['report']['not_carried_over'] );
    }

    public function test_wordpress_pattern_keeps_its_reference_when_it_cannot_be_rendered(): void {
        $result = $this->convert( [ 'type' => 'reusable-block', 'block_id' => 'block-123' ] );
        $block  = $this->firstModule( $result );
        $this->assertSame( 'divi/code', $block['name'] );
        $this->assertSame( '<!-- beaver builder module: reusable-block ref=123 -->', $block['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertArrayHasKey( 'code', $result['report']['converted'], 'the Lite module is source-verified, not approximate' );
    }

    public function test_widget_without_its_class_is_named_in_a_placeholder(): void {
        $result = $this->convert( [ 'type' => 'widget', 'widget' => 'WP_Widget_Recent_Posts', 'widget_title' => 'Recent Posts', 'widget-recent-posts' => [ 'number' => '5' ] ] );
        $block  = $this->firstModule( $result );
        $this->assertStringContainsString( 'Recent Posts', $block['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertSame( [], $result['report']['skipped_settings'], 'the widget form values are consumed' );
        $this->assertContains( [ 'kind' => 'integration', 'node_id' => 'mod1', 'detail' => 'widget Recent Posts' ], $result['report']['not_carried_over'] );
    }
}
