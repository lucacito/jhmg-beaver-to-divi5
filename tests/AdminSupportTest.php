<?php

use PHPUnit\Framework\TestCase;
use BeaverDivi5Converter\Admin\AdminPage;
use BeaverDivi5Converter\Admin\BeaverPageRepository;
use BeaverDivi5Converter\Admin\CoveragePanel;
use BeaverDivi5Converter\Admin\NotCarriedOverRenderer;
use BeaverDivi5Converter\Admin\OutlineRenderer;
use BeaverDivi5Converter\Admin\ReviewPrompt;
use BeaverDivi5Converter\History\ImportHistory;
use BeaverDivi5Converter\History\ImportRollback;
use BeaverDivi5Converter\Telemetry\CoverageTelemetry;

final class AdminSupportTest extends TestCase {

    protected function setUp(): void {
        bbdc_test_reset_hooks();
        $GLOBALS['__test_posts']     = [];
        $GLOBALS['__test_postmeta']  = [];
        $GLOBALS['__test_user_meta'] = [];
        $GLOBALS['__test_trashed']   = [];
        $GLOBALS['bbdc_test_http']    = [ 'queue' => [], 'log' => [] ];
        unset( $GLOBALS['__test_caps'] );
    }

    // --- repository ------------------------------------------------------------------

    public function test_repository_queries_beaver_enabled_posts_across_beaver_post_types(): void {
        update_option( BeaverPageRepository::POST_TYPES_OPTION, [ 'page', 'product' ] );
        $args = ( new BeaverPageRepository( fn() => [] ) )->query_args( [ 'search' => 'home', 'paged' => 2, 'per_page' => 21, 'offset' => 20 ] );

        $this->assertSame( [ 'page', 'post', 'product', 'fl-builder-template', 'fl-theme-layout' ], $args['post_type'] );
        $this->assertSame( '_fl_builder_enabled', $args['meta_key'] );
        $this->assertSame( '1', $args['meta_value'] );
        $this->assertSame( 'home', $args['s'] );
        $this->assertSame( 21, $args['posts_per_page'] );
        $this->assertSame( 20, $args['offset'] );
    }

    public function test_repository_rows_flag_already_converted_pages(): void {
        $GLOBALS['__test_posts'][5] = (object) [ 'ID' => 5, 'post_title' => 'Home', 'post_type' => 'page', 'post_status' => 'publish', 'post_modified' => '2026-09-01' ];
        $GLOBALS['__test_posts'][6] = (object) [ 'ID' => 6, 'post_title' => '', 'post_type' => 'page', 'post_status' => 'draft', 'post_modified' => '2026-09-02' ];
        wp_insert_post( [ 'post_type' => 'page', 'post_title' => 'Converted copy' ] );
        update_post_meta( 1000, '_bbdc_source_post_id', 5 );

        $rows = ( new BeaverPageRepository( fn() => [ $GLOBALS['__test_posts'][5], $GLOBALS['__test_posts'][6] ] ) )->find();

        $this->assertTrue( $rows[0]['converted'] );
        $this->assertFalse( $rows[1]['converted'] );
        $this->assertSame( '(no title)', $rows[1]['title'] );
    }

    // --- history / rollback ---------------------------------------------------------------

    public function test_history_records_runs_newest_first_and_caps_them(): void {
        $history = new ImportHistory();
        for ( $i = 1; $i <= 27; $i++ ) {
            $history->record( "run-{$i}", [ [ 'success' => true, 'post_id' => $i, 'unsupported' => [ [ 'module' => 'widget' ] ] ] ] );
        }

        $runs = $history->all();
        $this->assertCount( ImportHistory::MAX_RUNS, $runs );
        $this->assertSame( 'run-27', $runs[0]['id'] );
        $this->assertSame( [ 27 ], $runs[0]['post_ids'] );
        $this->assertSame( [ 'widget' ], $runs[0]['unsupported'] );
        $this->assertSame( 25, $history->coverage()[0]['runs'] );
        $this->assertNull( $history->find( 'run-1' ), 'the oldest runs were evicted' );
    }

    public function test_history_ignores_the_skipped_row_when_counting_failures(): void {
        $history = new ImportHistory();
        $history->record( 'r', [ [ 'success' => true, 'post_id' => 3 ], [ 'success' => false, 'skipped' => true, 'post_id' => 0 ] ] );

        $this->assertSame( 1, $history->all()[0]['succeeded'] );
        $this->assertSame( 0, $history->all()[0]['failed'] );
    }

