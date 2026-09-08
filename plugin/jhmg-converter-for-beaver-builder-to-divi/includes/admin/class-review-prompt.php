<?php
/**
 * Asks for a wordpress.org review, but only once the plugin has demonstrably
 * done its job: the counter moves in the conversion handler (never on a screen
 * a refresh can inflate), and a run with any failure suppresses the ask.
 */

namespace BeaverDivi5Converter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ReviewPrompt {

    const OPTION_COUNT      = 'bdc_conversions_total';
    const USER_META_KEY     = 'bdc_review_prompt_state';
    const STATE_DONE        = 'done';
    const STATE_SNOOZED     = 'snoozed:';
    const QUERY_ACTION      = 'bdc_review_action';
    const NONCE_ACTION      = 'bdc_review_prompt';
    const DEFAULT_THRESHOLD = 3;
    const SNOOZE_DAYS       = 14;
    const REVIEW_URL        = 'https://wordpress.org/support/plugin/jhmg-converter-for-beaver-builder-to-divi/reviews/#new-post';

    private string $today;

    public function __construct( ?string $today = null ) {
        $this->today = $today ?? gmdate( 'Y-m-d' );
    }

    public function init(): void {
        add_action( 'admin_init', [ $this, 'maybe_handle_response' ] );
    }

    /** Adds this run's successes to the running total. Call once per run. */
    public function record_run( array $results ): void {
        $succeeded = count( array_filter( $results, static fn( $r ) => ! empty( $r['success'] ) ) );
        if ( $succeeded < 1 ) {
            return;
        }
        update_option( self::OPTION_COUNT, $this->conversion_count() + $succeeded );
    }

    public function conversion_count(): int {
        return (int) get_option( self::OPTION_COUNT, 0 );
    }

    public function threshold(): int {
        return (int) apply_filters( 'bdc_review_prompt_threshold', self::DEFAULT_THRESHOLD );
    }

    /** @param array $results The run being displayed, so a failed run can veto the ask. */
    public function should_ask( array $results ): bool {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        if ( $this->conversion_count() < $this->threshold() ) {
            return false;
        }
        foreach ( $results as $r ) {
            if ( empty( $r['success'] ) && empty( $r['skipped'] ) ) {
                return false;
            }
        }
        return $this->state_allows_asking();
    }

    private function state_allows_asking(): bool {
        $state = (string) get_user_meta( get_current_user_id(), self::USER_META_KEY, true );
        if ( $state === self::STATE_DONE ) {
            return false;
        }
        if ( str_starts_with( $state, self::STATE_SNOOZED ) ) {
            return $this->today >= substr( $state, strlen( self::STATE_SNOOZED ) );
        }
        return true;
    }

    public function set_state( string $state ): void {
        update_user_meta( get_current_user_id(), self::USER_META_KEY, $state );
    }

    public function snooze(): void {
        $this->set_state( self::STATE_SNOOZED . gmdate( 'Y-m-d', strtotime( $this->today . ' +' . self::SNOOZE_DAYS . ' days' ) ) );
    }

    public function maybe_handle_response(): void {
        if ( empty( $_GET[ self::QUERY_ACTION ] ) ) {
            return;
        }
        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
            return;
        }
        switch ( sanitize_key( wp_unslash( $_GET[ self::QUERY_ACTION ] ) ) ) {
            case 'later':
                $this->snooze();
                break;
            case 'done':
                $this->set_state( self::STATE_DONE );
                break;
            case 'review':
                $this->set_state( self::STATE_DONE );
                $this->redirect_to_review();
                break;
        }
    }

    /** Separated so maybe_handle_response() stays testable. */
    protected function redirect_to_review(): void {
        wp_redirect( self::REVIEW_URL ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- off-host target is deliberate
        exit;
    }

    public function markup(): string {
        $headline = sprintf(
            /* translators: %d: number of pages converted so far */
            _n( 'Converted %d page so far.', 'Converted %d pages so far.', $this->conversion_count(), 'jhmg-converter-for-beaver-builder-to-divi' ),
            $this->conversion_count()
        );

        return sprintf(
            '<div class="bdc-card bdc-review-prompt"><p><strong>%1$s</strong> %2$s</p><p><a class="button button-primary" href="%3$s">%4$s</a> <a class="button" href="%5$s">%6$s</a> <a class="button-link" href="%7$s">%8$s</a></p></div>',
            esc_html( $headline ),
            esc_html__( 'If this saved you some time, a quick review on WordPress.org helps other people find it.', 'jhmg-converter-for-beaver-builder-to-divi' ),
            esc_url( $this->response_url( 'review' ) ),
            esc_html__( 'Leave a review', 'jhmg-converter-for-beaver-builder-to-divi' ),
            esc_url( $this->response_url( 'later' ) ),
            esc_html__( 'Maybe later', 'jhmg-converter-for-beaver-builder-to-divi' ),
            esc_url( $this->response_url( 'done' ) ),
            esc_html__( "Don't ask again", 'jhmg-converter-for-beaver-builder-to-divi' )
        );
    }

    private function response_url( string $action ): string {
        return add_query_arg( self::QUERY_ACTION, $action ) . '&_wpnonce=' . wp_create_nonce( self::NONCE_ACTION );
    }
}
