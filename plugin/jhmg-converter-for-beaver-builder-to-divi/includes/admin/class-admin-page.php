<?php

namespace BeaverDivi5Converter\Admin;

use BeaverDivi5Converter\Helpers\DiviRequirement;
use BeaverDivi5Converter\History\ImportHistory;
use BeaverDivi5Converter\Parsers\BeaverImportParser;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tools → Beaver Builder → Divi 5. Routes between the landing page (picker,
 * upload form, coverage), the "Check this page" report and the batch result.
 */
class AdminPage {

    const MENU_SLUG           = 'bdc-converter';
    const IMPORT_NONCE_NAME   = 'bbdc_import_nonce';
    const IMPORT_NONCE_ACTION = 'bbdc_import';
    const VIEW_DIRECT_REPORT  = 'direct_report';
    const PRO_URL             = 'https://divi5lab.com/plugins/beaver-builder-to-divi-5';
    const PRO_PRICE           = '$25/yr';

    /** Page slugs the shared stylesheet covers (Pro renders with the same class names). */
    private const STYLED_PAGE_SLUGS = [ self::MENU_SLUG, 'bdcp-pro' ];

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_init', [ $this, 'handle_post' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_styles' ] );
    }

    public function enqueue_admin_styles( string $hook ): void {
        if ( ! $this->hook_is_styled( $hook ) ) {
            return;
        }
        wp_register_style( 'bdc-admin', false, [], BBDC_PLUGIN_VERSION );
        wp_enqueue_style( 'bdc-admin' );
        wp_add_inline_style( 'bdc-admin', $this->inline_css() );
    }

    protected function hook_is_styled( string $hook ): bool {
        foreach ( self::STYLED_PAGE_SLUGS as $slug ) {
            if ( strpos( $hook, $slug ) !== false ) {
                return true;
            }
        }
        return false;
    }

    public function register_menu(): void {
        add_management_page(
            __( 'Beaver Builder to Divi 5 Converter', 'jhmg-converter-for-beaver-builder-to-divi' ),
            __( 'Beaver Builder → Divi 5', 'jhmg-converter-for-beaver-builder-to-divi' ),
            'manage_options',
            self::MENU_SLUG,
            [ $this, 'render_page' ]
        );
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'jhmg-converter-for-beaver-builder-to-divi' ) );
        }
        if ( ! DiviRequirement::is_satisfied() ) {
            $this->render_requirement_failure();
            return;
        }

        $action = sanitize_key( $_GET['action'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $action === 'batch_result' ) {
            $this->render_batch_result();
        } elseif ( $action === self::VIEW_DIRECT_REPORT ) {
            $this->render_direct_report();
        } else {
            $this->render_landing();
        }
    }

    private function render_requirement_failure(): void {
        echo '<div class="wrap bdc-wrap"><h1>' . esc_html__( 'Beaver Builder to Divi 5 Converter', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</h1>';
        echo '<div class="notice notice-error inline"><p>' . esc_html( DiviRequirement::message() ) . '</p></div>';
        echo '<p class="description">' . esc_html__( 'Install and activate Divi 5, then return to this screen. Nothing has been changed on your site.', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</p></div>';
    }

    // ------------------------------------------------------------------
    // POST dispatch
    // ------------------------------------------------------------------

    public function handle_post(): void {
        if ( ! DiviRequirement::is_satisfied() ) {
            return;
        }
        $action = sanitize_key( $_POST['action'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- each handler verifies its own nonce
        if ( $action === self::IMPORT_NONCE_ACTION ) {
            $this->handle_import();
        }
        $bbdc_action = sanitize_key( $_GET['bbdc_action'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $bbdc_action === 'publish' ) {
            $this->handle_publish();
        }
    }

    private function handle_import(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jhmg-converter-for-beaver-builder-to-divi' ) );
        }
        check_admin_referer( self::IMPORT_NONCE_ACTION, self::IMPORT_NONCE_NAME );

        $upload = isset( $_FILES['bbdc_import_file'] ) && is_array( $_FILES['bbdc_import_file'] ) ? $_FILES['bbdc_import_file'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if ( ! $upload ) {
            wp_die( esc_html__( 'No file was uploaded.', 'jhmg-converter-for-beaver-builder-to-divi' ) );
        }
        if ( (int) $upload['error'] !== UPLOAD_ERR_OK ) {
            wp_die( esc_html( $this->upload_error_message( (int) $upload['error'] ) ) );
        }

        $post_type = sanitize_key( $_POST['bbdc_post_type'] ?? 'page' );
        if ( ! in_array( $post_type, [ 'page', 'post' ], true ) ) {
            $post_type = 'page';
        }
        $post_status = sanitize_key( $_POST['bbdc_post_status'] ?? 'draft' );
        if ( ! in_array( $post_status, [ 'draft', 'publish' ], true ) ) {
            $post_status = 'draft';
        }

        try {
            $items = ( new BeaverImportParser() )->parse( (string) $upload['tmp_name'], (string) $upload['name'] );
        } catch ( \RuntimeException $e ) {
            wp_die( esc_html__( 'Could not read the file: ', 'jhmg-converter-for-beaver-builder-to-divi' ) . esc_html( $e->getMessage() ), '', [ 'back_link' => true ] );
        }

        $results = ( new BatchImporter() )->import( $items, [ 'post_type' => $post_type, 'post_status' => $post_status ] );

        ( new ReviewPrompt() )->record_run( $results );
        $import_id = wp_generate_uuid4();
        ( new ImportHistory() )->record( $import_id, $results );
        set_transient( 'bbdc_batch_' . $import_id, $results, HOUR_IN_SECONDS );

        wp_safe_redirect( add_query_arg( [ 'page' => self::MENU_SLUG, 'action' => 'batch_result', 'import_id' => $import_id ], admin_url( 'tools.php' ) ) );
        exit;
    }

    private function handle_publish(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jhmg-converter-for-beaver-builder-to-divi' ) );
        }
        $post_id   = absint( wp_unslash( $_GET['post_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked below
        $import_id = sanitize_key( $_GET['import_id'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        check_admin_referer( 'bbdc_publish_' . $post_id );

        if ( $post_id <= 0 || get_post_meta( $post_id, '_bbdc_import_source', true ) === '' ) {
            wp_die( esc_html__( 'That page was not created by this converter.', 'jhmg-converter-for-beaver-builder-to-divi' ) );
        }

        wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ] );
        wp_safe_redirect( add_query_arg( [ 'page' => self::MENU_SLUG, 'action' => 'batch_result', 'import_id' => $import_id ], admin_url( 'tools.php' ) ) );
        exit;
    }

    private function upload_error_message( int $code ): string {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => __( 'File exceeds the server upload limit.', 'jhmg-converter-for-beaver-builder-to-divi' ),
            UPLOAD_ERR_FORM_SIZE  => __( 'File exceeds the form upload limit.', 'jhmg-converter-for-beaver-builder-to-divi' ),
            UPLOAD_ERR_PARTIAL    => __( 'File was only partially uploaded.', 'jhmg-converter-for-beaver-builder-to-divi' ),
            UPLOAD_ERR_NO_FILE    => __( 'No file was selected.', 'jhmg-converter-for-beaver-builder-to-divi' ),
            UPLOAD_ERR_NO_TMP_DIR => __( 'Server is missing a temporary folder.', 'jhmg-converter-for-beaver-builder-to-divi' ),
            UPLOAD_ERR_CANT_WRITE => __( 'Failed to write the file to the server.', 'jhmg-converter-for-beaver-builder-to-divi' ),
            UPLOAD_ERR_EXTENSION  => __( 'Upload stopped by a server extension.', 'jhmg-converter-for-beaver-builder-to-divi' ),
        ];
        /* translators: %d: PHP upload error code */
        return $messages[ $code ] ?? sprintf( __( 'Unknown upload error (code %d).', 'jhmg-converter-for-beaver-builder-to-divi' ), $code );
    }

    // ------------------------------------------------------------------
    // Landing
    // ------------------------------------------------------------------

    private function render_landing(): void {
        $direct = new DirectConversionPage();
        $search = sanitize_text_field( wp_unslash( $_GET['bbdc_s'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $paged  = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $pro    = (bool) apply_filters( 'bbdc_pro_active', false );
        ?>
        <div class="wrap bdc-wrap">
            <h1><?php esc_html_e( 'Beaver Builder to Divi 5 Converter', 'jhmg-converter-for-beaver-builder-to-divi' ); ?></h1>
            <p class="bdc-subtitle"><?php esc_html_e( 'Convert Beaver Builder pages into native Divi 5 layouts. Check any page first, convert it with one click, undo any run.', 'jhmg-converter-for-beaver-builder-to-divi' ); ?></p>

            <?php if ( $pro ) : ?>
                <div class="notice notice-success inline"><p><?php echo wp_kses_post( sprintf(
                    /* translators: %s: Pro tools URL */
                    __( '<strong>Pro is active.</strong> Convert many pages per run, and send Beaver Themer headers and footers to the Divi Theme Builder from <a href="%s">Tools → Beaver Builder → Divi 5 Pro</a>.', 'jhmg-converter-for-beaver-builder-to-divi' ),
                    esc_url( admin_url( 'tools.php?page=bdcp-pro' ) )
                ) ); ?></p></div>
            <?php endif; ?>

            <div class="bdc-card bdc-card--direct">
                <h2><?php esc_html_e( 'Convert a page already on this site', 'jhmg-converter-for-beaver-builder-to-divi' ); ?></h2>
                <?php if ( $direct->has_beaver_content() ) : ?>
                    <p class="description"><?php esc_html_e( 'Pick a Beaver Builder page, check what the conversion will produce, then convert it. Your original page is never modified.', 'jhmg-converter-for-beaver-builder-to-divi' ); ?></p>
                    <?php echo $direct->render_picker( [ 'search' => $search, 'paged' => $paged ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render_picker() ?>
                <?php else : ?>
                    <p class="description"><?php esc_html_e( 'No Beaver Builder pages were found on this site. Upload an export below to convert pages from another site.', 'jhmg-converter-for-beaver-builder-to-divi' ); ?></p>
                <?php endif; ?>
            </div>

            <div class="bdc-card">
                <h2><?php esc_html_e( 'Convert from an exported file', 'jhmg-converter-for-beaver-builder-to-divi' ); ?></h2>
                <p class="description"><?php esc_html_e( 'On the other site, go to Tools → Export and export your pages (or Beaver Builder templates) — Beaver Builder stores the layout inside the export. You can also upload a Beaver Builder template .dat file.', 'jhmg-converter-for-beaver-builder-to-divi' ); ?></p>
                <form method="post" enctype="multipart/form-data" action="" class="bdc-import-form">
                    <?php wp_nonce_field( self::IMPORT_NONCE_ACTION, self::IMPORT_NONCE_NAME ); ?>
                    <input type="hidden" name="action" value="<?php echo esc_attr( self::IMPORT_NONCE_ACTION ); ?>">
                    <div class="bdc-import-fields">
                        <div class="bdc-import-field">
                            <label for="bbdc_import_file"><strong><?php esc_html_e( 'Export file', 'jhmg-converter-for-beaver-builder-to-divi' ); ?></strong></label>
                            <input type="file" id="bbdc_import_file" name="bbdc_import_file" accept=".xml,.dat,.json" required>
                            <p class="description"><?php esc_html_e( 'WordPress export (.xml), Beaver Builder template (.dat) or layout JSON.', 'jhmg-converter-for-beaver-builder-to-divi' ); ?></p>
                        </div>
                        <div class="bdc-import-field">
                            <label for="bbdc_post_type"><strong><?php esc_html_e( 'Create as', 'jhmg-converter-for-beaver-builder-to-divi' ); ?></strong></label>
                            <select id="bbdc_post_type" name="bbdc_post_type">
                                <option value="page"><?php esc_html_e( 'Page', 'jhmg-converter-for-beaver-builder-to-divi' ); ?></option>
                                <option value="post"><?php esc_html_e( 'Post', 'jhmg-converter-for-beaver-builder-to-divi' ); ?></option>
                            </select>
                        </div>
                        <div class="bdc-import-field">
                            <label for="bbdc_post_status"><strong><?php esc_html_e( 'Status', 'jhmg-converter-for-beaver-builder-to-divi' ); ?></strong></label>
                            <select id="bbdc_post_status" name="bbdc_post_status">
                                <option value="draft"><?php esc_html_e( 'Draft (recommended)', 'jhmg-converter-for-beaver-builder-to-divi' ); ?></option>
                                <option value="publish"><?php esc_html_e( 'Published', 'jhmg-converter-for-beaver-builder-to-divi' ); ?></option>
                            </select>
                        </div>
                        <div class="bdc-import-submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Convert Now', 'jhmg-converter-for-beaver-builder-to-divi' ); ?></button></div>
                    </div>
                    <?php if ( ! $pro ) : ?>
                        <p class="description bdc-free-notice"><?php esc_html_e( 'Free converts the first page in the file. Pro converts every page in it.', 'jhmg-converter-for-beaver-builder-to-divi' ); ?></p>
                    <?php endif; ?>
                </form>
            </div>

            <?php if ( ! $pro ) : ?>
            <div class="bdc-card bdc-card--pro">
                <span class="bdc-badge-pro"><?php esc_html_e( 'PRO', 'jhmg-converter-for-beaver-builder-to-divi' ); ?></span>
                <h2><?php esc_html_e( 'Migrate the whole site', 'jhmg-converter-for-beaver-builder-to-divi' ); ?></h2>
                <ul class="bdc-features">
                    <li><?php esc_html_e( 'Convert as many pages as you like in one run — from this site or from one export file', 'jhmg-converter-for-beaver-builder-to-divi' ); ?></li>
                    <li><?php esc_html_e( 'Send Beaver Themer headers and footers straight into the Divi Theme Builder', 'jhmg-converter-for-beaver-builder-to-divi' ); ?></li>
                    <li><?php esc_html_e( 'Priority support and regular updates', 'jhmg-converter-for-beaver-builder-to-divi' ); ?></li>
                </ul>
                <p><a class="button button-primary" href="<?php echo esc_url( self::PRO_URL ); ?>" target="_blank" rel="noopener"><?php
                    /* translators: %s: Pro price, e.g. $25/yr */
                    echo esc_html( sprintf( __( 'Get Pro — %s, unlimited sites', 'jhmg-converter-for-beaver-builder-to-divi' ), self::PRO_PRICE ) );
                ?></a></p>
            </div>
            <?php endif; ?>

            <?php ( new CoveragePanel() )->render(); ?>
        </div>
        <?php
    }

    // ------------------------------------------------------------------
    // Batch result
    // ------------------------------------------------------------------

    private function render_batch_result(): void {
        $import_id = sanitize_key( $_GET['import_id'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $import_id === '' ) {
            wp_die( esc_html__( 'No run ID provided.', 'jhmg-converter-for-beaver-builder-to-divi' ) );
        }
        $results = get_transient( 'bbdc_batch_' . $import_id );
        if ( ! is_array( $results ) ) {
            wp_die( esc_html__( 'Results not found or expired. Results are kept for one hour; the run itself is still listed under Recent conversions.', 'jhmg-converter-for-beaver-builder-to-divi' ) );
        }

        $review = new ReviewPrompt();
        echo '<div class="wrap bdc-wrap"><h1>' . esc_html__( 'Conversion results', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</h1>';
        if ( $review->should_ask( $results ) ) {
            echo $review->markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in markup()
        }
        echo '<div class="bdc-result-actions"><a href="' . esc_url( admin_url( 'tools.php?page=' . self::MENU_SLUG ) ) . '" class="button">&larr; ' . esc_html__( 'Back to converter', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</a></div>';
        echo self::batch_table( $results, $import_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in batch_table()
        echo '</div>';
    }

    /** The results table. Public and returning a string so it can be tested. */
    public static function batch_table( array $results, string $import_id ): string {
        $real      = array_filter( $results, static fn( $r ) => empty( $r['skipped'] ) );
        $succeeded = count( array_filter( $real, static fn( $r ) => ! empty( $r['success'] ) ) );
        $failed    = count( $real ) - $succeeded;

        $html = '<div class="bdc-batch-summary">';
        /* translators: %d: number of pages processed */
        $html .= '<span class="bdc-summary-stat bdc-summary-stat--total">' . esc_html( sprintf( __( '%d page(s) processed', 'jhmg-converter-for-beaver-builder-to-divi' ), count( $real ) ) ) . '</span>';
        if ( $succeeded > 0 ) {
            /* translators: %d: number of successfully converted pages */
            $html .= '<span class="bdc-summary-stat bdc-summary-stat--ok">' . esc_html( sprintf( __( '%d converted', 'jhmg-converter-for-beaver-builder-to-divi' ), $succeeded ) ) . '</span>';
        }
        if ( $failed > 0 ) {
            /* translators: %d: number of pages that failed */
            $html .= '<span class="bdc-summary-stat bdc-summary-stat--fail">' . esc_html( sprintf( __( '%d failed', 'jhmg-converter-for-beaver-builder-to-divi' ), $failed ) ) . '</span>';
        }
        $html .= '</div>';

        $html .= '<table class="wp-list-table widefat fixed striped bdc-batch-table"><thead><tr>'
            . '<th class="column-title column-primary">' . esc_html__( 'Title', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</th>'
            . '<th class="column-status">' . esc_html__( 'Status', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</th>'
            . '<th class="column-issues">' . esc_html__( 'Issues', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</th>'
            . '<th class="column-actions">' . esc_html__( 'Actions', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</th></tr></thead><tbody>';

        foreach ( $results as $result ) {
            $report     = $result['report'] ?? [];
            $warn_count = count( $report['warnings'] ?? [] ) + count( $result['unsupported'] ?? [] ) + count( $report['skipped_settings'] ?? [] )
                + count( $report['unresolved_globals'] ?? [] ) + count( $report['not_carried_over'] ?? [] ) + count( $report['approximate_matches'] ?? [] );

            $html .= '<tr><td class="column-title column-primary"><strong>' . esc_html( $result['title'] ?: __( '(no title)', 'jhmg-converter-for-beaver-builder-to-divi' ) ) . '</strong></td>';

            $html .= '<td class="column-status">';
            if ( ! empty( $result['skipped'] ) ) {
                $html .= '<span class="bdc-status bdc-status--skipped">' . esc_html__( 'Not converted', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</span><br><small class="bdc-error-msg">' . esc_html( (string) ( $result['error'] ?? '' ) ) . '</small>';
            } elseif ( ! empty( $result['success'] ) ) {
                $html .= '<span class="bdc-status bdc-status--converted">&#10003; ' . esc_html__( 'Converted', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</span>';
            } else {
                $html .= '<span class="bdc-status bdc-status--error">&#10007; ' . esc_html__( 'Failed', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</span>';
                if ( ! empty( $result['error'] ) ) {
                    $html .= '<br><small class="bdc-error-msg">' . esc_html( $result['error'] ) . '</small>';
                }
            }
            $html .= '</td>';

            $html .= '<td class="column-issues">';
            if ( ! empty( $result['success'] ) && $warn_count > 0 ) {
                $html .= '<details class="bdc-issues-details"><summary class="bdc-issues-summary"><span class="bdc-badge bdc-badge--warn">' . (int) $warn_count . '</span> ' . esc_html__( 'issues', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</summary><ul class="bdc-issues-list">';
                foreach ( $report['warnings'] ?? [] as $warning ) {
                    $html .= '<li class="bdc-issue bdc-issue--warn">' . esc_html( $warning ) . '</li>';
                }
                foreach ( $result['unsupported'] ?? [] as $item ) {
                    $html .= '<li class="bdc-issue bdc-issue--unsupported">' . esc_html__( 'Unsupported:', 'jhmg-converter-for-beaver-builder-to-divi' ) . ' <code>' . esc_html( $item['module'] ?? $item['type'] ?? 'unknown' ) . '</code></li>';
                }
                foreach ( $report['skipped_settings'] ?? [] as $setting ) {
                    $html .= '<li class="bdc-issue bdc-issue--skipped">' . esc_html__( 'Skipped:', 'jhmg-converter-for-beaver-builder-to-divi' ) . ' <code>' . esc_html( $setting ) . '</code></li>';
                }
                $html .= '</ul>' . NotCarriedOverRenderer::render( $report['not_carried_over'] ?? [], $report['approximate_matches'] ?? [], $report['unresolved_globals'] ?? [], $report['addon_settings_ignored'] ?? [] ) . '</details>';
            } elseif ( ! empty( $result['success'] ) ) {
                $html .= '<span class="bdc-status--clean">&#10003; ' . esc_html__( 'Clean', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</span>';
            } else {
                $html .= '&mdash;';
            }
            $html .= '</td>';

            $html .= '<td class="column-actions">';
            if ( ! empty( $result['success'] ) && (int) ( $result['post_id'] ?? 0 ) > 0 ) {
                $post_id = (int) $result['post_id'];
                $html   .= '<a href="' . esc_url( self::edit_link( $post_id ) ) . '" class="button button-small">' . esc_html__( 'Edit', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</a> ';
                $html   .= '<a href="' . esc_url( self::view_link( $post_id ) ) . '" class="button button-small" target="_blank" rel="noopener">' . esc_html__( 'View', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</a> ';
                if ( self::post_status( $post_id ) !== 'publish' ) {
                    $publish_url = add_query_arg( [ 'page' => self::MENU_SLUG, 'action' => 'batch_result', 'import_id' => $import_id, 'bbdc_action' => 'publish', 'post_id' => $post_id ], admin_url( 'tools.php' ) ) . '&_wpnonce=' . wp_create_nonce( 'bbdc_publish_' . $post_id );
                    $html       .= '<a href="' . esc_url( $publish_url ) . '" class="button button-small button-primary">' . esc_html__( 'Publish', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</a>';
                } else {
                    $html .= '<span class="bdc-published-label">&#10003; ' . esc_html__( 'Published', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</span>';
                }
            } else {
                $html .= '&mdash;';
            }
            $html .= '</td></tr>';
        }

        return $html . '</tbody></table>';
    }

    private static function edit_link( int $post_id ): string {
        return function_exists( 'get_edit_post_link' ) ? (string) get_edit_post_link( $post_id, 'raw' ) : admin_url( 'post.php?post=' . $post_id . '&action=edit' );
    }

    private static function view_link( int $post_id ): string {
        return function_exists( 'get_permalink' ) ? (string) get_permalink( $post_id ) : home_url( '/?p=' . $post_id );
    }

    private static function post_status( int $post_id ): string {
        if ( function_exists( 'get_post_status' ) ) {
            return (string) get_post_status( $post_id );
        }
        $post = get_post( $post_id );
        return (string) ( $post->post_status ?? '' );
    }

    // ------------------------------------------------------------------
    // Direct report ("Check this page")
    // ------------------------------------------------------------------

    private function render_direct_report(): void {
        $ids = get_transient( DirectConversionPage::PLAN_IDS_TRANSIENT_PREFIX . get_current_user_id() );
        if ( ! is_array( $ids ) || empty( $ids ) ) {
            wp_die( esc_html__( 'No checked selection found or it has expired. Please pick a page again.', 'jhmg-converter-for-beaver-builder-to-divi' ) );
        }
        $direct = new DirectConversionPage();
        $plan   = $direct->plan_for( $ids );

        echo '<div class="wrap bdc-wrap"><h1>' . esc_html__( 'Beaver Builder to Divi 5 Converter', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</h1>';
        echo '<div class="bdc-result-actions"><a href="' . esc_url( admin_url( 'tools.php?page=' . self::MENU_SLUG ) ) . '" class="button">&larr; ' . esc_html__( 'Back to converter', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</a></div>';
        echo $direct->render_report( $plan ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render_report()
        echo '</div>';
    }

    // ------------------------------------------------------------------
    // Styles
    // ------------------------------------------------------------------

    private function inline_css(): string {
        return '
.bdc-wrap { max-width: 1200px; }
.bdc-wrap h1 { font-size: 26px; font-weight: 700; color: #1e293b; }
.bdc-subtitle { color: #475569; font-size: 15px; margin: 4px 0 20px; }
.bdc-card { background: #fff; border: 1px solid #dcdcde; border-radius: 10px; padding: 20px 24px; margin: 0 0 20px; box-shadow: 0 1px 6px rgba(0,0,0,.05); }
.bdc-card h2 { margin: 0 0 8px; font-size: 16px; font-weight: 700; color: #1e293b; }
.bdc-card > p.description { margin: 0 0 14px; color: #64748b; }
.bdc-card--direct { border-top: 4px solid #22c55e; }
.bdc-card--pro { border-top: 4px solid #7c3aed; }
.bdc-card--success { border-left: 4px solid #2e7d32; }
.bdc-badge-pro { display: inline-block; background: #ede9fe; color: #6d28d9; border-radius: 99px; padding: 2px 10px; font-size: 10px; font-weight: 700; letter-spacing: .07em; }
.bdc-features { margin: 8px 0 12px 18px; list-style: disc; color: #334155; }
.bdc-direct-search { margin-bottom: 12px; }
.bdc-direct-search input[type="search"] { min-width: 240px; margin-right: 6px; }
.bdc-direct-table td { padding: 10px 12px; }
.bdc-direct-meta { color: #64748b; font-size: 12px; margin-left: 6px; }
.bdc-badge-converted { display: inline-block; background: #dcfce7; color: #15803d; border-radius: 10px; padding: 1px 8px; font-size: 11px; font-weight: 700; margin-left: 6px; }
.bdc-direct-pager a { margin-right: 6px; }
.bdc-direct-empty { color: #64748b; }
.bdc-direct-report h3 { margin: 20px 0 6px; }
.bdc-direct-unsupported { color: #7a4f00; }
.bdc-outline { list-style: none; margin: 0 0 10px; padding-left: 16px; }
.bdc-outline .bdc-outline { margin-top: 4px; }
.bdc-outline-node { margin-bottom: 4px; font-size: 13px; }
.bdc-outline-node--placeholder > .bdc-outline-label { color: #c62828; }
.bdc-outline-empty { color: #64748b; }
.bdc-import-fields { display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-end; }
.bdc-import-field { display: flex; flex-direction: column; gap: 4px; }
.bdc-import-field .description { margin: 4px 0 0; font-size: 11px; color: #757575; }
.bdc-free-notice { color: #946f00; margin-top: 10px; }
.bdc-batch-summary { display: flex; gap: 12px; margin: 16px 0 20px; flex-wrap: wrap; }
.bdc-summary-stat { display: inline-flex; align-items: center; padding: 6px 14px; border-radius: 3px; font-weight: 600; font-size: 13px; }
.bdc-summary-stat--total { background: #f0f0f1; color: #3c434a; }
.bdc-summary-stat--ok { background: #d1e7dd; color: #0a3622; }
.bdc-summary-stat--fail { background: #f8d7da; color: #58151c; }
.bdc-batch-table .column-status { width: 160px; }
.bdc-batch-table .column-issues { width: 260px; }
.bdc-batch-table .column-actions { width: 220px; }
.bdc-status { font-weight: 600; }
.bdc-status--converted, .bdc-status--clean { color: #2e7d32; }
.bdc-status--error { color: #c62828; }
.bdc-status--skipped { color: #946f00; }
.bdc-error-msg { color: #c62828; font-size: 11px; }
.bdc-badge { display: inline-block; background: #f0b429; color: #7a4f00; border-radius: 10px; padding: 1px 7px; font-size: 11px; font-weight: 700; vertical-align: middle; }
.bdc-issues-details summary { cursor: pointer; }
.bdc-issues-list { margin: 8px 0 0; padding-left: 14px; font-size: 12px; }
.bdc-issues-list li { margin-bottom: 4px; line-height: 1.4; }
.bdc-issue--warn { color: #7a4f00; }
.bdc-issue--unsupported { color: #c62828; }
.bdc-issue--skipped { color: #555; }
.bdc-not-carried { margin: 10px 0 0; padding: 10px 12px; border-left: 3px solid #c62828; background: #fdf6f6; }
.bdc-not-carried h3 { margin: 0 0 6px; font-size: 13px; }
.bdc-not-carried-label { margin: 8px 0 2px; font-size: 12px; font-weight: 400; }
.bdc-not-carried-list { margin: 0 0 6px; padding-left: 16px; font-size: 12px; }
.bdc-published-label { color: #2e7d32; font-size: 12px; font-weight: 600; }
.bdc-result-actions { display: flex; gap: 8px; margin: 16px 0 24px; flex-wrap: wrap; }
.bdc-review-prompt { border-left: 4px solid #2271b1; }
        ';
    }
}
