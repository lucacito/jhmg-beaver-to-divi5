<?php

use PHPUnit\Framework\TestCase;
use BeaverDivi5Converter\Exporters\DiviBlockSerializer;

final class DiviBlockSerializerTest extends TestCase {

    public function test_it_wraps_everything_in_a_placeholder_and_stamps_builder_version(): void {
        $out = ( new DiviBlockSerializer() )->serialize( [ 'divi' => [ 'elements' => [
            [ 'name' => 'divi/section', 'settings' => [], 'elements' => [
                [ 'name' => 'divi/row', 'settings' => [], 'elements' => [
                    [ 'name' => 'divi/column', 'settings' => [], 'elements' => [
                        [ 'name' => 'divi/heading', 'settings' => [ 'title' => [ 'innerContent' => [ 'desktop' => [ 'value' => 'Hi <b>there</b>' ] ] ] ], 'elements' => [] ],
                    ] ],
                ] ],
            ] ],
        ] ] ] );

        $this->assertStringStartsWith( '<!-- wp:divi/placeholder -->', $out );
        $this->assertStringEndsWith( '<!-- /wp:divi/placeholder -->', $out );
        $this->assertStringContainsString( '<!-- wp:divi/section {"builderVersion":"5.0.0"} -->', $out );
        // HTML inside attributes is stored as JSON unicode escapes, exactly as Divi 5 saves it.
        $this->assertStringContainsString( '<!-- wp:divi/heading {"builderVersion":"5.0.0","title":{"innerContent":{"desktop":{"value":"Hi \\u003Cb\\u003Ethere\\u003C/b\\u003E"}}}} /-->', $out );
        $this->assertStringContainsString( '<!-- /wp:divi/column --><!-- /wp:divi/row --><!-- /wp:divi/section -->', $out );
    }

    public function test_a_loose_module_under_a_section_is_wrapped_in_row_and_column(): void {
        $out = ( new DiviBlockSerializer() )->serialize( [ [ 'name' => 'divi/section', 'settings' => [], 'elements' => [
            [ 'name' => 'divi/text', 'settings' => [ 'content' => [ 'innerContent' => [ 'desktop' => [ 'value' => 'x' ] ] ] ], 'elements' => [] ],
        ] ] ] );

        $this->assertMatchesRegularExpression( '#wp:divi/section .*?wp:divi/row .*?wp:divi/column .*?wp:divi/text#', $out );
    }

    public function test_groups_wrap_their_children(): void {
        $out = ( new DiviBlockSerializer() )->serialize( [ [ 'name' => 'divi/group', 'settings' => [], 'elements' => [ [ 'name' => 'divi/code', 'settings' => [], 'elements' => [] ] ] ] ] );

        $this->assertStringContainsString( '<!-- wp:divi/group {"builderVersion":"5.0.0"} --><!-- wp:divi/code {"builderVersion":"5.0.0"} /--><!-- /wp:divi/group -->', $out );
    }
}
