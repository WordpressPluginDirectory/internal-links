<?php

namespace ILJ\Core;

use  ActionScheduler ;
use  ActionScheduler_Store ;
use  ILJ\Backend\AdminMenu ;
use  ILJ\Backend\Editor ;
use  ILJ\Backend\MenuPage\Tools ;
use  ILJ\Backend\RatingNotifier ;
use  ILJ\Backend\Environment ;
use  ILJ\Helper\Capabilities ;
use  ILJ\Backend\Menupage\Settings ;
use  ILJ\Core\Options\CustomFieldsToLinkPost ;
use  ILJ\Core\Options\TaxonomyWhitelist ;
use  ILJ\Core\Options\Whitelist ;
use  ILJ\Database\Keywords ;
use  ILJ\Database\Linkindex ;
use  ILJ\Enumeration\LinkType ;
use  ILJ\Enumeration\TagExclusion ;
use  ILJ\Helper\BatchBuilding ;
use  ILJ\Helper\BatchInfo as HelperBatchInfo ;
use  ILJ\Helper\Blacklist ;
use  ILJ\Helper\CustomMetaData ;
use  ILJ\Helper\IndexAsset ;
use  ILJ\Helper\LinkBuilding ;
use  ILJ\Helper\Options as OptionHelper ;
use  ILJ\Helper\Replacement ;
use  ILJ\Helper\Statistic ;
use  ILJ\Posttypes\CustomLinks ;
/**
 * The main app
 *
 * Coordinates all steps for the plugin usage
 *
 * @package ILJ\Core
 *
 * @since 1.0.1
 */
class App
{
    private static  $instance = null ;
    const  ILJ_FILTER_BATCH_SIZE = 'ilj_batch_size' ;
    /**
     * Initializes the construction of the app
     *
     * @static
     * @since  1.0.1
     *
     * @return void
     */
    public static function init()
    {
        if ( null !== self::$instance ) {
            return;
        }
        self::$instance = new self();
        $last_version = Environment::get( 'last_version' );
        
        if ( $last_version != ILJ_VERSION ) {
            ilj_install_db();
            Options::setOptionsDefault();
        }
    
    }
    
    protected function __construct()
    {
        $this->initSettings();
        $this->loadIncludes();
        add_action( 'admin_init', [ '\\ILJ\\Core\\Options', 'init' ] );
        add_action( 'admin_init', [ '\\ILJ\\Backend\\Editor', 'addAssets' ] );
        add_action( 'future_to_publish', [ $this, 'publishFuturePost' ], 99 );
        add_action( 'plugins_loaded', [ $this, 'afterPluginsLoad' ] );
        add_action( 'after_setup_theme', [ $this, 'afterThemesLoad' ] );
        add_action(
            IndexBuilder::ILJ_SET_INDIVIDUAL_INDEX_REBUILD,
            [ new BatchBuilding(), "ilj_set_individual_index_rebuild" ],
            10,
            1
        );
        add_action(
            IndexBuilder::ILJ_INDIVIDUAL_INDEX_REBUILD_OUTGOING,
            [ new BatchBuilding(), "ilj_individual_index_rebuild_outgoing" ],
            10,
            1
        );
        add_action(
            IndexBuilder::ILJ_INDIVIDUAL_INDEX_REBUILD_INCOMING,
            [ new BatchBuilding(), "ilj_individual_index_rebuild_incoming" ],
            10,
            1
        );
        add_action(
            IndexBuilder::ILJ_SET_INDIVIDUAL_INDEX_REBUILD_INCOMING,
            [ new BatchBuilding(), "ilj_set_individual_index_rebuild_incoming" ],
            10,
            1
        );
        add_action( IndexBuilder::ILJ_INITIATE_BATCH_REBUILD, [ new BatchBuilding(), "initiate_ilj_batch_rebuild" ], 10 );
        add_action( IndexBuilder::ILJ_RUN_SETTING_BATCHED_INDEX_REBUILD, [ new BatchBuilding(), "ilj_run_setting_batched_index_rebuild" ], 10 );
        add_action(
            IndexBuilder::ILJ_SET_BATCHED_INDEX_REBUILD,
            [ new BatchBuilding(), "ilj_set_batched_index_rebuild" ],
            10,
            1
        );
        add_action(
            IndexBuilder::ILJ_BUILD_BATCHED_INDEX,
            [ new BatchBuilding(), "ilj_build_batched_index" ],
            10,
            1
        );
        add_action(
            IndexBuilder::ILJ_UPDATE_STATISTICS_INFO,
            [ new BatchBuilding(), "ilj_update_statistics_info" ],
            10,
            1
        );
        //Used by blacklist options
        add_action(
            IndexBuilder::ILJ_DELETE_INDEX_BY_ID,
            [ $this, "ilj_delete_index_by_id" ],
            10,
            1
        );
        //Used by post/terms in individual index rebuilds
        add_action(
            IndexBuilder::ILJ_INDIVIDUAL_DELETE_INDEX,
            [ $this, "ilj_individual_delete_index" ],
            10,
            1
        );
        add_action( IndexBuilder::ILJ_ACTION_AFTER_INDEX_BUILT, function () {
            $batch_build_info = new HelperBatchInfo();
            $batch_build_info->incrementBatchFinished();
            $batch_build_info->updateBatchBuildInfo();
            Statistic::updateStatisticsInfo();
        }, 10 );
    }
    
