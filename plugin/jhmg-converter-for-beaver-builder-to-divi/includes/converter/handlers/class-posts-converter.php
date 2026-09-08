<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Beaver Builder Pro Posts / Post Grid / Post Slider / Post Carousel → divi/blog. */
class PostsConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bbdc_blog_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style = $this->mapStyle( 'generic', $node );
        $attrs = $style['divi_attrs'];

        $per_page = (int) ( $settings['posts_per_page'] ?? 10 );
        $attrs['post']['innerContent']['desktop']['value'] = [ 'perPage' => $per_page > 0 ? $per_page : 10 ];

        $type = $this->text( $settings, 'post_type' );
        if ( $type !== '' && $type !== 'post' ) {
            $this->engine->logWarning( "Posts {$id}: lists the '{$type}' post type; Divi's blog module shows posts." );
        }
        $slug = (string) ( $settings['type'] ?? 'posts' );
        if ( $slug !== 'posts' ) {
            $this->engine->logWarning( "Posts {$id}: the {$slug} layout became a blog grid." );
        }

        $this->engine->logConverted( 'blog' );
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [ 'posts_per_page', 'post_type', 'layout', 'post_columns', 'show_image', 'show_content', 'show_more_link', 'more_link_text', 'content_type', 'order_by', 'order', 'offset', 'pagination', 'show_author', 'show_date', 'show_comments', 'show_meta', 'title_tag', 'image_position', 'image_size' ],
            array_filter( array_keys( $settings ), static fn( string $k ) => str_starts_with( $k, 'show_' ) || str_starts_with( $k, 'post_' ) || str_starts_with( $k, 'match_' ) || str_starts_with( $k, 'tax_' ) || str_starts_with( $k, 'exclude_' ) || str_starts_with( $k, 'users' ) || str_starts_with( $k, 'data_source' ) ),
            $style['handled_keys']
        ) );

        return $this->block( $id, 'divi/blog', $attrs );
    }
}
