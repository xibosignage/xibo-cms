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
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

// Translations we want always available
Theme::SetTranslation('multiselect', Theme::Translate('Multiple Items Selected'));
Theme::SetTranslation('multiselectNoItemsMessage', Theme::Translate('Sorry, no items have been selected.'));
Theme::SetTranslation('multiselectMessage', Theme::Translate('Caution, you have selected %1 items. Clicking save will run the %2 transaction on all these items.'));
Theme::SetTranslation('save', Theme::Translate('Save'));
Theme::SetTranslation('cancel', Theme::Translate('Cancel'));
Theme::SetTranslation('close', Theme::Translate('Close'));
Theme::SetTranslation('success', Theme::Translate('Success'));
Theme::SetTranslation('failure', Theme::Translate('Failure'));
Theme::SetTranslation('enterText', Theme::Translate('Enter text...'));
?>
                </div>
            </div>
        </div>
        </div>
        </div>

		<script src="theme/default/libraries/jquery/jquery-1.11.1.min.js"></script>
		<script src="theme/default/libraries/jquery/jquery.validate.min.js"></script>
		<script src="theme/default/libraries/jquery/additional-methods.min.js"></script>
        <script src="theme/default/libraries/bootstrap/js/bootstrap.min.js"></script>
        <script src="theme/default/libraries/bootstrap/js/bootbox.min.js"></script>
        <?php if (Config::GetSetting('CALENDAR_TYPE') == 'Jalali') { ?>
        <script src="theme/default/libraries/bootstrap-datetimepicker/js/jalali-date.js"></script>
        <script src="theme/default/libraries/bootstrap-datetimepicker/js/bootstrap-datetimepicker-jalali.min.js"></script>
        <?php } else { ?>
        <script src="theme/default/libraries/bootstrap-datetimepicker/js/bootstrap-datetimepicker.min.js"></script>
        <?php } ?>
        <?php echo Theme::Script('libraries/bootstrap-datetimepicker/js/locales/bootstrap-datetimepicker.' . TranslationEngine::GetJsLocale() . '.js'); ?>
        <script src="theme/default/libraries/jquery-tablesorter/js/jquery.tablesorter.min.js"></script>
        <script src="theme/default/libraries/jquery-tablesorter/js/jquery.tablesorter.widgets.min.js"></script>
        <script src="theme/default/libraries/jquery-tablesorter/js/parsers/parser-input-select.js"></script>
        <script src="theme/default/libraries/jquery-tablesorter/js/widgets/widget-grouping.js"></script>
        <script src="theme/default/libraries/jquery-tablesorter/js/widgets/widget-pager.js"></script>
		<script src="theme/default/libraries/jquery/jquery-ui/jquery-ui-1.10.2.custom.min.js"></script>
        <script src="theme/default/libraries/bootstrap-colorpicker/js/bootstrap-colorpicker.min.js"></script>
        <script src="theme/default/libraries/bootstrap-select/js/bootstrap-select.min.js"></script>
        <script src="theme/default/libraries/bootstrap-ekko-lightbox/ekko-lightbox.min.js"></script>
        <script src="theme/default/libraries/underscore/underscore-min.js"></script>
        <script src="theme/default/libraries/jstimezonedetect/jstz.min.js"></script>
        <script src="theme/default/libraries/calendar/js/calendar.js"></script>
        <?php echo Theme::Script('libraries/calendar/js/language/' . ((strlen(TranslationEngine::GetJsLocale() <= 2)) ? TranslationEngine::GetJsLocale() . '-' . strtoupper(TranslationEngine::GetJsLocale()) : TranslationEngine::GetJsLocale()) . '.js'); ?>
        <script src="theme/default/libraries/ckeditor/ckeditor.js"></script>
    	<script src="theme/default/libraries/bootstrap/js/bootstrap-ckeditor-fix.js"></script>
        <script src="theme/default/libraries/jquery-file-upload/js/tmpl.min.js"></script>
        <script src="theme/default/libraries/jquery-file-upload/js/load-image.min.js"></script>
        <script src="theme/default/libraries/jquery-file-upload/js/jquery.iframe-transport.js"></script>
        <script src="theme/default/libraries/jquery-file-upload/js/jquery.fileupload.js"></script>
        <script src="theme/default/libraries/jquery-file-upload/js/jquery.fileupload-process.js"></script>
        <script src="theme/default/libraries/jquery-file-upload/js/jquery.fileupload-resize.js"></script>
        <script src="theme/default/libraries/jquery-file-upload/js/jquery.fileupload-validate.js"></script>
        <script src="theme/default/libraries/jquery-file-upload/js/jquery.fileupload-ui.js"></script>
        <script src="theme/default/libraries/jquery-message-queuing/jquery.ba-jqmq.min.js"></script>
        <script src="theme/default/libraries/date-time-format.js"></script>
        <script src="theme/default/libraries/momentjs/moment.js"></script>
        <script src="theme/default/libraries/morrisjs/raphael.min.js"></script>
        <script src="theme/default/libraries/morrisjs/morris.min.js"></script>
        <script src="theme/default/libraries/colors/colors.min.js"></script>
        <script src="theme/default/js/xibo-cms.js"></script>
    	<script src="theme/default/js/xibo-forms.js"></script>
    	<script src="theme/default/js/xibo-layout-designer.js"></script>
    	<script src="theme/default/js/xibo-preview-timeline.js"></script>
    	<script src="theme/default/js/xibo-calendar.js"></script>
    	<script src="theme/default/js/xibo-datasets.js"></script>
        <script type="text/javascript">
        var translations = <?php echo ((Theme::Get('translations') == '') ? '{}' : Theme::Get('translations')); ?>;
        var language = "<?php echo TranslationEngine::GetJsLocale(); ?>";
        var dateFormat = "<?php echo Config::GetSetting('DATE_FORMAT', 'Y-m-d h:i'); ?>";
        var calendarType = "<?php echo Config::GetSetting('CALENDAR_TYPE'); ?>";
        var calendarLanguage = "<?php echo ((strlen(TranslationEngine::GetJsLocale() <= 2)) ? TranslationEngine::GetJsLocale() . '-' . strtoupper(TranslationEngine::GetJsLocale()) : TranslationEngine::GetJsLocale()); ?>";
        </script>
	</body>
</html>