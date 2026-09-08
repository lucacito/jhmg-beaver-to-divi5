<?php
/**
 * Creates a new Divi 5 page from a Beaver Builder page, through the same
 * pipeline the admin screen uses. Prints the new page id.
 * Usage: SOURCE_PAGE_ID=<id> wp eval-file /tmp/convert-to-new-page.php --allow-root
 */
$source_id = (int) getenv( 'SOURCE_PAGE_ID' );
if ( ! $source_id ) {
    fwrite( STDERR, "SOURCE_PAGE_ID must be set\n" );
    exit( 1 );
}

$plan    = ( new \BeaverDivi5Converter\Conversion\ConversionPreflight() )->run( new \BeaverDivi5Converter\Conversion\InstalledPostSource( [ $source_id ] ) );
$results = ( new \BeaverDivi5Converter\Conversion\ConversionCommitter() )->commit( $plan, [ 'post_status' => 'publish' ] );

if ( empty( $results[0]['success'] ) ) {
    fwrite( STDERR, 'Conversion failed: ' . ( $results[0]['error'] ?? 'unknown' ) . "\n" );
    exit( 1 );
}

echo $results[0]['post_id'];