    /**
     * Initialising all menu and settings related stuff
     *
     * @since 1.0.1
     *
     * @return void
     */
    protected function initSettings()
    {
        add_action( 'admin_menu', [ '\\ILJ\\Backend\\AdminMenu', 'init' ] );
        add_filter( 'plugin_action_links_' . ILJ_NAME, [ $this, 'addSettingsLink' ] );
    }
    
    /**
     * Loads all include files
     *
     * @since 1.0.1
     *
     * @return void
     */
    public function loadIncludes()
    {
        require_once ILJ_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
        $include_files = [ 'install', 'uninstall' ];
        foreach ( $include_files as $file ) {
            include_once ILJ_PATH . 'includes/' . $file . '.php';
        }
    }
    
    /**
     * Handles post transitions for scheduled posts
     *
     * @since 1.1.5
     * @param object $post
     *
     * @return void
     */
    public function publishFuturePost( $post )
    {
        if ( !$this->postAffectsIndex( $post->ID ) ) {
            return;
        }
        $whitelisted_post_types = \ILJ\Core\Options::getOption( Whitelist::getKey() );
        if ( !in_array( get_post_type( $post->ID ), $whitelisted_post_types ) ) {
            return;
        }
        $batch_build_info = new HelperBatchInfo();
        
        if ( false === as_has_scheduled_action( IndexBuilder::ILJ_SET_INDIVIDUAL_INDEX_REBUILD, array( array(
            "id"        => $post->ID,
            "type"      => "post",
            "link_type" => LinkType::INCOMING,
        ) ), HelperBatchInfo::ILJ_ASYNC_GROUP ) ) {
            $batch_build_info->incrementBatchCounter();
            $batch_build_info->updateBatchBuildInfo();
            as_enqueue_async_action( IndexBuilder::ILJ_SET_INDIVIDUAL_INDEX_REBUILD, array( array(
                "id"        => $post->ID,
                "type"      => "post",
                "link_type" => LinkType::INCOMING,
            ) ), HelperBatchInfo::ILJ_ASYNC_GROUP );
        }
        
        if ( Blacklist::checkIfBlacklisted( "post", $post->ID ) ) {
            return;
        }
        
        if ( false === as_has_scheduled_action( IndexBuilder::ILJ_SET_INDIVIDUAL_INDEX_REBUILD, array( array(
            "id"        => $post->ID,
            "type"      => "post",
            "link_type" => LinkType::OUTGOING,
        ) ), HelperBatchInfo::ILJ_ASYNC_GROUP ) ) {
            $batch_build_info->incrementBatchCounter();
            as_enqueue_async_action( IndexBuilder::ILJ_SET_INDIVIDUAL_INDEX_REBUILD, array( array(
                "id"        => $post->ID,
                "type"      => "post",
                "link_type" => LinkType::OUTGOING,
            ) ), HelperBatchInfo::ILJ_ASYNC_GROUP );
        }
        
        $batch_build_info->updateBatchBuildInfo();
    }
    
