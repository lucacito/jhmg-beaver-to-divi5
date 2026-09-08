<?php
/**
 * Creates a page from one of Beaver Builder's own bundled layout templates
 * (the .dat packs under the plugin's data/ directory), so the e2e suite can
 * exercise real Beaver Builder content.
 *
 * Usage: TEMPLATE=layout-38-Contact-lite wp eval-file /tmp/import-bb-template.php --allow-root
 * Prints the new page id.
 */
$template = getenv( 'TEMPLATE' ) ?: 'layout-38-Contact-lite';
$path     = WP_PLUGIN_DIR . '/beaver-builder-lite-version/data/' . $template . '.dat';
if ( ! file_exists( $path ) ) {
    fwrite( STDERR, "Template not found: {$path}\n" );
    exit( 1 );
}

$data = unserialize( (string) file_get_contents( $path ), [ 'allowed_classes' => [ 'stdClass' ] ] );
$tpl  = $data['layout'][0] ?? null;
if ( ! is_object( $tpl ) || empty( $tpl->nodes ) ) {
    fwrite( STDERR, "Template has no nodes\n" );
    exit( 1 );
}

$page_id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => (string) $tpl->name . ' (Beaver Builder)' ] );
update_post_meta( $page_id, '_fl_builder_enabled', true );
update_post_meta( $page_id, '_fl_builder_data', $tpl->nodes );
update_post_meta( $page_id, '_fl_builder_draft', $tpl->nodes );
update_post_meta( $page_id, '_fl_builder_data_settings', $tpl->settings ?? new stdClass() );

echo $page_id;
