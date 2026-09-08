<?php
/**
 * The structural outline the report screen draws: sections, rows, columns and
 * modules as a tree of labels. Structure, not pixels — Divi 5 keys its CSS to
 * a post id, so rendering detached blocks would only show an unstyled skeleton.
 */

namespace BeaverDivi5Converter\Conversion;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ConversionOutline {

    private const STRUCTURAL = [
        'divi/section' => 'section',
        'divi/row'     => 'row',
        'divi/column'  => 'column',
        'divi/group'   => 'group',
    ];

    /** @return array Outline nodes: ['type','name','label','placeholder','children']. */
    public static function build( array $blocks ): array {
        $nodes = [];
        foreach ( $blocks as $block ) {
            if ( ! is_array( $block ) ) {
                continue;
            }
            $name = (string) ( $block['name'] ?? '' );
            if ( $name === '' ) {
                continue;
            }
            $content = $block['settings']['content']['innerContent']['desktop']['value'] ?? '';

            $nodes[] = [
                'type'        => self::STRUCTURAL[ $name ] ?? 'module',
                'name'        => $name,
                'label'       => self::label( $name ),
                'placeholder' => $name === 'divi/code' && is_string( $content ) && str_contains( $content, 'beaver builder module:' ),
                'children'    => self::build( $block['elements'] ?? [] ),
            ];
        }
        return $nodes;
    }

    /** 'divi/number-counter' → 'Number Counter'. */
    private static function label( string $name ): string {
        $bare = str_contains( $name, '/' ) ? substr( $name, strpos( $name, '/' ) + 1 ) : $name;
        return ucwords( str_replace( [ '-', '_' ], ' ', $bare ) );
    }
}
