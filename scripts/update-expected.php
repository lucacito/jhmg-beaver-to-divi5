#!/usr/bin/env php
<?php
/**
 * Regenerates fixtures/divi/<name>.json from fixtures/beaver/<name>.json.
 *
 * Only run this after reviewing the converter's output by hand
 * (scripts/render-fixture.php): the expected files are the specification the
 * fixture test enforces, not a snapshot of whatever the code happens to do.
 *
 * Usage: php scripts/update-expected.php heading button …   (or --all)
 */
require __DIR__ . '/../tests/bootstrap.php';

$root  = dirname( __DIR__ );
$names = array_slice( $argv, 1 );

if ( in_array( '--all', $names, true ) ) {
    $names = array_map( static fn( string $f ) => basename( $f, '.json' ), glob( $root . '/fixtures/beaver/*.json' ) ?: [] );
}
if ( empty( $names ) ) {
    fwrite( STDERR, "Usage: php scripts/update-expected.php <fixture name>… | --all\n" );
    exit( 2 );
}

foreach ( $names as $name ) {
    $in = $root . "/fixtures/beaver/{$name}.json";
    if ( ! is_file( $in ) ) {
        fwrite( STDERR, "missing: {$in}\n" );
        continue;
    }
    $payload = json_decode( (string) file_get_contents( $in ), true );
    $result  = ( new \BeaverDivi5Converter\Converter\ConverterEngine() )->convert( $payload );
    $out     = [ 'divi' => $result['divi'], 'unsupported' => $result['unsupported'] ];
    file_put_contents( $root . "/fixtures/divi/{$name}.json", json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
    echo "wrote fixtures/divi/{$name}.json\n";
}
