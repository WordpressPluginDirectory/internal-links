<?php

namespace ILJ\Core\IndexStrategy;

use  ILJ\Core\Options ;
use  ILJ\Database\Postmeta ;
use  ILJ\Enumeration\IndexMode ;
use  ILJ\Enumeration\LinkType ;
use  ILJ\Helper\Encoding ;
use  ILJ\Helper\IndexAsset ;
use  ILJ\Helper\Regex ;
use  ILJ\Helper\Blacklist ;
use  ILJ\Helper\Statistic ;
use  ILJ\Type\Ruleset ;
/**
 * Polylang compatible indexbuilder
 *
 * Takes care of interlinking only pages from the same language domain
 *
 * @package ILJ\Core\Indexbuilder
 *
 * @since 1.2.2
 */
class PolylangStrategy extends DefaultStrategy
{
    /**
     * @var   array
     * @since 1.2.2
     */
    public  $link_rules = array() ;
    /**
     * @var   array
     * @since 1.2.2
     */
    public  $languages = array() ;
    /**
     * 
     * @var   array
     * @since 1.2.15
     */
    public  $blacklisted_posts = array() ;
    /**
     * 
     * @var   array
     * @since 1.2.15
     */
    public  $blacklisted_terms = array() ;
    public function __construct()
    {
        $this->languages = self::getLanguages();
        $this->blacklisted_posts = Blacklist::getBlacklistedList( "post" );
        $this->blacklisted_posts = Blacklist::getBlacklistedList( "post" );
    }
    
    /**
     * Get all active Polylang languages
     *
     * @static
     * @since  1.2.2
     *
     * @return array
     */
    public static function getLanguages()
    {
        $languages = [];
        $languagesData = ( function_exists( 'icl_get_languages' ) ? icl_get_languages( 'skip_missing=0&orderby=code' ) : [] );
        if ( !count( $languagesData ) ) {
            return $languages;
        }
        foreach ( $languagesData as $language ) {
            $languages[] = $language['language_code'];
        }
        return array_unique( $languages );
    }
    
    /**
     * @inheritdoc
     */
    public function setIndices()
    {
        $index_count = 0;
        $this->loadLinkConfigurations();
        $posts = IndexAsset::getPosts();
        if ( is_array( $posts ) && !empty($posts) ) {
            $this->writeToIndex(
                $posts,
                'post',
                [
                'id'      => 'ID',
                'content' => 'post_content',
            ],
                $index_count,
                IndexAsset::ILJ_FULL_BUILD,
                IndexMode::NONE
            );
        }
        return $index_count;
    }
    
    /**
     * @inheritdoc
     */
    public function setBatchedIndices(
        $data,
        $data_type,
        $keyword_offset,
        $keyword_type
    )
    {
        $index_count = 0;
        $this->loadLinkConfigurationsBatched( $keyword_offset, $keyword_type );
        
        if ( $data_type == "post" ) {
            $posts = $data;
            if ( is_array( $posts ) && !empty($posts) ) {
                $this->writeToIndex(
                    $posts,
                    'post',
                    [
                    'id'      => 'ID',
                    'content' => 'post_content',
                ],
                    $index_count,
                    IndexAsset::ILJ_FULL_BUILD,
                    IndexMode::AUTOMATIC
                );
            }
        }
        
        return $index_count;
    }
    
    /**
     * setIndividualIndices
     *
     * @param  mixed $id
     * @param  mixed $type
     * @param  mixed $link_type
     * @param  mixed $keyword_offset
     * @param  mixed $keyword_type
     * @param  mixed $batched_data
     * @param  mixed $batched_data_type
     * @return int
     */
    public function setIndividualIndices(
        $id,
        $type,
        $link_type,
        $keyword_offset,
        $keyword_type,
        $batched_data,
        $batched_data_type
    )
    {
        $index_count = Statistic::getLinkIndexCount();
        $posts = array();
        
        if ( $link_type == LinkType::OUTGOING ) {
            $this->loadLinkConfigurationsBatched( $keyword_offset, $keyword_type );
            if ( $batched_data_type == "post" ) {
                array_push( $posts, $id );
            }
            if ( $batched_data_type == "post" ) {
                $this->writeToIndex(
                    $posts,
                    'post',
                    [
                    'id'      => 'ID',
                    'content' => 'post_content',
                ],
                    $index_count,
                    IndexAsset::ILJ_INDIVIDUAL_BUILD,
                    IndexMode::AUTOMATIC,
                    $link_type
                );
            }
        } else {
            
            if ( $link_type == LinkType::INCOMING ) {
                $this->loadLinkIndividualConfigurations( $id, $type );
                $data = $batched_data;
                if ( $batched_data_type == "post" ) {
                    $this->writeToIndex(
                        $data,
                        'post',
                        [
                        'id'      => 'ID',
                        'content' => 'post_content',
                    ],
                        $index_count,
                        IndexAsset::ILJ_INDIVIDUAL_BUILD,
                        IndexMode::AUTOMATIC,
                        $link_type
                    );
                }
            }
        
        }
        
        return $index_count;
    }
    
