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

import { isAxiosError } from 'axios';
import { useEffect, useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import LibraryQuotaInput from '../../Users/components/LibraryQuotaInput';
import SwitchRow from '../../Users/components/SwitchRow';

import Checkbox from '@/components/ui/forms/Checkbox';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { useUserContext } from '@/context/UserContext';
import { getUserGroupSchema } from '@/schema/userGroup';
import { createUserGroup, updateUserGroup } from '@/services/userGroupApi';
import { UserType } from '@/types/user';
import type { UserGroup } from '@/types/userGroup';

interface AddEditUserGroupModalProps {
  isOpen?: boolean;
  mode: 'add' | 'edit';
  userGroup?: UserGroup | null;
  onClose: () => void;
  onSuccess: () => void;
}

interface UserGroupDraft {
  group: string;
  description: string;
  libraryQuota: number;
  isSystemNotification: number;
  isDisplayNotification: number;
  isDataSetNotification: number;
  isCustomNotification: number;
  isLayoutNotification: number;
  isLibraryNotification: number;
  isReportNotification: number;
  isScheduleNotification: number;
  isShownForAddUser: number;
  defaultHomepageId: string;
}

type Tab = 'general' | 'notifications' | 'options';

function tabClass(activeTab: Tab, tab: Tab): string {
  const isActive = activeTab === tab;
  return `py-2 px-3 inline-flex items-center gap-2 border-b-2 text-sm font-semibold whitespace-nowrap focus:outline-none transition-all ${
    isActive ? 'border-blue-600 text-blue-500' : 'border-gray-200 text-gray-500 hover:text-blue-600'
  }`;
}

const DEFAULT_DRAFT: UserGroupDraft = {
  group: '',
  description: '',
  libraryQuota: 0,
  isSystemNotification: 0,
  isDisplayNotification: 0,
  isDataSetNotification: 0,
  isCustomNotification: 0,
  isLayoutNotification: 0,
  isLibraryNotification: 0,
  isReportNotification: 0,
  isScheduleNotification: 0,
  isShownForAddUser: 0,
  defaultHomepageId: '',
};

const HOMEPAGE_OPTIONS = [
  { label: 'Icon Dashboard', value: 'icondashboard.view' },
  { label: 'Status Dashboard', value: 'statusdashboard.view' },
  { label: 'Media Manager Dashboard', value: 'mediamanager.view' },
  { label: 'Playlist Dashboard', value: 'playlistdashboard.view' },
];

export default function AddEditUserGroupModal({
  isOpen = true,
  mode,
  userGroup,
  onClose,
  onSuccess,
}: AddEditUserGroupModalProps) {
  const { t } = useTranslation();
  const { user: currentUser } = useUserContext();
  const [isPending, startTransition] = useTransition();

  const isEdit = mode === 'edit';
  const isSuperAdmin = currentUser?.userTypeId === UserType.SuperAdmin;

  const [activeTab, setActiveTab] = useState<Tab>('general');
  const [draft, setDraft] = useState<UserGroupDraft>(DEFAULT_DRAFT);
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [apiError, setApiError] = useState<string | undefined>();

  const homepageOptions = HOMEPAGE_OPTIONS.map((hp) => ({
    ...hp,
    label: t(hp.label),
  }));

  // Populate draft in edit mode
  useEffect(() => {
    if (!isOpen) {
      setDraft(DEFAULT_DRAFT);
      setFormErrors({});
      setApiError(undefined);
      setActiveTab('general');
      return;
    }

    if (isEdit && userGroup) {
      setDraft({
        group: userGroup.group ?? '',
        description: userGroup.description ?? '',
        libraryQuota: userGroup.libraryQuota ?? 0,
        isSystemNotification: userGroup.isSystemNotification ?? 0,
        isDisplayNotification: userGroup.isDisplayNotification ?? 0,
        isDataSetNotification: userGroup.isDataSetNotification ?? 0,
        isCustomNotification: userGroup.isCustomNotification ?? 0,
        isLayoutNotification: userGroup.isLayoutNotification ?? 0,
        isLibraryNotification: userGroup.isLibraryNotification ?? 0,
        isReportNotification: userGroup.isReportNotification ?? 0,
        isScheduleNotification: userGroup.isScheduleNotification ?? 0,
        isShownForAddUser: userGroup.isShownForAddUser ?? 0,
        defaultHomepageId: userGroup.defaultHomepageId ?? '',
      });
    }
  }, [isOpen, isEdit, userGroup]);

  const handleSave = () => {
    setFormErrors({});
    setApiError(undefined);

    const schema = getUserGroupSchema(t);
    const result = schema.safeParse({
      group: draft.group,
      description: draft.description || undefined,
      libraryQuota: draft.libraryQuota,
    });

    if (!result.success) {
      const fieldErrors = result.error.flatten().fieldErrors;
      const mapped: Record<string, string> = {};
      Object.entries(fieldErrors).forEach(([key, value]) => {
        if (value?.[0]) mapped[key] = value[0];
      });
      setFormErrors(mapped);
      if (mapped.group || mapped.libraryQuota) {
        setActiveTab('general');
      }
      return;
    }

    startTransition(async () => {
      try {
        const payload = {
          group: draft.group,
          description: draft.description || undefined,
          libraryQuota: draft.libraryQuota,
          ...(isSuperAdmin
            ? {
                isSystemNotification: draft.isSystemNotification,
                isDisplayNotification: draft.isDisplayNotification,
                isDataSetNotification: draft.isDataSetNotification,
                isCustomNotification: draft.isCustomNotification,
                isLayoutNotification: draft.isLayoutNotification,
                isLibraryNotification: draft.isLibraryNotification,
                isReportNotification: draft.isReportNotification,
                isScheduleNotification: draft.isScheduleNotification,
                isShownForAddUser: draft.isShownForAddUser,
                defaultHomepageId: draft.defaultHomepageId || undefined,
              }
            : {}),
        };

        if (isEdit && userGroup) {
          await updateUserGroup(userGroup.groupId, payload);
        } else {
          await createUserGroup(payload);
        }

        onSuccess();
        onClose();
      } catch (err: unknown) {
        if (isAxiosError(err) && err.response?.data?.message) {
          setApiError(err.response.data.message);
        } else if (err instanceof Error) {
          setApiError(err.message);
        } else {
          setApiError(t('An unexpected error occurred.'));
        }
      }
    });
  };

  if (!isOpen) return null;

  return (
    <Modal
      variant="tabbed"
      isOpen={isOpen}
      onClose={onClose}
      title={isEdit ? t('Edit User Group') : t('Add User Group')}
      size="lg"
      scrollable={false}
      isPending={isPending}
      error={apiError}
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary' as const,
          disabled: isPending,
        },
        {
          label: isPending
            ? isEdit
              ? t('Saving...')
              : t('Creating...')
            : isEdit
              ? t('Save')
              : t('Create Group'),
          onClick: handleSave,
          disabled: isPending,
        },
      ]}
    >
      <div className="flex flex-col flex-1 min-h-0 overflow-hidden px-4">
        <div
          role="tablist"
          aria-label={t('User group settings tabs')}
          className="flex px-4 overflow-x-auto shrink-0"
        >
          <button
            role="tab"
            type="button"
            aria-selected={activeTab === 'general'}
            aria-controls="tabpanel-usergroup"
            className={tabClass(activeTab, 'general')}
            onClick={() => setActiveTab('general')}
          >
            {t('General')}
          </button>
          {isSuperAdmin && (
            <button
              role="tab"
              type="button"
              aria-selected={activeTab === 'notifications'}
              aria-controls="tabpanel-usergroup"
              className={tabClass(activeTab, 'notifications')}
              onClick={() => setActiveTab('notifications')}
            >
              {t('Notifications')}
            </button>
          )}
        </div>

        <div
          id="tabpanel-usergroup"
          role="tabpanel"
          className="flex flex-col gap-4 flex-1 min-h-0 p-4 overflow-y-auto"
        >
          {activeTab === 'general' && (
            <>
              <div className="flex flex-col gap-1">
                <span className="text-sm font-semibold text-gray-800">{t('Group Details')}</span>
                <p className="text-xs text-gray-500">
                  {t('Configure group identity, storage limits, and default navigation rules.')}
                </p>
              </div>
              <TextInput
                name="group"
                label={t('Name')}
                placeholder={t('Enter group name')}
                value={draft.group}
                onChange={(val) => setDraft((prev) => ({ ...prev, group: val }))}
                error={formErrors.group}
              />

              <TextInput
                name="description"
                label={t('Description')}
                placeholder={t('Enter description')}
                value={draft.description}
                onChange={(val) => {
                  setDraft((prev) => ({ ...prev, description: val }));
                  if (formErrors.description) {
                    setFormErrors((prev) =>
                      Object.fromEntries(
                        Object.entries(prev).filter(([key]) => key !== 'description'),
                      ),
                    );
                  }
                }}
                helpText={t('Short description of the user group. Up to 500 characters.')}
                error={formErrors.description}
                optional
                multiline
                rows={5}
              />

              <LibraryQuotaInput
                value={draft.libraryQuota}
                onChange={(kb) => setDraft((prev) => ({ ...prev, libraryQuota: kb }))}
                error={formErrors.libraryQuota}
              />
              <Checkbox
                id="shownForAddUser"
                title={t('Show when onboarding a new user?')}
                label={t(
                  'Should this User Group be available for selection when creating a New User via the onboarding form?',
                )}
                checked={draft.isShownForAddUser === 1}
                onChange={() =>
                  setDraft((prev) => ({
                    ...prev,
                    isShownForAddUser: prev.isShownForAddUser === 1 ? 0 : 1,
                  }))
                }
              />
              <SelectDropdown
                label={t('Default Homepage')}
                value={draft.defaultHomepageId}
                placeholder={t('Select a homepage')}
                options={homepageOptions}
                onSelect={(value) => setDraft((prev) => ({ ...prev, defaultHomepageId: value }))}
                clearable
              />
            </>
          )}

          {activeTab === 'notifications' && isSuperAdmin && (
            <div className="flex flex-col gap-3">
              <p className="text-sm text-gray-500">
                {t('Select which notification types members of this group should receive.')}
              </p>

              <SwitchRow
                title={t('System Notifications')}
                description={t('Receive system notifications')}
                checked={draft.isSystemNotification === 1}
                onChange={(val) =>
                  setDraft((prev) => ({ ...prev, isSystemNotification: val ? 1 : 0 }))
                }
              />

              <SwitchRow
                title={t('Display Notifications')}
                description={t('Receive display notifications')}
                checked={draft.isDisplayNotification === 1}
                onChange={(val) =>
                  setDraft((prev) => ({ ...prev, isDisplayNotification: val ? 1 : 0 }))
                }
              />

              <SwitchRow
                title={t('DataSet Notifications')}
                description={t('Receive dataset notifications')}
                checked={draft.isDataSetNotification === 1}
                onChange={(val) =>
                  setDraft((prev) => ({ ...prev, isDataSetNotification: val ? 1 : 0 }))
                }
              />

              <SwitchRow
                title={t('Layout Notifications')}
                description={t('Receive layout notifications')}
                checked={draft.isLayoutNotification === 1}
                onChange={(val) =>
                  setDraft((prev) => ({ ...prev, isLayoutNotification: val ? 1 : 0 }))
                }
              />

              <SwitchRow
                title={t('Library Notifications')}
                description={t('Receive library notifications')}
                checked={draft.isLibraryNotification === 1}
                onChange={(val) =>
                  setDraft((prev) => ({ ...prev, isLibraryNotification: val ? 1 : 0 }))
                }
              />

              <SwitchRow
                title={t('Report Notifications')}
                description={t('Receive report notifications')}
                checked={draft.isReportNotification === 1}
                onChange={(val) =>
                  setDraft((prev) => ({ ...prev, isReportNotification: val ? 1 : 0 }))
                }
              />

              <SwitchRow
                title={t('Schedule Notifications')}
                description={t('Receive schedule notifications')}
                checked={draft.isScheduleNotification === 1}
                onChange={(val) =>
                  setDraft((prev) => ({ ...prev, isScheduleNotification: val ? 1 : 0 }))
                }
              />

              <SwitchRow
                title={t('Custom Notifications')}
                description={t('Receive custom notifications')}
                checked={draft.isCustomNotification === 1}
                onChange={(val) =>
                  setDraft((prev) => ({ ...prev, isCustomNotification: val ? 1 : 0 }))
                }
              />
            </div>
          )}
        </div>
      </div>
    </Modal>
  );
}
