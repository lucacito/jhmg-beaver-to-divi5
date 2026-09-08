<?php

use PHPUnit\Framework\TestCase;
use BeaverDivi5Converter\Converter\ConverterEngine;

/** Behaviour the golden fixtures cannot show: warnings, notes and edge cases. */
final class ContentHandlersTest extends TestCase {

    protected function setUp(): void {
        bbdc_test_reset_hooks();
    }

    private function convertModule( array $settings ): array {
        $engine = new ConverterEngine();
        $result = $engine->convert( [ 'nodes' => [
            'row1' => [ 'node' => 'row1', 'type' => 'row', 'parent' => null, 'position' => 0, 'settings' => [] ],
            'grp1' => [ 'node' => 'grp1', 'type' => 'column-group', 'parent' => 'row1', 'position' => 0, 'settings' => '' ],
            'col1' => [ 'node' => 'col1', 'type' => 'column', 'parent' => 'grp1', 'position' => 0, 'settings' => [ 'size' => 100 ] ],
            'mod1' => [ 'node' => 'mod1', 'type' => 'module', 'parent' => 'col1', 'position' => 0, 'settings' => $settings ],
        ] ] );
        $blocks = $result['divi']['elements'][0]['elements'][0]['elements'][0]['elements'];
        return [ $blocks, $result['report'], $result['unsupported'] ];
    }

    public function test_button_hover_colours_are_reported_not_dropped_silently(): void {
        [ , $report ] = $this->convertModule( [ 'type' => 'button', 'text' => 'Go', 'link' => '#', 'bg_hover_color' => '000' ] );
        $this->assertSame( [ [ 'kind' => 'hover', 'node_id' => 'mod1', 'detail' => 'button hover colours' ] ], $report['not_carried_over'] );
    }

    public function test_button_non_link_click_actions_are_reported(): void {
        [ $blocks, $report ] = $this->convertModule( [ 'type' => 'button', 'text' => 'Copy', 'click_action' => 'copy_text', 'copy_text' => 'abc' ] );
        $this->assertArrayNotHasKey( 'linkUrl', $blocks[0]['settings']['button']['innerContent']['desktop']['value'] );
        $this->assertSame( 'interaction', $report['not_carried_over'][0]['kind'] );
    }

    public function test_button_full_width_becomes_custom_css(): void {
        [ $blocks ] = $this->convertModule( [ 'type' => 'button', 'text' => 'Wide', 'link' => '#', 'width' => 'full' ] );
        $this->assertSame( 'width: 100%; text-align: center;', $blocks[0]['settings']['css']['desktop']['value']['main'] );
    }

    public function test_button_unknown_icon_falls_back_with_a_warning(): void {
        [ $blocks, $report ] = $this->convertModule( [ 'type' => 'button', 'text' => 'Go', 'link' => '#', 'icon' => 'dashicons dashicons-star-filled' ] );
        $this->assertSame( '&#xf005;', $blocks[0]['settings']['button']['decoration']['button']['desktop']['value']['icon']['settings']['unicode'] );
        $this->assertStringContainsString( 'no Divi equivalent', $report['warnings'][0] );
    }

    public function test_button_group_becomes_a_nested_flex_row_of_buttons(): void {
        [ $blocks ] = $this->convertModule( [
            'type' => 'button-group', 'bg_color' => '111111', 'align' => 'right', 'button_spacing' => '12', 'button_spacing_unit' => 'px',
            'button_padding_top' => '10', 'button_padding_left' => '20', 'padding_top' => '99',
            'items' => [ [ 'text' => 'One', 'link' => '/one' ], [ 'text' => 'Two', 'link' => '/two' ] ],
        ] );

        // Divi 5 nests a row inside the column; its single column is a flex row.
        $this->assertCount( 1, $blocks );
        $this->assertSame( 'divi/row', $blocks[0]['name'] );
        $column = $blocks[0]['elements'][0];
        $this->assertSame( 'divi/column', $column['name'] );
        $layout = $column['settings']['module']['decoration']['layout']['desktop']['value'];
        $this->assertSame( 'flex', $layout['display'] );
        $this->assertSame( 'flex-end', $layout['justifyContent'] );
        $this->assertSame( '12px', $layout['columnGap'] );

        $buttons = $column['elements'];
        $this->assertCount( 2, $buttons );
        $this->assertSame( 'mod1-2', $buttons[1]['id'] );
        $this->assertSame( '#111111', $buttons[1]['settings']['button']['decoration']['background']['desktop']['value']['color'] );
        $this->assertSame( '10px', $buttons[1]['settings']['button']['decoration']['spacing']['desktop']['value']['padding']['top'] );
        $this->assertArrayNotHasKey( 'alignment', $buttons[1]['settings']['module']['advanced'] ?? [], 'the flex row aligns the group, not each button' );
    }

    public function test_vertical_button_groups_stay_stacked(): void {
        [ $blocks ] = $this->convertModule( [ 'type' => 'button-group', 'layout' => 'vertical', 'align' => 'center', 'items' => [ [ 'text' => 'One' ], [ 'text' => 'Two' ] ] ] );

        $this->assertCount( 2, $blocks );
        $this->assertSame( 'divi/button', $blocks[0]['name'] );
        $this->assertSame( 'center', $blocks[0]['settings']['module']['advanced']['alignment']['desktop']['value'] );
    }

