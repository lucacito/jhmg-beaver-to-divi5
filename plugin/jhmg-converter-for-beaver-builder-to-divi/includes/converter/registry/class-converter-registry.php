<?php

namespace BeaverDivi5Converter\Converter\Registry;

use BeaverDivi5Converter\Converter\ConverterEngine;
use BeaverDivi5Converter\Converter\ConverterInterface;
use BeaverDivi5Converter\Converter\Handlers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Node type / module slug ⇒ converter.
 *
 * Structural nodes register by type (`row`, `column-group`, `column`); modules
 * register by slug under `module:<slug>`. An entry is a class name or a factory
 * closure receiving the engine.
 */
class ConverterRegistry {
    private ConverterEngine $engine;
    /** @var array<string,mixed> */
    private array $registry = [];
    /** @var array<string,bool> Module slugs whose handler was written from documentation, not source. */
    private array $approximate = [];

    public function __construct( ConverterEngine $engine ) {
        $this->engine = $engine;
        $this->registerDefaults();
    }

    public function register( string $node_type, $entry ): void {
        $this->registry[ $node_type ] = $entry;
    }

    /**
     * @param bool $approximate True when the field names come from Beaver Builder's
     *   documentation rather than its source (Pro modules). The engine reports
     *   such conversions as approximate instead of counting them as clean.
     */
    public function registerModule( string $slug, $entry, bool $approximate = false ): void {
        $this->registry[ 'module:' . $slug ] = $entry;
        if ( $approximate ) {
            $this->approximate[ $slug ] = true;
        } else {
            unset( $this->approximate[ $slug ] );
        }
    }

    public function getConverter( array $node ): ?ConverterInterface {
        $key = self::keyFor( $node );
        if ( $key === null || ! isset( $this->registry[ $key ] ) ) {
            return null;
        }
        return $this->instantiate( $this->registry[ $key ] );
    }

    public function isApproximate( array $node ): bool {
        return ( $node['type'] ?? '' ) === 'module' && ! empty( $this->approximate[ (string) ( $node['settings']['type'] ?? '' ) ] );
    }

    /** Short class name of the converter a node resolves to, for the report. */
    public function converterName( array $node ): string {
        $key   = self::keyFor( $node );
        $entry = $key !== null ? ( $this->registry[ $key ] ?? null ) : null;
        if ( is_string( $entry ) ) {
            return basename( str_replace( '\\', '/', $entry ) );
        }
        return is_object( $entry ) ? 'closure' : 'none';
    }

    /** A labelled placeholder for a module nothing else claimed. */
    public function defaultConverter( array $node ): ConverterInterface {
        $slug = (string) ( $node['settings']['type'] ?? $node['type'] ?? 'unknown' );
        return new Handlers\GenericFallbackConverter( $this->engine, $slug, false );
    }

    /** @return string[] Every module slug with a registered handler. */
    public function knownModuleSlugs(): array {
        $slugs = [];
        foreach ( array_keys( $this->registry ) as $key ) {
            if ( str_starts_with( $key, 'module:' ) ) {
                $slugs[] = substr( $key, 7 );
            }
        }
        return $slugs;
    }

    /** @return string[] Module slugs registered as approximate. */
    public function approximateModuleSlugs(): array {
        return array_keys( $this->approximate );
    }

    private static function keyFor( array $node ): ?string {
        $type = $node['type'] ?? '';
        if ( $type === 'module' ) {
            $slug = $node['settings']['type'] ?? '';
            return is_string( $slug ) && $slug !== '' ? 'module:' . $slug : null;
        }
        return is_string( $type ) && $type !== '' ? $type : null;
    }

    private function instantiate( $entry ): ConverterInterface {
        if ( is_callable( $entry ) ) {
            return $entry( $this->engine );
        }
        return new $entry( $this->engine );
    }

