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

import type { TimeConnectedFilter } from '../TimeConnectedConfig';
import { GROUP_BY_OPTIONS } from '../TimeConnectedConfig';

import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import ReportScheduleModalShell from '@/pages/Reporting/Reports/shared/ReportScheduleModalShell';
import { DisplayGroupMultiSelect } from '@/pages/Schedule/Schedule/components/DisplayGroupMultiSelect';
import type { DisplayGroupMultiSelectValue } from '@/pages/Schedule/Schedule/components/DisplayGroupMultiSelect';

interface AddScheduleModalProps {
  isOpen: boolean;
  currentFilter: TimeConnectedFilter;
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

  const [groupByFilter, setGroupByFilter] = useState(currentFilter.groupByFilter);
  const [displayValue, setDisplayValue] = useState<DisplayGroupMultiSelectValue>({
    displaySpecificGroupIds: currentFilter.displaySpecificGroupIds,
    displayGroupIds: currentFilter.displayGroupIds,
  });

  useEffect(() => {
    if (isOpen) {
      setGroupByFilter(currentFilter.groupByFilter);
      setDisplayValue({
        displaySpecificGroupIds: currentFilter.displaySpecificGroupIds,
        displayGroupIds: currentFilter.displayGroupIds,
      });
    }
  }, [isOpen]);

  const displayGroupIds = [
    ...displayValue.displaySpecificGroupIds,
    ...displayValue.displayGroupIds,
  ];

  const handleDisplayChange = (value: DisplayGroupMultiSelectValue) => {
    setDisplayValue(value);
  };

  return (
    <ReportScheduleModalShell
      isOpen={isOpen}
      title={t('Schedule Time Connected Report')}
      onClose={onClose}
      onSuccess={onSuccess}
      buildPayload={(draft) => ({
        name: draft.name,
        reportName: 'timeconnected',
        filter: draft.filter,
        groupByFilter,
        displayGroupIds,
        fromDt: draft.fromDt || undefined,
        toDt: draft.toDt || undefined,
        sendEmail: draft.sendEmail,
        nonusers: draft.nonusers,
      })}
    >
      <div className="flex flex-col gap-1">
        <label className="text-sm font-semibold text-gray-500 leading-5">{t('Group By')}</label>
        <SelectDropdown
          value={groupByFilter}
          options={GROUP_BY_OPTIONS.map((o) => ({ value: o.value, label: t(o.label) }))}
          onSelect={(val) => setGroupByFilter(val as TimeConnectedFilter['groupByFilter'])}
        />
      </div>

      <div className="flex flex-col gap-1">
        <label className="text-sm font-semibold text-gray-500 leading-5">{t('Display')}</label>
        <DisplayGroupMultiSelect value={displayValue} onChange={handleDisplayChange} />
        <span className="text-xs text-gray-400">
          {t(
            'Please select one or more displays / groups for this notification to be shown on - Layouts will need the notification widget.',
          )}
        </span>
      </div>
    </ReportScheduleModalShell>
  );
}
