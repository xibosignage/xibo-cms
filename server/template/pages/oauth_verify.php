<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2010 Daniel Garner
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
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>Xibo API - Authorization Requested</title>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <link rel="stylesheet" type="text/css" href="template/css/login_box.css" />
        <link rel="stylesheet" type="text/css" href="3rdparty/jQuery/ui-elements.css" />

        <link rel="shortcut icon" href="img/favicon.ico" />
        <script type="text/javascript" src="3rdparty/jQuery/jquery.min.js"></script>
        <script type="text/javascript" src="3rdparty/jQuery/jquery-ui.packed.js"></script>
        <script type="text/javascript" src="3rdparty/jQuery/jquery.form.js"></script>
        <script type="text/javascript" src="3rdparty/jQuery/jquery.validate.min.js"></script>
        <script type="text/javascript" src="3rdparty/jQuery/jquery.bgiframe.min.js"></script>

        <script type="text/javascript" src="lib/js/functions.js"></script>
        <script type="text/javascript" src="lib/js/core.js"></script>
    </head>

    <body>

        <div id="container">
            <div id="content">
                <div class="login_box">
                    <div class="login_header">
                        <div class="login_header_left">
                        </div>
                        <div class="login_header_right">
                        </div>
                    </div>

                    <div class="login_body">
                        
                        <h2>Xibo API - Authorization Requested</h2>
                        <div style="text-align:left;">
                            <p>Are you sure you want to authorize this application to have access to your Xibo account?</p>
                            <p>
                                <strong>Application Name</strong>: <?php echo $consumer['application_title']; ?><br />
                                <strong>Application Description</strong>: <?php echo $consumer['application_descr']; ?><br />
                                <strong>Application Site</strong>: <?php echo $consumer['application_uri']; ?>
                            </p>
                        </div>
                        <form method="post">
                            <input type="submit" name="Allow" value="Allow">
                        </form>
                        <p><a href="http://www.xibo.org.uk"><img src='img/login/complogo.png'></a></p>
                    </div>

                    <div class="login_foot">
                        <div class="login_foot_left">
                        </div>
                        <div class="login_foot_right">
                        </div>
                    </div>
                </div>

            </div>
            <div id="system_working" style="display:none;"><img src="img/loading.gif"></div>

            <div id="system_message" style="text-align: center;">
                <span>Message</span>
            </div>

            <div id="div_dialog"></div>
    </body>
</html>