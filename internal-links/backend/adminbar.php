<?php

namespace ILJ\Backend;

use  ILJ\Core\Options ;
use  ILJ\Helper\BatchInfo as HelperBatchInfo ;
/**
 * Admin bar
 *
 * Manages everything related to the admin bar section
 *
 * @package ILJ\Backend
 * @since   2.0.0
 */
class AdminBar
{
    /**
     * Add a link to the admin toolbar
     *
     * @param WP_Admin_Bar $admin_bar
     *
     * @return void
     * @since  2.0.0
     */
    public static function addLink( $admin_bar )
    {
        $batch_build_info = new HelperBatchInfo();
        $batch_percentage = $batch_build_info->getBatchPercentage();
        $status = $batch_build_info->getBatchStatus();
        $args = array(
            'id'    => 'ilj',
            'title' => '<span class="ilj_icon" aria-hidden="true"></span>',
            'href'  => add_query_arg( [
            'page' => AdminMenu::ILJ_MENUPAGE_SLUG,
        ], admin_url( 'admin.php' ) ),
            'meta'  => array(
            'html' => '
                <a class="ilj_admin_bar_link" style="height: 0px;" href = "' . add_query_arg( [
            'page' => AdminMenu::ILJ_MENUPAGE_SLUG,
        ], admin_url( 'admin.php' ) ) . '">
                    <div class="ilj_admin_bar_container">
                        <div class="ilj_bar_title_container"> Linkindex: <span id="ilj_batch_progress">' . $batch_percentage . '%</span></div>
                        <div id="progressbar" class="ilj_progress_bar">
                            <div style="width:' . $batch_percentage . '%"></div>
                        </div>
                    </div>
                </a>',
        ),
        );
        $admin_bar->add_node( $args );
        $args = array(
            'parent' => 'ilj',
            'id'     => 'ilj-status',
            'title'  => '<div class="ilj-build-title"><strong>Status:</strong> <span  id="ilj_batch_status">' . HelperBatchInfo::translateBatchStatus( $status ) . '</span></div>',
            'meta'   => array(
            'html' => '
				<hr class="ilj-build-seperate" />
                <div class="ilj-build-info">
                	<p>
                		<span class="dashicons ilj_info_icon"></span>
                		' . __( 'We build your internal links in the background. As soon as 100% is reached, your new links will be visible in the frontend.', 'internal-links' ) . '
                	</p>
                </div>
                ',
        ),
        );
        $admin_bar->add_node( $args );
    }

}