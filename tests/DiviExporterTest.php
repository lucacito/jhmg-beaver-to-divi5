<?php

use PHPUnit\Framework\TestCase;
use BeaverDivi5Converter\Converter\ConverterEngine;
use BeaverDivi5Converter\Exporters\DiviExporter;

final class DiviExporterTest extends TestCase {

    public function test_export_produces_the_divi_meta(): void {
        $meta = ( new DiviExporter() )->export( [ 'divi' => [ 'elements' => [] ], 'unsupported' => [ [ 'id' => 'x' ] ], 'report' => [ 'converted' => [] ] ] );

        $this->assertSame( 'on', $meta['_et_pb_use_builder'] );
        $this->assertSame( 'on', $meta['_et_pb_use_divi_5'] );
        $this->assertSame( 'VB|Divi|5.0.0', $meta['_et_builder_version'] );
        $this->assertSame( [ [ 'id' => 'x' ] ], json_decode( $meta['_bbdc_conversion_report'], true )['unsupported'] );
    }

    public function test_save_writes_slashed_block_content_and_meta(): void {
        $payload   = json_decode( (string) file_get_contents( __DIR__ . '/../fixtures/beaver/simple-row.json' ), true );
        $converted = ( new ConverterEngine() )->convert( $payload );
        $post_id   = wp_insert_post( [ 'post_type' => 'page', 'post_content' => '' ] );

        $this->assertTrue( ( new DiviExporter() )->save( $post_id, $converted ) );

        $post = get_post( $post_id );
        $this->assertStringContainsString( '<!-- wp:divi/section', $post->post_content );
        $this->assertStringContainsString( 'Hello World', $post->post_content );
        $this->assertStringContainsString( '\\"builderVersion\\"', $post->post_content, 'content is slashed for wp_update_post' );
        $this->assertSame( 'on', get_post_meta( $post_id, '_et_pb_use_divi_5', true ) );
        $this->assertSame( 1, json_decode( get_post_meta( $post_id, '_bbdc_conversion_report', true ), true )['converted']['heading'] );
    }
}
