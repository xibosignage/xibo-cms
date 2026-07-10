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
import { Loader2 } from 'lucide-react';
import { useEffect, useRef, useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import type { UserDraft, UserFormErrors } from '../config/addEditUserTypes';

import FolderPermissionTab from './tabs/FolderPermissionTab';
import GeneralTab from './tabs/GeneralTab';
import NotificationsTab from './tabs/NotificationsTab';
import OptionsTab from './tabs/OptionsTab';
import ReferencesTab from './tabs/ReferencesTab';

import type { PermissionLevel } from '@/components/ui/FolderPermissionTree';
import type { SelectOption } from '@/components/ui/forms/SelectDropdown';
import Modal from '@/components/ui/modals/Modal';
import { useUserContext } from '@/context/UserContext';
import { useDebounce } from '@/hooks/useDebounce';
import { usePermissions } from '@/hooks/usePermissions';
import { getAddUserSchema, getEditUserSchema } from '@/schema/user';
import { fetchFolderTree } from '@/services/folderApi';
import { fetchGroupFolderPermissions, saveMultiPermissions } from '@/services/permissionsApi';
import { createUser, updateUser } from '@/services/userApi';
import { fetchUserGroups } from '@/services/userGroupApi';
import type { Folder } from '@/types/folder';
import type { User } from '@/types/user';
import { UserType } from '@/types/user';

const GROUP_PAGE_SIZE = 10;

type Tab = 'general' | 'folderPermission' | 'references' | 'notifications' | 'options';

function tabClass(activeTab: Tab, tab: Tab): string {
  const isActive = activeTab === tab;
  return `py-2 px-3 inline-flex items-center gap-2 border-b-2 text-sm font-semibold whitespace-nowrap focus:outline-none transition-all ${
    isActive ? 'border-blue-600 text-blue-500' : 'border-gray-200 text-gray-500 hover:text-blue-600'
  }`;
}

const DEFAULT_DRAFT: UserDraft = {
  userName: '',
  email: '',
  userTypeId: UserType.User,
  groupId: null,
  homePageId: '',
  homeFolderId: 1,
  password: '',
  retypePassword: '',
  libraryQuota: 0,
  firstName: '',
  lastName: '',
  phone: '',
  ref1: '',
  ref2: '',
  ref3: '',
  ref4: '',
  ref5: '',
  retired: 0,
  isPasswordChangeRequired: 0,
  hideNavigation: 0,
  newUserWizard: 0,
  disableTwoFactor: 0,
  isSystemNotification: 0,
  isDisplayNotification: 0,
  isDataSetNotification: 0,
  isCustomNotification: 0,
  isLayoutNotification: 0,
  isLibraryNotification: 0,
  isReportNotification: 0,
  isScheduleNotification: 0,
};

function permissionLevelFromApi(perm: {
  view: number;
  edit: number;
  delete: number;
}): PermissionLevel | null {
  if (perm.view === 1 && perm.edit === 1 && perm.delete === 1) return 'fullAccess';
  if (perm.view === 1 && perm.edit === 1 && perm.delete === 0) return 'readWrite';
  if (perm.view === 1 && perm.edit === 0 && perm.delete === 0) return 'viewOnly';
  return null;
}

function permissionLevelToApi(level: PermissionLevel) {
  switch (level) {
    case 'viewOnly':
      return { view: 1, edit: 0, delete: 0 };
    case 'readWrite':
      return { view: 1, edit: 1, delete: 0 };
    case 'fullAccess':
      return { view: 1, edit: 1, delete: 1 };
  }
}

interface AddEditUserModalProps {
  isOpen?: boolean;
  mode: 'add' | 'edit';
  user?: User | null;
  onClose: () => void;
  onSuccess: () => void;
}

export default function AddEditUserModal({
  isOpen = true,
  mode,
  user,
  onClose,
  onSuccess,
}: AddEditUserModalProps) {
  const { t } = useTranslation();
  const { user: currentUser } = useUserContext();
  const [isPending, startTransition] = useTransition();
  const { canViewFolders } = usePermissions();

  const isEdit = mode === 'edit';
  const isSuperAdmin = currentUser?.userTypeId === UserType.SuperAdmin;

  const [activeTab, setActiveTab] = useState<Tab>('general');
  const [draft, setDraft] = useState<UserDraft>(DEFAULT_DRAFT);
  const [formErrors, setFormErrors] = useState<UserFormErrors>({});
  const [apiError, setApiError] = useState<string | undefined>();
  const [isLoading, setIsLoading] = useState(false);
  const [showPassword, setShowPassword] = useState(false);

  // Dynamic options — paginated user group loading
  const [groupData, setGroupData] = useState<
    { groupId: number; name: string; features: string[] }[]
  >([]);
  const [groupOptions, setGroupOptions] = useState<SelectOption[]>([]);
  const [groupHasMore, setGroupHasMore] = useState(false);
  const [groupSearch, setGroupSearch] = useState('');
  const debouncedGroupSearch = useDebounce(groupSearch, 300);
  const [isLoadingMoreGroups, setIsLoadingMoreGroups] = useState(false);
  const groupPageRef = useRef(0);

  // Folder permissions
  const [folderTreeData, setFolderTreeData] = useState<Folder[]>([]);
  const [folderPermissions, setFolderPermissions] = useState<Map<number, PermissionLevel>>(
    new Map(),
  );
  const [initialFolderPermissions, setInitialFolderPermissions] = useState<
    Map<number, PermissionLevel>
  >(new Map());
  const [isLoadingFolders, setIsLoadingFolders] = useState(false);
  const [folderSearch, setFolderSearch] = useState('');

  // Available tabs based on context
  const tabs: { key: Tab; label: string }[] = [{ key: 'general', label: t('General') }];
  if (canViewFolders) {
    tabs.push({ key: 'folderPermission', label: t('Folder Permission') });
  }
  tabs.push({ key: 'references', label: t('References') });
  if (isSuperAdmin) {
    tabs.push({ key: 'notifications', label: t('Notifications') });
  }
  tabs.push({ key: 'options', label: t('Options') });

  // Homepage options — filtered by user type / group features
  const allHomepages = [
    { label: t('Icon Dashboard'), value: 'icondashboard.view', feature: '' },
    { label: t('Status Dashboard'), value: 'statusdashboard.view', feature: 'dashboard.status' },
    {
      label: t('Media Manager Dashboard'),
      value: 'mediamanager.view',
      feature: 'dashboard.media.manager',
    },
    {
      label: t('Playlist Dashboard'),
      value: 'playlistdashboard.view',
      feature: 'dashboard.playlist',
    },
  ];

  const homepageOptions: SelectOption[] = (() => {
    if (draft.userTypeId === UserType.SuperAdmin) {
      return allHomepages.map(({ label, value }) => ({ label, value }));
    }
    if (isEdit && user?.features) {
      return allHomepages
        .filter((hp) => !hp.feature || user.features![hp.feature])
        .map(({ label, value }) => ({ label, value }));
    }
    const selectedGroup = groupData.find((g) => g.groupId === draft.groupId);
    if (selectedGroup) {
      return allHomepages
        .filter((hp) => !hp.feature || selectedGroup.features.includes(hp.feature))
        .map(({ label, value }) => ({ label, value }));
    }
    return allHomepages.filter((hp) => !hp.feature).map(({ label, value }) => ({ label, value }));
  })();

  // Load user groups with pagination
  useEffect(() => {
    if (!isOpen) return;
    const controller = new AbortController();
    groupPageRef.current = 0;
    setGroupOptions([]);
    setGroupData([]);
    fetchUserGroups({
      start: 0,
      length: GROUP_PAGE_SIZE,
      isUser: 0,
      isShownForAddUser: 1,
      userGroup: debouncedGroupSearch || undefined,
      signal: controller.signal,
    })
      .then((res) => {
        if (controller.signal.aborted) return;
        const filtered = res.rows.filter((g) => g.isUserSpecific !== 1);
        setGroupData(
          filtered.map((g) => ({
            groupId: g.groupId,
            name: g.group,
            features: g.features ?? [],
          })),
        );
        setGroupOptions(
          filtered.map((g) => ({
            label: g.group,
            value: String(g.groupId),
          })),
        );
        setGroupHasMore(res.rows.length === GROUP_PAGE_SIZE);
        groupPageRef.current = 1;
      })
      .catch(() => {});
    return () => controller.abort();
  }, [isOpen, debouncedGroupSearch]);

  const loadMoreGroups = () => {
    if (isLoadingMoreGroups || !groupHasMore) return;
    setIsLoadingMoreGroups(true);
    fetchUserGroups({
      start: groupPageRef.current * GROUP_PAGE_SIZE,
      length: GROUP_PAGE_SIZE,
      isUser: 0,
      isShownForAddUser: 1,
      userGroup: debouncedGroupSearch || undefined,
    })
      .then((res) => {
        const filtered = res.rows.filter((g) => g.isUserSpecific !== 1);
        setGroupData((prev) => [
          ...prev,
          ...filtered.map((g) => ({
            groupId: g.groupId,
            name: g.group,
            features: g.features ?? [],
          })),
        ]);
        setGroupOptions((prev) => [
          ...prev,
          ...filtered.map((g) => ({
            label: g.group,
            value: String(g.groupId),
          })),
        ]);
        setGroupHasMore(res.rows.length === GROUP_PAGE_SIZE);
        groupPageRef.current += 1;
      })
      .catch(() => {})
      .finally(() => setIsLoadingMoreGroups(false));
  };

  // Load data for edit mode
  useEffect(() => {
    if (!isOpen || !isEdit || !user) return;
    setIsLoading(true);
    setDraft({
      userName: user.userName ?? '',
      email: user.email ?? '',
      userTypeId: user.userTypeId ?? UserType.User,
      groupId: user.groupId ?? null,
      homePageId: user.homePageId ?? '',
      homeFolderId: user.homeFolderId ?? 1,
      password: '',
      retypePassword: '',
      libraryQuota: user.libraryQuota ?? 0,
      firstName: user.firstName ?? '',
      lastName: user.lastName ?? '',
      phone: user.phone ?? '',
      ref1: user.ref1 ?? '',
      ref2: user.ref2 ?? '',
      ref3: user.ref3 ?? '',
      ref4: user.ref4 ?? '',
      ref5: user.ref5 ?? '',
      retired: user.retired ?? 0,
      isPasswordChangeRequired: user.isPasswordChangeRequired ?? 0,
      hideNavigation: 0,
      newUserWizard: user.newUserWizard ?? 0,
      disableTwoFactor: 0,
      isSystemNotification: user.isSystemNotification ?? 0,
      isDisplayNotification: user.isDisplayNotification ?? 0,
      isDataSetNotification: user.isDataSetNotification ?? 0,
      isCustomNotification: user.isCustomNotification ?? 0,
      isLayoutNotification: user.isLayoutNotification ?? 0,
      isLibraryNotification: user.isLibraryNotification ?? 0,
      isReportNotification: user.isReportNotification ?? 0,
      isScheduleNotification: user.isScheduleNotification ?? 0,
    });
    setIsLoading(false);
  }, [isOpen, isEdit, user]);

  // Load folder tree + permissions
  useEffect(() => {
    if (!isOpen || !canViewFolders) return;
    const controller = new AbortController();
    setIsLoadingFolders(true);
    fetchFolderTree(controller.signal)
      .then(async (tree) => {
        if (controller.signal.aborted) return;
        setFolderTreeData(tree);
        if (isEdit && user?.groupId) {
          const allIds = flattenFolderIds(tree);
          if (allIds.length > 0) {
            const perms = await fetchGroupFolderPermissions(allIds, user.groupId);
            if (controller.signal.aborted) return;
            const mapped = new Map<number, PermissionLevel>();
            perms.forEach((perm, folderId) => {
              const level = permissionLevelFromApi(perm);
              if (level) mapped.set(folderId, level);
            });
            setFolderPermissions(mapped);
            setInitialFolderPermissions(new Map(mapped));
          }
        }
      })
      .finally(() => {
        if (!controller.signal.aborted) setIsLoadingFolders(false);
      });
    return () => controller.abort();
  }, [isOpen, canViewFolders, isEdit, user?.groupId]);

  // Reset on close
  useEffect(() => {
    if (!isOpen) {
      setDraft(DEFAULT_DRAFT);
      setFormErrors({});
      setApiError(undefined);
      setActiveTab('general');
      setFolderPermissions(new Map());
      setInitialFolderPermissions(new Map());
      setFolderTreeData([]);
      setShowPassword(false);
      setFolderSearch('');
      setGroupSearch('');
    }
  }, [isOpen]);

  const handleSave = () => {
    setFormErrors({});
    setApiError(undefined);

    startTransition(async () => {
      const schema = isEdit ? getEditUserSchema(t) : getAddUserSchema(t);
      const parseData = isEdit
        ? {
            userName: draft.userName,
            email: draft.email || undefined,
            userTypeId: draft.userTypeId,
            homePageId: draft.homePageId || undefined,
            libraryQuota: draft.libraryQuota || undefined,
            firstName: draft.firstName || undefined,
            lastName: draft.lastName || undefined,
            phone: draft.phone || undefined,
            ref1: draft.ref1 || undefined,
            ref2: draft.ref2 || undefined,
            ref3: draft.ref3 || undefined,
            ref4: draft.ref4 || undefined,
            ref5: draft.ref5 || undefined,
            retired: draft.retired,
            isPasswordChangeRequired: draft.isPasswordChangeRequired,
            newUserWizard: draft.newUserWizard,
            hideNavigation: draft.hideNavigation,
            newPassword: draft.password || undefined,
            retypeNewPassword: draft.retypePassword || undefined,
          }
        : {
            userName: draft.userName,
            password: draft.password,
            email: draft.email || undefined,
            userTypeId: draft.userTypeId,
            groupId: draft.groupId ?? undefined,
            homePageId: draft.homePageId,
            libraryQuota: draft.libraryQuota || undefined,
            firstName: draft.firstName || undefined,
            lastName: draft.lastName || undefined,
            phone: draft.phone || undefined,
            ref1: draft.ref1 || undefined,
            ref2: draft.ref2 || undefined,
            ref3: draft.ref3 || undefined,
            ref4: draft.ref4 || undefined,
            ref5: draft.ref5 || undefined,
            isPasswordChangeRequired: draft.isPasswordChangeRequired || undefined,
            newUserWizard: draft.newUserWizard || undefined,
            hideNavigation: draft.hideNavigation || undefined,
          };

      const result = schema.safeParse(parseData);

      if (!result.success) {
        const fieldErrors = result.error.flatten().fieldErrors;
        const mapped: UserFormErrors = {};
        Object.entries(fieldErrors).forEach(([key, value]) => {
          if (value?.[0]) mapped[key as keyof UserFormErrors] = value[0];
        });
        setFormErrors(mapped);
        if (
          mapped.userName ||
          mapped.email ||
          mapped.userTypeId ||
          mapped.groupId ||
          mapped.homePageId ||
          mapped.password ||
          mapped.libraryQuota
        ) {
          setActiveTab('general');
        } else if (mapped.firstName || mapped.lastName || mapped.phone) {
          setActiveTab('references');
        }
        return;
      }

      try {
        let savedUser: User;

        if (isEdit && user) {
          if (draft.password && draft.password !== draft.retypePassword) {
            setFormErrors({ retypePassword: t('Passwords do not match') });
            setActiveTab('general');
            return;
          }

          savedUser = await updateUser(user.userId, {
            userName: draft.userName,
            email: draft.email || undefined,
            userTypeId: isSuperAdmin ? draft.userTypeId : undefined,
            homePageId: draft.homePageId || undefined,
            homeFolderId: draft.homeFolderId,
            libraryQuota: draft.libraryQuota,
            retired: draft.retired,
            firstName: draft.firstName || undefined,
            lastName: draft.lastName || undefined,
            phone: draft.phone || undefined,
            ref1: draft.ref1 || undefined,
            ref2: draft.ref2 || undefined,
            ref3: draft.ref3 || undefined,
            ref4: draft.ref4 || undefined,
            ref5: draft.ref5 || undefined,
            newUserWizard: draft.newUserWizard,
            hideNavigation: draft.hideNavigation,
            isPasswordChangeRequired: draft.isPasswordChangeRequired,
            newPassword: draft.password || undefined,
            retypeNewPassword: draft.retypePassword || undefined,
            disableTwoFactor: draft.disableTwoFactor || undefined,
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
                }
              : {}),
          });
        } else {
          savedUser = await createUser({
            userName: draft.userName,
            password: draft.password,
            email: draft.email || undefined,
            userTypeId: draft.userTypeId,
            groupId: draft.groupId!,
            homePageId: draft.homePageId || undefined,
            libraryQuota: draft.libraryQuota || undefined,
            firstName: draft.firstName || undefined,
            lastName: draft.lastName || undefined,
            phone: draft.phone || undefined,
            ref1: draft.ref1 || undefined,
            ref2: draft.ref2 || undefined,
            ref3: draft.ref3 || undefined,
            ref4: draft.ref4 || undefined,
            ref5: draft.ref5 || undefined,
            isPasswordChangeRequired: draft.isPasswordChangeRequired || undefined,
            newUserWizard: draft.newUserWizard || undefined,
            hideNavigation: draft.hideNavigation || undefined,
          });
        }

        if (canViewFolders && savedUser.groupId) {
          await saveFolderPermissions(savedUser.groupId);
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

  const saveFolderPermissions = async (groupId: number) => {
    const byLevel = new Map<PermissionLevel, number[]>();
    folderPermissions.forEach((level, folderId) => {
      const existing = byLevel.get(level) ?? [];
      existing.push(folderId);
      byLevel.set(level, existing);
    });

    const removedIds: number[] = [];
    initialFolderPermissions.forEach((_level, folderId) => {
      if (!folderPermissions.has(folderId)) removedIds.push(folderId);
    });

    const calls: Promise<void>[] = [];
    byLevel.forEach((ids, level) => {
      if (ids.length > 0) {
        calls.push(
          saveMultiPermissions({
            entity: 'Folder',
            ids,
            groupIds: { [groupId]: permissionLevelToApi(level) },
          }),
        );
      }
    });

    if (removedIds.length > 0) {
      calls.push(
        saveMultiPermissions({
          entity: 'Folder',
          ids: removedIds,
          groupIds: { [groupId]: { view: 0, edit: 0, delete: 0 } },
        }),
      );
    }

    if (calls.length > 0) await Promise.all(calls);
  };

  if (!isOpen) return null;

  return (
    <Modal
      variant="tabbed"
      isOpen={isOpen}
      onClose={onClose}
      title={isEdit ? t('Edit User') : t('Add User')}
      size="lg"
      scrollable={false}
      isPending={isPending || isLoading}
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
              : t('Create User'),
          onClick: handleSave,
          disabled: isPending || isLoading,
        },
      ]}
    >
      <div className="flex flex-col flex-1 min-h-0 overflow-hidden px-4">
        <div
          role="tablist"
          aria-label={t('User settings tabs')}
          className="flex px-4 overflow-x-auto shrink-0"
        >
          {tabs.map(({ key, label }) => (
            <button
              key={key}
              role="tab"
              type="button"
              aria-selected={activeTab === key}
              aria-controls="tabpanel-user"
              className={tabClass(activeTab, key)}
              onClick={() => setActiveTab(key)}
            >
              {label}
            </button>
          ))}
        </div>

        <div
          id="tabpanel-user"
          role="tabpanel"
          className="flex flex-col gap-4 flex-1 min-h-0 p-4 overflow-y-auto"
        >
          {isLoading && (
            <div className="flex items-center justify-center py-8 text-gray-400 text-sm">
              <Loader2 className="w-5 h-5 animate-spin mr-2" />
              {t('Loading...')}
            </div>
          )}

          {!isLoading && activeTab === 'general' && (
            <GeneralTab
              draft={draft}
              setDraft={setDraft}
              formErrors={formErrors}
              isEdit={isEdit}
              isSuperAdmin={isSuperAdmin}
              showPassword={showPassword}
              setShowPassword={setShowPassword}
              groupOptions={groupOptions}
              groupHasMore={groupHasMore}
              groupIsLoadingMore={isLoadingMoreGroups}
              onGroupLoadMore={loadMoreGroups}
              onGroupSearch={setGroupSearch}
              homepageOptions={homepageOptions}
            />
          )}

          {!isLoading && activeTab === 'folderPermission' && canViewFolders && (
            <FolderPermissionTab
              draft={draft}
              setDraft={setDraft}
              folderTreeData={folderTreeData}
              folderPermissions={folderPermissions}
              setFolderPermissions={setFolderPermissions}
              isLoadingFolders={isLoadingFolders}
              folderSearch={folderSearch}
              setFolderSearch={setFolderSearch}
            />
          )}

          {!isLoading && activeTab === 'references' && (
            <ReferencesTab draft={draft} setDraft={setDraft} />
          )}

          {!isLoading && activeTab === 'notifications' && isSuperAdmin && (
            <NotificationsTab draft={draft} setDraft={setDraft} />
          )}

          {!isLoading && activeTab === 'options' && (
            <OptionsTab
              draft={draft}
              setDraft={setDraft}
              isEdit={isEdit}
              isSuperAdmin={isSuperAdmin}
            />
          )}
        </div>
      </div>
    </Modal>
  );
}

function flattenFolderIds(nodes: Folder[]): number[] {
  const ids: number[] = [];
  for (const node of nodes) {
    ids.push(node.id);
    if (Array.isArray(node.children) && node.children.length > 0) {
      ids.push(...flattenFolderIds(node.children));
    }
  }
  return ids;
}
