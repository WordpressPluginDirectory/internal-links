<?php
namespace ILJ\Core\Options;

use ILJ\Helper\Help;
use ILJ\Helper\Options as OptionsHelper;

/**
 * Option: Links Per Paragraph Switch
 *
 * @since   1.2.19
 * @package ILJ\Core\Options
 */
class LinksPerParagraphSwitch extends AbstractOption
{
    /**
     * @inheritdoc
     */
    public static function getKey()
    {
        return self::ILJ_OPTIONS_PREFIX . 'links_per_paragraph_switch';
    }

    /**
     * @inheritdoc
     */
    public static function getDefault()
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getTitle()
    {
        return __('Limit links per paragraph', 'internal-links');
    }

    /**
     * @inheritdoc
     */
    public function getDescription()
    {
        return __('Limit the links created per paragraph', 'internal-links');
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
    public function renderField($value)
    {
        $checked = checked(1, $value, false);
        OptionsHelper::renderToggle($this, $checked);
    }

    /**
     * @inheritdoc
     */
    public function isValidValue($value)
    {
        return 1 === (int) $value || 0 === (int) $value;
    }
}
