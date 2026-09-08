#!/usr/bin/env php
<?php
/**
 * Converts a Beaver Builder bundled layout template (data/layout-*.dat, a
 * PHP-serialized template pack) into the JSON node-map format used by the
 * fixtures: {"name": ..., "nodes": {...}, "settings": {...}}.
 *
 * Usage: php scripts/bb-dat-to-json.php <layout.dat> <out.json>
 */

if ( $argc < 3 ) {
    fwrite( STDERR, "Usage: php scripts/bb-dat-to-json.php <layout.dat> <out.json>\n" );
    exit( 2 );
}

$raw  = file_get_contents( $argv[1] );
$data = unserialize( $raw, [ 'allowed_classes' => [ 'stdClass' ] ] );

$template = $data['layout'][0] ?? null;
if ( ! is_object( $template ) ) {
    fwrite( STDERR, "Not a Beaver Builder layout template pack: {$argv[1]}\n" );
    exit( 1 );
}

$nodes = json_decode( json_encode( $template->nodes ?? [] ), true );
if ( empty( $nodes ) ) {
    fwrite( STDERR, "Template has no nodes (blank layout): {$argv[1]}\n" );
    exit( 1 );
}

$out = [
    'name'     => (string) ( $template->name ?? basename( $argv[1], '.dat' ) ),
    'nodes'    => $nodes,
    'settings' => json_decode( json_encode( $template->settings ?? [] ), true ),
];

file_put_contents( $argv[2], json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
echo "wrote {$argv[2]} (" . count( $nodes ) . " nodes)\n";
