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

import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { SettingsTabProps } from '../../SettingsConfig';
import SettingsSection from '../SettingsSection';

import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import type { SelectOption } from '@/components/ui/forms/SelectDropdown';
import TextInput from '@/components/ui/forms/TextInput';
import { useDebounce } from '@/hooks/useDebounce';
import { fetchUsers } from '@/services/userApi';
import { fetchUserGroups } from '@/services/userGroupApi';

const PAGE_SIZE = 10;

function useUserOptions() {
  const [options, setOptions] = useState<SelectOption[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [hasMore, setHasMore] = useState(false);
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounce(search, 300);
  const pageRef = useRef(0);

  useEffect(() => {
    setIsLoading(true);
    setOptions([]);
    pageRef.current = 0;
    fetchUsers({
      start: 0,
      length: PAGE_SIZE,
      userTypeId: 1,
      userName: debouncedSearch || undefined,
    })
      .then((res) => {
        setOptions(res.rows.map((u) => ({ value: String(u.userId), label: u.userName })));
        setHasMore(res.rows.length === PAGE_SIZE);
        pageRef.current = 1;
      })
      .catch(() => setOptions([]))
      .finally(() => setIsLoading(false));
  }, [debouncedSearch]);

  const loadMore = () => {
    if (isLoadingMore || !hasMore) return;
    setIsLoadingMore(true);
    fetchUsers({
      start: pageRef.current * PAGE_SIZE,
      length: PAGE_SIZE,
      userTypeId: 1,
      userName: debouncedSearch || undefined,
    })
      .then((res) => {
        setOptions((prev) => [
          ...prev,
          ...res.rows.map((u) => ({ value: String(u.userId), label: u.userName })),
        ]);
        setHasMore(res.rows.length === PAGE_SIZE);
        pageRef.current += 1;
      })
      .catch(() => {})
      .finally(() => setIsLoadingMore(false));
  };

  return { options, isLoading, isLoadingMore, hasMore, loadMore, setSearch };
}

function useUserGroupOptions() {
  const [options, setOptions] = useState<SelectOption[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [hasMore, setHasMore] = useState(false);
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounce(search, 300);
  const pageRef = useRef(0);

  useEffect(() => {
    setIsLoading(true);
    setOptions([]);
    pageRef.current = 0;
    fetchUserGroups({
      start: 0,
      length: PAGE_SIZE,
      userGroup: debouncedSearch || undefined,
    })
      .then((res) => {
        setOptions(res.rows.map((g) => ({ value: String(g.groupId), label: g.group })));
        setHasMore(res.rows.length === PAGE_SIZE);
        pageRef.current = 1;
      })
      .catch(() => setOptions([]))
      .finally(() => setIsLoading(false));
  }, [debouncedSearch]);

  const loadMore = () => {
    if (isLoadingMore || !hasMore) return;
    setIsLoadingMore(true);
    fetchUserGroups({
      start: pageRef.current * PAGE_SIZE,
      length: PAGE_SIZE,
      userGroup: debouncedSearch || undefined,
    })
      .then((res) => {
        setOptions((prev) => [
          ...prev,
          ...res.rows.map((g) => ({ value: String(g.groupId), label: g.group })),
        ]);
        setHasMore(res.rows.length === PAGE_SIZE);
        pageRef.current += 1;
      })
      .catch(() => {})
      .finally(() => setIsLoadingMore(false));
  };

  return { options, isLoading, isLoadingMore, hasMore, loadMore, setSearch };
}

export default function UsersTab({
  formValues,
  updateField,
  isVisible,
  isEditable,
  relatedEntities,
}: SettingsTabProps) {
  const { t } = useTranslation();

  const userOptions = useUserOptions();
  const userGroupOptions = useUserGroupOptions();

  return (
    <div className="flex flex-col gap-3">
      <SettingsSection title={t('Account Defaults')}>
        <div className="flex items-start justify-between space-x-4">
          {isVisible('SYSTEM_USER') && (
            <SelectDropdown
              label={t('System User')}
              helpText={t('The system User for this CMS.')}
              value={formValues.SYSTEM_USER ?? ''}
              initialLabel={relatedEntities.systemUser?.userName}
              options={userOptions.options}
              onSelect={(v) => updateField('SYSTEM_USER', v ?? '')}
              isLoading={userOptions.isLoading}
              onLoadMore={userOptions.loadMore}
              hasMore={userOptions.hasMore}
              isLoadingMore={userOptions.isLoadingMore}
              searchable
              searchPlaceholder={t('Search users...')}
              onSearch={userOptions.setSearch}
              className="flex-1"
            />
          )}
          {isVisible('DEFAULT_USERGROUP') && (
            <SelectDropdown
              label={t('Default User Group')}
              helpText={t('The default User Group for new Users.')}
              value={formValues.DEFAULT_USERGROUP ?? ''}
              initialLabel={relatedEntities.defaultUserGroup?.group}
              options={userGroupOptions.options}
              onSelect={(v) => updateField('DEFAULT_USERGROUP', v ?? '')}
              isLoading={userGroupOptions.isLoading}
              onLoadMore={userGroupOptions.loadMore}
              hasMore={userGroupOptions.hasMore}
              isLoadingMore={userGroupOptions.isLoadingMore}
              searchable
              searchPlaceholder={t('Search groups...')}
              onSearch={userGroupOptions.setSearch}
              className="flex-1"
            />
          )}
        </div>
        {isVisible('defaultUsertype') && (
          <SelectDropdown
            label={t('Default User Type')}
            helpText={t(
              'Sets the default user type selected when creating a user. We recommend that this is set to User',
            )}
            value={formValues.defaultUsertype ?? ''}
            options={[
              { value: 'User', label: t('User') },
              { value: 'Group Admin', label: t('Group Admin') },
              { value: 'Super Admin', label: t('Super Admin') },
            ]}
            onSelect={(v) => updateField('defaultUsertype', v)}
          />
        )}
      </SettingsSection>
      <SettingsSection title={t('Password Policy')}>
        {isVisible('USER_PASSWORD_POLICY') && (
          <TextInput
            name="USER_PASSWORD_POLICY"
            label={t('Password Policy Regular Expression')}
            helpText={t('Regular Expression for password complexity, leave blank for no policy.')}
            value={formValues.USER_PASSWORD_POLICY ?? ''}
            onChange={(v) => updateField('USER_PASSWORD_POLICY', v)}
            disabled={!isEditable('USER_PASSWORD_POLICY')}
          />
        )}
        {isVisible('USER_PASSWORD_ERROR') && (
          <TextInput
            name="USER_PASSWORD_ERROR"
            label={t('Description of Password Policy')}
            helpText={t(
              'A text description of this password policy will be shown to users if they enter a password that does not meet the policy requirements set above.',
            )}
            value={formValues.USER_PASSWORD_ERROR ?? ''}
            onChange={(v) => updateField('USER_PASSWORD_ERROR', v)}
            disabled={!isEditable('USER_PASSWORD_ERROR')}
          />
        )}
        {isVisible('PASSWORD_REMINDER_ENABLED') && (
          <SelectDropdown
            label={t('Password Reminder')}
            helpText={t(
              'Enable password reminder on CMS login page? Valid sending email address is required',
            )}
            value={formValues.PASSWORD_REMINDER_ENABLED ?? ''}
            options={[
              { value: 'Off', label: t('Off') },
              { value: 'On except Admin', label: t('On except Admin') },
              { value: 'On', label: t('On') },
            ]}
            onSelect={(v) => updateField('PASSWORD_REMINDER_ENABLED', v)}
          />
        )}
        {isVisible('TWOFACTOR_ISSUER') && (
          <TextInput
            name="TWOFACTOR_ISSUER"
            label={t('Issuer name')}
            helpText={t(
              'Name that should appear as Issuer when two factor authorisation is enabled.',
            )}
            value={formValues.TWOFACTOR_ISSUER ?? ''}
            onChange={(v) => updateField('TWOFACTOR_ISSUER', v)}
            disabled={!isEditable('TWOFACTOR_ISSUER')}
          />
        )}
      </SettingsSection>
    </div>
  );
}
