<?php

use PHPUnit\Framework\TestCase;
use BeaverDivi5Converter\Conversion\ConversionPreflight;
use BeaverDivi5Converter\Pro\Admin\ProPage;
use BeaverDivi5Converter\Pro\Admin\ThemerRepository;
use BeaverDivi5Converter\Pro\Exporters\DiviThemeBuilderExporter;
use BeaverDivi5Converter\Pro\Licensing\LicenseClient;
use BeaverDivi5Converter\Pro\Plugin as ProPlugin;

final class ProPluginTest extends TestCase {

    protected function setUp(): void {
        bdc_test_reset_hooks();
        $GLOBALS['__test_posts']    = [];
        $GLOBALS['__test_postmeta'] = [];
        $GLOBALS['bdc_test_http']   = [ 'queue' => [], 'log' => [] ];
    }

    private function nodes(): array {
        return json_decode( (string) file_get_contents( __DIR__ . '/../fixtures/beaver/simple-row.json' ), true )['nodes'];
    }

    private function seedThemerLayout( int $id, string $title, string $type ): void {
        $GLOBALS['__test_posts'][ $id ] = (object) [ 'ID' => $id, 'post_title' => $title, 'post_name' => sanitize_title( $title ), 'post_type' => 'fl-theme-layout', 'post_status' => 'publish' ];
        update_post_meta( $id, '_fl_builder_enabled', '1' );
        update_post_meta( $id, '_fl_theme_layout_type', $type );
        update_post_meta( $id, '_fl_builder_data', json_decode( json_encode( $this->nodes() ) ) );
    }

    // --- wiring ----------------------------------------------------------------------------

    public function test_pro_raises_the_limit_declares_itself_and_registers_the_theme_builder_exporter(): void {
        ProPlugin::instance()->register_hooks();

        $this->assertTrue( apply_filters( 'bdc_pro_active', false ) );
        $this->assertSame( PHP_INT_MAX, ConversionPreflight::limit() );
        $this->assertInstanceOf( DiviThemeBuilderExporter::class, apply_filters( 'bdc_theme_builder_exporter', null ) );
        $this->assertSame( 'beaver-to-divi5-pro', BDCP_PRODUCT_SLUG );
    }

    // --- themer repository + page ----------------------------------------------------------------

    public function test_themer_repository_lists_only_header_and_footer_layouts(): void {
        $this->seedThemerLayout( 10, 'Site Header', 'header' );
        $this->seedThemerLayout( 11, 'Site Footer', 'footer' );
        $this->seedThemerLayout( 12, 'Archive', 'archive' );
        $repo = new ThemerRepository( fn() => array_values( $GLOBALS['__test_posts'] ) );

        $rows = $repo->find();

        $this->assertSame( [ 10, 11 ], array_column( $rows, 'id' ) );
        $this->assertSame( 'footer', $rows[1]['layout_type'] );
        $this->assertSame( 'fl-theme-layout', $repo->query_args()['post_type'] );
    }

    public function test_pro_page_verifies_layout_ids_against_the_repository(): void {
        $this->seedThemerLayout( 20, 'Header', 'header' );
        $page = new ProPage( ProPlugin::instance()->license(), new ThemerRepository( fn() => array_values( $GLOBALS['__test_posts'] ) ) );

        $this->assertSame( [ 20 ], $page->verified_layout_ids( [ 'bdcp_layout_ids' => [ '20', '20', '999', 'x' ] ] ) );
        $this->assertStringContainsString( 'name="bdcp_layout_ids[]" value="20"', $page->themer_markup() );
        $this->assertStringContainsString( 'nav-tab-active', $page->markup( 'license' ) );
        $this->assertStringContainsString( 'Licence key', $page->markup( 'license' ) );
    }

    public function test_converting_a_themer_header_installs_it_in_the_theme_builder(): void {
        ProPlugin::instance()->register_hooks();
        $this->seedThemerLayout( 30, 'Site Header', 'header' );
        $page = new ProPage( ProPlugin::instance()->license(), new ThemerRepository( fn() => array_values( $GLOBALS['__test_posts'] ) ) );

        $results = $page->convert_layouts( [ 30 ] );

        $this->assertTrue( $results[0]['success'] );
        $this->assertSame( 'header', $results[0]['template_type'] );
        $layout = get_post( $results[0]['post_id'] );
        $this->assertSame( 'et_header_layout', $layout->post_type );
        $this->assertSame( 'publish', $layout->post_status );
        $this->assertStringContainsString( 'wp:divi/heading', $layout->post_content );
        $this->assertSame( 'header:post-30', get_post_meta( $layout->ID, '_bdc_tb_source', true ) );

        $template = get_post( $results[0]['template_id'] );
        $this->assertSame( 'et_template', $template->post_type );
        $this->assertSame( '1', get_post_meta( $template->ID, '_et_default', true ) );
        $this->assertSame( $layout->ID, get_post_meta( $template->ID, '_et_header_layout_id', true ) );
        $this->assertContains( $template->ID, get_post_meta( $results[0]['theme_builder_id'], '_et_template' ) );
    }

