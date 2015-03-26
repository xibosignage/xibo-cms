<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2014 Alex Harrington
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version. 
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace Xibo\Controller;

use baseDAO;
use database;
use Xibo\Entity\User;
use Xibo\Helper\Theme;


class Preview extends Base
{
    /* @var \Xibo\Entity\Layout $layout */
    private $layout;

    function __construct(database $db, user $user)
    {
        $this->db =& $db;
        $this->user =& $user;

        $layoutId = \Xibo\Helper\Sanitize::getInt('layoutid');

        //if we have modify selected then we need to get some info
        if ($layoutId != 0) {
            // get the permissions
            $layout = $this->user->LayoutList(NULL, array('layoutId' => $layoutId));

            if (count($layout) <= 0)
                trigger_error(__('You do not have permissions to view this layout'), E_USER_ERROR);

            $this->layout = $layout[0];
        }
    }

    function render()
    {
        $favicon = Theme::ImageUrl('favicon.ico');

        // Render a specific layout in the previewer
        // layoutid must be provided
        $pfl = __('Preview for Layout');

        $previewCss = Theme::ItemPath('css/html-preview.css');

        $output = <<<EOT
            <!DOCTYPE html>
            <html>
                <head>
                    <meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
                    <title>$pfl {$this->layout->layoutId}</title>
                    <link rel="stylesheet" type="text/css" href="$previewCss" />
                    <script type="text/JavaScript" src="theme/default/libraries/jquery/jquery-1.9.1.js"></script>
                    <script type="text/JavaScript" src="modules/preview/html5Preloader.js"></script>
                    <script type="text/JavaScript" src="modules/preview/html-preview.js"></script>
                    <link rel="shortcut icon" href="$favicon" />
                </head>
                <body onload="dsInit({$this->layout->layoutId})">
                    <div id="player">
                        <div id="info"></div>
                        <div id="log"></div>
                        <div id="screen">
                            <div id="splash">
                                <div id="loader"></div>
                                <div id="loaderCaption"><p>
EOT;

        $output .= __("Loading layout...");
        $output .= "</p></div>";
        $output .= "</div>";
        $output .= '<div id="end"><a href="javascript:history.go(0)" style="text-decoration: none; color: #ffffff">';
        $output .= __("Play again?");
        $output .= "</a></div></div></div></body></html>";

        print $output;
    }

    function getXlf()
    {
        print $this->layout->toXlf();
    }
}
