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

import { keepPreviousData, useQuery } from '@tanstack/react-query';
import type { ColumnDef, PaginationState, SortingState } from '@tanstack/react-table';
import { isAxiosError } from 'axios';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { SearchAssignPanel } from '@/components/ui/SearchAssignPanel';
import Modal from '@/components/ui/modals/Modal';
import { TextCell } from '@/components/ui/table/cells';
import { useDebounce } from '@/hooks/useDebounce';
import { assignLayouts } from '@/services/displaysApi';
import { fetchLayouts } from '@/services/layoutsApi';
import type { Layout } from '@/types/layout';

interface AssignLayoutModalProps {
  display: { displayGroupId: number };
  onClose: () => void;
  onSave: () => void;
}

export default function AssignLayoutModal({ display, onClose, onSave }: AssignLayoutModalProps) {
  const { t } = useTranslation();

  const [assignedLayouts, setAssignedLayouts] = useState<Layout[]>([]);
  const [isLoadingAssigned, setIsLoadingAssigned] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  const [toAdd, setToAdd] = useState<number[]>([]);
  const [toRemove, setToRemove] = useState<number[]>([]);

  const [nameFilter, setNameFilter] = useState('');
  const debouncedName = useDebounce(nameFilter, 400);

  const [pagination, setPagination] = useState<PaginationState>({ pageIndex: 0, pageSize: 5 });
  const [sorting, setSorting] = useState<SortingState>([]);

  useEffect(() => {
    setIsLoadingAssigned(true);
    fetchLayouts({ start: 0, length: 500, displayGroupId: display.displayGroupId })
      .then(({ rows }) => setAssignedLayouts(rows))
      .catch(() => setAssignedLayouts([]))
      .finally(() => setIsLoadingAssigned(false));
  }, [display.displayGroupId]);

  const { data: searchData, isFetching } = useQuery({
    queryKey: ['assign-layouts-search', debouncedName, pagination, sorting],
    queryFn: ({ signal }) =>
      fetchLayouts({
        start: pagination.pageIndex * pagination.pageSize,
        length: pagination.pageSize,
        keyword: debouncedName || undefined,
        retired: 0,
        sortBy: sorting[0]?.id,
        sortDir: sorting[0] ? (sorting[0].desc ? 'desc' : 'asc') : undefined,
        signal,
      }),
    placeholderData: keepPreviousData,
    staleTime: 1000 * 60,
  });

  const searchRows = searchData?.rows ?? [];
  const pageCount = Math.ceil((searchData?.totalCount ?? 0) / pagination.pageSize);

  const handleAdd = (layout: Layout) => {
    if (assignedLayouts.some((l) => l.layoutId === layout.layoutId)) {
      return;
    }
    setAssignedLayouts((prev) => [...prev, layout]);
    setToAdd((prev) => [...prev, layout.layoutId]);
    setToRemove((prev) => prev.filter((id) => id !== layout.layoutId));
  };

  const handleRemove = (layout: Layout) => {
    setAssignedLayouts((prev) => prev.filter((l) => l.layoutId !== layout.layoutId));
    setToRemove((prev) => [...prev, layout.layoutId]);
    setToAdd((prev) => prev.filter((id) => id !== layout.layoutId));
  };

  const handleSave = async () => {
    if (toAdd.length === 0 && toRemove.length === 0) {
      onClose();
      return;
    }
    try {
      setSaveError(null);
      setIsSaving(true);
      await assignLayouts(display.displayGroupId, toAdd, toRemove);
      onSave();
      onClose();
    } catch (error) {
      const message =
        isAxiosError(error) && error.response?.data?.message
          ? error.response.data.message
          : t('Failed to assign layouts.');
      setSaveError(message);
    } finally {
      setIsSaving(false);
    }
  };

  const columns: ColumnDef<Layout>[] = [
    {
      accessorKey: 'layout',
      header: t('Name'),
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
  ];

  const handleClearAll = () => {
    const originalAssignedIds = assignedLayouts
      .filter((l) => !toAdd.includes(l.layoutId))
      .map((l) => l.layoutId);
    setAssignedLayouts([]);
    setToAdd([]);
    setToRemove((prev) => [...new Set([...prev, ...originalAssignedIds])]);
  };

  return (
    <Modal
      title={t('Assign Layouts')}
      isOpen
      isPending={isSaving}
      onClose={onClose}
      error={saveError ?? undefined}
      size="lg"
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary', disabled: isSaving },
        {
          label: isSaving ? t('Saving\u2026') : t('Save'),
          onClick: handleSave,
          disabled: isSaving,
        },
      ]}
    >
      <div className="p-5">
        <SearchAssignPanel<Layout>
          assignedItems={assignedLayouts}
          isLoadingAssigned={isLoadingAssigned}
          onAddItem={handleAdd}
          onRemoveItem={handleRemove}
          onClearAll={handleClearAll}
          assignedLabel={t('Assigned Layouts')}
          noAssignedText={t('No layouts assigned.')}
          getItemId={(l) => l.layoutId}
          getItemLabel={(l) => l.layout}
          keyword={nameFilter}
          onKeywordChange={setNameFilter}
          searchLabel={t('Name')}
          searchPlaceholder={t('Filter by name\u2026')}
          columns={columns}
          searchRows={searchRows}
          pageCount={pageCount}
          pagination={pagination}
          onPaginationChange={setPagination}
          sorting={sorting}
          onSortingChange={setSorting}
          isSearching={isFetching}
          warningMessage={t(
            'Assigning a Layout to a Display does NOT schedule that Layout to be shown. Please use the Schedule to show Layouts.',
          )}
        />
      </div>
    </Modal>
  );
}
