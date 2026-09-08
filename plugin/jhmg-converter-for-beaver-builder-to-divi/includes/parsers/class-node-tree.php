<?php
/**
 * Turns Beaver Builder's flat node map into the nested tree the converter walks.
 *
 * Beaver Builder keeps every node in one map keyed by id, each pointing at its
 * parent. Rows have no parent; column groups belong to rows (or to columns,
 * for nested columns); columns belong to groups; modules belong to columns —
 * or to a container module such as Box, whose children point at the box.
 */

namespace BeaverDivi5Converter\Parsers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NodeTree {

    /**
     * @param array<string,array> $nodes As BeaverDocumentParser::parseValue() returns them.
     * @return array{roots: array<int,array>, orphans: string[]}
     *   Each tree node: ['id','type','parent','position','settings','children'].
     */
    public static function build( array $nodes ): array {
        $children = [];
        $orphans  = [];

        foreach ( $nodes as $id => $node ) {
            $parent = $node['parent'] ?? null;

            if ( $parent === null ) {
                $children['']['x' . $id] = $node;
                continue;
            }

            if ( ! isset( $nodes[ $parent ] ) ) {
                // A child whose parent is gone. Keep it at the root so its
                // content is not lost, and say so.
                $orphans[]                 = (string) $id;
                $children['']['x' . $id]   = $node;
                continue;
            }

            $children[ $parent ]['x' . $id] = $node;
        }

        return [
            'roots'   => self::assemble( '', $children, [] ),
            'orphans' => $orphans,
        ];
    }

    /**
     * @param array<string,array<string,array>> $children_by_parent
     * @param string[] $path Ids on the current branch, to stop on a cycle.
     */
    private static function assemble( string $parent_id, array $children_by_parent, array $path ): array {
        $list = array_values( $children_by_parent[ $parent_id ] ?? [] );

        usort( $list, static fn( array $a, array $b ): int => ( $a['position'] ?? 0 ) <=> ( $b['position'] ?? 0 ) );

        $result = [];
        foreach ( $list as $node ) {
            $id = (string) $node['node'];

            if ( in_array( $id, $path, true ) ) {
                continue;
            }

            $result[] = [
                'id'       => $id,
                'type'     => (string) $node['type'],
                'parent'   => $node['parent'] ?? null,
                'position' => (int) ( $node['position'] ?? 0 ),
                'settings' => is_array( $node['settings'] ?? null ) ? $node['settings'] : [],
                'children' => self::assemble( $id, $children_by_parent, array_merge( $path, [ $id ] ) ),
            ];
        }

        return $result;
    }

    /** Every module slug used in a tree, with a count. */
    public static function moduleCensus( array $roots ): array {
        $census = [];
        $walk   = static function ( array $nodes ) use ( &$walk, &$census ): void {
            foreach ( $nodes as $node ) {
                if ( ( $node['type'] ?? '' ) === 'module' ) {
                    $slug            = (string) ( $node['settings']['type'] ?? 'unknown' );
                    $census[ $slug ] = ( $census[ $slug ] ?? 0 ) + 1;
                }
                $walk( $node['children'] ?? [] );
            }
        };
        $walk( $roots );

        return $census;
    }
}