    /**
     * Gets called after all plugins are loaded for registering actions and filter
     *
     * @since 1.0.1
     *
     * @return void
     */
    public function afterPluginsLoad()
    {
        Compat::init();
        RatingNotifier::init();
        $this->registerActions();
        $this->registerFilter();
        load_plugin_textdomain( 'internal-links', false, false );
    }
    
    /**
     * Gets called after themes are loaded for registering actions and filter
     *
     * @since 1.3.11
     * @return void
     */
    public function afterThemesLoad()
    {
        ThemeCompat::init();
    }
    
    /**
     * Registers all actions for the plugin
     *
     * @since 1.1.5
     *
     * @return void
     */
    protected function registerActions()
    {
        $capability = current_user_can( 'administrator' );
        add_action( 'admin_post_' . Options::KEY, array( '\\ILJ\\Helper\\Post', 'option_actions' ) );
        if ( !$capability ) {
            return;
        }
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueueAdminBarScripts' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAdminBarScripts' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueueRebuildIndexAssets' ] );
        add_action( 'load-post.php', [ '\\ILJ\\Backend\\Editor', 'addKeywordMetaBox' ] );
        add_action( 'load-post-new.php', [ '\\ILJ\\Backend\\Editor', 'addKeywordMetaBox' ] );
        add_action(
            'save_post',
            [ '\\ILJ\\Backend\\Editor', 'saveKeywordMeta' ],
            10,
            2
        );
        add_action( 'wp_ajax_ilj_search_posts', [ '\\ILJ\\Helper\\Ajax', 'searchPostsAction' ] );
        add_action( 'wp_ajax_ilj_hide_promo', [ '\\ILJ\\Helper\\Ajax', 'hidePromo' ] );
        add_action( 'wp_loaded', [ '\\ILJ\\Backend\\Column', 'addConfiguredLinksColumn' ] );
        add_action( 'wp_ajax_ilj_rating_notification_add', [ '\\ILJ\\Helper\\Ajax', 'ratingNotificationAdd' ] );
        add_action( 'wp_ajax_ilj_upload_import', [ '\\ILJ\\Helper\\Ajax', 'uploadImport' ] );
        add_action( 'wp_ajax_ilj_start_import', [ '\\ILJ\\Helper\\Ajax', 'startImport' ] );
        add_action( 'wp_ajax_ilj_export_settings', [ '\\ILJ\\Helper\\Ajax', 'exportSettings' ] );
        add_action( 'wp_ajax_ilj_render_link_detail_statistic', [ '\\ILJ\\Helper\\Ajax', 'renderLinkDetailStatisticAction' ] );
        add_action( 'wp_ajax_ilj_render_links_statistic', [ '\\ILJ\\Helper\\Ajax', 'renderLinksStatisticAction' ] );
        add_action( 'wp_ajax_ilj_render_anchor_detail_statistic', [ '\\ILJ\\Helper\\Ajax', 'renderAnchorDetailStatisticAction' ] );
        add_action( 'wp_ajax_ilj_render_anchors_statistic', [ '\\ILJ\\Helper\\Ajax', 'renderAnchorsStatistic' ] );
        add_action( 'wp_ajax_ilj_rebuild_index', [ '\\ILJ\\Helper\\Ajax', 'indexRebuildAction' ] );
        add_action(
            'updated_option',
            [ 'ILJ\\Helper\\Options', 'updateOptionIndexRebuild' ],
            10,
            3
        );
        $hide_status_bar = Options::getOption( \ILJ\Core\Options\HideStatusBar::getKey() );
        
        if ( !$hide_status_bar ) {
            add_action( 'admin_bar_menu', [ '\\ILJ\\Backend\\AdminBar', 'addLink' ], 999 );
            add_action( 'wp_ajax_ilj_render_batch_info', [ '\\ILJ\\Helper\\Ajax', 'renderBatchInfo' ] );
        }
        
        $this->addPostIndexTrigger();
        add_action( CustomFieldsToLinkPost::ILJ_ACTION_ADD_PRO_FEATURES, function () {
            echo  '<li><span>' . __( 'Activate custom fields', 'internal-links' ) . '</span>: ' . __( 'Maximize compatibility with builders, themes and plugins enabling linking from <strong>custom fields</strong>.', 'internal-links' ) . '</li>' ;
        }, 10 );
    }
    
