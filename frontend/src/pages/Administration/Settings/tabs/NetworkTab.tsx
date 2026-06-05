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

export default function NetworkTab({
  formValues,
  updateField,
  isVisible,
  isEditable,
}: SettingsTabProps) {
  const { t } = useTranslation();

  return (
    <div className="flex flex-col gap-3">
      <SettingsSection title={t('Email')}>
        <div className="flex items-start justify-between space-x-4">
          {isVisible('mail_to') && (
            <TextInput
              name="mail_to"
              label={t('Admin Email Address')}
              helpText={t('Receives all CMS-generated email alerts.')}
              value={formValues.mail_to ?? ''}
              onChange={(v) => updateField('mail_to', v)}
              disabled={!isEditable('mail_to')}
            />
          )}
          {isVisible('mail_from') && (
            <TextInput
              name="mail_from"
              label={t('Sending Address')}
              helpText={t('Emails are sent from this address.')}
              value={formValues.mail_from ?? ''}
              onChange={(v) => updateField('mail_from', v)}
              disabled={!isEditable('mail_from')}
            />
          )}
        </div>
        {isVisible('mail_from_name') && (
          <TextInput
            name="mail_from_name"
            label={t('Sending Name')}
            helpText={t('Mail will be sent under this name.')}
            value={formValues.mail_from_name ?? ''}
            onChange={(v) => updateField('mail_from_name', v)}
            disabled={!isEditable('mail_from_name')}
          />
        )}
      </SettingsSection>
      <SettingsSection title={t('Proxy')}>
        <div className="flex items-start justify-between space-x-4">
          {isVisible('PROXY_HOST') && (
            <TextInput
              name="PROXY_HOST"
              label={t('Proxy URL')}
              value={formValues.PROXY_HOST ?? ''}
              onChange={(v) => updateField('PROXY_HOST', v)}
              disabled={!isEditable('PROXY_HOST')}
            />
          )}
          {isVisible('PROXY_PORT') && (
            <NumberInput
              name="PROXY_PORT"
              label={t('Port')}
              value={Number(formValues.PROXY_PORT) || 0}
              onChange={(v) => updateField('PROXY_PORT', String(v))}
              disabled={!isEditable('PROXY_PORT')}
            />
          )}
        </div>
        <div className="flex items-start justify-between space-x-4">
          {isVisible('PROXY_AUTH') && (
            <TextInput
              name="PROXY_AUTH"
              label={t('Credentials')}
              helpText={t('Leave blank if not required.')}
              placeholder={t('username:password')}
              value={formValues.PROXY_AUTH ?? ''}
              onChange={(v) => updateField('PROXY_AUTH', v)}
              disabled={!isEditable('PROXY_AUTH')}
            />
          )}
          {isVisible('PROXY_EXCEPTIONS') && (
            <TextInput
              name="PROXY_EXCEPTIONS"
              label={t('Exceptions')}
              helpText={t(
                'Hosts and Keywords that should not be loaded via the Proxy Specified. These should be comma separated.',
              )}
              placeholder={t('Enter Proxy Exceptions')}
              value={formValues.PROXY_EXCEPTIONS ?? ''}
              onChange={(v) => updateField('PROXY_EXCEPTIONS', v)}
              disabled={!isEditable('PROXY_EXCEPTIONS')}
            />
          )}
        </div>
      </SettingsSection>
      <SettingsSection title={t('Limits')}>
        <div className="flex items-start justify-between space-x-4">
          {isVisible('MONTHLY_XMDS_TRANSFER_LIMIT_KB') && (
            <NumberInput
              name="MONTHLY_XMDS_TRANSFER_LIMIT_KB"
              label={t('Monthly bandwidth limit (KB)')}
              helpText={t('0 = unlimited.')}
              value={Number(formValues.MONTHLY_XMDS_TRANSFER_LIMIT_KB) || 0}
              onChange={(v) => updateField('MONTHLY_XMDS_TRANSFER_LIMIT_KB', String(v))}
              disabled={!isEditable('MONTHLY_XMDS_TRANSFER_LIMIT_KB')}
            />
          )}
          {isVisible('LIBRARY_SIZE_LIMIT_KB') && (
            <NumberInput
              name="LIBRARY_SIZE_LIMIT_KB"
              label={t('Library size limit (KB)')}
              helpText={t('0 = unlimited.')}
              value={Number(formValues.LIBRARY_SIZE_LIMIT_KB) || 0}
              onChange={(v) => updateField('LIBRARY_SIZE_LIMIT_KB', String(v))}
              disabled={!isEditable('LIBRARY_SIZE_LIMIT_KB')}
            />
          )}
        </div>
      </SettingsSection>
      <SettingsSection title={t('HTTPS & Security')}>
        {isVisible('FORCE_HTTPS') && (
          <SwitchRow
            title={t('Allow Library imports')}
            description={t('Enable importing content packages into the Library.')}
            checked={formValues.FORCE_HTTPS === '1'}
            onChange={(v) => updateField('FORCE_HTTPS', v ? '1' : '0')}
            disabled={!isEditable('FORCE_HTTPS')}
          />
        )}
        {isVisible('ISSUE_STS') && (
          <SwitchRow
            title={t('Enable Library Tidy')}
            description={t('Show the Tidy Library button to remove unused media.')}
            checked={formValues.ISSUE_STS === '1'}
            onChange={(v) => updateField('ISSUE_STS', v ? '1' : '0')}
            disabled={!isEditable('ISSUE_STS')}
          />
        )}
        {isVisible('STS_TTL') && (
          <NumberInput
            name="STS_TTL"
            label={t('STS Time-out (seconds)')}
            helpText={t('The Time to Live (maxage) of the STS header expressed in seconds.')}
            value={Number(formValues.STS_TTL) || 0}
            onChange={(v) => updateField('STS_TTL', String(v))}
            disabled={!isEditable('STS_TTL')}
          />
        )}
        {isVisible('WHITELIST_LOAD_BALANCERS') && (
          <TextInput
            name="WHITELIST_LOAD_BALANCERS"
            label={t('Whitelist Load Balancers')}
            helpText={t(
              'If the CMS is behind a load balancer, what are the load balancer IP addresses, comma delimited.',
            )}
            placeholder={t('Comma-separated Ips')}
            value={formValues.WHITELIST_LOAD_BALANCERS ?? ''}
            onChange={(v) => updateField('WHITELIST_LOAD_BALANCERS', v)}
            disabled={!isEditable('WHITELIST_LOAD_BALANCERS')}
          />
        )}
        {isVisible('SENDFILE_MODE') && (
          <SelectDropdown
            label={t('File download mode')}
            helpText={t(
              'Should the CMS use Apache X-Sendfile, Nginx X-Accel, or PHP (Off) to return the files from the library?',
            )}
            value={formValues.SENDFILE_MODE ?? ''}
            options={[
              { value: 'Off', label: t('Off') },
              { value: 'Apache', label: 'Apache' },
              { value: 'Nginx', label: 'Nginx' },
            ]}
            onSelect={(v) => updateField('SENDFILE_MODE', v)}
          />
        )}
        {isVisible('CDN_URL') && (
          <TextInput
            name="CDN_URL"
            label={t('CDN Address')}
            helpText={t('Content Delivery Network Address for serving file requests to Players')}
            value={formValues.CDN_URL ?? ''}
            onChange={(v) => updateField('CDN_URL', v)}
            disabled={!isEditable('CDN_URL')}
          />
        )}
      </SettingsSection>
    </div>
  );
}
