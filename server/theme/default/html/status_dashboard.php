<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2014 Daniel Garner
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
 *
 * Theme variables:
 *  id = The GridID for rendering AJAX layout table return
 *  filter_id = The Filter Form ID
 *  form_meta = Extra form meta that needs to be sent to the CMS to return the list of layouts
 *  pager = A paging control for this Xibo Grid
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

$displays = Theme::Get('display-widget-rows');
$displays = (is_array($displays)) ? $displays : array();
?>
<div class="row">
    <div class="col-lg-3 col-md-6 col-xs-12">
      <div class="widget">
        <div class="widget-body">
          <div class="widget-icon orange pull-left">
            <i class="fa fa-sitemap"></i>
          </div>
          <div class="widget-content pull-left">
            <div class="title"><?php echo count($displays); ?></div>
            <div class="comment"><?php echo Theme::Translate('Displays'); ?></div>
          </div>
          <div class="clearfix"></div>
        </div>
      </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-6">
        <div class="widget">
            <div class="widget-title">
                <i class="fa fa-tasks"></i>
                <?php echo Theme::Translate('Bandwidth Usage'); ?>
                <div class="clearfix"></div>
            </div>
            <div class="widget-body medium no-padding">
                <div id="bandwidthChart" class="morrisChart" style="height: 230px;"></div>   
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="widget">
            <div class="widget-title">
                <i class="fa fa-tasks"></i>
                <?php echo Theme::Translate('Library Usage'); ?>
                <div class="clearfix"></div>
            </div>
            <div class="widget-body medium no-padding">
                <div id="libraryChart" class="morrisChart" style="height: 230px;"></div>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-6">
        <div class="widget">
            <div class="widget-title">
                <i class="fa fa-tasks"></i>
                <?php echo Theme::Translate('Display Activity'); ?>
                <div class="clearfix"></div>
            </div>
            <div class="widget-body medium no-padding">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?php echo Theme::Translate('Display'); ?></th> 
                                <th><?php echo Theme::Translate('Logged In'); ?></th>   
                                <th><?php echo Theme::Translate('Licence'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($displays as $row) { ?>
                            <tr class="<?php echo $row['mediainventorystatus']; ?>">
                                <td><?php echo $row['display']; ?></td>
                                <td><span class="<?php echo ($row['loggedin'] == 1) ? 'glyphicon glyphicon-ok' : 'glyphicon glyphicon-remove'; ?>"></span></td>
                                <td><span class="<?php echo ($row['licensed'] == 1) ? 'glyphicon glyphicon-ok' : 'glyphicon glyphicon-remove'; ?>"></span></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-6">
        <?php echo Theme::Get('embedded-widget'); ?>
    </div>
</div>
<script type="text/javascript">

    <?php if (Theme::Get('xmdsLimitSet') == '') { ?>
        var yKeys = ['value'];
        var labels = ['<?php echo Theme::Translate("Value"); ?>'];
    <?php } else { ?>
        var yKeys = ['value','limit'];
        var labels = ['<?php echo Theme::Translate("Value"); ?>','<?php echo Theme::Translate("Remaining"); ?>'];
    <?php } ?>

    var bandwidthChart = {
        type: 'bar',
        data: {
            element: 'bandwidthChart',
            data: <?php echo Theme::Get('bandwidthWidget'); ?>,
            xkey: 'label',
            ykeys: yKeys,
            labels: labels,
            stacked: <?php echo (Theme::Get('xmdsLimitSet') == '') ? 'false' : 'true'; ?>
        }
    };

    var libraryChart = {
        type: 'donut',
        data: {
            element: 'libraryChart',
            data: <?php echo Theme::Get('libraryWidget'); ?>
        }
    };
</script>