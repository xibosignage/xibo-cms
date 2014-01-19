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
 *
 * Theme variables:
 *  form_id = The ID of the Form
 * 	form_meta = Extra META information required by the Transation
 * 	form_action = The URL for calling the Transaction
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<div class="container-fluid">
    <form id="<?php echo Theme::Get('form_id'); ?>" class="XiboForm form-horizontal" method="post" action="<?php echo Theme::Get('form_action'); ?>">
    	<?php echo Theme::Get('form_meta'); ?>
        <div class="row-fluid">
            <div class="span6">
                <div class="control-group">
                    <label class="control-label" for="display" accesskey="n" title="<?php echo Theme::Translate('The Name of the Display - (1 - 50 characters).'); ?>"><?php echo Theme::Translate('Display'); ?></label>
                    <div class="controls">
                        <input class="required" name="display" type="text" id="display" tabindex="1" value="<?php echo Theme::Get('display'); ?>" maxlength="50" />
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label" for="hardwareKey" accesskey="n" title="<?php echo Theme::Translate(''); ?>"><?php echo Theme::Translate('Display\'s Hardware Key'); ?></label>
                    <div class="controls">
                        <input class="" name="hardwareKey" type="text" id="hardwareKey" tabindex="1" value="<?php echo Theme::Get('license'); ?>" />
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label" for="licensed" accesskey="n" title="<?php echo Theme::Translate('Use one of the available licenses for this display?'); ?>"><?php echo Theme::Translate('Licence Display?'); ?></label>
                    <div class="controls">
                        <?php echo Theme::SelectList('licensed', Theme::Get('license_field_list'), 'licensedid', 'licensed', Theme::Get('licensed')); ?>
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label" for="inc_schedule" accesskey="n" title="<?php echo Theme::Translate('Whether to always put the default layout into the cycle.'); ?>"><?php echo Theme::Translate('Interleave Default'); ?></label>
                    <div class="controls">
                        <?php echo Theme::SelectList('inc_schedule', Theme::Get('interleave_default_field_list'), 'inc_scheduleid', 'inc_schedule', Theme::Get('inc_schedule')); ?>
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label" for="defaultlayoutid" accesskey="n" title="<?php echo Theme::Translate('The Default Layout to Display where there is no other content.'); ?>"><?php echo Theme::Translate('Default Layout'); ?></label>
                    <div class="controls">
                        <?php echo Theme::SelectList('defaultlayoutid', Theme::Get('default_layout_field_list'), 'layoutid', 'layout', Theme::Get('defaultlayoutid')); ?>
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label" for="auditing" accesskey="n" title="<?php echo Theme::Translate('Collect auditing from this client. Should only be used if there is a problem with the display.'); ?>"><?php echo Theme::Translate('Auditing'); ?></label>
                    <div class="controls">
                        <?php echo Theme::SelectList('auditing', Theme::Get('auditing_field_list'), 'auditingid', 'auditing', Theme::Get('auditing')); ?>
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label" for="email_alert" accesskey="n" title="<?php echo Theme::Translate('Do you want to be notified by email if there is a problem with this display?'); ?>"><?php echo Theme::Translate('Email Alerts'); ?></label>
                    <div class="controls">
                        <?php echo Theme::SelectList('email_alert', Theme::Get('email_alert_field_list'), 'email_alertid', 'email_alert', Theme::Get('email_alert')); ?>
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label" for="alert_timeout" accesskey="n" title="<?php echo Theme::Translate('How long in minutes after the display last connected to the webservice should we send an alert. Set this value higher than the collection interval on the client. Set to 0 to use global default.'); ?>"><?php echo Theme::Translate('Alert Timeout'); ?></label>
                    <div class="controls">
                        <input class="" name="alert_timeout" type="text" id="alert_timeout" tabindex="1" value="<?php echo Theme::Get('alert_timeout'); ?>" />
                    </div>
                </div>
            </div>
            <div class="span6">
                <div class="control-group">
                    <div class="controls">
                        <label class="checkbox" for="wakeOnLanEnabled" accesskey="n" title="<?php echo Theme::Translate('Wake on Lan requires the correct network configuration to route the magic packet to the display PC'); ?>"><?php echo Theme::Translate('Enable Wake on LAN'); ?>
                            <input class="checkbox" type="checkbox" id="wakeOnLanEnabled" name="wakeOnLanEnabled" <?php echo Theme::Get('wake_on_lan_checked'); ?>>
                        </label>
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label" for="broadCastAddress" accesskey="n" title="<?php echo Theme::Translate('The IP address of the remote host\'s broadcast address (or gateway)'); ?>"><?php echo Theme::Translate('BroadCast Address'); ?></label>
                    <div class="controls">
                        <input class="" name="broadCastAddress" type="text" id="broadCastAddress" tabindex="1" value="<?php echo Theme::Get('broadCastAddress'); ?>" />
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label" for="secureOn" accesskey="n" title="<?php echo Theme::Translate('Enter a hexidecimal password of a SecureOn enabled Network Interface Card (NIC) of the remote host. Enter a value in this pattern: \'xx-xx-xx-xx-xx-xx\'. Leave the following field empty, if SecureOn is not used (for example, because the NIC of the remote host does not support SecureOn).'); ?>"><?php echo Theme::Translate('Wake on LAN SecureOn'); ?></label>
                    <div class="controls">
                        <input class="" name="secureOn" type="text" id="secureOn" tabindex="1" value="<?php echo Theme::Get('secureOn'); ?>" />
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label" for="wakeOnLanTime" accesskey="n" title="<?php echo Theme::Translate('The time this display should receive the WOL command, using the 24hr clock - e.g. 19:00. Maintenance must be enabled.'); ?>"><?php echo Theme::Translate('Wake on LAN Time'); ?></label>
                    <div class="controls">
                        <input class="" name="wakeOnLanTime" type="text" id="wakeOnLanTime" tabindex="1" value="<?php echo Theme::Get('wakeOnLanTime'); ?>" />
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label" for="cidr" accesskey="n" title="<?php echo Theme::Translate('Enter a number within the range of 0 to 32 in the following field. Leave the following field empty, if no subnet mask should be used (CIDR = 0). If the remote host\'s broadcast address is unkown: Enter the host name or IP address of the remote host in Broad Cast Address and enter the CIDR subnet mask of the remote host in this field.'); ?>"><?php echo Theme::Translate('Wake on LAN CIDR'); ?></label>
                    <div class="controls">
                        <input class="" name="cidr" type="text" id="cidr" tabindex="1" value="<?php echo Theme::Get('cidr'); ?>" />
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label" for="latitude" accesskey="n" title="<?php echo Theme::Translate('The Latitude of this display'); ?>"><?php echo Theme::Translate('Latitude'); ?></label>
                    <div class="controls">
                        <input class="" name="latitude" type="text" id="latitude" tabindex="1" value="<?php echo Theme::Get('latitude'); ?>" />
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label" for="longitude" accesskey="n" title="<?php echo Theme::Translate('The Longitude of this Display'); ?>"><?php echo Theme::Translate('Longitude'); ?></label>
                    <div class="controls">
                        <input class="" name="longitude" type="text" id="longitude" tabindex="1" value="<?php echo Theme::Get('longitude'); ?>" />
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>