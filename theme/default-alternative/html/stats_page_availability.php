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
        <div id="availabilityChart" class="morrisChart" style="height: 230px;"></div>
    </div>
</div>

<?php if (Theme::Get('availabilityWidget') != '') { ?>
<script type="text/javascript">

    var yKeys = ['value'];
    var labels = ['<?php echo Theme::Translate("Downtime"); ?>'];
    var availabilityChart = {
        type: 'bar',
        data: {
            element: 'availabilityChart',
            data: <?php echo Theme::Get('availabilityWidget'); ?>,
            xkey: 'label',
            ykeys: yKeys,
            labels: labels,
            stacked: false,
            postUnits: 'min',
            hoverCallback: function (index, options, content, row) {
                console.log(row);
                return secondsToTime(row.value * 60);
            }
        }
    };

    function secondsToTime(secs)
    {
        secs = Math.round(secs);
        var hours = Math.floor(secs / (60 * 60));

        var divisor_for_minutes = secs % (60 * 60);
        var minutes = Math.floor(divisor_for_minutes / 60);

        var divisor_for_seconds = divisor_for_minutes % 60;
        var seconds = Math.ceil(divisor_for_seconds);

        return hours + ":" + minutes + ":" + seconds;
    }
</script>
<?php } ?>
