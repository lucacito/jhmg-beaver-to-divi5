<?php

use PHPUnit\Framework\TestCase;
use BeaverDivi5Converter\Converter\ConverterEngine;
use BeaverDivi5Converter\Helpers\FieldConnections;

/**
 * Beaver Themer field connections (`[wpbb …]` shortcodes inline in text) become
 * Divi 5 dynamic-content tokens where Divi has the option, and are reported
 * where it does not.
 */
final class FieldConnectionsTest extends TestCase {

    protected function setUp(): void {
        bdc_test_reset_hooks();
    }

    private function token( string $text ): array {
        $this->assertMatchesRegularExpression( '/^\$variable\((.+)\)\$$/', $text );
        return json_decode( substr( $text, 10, -2 ), true );
    }

    public function test_site_year_becomes_current_date_with_the_format(): void {
        $result = FieldConnections::translate( "© [wpbb site:year format='Y'] Acme" );

        $this->assertSame( [ "[wpbb site:year format='Y']" ], $result['translated'] );
        $this->assertSame( [], $result['unmapped'] );
        $this->assertStringStartsWith( '© $variable(', $result['text'] );
        $this->assertStringEndsWith( ')$ Acme', $result['text'] );

        $token = $this->token( trim( substr( $result['text'], 2, -5 ) ) );
        $this->assertSame( 'content', $token['type'] );
        $this->assertSame( 'current_date', $token['value']['name'] );
        $this->assertSame( [ 'date_format' => 'custom', 'custom_date_format' => 'Y' ], $token['value']['settings'] );
    }

    public function test_post_connections_map_to_divi_options_with_their_settings(): void {
        $cases = [
            '[wpbb post:title]'                           => [ 'post_title', [] ],
            "[wpbb post:excerpt length='20']"             => [ 'post_excerpt', [ 'words' => '20' ] ],
            "[wpbb post:date format='F j, Y']"            => [ 'post_date', [ 'date_format' => 'custom', 'custom_date_format' => 'F j, Y' ] ],
            '[wpbb post:url]'                             => [ 'post_link_url', [] ],
            "[wpbb post:featured_image size='large']"     => [ 'post_featured_image', [ 'thumbnail_size' => 'large' ] ],
            "[wpbb post:author_name type='first_last']"   => [ 'post_author', [ 'name_format' => 'first_last_name' ] ],
            "[wpbb post:custom_field key='price']"        => [ 'post_meta_key', [ 'meta_key' => 'price' ] ],
            "[wpbb acf name='subtitle']"                  => [ 'post_meta_key', [ 'meta_key' => 'subtitle' ] ],
            '[wpbb site:title]'                           => [ 'site_title', [] ],
            '[wpbb site:tagline]'                         => [ 'site_tagline', [] ],
        ];
        foreach ( $cases as $shortcode => [ $name, $settings ] ) {
            $result = FieldConnections::translate( $shortcode );
            $this->assertSame( [], $result['unmapped'], $shortcode );
            $token = $this->token( $result['text'] );
            $this->assertSame( $name, $token['value']['name'], $shortcode );
            $this->assertSame( $settings, $token['value']['settings'], $shortcode );
        }
    }

    public function test_unknown_connections_stay_as_text_and_are_listed(): void {
        $result = FieldConnections::translate( "[wpbb post:terms_list taxonomy='category'] and [wpbb post:title]" );

        $this->assertSame( [ "[wpbb post:terms_list taxonomy='category']" ], $result['unmapped'] );
        $this->assertStringStartsWith( "[wpbb post:terms_list taxonomy='category'] and \$variable(", $result['text'] );
    }

    public function test_plain_text_is_untouched(): void {
        $this->assertSame( 'No connections [here]', FieldConnections::translate( 'No connections [here]' )['text'] );
    }

    public function test_the_engine_rewrites_connections_in_every_block_and_reports_the_rest(): void {
        $result = ( new ConverterEngine() )->convert( [ 'nodes' => [
            'row1' => [ 'node' => 'row1', 'type' => 'row', 'parent' => null, 'position' => 0, 'settings' => [] ],
            'grp1' => [ 'node' => 'grp1', 'type' => 'column-group', 'parent' => 'row1', 'position' => 0, 'settings' => '' ],
            'col1' => [ 'node' => 'col1', 'type' => 'column', 'parent' => 'grp1', 'position' => 0, 'settings' => [ 'size' => 100 ] ],
            'h1'   => [ 'node' => 'h1', 'type' => 'module', 'parent' => 'col1', 'position' => 0, 'settings' => [ 'type' => 'heading', 'heading' => '[wpbb post:title]', 'tag' => 'h1' ] ],
            't1'   => [ 'node' => 't1', 'type' => 'module', 'parent' => 'col1', 'position' => 1, 'settings' => [ 'type' => 'rich-text', 'text' => "<p>[wpbb site:year format='Y'] [wpbb post:terms_list taxonomy='category']</p>" ] ],
        ] ] );

        $column  = $result['divi']['elements'][0]['elements'][0]['elements'][0];
        $heading = $column['elements'][0]['settings']['title']['innerContent']['desktop']['value'];
        $this->assertSame( 'post_title', $this->token( $heading )['value']['name'] );

        $text = $column['elements'][1]['settings']['content']['innerContent']['desktop']['value'];
        $this->assertStringContainsString( '"name":"current_date"', $text );
        $this->assertStringContainsString( "[wpbb post:terms_list taxonomy='category']", $text );

        $this->assertSame( 2, $result['report']['field_connections'] );
        $this->assertContains(
            [ 'kind' => 'integration', 'node_id' => 't1', 'detail' => "field connection [wpbb post:terms_list taxonomy='category'] has no Divi dynamic content equivalent; kept as text" ],
            $result['report']['not_carried_over']
        );
    }
}
