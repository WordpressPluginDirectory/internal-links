<?php
namespace ILJ\Core\Options;

use ILJ\Helper\Options as OptionsHelper;

/**
 * Option: Disable ILJ Status Bar
 *
 * @since   2.1.1
 * @package ILJ\Core\Options
 */
class HideStatusBar extends AbstractOption
{
    /**
     * @inheritdoc
     */
    public static function getKey()
    {
        return self::ILJ_OPTIONS_PREFIX . 'hide_status_bar';
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
        return __('Hide the linkindex indicator from wordpress admin bar', 'internal-links');
    }

    /**
     * @inheritdoc
     */
    public function getDescription()
    {
        return __('If activated, our admin bar entry will be disabled', 'internal-links');
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
