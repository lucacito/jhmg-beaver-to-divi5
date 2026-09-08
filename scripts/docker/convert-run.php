<?php
/**
 * Converts a Beaver Builder page in place (the page itself becomes the Divi page).
 * Usage: PAGE_ID=<id> wp eval-file /tmp/convert-run.php --allow-root
 */
$page_id = (int) getenv( 'PAGE_ID' );
if ( ! $page_id ) {
    fwrite( STDERR, "PAGE_ID must be set\n" );
    exit( 1 );
}

$document = ( new \BeaverDivi5Converter\Parsers\BeaverDocumentParser() )->parse( get_post_meta( $page_id ) );
if ( empty( $document['nodes'] ) ) {
    fwrite( STDERR, "No Beaver Builder layout on post {$page_id}\n" );
    exit( 1 );
}

$converted = ( new \BeaverDivi5Converter\Converter\ConverterEngine() )->convert( $document );
( new \BeaverDivi5Converter\Exporters\DiviExporter() )->save( $page_id, $converted );
delete_post_meta( $page_id, '_fl_builder_enabled' );

echo "Converted post {$page_id} in place\n";
