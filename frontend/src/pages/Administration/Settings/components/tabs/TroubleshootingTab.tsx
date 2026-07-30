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

import DatePickerInput from '@/components/ui/forms/DatePickerInput';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import { formatDateTime } from '@/utils/date';

export default function TroubleshootingTab({
  formValues,
  updateField,
  isVisible,
}: SettingsTabProps) {
  const { t } = useTranslation();

  return (
    <div className="flex flex-col gap-3">
      <SettingsSection title={t('Logging')}>
        <div className="flex items-start justify-between space-x-4">
          {isVisible('RESTING_LOG_LEVEL') && (
            <SelectDropdown
              label={t('Resting Log Level')}
              helpText={t(
                'Set the level of the resting log level. The CMS will revert to this log level after an elevated period ends. In production systems "error" is recommended.',
              )}
              value={formValues.RESTING_LOG_LEVEL ?? ''}
              options={[
                { value: 'emergency', label: t('Emergency') },
                { value: 'alert', label: t('Alert') },
                { value: 'critical', label: t('Critical') },
                { value: 'error', label: t('Error') },
              ]}
              onSelect={(v) => updateField('RESTING_LOG_LEVEL', v)}
              className="w-full"
            />
          )}
          {isVisible('audit') && (
            <SelectDropdown
              label={t('Log Level')}
              helpText={t(
                'Set the level of logging the CMS should record. In production systems "error" is recommended.',
              )}
              value={formValues.audit ?? ''}
              options={[
                { value: 'emergency', label: t('Emergency') },
                { value: 'alert', label: t('Alert') },
                { value: 'critical', label: t('Critical') },
                { value: 'error', label: t('Error') },
                { value: 'warning', label: t('Warning') },
                { value: 'notice', label: t('Notice') },
                { value: 'info', label: t('Information') },
                { value: 'debug', label: t('Debug') },
              ]}
              onSelect={(v) => updateField('audit', v)}
              className="w-full"
            />
          )}
        </div>
        {isVisible('ELEVATE_LOG_UNTIL') && (
          <DatePickerInput
            label={t('Elevate Log Until')}
            helpText={t('Elevate the log level until this date.')}
            value={formValues.ELEVATE_LOG_UNTIL || undefined}
            onChange={(isoString) => {
              if (!isoString) {
                updateField('ELEVATE_LOG_UNTIL', '');
                return;
              }
              updateField('ELEVATE_LOG_UNTIL', formatDateTime(new Date(isoString)));
            }}
          />
        )}
      </SettingsSection>
      <SettingsSection title={t('Server Mode')}>
        {isVisible('SERVER_MODE') && (
          <SelectDropdown
            label={t('Server Mode')}
            helpText={t(
              'This should only be set if you want to display the maximum allowed error messaging through the user interface.\nUseful for capturing critical php errors and environment issues.',
            )}
            value={formValues.SERVER_MODE ?? ''}
            options={[
              { value: 'Production', label: t('Production') },
              { value: 'Test', label: t('Test') },
            ]}
            onSelect={(v) => updateField('SERVER_MODE', v)}
          />
        )}
      </SettingsSection>
    </div>
  );
}
