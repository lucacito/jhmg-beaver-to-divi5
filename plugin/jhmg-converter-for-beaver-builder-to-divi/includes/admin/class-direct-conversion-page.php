<?php
/**
 * "Convert a page already on this site": pick an installed Beaver Builder
 * page, check what the conversion will produce, then convert it.
 *
 * Two safety properties are enforced here rather than in markup:
 *  - The rendered picker is never trusted on the way back in. Every submitted
 *    post ID is re-verified as an existing Beaver Builder-built post.
 *  - The selection is capped server-side at bdc_direct_conversion_limit.
 */

namespace BeaverDivi5Converter\Admin;

use BeaverDivi5Converter\Conversion\ConversionPlan;
use BeaverDivi5Converter\Conversion\ConversionPreflight;
use BeaverDivi5Converter\Conversion\InstalledPostSource;
use BeaverDivi5Converter\Helpers\DiviRequirement;
use BeaverDivi5Converter\History\ImportHistory;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DirectConversionPage {

    const CHECK_ACTION   = 'bdc_direct_check';
    const CONVERT_ACTION = 'bdc_direct_convert';
    const CHECK_NONCE    = 'bdc_direct_check_nonce';
    const CONVERT_NONCE  = 'bdc_direct_convert_nonce';
    const CAPABILITY     = 'manage_options';

    /** A checked selection lives here (per user) between the check step and the report render. */
    const PLAN_IDS_TRANSIENT_PREFIX = 'bdc_direct_plan_ids_';

    private BeaverPageRepository $repo;

    public function __construct( ?BeaverPageRepository $repo = null ) {
        $this->repo = $repo ?? new BeaverPageRepository();
    }

    public function init(): void {
        add_action( 'admin_init', [ $this, 'maybe_handle_request' ] );
    }

    /**
     * Sanitise and verify a submitted selection without capping it, so the
     * check handler can tell whether a selection will be truncated.
     *
     * @return int[] Post IDs that are real, Beaver Builder-built, and deduplicated.
     */
    public function verified_post_ids( array $request ): array {
        $raw = $request['bdc_post_ids'] ?? [];
        if ( ! is_array( $raw ) ) {
            $raw = [ $raw ];
        }
        $ids = [];
        foreach ( $raw as $value ) {
            if ( ! is_scalar( $value ) || ! ctype_digit( (string) $value ) ) {
                continue;
            }
            $id = (int) $value;
            if ( $id <= 0 || in_array( $id, $ids, true ) || ! $this->is_beaver_post( $id ) ) {
                continue;
            }
            $ids[] = $id;
        }
        return $ids;
    }

    /** @return int[] Verified and capped. */
    public function selected_post_ids( array $request ): array {
        return array_slice( $this->verified_post_ids( $request ), 0, ConversionPreflight::limit() );
    }

    /** A dry run over the selection. Writes nothing. */
    public function plan_for( array $post_ids ): ConversionPlan {
        return ( new ConversionPreflight() )->run( new InstalledPostSource( $post_ids ) );
    }

    /** Convert the selection. Creates new posts; never modifies the source. */
    public function convert( array $post_ids, array $options = [] ): array {
        return ( new BatchImporter() )->importPlan( $this->plan_for( $post_ids ), array_merge( [ 'post_status' => 'draft' ], $options ) );
    }

    private function is_beaver_post( int $post_id ): bool {
        if ( ! get_post( $post_id ) ) {
            return false;
        }
        return (string) get_post_meta( $post_id, BeaverPageRepository::ENABLED_META, true ) === '1';
    }

    public function has_beaver_content(): bool {
        return $this->repo->has_any();
    }

    /** The page picker. Renders, never echoes. */
    public function render_picker( array $args = [] ): string {
        $limit    = ConversionPreflight::limit();
        $search   = trim( (string) ( $args['search'] ?? '' ) );
        $paged    = max( 1, (int) ( $args['paged'] ?? 1 ) );
        $per_page = BeaverPageRepository::PER_PAGE;

        // One extra row reveals whether a next page exists; the offset stays on the real page size.
        $rows     = $this->repo->find( [ 'search' => $search, 'paged' => $paged, 'per_page' => $per_page + 1, 'offset' => ( $paged - 1 ) * $per_page ] );
        $has_next = count( $rows ) > $per_page;
        if ( $has_next ) {
            $rows = array_slice( $rows, 0, $per_page );
        }

        $html = $this->render_search_box( $search );

        if ( empty( $rows ) ) {
            return $html . '<p class="bdc-direct-empty">' . esc_html__( 'No Beaver Builder pages found on this site. If your pages live elsewhere, use the file upload below.', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</p>';
        }

        $input_type = $limit > 1 ? 'checkbox' : 'radio';
        $name       = $limit > 1 ? 'bdc_post_ids[]' : 'bdc_post_ids';

        $html .= '<form method="post" class="bdc-direct-picker">';
        $html .= wp_nonce_field( self::CHECK_ACTION, self::CHECK_NONCE, true, false );
        $html .= '<input type="hidden" name="action" value="' . esc_attr( self::CHECK_ACTION ) . '">';
        $html .= '<table class="widefat bdc-direct-table"><tbody>';
        foreach ( $rows as $row ) {
            $html .= '<tr><td><label><input type="' . esc_attr( $input_type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $row['id'] ) . '"> <strong>' . esc_html( $row['title'] ) . '</strong></label>';
            $html .= ' <span class="bdc-direct-meta">' . esc_html( $row['post_type'] . ' · ' . $row['status'] . ' · ' . $row['modified'] ) . '</span>';
            if ( ! empty( $row['converted'] ) ) {
                $html .= ' <span class="bdc-badge-converted">' . esc_html__( 'already converted', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</span>';
            }
            $html .= '</td></tr>';
        }
        $html .= '</tbody></table>';
        $html .= '<p><button type="submit" class="button button-primary">' . esc_html__( 'Check this page', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</button></p>';
        if ( $limit === 1 ) {
            $html .= '<p class="description">' . esc_html__( 'Free converts one page at a time, as many times as you like. Pro converts your whole site in one run.', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</p>';
        }
        $html .= '</form>';

        return $html . $this->render_pager( $search, $paged, $has_next );
    }

    private function render_search_box( string $search ): string {
        return '<form method="get" class="bdc-direct-search">'
            . '<input type="hidden" name="page" value="' . esc_attr( AdminPage::MENU_SLUG ) . '">'
            . '<label class="screen-reader-text" for="bdc-direct-search-input">' . esc_html__( 'Search Beaver Builder pages', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</label>'
            . '<input type="search" id="bdc-direct-search-input" name="bdc_s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr( __( 'Search by title…', 'jhmg-converter-for-beaver-builder-to-divi' ) ) . '">'
            . '<button type="submit" class="button">' . esc_html__( 'Search', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</button></form>';
    }

    private function render_pager( string $search, int $paged, bool $has_next ): string {
        if ( $paged <= 1 && ! $has_next ) {
            return '';
        }
        $base = [ 'page' => AdminPage::MENU_SLUG ];
        if ( $search !== '' ) {
            $base['bdc_s'] = $search;
        }
        $html = '<p class="bdc-direct-pager">';
        if ( $paged > 1 ) {
            $html .= '<a class="button" href="' . esc_url( add_query_arg( $base + [ 'paged' => $paged - 1 ], admin_url( 'tools.php' ) ) ) . '">&laquo; ' . esc_html__( 'Previous', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</a> ';
        }
        if ( $has_next ) {
            $html .= '<a class="button" href="' . esc_url( add_query_arg( $base + [ 'paged' => $paged + 1 ], admin_url( 'tools.php' ) ) ) . '">' . esc_html__( 'Next', 'jhmg-converter-for-beaver-builder-to-divi' ) . ' &raquo;</a>';
        }
        return $html . '</p>';
    }

    /** The conversion report: structure, losses and a Convert button. Never renders pixels. */
    public function render_report( ConversionPlan $plan ): string {
        $html  = '<div class="bdc-direct-report"><h2>' . esc_html__( 'Conversion report', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</h2>';
        $html .= '<p class="description">' . esc_html__( 'Nothing has been written yet. This is what the conversion will produce.', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</p>';

        if ( $plan->truncated() ) {
            $html .= '<div class="notice notice-info inline"><p>' . esc_html__( 'Only the first page was checked. Converting several pages in one run is a Pro feature.', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</p></div>';
        }

        $ids = [];
        foreach ( $plan->items() as $item ) {
            $html .= '<h3>' . esc_html( $item['title'] ) . '</h3>';
            if ( $item['error'] !== '' ) {
                $html .= '<div class="notice notice-error inline"><p>' . esc_html( $item['error'] ) . '</p></div>';
                continue;
            }
            if ( ! empty( $item['source_ref']['post_id'] ) ) {
                $ids[] = (int) $item['source_ref']['post_id'];
            }

            $converted = array_sum( $item['report']['converted'] ?? [] );
            $html     .= '<p>' . esc_html( sprintf(
                /* translators: %d: number of Divi modules the conversion produced */
                _n( '%d module converted.', '%d modules converted.', $converted, 'jhmg-converter-for-beaver-builder-to-divi' ),
                $converted
            ) ) . '</p>';
            $html .= OutlineRenderer::render( $item['outline'] );

            if ( ! empty( $item['unsupported'] ) ) {
                $names = array_values( array_unique( array_filter( array_map( static fn( array $e ) => (string) ( $e['module'] ?? $e['type'] ?? '' ), $item['unsupported'] ) ) ) );
                if ( ! empty( $names ) ) {
                    $html .= '<p class="bdc-direct-unsupported"><strong>' . esc_html__( 'Could not be converted:', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</strong> ' . esc_html( implode( ', ', $names ) ) . '</p>';
                }
            }
            $html .= NotCarriedOverRenderer::render( $item['report']['not_carried_over'] ?? [], $item['report']['approximate_matches'] ?? [], $item['report']['unresolved_globals'] ?? [] );
        }

        if ( ! empty( $ids ) ) {
            $html .= '<form method="post" class="bdc-direct-convert">' . wp_nonce_field( self::CONVERT_ACTION, self::CONVERT_NONCE, true, false );
            $html .= '<input type="hidden" name="action" value="' . esc_attr( self::CONVERT_ACTION ) . '">';
            foreach ( $ids as $id ) {
                $html .= '<input type="hidden" name="bdc_post_ids[]" value="' . esc_attr( (string) $id ) . '">';
            }
            $html .= '<p><button type="submit" class="button button-primary">' . esc_html__( 'Convert to Divi 5', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</button></p>';
            $html .= '<p class="description">' . esc_html__( 'Creates a new Divi draft. Your Beaver Builder page is left exactly as it is.', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</p></form>';
        }

        return $html . '</div>';
    }

    public function maybe_handle_request(): void {
        if ( ! DiviRequirement::is_satisfied() ) {
            return;
        }
        $action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
        if ( $action !== self::CHECK_ACTION && $action !== self::CONVERT_ACTION ) {
            return;
        }
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to do that.', 'jhmg-converter-for-beaver-builder-to-divi' ) );
        }
        if ( $action === self::CHECK_ACTION ) {
            check_admin_referer( self::CHECK_ACTION, self::CHECK_NONCE );
            $this->handle_check();
            return;
        }
        check_admin_referer( self::CONVERT_ACTION, self::CONVERT_NONCE );
        $this->handle_convert();
    }

    /** Verified but uncapped, stashed per user; the report screen re-plans from the stash. */
    protected function handle_check(): void {
        $ids = $this->verified_post_ids( wp_unslash( $_POST ) );
        if ( empty( $ids ) ) {
            wp_die( esc_html__( 'Pick a page to check first.', 'jhmg-converter-for-beaver-builder-to-divi' ) );
        }
        set_transient( self::PLAN_IDS_TRANSIENT_PREFIX . get_current_user_id(), $ids, HOUR_IN_SECONDS );
        $this->redirect( add_query_arg( [ 'page' => AdminPage::MENU_SLUG, 'action' => AdminPage::VIEW_DIRECT_REPORT ], admin_url( 'tools.php' ) ) );
    }

    /** Commits the plan and lands on the batch result screen, recorded in ImportHistory so it can be undone. */
    protected function handle_convert(): void {
        $ids = $this->selected_post_ids( wp_unslash( $_POST ) );
        if ( empty( $ids ) ) {
            wp_die( esc_html__( 'No pages were selected to convert.', 'jhmg-converter-for-beaver-builder-to-divi' ) );
        }

        $results = $this->convert( $ids );
        ( new ReviewPrompt() )->record_run( $results );

        $import_id = wp_generate_uuid4();
        ( new ImportHistory() )->record( $import_id, $results );
        set_transient( 'bdc_batch_' . $import_id, $results, HOUR_IN_SECONDS );

        $this->redirect( add_query_arg( [ 'page' => AdminPage::MENU_SLUG, 'action' => 'batch_result', 'import_id' => $import_id ], admin_url( 'tools.php' ) ) );
    }

    /** Isolated so tests can observe a redirect without exit() ending the process. */
    protected function redirect( string $location ): void {
        wp_safe_redirect( $location );
        exit;
    }
}
