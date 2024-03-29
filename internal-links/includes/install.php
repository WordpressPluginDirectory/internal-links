<?php

/**
 * @package ILJ\Includes
 */

use ILJ\Database\Usermeta;
use ILJ\Database\Linkindex;
use ILJ\Database\Postmeta;
use ILJ\Backend\Environment;
use ILJ\Core\Options;
use ILJ\Database\LinkindexTemp;

/**
 * Responsible for creating the database tables
 *
 * @since  1.0.0
 * @return void
 */
function ilj_install_db()
{
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    $query_linkindex = "CREATE TABLE " . $wpdb->prefix . Linkindex::ILJ_DATABASE_TABLE_LINKINDEX . " (
        `link_from` BIGINT(20) NULL,
        `link_to` BIGINT(20) NULL,
        `type_from` VARCHAR(45) NULL,
        `type_to` VARCHAR(45) NULL,
        `anchor` TEXT NULL,
        INDEX `link_from` (`link_from` ASC),
        INDEX `type_from` (`type_from` ASC),
        INDEX `type_to` (`type_to` ASC),
        INDEX `link_to` (`link_to` ASC))" . $charset_collate . ";";

    include_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta($query_linkindex);

    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '" . $wpdb->prefix . Linkindex::ILJ_DATABASE_TABLE_LINKINDEX . "' AND column_name = 'id'");

    if(empty($row)) {
        $wpdb->query("ALTER TABLE " . $wpdb->prefix . Linkindex::ILJ_DATABASE_TABLE_LINKINDEX . " ADD id BIGINT(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST");
    }

    Environment::update("last_version", ILJ_VERSION);
}

register_activation_hook(ILJ_FILE, '\\ilj_install_db');
register_activation_hook(ILJ_FILE, ['ILJ\Core\Options', 'setOptionsDefault']);
