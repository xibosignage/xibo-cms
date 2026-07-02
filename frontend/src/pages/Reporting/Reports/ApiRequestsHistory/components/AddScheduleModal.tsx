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

import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { ApiRequestsHistoryFilter, ApiRequestsLogType } from '../ApiRequestsHistoryConfig';
import { TYPE_OPTIONS } from '../ApiRequestsHistoryConfig';

import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import ReportScheduleModalShell from '@/pages/Reporting/Reports/shared/ReportScheduleModalShell';
import { useUserOptions } from '@/pages/Reporting/Reports/shared/hooks/useUserOptions';

interface AddScheduleModalProps {
  isOpen: boolean;
  currentFilter: ApiRequestsHistoryFilter;
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

  const [userId, setUserId] = useState<number | null>(currentFilter.userId);
  const [type, setType] = useState<ApiRequestsLogType>(currentFilter.type);
  const userSelect = useUserOptions(isOpen);

  useEffect(() => {
    if (isOpen) {
      setUserId(currentFilter.userId);
      setType(currentFilter.type);
    }
  }, [isOpen]);

  return (
    <ReportScheduleModalShell
      isOpen={isOpen}
      title={t('Schedule API Request History Report')}
      onClose={onClose}
      onSuccess={onSuccess}
      buildPayload={(draft) => ({
        name: draft.name,
        reportName: 'apirequests',
        filter: draft.filter,
        groupByFilter: '',
        displayGroupIds: [],
        userId,
        fromDt: draft.fromDt || undefined,
        toDt: draft.toDt || undefined,
        sendEmail: draft.sendEmail,
        nonusers: draft.nonusers,
        hiddenFields: { type },
      })}
    >
      <div className="flex flex-col gap-1">
        <label className="text-sm font-semibold text-gray-500 leading-5">{t('Type')}</label>
        <SelectDropdown
          value={type}
          options={TYPE_OPTIONS.map((o) => ({ value: o.value, label: t(o.label) }))}
          onSelect={(val) => setType((val as ApiRequestsLogType) || currentFilter.type)}
        />
      </div>

      <div className="flex flex-col gap-1">
        <label className="text-sm font-semibold text-gray-500 leading-5">{t('User')}</label>
        <SelectDropdown
          value={userId ? String(userId) : ''}
          placeholder={t('All Users')}
          searchable
          clearable
          resolveLabel={userSelect.resolveLabel}
          options={userSelect.options}
          isLoading={userSelect.isLoading}
          isLoadingMore={userSelect.isLoadingMore}
          hasMore={userSelect.hasMore}
          onSearch={userSelect.onSearch}
          onLoadMore={userSelect.onLoadMore}
          onSelect={(val) => setUserId(val ? Number(val) : null)}
        />
      </div>
    </ReportScheduleModalShell>
  );
}
