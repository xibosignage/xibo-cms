/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

import type { Dispatch, SetStateAction } from 'react';

import type { PermissionLevel } from '@/components/ui/FolderPermissionTree';
import type { SelectOption } from '@/components/ui/forms/SelectDropdown';
import type { Folder } from '@/types/folder';

export interface UserDraft {
  userName: string;
  email: string;
  userTypeId: number;
  groupId: number | null;
  homePageId: string;
  homeFolderId: number;
  password: string;
  retypePassword: string;
  libraryQuota: number;
  firstName: string;
  lastName: string;
  phone: string;
  ref1: string;
  ref2: string;
  ref3: string;
  ref4: string;
  ref5: string;
  retired: number;
  isPasswordChangeRequired: number;
  hideNavigation: number;
  newUserWizard: number;
  disableTwoFactor: number;
  isSystemNotification: number;
  isDisplayNotification: number;
  isDataSetNotification: number;
  isCustomNotification: number;
  isLayoutNotification: number;
  isLibraryNotification: number;
  isReportNotification: number;
  isScheduleNotification: number;
}

export type UserFormErrors = Partial<Record<keyof UserDraft, string>>;

export interface GeneralTabProps {
  draft: UserDraft;
  setDraft: Dispatch<SetStateAction<UserDraft>>;
  formErrors: UserFormErrors;
  isEdit: boolean;
  isSuperAdmin: boolean;
  showPassword: boolean;
  setShowPassword: Dispatch<SetStateAction<boolean>>;
  groupOptions: SelectOption[];
  groupHasMore: boolean;
  groupIsLoadingMore: boolean;
  onGroupLoadMore: () => void;
  onGroupSearch: (search: string) => void;
  homepageOptions: SelectOption[];
}

export interface FolderPermissionTabProps {
  draft: UserDraft;
  setDraft: Dispatch<SetStateAction<UserDraft>>;
  folderTreeData: Folder[];
  folderPermissions: Map<number, PermissionLevel>;
  setFolderPermissions: Dispatch<SetStateAction<Map<number, PermissionLevel>>>;
  isLoadingFolders: boolean;
  folderSearch: string;
  setFolderSearch: Dispatch<SetStateAction<string>>;
}

export interface ReferencesTabProps {
  draft: UserDraft;
  setDraft: Dispatch<SetStateAction<UserDraft>>;
}

export interface NotificationsTabProps {
  draft: UserDraft;
  setDraft: Dispatch<SetStateAction<UserDraft>>;
}

export interface OptionsTabProps {
  draft: UserDraft;
  setDraft: Dispatch<SetStateAction<UserDraft>>;
  isEdit: boolean;
  isSuperAdmin: boolean;
}
