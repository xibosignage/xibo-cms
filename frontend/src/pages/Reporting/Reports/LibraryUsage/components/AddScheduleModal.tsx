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

import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { LibraryUsageFilter } from '../LibraryUsageConfig';
import { useUserGroupOptions } from '../hooks/useUserGroupOptions';

import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import ReportScheduleModalShell from '@/pages/Reporting/Reports/shared/ReportScheduleModalShell';

interface AddScheduleModalProps {
  isOpen: boolean;
  currentFilter: LibraryUsageFilter;
  onClose: () => void;
  onSuccess: () => void;
}

export default function AddScheduleModal({
  isOpen,
  currentFilter,
  onClose,
  onSuccess,
}: AddScheduleModalProps) {
  const { t } = useTranslation();
  const { users, groups } = useUserGroupOptions();

  const [userId, setUserId] = useState<number | null>(currentFilter.userId);
  const [groupId, setGroupId] = useState<number | null>(currentFilter.groupId);

  // Snapshot the current filter only when the modal opens; re-running on currentFilter changes
  // would clobber an in-progress edit, so it is intentionally excluded from the deps.
  useEffect(() => {
    if (isOpen) {
      setUserId(currentFilter.userId);
      setGroupId(currentFilter.groupId);
    }
  }, [isOpen]);

  return (
    <ReportScheduleModalShell
      isOpen={isOpen}
      title={t('Schedule Library Usage Report')}
      onClose={onClose}
      onSuccess={onSuccess}
      buildPayload={(draft) => ({
        name: draft.name,
        reportName: 'libraryusage',
        filter: draft.filter,
        groupByFilter: '',
        displayGroupIds: [],
        userId,
        groupId,
        fromDt: draft.fromDt || undefined,
        toDt: draft.toDt || undefined,
        sendEmail: draft.sendEmail,
        nonusers: draft.nonusers,
      })}
    >
      <div className="flex flex-col gap-1">
        <label className="text-sm font-semibold text-gray-500 leading-5">{t('User')}</label>
        <SelectDropdown
          value={userId ? String(userId) : ''}
          placeholder={t('All Users')}
          options={users.options}
          searchable
          clearable
          translateLabels={false}
          isLoading={users.isLoading}
          onSearch={users.onSearch}
          onLoadMore={users.onLoadMore}
          hasMore={users.hasMore}
          isLoadingMore={users.isLoadingMore}
          resolveLabel={users.resolveLabel}
          onSelect={(val) => setUserId(val ? Number(val) : null)}
        />
      </div>

      <div className="flex flex-col gap-1">
        <label className="text-sm font-semibold text-gray-500 leading-5">{t('User Group')}</label>
        <SelectDropdown
          value={groupId ? String(groupId) : ''}
          placeholder={t('All User Groups')}
          options={groups.options}
          searchable
          clearable
          translateLabels={false}
          isLoading={groups.isLoading}
          onSearch={groups.onSearch}
          onLoadMore={groups.onLoadMore}
          hasMore={groups.hasMore}
          isLoadingMore={groups.isLoadingMore}
          resolveLabel={groups.resolveLabel}
          onSelect={(val) => setGroupId(val ? Number(val) : null)}
        />
      </div>
    </ReportScheduleModalShell>
  );
}
