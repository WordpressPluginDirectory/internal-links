<?php

namespace ILJ\Core\IndexStrategy;

use  ILJ\Backend\Editor ;
use  ILJ\Core\Options ;
use  ILJ\Database\LinkindexIndividualTemp ;
use  ILJ\Database\LinkindexTemp ;
use  ILJ\Enumeration\IndexMode ;
use  ILJ\Enumeration\LinkType ;
use  ILJ\Helper\Blacklist ;
use  ILJ\Helper\Encoding ;
use  ILJ\Helper\IndexAsset ;
use  ILJ\Helper\LinkBuilding ;
use  ILJ\Helper\Replacement ;
use  ILJ\Helper\Url ;
/**
 *  The index builder strategy
 *
 * @since 1.2.15
 */
class IndexStrategy
{
    /**
     * @var   Object
     * @since 1.2.15
     */
    protected  $data = array() ;
    /**
     * @var   String
     * @since 1.2.15
     */
    protected  $data_type = '' ;
    /**
     * @var   object
     * @since 1.2.15
     */
    protected  $link_rules = array() ;
    /**
     * @var   Array
     * @since 1.2.15
     */
    protected  $fields = array() ;
    /**
     * @var   int
     * @since 1.2.15
     */
    protected  $linkcount_per_paragraph = 0 ;
    /**
     * 
     * @var   array
     * @since 1.2.15
     */
    protected  $blacklisted_posts = array() ;
    /**
     * 
     * @var   array
     * @since 1.2.15
     */
    protected  $blacklisted_terms = array() ;
    /**
     * @var   array
     * @since 1.2.15
     */
    protected  $link_options = array() ;
    /**
     * @var   int
     * @since 1.0.1
     */
    protected  $counter = 0 ;
    /**
     * 
     * @var   array
     * @since 1.2.17
     */
    protected  $incoming_link = array() ;
    /**
     * Determine the index mode and if the action is called by cli or not
     *
     * @var string
     */
    protected  $index_mode = "" ;
    public function __construct(
        $data_type,
        $fields,
        $link_options,
        $build_mode
    )
    {
        $this->fields = $fields;
        $this->data_type = $data_type;
        $this->link_options = $link_options;
        $this->index_mode = $build_mode;
        $this->setupLinkOptions();
    }
    
    /**
     * Setup the link options for link Limitations
     *
     * @return void
     */
    protected function setupLinkOptions()
    {
        $this->multi_keyword_mode = $this->link_options['multi_keyword_mode'];
        $this->links_per_page = $this->link_options['links_per_page'];
        $this->links_per_target = $this->link_options['links_per_target'];
        $this->blacklisted_posts = Blacklist::getBlacklistedList( "post" );
    }
    
    /**
     * Set the link Rules
     *
     * @param  array $link_rules
     * @return void
     */
    public function setLinkRules( $link_rules )
    {
        $this->link_rules = $link_rules;
    }
    
