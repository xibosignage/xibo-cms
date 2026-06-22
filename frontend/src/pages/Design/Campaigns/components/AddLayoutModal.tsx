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

import GeoScheduleMap from '@/components/ui/GeoScheduleMap';
import MultiSelectDropdown from '@/components/ui/forms/MultiSelectDropdown';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import type { SelectOption } from '@/components/ui/forms/SelectDropdown';
import Modal from '@/components/ui/modals/Modal';
import { useDebounce } from '@/hooks/useDebounce';
import { fetchDaypart } from '@/services/daypartApi';

const DAYPART_PAGE_SIZE = 10;

const ALL_DAYS = ['1', '2', '3', '4', '5', '6', '7'];

function getDayOptions(t: (s: string) => string) {
  return [
    { value: '1', label: t('Monday') },
    { value: '2', label: t('Tuesday') },
    { value: '3', label: t('Wednesday') },
    { value: '4', label: t('Thursday') },
    { value: '5', label: t('Friday') },
    { value: '6', label: t('Saturday') },
    { value: '7', label: t('Sunday') },
  ];
}

export interface LayoutAssignmentValues {
  daysOfWeek: number[];
  dayPartId: number | null;
  geoFence: string;
}

export interface AddLayoutModalProps {
  isOpen: boolean;
  layoutName: string;
  initialValues?: LayoutAssignmentValues;
  isSaving: boolean;
  defaultLat: number;
  defaultLng: number;
  onClose: () => void;
  onSave: (values: LayoutAssignmentValues) => void;
}

export default function AddLayoutModal({
  isOpen,
  layoutName,
  initialValues,
  isSaving,
  defaultLat,
  defaultLng,
  onClose,
  onSave,
}: AddLayoutModalProps) {
  const { t } = useTranslation();

  const [daysOfWeek, setDaysOfWeek] = useState<string[]>([]);
  const [dayPartId, setDayPartId] = useState<number | null>(null);
  const [geoFence, setGeoFence] = useState('');

  // Daypart paginated select state
  const [dayparts, setDayparts] = useState<SelectOption[]>([]);
  const [isLoadingDayparts, setIsLoadingDayparts] = useState(false);
  const [isLoadingMoreDayparts, setIsLoadingMoreDayparts] = useState(false);
  const [hasMoreDayparts, setHasMoreDayparts] = useState(false);
  const [daypartPage, setDaypartPage] = useState(0);
  const [daypartSearch, setDaypartSearch] = useState('');
  const debouncedDaypartSearch = useDebounce(daypartSearch, 300);

  useEffect(() => {
    if (isOpen) {
      setDaysOfWeek(initialValues ? initialValues.daysOfWeek.map(String) : ALL_DAYS);
      setDayPartId(initialValues?.dayPartId ?? null);
      setGeoFence(initialValues?.geoFence ?? '');
      setDaypartSearch('');
    }
  }, [isOpen, initialValues]);

  useEffect(() => {
    if (!isOpen) {
      return;
    }
    setIsLoadingDayparts(true);
    setDayparts([]);
    setDaypartPage(0);
    fetchDaypart({
      start: 0,
      length: DAYPART_PAGE_SIZE,
      isAlways: 0,
      isCustom: 0,
      name: debouncedDaypartSearch || undefined,
    })
      .then((res) => {
        setDayparts(res.rows.map((d) => ({ value: String(d.dayPartId), label: d.name })));
        setHasMoreDayparts(res.rows.length === DAYPART_PAGE_SIZE);
      })
      .catch(() => setDayparts([]))
      .finally(() => setIsLoadingDayparts(false));
  }, [isOpen, debouncedDaypartSearch]);

  const handleLoadMoreDayparts = () => {
    if (isLoadingMoreDayparts || !hasMoreDayparts) {
      return;
    }
    const nextPage = daypartPage + 1;
    setIsLoadingMoreDayparts(true);
    fetchDaypart({
      start: nextPage * DAYPART_PAGE_SIZE,
      length: DAYPART_PAGE_SIZE,
      isAlways: 0,
      isCustom: 0,
      name: debouncedDaypartSearch || undefined,
    })
      .then((res) => {
        setDayparts((prev) => [
          ...prev,
          ...res.rows.map((d) => ({ value: String(d.dayPartId), label: d.name })),
        ]);
        setDaypartPage(nextPage);
        setHasMoreDayparts(res.rows.length === DAYPART_PAGE_SIZE);
      })
      .catch(() => {})
      .finally(() => setIsLoadingMoreDayparts(false));
  };

  const handleSave = () => {
    onSave({
      daysOfWeek: daysOfWeek.map(Number),
      dayPartId,
      geoFence,
    });
  };

  if (!isOpen) {
    return null;
  }

  const isEdit = initialValues !== undefined;

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={isEdit ? t('Edit Layout') : t('Add Layout')}
      size="lg"
      scrollable
      isPending={isSaving}
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary', disabled: isSaving },
        {
          label: isSaving ? t('Saving…') : t('Save'),
          onClick: handleSave,
          disabled: isSaving,
        },
      ]}
    >
      <div className="flex flex-col gap-4 p-5">
        {layoutName && (
          <p className="text-sm text-gray-500">
            {t('Configure how {{name}} plays within this campaign.', { name: layoutName })}
          </p>
        )}

        <MultiSelectDropdown
          label={t('Days of the Week')}
          value={daysOfWeek}
          options={getDayOptions(t)}
          onChange={setDaysOfWeek}
          placeholder={t('All days')}
          selectAllOption
          selectAllText={t('Select All')}
          showTags
        />

        <SelectDropdown
          label={t('Dayparting')}
          value={dayPartId != null ? String(dayPartId) : ''}
          options={dayparts}
          onSelect={(v) => setDayPartId(v ? Number(v) : null)}
          placeholder={t('Select Dayparting')}
          isLoading={isLoadingDayparts}
          onLoadMore={handleLoadMoreDayparts}
          hasMore={hasMoreDayparts}
          isLoadingMore={isLoadingMoreDayparts}
          searchable
          clearable
          searchPlaceholder={t('Search')}
          onSearch={setDaypartSearch}
        />

        <div className="flex flex-col gap-2">
          <span className="text-sm font-medium text-gray-700">{t('Geofence')}</span>
          <p className="text-sm text-gray-500">
            {t('Draw areas on the map where you want this layout to play.')}
          </p>
          <div className="flex h-96">
            <GeoScheduleMap
              geoLocation={geoFence}
              onChange={setGeoFence}
              defaultLat={defaultLat}
              defaultLng={defaultLng}
            />
          </div>
        </div>
      </div>
    </Modal>
  );
}
