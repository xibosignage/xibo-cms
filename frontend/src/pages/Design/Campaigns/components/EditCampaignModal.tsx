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
import { useEffect, useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import { SearchAssignPanel } from '@/components/ui/SearchAssignPanel';
import Checkbox from '@/components/ui/forms/Checkbox';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import SelectFolder from '@/components/ui/forms/SelectFolder';
import TagInput from '@/components/ui/forms/TagInput';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { CheckMarkCell, TextCell } from '@/components/ui/table/cells';
import { useDebounce } from '@/hooks/useDebounce';
import { updateCampaign } from '@/services/campaignApi';
import {
  assignLayoutToCampaign,
  fetchLayouts,
  unassignLayoutFromCampaign,
} from '@/services/layoutsApi';
import type { Campaign } from '@/types/campaign';
import type { Layout } from '@/types/layout';
import type { Tag } from '@/types/tag';

interface EditCampaignModalProps {
  isOpen?: boolean;
  campaign: Campaign | null;
  onClose: () => void;
  onSuccess: () => void;
}

interface EditDraft {
  name: string;
  folderId: number | null;
  tags: Tag[];
  cyclePlaybackEnabled: boolean;
  playCount: number | '';
  listPlayOrder: 'round' | 'block';
  ref1: string;
  ref2: string;
  ref3: string;
  ref4: string;
  ref5: string;
}

type Tab = 'general' | 'reference' | 'layouts';

export default function EditCampaignModal({
  isOpen = true,
  campaign,
  onClose,
  onSuccess,
}: EditCampaignModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();
  const [activeTab, setActiveTab] = useState<Tab>('general');
  const [apiError, setApiError] = useState('');

  const [draft, setDraft] = useState<EditDraft>({
    name: '',
    folderId: null,
    tags: [],
    cyclePlaybackEnabled: false,
    playCount: '',
    listPlayOrder: 'round',
    ref1: '',
    ref2: '',
    ref3: '',
    ref4: '',
    ref5: '',
  });

  const [assignedLayouts, setAssignedLayouts] = useState<Layout[]>([]);
  const [originalLayoutIds, setOriginalLayoutIds] = useState<Set<number>>(new Set());
  const [layoutKeyword, setLayoutKeyword] = useState('');
  const [layoutPagination, setLayoutPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: 5,
  });
  const [layoutSorting, setLayoutSorting] = useState<SortingState>([]);

  const debouncedKeyword = useDebounce(layoutKeyword, 400);

  useEffect(() => {
    if (isOpen && campaign) {
      setDraft({
        name: campaign.campaign,
        folderId: campaign.folderId ?? null,
        tags: campaign.tags ? campaign.tags.map((t) => ({ ...t })) : [],
        cyclePlaybackEnabled: campaign.cyclePlaybackEnabled === 1,
        playCount: campaign.playCount ?? '',
        listPlayOrder: (campaign.listPlayOrder as 'round' | 'block') || 'round',
        ref1: campaign.ref1 ?? '',
        ref2: campaign.ref2 ?? '',
        ref3: campaign.ref3 ?? '',
        ref4: campaign.ref4 ?? '',
        ref5: campaign.ref5 ?? '',
      });
      setActiveTab('general');
      setApiError('');
      setLayoutKeyword('');
      setLayoutPagination({ pageIndex: 0, pageSize: 5 });
    }
  }, [isOpen, campaign]);

  const { data: assignedData } = useQuery({
    queryKey: ['layouts', 'campaign', campaign?.campaignId],
    queryFn: () => fetchLayouts({ start: 0, length: 200, campaignId: campaign!.campaignId }),
    enabled: isOpen && !!campaign,
    staleTime: 0,
  });

  useEffect(() => {
    if (assignedData) {
      setAssignedLayouts(assignedData.rows);
      const ids = new Set(assignedData.rows.map((l) => l.layoutId));
      setOriginalLayoutIds(ids);
    }
  }, [assignedData]);

  const layoutSortBy = layoutSorting[0]?.id;
  const layoutSortDir = layoutSorting[0] ? (layoutSorting[0].desc ? 'desc' : 'asc') : undefined;

  const { data: layoutsData, isFetching: isFetchingLayouts } = useQuery({
    queryKey: [
      'layouts',
      'campaignPicker',
      { keyword: debouncedKeyword, pagination: layoutPagination, layoutSortBy, layoutSortDir },
    ],
    queryFn: () =>
      fetchLayouts({
        start: layoutPagination.pageIndex * layoutPagination.pageSize,
        length: layoutPagination.pageSize,
        keyword: debouncedKeyword || undefined,
        sortBy: layoutSortBy,
        sortDir: layoutSortDir,
      }),
    enabled: isOpen && !!campaign && activeTab === 'layouts',
    placeholderData: keepPreviousData,
    staleTime: 1000 * 30,
  });

  const layoutRows = layoutsData?.rows ?? [];
  const layoutPageCount = Math.ceil((layoutsData?.totalCount ?? 0) / layoutPagination.pageSize);

  const addLayout = (layout: Layout) => {
    if (assignedLayouts.some((l) => l.layoutId === layout.layoutId)) {
      return;
    }
    setAssignedLayouts((prev) => [...prev, layout]);
  };

  const removeLayout = (layout: Layout) => {
    setAssignedLayouts((prev) => prev.filter((l) => l.layoutId !== layout.layoutId));
  };

  const handleSave = () => {
    if (!campaign) return;

    startTransition(async () => {
      try {
        const serializedTags = draft.tags
          .map((tag) =>
            tag.value != null && tag.value !== '' ? `${tag.tag}|${tag.value}` : tag.tag,
          )
          .join(',');

        await updateCampaign(campaign.campaignId, {
          name: draft.name,
          folderId: draft.folderId,
          tags: serializedTags || undefined,
          cyclePlaybackEnabled: draft.cyclePlaybackEnabled ? 1 : 0,
          playCount:
            draft.cyclePlaybackEnabled && draft.playCount !== ''
              ? Number(draft.playCount)
              : undefined,
          listPlayOrder: !draft.cyclePlaybackEnabled ? draft.listPlayOrder : undefined,
          ref1: draft.ref1 || undefined,
          ref2: draft.ref2 || undefined,
          ref3: draft.ref3 || undefined,
          ref4: draft.ref4 || undefined,
          ref5: draft.ref5 || undefined,
        });

        // Layout assignment changes
        const currentIds = new Set(assignedLayouts.map((l) => l.layoutId));
        const toAssign = [...currentIds].filter((id) => !originalLayoutIds.has(id));
        const toUnassign = [...originalLayoutIds].filter((id) => !currentIds.has(id));

        await Promise.all([
          ...toAssign.map((id) => assignLayoutToCampaign(campaign.campaignId, id)),
          ...toUnassign.map((id) => unassignLayoutFromCampaign(campaign.campaignId, id)),
        ]);

        onSuccess();
        onClose();
      } catch (err: unknown) {
        const apiErr = err as { response?: { data?: { message?: string } } };
        if (apiErr.response?.data?.message) {
          setApiError(apiErr.response.data.message);
        } else if (err instanceof Error) {
          setApiError(err.message);
        } else {
          setApiError(t('An unexpected error occurred.'));
        }
      }
    });
  };

  const layoutColumns: ColumnDef<Layout>[] = [
    {
      accessorKey: 'layoutId',
      header: t('ID'),
      size: 70,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'layout',
      header: t('Name'),
      cell: ({ row }) => <TextCell weight="bold">{row.original.layout}</TextCell>,
    },
    {
      id: 'publishedStatus',
      accessorFn: (row) => row.publishedStatusId,
      header: t('Status'),
      size: 90,
      cell: (info) => <CheckMarkCell active={info.getValue<number>() === 1} />,
    },
  ];

  if (!isOpen || !campaign) return null;

  const tabs: { key: Tab; label: string }[] = [
    { key: 'general', label: t('General') },
    { key: 'reference', label: t('Reference') },
    { key: 'layouts', label: t('Layouts') },
  ];

  return (
    <Modal
      isOpen={isOpen}
      title={t('Edit Campaign')}
      onClose={onClose}
      size="lg"
      isPending={isPending}
      error={apiError}
      scrollable={false}
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary', disabled: isPending },
        {
          label: isPending ? t('Saving…') : t('Save'),
          onClick: handleSave,
          disabled: isPending,
        },
      ]}
    >
      {/* Tabs */}
      <div className="border-b border-gray-200 px-6">
        <nav className="flex">
          {tabs.map(({ key, label }) => (
            <button
              key={key}
              type="button"
              onClick={() => setActiveTab(key)}
              className={`px-4 py-3 text-sm font-medium border-b-2 transition-colors ${
                activeTab === key
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700'
              }`}
            >
              {label}
            </button>
          ))}
        </nav>
      </div>

      <div className="flex flex-col h-full overflow-y-hidden overflow-x-visible gap-3 px-4">
        <div className="flex flex-col gap-3 flex-1 min-h-0 p-4 overflow-y-auto">
          {/* General Tab */}

          {activeTab === 'general' && (
            <>
              <div className="relative z-20">
                <SelectFolder
                  selectedId={draft.folderId}
                  onSelect={(folder) =>
                    setDraft((prev) => ({ ...prev, folderId: folder?.id ?? null }))
                  }
                />
              </div>

              <TextInput
                name="name"
                label={t('Name')}
                placeholder={t('Enter name')}
                value={draft.name}
                onChange={(val) => setDraft((prev) => ({ ...prev, name: val }))}
              />

              <TagInput
                value={draft.tags}
                helpText={t('Tags for this Campaign — comma-separated Tag or Tag|Value format.')}
                onChange={(tags) => setDraft((prev) => ({ ...prev, tags }))}
              />

              <Checkbox
                id="cyclePlayback"
                title={t('Enable cycle based playback')}
                className="items-start px-3 py-2.5"
                label={t(
                  `When cycle based playback is enabled only 1 Layout from this Campaign will be played each time it is in a Schedule loop. The same Layout will be shown until the 'Play count' is achieved.`,
                )}
                checked={draft.cyclePlaybackEnabled}
                onChange={() =>
                  setDraft((prev) => ({
                    ...prev,
                    cyclePlaybackEnabled: !prev.cyclePlaybackEnabled,
                  }))
                }
              />

              {draft.cyclePlaybackEnabled ? (
                <TextInput
                  name="playCount"
                  label={t('Play count')}
                  type="number"
                  value={draft.playCount === '' ? '' : String(draft.playCount)}
                  onChange={(val) =>
                    setDraft((prev) => ({
                      ...prev,
                      playCount: val === '' ? '' : Number(val),
                    }))
                  }
                />
              ) : (
                <SelectDropdown
                  label={t('List play order')}
                  value={draft.listPlayOrder}
                  options={[
                    { label: t('Round-robin'), value: 'round' },
                    { label: t('Block'), value: 'block' },
                  ]}
                  onSelect={(val) =>
                    setDraft((prev) => ({ ...prev, listPlayOrder: val as 'round' | 'block' }))
                  }
                />
              )}
            </>
          )}

          {/* Reference Tab */}
          {activeTab === 'reference' && (
            <>
              <p className="text-sm text-gray-500">{t('Add reference fields if needed')}</p>
              {(['ref1', 'ref2', 'ref3', 'ref4', 'ref5'] as const).map((ref, i) => (
                <TextInput
                  key={ref}
                  name={ref}
                  label={t('Reference {{n}}', { n: i + 1 })}
                  placeholder={t('Enter reference {{n}}', { n: i + 1 })}
                  value={draft[ref]}
                  onChange={(val) => setDraft((prev) => ({ ...prev, [ref]: val }))}
                />
              ))}
            </>
          )}

          {/* Layouts Tab */}
          {activeTab === 'layouts' && (
            <SearchAssignPanel<Layout>
              assignedItems={assignedLayouts}
              assignedLabel={t('Selected Layouts')}
              onAddItem={addLayout}
              onRemoveItem={removeLayout}
              onClearAll={() => setAssignedLayouts([])}
              noAssignedText={t('No layouts assigned yet')}
              getItemId={(l) => l.layoutId}
              getItemLabel={(l) => l.layout}
              keyword={layoutKeyword}
              onKeywordChange={setLayoutKeyword}
              searchPlaceholder={t('Search')}
              columns={layoutColumns}
              searchRows={layoutRows}
              pageCount={layoutPageCount}
              pagination={layoutPagination}
              onPaginationChange={setLayoutPagination}
              sorting={layoutSorting}
              onSortingChange={setLayoutSorting}
              isSearching={isFetchingLayouts}
            />
          )}
        </div>
      </div>
    </Modal>
  );
}