    /**
     * Loops thru given data and build Link Index
     *
     * @param  mixed $data
     * @param  mixed $data_type
     * @param  mixed $scope
     * @param  mixed $link_type
     * @return void
     */
    public function buildLinksPerData(
        $data,
        $data_type,
        $scope,
        $link_type = null
    )
    {
        $data_filtered = $data;
        foreach ( $data_filtered as $key => $id ) {
            $linked_urls = [];
            $linked_anchors = [];
            $post_outlinks_count = 0;
            $item = array();
            if ( $this->data_type == "post" ) {
                
                if ( is_object( $id ) ) {
                    $item = $id;
                    if ( $this->index_mode == IndexMode::AUTOMATIC ) {
                        $linked_anchors = IndexAsset::getLinkedAnchors( $item->{$this->fields['id']}, "post", $scope );
                    }
                } else {
                    $item = get_post( (int) $id );
                    if ( $this->index_mode == IndexMode::AUTOMATIC ) {
                        $linked_anchors = IndexAsset::getLinkedAnchors( $item->{$this->fields['id']}, "post", $scope );
                    }
                }
            
            }
            if ( !property_exists( $item, $this->fields['content'] ) || !property_exists( $item, $this->fields['id'] ) ) {
                continue;
            }
            $limit_outgoing_links = false;
            $outgoing_links_limit = 0;
            
            if ( $this->links_per_page > 0 ) {
                $limit_outgoing_links = true;
                $outgoing_links_limit = $this->links_per_page;
            }
            
            
            if ( $limit_outgoing_links ) {
                $data_type = $this->data_type;
                
                if ( $this->data_type == "post_meta" ) {
                    $data_type = "post";
                } elseif ( $this->data_type == "term_meta" ) {
                    $data_type = "term";
                }
                
                $post_outlinks_count = IndexAsset::getOutgoingLinksCount( $item->{$this->fields['id']}, $data_type, $scope );
            }
            
            $content = $item->{$this->fields['content']};
            try {
                /**
                 * Loads Builder's Necessary Compatibility codes 
                 * 
                 * @since  1.3.10
                 */
                do_action( "builder_compat" );
                $content = do_shortcode( $item->{$this->fields['content']} );
            } catch ( \Exception $e ) {
                continue;
            }
            if ( $this->data_type == 'post' ) {
                $this->filterTheContentWithoutTexturize( $content );
            }
            if ( $this->index_mode == IndexMode::AUTOMATIC ) {
                if ( isset( $linked_anchors ) && !is_null( $linked_anchors ) && !empty($linked_anchors) ) {
                    foreach ( $linked_anchors as $anchors ) {
                        $rule_id = 'ilj_' . uniqid( '', true );
                        $content = preg_replace(
                            '/' . Encoding::maskPattern( $anchors ) . '/ui',
                            $rule_id,
                            $content,
                            ( $this->multi_keyword_mode ? -1 : 1 )
                        );
                    }
                }
            }
            $existing_link_targets = array();
            Replacement::mask( $content );
            $this->createLinkIndex(
                $content,
                0,
                $post_outlinks_count,
                $linked_urls,
                $linked_anchors,
                $item,
                0,
                false,
                null,
                $outgoing_links_limit,
                $existing_link_targets,
                $scope,
                $link_type
            );
            $this->link_rules->reset();
        }
    }
    
