<?php
/**
 * Tools → Beaver Builder → Divi 5 Pro: the Themer tab (send header/footer
 * layouts to the Divi Theme Builder) and the License tab.
 *
 * Every identifier is bdcp_-prefixed: free's AdminPage dispatches on the POST
 * action / GET query params unscoped, so Pro's must never collide.
 */

namespace BeaverDivi5Converter\Pro\Admin;

use BeaverDivi5Converter\Admin\AdminPage;
use BeaverDivi5Converter\Admin\BatchImporter;
use BeaverDivi5Converter\Conversion\ConversionPreflight;
use BeaverDivi5Converter\Conversion\InstalledPostSource;
use BeaverDivi5Converter\Helpers\DiviRequirement;
use BeaverDivi5Converter\History\ImportHistory;
use BeaverDivi5Converter\Pro\Licensing\LicenseClient;
use BeaverDivi5Converter\Pro\Licensing\LicensePage;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ProPage {

    const MENU_SLUG      = 'bdcp-pro';
    const THEMER_ACTION  = 'bdcp_convert_themer';
    const THEMER_NONCE   = 'bdcp_convert_themer_nonce';

    private LicensePage $licensePage;
    private ThemerRepository $themer;

    public function __construct( private LicenseClient $license, ?ThemerRepository $themer = null ) {
        $this->licensePage = new LicensePage( $license );
        $this->themer      = $themer ?? new ThemerRepository();
    }

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_init', [ $this, 'handle_post' ] );
    }

    public function register_menu(): void {
        add_management_page(
            __( 'Beaver Builder to Divi 5 Pro', 'jhmg-converter-for-beaver-builder-to-divi-pro' ),
            __( 'Beaver Builder → Divi 5 Pro', 'jhmg-converter-for-beaver-builder-to-divi-pro' ),
            'manage_options',
            self::MENU_SLUG,
            [ $this, 'render_page' ]
        );
    }

    public function handle_post(): void {
        $page = sanitize_key( $_GET['page'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $page !== self::MENU_SLUG ) {
            return;
        }
        $this->licensePage->handle_post();

        $action = sanitize_key( $_POST['action'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified below
        if ( $action === self::THEMER_ACTION && DiviRequirement::is_satisfied() ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Insufficient permissions.', 'jhmg-converter-for-beaver-builder-to-divi-pro' ) );
            }
            check_admin_referer( self::THEMER_ACTION, self::THEMER_NONCE );
            $this->handle_themer( wp_unslash( $_POST ) );
        }
    }

    /** @return int[] Themer layout ids that exist and are header/footer layouts. */
    public function verified_layout_ids( array $request ): array {
        $allowed = array_column( $this->themer->find(), 'id' );
        $raw     = $request['bdcp_layout_ids'] ?? [];
        $ids     = [];
        foreach ( (array) $raw as $value ) {
            if ( is_scalar( $value ) && ctype_digit( (string) $value ) && in_array( (int) $value, $allowed, true ) && ! in_array( (int) $value, $ids, true ) ) {
                $ids[] = (int) $value;
            }
        }
        return $ids;
    }

    /** Converts the layouts through the same pipeline as pages; the committer routes header/footer to the Theme Builder. */
    public function convert_layouts( array $ids ): array {
        $plan = ( new ConversionPreflight() )->run( new InstalledPostSource( $ids ) );
        return ( new BatchImporter() )->importPlan( $plan );
    }

    protected function handle_themer( array $request ): void {
        $ids = $this->verified_layout_ids( $request );
        if ( empty( $ids ) ) {
            wp_die( esc_html__( 'Pick at least one header or footer layout.', 'jhmg-converter-for-beaver-builder-to-divi-pro' ) );
        }
        $results   = $this->convert_layouts( $ids );
        $import_id = wp_generate_uuid4();
        ( new ImportHistory() )->record( $import_id, $results );
        set_transient( 'bdc_batch_' . $import_id, $results, HOUR_IN_SECONDS );
        $this->redirect( add_query_arg( [ 'page' => AdminPage::MENU_SLUG, 'action' => 'batch_result', 'import_id' => $import_id ], admin_url( 'tools.php' ) ) );
    }

    protected function redirect( string $location ): void {
        wp_safe_redirect( $location );
        exit;
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'jhmg-converter-for-beaver-builder-to-divi-pro' ) );
        }
        $tab = sanitize_key( $_GET['tab'] ?? 'themer' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        echo $this->markup( $tab ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in markup()
    }

    public function markup( string $tab = 'themer' ): string {
        $td   = 'jhmg-converter-for-beaver-builder-to-divi-pro';
        $base = admin_url( 'tools.php?page=' . self::MENU_SLUG );
        $html = '<div class="wrap bdc-wrap"><h1>' . esc_html__( 'Beaver Builder to Divi 5 Pro', $td ) . '</h1>';
        $html .= $this->notice_markup();
        $html .= '<h2 class="nav-tab-wrapper">';
        foreach ( [ 'themer' => __( 'Themer headers & footers', $td ), 'license' => __( 'Licence', $td ) ] as $slug => $label ) {
            $html .= '<a class="nav-tab' . ( $tab === $slug ? ' nav-tab-active' : '' ) . '" href="' . esc_url( $base . '&tab=' . $slug ) . '">' . esc_html( $label ) . '</a>';
        }
        $html .= '</h2>';
        $html .= $tab === 'license' ? $this->licensePage->markup() : $this->themer_markup();
        return $html . '</div>';
    }

    private function notice_markup(): string {
        $notice = sanitize_key( $_GET['bdcp_notice'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $td     = 'jhmg-converter-for-beaver-builder-to-divi-pro';
        $map    = [
            'license_activated'   => [ 'success', __( 'Licence activated.', $td ) ],
            'license_deactivated' => [ 'info', __( 'Licence deactivated on this site.', $td ) ],
            'license_refreshed'   => [ 'info', __( 'Licence status refreshed.', $td ) ],
            'license_error'       => [ 'error', __( 'The licence could not be activated.', $td ) ],
        ];
        if ( ! isset( $map[ $notice ] ) ) {
            return '';
        }
        [ $class, $text ] = $map[ $notice ];
        $error = sanitize_key( $_GET['bdcp_error'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return '<div class="notice notice-' . esc_attr( $class ) . ' inline"><p>' . esc_html( $text ) . ( $error !== '' ? ' <code>' . esc_html( $error ) . '</code>' : '' ) . '</p></div>';
    }

    public function themer_markup(): string {
        $td   = 'jhmg-converter-for-beaver-builder-to-divi-pro';
        $rows = $this->themer->find();
        $html = '<div class="bdc-card"><h2>' . esc_html__( 'Send Beaver Themer headers and footers to the Divi Theme Builder', $td ) . '</h2>';
        $html .= '<p class="description">' . esc_html__( 'Each layout becomes a Divi Theme Builder global header or footer. Converting the same layout again updates the previous result instead of adding another. Assign display conditions in Divi → Theme Builder afterwards.', $td ) . '</p>';

        if ( ! DiviRequirement::is_satisfied() ) {
            return $html . '<div class="notice notice-error inline"><p>' . esc_html( DiviRequirement::message() ) . '</p></div></div>';
        }
        if ( empty( $rows ) ) {
            return $html . '<p class="bdc-direct-empty">' . esc_html__( 'No Beaver Themer header or footer layouts were found on this site.', $td ) . '</p></div>';
        }

        $html .= '<form method="post">' . wp_nonce_field( self::THEMER_ACTION, self::THEMER_NONCE, true, false );
        $html .= '<input type="hidden" name="action" value="' . esc_attr( self::THEMER_ACTION ) . '"><table class="widefat bdc-direct-table"><tbody>';
        foreach ( $rows as $row ) {
            $html .= '<tr><td><label><input type="checkbox" name="bdcp_layout_ids[]" value="' . esc_attr( (string) $row['id'] ) . '"> <strong>' . esc_html( $row['title'] ) . '</strong></label> <span class="bdc-direct-meta">' . esc_html( $row['layout_type'] . ' · ' . $row['status'] ) . '</span></td></tr>';
        }
        $html .= '</tbody></table><p><button type="submit" class="button button-primary">' . esc_html__( 'Convert into the Divi Theme Builder', $td ) . '</button></p></form>';
        return $html . '</div>';
    }
}
