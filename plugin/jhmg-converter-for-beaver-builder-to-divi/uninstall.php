<?php
/**
 * Fires on plugin deletion (not deactivation). Removes the plugin's options.
 * Converted pages are the user's content and are left alone.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

array_map(
    'delete_option',
    [ 'bbdc_import_history', 'bbdc_telemetry_consent', 'bbdc_telemetry_last_sent', 'bbdc_divi_requirement_failed', 'bbdc_conversions_total' ]
);
