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

import type { ColumnDef, PaginationState, SortingState } from '@tanstack/react-table';
import type { TFunction } from 'i18next';
import { ArrowLeft, PenSquare, Trash2 } from 'lucide-react';
import type { ComponentProps } from 'react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate, useParams } from 'react-router-dom';

import type { LayoutAssignmentValues } from './components/AddLayoutModal';
import AddLayoutModal from './components/AddLayoutModal';
import { useAdCampaignActions } from './hooks/useAdCampaignActions';
import { useAdCampaignData } from './hooks/useAdCampaignData';

import Button from '@/components/ui/Button';
import DatePickerInput from '@/components/ui/forms/DatePickerInput';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import type { SelectOption } from '@/components/ui/forms/SelectDropdown';
import TagInput, { collectTags, serializeTags } from '@/components/ui/forms/TagInput';
import TextInput from '@/components/ui/forms/TextInput';
import { DataTable } from '@/components/ui/table/DataTable';
import { ActionsCell, CheckMarkCell, StatusCell, TextCell } from '@/components/ui/table/cells';
import { useUserContext } from '@/context/UserContext';
import { useDebounce } from '@/hooks/useDebounce';
import { DisplayGroupMultiSelect } from '@/pages/Schedule/Schedule/components/DisplayGroupMultiSelect';
import type { DisplayGroupMultiSelectValue } from '@/pages/Schedule/Schedule/components/DisplayGroupMultiSelect';
import { fetchDisplayGroups } from '@/services/displayGroupApi';
import { fetchLayouts } from '@/services/layoutsApi';
import type { Campaign, LayoutOnCampaign } from '@/types/campaign';
import type { Tag } from '@/types/tag';
import { formatDateTime } from '@/utils/date';

const DEFAULT_LAT_FALLBACK = 51.5;
const DEFAULT_LNG_FALLBACK = -0.13;
const LAYOUT_PAGE_SIZE = 10;

function formatDuration(seconds: number): string {
  const s = Math.max(0, Math.floor(seconds || 0));
  const hh = String(Math.floor(s / 3600)).padStart(2, '0');
  const mm = String(Math.floor((s % 3600) / 60)).padStart(2, '0');
  const ss = String(s % 60).padStart(2, '0');
  return `${hh}:${mm}:${ss}`;
}

function formatDays(daysOfWeek: string | null, t: TFunction): string {
  if (!daysOfWeek) {
    return '—';
  }
  const labels: Record<string, string> = {
    '1': t('Mon'),
    '2': t('Tue'),
    '3': t('Wed'),
    '4': t('Thu'),
    '5': t('Fri'),
    '6': t('Sat'),
    '7': t('Sun'),
  };
  return daysOfWeek
    .split(',')
    .map((d) => labels[d.trim()] ?? d)
    .join(', ');
}

function unixToIso(unix: number | null | undefined): string {
  if (!unix) {
    return '';
  }
  return new Date(unix * 1000).toISOString();
}

interface GeneralDraft {
  name: string;
  startDt: string;
  endDt: string;
  targetType: string;
  target: string;
  tags: Tag[];
  ref1: string;
  ref2: string;
  ref3: string;
  ref4: string;
  ref5: string;
}

function buildDraft(campaign: Campaign): GeneralDraft {
  return {
    name: campaign.campaign,
    startDt: unixToIso(campaign.startDt),
    endDt: unixToIso(campaign.endDt),
    targetType: campaign.targetType ?? 'plays',
    target: campaign.target != null ? String(campaign.target) : '',
    tags: campaign.tags ? campaign.tags.map((tag) => ({ ...tag })) : [],
    ref1: campaign.ref1 ?? '',
    ref2: campaign.ref2 ?? '',
    ref3: campaign.ref3 ?? '',
    ref4: campaign.ref4 ?? '',
    ref5: campaign.ref5 ?? '',
  };
}

type EditorTab = 'general' | 'reference';

