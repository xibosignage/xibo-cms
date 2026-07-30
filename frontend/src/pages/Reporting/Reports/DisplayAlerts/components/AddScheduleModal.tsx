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

import type { DisplayAlertsFilter } from '../DisplayAlertsConfig';

import MultiSelectDropdown from '@/components/ui/forms/MultiSelectDropdown';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import ReportScheduleModalShell from '@/pages/Reporting/Reports/shared/ReportScheduleModalShell';
import { useDisplayGroupOptions } from '@/pages/Reporting/Reports/shared/hooks/useDisplayGroupOptions';
import { useDisplayOptions } from '@/pages/Reporting/Reports/shared/hooks/useDisplayOptions';

interface AddScheduleModalProps {
  isOpen: boolean;
  currentFilter: DisplayAlertsFilter;
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

  const [displayId, setDisplayId] = useState<number | null>(currentFilter.displayId);
  const [displayGroupId, setDisplayGroupId] = useState<number[]>(currentFilter.displayGroupId);

  useEffect(() => {
    if (isOpen) {
      setDisplayId(currentFilter.displayId);
      setDisplayGroupId(currentFilter.displayGroupId);
    }
  }, [isOpen]);

  const displaySelect = useDisplayOptions(isOpen);
  const displayGroupOpts = useDisplayGroupOptions(isOpen);

  const displaySelected = displayId !== null;

  return (
    <ReportScheduleModalShell
      isOpen={isOpen}
      title={t('Schedule Display Alerts Report')}
      onClose={onClose}
      onSuccess={onSuccess}
      buildPayload={(draft) => ({
        name: draft.name,
        reportName: 'displayalerts',
        filter: draft.filter,
        groupByFilter: '',
        displayGroupIds: [],
        displayId,
        displayGroupId: displaySelected ? [] : displayGroupId,
        fromDt: draft.fromDt || undefined,
        toDt: draft.toDt || undefined,
        sendEmail: draft.sendEmail,
        nonusers: draft.nonusers,
        hiddenFields: {
          eventType: currentFilter.eventType,
          onlyLoggedIn: currentFilter.onlyLoggedIn,
        },
      })}
    >
      <div className="flex flex-col gap-1">
        <label className="text-sm font-semibold text-gray-500 leading-5">{t('Display')}</label>
        <SelectDropdown
          value={displayId ? String(displayId) : ''}
          placeholder={t('All Displays')}
          searchable
          clearable
          resolveLabel={displaySelect.resolveLabel}
          options={displaySelect.options}
          isLoading={displaySelect.isLoading}
          isLoadingMore={displaySelect.isLoadingMore}
          hasMore={displaySelect.hasMore}
          onSearch={displaySelect.onSearch}
          onLoadMore={displaySelect.onLoadMore}
          onSelect={(val) => setDisplayId(val ? Number(val) : null)}
        />
      </div>

      {!displaySelected && (
        <MultiSelectDropdown
          label={t('Display Group')}
          value={displayGroupId.map(String)}
          options={displayGroupOpts}
          placeholder={t('All Display Groups')}
          showTags
          onChange={(vals) => setDisplayGroupId(vals.map(Number))}
        />
      )}
    </ReportScheduleModalShell>
  );
}