    public function test_rollback_trashes_only_posts_the_plugin_still_owns(): void {
        wp_insert_post( [ 'post_type' => 'page', 'ID' => 300 ] );
        wp_insert_post( [ 'post_type' => 'page', 'ID' => 301 ] );
        update_post_meta( 300, '_bbdc_import_source', 'direct' );
        $history = new ImportHistory();
        $history->record( 'run-a', [ [ 'success' => true, 'post_id' => 300 ], [ 'success' => true, 'post_id' => 301 ] ] );

        $result = ( new ImportRollback( $history ) )->rollback( 'run-a' );

        $this->assertSame( [ 'trashed' => 1, 'skipped' => 1, 'trash_unavailable' => false ], $result );
        $this->assertSame( [ 300 ], $GLOBALS['__test_trashed'] );
        $this->assertTrue( $history->find( 'run-a' )['rolled_back'] );
    }

    public function test_rollback_of_an_unknown_run_does_nothing(): void {
        $this->assertSame( [ 'trashed' => 0, 'skipped' => 0, 'trash_unavailable' => false ], ( new ImportRollback() )->rollback( 'nope' ) );
    }

    public function test_rollback_notice_markup(): void {
        $rollback = new ImportRollback();
        $this->assertStringContainsString( '2 pages were moved to the Trash', $rollback->notice_markup( [ 'trashed' => 2, 'skipped' => 0, 'trash_unavailable' => false ] ) );
        $this->assertStringContainsString( 'empty the Trash immediately', $rollback->notice_markup( [ 'trashed' => 0, 'skipped' => 3, 'trash_unavailable' => true ] ) );
        $this->assertStringContainsString( 'Nothing to undo', $rollback->notice_markup( [ 'trashed' => 0, 'skipped' => 0, 'trash_unavailable' => false ] ) );
    }

    // --- telemetry -------------------------------------------------------------------------

    public function test_telemetry_is_off_by_default_and_sends_only_module_names_when_on(): void {
        $history = new ImportHistory();
        $history->record( 'r', [ [ 'success' => true, 'post_id' => 1, 'unsupported' => [ [ 'module' => 'wpforms' ], [ 'module' => 'wpforms' ], [ 'module' => 'acf-block' ] ] ] ] );

        $telemetry = new CoverageTelemetry( $history, '2026-09-08' );
        $telemetry->maybe_send();
        $this->assertSame( [], $GLOBALS['bbdc_test_http']['log'], 'nothing leaves the site without consent' );

        update_option( CoverageTelemetry::CONSENT_OPTION, '1' );
        $telemetry->maybe_send();

        $this->assertCount( 1, $GLOBALS['bbdc_test_http']['log'] );
        $this->assertSame( CoverageTelemetry::ENDPOINT, $GLOBALS['bbdc_test_http']['log'][0]['url'] );
        $this->assertSame( [ 'product' => 'beaver-to-divi5', 'widget_types' => [ 'acf-block', 'wpforms' ] ], json_decode( $GLOBALS['bbdc_test_http']['log'][0]['args']['body'], true ) );
        $this->assertSame( '2026-09-08', get_option( CoverageTelemetry::LAST_SENT_OPTION ) );

        $telemetry->maybe_send();
        $this->assertCount( 1, $GLOBALS['bbdc_test_http']['log'], 'not due again for a week' );
        $this->assertTrue( ( new CoverageTelemetry( $history, '2026-09-15' ) )->due() );
    }

    // --- renderers --------------------------------------------------------------------------

    public function test_outline_renderer(): void {
        $html = OutlineRenderer::render( [ [ 'type' => 'section', 'label' => 'Section', 'children' => [ [ 'type' => 'module', 'label' => 'Code', 'placeholder' => true, 'children' => [] ] ] ] ] );
        $this->assertStringContainsString( 'bdc-outline-node--section', $html );
        $this->assertStringContainsString( 'bdc-outline-node--placeholder', $html );
        $this->assertStringContainsString( 'rebuild by hand', $html );
        $this->assertStringContainsString( 'Nothing to show', OutlineRenderer::render( [] ) );
    }

