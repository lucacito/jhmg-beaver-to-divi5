<?php

use PHPUnit\Framework\TestCase;
use BeaverDivi5Converter\Conversion\ConversionCommitter;
use BeaverDivi5Converter\Conversion\ConversionOutline;
use BeaverDivi5Converter\Conversion\ConversionPlan;
use BeaverDivi5Converter\Conversion\ConversionPreflight;
use BeaverDivi5Converter\Conversion\ConversionSource;
use BeaverDivi5Converter\Conversion\InstalledPostSource;

class FakeBeaverSource implements ConversionSource {
    public function __construct( private array $items ) {}
    public function items(): array { return $this->items; }
}

final class ConversionPipelineTest extends TestCase {

    protected function setUp(): void {
        bdc_test_reset_hooks();
        $GLOBALS['__test_posts']    = [];
        $GLOBALS['__test_postmeta'] = [];
    }

    private function nodes(): array {
        return json_decode( (string) file_get_contents( __DIR__ . '/../fixtures/beaver/simple-row.json' ), true )['nodes'];
    }

    private function item( string $title = 'Home', array $extra = [] ): array {
        return array_merge( [
            'title' => $title, 'post_type' => 'page', 'post_name' => 'home', 'template_type' => '',
            'nodes' => $this->nodes(), 'settings' => [], 'error' => '',
            'source_ref' => [ 'kind' => 'installed', 'post_id' => 7, 'file' => null ],
        ], $extra );
    }

    private function seedBeaverPost( int $id, string $title = 'Home', string $type = 'page', bool $published = true ): void {
        $GLOBALS['__test_posts'][ $id ] = (object) [ 'ID' => $id, 'post_title' => $title, 'post_name' => 'home', 'post_type' => $type, 'post_status' => 'publish', 'post_modified' => '2026-09-01 00:00:00' ];
        update_post_meta( $id, '_fl_builder_enabled', '1' );
        if ( $published ) {
            update_post_meta( $id, '_fl_builder_data', json_decode( json_encode( $this->nodes() ) ) ); // stdClass objects, as WordPress returns them
        } else {
            update_post_meta( $id, '_fl_builder_draft', json_decode( json_encode( $this->nodes() ) ) );
        }
    }

    // --- preflight ---------------------------------------------------------------

    public function test_preflight_converts_an_item_into_blocks_content_report_and_outline(): void {
        $plan = ( new ConversionPreflight() )->run( new FakeBeaverSource( [ $this->item() ] ) );
        $item = $plan->items()[0];

        $this->assertInstanceOf( ConversionPlan::class, $plan );
        $this->assertSame( 'Home', $item['title'] );
        $this->assertSame( '', $item['error'] );
        $this->assertSame( 'divi/section', $item['blocks']['elements'][0]['name'] );
        $this->assertStringContainsString( 'wp:divi/heading', $item['content'] );
        $this->assertSame( 1, $item['report']['converted']['heading'] );
        $this->assertSame( 'section', $item['outline'][0]['type'] );
        $this->assertSame( 7, $item['source_ref']['post_id'] );
    }

    public function test_preflight_caps_at_the_free_limit_and_reports_truncation(): void {
        $plan = ( new ConversionPreflight() )->run( new FakeBeaverSource( [ $this->item( 'One' ), $this->item( 'Two' ) ] ) );

        $this->assertSame( 1, $plan->count() );
        $this->assertTrue( $plan->truncated() );

        add_filter( ConversionPreflight::LIMIT_FILTER, fn() => 10 );
        $plan = ( new ConversionPreflight() )->run( new FakeBeaverSource( [ $this->item( 'One' ), $this->item( 'Two' ) ] ) );
        $this->assertSame( 2, $plan->count() );
        $this->assertFalse( $plan->truncated() );
    }

