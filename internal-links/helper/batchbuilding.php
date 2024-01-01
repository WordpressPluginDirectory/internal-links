<?php

namespace ILJ\Helper;

use  ActionScheduler ;
use  ActionScheduler_Store ;
use  ILJ\Backend\User ;
use  ILJ\Core\IndexBuilder ;
use  ILJ\Core\Options ;
use  ILJ\Core\ThemeCompat ;
use  ILJ\Database\LinkindexIndividualTemp ;
use  ILJ\Database\LinkindexTemp ;
use  ILJ\Database\Postmeta ;
use  ILJ\Database\Termmeta ;
use  ILJ\Enumeration\LinkType ;
/**
 * Batch Building helper
 *
 * Methods for handling batching of index builds
 *
 * @since   2.0.3
 * @package ILJ\Helper
 */
class BatchBuilding
{
    const  ILJ_FILTER_BUILDING_BATCH_SIZE = 'ilj_building_batch_size' ;
    const  ILJ_FILTER_FETCH_BATCH_KEYWORD_SIZE = 'ilj_fetch_batch_keyword_size' ;
    const  ILJ_FILTER_BATCH_SIZE = 'ilj_batch_size' ;
    const  ILJ_FILTER_BATCH_KEYWORD_SIZE = 'ilj_batch_keyword_size' ;
    /**
     * @var   int
     * @since 2.0.3
     */
    public  $building_batch_size ;
    /**
     * @var   int
     * @since 2.0.3
     */
    public  $fetch_batch_keyword_size ;
    /**
     * @var   int
     * @since 2.0.3
     */
    public  $batch_size ;
    /**
     * @var   int
     * @since 2.0.3
     */
    public  $batch_keyword_size ;
    public  $post_keyword_batch = 0 ;
    public  $term_keyword_batch = 0 ;
    public  $individual_data ;
    public function __construct()
    {
        $this->building_batch_size = 500;
        /**
         * Filter and change the building batch size
         *
         * @since 2.0.3
         *
         * @param int $building_batch_size
         */
        $this->building_batch_size = apply_filters( self::ILJ_FILTER_BUILDING_BATCH_SIZE, $this->building_batch_size );
        $this->fetch_batch_keyword_size = 100;
        /**
         * Filter and change the size of the fetched keywords size
         *
         * @since 2.0.3
         *
         * @param int $batch_keyword_size
         */
        $this->fetch_batch_keyword_size = apply_filters( self::ILJ_FILTER_FETCH_BATCH_KEYWORD_SIZE, $this->fetch_batch_keyword_size );
        $this->batch_size = 100;
        /**
         * Filter and change the batch size
         *
         * @since 2.0.3
         *
         * @param int $batch_size
         */
        $this->batch_size = apply_filters( self::ILJ_FILTER_BATCH_SIZE, $this->batch_size );
    }
    
    /**
     * Returns the batch keyword size
     *
     * @return void
     */
    public function get_fetch_batch_keyword_size()
    {
        return $this->fetch_batch_keyword_size;
    }
    
    /**
     * Set Scheduled Batch index builds
     *
     * @since 2.0.3
     * @return void
     */
    public function ilj_run_setting_batched_index_rebuild()
    {
        $start = microtime( true );
        LinkindexTemp::install_temp_db();
        $batch_build_info = new BatchInfo();
        $batch_build_info->resetBatchedFinished();
        $this->set_keyword_batching();
        $starting_type = $this->getStartingBuildType();
        self::ilj_set_batched_index_rebuild( array(
            "offset"         => 0,
            "start_time"     => $start,
            "type"           => $starting_type,
            "keyword_offset" => 0,
        ) );
    }
    
