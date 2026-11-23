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

import type { BandwidthFilter } from '../BandwidthConfig';
import { FREQUENCY_OPTIONS } from '../BandwidthConfig';

import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import ReportScheduleModalShell from '@/pages/Reporting/Reports/shared/ReportScheduleModalShell';
import { useDisplayOptions } from '@/pages/Reporting/Reports/shared/hooks/useDisplayOptions';

interface AddScheduleModalProps {
  isOpen: boolean;
  currentFilter: BandwidthFilter;
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

  useEffect(() => {
    if (isOpen) {
      setDisplayId(currentFilter.displayId);
    }
  }, [isOpen]);

  const displaySelect = useDisplayOptions(isOpen);

  return (
    <ReportScheduleModalShell
      isOpen={isOpen}
      title={t('Schedule Bandwidth Report')}
      onClose={onClose}
      onSuccess={onSuccess}
      frequencyOptions={FREQUENCY_OPTIONS}
      buildPayload={(draft) => ({
        name: draft.name,
        reportName: 'bandwidth',
        filter: draft.filter,
        groupByFilter: '',
        displayGroupIds: [],
        displayId,
        fromDt: draft.fromDt || undefined,
        toDt: draft.toDt || undefined,
        sendEmail: draft.sendEmail,
        nonusers: draft.nonusers,
      })}
    >
      <div className="flex flex-col gap-1">
        <label className="text-sm font-semibold text-gray-500 leading-5">{t('Display')}</label>
        <SelectDropdown
          value={displayId ? String(displayId) : ''}
          placeholder={t('All Displays')}
          options={displaySelect.options}
          searchable
          clearable
          translateLabels={false}
          isLoading={displaySelect.isLoading}
          onSearch={displaySelect.onSearch}
          onLoadMore={displaySelect.onLoadMore}
          hasMore={displaySelect.hasMore}
          isLoadingMore={displaySelect.isLoadingMore}
          resolveLabel={displaySelect.resolveLabel}
          onSelect={(val) => setDisplayId(val ? Number(val) : null)}
        />
      </div>
    </ReportScheduleModalShell>
  );
}