    private function registerDefaults(): void {
        $this->register( 'row', Handlers\RowConverter::class );
        $this->register( 'column-group', Handlers\ColumnGroupConverter::class );
        $this->register( 'column', Handlers\ColumnConverter::class );

        // Beaver Builder Lite modules — field names read from the plugin source (2.10.3.2).
        $lite = [
            'heading'        => Handlers\HeadingConverter::class,
            'rich-text'      => Handlers\RichTextConverter::class,
            'photo'          => Handlers\PhotoConverter::class,
            'button'         => Handlers\ButtonConverter::class,
            'button-group'   => Handlers\ButtonGroupConverter::class,
            'html'           => Handlers\HtmlConverter::class,
            'video'          => Handlers\VideoConverter::class,
            'audio'          => Handlers\AudioConverter::class,
            'sidebar'        => Handlers\SidebarConverter::class,
            'icon'           => Handlers\IconConverter::class,
            'callout'        => Handlers\CalloutConverter::class,
            'cta'            => Handlers\CtaConverter::class,
            'numbers'        => Handlers\NumbersConverter::class,
            'star-rating'    => Handlers\StarRatingConverter::class,
            'menu'           => Handlers\MenuConverter::class,
            'box'            => Handlers\BoxConverter::class,
        ];
        foreach ( $lite as $slug => $class ) {
            if ( class_exists( $class ) ) {
                $this->registerModule( $slug, $class );
            }
        }

        // Lite modules with no Divi equivalent: a labelled placeholder keeps their place.
        foreach ( [ 'widget', 'reusable-block', 'acf-block' ] as $slug ) {
            $this->registerModule( $slug, $this->placeholder( $slug ) );
        }

        // Beaver Builder Pro modules — field names from Beaver Builder's public
        // documentation; every handler reads defensively and is reported as approximate.
        $pro = [
            'separator'      => Handlers\SeparatorConverter::class,
            'accordion'      => Handlers\AccordionConverter::class,
            'tabs'           => Handlers\TabsConverter::class,
            'testimonials'   => Handlers\TestimonialsConverter::class,
            'pricing-table'  => Handlers\PricingTableConverter::class,
            'contact-form'   => Handlers\ContactFormConverter::class,
            'subscribe-form' => Handlers\SubscribeFormConverter::class,
            'map'            => Handlers\MapConverter::class,
            'gallery'        => Handlers\GalleryConverter::class,
            'slideshow'      => Handlers\GalleryConverter::class,
            'content-slider' => Handlers\ContentSliderConverter::class,
            'posts'          => Handlers\PostsConverter::class,
            'post-grid'      => Handlers\PostsConverter::class,
            'post-slider'    => Handlers\PostsConverter::class,
            'post-carousel'  => Handlers\PostsConverter::class,
            'countdown'      => Handlers\CountdownConverter::class,
            'icon-group'     => Handlers\IconGroupConverter::class,
            'social-buttons' => Handlers\SocialButtonsConverter::class,
            'login-form'     => Handlers\LoginFormConverter::class,
            'search'         => Handlers\SearchConverter::class,
            'number-counter' => Handlers\NumbersConverter::class,
        ];
        foreach ( $pro as $slug => $class ) {
            if ( class_exists( $class ) ) {
                $this->registerModule( $slug, $class, true );
            }
        }

        // Beaver Builder Pro modules added after 2.6 and PowerPack (third-party)
        // modules — field names read from exported layouts; reported as approximate.
        $addons = [
            'list'           => Handlers\ListConverter::class,
            'progress-bar'   => Handlers\ProgressBarConverter::class,
            'pp-heading'     => Handlers\PpHeadingConverter::class,
            'pp-iconlist'    => Handlers\PpIconListConverter::class,
            'pp-fluent-form' => Handlers\PpFluentFormConverter::class,
        ];
        foreach ( $addons as $slug => $class ) {
            if ( class_exists( $class ) ) {
                $this->registerModule( $slug, $class, true );
            }
        }
    }

    private function placeholder( string $slug ): \Closure {
        return static function ( ConverterEngine $engine ) use ( $slug ) {
            return new Handlers\GenericFallbackConverter( $engine, $slug, true );
        };
    }
}
