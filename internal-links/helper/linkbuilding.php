<?php
namespace ILJ\Helper;

use ILJ\Core\LinkBuilder;

/**
 * Toolset for LinkBuilding
 *
 * Methods for handling Link building for frontend
 *
 * @package ILJ\Helper
 * @since   2.0.3
 */
class LinkBuilding
{
    /**
     * Applies the linkbuilder to a piece of content
     *
     * @since 1.2.19
     * @param  mixed $content The content of an post or page
     * @return string
     */
    public function linkContent($content){
	    if (is_admin()) {
		    return $content;
	    }

        if(self::excludeLinkBuilderFilter()){
            return $content;
        }

        $link_builder = new LinkBuilder(get_the_ID(), 'post');
        return $link_builder->linkContent($content);
    }
    
    /**
     * Handles linking temporarily created link index to currently building content to determine the links already built on paragraphs
     *
     * @param  mixed $content
     * @param  mixed $id
     * @param  mixed $type
     * @param  mixed $build_type
     * @return void
     */
    public static function linkContentTemp($content, $id, $type, $build_type){
        if(self::excludeLinkBuilderFilter()){
            return $content;
        }

        $link_builder = new LinkBuilder($id, $type, $build_type);
        return $link_builder->linkContent($content);
    }
    
    
    /**
     * Excludes sitemap urls from applying the link builder filter
     *
     * @return bool
     */
    public static function excludeLinkBuilderFilter(){

        global $wp;
        $link = home_url( $wp->request );
        $match = preg_match('/[a-zA-Z0-9_]*-sitemap(?:[0-9]*|_index).xml/', strtolower($link));

        return $match;
    }
    


}