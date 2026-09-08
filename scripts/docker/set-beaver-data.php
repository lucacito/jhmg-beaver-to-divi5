<?php
/**
 * Attaches a Beaver Builder layout fixture to a page, exactly as Beaver
 * Builder itself would store it (array of stdClass in _fl_builder_data).
 *
 * Usage: FIXTURE=beaver/heading PAGE_ID=<id> wp eval-file /tmp/set-beaver-data.php --allow-root
 *   FIXTURE is relative to /var/www/html/fixtures/, without .json.
 *   JSON_PATH=/tmp/file.json can be used instead of FIXTURE.
 */
$page_id   = (int) getenv( 'PAGE_ID' );
$fixture   = getenv( 'FIXTURE' );
$json_path = getenv( 'JSON_PATH' );

if ( ! $page_id || ( ! $fixture && ! $json_path ) ) {
    fwrite( STDERR, "PAGE_ID and FIXTURE (or JSON_PATH) must be set\n" );
    exit( 1 );
}

$file = $json_path ?: ABSPATH . 'fixtures/' . $fixture . '.json';
if ( ! file_exists( $file ) ) {
    fwrite( STDERR, "Fixture not found: {$file}\n" );
    exit( 1 );
}

$decoded = json_decode( (string) file_get_contents( $file ) );
if ( ! is_object( $decoded ) && ! is_array( $decoded ) ) {
    fwrite( STDERR, "Fixture is not valid JSON: {$file}\n" );
    exit( 1 );
}

$nodes = isset( $decoded->nodes ) ? (array) $decoded->nodes : (array) $decoded;
foreach ( $nodes as $id => $node ) {
    if ( is_object( $node ) && is_string( $node->settings ?? null ) && $node->settings === '' ) {
        $node->settings = new stdClass();
    }
}

update_post_meta( $page_id, '_fl_builder_enabled', true );
update_post_meta( $page_id, '_fl_builder_data', $nodes );
update_post_meta( $page_id, '_fl_builder_draft', $nodes );
if ( isset( $decoded->settings ) ) {
    update_post_meta( $page_id, '_fl_builder_data_settings', (object) $decoded->settings );
}

echo "Set Beaver Builder layout on page {$page_id} (" . count( $nodes ) . " nodes)\n";
