#!/usr/bin/env php
<?php
/**
 * Runs the converter over a Beaver Builder fixture and prints the structural
 * output (divi tree + unsupported) as JSON — the shape fixtures/divi/*.json holds.
 *
 * Usage: php scripts/render-fixture.php fixtures/beaver/heading.json [--report]
 */
require __DIR__ . '/../tests/bootstrap.php';

$file = $argv[1] ?? '';
if ( ! is_file( $file ) ) {
    fwrite( STDERR, "Usage: php scripts/render-fixture.php <fixture.json> [--report]\n" );
    exit( 2 );
}

$payload = json_decode( (string) file_get_contents( $file ), true );
$result  = ( new \BeaverDivi5Converter\Converter\ConverterEngine() )->convert( $payload );

$out = [ 'divi' => $result['divi'], 'unsupported' => $result['unsupported'] ];
if ( in_array( '--report', $argv, true ) ) {
    $out['report'] = $result['report'];
}
echo json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), "\n";
