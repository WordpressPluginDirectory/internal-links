<?php

namespace ILJ\Helper;

use  ILJ\Backend\Editor ;
use  ILJ\Core\LinkBuilder ;
use  ILJ\Core\Options ;
use  ILJ\Posttypes\CustomLinks ;
/**
 * This is where custom meta data output is filtered
 *
 * @package ILJ\Helper
 * @since   2.0.0
 */
class CustomMetaData
{
    /**
     * LinkBuilder for postmeta
     *
     * @var LinkBuilder
     */
    private  $link_builder_postmeta ;
    /**
     * LinkBuilder for termmeta
     *
     * @var LinkBuilder
     */
    private  $link_builder_termmeta ;
    const  ILJ_MUFFIN_BUILDER_META_FIELD = 'mfn-page-items' ;
    const  ILJ_OXYGEN_BUILDER_META_FIELD = 'ct_builder_shortcodes' ;
}