    public function test_photo_alt_falls_back_through_description_caption_and_title(): void {
        [ $blocks ] = $this->convertModule( [ 'type' => 'photo', 'photo_src' => 'https://x/a.jpg', 'data' => [ 'alt' => '', 'description' => 'Described', 'caption' => 'Cap' ] ] );
        $this->assertSame( 'Described', $blocks[0]['settings']['image']['innerContent']['desktop']['value']['alt'] );
    }

    public function test_photo_without_alt_or_source_warns(): void {
        [ , $report ] = $this->convertModule( [ 'type' => 'photo', 'photo_source' => 'library', 'photo' => 1 ] );
        $this->assertStringContainsString( 'no image URL', $report['warnings'][0] );
        $this->assertStringContainsString( 'missing alt text', $report['warnings'][1] );
    }

    public function test_photo_lightbox_and_file_links(): void {
        [ $lightbox ] = $this->convertModule( [ 'type' => 'photo', 'photo_src' => 'https://x/a.jpg', 'link_type' => 'lightbox' ] );
        $this->assertSame( 'on', $lightbox[0]['settings']['image']['advanced']['lightbox']['desktop']['value'] );

        [ $file ] = $this->convertModule( [ 'type' => 'photo', 'photo_src' => 'https://x/a.jpg', 'link_type' => 'file' ] );
        $this->assertSame( 'https://x/a.jpg', $file[0]['settings']['image']['innerContent']['desktop']['value']['linkUrl'] );
    }

    public function test_photo_non_circle_crops_warn(): void {
        [ , $report ] = $this->convertModule( [ 'type' => 'photo', 'photo_src' => 'https://x/a.jpg', 'crop' => 'landscape', 'data' => [ 'alt' => 'a' ] ] );
        $this->assertStringContainsString( "'landscape' crop", $report['warnings'][0] );
    }

    public function test_rich_text_without_block_tags_is_wrapped_in_paragraphs(): void {
        [ $blocks ] = $this->convertModule( [ 'type' => 'rich-text', 'text' => "Line one\n\nLine two" ] );
        $this->assertSame( "<p>Line one</p>\n<p>Line two</p>", $blocks[0]['settings']['content']['innerContent']['desktop']['value'] );
    }

    public function test_video_unknown_embed_is_kept_as_code(): void {
        [ $blocks, $report ] = $this->convertModule( [ 'type' => 'video', 'video_type' => 'embed', 'embed_code' => '<iframe src="https://player.wistia.com/embed/abc"></iframe>' ] );
        $this->assertSame( 'divi/code', $blocks[0]['name'] );
        $this->assertSame( 1, $report['converted']['code'] );
    }

    public function test_video_vimeo_and_lightbox(): void {
        [ $blocks, $report ] = $this->convertModule( [ 'type' => 'video', 'video_type' => 'embed', 'embed_code' => 'https://vimeo.com/122546221', 'video_lightbox' => 'yes' ] );
        $this->assertSame( 'https://vimeo.com/122546221', $blocks[0]['settings']['video']['innerContent']['desktop']['value']['src'] );
        $this->assertSame( 'lightbox', $report['not_carried_over'][0]['kind'] );
    }

    public function test_video_without_a_source_warns(): void {
        [ , $report ] = $this->convertModule( [ 'type' => 'video', 'video_type' => 'media_library', 'video' => '' ] );
        $this->assertStringContainsString( 'missing source URL', $report['warnings'][0] );
    }

    public function test_audio_playlists_keep_the_first_track_and_warn(): void {
        [ $blocks, $report ] = $this->convertModule( [ 'type' => 'audio', 'audio_type' => 'media_library', 'audios' => [ 'https://x/1.mp3', 'https://x/2.mp3' ] ] );
        $this->assertSame( 'https://x/1.mp3', $blocks[0]['settings']['audio']['innerContent']['desktop']['value'] );
        $this->assertStringContainsString( 'playlist of 2 tracks', $report['warnings'][0] );
    }

    public function test_audio_link_type(): void {
        [ $blocks ] = $this->convertModule( [ 'type' => 'audio', 'audio_type' => 'link', 'link' => 'https://x/stream.mp3' ] );
        $this->assertSame( 'https://x/stream.mp3', $blocks[0]['settings']['audio']['innerContent']['desktop']['value'] );
    }

    public function test_placeholder_modules_keep_their_text_and_count_as_placeholders(): void {
        [ $blocks, $report, $unsupported ] = $this->convertModule( [ 'type' => 'widget', 'widget' => 'WP_Widget_Text', 'title' => 'Widget title' ] );
        $this->assertSame( 'divi/code', $blocks[0]['name'] );
        $this->assertStringContainsString( 'Widget title', $blocks[0]['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertSame( 1, $report['converted']['code'], 'a registered placeholder counts, an unknown module does not' );
        $this->assertSame( [], $unsupported );
    }
}
