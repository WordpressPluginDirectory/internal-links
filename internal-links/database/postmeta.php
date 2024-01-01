<?php

namespace ILJ\Database;

use  ILJ\Core\Options ;
use  ILJ\Core\Options\Whitelist ;
use  ILJ\Helper\BatchBuilding ;
use  ILJ\Helper\BatchInfo ;
use  ILJ\Helper\Blacklist ;
/**
 * Postmeta wrapper for the inlink postmeta
 *
 * @package ILJ\Database
 * @since   1.0.0
 */
class Postmeta
{
    const  ILJ_META_KEY_LINKDEFINITION = 'ilj_linkdefinition' ;
    /**
     * Returns all Linkdefinitions from postmeta table
     *
     * @since  1.0.0
     * @return array
     */
    public static function getAllLinkDefinitions()
    {
        global  $wpdb ;
        $meta_key = self::ILJ_META_KEY_LINKDEFINITION;
        $public_post_types = Options::getOption( \ILJ\Core\Options\Whitelist::getKey() );
        
        if ( empty($public_post_types) ) {
            return array();
        } else {
            $public_post_types_list = "'" . implode( "','", $public_post_types ) . "'";
        }
        
        $query = "\r\n            SELECT postmeta.*\r\n            FROM {$wpdb->postmeta} postmeta\r\n            LEFT JOIN {$wpdb->posts} posts ON postmeta.post_id = posts.ID\r\n            WHERE postmeta.meta_key = '{$meta_key}'\r\n            AND posts.post_status = 'publish'\r\n            AND posts.post_type IN ({$public_post_types_list})\r\n        ";
        return $wpdb->get_results( $query );
    }
    
    /**
     * Returns all Linkdefinitions from postmeta table
     *
     * @since  1.0.0
     * @return array
     */
    public static function getAllLinkDefinitionsByBatch( $offset )
    {
        global  $wpdb ;
        $meta_key = self::ILJ_META_KEY_LINKDEFINITION;
        $public_post_types = Options::getOption( \ILJ\Core\Options\Whitelist::getKey() );
        $batch_building = new BatchBuilding();
        $limit = $batch_building->get_fetch_batch_keyword_size();
        
        if ( empty($public_post_types) ) {
            return array();
        } else {
            $public_post_types_list = "'" . implode( "','", $public_post_types ) . "'";
        }
        
        $query = "\r\n            SELECT postmeta.*\r\n            FROM {$wpdb->postmeta} postmeta\r\n            LEFT JOIN {$wpdb->posts} posts ON postmeta.post_id = posts.ID\r\n            WHERE postmeta.meta_key = '{$meta_key}'\r\n            AND posts.post_status = 'publish'\r\n            AND posts.post_type IN ({$public_post_types_list}) LIMIT {$offset} , {$limit} \r\n        ";
        return $wpdb->get_results( $query );
    }
    
    /**
     * getLinkDefinitionsById
     *
     * @param  int $id
     * @return array
     */
    public static function getLinkDefinitionsById( $id )
    {
        global  $wpdb ;
        $meta_key = self::ILJ_META_KEY_LINKDEFINITION;
        $public_post_types = Options::getOption( \ILJ\Core\Options\Whitelist::getKey() );
        
        if ( empty($public_post_types) ) {
            return array();
        } else {
            $public_post_types_list = "'" . implode( "','", $public_post_types ) . "'";
        }
        
        $query = "\r\n            SELECT postmeta.*\r\n            FROM {$wpdb->postmeta} postmeta\r\n            LEFT JOIN {$wpdb->posts} posts ON postmeta.post_id = posts.ID\r\n            WHERE postmeta.meta_key = '{$meta_key}'\r\n            AND posts.post_status = 'publish'\r\n            AND posts.post_type IN ({$public_post_types_list}) AND posts.ID = {$id}\r\n        ";
        return $wpdb->get_results( $query );
    }
    
    /**
     * Removes all link definitions from postmeta table
     *
     * @since  1.1.3
     * @return int
     */
    public static function removeAllLinkDefinitions()
    {
        global  $wpdb ;
        $meta_key = self::ILJ_META_KEY_LINKDEFINITION;
        return $wpdb->delete( $wpdb->postmeta, array(
            'meta_key' => $meta_key,
        ) );
    }
    
    public static function getLinkDefinitionCount()
    {
        global  $wpdb ;
        $meta_key = self::ILJ_META_KEY_LINKDEFINITION;
        $public_post_types = Options::getOption( \ILJ\Core\Options\Whitelist::getKey() );
        
        if ( empty($public_post_types) ) {
            return 0;
        } else {
            $public_post_types_list = "'" . implode( "','", $public_post_types ) . "'";
        }
        
        $query = "\r\n            SELECT COUNT(postmeta.meta_id)\r\n            FROM {$wpdb->postmeta} postmeta\r\n            LEFT JOIN {$wpdb->posts} posts ON postmeta.post_id = posts.ID\r\n            WHERE postmeta.meta_key = '{$meta_key}'\r\n            AND posts.post_status = 'publish'\r\n            AND posts.post_type IN ({$public_post_types_list})\r\n        ";
        return $wpdb->get_var( $query );
    }

}