    /**
     * Enqueue the admin bar scripts and style
     *
     * @return void
     */
    function enqueueAdminBarScripts()
    {
        $hide_status_bar = Options::getOption( \ILJ\Core\Options\HideStatusBar::getKey() );
        
        if ( !$hide_status_bar ) {
            wp_enqueue_style(
                'ilj_admin_menu_bar_style',
                ILJ_URL . 'admin/css/ilj_admin_menu_bar.css',
                [],
                ILJ_VERSION
            );
            wp_register_script(
                'ilj_admin_menu_bar_script',
                ILJ_URL . 'admin/js/ilj_admin_menu_bar.js',
                array( 'jquery' ),
                ILJ_VERSION
            );
            wp_enqueue_script( 'ilj_admin_menu_bar_script' );
            wp_localize_script( 'ilj_admin_menu_bar_script', 'ilj_ajax_object', array(
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
            ) );
        }
    
    }
    
    /**
     * Registers all assets for the frontend rebuild notification
     *
     * @return void
     * @since  2.0.0
     */
    function enqueueRebuildIndexAssets()
    {
        $current_screen = get_current_screen();
        if ( $current_screen->base != "toplevel_page_internal_link_juicer" ) {
            return;
        }
        wp_register_script(
            'ilj_index_rebuild_button',
            ILJ_URL . 'admin/js/ilj_ajax_index_rebuild.js',
            [],
            ILJ_VERSION
        );
        wp_enqueue_script( 'ilj_index_rebuild_button' );
    }
    
    /**
     * Delete incoming and outgoing link index by ID
     *
     * @param  array $data
     * @return void
     */
    function ilj_delete_index_by_id( $data )
    {
        Linkindex::delete_link_by_id( $data['id'], $data['type'] );
        $batch_build_info = new HelperBatchInfo();
        $batch_build_info->updateBatchBuildInfo();
    }
    
    /**
     * Delete outgoing link index by ID directly from current linkindex
     *
     * @param  array $data
     * @return void
     */
    function ilj_individual_delete_index( $data )
    {
        Linkindex::delete_link_from( $data['id'], $data['type'] );
    }
    
