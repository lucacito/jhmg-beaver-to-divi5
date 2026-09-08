#!/usr/bin/env php
<?php
/**
 * Prints the module coverage the readme may quote: which Beaver Builder module
 * slugs have a source-verified handler, which are documentation-based
 * (approximate), and which only leave a placeholder.
 *
 * Usage: php scripts/module-coverage.php
 */
require __DIR__ . '/../tests/bootstrap.php';

$registry = ( new \BeaverDivi5Converter\Converter\ConverterEngine() )->registry();
$all      = $registry->knownModuleSlugs();
$approx   = $registry->approximateModuleSlugs();
$lite_placeholders = [ 'acf-block' ];
$verified = array_values( array_diff( $all, $approx, $lite_placeholders ) );

printf( "Source-verified handlers (%d): %s\n", count( $verified ), implode( ', ', $verified ) );
printf( "Documentation-based, reported approximate (%d): %s\n", count( $approx ), implode( ', ', $approx ) );
printf( "Placeholder only (%d): %s\n", count( $lite_placeholders ), implode( ', ', $lite_placeholders ) );