    /**
     * Rebuilds the Index Individually
     *
     * @param  array $data
     * @return void
     */
    function ilj_set_individual_index_rebuild( $data )
    {
        $start = microtime( true );
        LinkindexIndividualTemp::install_temp_db();
        
        if ( $data['link_type'] == LinkType::OUTGOING ) {
            $this->set_keyword_batching();
            $batch_build_info = new BatchInfo();
            $keyword_offset = 0;
            $meta = $data["type"];
            if ( isset( $data["meta"] ) ) {
                $meta = $data["meta"];
            }
            //Log individual link build for correct execution of delete_for_individual_builds and importIndexFromTemp
            if ( !LinkindexIndividualTemp::check_exists(
                $data["id"],
                0,
                "",
                $meta,
                "",
                $data['link_type']
            ) ) {
                LinkindexIndividualTemp::addRule(
                    $data["id"],
                    0,
                    "",
                    $meta,
                    "",
                    $data['link_type']
                );
            }
            for ( $x = 0 ;  $x < $this->post_keyword_batch ;  $x++ ) {
                as_enqueue_async_action( IndexBuilder::ILJ_INDIVIDUAL_INDEX_REBUILD_OUTGOING, array( array(
                    "id"                => $data["id"],
                    "type"              => $data["type"],
                    "batched_data_type" => $meta,
                    "start_time"        => $start,
                    "link_type"         => $data['link_type'],
                    "keyword_offset"    => $keyword_offset,
                    "keyword_type"      => "post",
                ) ), BatchInfo::ILJ_ASYNC_GROUP );
                $keyword_offset += $this->fetch_batch_keyword_size;
                $batch_build_info->incrementBatchCounter();
                $batch_build_info->updateBatchBuildInfo();
            }
            if ( !has_action( "action_scheduler_completed_action", [ $this, "ilj_after_scheduler_completed_action_individual" ] ) ) {
                add_action(
                    "action_scheduler_completed_action",
                    [ $this, "ilj_after_scheduler_completed_action_individual" ],
                    25,
                    1
                );
            }
        } elseif ( $data['link_type'] == LinkType::INCOMING ) {
            //Log individual link build for correct execution of delete_for_individual_builds and importIndexFromTemp
            if ( !LinkindexIndividualTemp::check_exists(
                0,
                $data["id"],
                "",
                "",
                $data["type"],
                $data['link_type']
            ) ) {
                LinkindexIndividualTemp::addRule(
                    0,
                    $data["id"],
                    "",
                    "",
                    $data["type"],
                    $data['link_type']
                );
            }
            $starting_type = $this->getStartingBuildType();
            $this->ilj_set_individual_index_rebuild_incoming( array(
                "id"         => $data["id"],
                "offset"     => 0,
                "start_time" => $start,
                "type"       => $data["type"],
                "build_type" => $starting_type,
                "link_type"  => $data['link_type'],
            ) );
        }
    
    }
    
    /**
     * Check whether to set build schedule for posts or term
     *
     * @param  mixed $data
     * @return void
     */
    public function ilj_set_individual_index_rebuild_incoming( $data )
    {
        $this->set_keyword_batching();
        
        if ( $data["build_type"] == "post" ) {
            $this->ilj_set_individual_post_index_rebuild( $data );
        } elseif ( $data["build_type"] == "term" ) {
            $this->ilj_set_individual_term_index_rebuild__premium_only( $data );
        } elseif ( $data["build_type"] == "post_meta" ) {
            $this->ilj_set_individual_post_meta_index_rebuild__premium_only( $data );
        } elseif ( $data["build_type"] == "term_meta" ) {
            $this->ilj_set_individual_term_meta_rebuild__premium_only( $data );
        }
    
    }
    
