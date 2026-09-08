#!/usr/bin/env php
<?php
/**
 * Builds plugin/…/data/fa-icons.json from Divi 5's own icon list, so Beaver
 * Builder's Font Awesome 5 classes (`fas fa-check`) can become the Divi icon
 * with the same glyph instead of a generic placeholder.
 *
 * Usage: php scripts/build-fa-icon-map.php /path/to/Divi
 *
 * Output shape: { "<fa name>": { "u": "&#xf00c;", "w": [900, 400] } }
 *   u — the unicode entity Divi stores in an icon attribute
 *   w — font weights Divi lists for that glyph (900 = solid, 400 = regular/brands)
 */

$divi = rtrim( $argv[1] ?? '', '/' );
$list = $divi . '/includes/builder-5/visual-builder/packages/icon-library/src/components/icon-font/iconList.json';

if ( ! is_file( $list ) ) {
    fwrite( STDERR, "Usage: php scripts/build-fa-icon-map.php /path/to/Divi (iconList.json not found at $list)\n" );
    exit( 2 );
}

$icons = json_decode( (string) file_get_contents( $list ), true );
$map   = [];

foreach ( $icons as $icon ) {
    if ( ! in_array( 'fa', $icon['styles'] ?? [], true ) ) {
        continue;
    }
    $name = strtolower( (string) $icon['name'] );
    $map[ $name ]['u'] = (string) $icon['unicode'];
    $map[ $name ]['w'][] = (int) $icon['fontWeight'];
}

ksort( $map );
foreach ( $map as &$entry ) {
    $entry['w'] = array_values( array_unique( $entry['w'] ) );
    sort( $entry['w'] );
}
unset( $entry );

$out = dirname( __DIR__ ) . '/plugin/jhmg-converter-for-beaver-builder-to-divi/data/fa-icons.json';
file_put_contents( $out, json_encode( $map, JSON_UNESCAPED_SLASHES ) . "\n" );
echo 'wrote ' . $out . ' (' . count( $map ) . " icons)\n";
