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

import type { OptionsLoader } from '@/pages/Reporting/Reports/shared/hooks/usePaginatedOptions';
import { usePaginatedOptions } from '@/pages/Reporting/Reports/shared/hooks/usePaginatedOptions';
import { fetchUsers } from '@/services/userApi';
import { fetchUserGroups } from '@/services/userGroupApi';

const PAGE_SIZE = 10;

const userLoader: OptionsLoader = async (search, start, signal) => {
  const res = await fetchUsers({
    start,
    length: PAGE_SIZE,
    userName: search || undefined,
    signal,
  });
  return {
    options: res.rows.map((u) => ({ label: u.userName, value: String(u.userId) })),
    totalCount: res.totalCount,
  };
};

const groupLoader: OptionsLoader = async (search, start, signal) => {
  // isUser = 0 restricts to "real" user groups, not per-user specific groups.
  const res = await fetchUserGroups({
    start,
    length: PAGE_SIZE,
    isUser: 0,
    userGroup: search || undefined,
    signal,
  });
  return {
    options: res.rows.map((g) => ({ label: g.group, value: String(g.groupId) })),
    totalCount: res.totalCount,
  };
};

// Resolve a single selected id back to its label, so a restored/persisted filter shows the
// name rather than the bare id even when that option isn't on the current page.
const resolveUserLabel = async (value: string): Promise<string> => {
  const res = await fetchUsers({ start: 0, length: 1, userId: Number(value) });
  return res.rows[0]?.userName ?? value;
};

const resolveGroupLabel = async (value: string): Promise<string> => {
  const res = await fetchUserGroups({ start: 0, length: 1, userGroupId: Number(value) });
  return res.rows[0]?.group ?? value;
};

/** Paginated, searchable option lists for the User and User Group select dropdowns. */
export function useUserGroupOptions() {
  const users = usePaginatedOptions({ loader: userLoader });
  const groups = usePaginatedOptions({ loader: groupLoader });

  return {
    users: { ...users, resolveLabel: resolveUserLabel, translateLabels: false },
    groups: { ...groups, resolveLabel: resolveGroupLabel, translateLabels: false },
  };
}