    public function test_reconverting_the_same_header_updates_instead_of_duplicating(): void {
        ProPlugin::instance()->register_hooks();
        $this->seedThemerLayout( 31, 'Site Header', 'header' );
        $page = new ProPage( ProPlugin::instance()->license(), new ThemerRepository( fn() => array_values( $GLOBALS['__test_posts'] ) ) );

        $first  = $page->convert_layouts( [ 31 ] )[0];
        $second = $page->convert_layouts( [ 31 ] )[0];

        $this->assertSame( $first['post_id'], $second['post_id'] );
        $this->assertSame( $first['template_id'], $second['template_id'] );
        $this->assertCount( 1, get_post_meta( $first['theme_builder_id'], '_et_template' ) );
    }

    // --- licence client ----------------------------------------------------------------------------

    private function client(): LicenseClient {
        return new LicenseClient( 'beaver-to-divi5-pro', '1.0.0', 'https://license.test', 'pro/pro.php', 'bdcp-pro', 'https://divi5lab.com/plugins/beaver-builder-to-divi-5', 'bdcp' );
    }

    public function test_activation_stores_the_key_and_state_under_the_bdcp_prefix(): void {
        bdc_test_http_queue( [ 'code' => 200, 'body' => [ 'status' => 'active', 'expires' => '2027-09-08' ] ] );

        $result = $this->client()->activate( 'KEY-123' );

        $this->assertTrue( $result['ok'] );
        $this->assertSame( 'KEY-123', get_option( 'bdcp_license_key' ) );
        $this->assertSame( 'active', get_option( 'bdcp_license_state' )['status'] );
        $this->assertSame( 'https://license.test/api/license/activate', $GLOBALS['bdc_test_http']['log'][0]['url'] );
        $this->assertSame( 'beaver-to-divi5-pro', json_decode( $GLOBALS['bdc_test_http']['log'][0]['args']['body'], true )['product'] );
    }

    public function test_a_rejected_key_is_reported_and_nothing_is_stored(): void {
        bdc_test_http_queue( [ 'code' => 404, 'body' => [ 'error' => 'invalid_key' ] ] );

        $result = $this->client()->activate( 'BAD' );

        $this->assertFalse( $result['ok'] );
        $this->assertSame( 'invalid_key', $result['error'] );
        $this->assertNull( $this->client()->get_key() );
    }

    public function test_update_check_injects_a_package_only_when_the_server_offers_one(): void {
        update_option( 'bdcp_license_key', 'KEY-123' );
        bdc_test_http_queue( [ 'code' => 200, 'body' => [ 'update' => true, 'version' => '1.1.0', 'package' => 'https://license.test/pro-1.1.0.zip' ] ] );
        $transient = $this->client()->inject_update( (object) [ 'response' => [] ] );
        $this->assertSame( '1.1.0', $transient->response['pro/pro.php']->new_version );

        bdc_test_reset_hooks();
        update_option( 'bdcp_license_key', 'KEY-123' );
        bdc_test_http_queue( [ 'code' => 200, 'body' => [ 'update' => true, 'version' => '1.1.0' ] ] );
        $blocked = $this->client()->inject_update( (object) [ 'response' => [] ] );
        $this->assertSame( [], $blocked->response );
        $this->assertSame( '1.1.0', get_option( 'bdcp_update_blocked' ) );
    }

    public function test_pro_release_metadata_agrees(): void {
        $main = (string) file_get_contents( __DIR__ . '/../plugin/jhmg-converter-for-beaver-builder-to-divi-pro/jhmg-converter-for-beaver-builder-to-divi-pro.php' );
        $this->assertMatchesRegularExpression( '/^\s*\*\s*Version:\s*' . preg_quote( BDCP_PLUGIN_VERSION, '/' ) . '\s*$/m', $main );
        $this->assertStringContainsString( 'Requires Plugins:  jhmg-converter-for-beaver-builder-to-divi', $main );
    }
}
