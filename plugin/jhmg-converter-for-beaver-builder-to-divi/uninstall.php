<?php
/**
 * Fires on plugin deletion (not deactivation). Removes the plugin's options.
 * Converted pages are the user's content and are left alone.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

foreach ( [ 'bdc_import_history', 'bdc_telemetry_consent', 'bdc_telemetry_last_sent', 'bdc_divi_requirement_failed', 'bdc_conversions_total' ] as $option ) {
    delete_option( $option );
}
