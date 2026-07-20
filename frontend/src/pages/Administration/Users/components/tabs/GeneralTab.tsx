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

import { Eye, EyeOff } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import type { GeneralTabProps } from '../../config/addEditUserTypes';
import LibraryQuotaInput from '../LibraryQuotaInput';

import Checkbox from '@/components/ui/forms/Checkbox';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import TextInput from '@/components/ui/forms/TextInput';
import { UserType } from '@/types/user';

export default function GeneralTab({
  draft,
  setDraft,
  formErrors,
  isEdit,
  isSuperAdmin,
  showPassword,
  setShowPassword,
  groupOptions,
  groupHasMore,
  groupIsLoadingMore,
  onGroupLoadMore,
  onGroupSearch,
  homepageOptions,
}: GeneralTabProps) {
  const { t } = useTranslation();

  return (
    <>
      <div className="flex bg-slate-50 gap-y-3 flex-col px-3 py-2">
        <h4 className="text-base font-semibold text-gray-800">{t('Settings')}</h4>
        <div className={twMerge('grid  gap-4', isEdit ? 'grid-cols-1' : 'grid-cols-2')}>
          {isSuperAdmin && (
            <SelectDropdown
              label={t('User Type')}
              value={String(draft.userTypeId)}
              options={[
                { label: t('Super Admin'), value: String(UserType.SuperAdmin) },
                { label: t('Group Admin'), value: String(UserType.GroupAdmin) },
                { label: t('User'), value: String(UserType.User) },
              ]}
              onSelect={(val) => setDraft((prev) => ({ ...prev, userTypeId: Number(val) }))}
              error={formErrors.userTypeId}
            />
          )}

          {!isEdit && (
            <SelectDropdown
              label={t('Initial User Group')}
              value={draft.groupId !== null ? String(draft.groupId) : ''}
              options={groupOptions}
              searchable
              searchPlaceholder={t('Search groups...')}
              onSearch={onGroupSearch}
              onLoadMore={onGroupLoadMore}
              hasMore={groupHasMore}
              isLoadingMore={groupIsLoadingMore}
              onSelect={(val) => setDraft((prev) => ({ ...prev, groupId: Number(val) }))}
              error={formErrors.groupId}
            />
          )}
        </div>

        <SelectDropdown
          label={t('Homepage')}
          value={draft.homePageId}
          options={homepageOptions}
          onSelect={(val) => setDraft((prev) => ({ ...prev, homePageId: val }))}
          error={formErrors.homePageId}
          optional={isEdit}
        />
        <LibraryQuotaInput
          value={draft.libraryQuota}
          onChange={(val) => setDraft((prev) => ({ ...prev, libraryQuota: val }))}
          error={formErrors.libraryQuota}
        />
      </div>
      <h4 className="text-base font-semibold text-gray-800">{t('Account Credentials')}</h4>
      <div className="grid grid-cols-2 gap-4">
        <TextInput
          name="userName"
          label={t('Username')}
          placeholder={t('Enter username')}
          value={draft.userName}
          onChange={(val) => setDraft((prev) => ({ ...prev, userName: val }))}
          error={formErrors.userName}
        />

        <TextInput
          name="email"
          label={t('Email')}
          placeholder="username@email.com"
          type="email"
          value={draft.email}
          onChange={(val) => setDraft((prev) => ({ ...prev, email: val }))}
          error={formErrors.email}
          optional
        />
      </div>

      {(isSuperAdmin || !isEdit) && (
        <TextInput
          name="password"
          label={isEdit ? t('New Password') : t('Password')}
          placeholder="••••••••"
          type={showPassword ? 'text' : 'password'}
          value={draft.password}
          onChange={(val) => setDraft((prev) => ({ ...prev, password: val }))}
          error={formErrors.password}
          optional={isEdit}
          suffix={
            <button
              type="button"
              onClick={() => setShowPassword((prev) => !prev)}
              aria-label={showPassword ? t('Hide password') : t('Show password')}
              className="px-3 text-gray-500 hover:text-gray-700 transition-colors"
            >
              {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
            </button>
          }
        />
      )}

      {isSuperAdmin && isEdit && (
        <TextInput
          name="retypePassword"
          label={t('Retype New Password')}
          placeholder="••••••••"
          type={showPassword ? 'text' : 'password'}
          value={draft.retypePassword}
          onChange={(val) => setDraft((prev) => ({ ...prev, retypePassword: val }))}
          error={formErrors.retypePassword}
          optional
          suffix={
            <button
              type="button"
              onClick={() => setShowPassword((prev) => !prev)}
              className="px-3 text-gray-500 hover:text-gray-700 transition-colors"
            >
              {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
            </button>
          }
        />
      )}

      <hr className="w-full border-b border-t-0 border-gray-200" />
      <h4 className="text-base font-semibold text-gray-800">{t('Personal Information')}</h4>
      <div className="grid grid-cols-2 gap-4">
        <TextInput
          name="firstName"
          label={t('First Name')}
          placeholder={t('Enter first name')}
          value={draft.firstName}
          onChange={(val) => setDraft((prev) => ({ ...prev, firstName: val }))}
          optional
        />

        <TextInput
          name="lastName"
          label={t('Last Name')}
          placeholder={t('Enter last name')}
          value={draft.lastName}
          onChange={(val) => setDraft((prev) => ({ ...prev, lastName: val }))}
          optional
        />
      </div>

      {isEdit && (
        <Checkbox
          id="retired"
          title={t('Retired')}
          label={t('Retire this user. They will no longer be able to log in.')}
          className="items-center px-3 py-2.5"
          checked={draft.retired === 1}
          onChange={() => setDraft((prev) => ({ ...prev, retired: prev.retired === 1 ? 0 : 1 }))}
        />
      )}
    </>
  );
}
