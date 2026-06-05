/*
 * Copyright (C) 2026 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

import { useTranslation } from 'react-i18next';

import type { SettingsTabProps } from '../SettingsConfig';
import SettingsSection from '../components/SettingsSection';

import NumberInput from '@/components/ui/forms/NumberInput';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import TextInput from '@/components/ui/forms/TextInput';
import SwitchRow from '@/pages/Administration/Users/components/SwitchRow';

export default function GeneralTab({
  formValues,
  updateField,
  isVisible,
  isEditable,
}: SettingsTabProps) {
  const { t } = useTranslation();

  return (
    <div className="flex flex-col gap-3">
      <SettingsSection title={t('Help & Resources')}>
        {isVisible('HELP_BASE') && (
          <TextInput
            name="HELP_BASE"
            label={t('User manual URL')}
            helpText={t('Used as the base URL for all in-app help links.')}
            value={formValues.HELP_BASE ?? ''}
            onChange={(v) => updateField('HELP_BASE', v)}
            disabled={!isEditable('HELP_BASE')}
          />
        )}

        {isVisible('QUICK_CHART_URL') && (
          <TextInput
            name="QUICK_CHART_URL"
            label={t('Quick Chart URL')}
            helpText={t(
              'Enter the URL to a Quick Chart service. This is used to draw charts in emailed reports and for showing a QR code during two factor authentication.',
            )}
            value={formValues.QUICK_CHART_URL ?? ''}
            onChange={(v) => updateField('QUICK_CHART_URL', v)}
            disabled={!isEditable('QUICK_CHART_URL')}
          />
        )}

        {isVisible('PHONE_HOME') && (
          <SwitchRow
            title={t('Send anonymous statistics to help us improve')}
            description={t(
              'Help us improve Xibo by automatically sending diagnostic and usage statistics.',
            )}
            checked={formValues.PHONE_HOME === '1'}
            onChange={(v) => updateField('PHONE_HOME', v ? '1' : '0')}
            disabled={!isEditable('PHONE_HOME')}
          />
        )}
        {isVisible('SCHEDULE_LOOKAHEAD') && (
          <SwitchRow
            title={t('Pre-send future Schedule to Players')}
            description={t('Send future Schedule information to Players.')}
            checked={formValues.SCHEDULE_LOOKAHEAD === '1'}
            onChange={(v) => updateField('SCHEDULE_LOOKAHEAD', v ? '1' : '0')}
            disabled={!isEditable('SCHEDULE_LOOKAHEAD')}
          />
        )}
        {isVisible('PHONE_HOME_KEY') && (
          <TextInput
            name="PHONE_HOME_KEY"
            label={t('Phone home key')}
            helpText={t(
              'Key used to distinguish each CMS instance. This is generated randomly based on the time you first installed the CMS, and is completely untraceable.',
            )}
            value={formValues.PHONE_HOME_KEY ?? ''}
            onChange={(v) => updateField('PHONE_HOME_KEY', v)}
            disabled={!isEditable('PHONE_HOME_KEY')}
          />
        )}
        {isVisible('PHONE_HOME_DATE') && (
          <TextInput
            name="PHONE_HOME_DATE"
            label={t('Phone home time')}
            helpText={t('The last time we PHONED_HOME in seconds since the epoch')}
            value={formValues.PHONE_HOME_DATE ?? ''}
            onChange={(v) => updateField('PHONE_HOME_DATE', v)}
            disabled={!isEditable('PHONE_HOME_DATE')}
          />
        )}
      </SettingsSection>
      <SettingsSection title={t('Content')}>
        {isVisible('REQUIRED_FILES_LOOKAHEAD') && (
          <NumberInput
            name="REQUIRED_FILES_LOOKAHEAD"
            label={t('Duration (seconds)')}
            value={Number(formValues.REQUIRED_FILES_LOOKAHEAD) || 0}
            onChange={(v) => updateField('REQUIRED_FILES_LOOKAHEAD', String(v))}
            disabled={!isEditable('REQUIRED_FILES_LOOKAHEAD')}
          />
        )}

        {isVisible('SETTING_IMPORT_ENABLED') && (
          <SwitchRow
            title={t('Allow Library imports')}
            description={t('Enable importing content packages into the Library.')}
            checked={formValues.SETTING_IMPORT_ENABLED === '1'}
            onChange={(v) => updateField('SETTING_IMPORT_ENABLED', v ? '1' : '0')}
            disabled={!isEditable('SETTING_IMPORT_ENABLED')}
          />
        )}
        {isVisible('SETTING_LIBRARY_TIDY_ENABLED') && (
          <SwitchRow
            title={t('Enable Library Tidy')}
            description={t('Show the Tidy Library button to remove unused media.')}
            checked={formValues.SETTING_LIBRARY_TIDY_ENABLED === '1'}
            onChange={(v) => updateField('SETTING_LIBRARY_TIDY_ENABLED', v ? '1' : '0')}
            disabled={!isEditable('SETTING_LIBRARY_TIDY_ENABLED')}
          />
        )}
      </SettingsSection>
      <SettingsSection title={t('Other Features')}>
        {isVisible('REPORTS_EXPORT_SHOW_LOGO') && (
          <SwitchRow
            title={t('Show logo on PDF Report exports')}
            description={t('Include the CMS logo when exporting saved reports to PDF.')}
            checked={formValues.REPORTS_EXPORT_SHOW_LOGO === '1'}
            onChange={(v) => updateField('REPORTS_EXPORT_SHOW_LOGO', v ? '1' : '0')}
            disabled={!isEditable('REPORTS_EXPORT_SHOW_LOGO')}
          />
        )}

        {isVisible('DASHBOARD_LATEST_NEWS_ENABLED') && (
          <SwitchRow
            title={t('Show latest news on Dashboard')}
            description={t("Display the theme provider's news feed on the status dashboard.")}
            checked={formValues.DASHBOARD_LATEST_NEWS_ENABLED === '1'}
            onChange={(v) => updateField('DASHBOARD_LATEST_NEWS_ENABLED', v ? '1' : '0')}
            disabled={!isEditable('DASHBOARD_LATEST_NEWS_ENABLED')}
          />
        )}
        {isVisible('DEFAULTS_IMPORTED') && (
          <SwitchRow
            title={t('Send anonymous usage statistics')}
            description={t(
              'Share anonymised data to help improve Xibo. No personal data is included.',
            )}
            checked={formValues.DEFAULTS_IMPORTED === '1'}
            onChange={(v) => updateField('DEFAULTS_IMPORTED', v ? '1' : '0')}
            disabled={!isEditable('DEFAULTS_IMPORTED')}
          />
        )}
        {isVisible('EMBEDDED_STATUS_WIDGET') && (
          <TextInput
            name="EMBEDDED_STATUS_WIDGET"
            label={t('Status Dashboard Widget')}
            helpText={t('HTML to embed in an iframe on the Status Dashboard')}
            value={formValues.EMBEDDED_STATUS_WIDGET ?? ''}
            onChange={(v) => updateField('EMBEDDED_STATUS_WIDGET', v)}
            disabled={!isEditable('EMBEDDED_STATUS_WIDGET')}
          />
        )}
        {isVisible('INSTANCE_SUSPENDED') && (
          <SelectDropdown
            label={t('Instance Suspended')}
            helpText={t(
              'Is this instance suspended? Warning: Direct database access will be required to reactivate the CMS if you select Yes',
            )}
            value={formValues.INSTANCE_SUSPENDED ?? ''}
            options={[
              { value: 'no', label: t('No') },
              { value: 'partial', label: t('Partially') },
              { value: 'yes', label: t('Yes') },
            ]}
            onSelect={(v) => updateField('INSTANCE_SUSPENDED', v)}
          />
        )}
        {isVisible('LATEST_NEWS_URL') && (
          <TextInput
            name="LATEST_NEWS_URL"
            label={t('Latest News URL')}
            helpText={t('RSS/Atom Feed to be displayed on the Status Dashboard')}
            value={formValues.LATEST_NEWS_URL ?? ''}
            onChange={(v) => updateField('LATEST_NEWS_URL', v)}
            disabled={!isEditable('LATEST_NEWS_URL')}
          />
        )}
      </SettingsSection>
    </div>
  );
}