    /**
     * Triggers all actions for automatic index building mode.
     *
     * @since  1.1.0
     * @return void
     */
    protected function addPostIndexTrigger()
    {
        add_action(
            Editor::ILJ_ACTION_AFTER_KEYWORDS_UPDATE,
            function ( $id, $type, $status ) {
            
            if ( $status == 'publish' ) {
                $batch_build_info = new HelperBatchInfo();
                
                if ( false === as_has_scheduled_action( IndexBuilder::ILJ_SET_INDIVIDUAL_INDEX_REBUILD, array( array(
                    "id"        => $id,
                    "type"      => $type,
                    "link_type" => LinkType::INCOMING,
                ) ), HelperBatchInfo::ILJ_ASYNC_GROUP ) ) {
                    $batch_build_info->incrementBatchCounter();
                    $batch_build_info->updateBatchBuildInfo();
                    as_enqueue_async_action( IndexBuilder::ILJ_SET_INDIVIDUAL_INDEX_REBUILD, array( array(
                        "id"        => $id,
                        "type"      => $type,
                        "link_type" => LinkType::INCOMING,
                    ) ), HelperBatchInfo::ILJ_ASYNC_GROUP );
                }
            
            }
        
        },
            100,
            3
        );
        add_action(
            'post_updated',
            function ( $post_id, $post_after, $post_before ) {
            
            if ( $post_after->post_content != $post_before->post_content ) {
                $whitelisted_post_types = \ILJ\Core\Options::getOption( Whitelist::getKey() );
                
                if ( !empty($whitelisted_post_types) ) {
                    if ( !in_array( get_post_type( $post_id ), $whitelisted_post_types ) ) {
                        return;
                    }
                    if ( Blacklist::checkIfBlacklisted( "post", $post_id ) ) {
                        return;
                    }
                    $batch_build_info = new HelperBatchInfo();
                    
                    if ( false === as_has_scheduled_action( IndexBuilder::ILJ_SET_INDIVIDUAL_INDEX_REBUILD, array( array(
                        "id"        => $post_id,
                        "type"      => "post",
                        "link_type" => LinkType::OUTGOING,
                    ) ), HelperBatchInfo::ILJ_ASYNC_GROUP ) ) {
                        $batch_build_info->incrementBatchCounter();
                        as_enqueue_async_action( IndexBuilder::ILJ_SET_INDIVIDUAL_INDEX_REBUILD, array( array(
                            "id"        => $post_id,
                            "type"      => "post",
                            "link_type" => LinkType::OUTGOING,
                        ) ), HelperBatchInfo::ILJ_ASYNC_GROUP );
                    }
                    
                    $batch_build_info->updateBatchBuildInfo();
                }
            
            }
        
        },
            20,
            3
        );
        //rebuild index after keyword meta got updated on gutenberg editor:
        add_action(
            'updated_post_meta',
            function (
            $meta_id,
            $post_id,
            $meta_key,
            $meta_value
        ) {
            if ( !is_admin() || !function_exists( 'get_current_screen' ) ) {
                return;
            }
            $current_screen = get_current_screen();
            $batch_build_info = new HelperBatchInfo();
            $incoming_metas = array( Editor::ILJ_META_KEY_LIMITINCOMINGLINKS, Editor::ILJ_META_KEY_MAXINCOMINGLINKS );
            $outgoing_metas = array(
                Editor::ILJ_META_KEY_BLACKLISTDEFINITION,
                Editor::ILJ_META_KEY_LIMITLINKSPERPARAGRAPH,
                Editor::ILJ_META_KEY_LINKSPERPARAGRAPH,
                Editor::ILJ_META_KEY_LIMITOUTGOINGLINKS,
                Editor::ILJ_META_KEY_MAXOUTGOINGLINKS
            );
            $whitelisted_post_types = \ILJ\Core\Options::getOption( Whitelist::getKey() );
            
            if ( !empty($whitelisted_post_types) ) {
                if ( !in_array( $meta_key, $outgoing_metas ) && !in_array( $meta_key, $incoming_metas ) ) {
                    return;
                }
                if ( !in_array( get_post_type( $post_id ), $whitelisted_post_types ) ) {
                    return;
                }
                if ( !in_array( $meta_key, $incoming_metas ) ) {
                    
                    if ( in_array( $meta_key, $outgoing_metas ) ) {
                        if ( Blacklist::checkIfBlacklisted( "post", $post_id ) ) {
                            return;
                        }
                        
                        if ( false === as_has_scheduled_action( IndexBuilder::ILJ_SET_INDIVIDUAL_INDEX_REBUILD, array( array(
                            "id"        => $post_id,
                            "type"      => "post",
                            "link_type" => LinkType::OUTGOING,
                        ) ), HelperBatchInfo::ILJ_ASYNC_GROUP ) ) {
                            $batch_build_info->incrementBatchCounter();
                            as_enqueue_async_action( IndexBuilder::ILJ_SET_INDIVIDUAL_INDEX_REBUILD, array( array(
                                "id"        => $post_id,
                                "type"      => "post",
                                "link_type" => LinkType::OUTGOING,
                            ) ), HelperBatchInfo::ILJ_ASYNC_GROUP );
                        }
                        
                        $batch_build_info->updateBatchBuildInfo();
                        return;
                    } else {
                        return;
                    }
                
                }
                if ( !$this->postAffectsIndex( $post_id ) ) {
                    return;
                }
                if ( in_array( $meta_key, $incoming_metas ) ) {
                    
                    if ( false === as_has_scheduled_action( IndexBuilder::ILJ_SET_INDIVIDUAL_INDEX_REBUILD, array( array(
                        "id"        => $post_id,
                        "type"      => "post",
                        "link_type" => LinkType::INCOMING,
                    ) ), HelperBatchInfo::ILJ_ASYNC_GROUP ) ) {
                        $batch_build_info->incrementBatchCounter();
                        $batch_build_info->updateBatchBuildInfo();
                        as_enqueue_async_action( IndexBuilder::ILJ_SET_INDIVIDUAL_INDEX_REBUILD, array( array(
                            "id"        => $post_id,
                            "type"      => "post",
                            "link_type" => LinkType::INCOMING,
                        ) ), HelperBatchInfo::ILJ_ASYNC_GROUP );
                    }
                
                }
            }
        
        },
            30,
            4
        );
        add_action( Linkindex::ILJ_ACTION_AFTER_DELETE_LINKINDEX, function () {
            Statistic::updateStatisticsInfo();
        }, 10 );
        add_action(
            'transition_post_status',
            function ( $new_status, $old_status, $post ) {
            if ( $old_status == 'publish' && $new_status == 'publish' || $old_status == 'new' && $new_status == 'auto-draft' || $old_status == 'draft' && $new_status == 'draft' ) {
                return;
            }
            $whitelisted_post_types = \ILJ\Core\Options::getOption( Whitelist::getKey() );
            
            if ( !empty($whitelisted_post_types) ) {
                $batch_build_info = new HelperBatchInfo();
                if ( !in_array( get_post_type( $post->ID ), $whitelisted_post_types ) ) {
                    return;
                }
                $type = "post";
                
                if ( $new_status == 'trash' ) {
                    $batch_build_info->incrementBatchCounter();
                    $updated = $batch_build_info->updateBatchBuildInfo();
                    if ( $updated ) {
                        if ( false === as_has_scheduled_action( IndexBuilder::ILJ_DELETE_INDEX_BY_ID, array( array(
                            "id"   => $post->ID,
                            "type" => $type,
                        ) ), HelperBatchInfo::ILJ_ASYNC_GROUP ) ) {
                            as_enqueue_async_action( IndexBuilder::ILJ_DELETE_INDEX_BY_ID, array( array(
                                "id"   => $post->ID,
                                "type" => $type,
                            ) ), HelperBatchInfo::ILJ_ASYNC_GROUP );
                        }
                    }
                    return;
                }
                
                
                if ( $old_status == 'publish' && $new_status == 'draft' || $old_status == 'trash' && $new_status == 'draft' ) {
                    if ( Blacklist::checkIfBlacklisted( "post", $post->ID ) ) {
                        return;
                    }
                    
                    if ( $type == "post" ) {
                        
                        if ( false === as_has_scheduled_action( IndexBuilder::ILJ_SET_INDIVIDUAL_INDEX_REBUILD, array( array(
                            "id"        => $post->ID,
                            "type"      => "post",
                            "link_type" => LinkType::OUTGOING,
                        ) ), HelperBatchInfo::ILJ_ASYNC_GROUP ) ) {
                            $batch_build_info->incrementBatchCounter();
                            as_enqueue_async_action( IndexBuilder::ILJ_SET_INDIVIDUAL_INDEX_REBUILD, array( array(
                                "id"        => $post->ID,
                                "type"      => "post",
                                "link_type" => LinkType::OUTGOING,
                            ) ), HelperBatchInfo::ILJ_ASYNC_GROUP );
                        }
                        
                        $batch_build_info->updateBatchBuildInfo();
                    }
                    
                    return;
                }
                
                
                if ( $old_status == 'trash' && $new_status == 'publish' ) {
                    if ( Blacklist::checkIfBlacklisted( "post", $post->ID ) ) {
                        return;
                    }
                    
                    if ( $type == "post" ) {
                        
                        if ( false === as_has_scheduled_action( IndexBuilder::ILJ_SET_INDIVIDUAL_INDEX_REBUILD, array( array(
                            "id"        => $post->ID,
                            "type"      => "post",
                            "link_type" => LinkType::OUTGOING,
                        ) ), HelperBatchInfo::ILJ_ASYNC_GROUP ) ) {
                            $batch_build_info->incrementBatchCounter();
                            as_enqueue_async_action( IndexBuilder::ILJ_SET_INDIVIDUAL_INDEX_REBUILD, array( array(
                                "id"        => $post->ID,
                                "type"      => "post",
                                "link_type" => LinkType::OUTGOING,
                            ) ), HelperBatchInfo::ILJ_ASYNC_GROUP );
                        }
                        
                        $batch_build_info->updateBatchBuildInfo();
                    }
                
                }
                
                if ( $new_status == 'draft' ) {
                    return;
                }
                
                if ( false === as_has_scheduled_action( IndexBuilder::ILJ_SET_INDIVIDUAL_INDEX_REBUILD, array( array(
                    "id"        => $post->ID,
                    "type"      => "post",
                    "link_type" => LinkType::INCOMING,
                ) ), HelperBatchInfo::ILJ_ASYNC_GROUP ) ) {
                    $batch_build_info->incrementBatchCounter();
                    $batch_build_info->updateBatchBuildInfo();
                    as_enqueue_async_action( IndexBuilder::ILJ_SET_INDIVIDUAL_INDEX_REBUILD, array( array(
                        "id"        => $post->ID,
                        "type"      => "post",
                        "link_type" => LinkType::INCOMING,
                    ) ), HelperBatchInfo::ILJ_ASYNC_GROUP );
                }
            
            }
        
        },
            40,
            3
        );
    }
    