    public function test_not_carried_over_renderer_groups_by_kind_and_escapes(): void {
        $html = NotCarriedOverRenderer::render(
            [ [ 'kind' => 'animation', 'node_id' => 'a1', 'detail' => 'fade<b>' ], [ 'kind' => 'hover', 'node_id' => 'b1', 'detail' => 'button hover colours' ] ],
            [ [ 'node_id' => 'c1', 'module' => 'accordion', 'matched_to' => 'AccordionConverter' ] ],
            [ [ 'node_id' => 'd1', 'setting_key' => 'bg_color', 'ref' => 'var(--fl-global-x)' ] ]
        );
        $this->assertStringContainsString( 'Animations — removed', $html );
        $this->assertStringContainsString( 'fade&lt;b&gt;', $html );
        $this->assertStringContainsString( 'Hover colours', $html );
        $this->assertStringContainsString( 'accordion → AccordionConverter', $html );
        $this->assertStringContainsString( 'bg_color = var(--fl-global-x)', $html );
        $this->assertSame( '', NotCarriedOverRenderer::render( [] ) );
    }

    public function test_coverage_panel_lists_gaps_and_undo_controls(): void {
        $history = new ImportHistory();
        $history->record( 'run-x', [ [ 'success' => true, 'post_id' => 9, 'unsupported' => [ [ 'module' => 'acf-block' ] ] ] ] );

        $html = ( new CoveragePanel( $history ) )->markup();
        $this->assertStringContainsString( '<code>acf-block</code>', $html );
        $this->assertStringContainsString( 'Share these module names', $html );
        $this->assertStringContainsString( 'bbdc_rollback=run-x', $html );
        delete_option( ImportHistory::OPTION );
        $this->assertSame( '', ( new CoveragePanel( new ImportHistory() ) )->markup(), 'nothing to show before the first run' );
    }

    // --- review prompt --------------------------------------------------------------------------

    public function test_review_prompt_asks_after_three_clean_conversions_and_respects_dismissal(): void {
        $prompt = new ReviewPrompt( '2026-09-08' );
        $clean  = [ [ 'success' => true ] ];

        $prompt->record_run( [ [ 'success' => true ], [ 'success' => true ] ] );
        $this->assertFalse( $prompt->should_ask( $clean ) );
        $prompt->record_run( $clean );
        $this->assertTrue( $prompt->should_ask( $clean ) );
        $this->assertFalse( $prompt->should_ask( [ [ 'success' => false ] ] ), 'never after a failure' );
        $this->assertTrue( $prompt->should_ask( [ [ 'success' => true ], [ 'success' => false, 'skipped' => true ] ] ), 'the free-limit row is not a failure' );

        $prompt->snooze();
        $this->assertFalse( $prompt->should_ask( $clean ) );
        $this->assertTrue( ( new ReviewPrompt( '2026-09-22' ) )->should_ask( $clean ) );

        $prompt->set_state( ReviewPrompt::STATE_DONE );
        $this->assertFalse( ( new ReviewPrompt( '2027-01-01' ) )->should_ask( $clean ) );
        $this->assertStringContainsString( 'Converted 3 pages so far.', $prompt->markup() );
    }

    // --- batch table ------------------------------------------------------------------------------

    public function test_batch_table_shows_each_outcome(): void {
        wp_insert_post( [ 'ID' => 400, 'post_type' => 'page', 'post_status' => 'draft' ] );
        $html = AdminPage::batch_table( [
            [ 'title' => 'Home', 'post_id' => 400, 'success' => true, 'error' => '', 'report' => [ 'warnings' => [ 'Image missing alt text: p1' ], 'not_carried_over' => [] ], 'unsupported' => [] ],
            [ 'title' => 'Broken', 'post_id' => 0, 'success' => false, 'error' => 'No layout', 'report' => [], 'unsupported' => [] ],
            [ 'title' => '2 more pages in this file were not converted', 'post_id' => 0, 'success' => false, 'skipped' => true, 'error' => 'Free converts one page per upload.', 'report' => [], 'unsupported' => [] ],
        ], 'abc' );

        $this->assertStringContainsString( '2 page(s) processed', $html );
        $this->assertStringContainsString( '1 converted', $html );
        $this->assertStringContainsString( '1 failed', $html );
        $this->assertStringContainsString( 'Image missing alt text: p1', $html );
        $this->assertStringContainsString( 'bbdc_action=publish', $html );
        $this->assertStringContainsString( 'No layout', $html );
        $this->assertStringContainsString( 'Not converted', $html );
    }
}
