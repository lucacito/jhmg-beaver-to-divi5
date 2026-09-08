<?php
/**
 * A place conversion input comes from.
 *
 * Implementations normalise their input to the item shape the pipeline
 * expects, so ConversionPreflight never needs to know whether the work arrived
 * as an upload or was read off a post already on this site.
 */

namespace BeaverDivi5Converter\Conversion;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface ConversionSource {

    /**
     * @return array[] Items shaped
     *   ['title','post_type','post_name','template_type','nodes','settings','error','source_ref'].
     *   Returns [] when there is nothing to convert — never throws.
     */
    public function items(): array;
}