const getLayoutColumns = (
  t: TFunction,
  onEdit: (row: LayoutOnCampaign) => void,
  onRemove: (row: LayoutOnCampaign) => void,
): ColumnDef<LayoutOnCampaign>[] => [
  {
    accessorKey: 'layoutId',
    header: t('ID'),
    size: 80,
    cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
  },
  {
    accessorKey: 'layout',
    header: t('Name'),
    size: 200,
    cell: ({ row }) => <TextCell weight="bold">{row.original.layout}</TextCell>,
  },
  {
    accessorKey: 'duration',
    header: t('Duration'),
    size: 120,
    cell: (info) => <TextCell>{formatDuration(info.getValue<number>())}</TextCell>,
  },
  {
    accessorKey: 'daysOfWeek',
    header: t('Days'),
    size: 140,
    enableSorting: false,
    cell: ({ row }) => <TextCell>{formatDays(row.original.daysOfWeek, t)}</TextCell>,
  },
  {
    accessorKey: 'dayPart',
    header: t('Dayparting'),
    size: 130,
    cell: ({ row }) =>
      row.original.dayPart ? <StatusCell label={row.original.dayPart} /> : <TextCell>—</TextCell>,
  },
  {
    accessorKey: 'geoFence',
    header: t('Geofence'),
    size: 110,
    enableSorting: false,
    cell: ({ row }) => <CheckMarkCell active={!!row.original.geoFence} />,
  },
  {
    id: 'tableActions',
    header: '',
    size: 80,
    minSize: 80,
    maxSize: 80,
    enableHiding: false,
    enableResizing: false,
    enableSorting: false,
    cell: (info) => {
      const row = info.row.original;
      const actions = [
        {
          label: t('Edit'),
          icon: PenSquare,
          onClick: () => onEdit(row),
          isQuickAction: true,
          variant: 'primary' as const,
        },
        {
          label: t('Remove'),
          icon: Trash2,
          onClick: () => onRemove(row),
          variant: 'danger' as const,
        },
      ];
      return (
        <ActionsCell
          row={info.row}
          actions={actions as ComponentProps<typeof ActionsCell>['actions']}
        />
      );
    },
  },
];

