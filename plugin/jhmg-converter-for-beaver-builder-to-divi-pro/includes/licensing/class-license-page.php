<?php

namespace BeaverDivi5Converter\Pro\Licensing;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The License tab: activate, deactivate, re-check. Soft enforcement — this
 * only informs the site owner about licence and update status; it never
 * disables a conversion feature.
 */
class LicensePage {

    const NONCE_NAME        = 'bdcp_license_nonce';
    const NONCE_ACTION      = 'bdcp_license';
    const SAVE_ACTION       = 'bdcp_save_license';
    const DEACTIVATE_ACTION = 'bdcp_deactivate_license';
    const REFRESH_ACTION    = 'bdcp_refresh_license';

    public function __construct( private LicenseClient $license ) {}

    public function maybe_render_notice(): void {
        $this->license->status_notice();
    }

    /** Called from ProPage::handle_post(). */
    public function handle_post(): void {
        $action = sanitize_key( $_POST['action'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked per branch
        if ( $action === self::SAVE_ACTION ) {
            $this->handle_activate();
            return;
        }
        $bdcp_action = sanitize_key( $_GET['bdcp_action'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $bdcp_action === 'deactivate_license' ) {
            $this->handle_deactivate();
        } elseif ( $bdcp_action === 'refresh_license' ) {
            $this->handle_refresh();
        }
    }

    private function handle_activate(): void {
        $this->require_admin();
        check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );
        $key    = sanitize_text_field( wp_unslash( $_POST['bdcp_license_key'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $result = $key !== '' ? $this->license->activate( $key ) : [ 'ok' => false, 'error' => 'invalid_request' ];
        $this->redirect( [ 'bdcp_notice' => $result['ok'] ? 'license_activated' : 'license_error', 'bdcp_error' => $result['ok'] ? '' : (string) ( $result['error'] ?? 'unknown' ) ] );
    }

    private function handle_deactivate(): void {
        $this->require_admin();
        check_admin_referer( self::DEACTIVATE_ACTION );
        $this->license->deactivate();
        $this->redirect( [ 'bdcp_notice' => 'license_deactivated' ] );
    }

    private function handle_refresh(): void {
        $this->require_admin();
        check_admin_referer( self::REFRESH_ACTION );
        $this->license->refresh( true );
        $this->redirect( [ 'bdcp_notice' => 'license_refreshed' ] );
    }

    private function require_admin(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'jhmg-converter-for-beaver-builder-to-divi-pro' ) );
        }
    }

    protected function redirect( array $args ): void {
        wp_safe_redirect( add_query_arg( array_merge( [ 'page' => \BeaverDivi5Converter\Pro\Admin\ProPage::MENU_SLUG, 'tab' => 'license' ], $args ), admin_url( 'tools.php' ) ) );
        exit;
    }

    /** The tab body. Returns a string. */
    public function markup(): string {
        $key    = $this->license->get_key();
        $state  = $this->license->get_state();
        $status = (string) ( $state['status'] ?? '' );
        $td     = 'jhmg-converter-for-beaver-builder-to-divi-pro';
        $base   = admin_url( 'tools.php?page=' . \BeaverDivi5Converter\Pro\Admin\ProPage::MENU_SLUG . '&tab=license' );

        $html = '<div class="bdc-card"><h2>' . esc_html__( 'Licence', $td ) . '</h2>';
        $html .= '<p class="description">' . esc_html__( 'Your licence unlocks automatic updates and support. Conversion features work regardless.', $td ) . '</p>';

        if ( $key ) {
            $html .= '<p><strong>' . esc_html__( 'Status:', $td ) . '</strong> ' . esc_html( $status !== '' ? $status : 'unknown' );
            if ( ! empty( $state['expires'] ) ) {
                $html .= ' — ' . esc_html( sprintf( /* translators: %s: expiry date */ __( 'expires %s', $td ), (string) $state['expires'] ) );
            }
            $html .= '</p><p><code>' . esc_html( substr( $key, 0, 4 ) . str_repeat( '•', max( 0, strlen( $key ) - 8 ) ) . substr( $key, -4 ) ) . '</code></p>';
            $html .= '<p><a class="button" href="' . esc_url( $base . '&bdcp_action=refresh_license&_wpnonce=' . wp_create_nonce( self::REFRESH_ACTION ) ) . '">' . esc_html__( 'Check again', $td ) . '</a> ';
            $html .= '<a class="button-link-delete" href="' . esc_url( $base . '&bdcp_action=deactivate_license&_wpnonce=' . wp_create_nonce( self::DEACTIVATE_ACTION ) ) . '">' . esc_html__( 'Deactivate on this site', $td ) . '</a></p>';
        } else {
            $html .= '<form method="post">' . wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME, true, false );
            $html .= '<input type="hidden" name="action" value="' . esc_attr( self::SAVE_ACTION ) . '">';
            $html .= '<p><label for="bdcp_license_key"><strong>' . esc_html__( 'Licence key', $td ) . '</strong></label><br><input type="text" id="bdcp_license_key" name="bdcp_license_key" class="regular-text" required></p>';
            $html .= '<p><button type="submit" class="button button-primary">' . esc_html__( 'Activate', $td ) . '</button></p></form>';
        }

        return $html . '</div>';
    }
}
