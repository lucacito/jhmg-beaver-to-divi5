<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Beaver Builder "WordPress Patterns" module (module slug `reusable-block`,
 * Lite source: modules/reusable-block). Its `block_id` (`block-123` or `123`)
 * is a `wp_block` post; Beaver Builder renders it with
 * `do_blocks('<!-- wp:block {"ref":123} /-->')`.
 *
 * A Divi 5 layout holds Divi blocks only, so the pattern is rendered the same
 * way at conversion time and the HTML goes into a divi/code module. The copy is
 * static: later edits to the pattern no longer flow into this page, which the
 * report says. Without WordPress (unit tests) the block reference is kept as a
 * comment.
 */
class ReusableBlockConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bbdc_pattern_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style = $this->mapStyle( 'generic', $node );
        $raw   = $this->text( $settings, 'block_id' );
        $ref   = (int) preg_replace( '/\D+/', '', $raw );

        if ( $ref <= 0 ) {
            $this->engine->logWarning( "WordPress Pattern {$id}: no pattern selected in Beaver Builder; nothing to render." );
            $html = '<!-- beaver builder module: reusable-block (no pattern selected) -->';
        } else {
            $html = $this->render( $ref );
            if ( $html === null ) {
                $this->engine->logWarning( "WordPress Pattern {$id}: pattern #{$ref} could not be rendered here; a reference comment marks its place." );
                $html = '<!-- beaver builder module: reusable-block ref=' . $ref . ' -->';
            } else {
                $this->engine->logNotCarriedOver( 'integration', $id, "WordPress pattern #{$ref} copied as static HTML; edits to the pattern no longer sync into this page" );
            }
        }

        $block = $this->codeBlock( $id, $html );
        $block['settings'] = $this->deepMergeSettings( $block['settings'], [ 'module' => $style['divi_attrs']['module'] ?? [] ] );

        $this->engine->logConverted( 'code' );
        $this->logUnmappedSettings( $id, $settings, array_merge( $style['handled_keys'], [ 'block_id' ] ) );

        return $block;
    }

    /** The pattern's rendered HTML, or null when WordPress is not loaded or the pattern is gone. */
    private function render( int $ref ): ?string {
        if ( ! function_exists( 'do_blocks' ) || ! function_exists( 'get_post' ) ) {
            return null;
        }
        $post = get_post( $ref );
        if ( ! is_object( $post ) || ( $post->post_type ?? '' ) !== 'wp_block' ) {
            return null;
        }
        $html = do_blocks( '<!-- wp:block {"ref":' . $ref . '} /-->' );
        return is_string( $html ) ? trim( $html ) : null;
    }
}