    /**
     * Set individual build schedules for posts until all posts batch are scheduled
     *
     * @param  mixed $data
     * @return void
     */
    public function ilj_set_individual_post_index_rebuild( $data )
    {
        $posts = IndexAsset::getPostsBatched( $this->building_batch_size, $data["offset"] );
        $data_ids = array_column( $posts, "ID" );
        $post_batches = array_chunk( $data_ids, $this->batch_size, true );
        $batch_build_info = new BatchInfo();
        foreach ( $post_batches as $index => $batch ) {
            as_enqueue_async_action( IndexBuilder::ILJ_INDIVIDUAL_INDEX_REBUILD_INCOMING, array( array(
                "id"                => $data["id"],
                "batched_data"      => $batch,
                "batched_data_type" => "post",
                "type"              => $data["type"],
                "build_type"        => $data["build_type"],
                "start_time"        => $data['start_time'],
                "link_type"         => $data['link_type'],
                "offset"            => $data['offset'],
            ) ), BatchInfo::ILJ_ASYNC_GROUP );
            $batch_build_info->incrementBatchCounter();
            $batch_build_info->updateBatchBuildInfo();
        }
        $data["offset"] += $this->building_batch_size;
        //Checking for possible next recursive schedule
        $posts = IndexAsset::getPostsBatched( $this->building_batch_size, $data["offset"] );
        
        if ( empty($posts) ) {
            if ( !has_action( 'action_scheduler_completed_action', array( $this, 'ilj_after_scheduler_completed_action_individual' ) ) ) {
                add_action(
                    'action_scheduler_completed_action',
                    array( $this, 'ilj_after_scheduler_completed_action_individual' ),
                    25,
                    1
                );
            }
            return;
        }
        
        as_enqueue_async_action( IndexBuilder::ILJ_SET_INDIVIDUAL_INDEX_REBUILD_INCOMING, array( array(
            "id"         => $data["id"],
            "offset"     => $data["offset"],
            "start_time" => $data['start_time'],
            "type"       => $data["type"],
            "build_type" => $data["build_type"],
            "link_type"  => $data['link_type'],
        ) ), BatchInfo::ILJ_ASYNC_GROUP );
    }
    
    /**
     * Triggers the individual index rebuild for outgoing
     *
     * @param  int    $id
     * @param  string $type
     * @param  string $option
     * @return void
     */
    public function ilj_individual_index_rebuild_outgoing( $data )
    {
        User::update( 'index', [
            'last_trigger' => new \DateTime(),
        ] );
        if ( !defined( "ILJ_THEME_COMPAT" ) ) {
            ThemeCompat::init();
        }
        $index_builder = new IndexBuilder();
        $index_builder->setBatchedKeywordOffset( $data["keyword_offset"] );
        $index_builder->setBatchedKeywordType( $data["keyword_type"] );
        $index_builder->setBatchedDataType( $data["batched_data_type"] );
        $index_builder->buildIndividualIndex( $data["id"], $data["type"], $data["link_type"] );
    }
    
    /**
     * Triggers the individual index rebuild for incoming
     *
     * @param  int    $id
     * @param  string $type
     * @param  string $option
     * @return void
     */
    public function ilj_individual_index_rebuild_incoming( $data )
    {
        User::update( 'index', [
            'last_trigger' => new \DateTime(),
        ] );
        if ( !defined( "ILJ_THEME_COMPAT" ) ) {
            ThemeCompat::init();
        }
        $index_builder = new IndexBuilder();
        $index_builder->setBatchedData( $data["batched_data"] );
        $index_builder->setBatchedDataType( $data["batched_data_type"] );
        $index_builder->buildIndividualIndex( $data["id"], $data["type"], $data["link_type"] );
    }
    
    /**
     * Calculate posts and term keyword batches count
     *
     * @return void
     */
    public function set_keyword_batching()
    {
        $post_keyword_count = Postmeta::getLinkDefinitionCount();
        $this->post_keyword_batch = (int) ($post_keyword_count / $this->fetch_batch_keyword_size);
        $batch_excess = $post_keyword_count % $this->fetch_batch_keyword_size;
        if ( $batch_excess > 0 ) {
            $this->post_keyword_batch++;
        }
    }
    
    /**
     * Set Scheduled Batch index builds for post/term
     *
     * @since 2.0.3
     * @return void
     */
    public function ilj_set_batched_index_rebuild( $data )
    {
        $this->set_keyword_batching();
        if ( $data["type"] == "post" ) {
            $this->ilj_set_batched_post_index_rebuild( $data );
        }
    }
    
    /**
     * Setup batched index per keyword offset
     *
     * @param  mixed $batch
     * @param  mixed $data
     * @return 
     */
    public function set_looped_keywords( $batch, $data )
    {
        $batch_build_info = new BatchInfo();
        $keyword_offset = 0;
        for ( $x = 0 ;  $x < $this->post_keyword_batch ;  $x++ ) {
            as_enqueue_async_action( IndexBuilder::ILJ_BUILD_BATCHED_INDEX, array( array(
                "batched_data"   => $batch,
                "type"           => $data["type"],
                "start_time"     => $data['start_time'],
                "keyword_offset" => $keyword_offset,
                "keyword_type"   => "post",
            ) ), BatchInfo::ILJ_ASYNC_GROUP );
            $keyword_offset += $this->fetch_batch_keyword_size;
            $batch_build_info->incrementBatchCounter();
            $batch_build_info->updateBatchBuildInfo();
        }
    }
    