    /**
     * Registers plugin relevant filters
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function registerFilter()
    {
        add_filter( 'the_content', [ new LinkBuilding(), 'linkContent' ], 99 );
        add_filter(
            'ilj_get_the_content',
            [ new LinkBuilding(), 'linkContent' ],
            99,
            1
        );
        $tag_exclusions = Options::getOption( \ILJ\Core\Options\NoLinkTags::getKey() );
        if ( is_array( $tag_exclusions ) && count( $tag_exclusions ) ) {
            add_filter( Replacement::ILJ_FILTER_EXCLUDE_TEXT_PARTS, function ( $search_parts ) use( $tag_exclusions ) {
                foreach ( $tag_exclusions as $tag_exclusion ) {
                    $regex = TagExclusion::getRegex( $tag_exclusion );
                    if ( $regex ) {
                        $search_parts[] = $regex;
                    }
                }
                return $search_parts;
            } );
        }
        \ILJ\ilj_fs()->add_filter( 'reshow_trial_after_every_n_sec', function ( $thirty_days_in_sec ) {
            // 40 days in sec.
            return 60 * 24 * 60 * 60;
        } );
        \ILJ\ilj_fs()->add_filter( 'show_first_trial_after_n_sec', function ( $day_in_sec ) {
            // 3 days in sec.
            return 3 * 24 * 60 * 60;
        } );
        \ILJ\ilj_fs()->add_filter( 'show_affiliate_program_notice', function () {
            return false;
        } );
    }
    
    /**
     * Adds a link to the plugins settings page on plugins overview
     *
     * @since 1.0.0
     *
     * @param  array $links All links that get displayed
     * @return array
     */
    public function addSettingsLink( $links )
    {
        $settings_link = '<a href="admin.php?page=' . AdminMenu::ILJ_MENUPAGE_SLUG . '-' . Settings::ILJ_MENUPAGE_SETTINGS_SLUG . '">' . __( 'Settings' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }
    
    /**
     * Checks if a (changed) post affects the index creation
     *
     * @since  1.2.0
     * @param  int $post_id The ID of the post
     * @return bool
     */
    protected function postAffectsIndex( $post_id )
    {
        $post = get_post( $post_id );
        if ( !$post || !in_array( $post->post_status, [ 'publish', 'trash' ] ) ) {
            return false;
        }
        return true;
    }
    
    /**
     * Applies the linkbuilder to a piece of content
     *
     * @since 1.2.19
     * @param  mixed $content The content of an post or page
     * @return string
     */
    public function linkContent( $content )
    {
        if ( is_admin() ) {
            return $content;
        }
        if ( $this->excludeLinkBuilderFilter() ) {
            return $content;
        }
        $link_builder = new LinkBuilder( get_the_ID(), 'post' );
        return $link_builder->linkContent( $content );
    }
    
    /**
     * Excludes sitemap urls from applying the link builder filter
     *
     * @return bool
     */
    public function excludeLinkBuilderFilter()
    {
        global  $wp ;
        $link = home_url( $wp->request );
        $match = preg_match( '/[a-zA-Z0-9_]*-sitemap(?:[0-9]*|_index).xml/', strtolower( $link ) );
        return $match;
    }

}