    public function test_each_item_gets_a_fresh_report(): void {
        add_filter( ConversionPreflight::LIMIT_FILTER, fn() => 2 );
        $plan = ( new ConversionPreflight() )->run( new FakeBeaverSource( [ $this->item( 'One' ), $this->item( 'Two' ) ] ) );

        $this->assertSame( 1, $plan->items()[1]['report']['converted']['heading'], 'counts must not accumulate across items' );
    }

    public function test_a_source_error_is_carried_through(): void {
        $plan = ( new ConversionPreflight() )->run( new FakeBeaverSource( [ $this->item( 'Gone', [ 'error' => 'That page no longer exists.', 'nodes' => [] ] ) ] ) );

        $this->assertTrue( $plan->hasFailures() );
        $this->assertSame( 'That page no longer exists.', $plan->items()[0]['error'] );
    }

    public function test_preflight_writes_nothing(): void {
        $this->seedBeaverPost( 900 );
        $posts_before = array_map( static fn( $p ) => (array) $p, $GLOBALS['__test_posts'] );
        $meta_before  = $GLOBALS['__test_postmeta'];

        ( new ConversionPreflight() )->run( new InstalledPostSource( [ 900 ] ) );

        $this->assertSame( $posts_before, array_map( static fn( $p ) => (array) $p, $GLOBALS['__test_posts'] ) );
        $this->assertSame( $meta_before, $GLOBALS['__test_postmeta'] );
    }

    // --- installed post source ---------------------------------------------------------

    public function test_installed_source_reads_published_layout_data(): void {
        $this->seedBeaverPost( 10, 'Landing' );
        $items = ( new InstalledPostSource( [ 10 ] ) )->items();

        $this->assertSame( 'Landing', $items[0]['title'] );
        $this->assertSame( 'page', $items[0]['post_type'] );
        $this->assertCount( 4, $items[0]['nodes'] );
        $this->assertSame( '', $items[0]['error'] );
        $this->assertSame( [ 'kind' => 'installed', 'post_id' => 10, 'file' => null ], $items[0]['source_ref'] );
    }

    public function test_installed_source_refuses_draft_only_layouts_with_a_clear_message(): void {
        $this->seedBeaverPost( 11, 'Draft', 'page', false );
        $items = ( new InstalledPostSource( [ 11 ] ) )->items();

        $this->assertStringContainsString( 'Publish it in Beaver Builder first', $items[0]['error'] );
    }

    public function test_installed_source_maps_themer_and_template_post_types(): void {
        $this->seedBeaverPost( 12, 'Site Header', 'fl-theme-layout' );
        update_post_meta( 12, '_fl_theme_layout_type', 'header' );
        $this->seedBeaverPost( 13, 'Saved Row', 'fl-builder-template' );
        $this->seedBeaverPost( 14, 'Blog post', 'post' );

        $items = ( new InstalledPostSource( [ 12, 13, 14, 999 ] ) )->items();

        $this->assertSame( 'header', $items[0]['template_type'] );
        $this->assertSame( 'page', $items[0]['post_type'] );
        $this->assertSame( 'page', $items[1]['post_type'] );
        $this->assertSame( 'post', $items[2]['post_type'] );
        $this->assertSame( 'That page no longer exists.', $items[3]['error'] );
    }

    // --- committer ------------------------------------------------------------------------

    public function test_commit_creates_a_new_draft_with_divi_content_and_source_stamps(): void {
        $plan    = ( new ConversionPreflight() )->run( new FakeBeaverSource( [ $this->item() ] ) );
        $results = ( new ConversionCommitter() )->commit( $plan, [ 'post_status' => 'draft' ] );

        $this->assertTrue( $results[0]['success'] );
        $post = get_post( $results[0]['post_id'] );
        $this->assertSame( 'draft', $post->post_status );
        $this->assertSame( 'page', $post->post_type );
        $this->assertStringContainsString( 'wp:divi/heading', $post->post_content );
        $this->assertSame( 'on', get_post_meta( $post->ID, '_et_pb_use_divi_5', true ) );
        $this->assertSame( 'direct', get_post_meta( $post->ID, '_bdc_import_source', true ) );
        $this->assertSame( 7, get_post_meta( $post->ID, '_bdc_source_post_id', true ) );
    }