    /**
     * Set Scheduled Batch build for posts
     *
     * @param  mixed $data Containing Offset, start time and type
     * @return void
     */
    public function ilj_set_batched_post_index_rebuild( $data )
    {
        $posts = IndexAsset::getPostsBatched( $this->building_batch_size, $data["offset"] );
        $data_ids = array_column( $posts, "ID" );
        $post_batches = array_chunk( $data_ids, $this->batch_size, true );
        foreach ( $post_batches as $index => $batch ) {
            $this->set_looped_keywords( $batch, $data );
        }
        $data["offset"] += $this->building_batch_size;
        //Checking for possible next recursive schedule
        $posts = IndexAsset::getPostsBatched( $this->building_batch_size, $data["offset"] );
        
        if ( empty($posts) ) {
            $nextBuild = $this->getNextBuildType( $data['type'] );
            if ( $nextBuild == '' ) {
                as_enqueue_async_action( IndexBuilder::ILJ_UPDATE_STATISTICS_INFO, array( array(
                    "switch" => true,
                ) ), BatchInfo::ILJ_ASYNC_GROUP );
            }
            return;
        }
        
        as_enqueue_async_action( IndexBuilder::ILJ_SET_BATCHED_INDEX_REBUILD, array( array(
            "offset"     => $data["offset"],
            "start_time" => $data['start_time'],
            "type"       => "post",
        ) ), BatchInfo::ILJ_ASYNC_GROUP );
    }
    
    /**
     * Executes the batch build per batched data
     *
     * @since 2.0.0
     * @param  array $data
     * @return void
     */
    public function ilj_build_batched_index( $data )
    {
        if ( !defined( "ILJ_THEME_COMPAT" ) ) {
            ThemeCompat::init();
        }
        $batch_build_info = new BatchInfo();
        $index_builder = new IndexBuilder();
        $index_builder->setBatchedData( $data["batched_data"] );
        $index_builder->setBatchedDataType( $data["type"] );
        $index_builder->setBatchedKeywordOffset( $data["keyword_offset"] );
        $index_builder->setBatchedKeywordType( $data["keyword_type"] );
        $index_builder->buildBatchedIndex();
        $batch_build_info->incrementBatchFinished();
        $updated = $batch_build_info->updateBatchBuildInfo();
    }
    
    /**
     * Update Statistics info and switchTable
     *
     * @return void
     */
    public function ilj_update_statistics_info( $data )
    {
        
        if ( $data['switch'] == true ) {
            LinkindexTemp::switchTableTemp();
        } elseif ( $data['switch'] == false ) {
            LinkindexIndividualTemp::importIndexFromTemp();
        }
        
        Statistic::updateStatisticsInfo();
    }
    
    /**
     * Add this call back to actionscheduler action_scheduler_completed_action hook
     *
     * @param  mixed $action_id
     * @return void
     */
    public function ilj_after_scheduler_completed_action_individual( $action_id )
    {
        $has_scheduled_actions = as_has_scheduled_action( IndexBuilder::ILJ_SET_INDIVIDUAL_INDEX_REBUILD );
        if ( $has_scheduled_actions ) {
            return;
        }
        $has_scheduled_actions = as_has_scheduled_action( IndexBuilder::ILJ_SET_INDIVIDUAL_INDEX_REBUILD_INCOMING );
        if ( $has_scheduled_actions ) {
            return;
        }
        $has_scheduled_actions = as_has_scheduled_action( IndexBuilder::ILJ_INDIVIDUAL_INDEX_REBUILD_OUTGOING );
        if ( $has_scheduled_actions ) {
            return;
        }
        $has_scheduled_actions = as_has_scheduled_action( IndexBuilder::ILJ_INDIVIDUAL_INDEX_REBUILD_INCOMING );
        if ( $has_scheduled_actions ) {
            return;
        }
        remove_all_actions( "action_scheduler_completed_action", 25 );
        if ( false === as_has_scheduled_action( IndexBuilder::ILJ_UPDATE_STATISTICS_INFO, array( array(
            "switch" => false,
        ) ), BatchInfo::ILJ_ASYNC_GROUP ) ) {
            as_enqueue_async_action( IndexBuilder::ILJ_UPDATE_STATISTICS_INFO, array( array(
                "switch" => false,
            ) ), BatchInfo::ILJ_ASYNC_GROUP );
        }
    }
    
