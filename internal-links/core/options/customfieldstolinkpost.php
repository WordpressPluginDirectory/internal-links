<?php

namespace ILJ\Core\Options;

use  ILJ\Helper\Options as OptionsHelper ;
use  ILJ\Core\Options ;
use  ILJ\Helper\IndexAsset ;
/**
 * Option: List of Post Custom Fields used for limiting links
 *
 * @since   2.1.0
 * @package ILJ\Core\Options
 */
class CustomFieldsToLinkPost extends AbstractOption
{
    const  ILJ_ACF_HINT_FILTER_POST = "ilj_hint_for_acf_post" ;
    const  ILJ_ACTION_ADD_PRO_FEATURES = "ilj_add_pro_features" ;
    /**
     * @inheritdoc
     */
    public static function getKey()
    {
        return self::ILJ_OPTIONS_PREFIX . 'custom_fields_to_link_post';
    }
    
    /**
     * @inheritdoc
     */
    public static function getDefault()
    {
        return [];
    }
    
    /**
     * @inheritdoc
     */
    public static function isPro()
    {
        return true;
    }
    
    /**
     * @inheritdoc
     */
    public function register( $option_group )
    {
        return;
    }
    
    /**
     * @inheritdoc
     */
    public function getTitle()
    {
        return __( 'Custom fields of posts that get used for linking', 'internal-links' );
    }
    
    /**
     * @inheritdoc
     */
    public function getDescription()
    {
        return __( 'This is a list of post custom fields that should be used for automatic linking.<br>Leave empty to not link any custom fields.', 'internal-links' );
    }
    
    /**
     * @inheritdoc
     */
    public function renderField( $value )
    {
        if ( $value == "" ) {
            $value = [];
        }
        $custom_fields = [];
        echo  '<select name="' . self::getKey() . '[]" id="' . self::getKey() . '" multiple="multiple"' . OptionsHelper::getDisabler( $this ) . '>' ;
        if ( count( $custom_fields ) ) {
            foreach ( $custom_fields as $custom_field ) {
                echo  '<option value="' . $custom_field->meta_key . '"' . (( in_array( $custom_field->meta_key, $value ) ? ' selected' : '' )) . '>' . $custom_field->meta_key . '</option>' ;
            }
        }
        echo  '</select>' ;
    }
    
    /**
     * @inheritdoc
     */
    public function isValidValue( $value )
    {
        return false;
    }
    
    /**
     * @inheritdoc
     */
    public function getHint()
    {
        $hint = "";
        $hint = apply_filters( self::ILJ_ACF_HINT_FILTER_POST, $hint );
        return $hint;
    }

}