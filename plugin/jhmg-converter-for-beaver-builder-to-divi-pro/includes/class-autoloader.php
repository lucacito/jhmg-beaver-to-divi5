<?php

namespace BeaverDivi5Converter\Pro;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Autoloader {
    public static function register(): void {
        spl_autoload_register( [ self::class, 'autoload' ] );
    }

    public static function autoload( string $class ): void {
        $prefix = 'BeaverDivi5Converter\\Pro\\';
        if ( ! str_starts_with( $class, $prefix ) ) {
            return;
        }
        $parts      = explode( '\\', substr( $class, strlen( $prefix ) ) );
        $class_name = array_pop( $parts );
        $directory  = BDCP_PLUGIN_DIR . 'includes/' . ( empty( $parts ) ? '' : implode( '/', array_map( 'strtolower', $parts ) ) . '/' );
        $path       = $directory . 'class-' . strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $class_name ) ) . '.php';
        if ( file_exists( $path ) ) {
            require_once $path;
        }
    }
}

Autoloader::register();