    public function test_commit_keeps_failures_and_never_touches_the_source_post(): void {
        $this->seedBeaverPost( 20 );
        $before = (array) get_post( 20 );
        $plan   = ( new ConversionPreflight() )->run( new FakeBeaverSource( [ $this->item( 'Broken', [ 'error' => 'nope' ] ) ] ) );

        $results = ( new ConversionCommitter() )->commit( $plan );

        $this->assertFalse( $results[0]['success'] );
        $this->assertSame( 'nope', $results[0]['error'] );
        $this->assertSame( $before, (array) get_post( 20 ) );
    }

    public function test_headers_without_pro_become_drafts_with_a_warning(): void {
        $plan    = ( new ConversionPreflight() )->run( new FakeBeaverSource( [ $this->item( 'Header', [ 'template_type' => 'header' ] ) ] ) );
        $results = ( new ConversionCommitter() )->commit( $plan );

        $this->assertTrue( $results[0]['success'] );
        $this->assertStringContainsString( 'requires the Pro add-on', end( $results[0]['report']['warnings'] ) );
    }

    public function test_headers_with_a_theme_builder_exporter_go_through_it(): void {
        $exporter = new class {
            public array $calls = [];
            public function saveHeader( string $title, array $data, array $ref ): array { $this->calls[] = [ 'header', $title, $ref ]; return [ 'post_id' => 55, 'template_id' => 56, 'theme_builder_id' => 57, 'success' => true, 'error' => '' ]; }
            public function saveFooter( string $title, array $data, array $ref ): array { $this->calls[] = [ 'footer', $title, $ref ]; return [ 'post_id' => 65, 'template_id' => 66, 'theme_builder_id' => 67, 'success' => true, 'error' => '' ]; }
        };
        add_filter( 'bdc_direct_conversion_limit', fn() => 5 );
        $plan    = ( new ConversionPreflight() )->run( new FakeBeaverSource( [ $this->item( 'H', [ 'template_type' => 'header' ] ), $this->item( 'F', [ 'template_type' => 'footer' ] ) ] ) );
        $results = ( new ConversionCommitter( null, $exporter ) )->commit( $plan );

        $this->assertSame( [ 'header', 'H', [ 'kind' => 'installed', 'post_id' => 7, 'file' => null ] ], $exporter->calls[0] );
        $this->assertSame( 65, $results[1]['post_id'] );
        $this->assertSame( 'footer', $results[1]['template_type'] );
    }

    // --- outline --------------------------------------------------------------------------

    public function test_outline_labels_structure_and_flags_placeholders(): void {
        $outline = ConversionOutline::build( [ [ 'name' => 'divi/section', 'elements' => [ [ 'name' => 'divi/row', 'elements' => [ [ 'name' => 'divi/column', 'elements' => [
            [ 'name' => 'divi/number-counter', 'settings' => [] ],
            [ 'name' => 'divi/code', 'settings' => [ 'content' => [ 'innerContent' => [ 'desktop' => [ 'value' => '<!-- beaver builder module: widget (not convertible) -->' ] ] ] ] ],
            [ 'name' => 'divi/code', 'settings' => [ 'content' => [ 'innerContent' => [ 'desktop' => [ 'value' => '<iframe></iframe>' ] ] ] ] ],
        ] ] ] ] ] ] ] );

        $modules = $outline[0]['children'][0]['children'][0]['children'];
        $this->assertSame( 'Number Counter', $modules[0]['label'] );
        $this->assertFalse( $modules[0]['placeholder'] );
        $this->assertTrue( $modules[1]['placeholder'] );
        $this->assertFalse( $modules[2]['placeholder'] );
        $this->assertSame( 'column', $outline[0]['children'][0]['children'][0]['type'] );
    }
}
