<?php
/** Fires on plugin deletion. Removes the Pro add-on's own options; converted layouts are left alone. */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

foreach ( [ 'bdcp_license_key', 'bdcp_license_state', 'bdcp_update_blocked' ] as $option ) {
    delete_option( $option );
}
