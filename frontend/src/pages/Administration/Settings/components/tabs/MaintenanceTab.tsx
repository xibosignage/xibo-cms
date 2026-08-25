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
import SwitchRow from '@/pages/Administration/Users/components/SwitchRow';

export default function MaintenanceTab({
  formValues,
  updateField,
  isVisible,
  isEditable,
}: SettingsTabProps) {
  const { t } = useTranslation();

  return (
    <div className="flex flex-col gap-3">
      <SettingsSection title={t('Task Runner')}>
        {isVisible('MAINTENANCE_ENABLED') && (
          <SelectDropdown
            label={t('Enable Maintenance?')}
            helpText={t('Allow the maintenance script to run if it is called?')}
            value={formValues.MAINTENANCE_ENABLED ?? ''}
            options={[
              { value: 'Off', label: t('Off') },
              { value: 'On', label: t('On') },
              { value: 'Protected', label: t('Protected') },
            ]}
            onSelect={(v) => updateField('MAINTENANCE_ENABLED', v)}
            disabled={!isEditable('MAINTENANCE_ENABLED')}
          />
        )}
      </SettingsSection>
      <SettingsSection title={t('Email Alerts')}>
        {isVisible('MAINTENANCE_EMAIL_ALERTS') && (
          <SwitchRow
            title={t('Enable Email Alerts?')}
            description={t('Global switch for email alerts to be sent')}
            checked={formValues.MAINTENANCE_EMAIL_ALERTS === '1'}
            onChange={(v) => updateField('MAINTENANCE_EMAIL_ALERTS', v ? '1' : '0')}
            disabled={!isEditable('MAINTENANCE_EMAIL_ALERTS')}
          />
        )}
        {isVisible('MAINTENANCE_ALWAYS_ALERT') && (
          <SwitchRow
            title={t('Send repeat Display Timeouts')}
            description={t(
              'Should the CMS send an email if a display is in an error state every time maintenance runs?',
            )}
            checked={formValues.MAINTENANCE_ALWAYS_ALERT === '1'}
            onChange={(v) => updateField('MAINTENANCE_ALWAYS_ALERT', v ? '1' : '0')}
            disabled={!isEditable('MAINTENANCE_ALWAYS_ALERT')}
          />
        )}
        {isVisible('MAINTENANCE_ALERT_TOUT') && (
          <NumberInput
            name="MAINTENANCE_ALERT_TOUT"
            label={t('Max Display Timeout')}
            helpText={t(
              'How long in minutes after the last time a Player connects should we send an alert? Can be overridden on a per Player basis.',
            )}
            value={Number(formValues.MAINTENANCE_ALERT_TOUT) || 0}
            onChange={(v) => updateField('MAINTENANCE_ALERT_TOUT', String(v))}
            disabled={!isEditable('MAINTENANCE_ALERT_TOUT')}
          />
        )}
      </SettingsSection>
      <SettingsSection title={t('Data Retention')}>
        <div className="flex items-start justify-between space-x-4">
          {isVisible('MAINTENANCE_LOG_MAXAGE') && (
            <NumberInput
              name="MAINTENANCE_LOG_MAXAGE"
              label={t('Max Log Age')}
              helpText={t(
                'Maximum age for log entries in days. Set to 0 to keep logs indefinitely.',
              )}
              value={Number(formValues.MAINTENANCE_LOG_MAXAGE) || 0}
              onChange={(v) => updateField('MAINTENANCE_LOG_MAXAGE', String(v))}
              disabled={!isEditable('MAINTENANCE_LOG_MAXAGE')}
            />
          )}
          {isVisible('MAINTENANCE_STAT_MAXAGE') && (
            <NumberInput
              name="MAINTENANCE_STAT_MAXAGE"
              label={t('Max Statistics Age')}
              helpText={t(
                'Maximum age for statistics entries in days. Entries older than this will not be processed and existing entries will be removed. Set to 0 to keep statistics indefinitely.',
              )}
              value={Number(formValues.MAINTENANCE_STAT_MAXAGE) || 0}
              onChange={(v) => updateField('MAINTENANCE_STAT_MAXAGE', String(v))}
              disabled={!isEditable('MAINTENANCE_STAT_MAXAGE')}
            />
          )}
        </div>
      </SettingsSection>
    </div>
  );
}
