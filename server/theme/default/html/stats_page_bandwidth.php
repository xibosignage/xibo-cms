<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
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
<div class="row">
    <div class="col-md-12">
        <div id="bandwidthChart" class="morrisChart" style="height: 230px;"></div>
    </div>
</div>

<?php if (Theme::Get('bandwidthWidget') != '') { ?>
<script type="text/javascript">

    var yKeys = ['value'];
    var labels = ['<?php echo Theme::Translate("Bandwidth"); ?>'];
    var bandwidthChart = {
        type: 'bar',
        data: {
            element: 'bandwidthChart',
            data: <?php echo Theme::Get('bandwidthWidget'); ?>,
            xkey: 'label',
            ykeys: yKeys,
            labels: labels,
            stacked: false,
            postUnits: '<?php echo Theme::Get('bandwidthWidgetUnits'); ?>'
        }
    };
</script>
<?php } ?>
