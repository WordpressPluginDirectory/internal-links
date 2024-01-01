<?php

namespace ILJ\Helper;

use  ILJ\Backend\Editor ;
use  ILJ\Backend\Environment ;
use  ILJ\Database\Linkindex ;
use  ILJ\Database\Postmeta ;
use  ILJ\Database\Termmeta ;
/**
 * Statistics toolset
 *
 * Methods for providing statistics
 *
 * @package ILJ\Helper
 * @since   1.0.0
 */
class Statistic
{
    /**
     * Returns the amount of configured keywords
     *
     * @since  1.1.3
     * @return int
     */
    public static function getConfiguredKeywordsCount()
    {
        $configuredKeywords = [];
        $postmeta = Postmeta::getAllLinkDefinitions();
        foreach ( $postmeta as $meta ) {
            $keywords = get_post_meta( $meta->post_id, Postmeta::ILJ_META_KEY_LINKDEFINITION, true );
            if ( is_array( $keywords ) ) {
                $configuredKeywords = array_merge( $configuredKeywords, $keywords );
            }
        }
        return count( $configuredKeywords );
    }
    
    /**
     * Returns the count of configured keywords by a given asset type
     *
     * @since 1.2.5
     * @param int    $asset_id   The Id of the asset
     * @param string $asset_type The type of the asset
     *
     * @return int
     */
    public static function getConfiguredKeywordsCountForAsset( $asset_id, $asset_type )
    {
        $allowed_asset_types = [ 'post' ];
        if ( !in_array( $asset_type, $allowed_asset_types ) ) {
            return 0;
        }
        $data = get_post_meta( $asset_id, Postmeta::ILJ_META_KEY_LINKDEFINITION );
        $count = 0;
        if ( is_array( $data ) && count( $data ) != 0 ) {
            $count = count( $data[0] );
        }
        return $count;
    }
    
    /**
     * Returns the statistics for links
     *
     * @since  1.1.0
     * @param  int $results Number of results to display
     * @param  int $page    Number of page to display
     * @return array
     */
    public static function getLinkStatistics( $results = -1, $page = 0 )
    {
        $page = ( $page > 0 ? $page : 1 );
        $limit = (int) $results;
        $offset = (int) ($page - 1) * $results;
        $links = Linkindex::getGroupedCountFull( 'elements_to', $limit, $offset );
        return $links;
    }
    
    /**
     * Returns the statistics for anchor texts
     *
     * @since  1.1.0
     * @param  int $results
     * @param  int $page
     * @return array
     */
    public static function getAnchorStatistics( $results = -1, $page = 0 )
    {
        $page = ( $page > 0 ? $page : 1 );
        $limit = (int) $results;
        $offset = (int) ($page - 1) * $results;
        $anchors = Linkindex::getAnchorCountFull();
        return $anchors;
    }
    
    /**
     * A configureable wrapper for the aggregation of columns of the linkindex
     *
     * @deprecated
     * @since      1.0.0
     * @param      array $args Configuration of the selection
     * @return     array
     */
    public static function getAggregatedCount( $args = array() )
    {
        $defaults = [
            "type"  => "link_from",
            "limit" => 10,
        ];
        $args = wp_parse_args( $args, $defaults );
        extract( $args );
        if ( !is_numeric( $limit ) ) {
            $limit = $defaults['limit'];
        }
        $inlinks = Linkindex::getGroupedCount( $type );
        return array_slice( $inlinks, 0, $limit );
    }
    
    /**
     * getLinkIndexCount
     *
     * @return int
     */
    public static function getLinkIndexCount()
    {
        global  $wpdb ;
        $ilj_linkindex_table = $wpdb->prefix . Linkindex::ILJ_DATABASE_TABLE_LINKINDEX;
        $index_count = $wpdb->get_var( "SELECT count(*) FROM {$ilj_linkindex_table}" );
        return (int) $index_count;
    }
    
    /**
     * update Statistics Info
     *
     * @param  mixed $start
     * @return void
     */
    public static function updateStatisticsInfo( $start = null )
    {
        if ( $start == null ) {
            $start = microtime( true );
        }
        $index_count = self::getLinkIndexCount();
        $duration = round( microtime( true ) - $start, 2 );
        $offset = get_option( 'gmt_offset' );
        $hours = (int) $offset;
        $minutes = ($offset - floor( $offset )) * 60;
        $feedback = [
            "last_update" => [
            "date"     => new \DateTime( 'now', new \DateTimeZone( sprintf( '%+03d:%02d', $hours, $minutes ) ) ),
            "entries"  => $index_count,
            "duration" => $duration,
        ],
        ];
        Environment::update( 'linkindex', $feedback );
    }
    
    /**
     * Reset Linkindex info
     *
     * @return void
     */
    public static function reset_statistics_info()
    {
        $offset = get_option( 'gmt_offset' );
        $hours = floatval( $offset );
        $minutes = ($offset - floor( $offset )) * 60;
        $default_data = [
            "last_update" => [
            "date"     => new \DateTime( 'now', new \DateTimeZone( sprintf( '%+03d:%02d', $hours, $minutes ) ) ),
            "entries"  => 0,
            "duration" => 0,
        ],
        ];
        Environment::update( 'linkindex', $default_data );
    }

}