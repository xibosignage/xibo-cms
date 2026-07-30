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

import ReportScheduleModalShell from './ReportScheduleModalShell';
import { useDisplayGroupSelect } from './hooks/useDisplayGroupSelect';
import { useDisplayOptions } from './hooks/useDisplayOptions';
import type { StatsFilter, StatsReportConfig } from './types';

import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import { fetchLayouts } from '@/services/layoutsApi';
import { fetchMedia } from '@/services/mediaApi';

interface StatsReportScheduleModalProps {
  config: StatsReportConfig;
  isOpen: boolean;
  currentFilter: StatsFilter;
  onClose: () => void;
  onSuccess: () => void;
}

export default function StatsReportScheduleModal({
  config,
  isOpen,
  currentFilter,
  onClose,
  onSuccess,
}: StatsReportScheduleModalProps) {
  const { t } = useTranslation();

  const [groupByFilter, setGroupByFilter] = useState(currentFilter.groupByFilter);
  const [displayId, setDisplayId] = useState<number | null>(currentFilter.displayId);
  const [displayGroupId, setDisplayGroupId] = useState<number[]>(currentFilter.displayGroupId);
  const [itemName, setItemName] = useState('');

  useEffect(() => {
    if (isOpen) {
      setGroupByFilter(currentFilter.groupByFilter);
      setDisplayId(currentFilter.displayId);
      setDisplayGroupId(currentFilter.displayGroupId);
    }
  }, [isOpen]);

  useEffect(() => {
    if (!isOpen) {
      return;
    }
    let active = true;
    const resolveName = async () => {
      if (currentFilter.type === 'layout' && currentFilter.layoutId) {
        const { rows } = await fetchLayouts({
          start: 0,
          length: 1,
          layoutId: currentFilter.layoutId,
        });
        if (active) {
          setItemName(rows[0]?.layout ?? '');
        }
      } else if (currentFilter.type === 'media' && currentFilter.mediaId) {
        const { rows } = await fetchMedia({ start: 0, length: 1, mediaId: currentFilter.mediaId });
        if (active) {
          setItemName(rows[0]?.name ?? '');
        }
      } else {
        setItemName('');
      }
    };
    void resolveName();
    return () => {
      active = false;
    };
  }, [isOpen, currentFilter.type, currentFilter.layoutId, currentFilter.mediaId]);

  const displaySelect = useDisplayOptions(isOpen);
  const displayGroupSelect = useDisplayGroupSelect(isOpen);

  const selectedId =
    currentFilter.type === 'layout'
      ? currentFilter.layoutId
      : currentFilter.type === 'media'
        ? currentFilter.mediaId
        : null;

  const hasItem =
    currentFilter.type === 'event' ? currentFilter.eventTag.trim() !== '' : !!selectedId;

  const scheduleGroupByOptions = config.scheduleGroupByOptions ?? [];
  const displayGroupValue = displayGroupId[0]?.toString() ?? '';

  const itemLabel = currentFilter.type === 'event' ? currentFilter.eventTag : itemName;
  const title =
    hasItem && itemLabel
      ? t('Add Report Schedule for {{type}} - {{name}}', {
          type: currentFilter.type,
          name: itemLabel,
        })
      : t(config.scheduleTitle);

  return (
    <ReportScheduleModalShell
      isOpen={isOpen}
      title={title}
      onClose={onClose}
      onSuccess={onSuccess}
      canSave={hasItem}
      warning={
        !hasItem ? (
          <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {t('Select a type and an item (layout, media or event) before scheduling.')}
          </div>
        ) : null
      }
      buildPayload={(draft) => ({
        name: draft.name,
        reportName: config.reportName,
        filter: draft.filter,
        groupByFilter,
        displayGroupIds: [],
        displayId,
        displayGroupId,
        hiddenFields: {
          type: currentFilter.type,
          selectedId: selectedId ?? '',
          eventTag: currentFilter.eventTag,
        },
        fromDt: draft.fromDt || undefined,
        toDt: draft.toDt || undefined,
        sendEmail: draft.sendEmail,
        nonusers: draft.nonusers,
      })}
    >
      {scheduleGroupByOptions.length > 0 && (
        <SelectDropdown
          label={t('Group by')}
          value={groupByFilter}
          options={scheduleGroupByOptions.map((o) => ({ value: o.value, label: t(o.label) }))}
          onSelect={setGroupByFilter}
        />
      )}

      <SelectDropdown
        label={t('Display')}
        value={displayId?.toString() ?? ''}
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
        onSelect={(val) => {
          const next = val ? Number(val) : null;
          setDisplayId(next);
          // A specific display takes precedence over the display group selection.
          if (next) {
            setDisplayGroupId([]);
          }
        }}
      />

      {!displayId && (
        <SelectDropdown
          label={t('Display Group')}
          value={displayGroupValue}
          placeholder={t('All Groups')}
          searchable
          clearable
          resolveLabel={displayGroupSelect.resolveLabel}
          options={displayGroupSelect.options}
          isLoading={displayGroupSelect.isLoading}
          isLoadingMore={displayGroupSelect.isLoadingMore}
          hasMore={displayGroupSelect.hasMore}
          onSearch={displayGroupSelect.onSearch}
          onLoadMore={displayGroupSelect.onLoadMore}
          onSelect={(val) => setDisplayGroupId(val ? [Number(val)] : [])}
        />
      )}
    </ReportScheduleModalShell>
  );
}
