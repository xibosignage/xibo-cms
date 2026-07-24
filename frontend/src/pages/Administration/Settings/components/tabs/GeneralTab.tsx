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

import type { SettingsTabProps } from '../../SettingsConfig';
import SettingsSection from '../SettingsSection';

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
            label={t('Location of the Manual')}
            required
            helpText={t(
              'The address of the user manual, which will be used as a prefix for all help links.',
            )}
            value={formValues.HELP_BASE ?? ''}
            onChange={(v) => updateField('HELP_BASE', v)}
            disabled={!isEditable('HELP_BASE')}
          />
        )}

        {isVisible('QUICK_CHART_URL') && (
          <TextInput
            name="QUICK_CHART_URL"
            label={t('Quick Chart URL')}
            required
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
            title={t('Should the CMS send anonymous statistics to help improve the software?')}
            description={t(
              'When this is enabled the CMS will periodically send usage information to the software authors so that improvements can be made to the product.',
            )}
            checked={formValues.PHONE_HOME === '1'}
            onChange={(v) => updateField('PHONE_HOME', v ? '1' : '0')}
            disabled={!isEditable('PHONE_HOME')}
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
        {isVisible('SCHEDULE_LOOKAHEAD') && (
          <SwitchRow
            title={t('Send Schedule in advance?')}
            description={t('Should the CMS send future schedule information to Players?')}
            checked={formValues.SCHEDULE_LOOKAHEAD === '1'}
            onChange={(v) => updateField('SCHEDULE_LOOKAHEAD', v ? '1' : '0')}
            disabled={!isEditable('SCHEDULE_LOOKAHEAD')}
          />
        )}
      </SettingsSection>
      <SettingsSection title={t('Content')}>
        {isVisible('REQUIRED_FILES_LOOKAHEAD') && (
          <NumberInput
            name="REQUIRED_FILES_LOOKAHEAD"
            label={t('Send files in advance?')}
            helpText={t(
              'How many seconds in to the future should the calls to RequiredFiles look?',
            )}
            value={Number(formValues.REQUIRED_FILES_LOOKAHEAD) || 0}
            onChange={(v) => updateField('REQUIRED_FILES_LOOKAHEAD', String(v))}
            disabled={!isEditable('REQUIRED_FILES_LOOKAHEAD')}
          />
        )}

        {isVisible('SETTING_IMPORT_ENABLED') && (
          <SwitchRow
            title={t('Allow Import?')}
            checked={formValues.SETTING_IMPORT_ENABLED === '1'}
            onChange={(v) => updateField('SETTING_IMPORT_ENABLED', v ? '1' : '0')}
            disabled={!isEditable('SETTING_IMPORT_ENABLED')}
          />
        )}
        {isVisible('SETTING_LIBRARY_TIDY_ENABLED') && (
          <SwitchRow
            title={t('Enable Library Tidy?')}
            checked={formValues.SETTING_LIBRARY_TIDY_ENABLED === '1'}
            onChange={(v) => updateField('SETTING_LIBRARY_TIDY_ENABLED', v ? '1' : '0')}
            disabled={!isEditable('SETTING_LIBRARY_TIDY_ENABLED')}
          />
        )}
      </SettingsSection>
      <SettingsSection title={t('Other Features')}>
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
        {isVisible('DEFAULTS_IMPORTED') && (
          <SwitchRow
            title={t('Defaults Imported?')}
            description={t('Has the default layout been imported?')}
            checked={formValues.DEFAULTS_IMPORTED === '1'}
            onChange={(v) => updateField('DEFAULTS_IMPORTED', v ? '1' : '0')}
            disabled={!isEditable('DEFAULTS_IMPORTED')}
          />
        )}
        {isVisible('DASHBOARD_LATEST_NEWS_ENABLED') && (
          <SwitchRow
            title={t('Enable Latest News?')}
            description={t(
              'Should the Dashboard show latest news? The address is provided by the theme.',
            )}
            checked={formValues.DASHBOARD_LATEST_NEWS_ENABLED === '1'}
            onChange={(v) => updateField('DASHBOARD_LATEST_NEWS_ENABLED', v ? '1' : '0')}
            disabled={!isEditable('DASHBOARD_LATEST_NEWS_ENABLED')}
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
        {isVisible('REPORTS_EXPORT_SHOW_LOGO') && (
          <SwitchRow
            title={t('Show the Logo on report exports?')}
            description={t(
              'When exporting a saved report to PDF, should the logo be shown on the PDF?',
            )}
            checked={formValues.REPORTS_EXPORT_SHOW_LOGO === '1'}
            onChange={(v) => updateField('REPORTS_EXPORT_SHOW_LOGO', v ? '1' : '0')}
            disabled={!isEditable('REPORTS_EXPORT_SHOW_LOGO')}
          />
        )}
      </SettingsSection>
    </div>
  );
}
