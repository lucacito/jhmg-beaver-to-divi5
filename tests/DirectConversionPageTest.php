<?php

use PHPUnit\Framework\TestCase;
use BeaverDivi5Converter\Admin\DirectConversionPage;

final class DirectConversionPageTest extends TestCase {

    protected function setUp(): void {
        bbdc_test_reset_hooks();
        $GLOBALS['__test_posts']    = [];
        $GLOBALS['__test_postmeta'] = [];
    }

    private function seed( int $id, string $title = 'Home', bool $beaver = true ): int {
        $GLOBALS['__test_posts'][ $id ] = (object) [ 'ID' => $id, 'post_title' => $title, 'post_name' => 'home', 'post_type' => 'page', 'post_status' => 'publish', 'post_modified' => '2026-09-01 00:00:00' ];
        if ( $beaver ) {
            update_post_meta( $id, '_fl_builder_enabled', '1' );
            update_post_meta( $id, '_fl_builder_data', json_decode( json_encode( json_decode( (string) file_get_contents( __DIR__ . '/../fixtures/beaver/simple-row.json' ), true )['nodes'] ) ) );
        }
        return $id;
    }

    public function test_it_reads_verifies_and_caps_selected_ids(): void {
        $a = $this->seed( 201 );
        $b = $this->seed( 202, 'B' );
        $plain = $this->seed( 203, 'Plain', false );
        $page  = new DirectConversionPage();

        $this->assertSame( [ 201, 202 ], $page->verified_post_ids( [ 'bbdc_post_ids' => [ '201', '202', '203', 'abc', '-1', '999', '201', [ 'x' ] ] ] ) );
        $this->assertSame( [ 201 ], $page->selected_post_ids( [ 'bbdc_post_ids' => [ '201', '202' ] ] ), 'free converts one page per run' );
        $this->assertSame( [ 202 ], $page->selected_post_ids( [ 'bbdc_post_ids' => '202' ] ) );

        add_filter( 'bbdc_direct_conversion_limit', fn() => 10 );
        $this->assertSame( [ 201, 202 ], $page->selected_post_ids( [ 'bbdc_post_ids' => [ '201', '202', (string) $plain ] ] ) );
    }

    public function test_the_report_shows_the_outline_and_a_convert_button_and_writes_nothing(): void {
        $id   = $this->seed( 210, 'Landing' );
        $page = new DirectConversionPage();
        $posts_before = count( $GLOBALS['__test_posts'] );

        $html = $page->render_report( $page->plan_for( [ $id ] ) );

        $this->assertStringContainsString( 'Landing', $html );
        $this->assertStringContainsString( '4 modules converted.', $html );
        $this->assertStringContainsString( 'bdc-outline-node--section', $html );
        $this->assertStringContainsString( 'name="bbdc_post_ids[]" value="210"', $html );
        $this->assertStringContainsString( 'Convert to Divi 5', $html );
        $this->assertCount( $posts_before, $GLOBALS['__test_posts'] );
    }

    public function test_the_report_explains_a_page_that_cannot_convert(): void {
        $id   = $this->seed( 211, 'Empty', false );
        update_post_meta( $id, '_fl_builder_enabled', '1' );
        $page = new DirectConversionPage();

        $html = $page->render_report( $page->plan_for( [ $id ] ) );

        $this->assertStringContainsString( 'No published Beaver Builder layout', $html );
        $this->assertStringNotContainsString( 'Convert to Divi 5', $html );
    }

    public function test_convert_creates_a_draft_and_leaves_the_source_alone(): void {
        $id      = $this->seed( 220 );
        $before  = (array) get_post( $id );
        $results = ( new DirectConversionPage() )->convert( [ $id ] );

        $this->assertTrue( $results[0]['success'] );
        $this->assertSame( 'draft', get_post( $results[0]['post_id'] )->post_status );
        $this->assertSame( $before, (array) get_post( $id ) );
        $this->assertSame( 220, get_post_meta( $results[0]['post_id'], '_bbdc_source_post_id', true ) );
    }

    public function test_check_handler_stashes_the_selection_and_redirects(): void {
        $id   = $this->seed( 230 );
        $page = new class() extends DirectConversionPage {
            public array $redirects = [];
            protected function redirect( string $location ): void { $this->redirects[] = $location; }
            public function check( array $post ): void { $this->handle_check( $post ); }
        };

        $page->check( [ 'bbdc_post_ids' => [ '230' ] ] );

        $this->assertSame( [ 230 ], get_transient( DirectConversionPage::PLAN_IDS_TRANSIENT_PREFIX . get_current_user_id() ) );
        $this->assertStringContainsString( 'action=direct_report', $page->redirects[0] );
    }

    public function test_convert_handler_records_history_and_redirects_to_the_result_screen(): void {
        $id   = $this->seed( 240 );
        $page = new class() extends DirectConversionPage {
            public array $redirects = [];
            protected function redirect( string $location ): void { $this->redirects[] = $location; }
            public function go( array $post ): void { $this->handle_convert( $post ); }
        };

        $page->go( [ 'bbdc_post_ids' => [ '240' ] ] );

        $this->assertStringContainsString( 'action=batch_result', $page->redirects[0] );
        $runs = get_option( 'bbdc_import_history' );
        $this->assertCount( 1, $runs );
        $this->assertCount( 1, $runs[0]['post_ids'] );
        $this->assertSame( 1, get_option( 'bbdc_conversions_total' ) );
    }

    public function test_an_empty_selection_never_writes_a_junk_history_entry(): void {
        $page = new class() extends DirectConversionPage {
            protected function redirect( string $location ): void {}
            public function go( array $post ): void { $this->handle_convert( $post ); }
        };
        $this->expectException( \RuntimeException::class );
        $page->go( [ 'bbdc_post_ids' => [ '999' ] ] );
    }
}