    /**
     * Initiate the ILJ Full index Rebuild and Unschedule any ongoing batches
     *
     * @since 2.0.0
     * @return void
     */
    function initiate_ilj_batch_rebuild()
    {
        User::update( 'index', [
            'last_trigger' => new \DateTime(),
        ] );
        if ( !function_exists( 'as_has_scheduled_action' ) ) {
            return;
        }
        
        if ( true === as_has_scheduled_action( IndexBuilder::ILJ_RUN_SETTING_BATCHED_INDEX_REBUILD ) || true === as_has_scheduled_action( IndexBuilder::ILJ_SET_BATCHED_INDEX_REBUILD ) || true === as_has_scheduled_action( IndexBuilder::ILJ_BUILD_BATCHED_INDEX ) || true === as_has_scheduled_action( IndexBuilder::ILJ_DELETE_INDEX_BY_ID ) || true === as_has_scheduled_action( IndexBuilder::ILJ_SET_INDIVIDUAL_INDEX_REBUILD ) || true === as_has_scheduled_action( IndexBuilder::ILJ_INDIVIDUAL_DELETE_INDEX ) || true === as_has_scheduled_action( IndexBuilder::ILJ_INDIVIDUAL_INDEX_REBUILD_OUTGOING ) || true === as_has_scheduled_action( IndexBuilder::ILJ_SET_INDIVIDUAL_INDEX_REBUILD_INCOMING ) || true === as_has_scheduled_action( IndexBuilder::ILJ_INDIVIDUAL_INDEX_REBUILD_INCOMING ) ) {
            as_unschedule_all_actions( IndexBuilder::ILJ_RUN_SETTING_BATCHED_INDEX_REBUILD );
            as_unschedule_all_actions( IndexBuilder::ILJ_SET_BATCHED_INDEX_REBUILD );
            as_unschedule_all_actions( IndexBuilder::ILJ_BUILD_BATCHED_INDEX );
            as_unschedule_all_actions( IndexBuilder::ILJ_DELETE_INDEX_BY_ID );
            as_unschedule_all_actions( IndexBuilder::ILJ_SET_INDIVIDUAL_INDEX_REBUILD );
            as_unschedule_all_actions( IndexBuilder::ILJ_INDIVIDUAL_DELETE_INDEX );
            as_unschedule_all_actions( IndexBuilder::ILJ_INDIVIDUAL_INDEX_REBUILD_OUTGOING );
            as_unschedule_all_actions( IndexBuilder::ILJ_SET_INDIVIDUAL_INDEX_REBUILD_INCOMING );
            as_unschedule_all_actions( IndexBuilder::ILJ_INDIVIDUAL_INDEX_REBUILD_INCOMING );
            LinkindexTemp::flush();
        }
        
        $batch_build_info = new BatchInfo();
        $batch_build_info->setBatchCounter( 0 );
        $batch_build_info->resetBatchedFinished();
        $batch_build_info->updateBatchBuildInfo( "calculating" );
        as_enqueue_async_action( IndexBuilder::ILJ_RUN_SETTING_BATCHED_INDEX_REBUILD, array(), BatchInfo::ILJ_ASYNC_GROUP );
    }
    
    /**
     * getStartingBuildType
     *
     * @return string
     */
    public function getStartingBuildType()
    {
        $whitelist_post = Options::getOption( \ILJ\Core\Options\Whitelist::getKey() );
        if ( is_array( $whitelist_post ) || count( $whitelist_post ) ) {
            return "post";
        }
        return "";
    }
    
    /**
     * getNextBuildType
     *
     * @param  mixed $currentType
     * @return string
     */
    public function getNextBuildType( $currentType )
    {
        switch ( $currentType ) {
            case 'post':
                return '';
                break;
        }
        return '';
    }

}