<?php
namespace ILJ\Database;

use ILJ\Enumeration\LinkType;

/**
 * Database wrapper for the linkindex table
 *
 * @package ILJ\Database
 * @since   1.3.10
 */
class LinkindexTemp
{
    const ILJ_DATABASE_TABLE_LINKINDEX_TEMP = "ilj_linkindex_temp";
    const ILJ_ACTION_AFTER_DELETE_LINKINDEX_TEMP = "ilj_after_delete_linkindex_temp";

    public static function install_temp_db()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $query_linkindex = "CREATE TABLE " . $wpdb->prefix . self::ILJ_DATABASE_TABLE_LINKINDEX_TEMP . " (
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

        $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '" . $wpdb->prefix . self::ILJ_DATABASE_TABLE_LINKINDEX_TEMP . "' AND column_name = 'id'");

        if(empty($row)) {
            $wpdb->query("ALTER TABLE " . $wpdb->prefix . self::ILJ_DATABASE_TABLE_LINKINDEX_TEMP . " ADD id BIGINT(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST");
        }
    }
    
    /**
     * Drops the current temporary table
     *
     * @return void
     */
    public static function uninstall_temp_db()
    {
        global $wpdb;
        $query_linkindex = "DROP TABLE IF EXISTS " . $wpdb->prefix . self::ILJ_DATABASE_TABLE_LINKINDEX_TEMP . ";";
        $wpdb->query($query_linkindex);
        
    }

    /**
     * Cleans the whole index table
     *
     * @since  1.3.10
     * @return void
     */
    public static function flush()
    {
        global $wpdb;
        $row = $wpdb->get_var("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE table_schema = '".$wpdb->dbname."' AND table_name = '" . $wpdb->prefix . self::ILJ_DATABASE_TABLE_LINKINDEX_TEMP . "'");

        if($row==1) {
            $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . self::ILJ_DATABASE_TABLE_LINKINDEX_TEMP);
        }
        
    }

    /**
     * Returns all post outlinks from linkindex table
     *
     * @since  1.0.1
     * @param  int $id The post ID where outlinks should be retrieved
     * @return array
     */
    public static function getRules($id, $type)
    {
        if (!is_numeric($id)) {
            return [];
        }
        global $wpdb;
        $query = $wpdb->prepare("SELECT * FROM " . $wpdb->prefix . self::ILJ_DATABASE_TABLE_LINKINDEX_TEMP . " linkindex WHERE linkindex.link_from = %d AND linkindex.type_from = %s", $id, $type);
        return $wpdb->get_results($query);
    }

    /**
     * Adds a post rule to the linkindex table
     *
     * @since  1.0.1
     * @param  int    $link_from Post ID which gives the link
     * @param  int    $link_to   Post ID where the link should point to
     * @param  string $anchor    The anchor text which gets used for linking
     * @param  string $type_from The type of asset which gives the link
     * @param  string $type_to   The type of asset which receives the link
     * @return void
     */
    public static function addRule($link_from, $link_to, $anchor, $type_from, $type_to)
    {
        if (!is_integer((int) $link_from) || !is_integer((int) $link_to) || !is_string((string) $anchor)) {
            return;
        }

        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . self::ILJ_DATABASE_TABLE_LINKINDEX_TEMP,
            [
                'link_from' => $link_from,
                'link_to'   => $link_to,
                'anchor'    => $anchor,
                'type_from' => $type_from,
                'type_to'   => $type_to
            ],
            [
                '%d',
                '%d',
                '%s',
                '%s',
                '%s'
            ]
        );
    }

        
    /**
     * Rename old ilj index table to temp and vice versa
     *
     * @return void
     */
    public static function switchTableTemp()
    {

        global $wpdb;

        $dummy_table =  $wpdb->prefix . self::ILJ_DATABASE_TABLE_LINKINDEX_TEMP . "2";

        $wpdb->query("RENAME TABLE ". $wpdb->prefix .Linkindex::ILJ_DATABASE_TABLE_LINKINDEX ." TO ". $dummy_table .", ". $wpdb->prefix .self::ILJ_DATABASE_TABLE_LINKINDEX_TEMP ." TO ". $wpdb->prefix .Linkindex::ILJ_DATABASE_TABLE_LINKINDEX .";");
        $wpdb->query("RENAME TABLE ". $dummy_table ." TO ". $wpdb->prefix .self::ILJ_DATABASE_TABLE_LINKINDEX_TEMP .";");
        
        self::uninstall_temp_db();
    }
}