    /**
     * loadLinkIndividualConfigurations
     *
     * @param  int    $id
     * @param  string $type
     * @return void
     */
    protected function loadLinkIndividualConfigurations( $id, $type )
    {
        
        if ( $type == "post" ) {
            $post_definitions = Postmeta::getLinkDefinitionsById( $id );
            foreach ( $this->languages as $language ) {
                $this->link_rules[$language] = new Ruleset();
                foreach ( $post_definitions as $definition ) {
                    if ( $this->getDataLanguage( $definition->post_id, 'post' ) != $language ) {
                        continue;
                    }
                    $post_type = 'post';
                    $anchors = unserialize( $definition->meta_value );
                    if ( !$anchors || !is_array( $anchors ) ) {
                        continue;
                    }
                    $anchors = $this->applyKeywordOrder( $anchors );
                    $this->addAnchorsToLinkRules( $anchors, [
                        'id'       => $definition->post_id,
                        'type'     => $post_type,
                        'language' => $language,
                    ] );
                }
            }
            return;
        }
        
        return;
    }
    
    /**
     * Picks up all meta definitions for configured keywords by language and adds them to internal ruleset
     *
     * @since 1.2.2
     *
     * @return void
     */
    protected function loadLinkConfigurations()
    {
        $post_definitions = Postmeta::getAllLinkDefinitions();
        foreach ( $this->languages as $language ) {
            $this->link_rules[$language] = new Ruleset();
            foreach ( $post_definitions as $definition ) {
                if ( $this->getDataLanguage( $definition->post_id, 'post' ) != $language ) {
                    continue;
                }
                $type = 'post';
                $anchors = unserialize( $definition->meta_value );
                if ( !$anchors || !is_array( $anchors ) ) {
                    continue;
                }
                $anchors = $this->applyKeywordOrder( $anchors );
                $this->addAnchorsToLinkRules( $anchors, [
                    'id'       => $definition->post_id,
                    'type'     => $type,
                    'language' => $language,
                ] );
            }
        }
        return;
    }
    
    /**
     * Picks up all meta definitions for configured keywords and adds them to internal ruleset
     *
     * @param  int $offset
     * @param  string $keyword_type
     * @return void
     */
    public function loadLinkConfigurationsBatched( $offset, $keyword_type )
    {
        
        if ( $keyword_type == "post" ) {
            $post_definitions = Postmeta::getAllLinkDefinitionsByBatch( $offset );
            foreach ( $this->languages as $language ) {
                $this->link_rules[$language] = new Ruleset();
                foreach ( $post_definitions as $definition ) {
                    if ( $this->getDataLanguage( $definition->post_id, 'post' ) != $language ) {
                        continue;
                    }
                    $type = 'post';
                    $anchors = unserialize( $definition->meta_value );
                    if ( !$anchors || !is_array( $anchors ) ) {
                        continue;
                    }
                    $anchors = $this->applyKeywordOrder( $anchors );
                    $this->addAnchorsToLinkRules( $anchors, [
                        'id'       => $definition->post_id,
                        'type'     => $type,
                        'language' => $language,
                    ] );
                }
            }
            return;
        }
        
        return;
    }
    
    /**
     * Writes a set of data to the linkindex
     *
     * @since 1.2.2
     *
     * @param  array  $data      The data container
     * @param  string $data_type Type of the data inside the container
     * @param  array  $fields    Field settings for the container objects
     * @param  int    &$counter  Counts the written operations
     * @param  mixed  $scope
     * @param  mixed  $build_mode
     * @param  mixed  $link_type
     * @return void
     */
    protected function writeToIndex(
        $data,
        $data_type,
        array $fields,
        &$counter,
        $scope,
        $build_mode,
        $link_type = null
    )
    {
        if ( !is_array( $data ) || !count( $data ) ) {
            return;
        }
        $fields = wp_parse_args( $fields, [
            'id'      => '',
            'content' => '',
        ] );
        $IndexStrategy = new IndexStrategy(
            $data_type,
            $fields,
            $this->link_options,
            $build_mode
        );
        $IndexStrategy->setCounter( $counter );
        foreach ( $this->languages as $language ) {
            $IndexStrategy->setLinkRules( $this->link_rules[$language] );
            $data_filtered = $this->filterDataByLanguage( $data, $language, $data_type );
            $IndexStrategy->buildLinksPerData(
                $data_filtered,
                $data_type,
                $scope,
                $link_type
            );
        }
        $counter = $IndexStrategy->getCounter();
    }
    
    /**
     * @inheritDoc
     */
    protected function addAnchorsToLinkRules( array $anchors, array $params )
    {
        foreach ( $anchors as $anchor ) {
            $anchor = Encoding::unmaskSlashes( $anchor );
            if ( !Regex::isValid( $anchor ) ) {
                continue;
            }
            $pattern = Regex::escapeDot( $anchor );
            $this->link_rules[$params['language']]->addRule( $pattern, $params['id'], $params['type'] );
        }
        return;
    }
    
    /**
     * Get the language of any asset data (post, tax)
     *
     * @since 1.2.2
     * @param int    $data_id   The id of the asset
     * @param string $data_type The type of the asset (post, tax)
     *
     * @return string
     */
    protected function getDataLanguage( $data_id, $data_type )
    {
        return pll_get_post_language( $data_id );
    }
    
    /**
     * Filters a collection of data (posts, taxes) by a given language
     *
     * @since 1.2.2
     * @param array  $data      The data collection
     * @param string $language  The language code
     * @param string $data_type The type of the collection items
     *
     * @return array
     */
    protected function filterDataByLanguage( $data, $language, $data_type )
    {
        $data_filtered = [];
        foreach ( $data as $id ) {
            if ( $data_type == "post" || $data_type == "post_meta" ) {
                $current = get_post( $id );
            }
            $data_id = ( isset( $data_id ) ? $data_id : $current->ID );
            $data_language = $this->getDataLanguage( $data_id, $data_type );
            if ( $data_language == $language ) {
                $data_filtered[] = $current;
            }
            unset( $data_id );
        }
        return $data_filtered;
    }

}