    /**
     * Runs Logical Checks For Linking Rules and settings
     *
     * @param  String $content
     * @param  int    $index
     * @param  int    $post_outlinks_count
     * @param  array  $linked_urls
     * @param  array  $linked_anchors
     * @param  object $item
     * @param  int    $linkcount_per_paragraph
     * @param  bool   $loop_thru_per_paragraph
     * @param  int    $links_per_paragraph_limit
     * @param  int    $outgoing_links_limit
     * @param  array  $existing_link_targets
     * @param  String $scope
     * @param  String $link_type
     * @return array
     */
    public function createLinkIndex(
        $content,
        $index,
        $post_outlinks_count,
        $linked_urls,
        $linked_anchors,
        $item,
        $linkcount_per_paragraph,
        $loop_thru_per_paragraph,
        $links_per_paragraph_limit,
        $outgoing_links_limit,
        $existing_link_targets = null,
        $scope = null,
        $link_type = null
    )
    {
        while ( $this->link_rules->hasRule() ) {
            $link_rule = $this->link_rules->getRule();
            
            if ( $outgoing_links_limit > 0 && $post_outlinks_count >= $outgoing_links_limit ) {
                $this->link_rules->reset();
                break;
            }
            
            
            if ( !isset( $linked_urls[$link_rule->value] ) ) {
                $linked_urls[$link_rule->value] = 0;
                
                if ( $this->index_mode == IndexMode::AUTOMATIC ) {
                    
                    if ( $this->data_type == "post_meta" || $this->data_type == "post" ) {
                        $linked_urls[$link_rule->value] = IndexAsset::getLinkedUrlsCount(
                            $link_rule->value,
                            $item->{$this->fields['id']},
                            "post",
                            $scope
                        );
                    } elseif ( $this->data_type == "term_meta" || $this->data_type == "term" ) {
                        $linked_urls[$link_rule->value] = IndexAsset::getLinkedUrlsCount(
                            $link_rule->value,
                            $item->{$this->fields['id']},
                            "term",
                            $scope
                        );
                    }
                
                } else {
                    
                    if ( $this->data_type == "post_meta" ) {
                        $linked_urls[$link_rule->value] = IndexAsset::getLinkedUrlsCount(
                            $link_rule->value,
                            $item->{$this->fields['id']},
                            "post",
                            $scope
                        );
                    } elseif ( $this->data_type == "term_meta" ) {
                        $linked_urls[$link_rule->value] = IndexAsset::getLinkedUrlsCount(
                            $link_rule->value,
                            $item->{$this->fields['id']},
                            "term",
                            $scope
                        );
                    }
                
                }
            
            }
            
            
            if ( !$this->multi_keyword_mode && ($this->links_per_target > 0 && $linked_urls[$link_rule->value] >= $this->links_per_target) ) {
                $this->link_rules->nextRule();
                continue;
            }
            
            if ( $link_rule->value == $item->{$this->fields['id']} ) {
                
                if ( $link_rule->type == $this->data_type ) {
                    $this->link_rules->nextRule();
                    continue;
                } elseif ( $link_rule->type . "_meta" == $this->data_type ) {
                    $this->link_rules->nextRule();
                    continue;
                }
            
            }
            preg_match( '/' . Encoding::maskPattern( $link_rule->pattern ) . '/ui', $content, $rule_match );
            
            if ( isset( $rule_match['phrase'] ) ) {
                $phrase = trim( $rule_match['phrase'] );
                
                if ( !$this->multi_keyword_mode && in_array( strtolower( $phrase ), array_map( "strtolower", $linked_anchors ) ) ) {
                    $this->link_rules->nextRule();
                    continue;
                }
                
                $is_blacklisted_keyword = IndexAsset::checkIfBlacklistedKeyword( $item->{$this->fields['id']}, $phrase, $this->data_type );
                
                if ( $is_blacklisted_keyword ) {
                    $this->link_rules->nextRule();
                    continue;
                }
                
                
                if ( $scope == IndexAsset::ILJ_FULL_BUILD ) {
                    LinkindexTemp::addRule(
                        $item->{$this->fields['id']},
                        $link_rule->value,
                        $phrase,
                        $this->data_type,
                        $link_rule->type
                    );
                } elseif ( $scope == IndexAsset::ILJ_INDIVIDUAL_BUILD ) {
                    LinkindexIndividualTemp::addRule(
                        $item->{$this->fields['id']},
                        $link_rule->value,
                        $phrase,
                        $this->data_type,
                        $link_rule->type,
                        $link_type
                    );
                }
                
                $rule_id = 'ilj_' . uniqid( '', true );
                $content = preg_replace(
                    '/' . Encoding::maskPattern( $link_rule->pattern ) . '/ui',
                    $rule_id,
                    $content,
                    ( $this->multi_keyword_mode ? -1 : 1 )
                );
                $this->counter++;
                $post_outlinks_count++;
                $linked_urls[$link_rule->value]++;
                $linked_anchors[] = $phrase;
            }
            
            $this->link_rules->nextRule();
        }
        $this->link_rules->reset();
        $obj = array(
            "linked_anchors"      => $linked_anchors,
            "linked_urls"         => $linked_urls,
            "post_outlinks_count" => $post_outlinks_count,
        );
        return $obj;
    }
    
    /**
     * Applies content filters to a given piece of content without calling
     * WordPress' texturize method (that escapes special chars like apostrophes)
     *
     * @since  1.2.9
     * @param  $content The content that gets filtered
     * @return void
     */
    protected function filterTheContentWithoutTexturize( &$content )
    {
        remove_filter( 'the_content', 'wptexturize' );
        $content = apply_filters( 'the_content', $content );
        add_filter( 'the_content', 'wptexturize' );
    }
    
    /**
     * Set the counter
     *
     * @param  intr $counter
     * @return void
     */
    public function setCounter( $counter )
    {
        $this->counter = $counter;
    }
    
    /**
     * Gets the currect counter value
     *
     * @return int $counter
     */
    public function getCounter()
    {
        return $this->counter;
    }

}