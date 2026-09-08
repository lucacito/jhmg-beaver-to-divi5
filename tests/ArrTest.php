<?php

use BeaverDivi5Converter\Helpers\Arr;
use PHPUnit\Framework\TestCase;

/** Arr::isList() stands in for array_is_list(), which PHP 8.0 lacks and WordPress polyfills only from 6.5. */
final class ArrTest extends TestCase {

    public function test_matches_array_is_list_on_every_shape(): void {
        $cases = [
            'empty'                 => [],
            'sequential'            => [ 'a', 'b', 'c' ],
            'explicit zero based'   => [ 0 => 'a', 1 => 'b' ],
            'nested documents'      => [ [ 'nodes' => [] ], [ 'nodes' => [] ] ],
            'string keys'           => [ 'x' => 1 ],
            'mixed keys'            => [ 0 => 'a', 'k' => 'b' ],
            'gap'                   => [ 0 => 'a', 2 => 'c' ],
            'reordered'             => [ 1 => 'b', 0 => 'a' ],
            'one based'             => [ 1 => 'a' ],
        ];
        foreach ( $cases as $label => $array ) {
            $this->assertSame( array_is_list( $array ), Arr::isList( $array ), $label );
        }
    }
}