export default function CampaignEditor() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const campaignId = Number(id);

  const { user } = useUserContext();
  const timezone = user?.settings?.defaultTimezone ?? 'UTC';
  const defaultLat = Number(user?.settings?.DEFAULT_LAT ?? DEFAULT_LAT_FALLBACK);
  const defaultLng = Number(user?.settings?.DEFAULT_LONG ?? DEFAULT_LNG_FALLBACK);

  const { data: campaign, isLoading, isError } = useAdCampaignData(campaignId);
  const { saveGeneral, assignLayout, removeLayout, editAssignment } = useAdCampaignActions({
    campaignId,
    t,
  });

  const [activeTab, setActiveTab] = useState<EditorTab>('general');
  const [draft, setDraft] = useState<GeneralDraft | null>(null);
  const [pendingTagInput, setPendingTagInput] = useState('');
  const [dateErrors, setDateErrors] = useState<{ startDt?: string; endDt?: string }>({});

  const [displayTargets, setDisplayTargets] = useState<DisplayGroupMultiSelectValue>({
    displaySpecificGroupIds: [],
    displayGroupIds: [],
  });

  const [layoutTablePagination, setLayoutTablePagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: 10,
  });
  const [layoutTableSorting, setLayoutTableSorting] = useState<SortingState>([]);

  const [layoutOptions, setLayoutOptions] = useState<SelectOption[]>([]);
  const [isLoadingLayouts, setIsLoadingLayouts] = useState(false);
  const [isLoadingMoreLayouts, setIsLoadingMoreLayouts] = useState(false);
  const [hasMoreLayouts, setHasMoreLayouts] = useState(false);
  const [layoutPage, setLayoutPage] = useState(0);
  const [layoutSearch, setLayoutSearch] = useState('');
  const debouncedLayoutSearch = useDebounce(layoutSearch, 300);

  // Add/Edit layout modal.
  const [modalLayout, setModalLayout] = useState<{
    layoutId: number;
    name: string;
    initialValues?: LayoutAssignmentValues;
    displayOrder?: number;
  } | null>(null);

  useEffect(() => {
    if (campaign) {
      setDraft(buildDraft(campaign));
    }
  }, [campaign]);

  useEffect(() => {
    const ids = campaign?.displayGroupIds ?? [];
    if (ids.length === 0) {
      setDisplayTargets({ displaySpecificGroupIds: [], displayGroupIds: [] });
      return;
    }
    let cancelled = false;
    fetchDisplayGroups({
      start: 0,
      length: ids.length,
      isDisplaySpecific: -1,
      displayGroupIds: ids,
    })
      .then((res) => {
        if (cancelled) {
          return;
        }
        const displaySpecificGroupIds: number[] = [];
        const displayGroupIds: number[] = [];
        res.rows.forEach((g) => {
          if (g.isDisplaySpecific === 1) {
            displaySpecificGroupIds.push(g.displayGroupId);
          } else {
            displayGroupIds.push(g.displayGroupId);
          }
        });
        setDisplayTargets({ displaySpecificGroupIds, displayGroupIds });
      })
      .catch(() => {});
    return () => {
      cancelled = true;
    };
  }, [campaign]);

  useEffect(() => {
    setIsLoadingLayouts(true);
    setLayoutOptions([]);
    setLayoutPage(0);
    fetchLayouts({
      start: 0,
      length: LAYOUT_PAGE_SIZE,
      retired: 0,
      layout: debouncedLayoutSearch || undefined,
    })
      .then((res) => {
        setLayoutOptions(res.rows.map((l) => ({ value: String(l.layoutId), label: l.layout })));
        setHasMoreLayouts(res.rows.length === LAYOUT_PAGE_SIZE);
      })
      .catch(() => setLayoutOptions([]))
      .finally(() => setIsLoadingLayouts(false));
  }, [debouncedLayoutSearch]);

  const handleLoadMoreLayouts = () => {
    if (isLoadingMoreLayouts || !hasMoreLayouts) {
      return;
    }
    const nextPage = layoutPage + 1;
    setIsLoadingMoreLayouts(true);
    fetchLayouts({
      start: nextPage * LAYOUT_PAGE_SIZE,
      length: LAYOUT_PAGE_SIZE,
      retired: 0,
      layout: debouncedLayoutSearch || undefined,
    })
      .then((res) => {
        setLayoutOptions((prev) => [
          ...prev,
          ...res.rows.map((l) => ({ value: String(l.layoutId), label: l.layout })),
        ]);
        setLayoutPage(nextPage);
        setHasMoreLayouts(res.rows.length === LAYOUT_PAGE_SIZE);
      })
      .catch(() => {})
      .finally(() => setIsLoadingMoreLayouts(false));
  };

  const assignedLayouts: LayoutOnCampaign[] = campaign?.layouts ?? [];

  const computeProgress = () => {
    if (!campaign) {
      return { timePct: 0, targetPct: 0 };
    }
    const now = Date.now() / 1000;
    let time = 0;
    if (campaign.startDt && campaign.endDt && campaign.endDt > campaign.startDt) {
      time = ((now - campaign.startDt) / (campaign.endDt - campaign.startDt)) * 100;
    }
    const achieved =
      campaign.targetType === 'budget'
        ? campaign.spend
        : campaign.targetType === 'imp'
          ? campaign.impressions
          : campaign.plays;
    const target = campaign.target > 0 ? (achieved / campaign.target) * 100 : 0;
    const clamp = (v: number) => Math.min(100, Math.max(0, Math.round(v)));
    return { timePct: clamp(time), targetPct: clamp(target) };
  };
  const { timePct, targetPct } = computeProgress();

  const handleSaveGeneral = () => {
    if (!draft) {
      return;
    }

    const errors: { startDt?: string; endDt?: string } = {};
    if (!draft.startDt) {
      errors.startDt = t('Please select a start date.');
    }
    if (!draft.endDt) {
      errors.endDt = t('Please select an end date.');
    }
    if (errors.startDt || errors.endDt) {
      setDateErrors(errors);
      setActiveTab('general');
      return;
    }
    setDateErrors({});

    const finalTags = collectTags(draft.tags, pendingTagInput);
    setPendingTagInput('');
    const serializedTags = serializeTags(finalTags);

    saveGeneral.mutate({
      name: draft.name,
      tags: serializedTags || undefined,
      startDt: draft.startDt ? formatDateTime(new Date(draft.startDt), timezone) : undefined,
      endDt: draft.endDt ? formatDateTime(new Date(draft.endDt), timezone) : undefined,
      targetType: draft.targetType,
      target: draft.target !== '' ? Number(draft.target) : undefined,
      displayGroupIds: [
        ...displayTargets.displaySpecificGroupIds,
        ...displayTargets.displayGroupIds,
      ],
      ref1: draft.ref1,
      ref2: draft.ref2,
      ref3: draft.ref3,
      ref4: draft.ref4,
      ref5: draft.ref5,
    });
  };

  const handlePickLayout = (value: string) => {
    if (!value) {
      return;
    }
    const opt = layoutOptions.find((o) => o.value === value);
    setModalLayout({ layoutId: Number(value), name: opt?.label ?? '' });
  };

  const handleEditAssignment = (row: LayoutOnCampaign) => {
    setModalLayout({
      layoutId: row.layoutId,
      name: row.layout,
      displayOrder: row.displayOrder,
      initialValues: {
        daysOfWeek: row.daysOfWeek ? row.daysOfWeek.split(',').map((d) => Number(d.trim())) : [],
        dayPartId: row.dayPartId,
        geoFence:
          typeof row.geoFence === 'string'
            ? row.geoFence
            : row.geoFence
              ? JSON.stringify(row.geoFence)
              : '',
      },
    });
  };

  const handleSaveAssignment = (values: LayoutAssignmentValues) => {
    if (!modalLayout) {
      return;
    }
    const next = {
      layoutId: modalLayout.layoutId,
      daysOfWeek: values.daysOfWeek,
      dayPartId: values.dayPartId,
      geoFence: values.geoFence || undefined,
    };

    const onSuccess = () => setModalLayout(null);

    if (modalLayout.displayOrder != null) {
      editAssignment.mutate(
        {
          displayOrder: modalLayout.displayOrder,
          next,
          original: {
            layoutId: modalLayout.layoutId,
            daysOfWeek: modalLayout.initialValues?.daysOfWeek,
            dayPartId: modalLayout.initialValues?.dayPartId,
            geoFence: modalLayout.initialValues?.geoFence || undefined,
          },
        },
        { onSuccess },
      );
    } else {
      assignLayout.mutate(next, { onSuccess });
    }
  };

  const layoutColumns = getLayoutColumns(t, handleEditAssignment, (row) =>
    removeLayout.mutate({ layoutId: row.layoutId, displayOrder: row.displayOrder }),
  );

  const sortedLayouts = (() => {
    const sort = layoutTableSorting[0];
    if (!sort) {
      return assignedLayouts;
    }
    const dir = sort.desc ? -1 : 1;
    return [...assignedLayouts].sort((a, b) => {
      const av = a[sort.id as keyof LayoutOnCampaign] ?? '';
      const bv = b[sort.id as keyof LayoutOnCampaign] ?? '';
      if (typeof av === 'number' && typeof bv === 'number') {
        return (av - bv) * dir;
      }
      return String(av).localeCompare(String(bv)) * dir;
    });
  })();
  const layoutPageCount = Math.max(
    1,
    Math.ceil(sortedLayouts.length / layoutTablePagination.pageSize),
  );
  const layoutPageRows = sortedLayouts.slice(
    layoutTablePagination.pageIndex * layoutTablePagination.pageSize,
    layoutTablePagination.pageIndex * layoutTablePagination.pageSize +
      layoutTablePagination.pageSize,
  );

  if (isLoading || !draft) {
    return (
      <div className="flex h-full w-full items-center justify-center">
        <span className="text-gray-400 font-medium">{t('Loading campaign…')}</span>
      </div>
    );
  }

  if (isError || !campaign) {
    return (
      <div className="flex h-full w-full flex-col items-center justify-center gap-4">
        <p className="text-red-700">{t('Unable to load this campaign.')}</p>
        <Button variant="secondary" onClick={() => navigate('/design/campaign')}>
          {t('Back to Campaigns')}
        </Button>
      </div>
    );
  }

  return (
    <section className="flex h-full w-full min-h-0 flex-col overflow-y-auto px-5 pb-5">
      <div className="flex items-center gap-3 py-4">
        <button
          type="button"
          aria-label={t('Back')}
          onClick={() => navigate('/design/campaign')}
          className="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200"
        >
          <ArrowLeft className="h-5 w-5" />
        </button>
        <h1 className="text-xl font-semibold text-gray-900">
          {t('Edit “{{name}}”', { name: campaign.campaign })}
        </h1>
      </div>

      <div className="flex flex-col gap-6 lg:flex-row">
        <div className="flex flex-1 min-w-0 flex-col">
          <div>
            <nav className="flex">
              {(
                [
                  { key: 'general', label: t('General') },
                  { key: 'reference', label: t('References') },
                ] as const
              ).map(({ key, label }) => (
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

          <div className="flex flex-col gap-4 py-5">
            {activeTab === 'general' && (
              <>
                <TextInput
                  name="name"
                  label={t('Name')}
                  placeholder=" "
                  helpText={t('The Name for this Campaign')}
                  value={draft.name}
                  onChange={(val) => setDraft((prev) => prev && { ...prev, name: val })}
                />

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <DatePickerInput
                    label={t('Start Date')}
                    value={draft.startDt || undefined}
                    showTimePicker
                    helpText={t('Select the start date for this campaign')}
                    error={dateErrors.startDt}
                    onChange={(val) => {
                      setDraft((prev) => prev && { ...prev, startDt: val });
                      setDateErrors((prev) => ({ ...prev, startDt: undefined }));
                    }}
                  />
                  <DatePickerInput
                    label={t('End Date')}
                    value={draft.endDt || undefined}
                    showTimePicker
                    helpText={t('Select the end date for this campaign')}
                    error={dateErrors.endDt}
                    onChange={(val) => {
                      setDraft((prev) => prev && { ...prev, endDt: val });
                      setDateErrors((prev) => ({ ...prev, endDt: undefined }));
                    }}
                  />
                </div>

                <div className="flex flex-col gap-1">
                  <label className="text-sm font-semibold text-gray-500">{t('Displays')}</label>
                  <DisplayGroupMultiSelect
                    value={displayTargets}
                    onChange={setDisplayTargets}
                    triggerClassName="bg-white text-gray-800"
                    helpText={t(
                      'Please select one or more displays / groups for this event to be shown on.',
                    )}
                  />
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <SelectDropdown
                    label={t('Target Type')}
                    helpText={t('How would you like to set the target for this campaign?')}
                    value={draft.targetType}
                    options={[
                      { label: t('Plays'), value: 'plays' },
                      { label: t('Budget'), value: 'budget' },
                      { label: t('Impressions'), value: 'imp' },
                    ]}
                    onSelect={(val) => setDraft((prev) => prev && { ...prev, targetType: val })}
                  />
                  <TextInput
                    name="target"
                    type="number"
                    label={t('Target')}
                    helpText={t(
                      'What is the target number for this Campaign over its entire playtime',
                    )}
                    value={draft.target}
                    onChange={(val) => setDraft((prev) => prev && { ...prev, target: val })}
                  />
                </div>

                <TagInput
                  value={draft.tags}
                  helpText={t(
                    'Tags for this Campaign - Comma separated string of Tags or Tag|Value format. If you choose a Tag that has associated values, they will be shown for selection below.',
                  )}
                  onChange={(tags) => setDraft((prev) => prev && { ...prev, tags })}
                  inputValue={pendingTagInput}
                  onInputChange={setPendingTagInput}
                />
              </>
            )}

            {activeTab === 'reference' && (
              <>
                <p className="text-sm text-gray-800 font-semibold">
                  {t('Add reference fields if needed')}
                </p>
                {(['ref1', 'ref2', 'ref3', 'ref4', 'ref5'] as const).map((ref, i) => (
                  <TextInput
                    key={ref}
                    name={ref}
                    label={t('Reference {{n}}', { n: i + 1 })}
                    placeholder={t('Enter here')}
                    value={draft[ref]}
                    onChange={(val) => setDraft((prev) => prev && { ...prev, [ref]: val })}
                  />
                ))}
              </>
            )}
          </div>
        </div>

        <div className="flex w-full min-w-0 flex-1 flex-col gap-3 p-3 bg-slate-50">
          <div className="rounded-lg border border-slate-200 bg-white p-5">
            <div className="grid grid-cols-2 gap-5 p-5">
              <div className="flex flex-col gap-3">
                <ProgressBar label={t('Time')} pct={timePct} />
                <ProgressBar label={t('Target')} pct={targetPct} />
              </div>
              <div className="flex flex-col gap-2.5 text-sm">
                <Stat label={t('Plays')} value={campaign.plays} />
                <Stat label={t('Spend')} value={campaign.spend} />
                <Stat label={t('Impressions')} value={campaign.impressions} />
              </div>
            </div>
          </div>

          <SelectDropdown
            label={t('Layout')}
            value=""
            options={layoutOptions}
            onSelect={handlePickLayout}
            placeholder={t('Select Layout')}
            helpText={t('Select a Layout to add to this Campaign.')}
            isLoading={isLoadingLayouts}
            onLoadMore={handleLoadMoreLayouts}
            hasMore={hasMoreLayouts}
            isLoadingMore={isLoadingMoreLayouts}
            searchable
            searchPlaceholder={t('Search layouts...')}
            onSearch={setLayoutSearch}
          />

          <DataTable
            columns={layoutColumns}
            data={layoutPageRows}
            enableSelection={false}
            pageCount={layoutPageCount}
            rowCount={sortedLayouts.length}
            pagination={layoutTablePagination}
            onPaginationChange={setLayoutTablePagination}
            sorting={layoutTableSorting}
            onSortingChange={setLayoutTableSorting}
            globalFilter=""
            onGlobalFilterChange={() => {}}
            rowSelection={{}}
            onRowSelectionChange={() => {}}
            loading={removeLayout.isPending}
            viewMode={null}
            hideToolbar
            columnPinning={{ right: ['tableActions'] }}
            getRowId={(row) => String(row.lkCampaignLayoutId)}
            noResultsCustom={
              <div className="px-4 py-6 text-center text-gray-400">
                {t('No layouts assigned yet')}
              </div>
            }
          />

          <div className="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            {t('Stats need to be enabled on the Displays and Layouts selected on this campaign')}
          </div>
        </div>
      </div>

      {/* Footer actions */}
      <div className="mt-6 flex justify-end gap-2 pt-4">
        <Button variant="secondary" onClick={() => navigate('/design/campaign')}>
          {t('Back')}
        </Button>
        <Button variant="primary" disabled={saveGeneral.isPending} onClick={handleSaveGeneral}>
          {saveGeneral.isPending ? t('Saving…') : t('Save')}
        </Button>
      </div>

      {modalLayout && (
        <AddLayoutModal
          isOpen
          layoutName={modalLayout.name}
          initialValues={modalLayout.initialValues}
          isSaving={assignLayout.isPending || removeLayout.isPending || editAssignment.isPending}
          defaultLat={defaultLat}
          defaultLng={defaultLng}
          onClose={() => setModalLayout(null)}
          onSave={handleSaveAssignment}
        />
      )}
    </section>
  );
}

function ProgressBar({ label, pct }: { label: string; pct: number }) {
  return (
    <div className="flex flex-col gap-1">
      <div className="flex items-center justify-between text-xs text-gray-800 font-semibold">
        <span>{label}</span>
      </div>
      <div className="flex flex-row gap-1 items-center">
        <div className="flex-1 h-2.5 overflow-hidden rounded-full bg-gray-100">
          <div className="h-full rounded-full bg-xibo-blue-600" style={{ width: `${pct}%` }} />
        </div>
        <span className={`font-medium ${pct === 100 ? 'text-xibo-blue-600' : 'text-gray-700'}`}>
          {pct}%
        </span>
      </div>
    </div>
  );
}

function Stat({ label, value }: { label: string; value: number }) {
  return (
    <div className="flex items-center justify-between gap-2.5">
      <span className="text-gray-500">{label}:</span>
      <span className="font-medium text-gray-800">{value ?? 0}</span>
    </div>
  );
}
