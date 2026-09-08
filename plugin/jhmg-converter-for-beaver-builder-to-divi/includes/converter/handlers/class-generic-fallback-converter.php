<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;
use BeaverDivi5Converter\Converter\ConverterEngine;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Catch-all for modules with no Divi 5 equivalent.
 *
 * Emits a divi/code block whose HTML comment names the Beaver Builder module,
 * followed by whatever text the module carried, so an editor can see what was
 * there and where. It is also the registry's default for unknown modules; in
 * that case the module has already been reported as unsupported and this
 * placeholder is a rescue, not a conversion, so it is not counted.
 */
class GenericFallbackConverter extends BaseBeaverConverter {

    /** Setting keys that commonly hold a module's visible text, most specific first. */
    private const TEXT_KEYS = [
        'heading', 'title', 'text', 'html', 'content', 'description', 'caption', 'label',
        'btn_text', 'cta_text', 'link_text', 'before_number_text', 'after_number_text',
    ];

    private string $module_slug;
    private bool $count_as_converted;

    public function __construct( ConverterEngine $engine, string $module_slug, bool $count_as_converted = true ) {
        parent::__construct( $engine );
        $this->module_slug        = $module_slug;
        $this->count_as_converted = $count_as_converted;
    }

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bdc_code_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $this->engine->logWarning(
            "Module '{$this->module_slug}' has no Divi 5 equivalent; replaced with a labelled placeholder block (id: {$id})."
        );

        if ( $this->count_as_converted ) {
            $this->engine->logConverted( 'code' );
        }

        $marker = '<!-- beaver builder module: ' . esc_html( $this->module_slug ) . ' (not convertible) -->';
        $text   = $this->extractText( $settings );

        if ( $text !== '' ) {
            $marker .= '<div class="bdc-unconverted-module" data-beaver-module="' . esc_attr( $this->module_slug ) . '">' . $text . '</div>';
        }

        return $this->codeBlock( $id, $marker );
    }

    /** The first text-like setting, or ''. Only one key is used to avoid duplicating text. */
    private function extractText( array $settings ): string {
        foreach ( self::TEXT_KEYS as $key ) {
            $value = $settings[ $key ] ?? null;
            if ( is_string( $value ) && trim( $value ) !== '' ) {
                return trim( $value );
            }
        }
        return '';
    }
}
