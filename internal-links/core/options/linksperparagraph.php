<?php

namespace ILJ\Core\Options;

use  ILJ\Helper\Help ;
use  ILJ\Core\Options ;
use  ILJ\Core\Options\MultipleKeywords ;
use  ILJ\Helper\Options as OptionsHelper ;
/**
 * Option: Links per Paragraph
 *
 * @since   1.1.3
 * @package ILJ\Core\Options
 */
class LinksPerParagraph extends AbstractOption
{
    /**
     * @inheritdoc
     */
    public static function getKey()
    {
        return self::ILJ_OPTIONS_PREFIX . 'links_per_paragraph';
    }
    
    /**
     * @inheritdoc
     */
    public static function getDefault()
    {
        return (int) 0;
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
        return __( 'Maximum amount of links per paragraph', 'internal-links' );
    }
    
    /**
     * @inheritdoc
     */
    public function getDescription()
    {
        return __( 'Set the maximum links per paragraph.', 'internal-links' );
    }
    
    /**
     * @inheritdoc
     */
    public function renderField( $value )
    {
        $multiple_keywords = Options::getOption( MultipleKeywords::getKey() );
        echo  '<input type="number" min="1" name="' . self::getKey() . '" id="' . self::getKey() . '" value="' . $value . '"' . (( $multiple_keywords ? ' disabled="disabled"' : '' )) . ' ' . OptionsHelper::getDisabler( $this ) . '  /> ' ;
    }
    
    /**
     * @inheritdoc
     */
    public function isValidValue( $value )
    {
        return 0;
    }

}