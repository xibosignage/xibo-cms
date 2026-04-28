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

import { useQuery } from '@tanstack/react-query';
import type { ColumnDef, PaginationState, SortingState } from '@tanstack/react-table';
import { useEffect, useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import Checkbox from '@/components/ui/forms/Checkbox';
import SelectFolder from '@/components/ui/forms/SelectFolder';
import TagInput from '@/components/ui/forms/TagInput';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { DataTable } from '@/components/ui/table/DataTable';
import { CheckMarkCell, TagsCell, TextCell } from '@/components/ui/table/cells';
import { useDebounce } from '@/hooks/useDebounce';
import { getDisplayGroupSchema } from '@/schema/displayGroup';
import { createDisplayGroup, updateDisplayGroup } from '@/services/displayGroupApi';
import { fetchDisplays } from '@/services/displaysApi';
import type { Display } from '@/types/display';
import type { DisplayGroup } from '@/types/displayGroup';
import type { Tag } from '@/types/tag';

interface AddAndEditDisplayGroupModalProps {
  type: 'add' | 'edit';
  isOpen?: boolean;
  data?: DisplayGroup | null;
  onClose: () => void;
  onSave: (updated: DisplayGroup) => void;
}

interface DraftState {
  displayGroup: string;
  description: string;
  folderId: number | null;
  tags: Tag[];
  isDynamic: boolean;
  dynamicCriteria: string;
  logicalOperatorName: 'OR' | 'AND';
  dynamicCriteriaTags: string;
  exactTags: boolean;
  logicalOperator: 'OR' | 'AND';
  ref1: string;
  ref2: string;
  ref3: string;
  ref4: string;
  ref5: string;
}

type DisplayGroupFormErrors = Partial<Record<keyof DraftState, string>>;

const DEFAULT_DRAFT: DraftState = {
  displayGroup: '',
  description: '',
  folderId: null,
  tags: [],
  isDynamic: false,
  dynamicCriteria: '',
  logicalOperatorName: 'OR',
  dynamicCriteriaTags: '',
  exactTags: false,
  logicalOperator: 'OR',
  ref1: '',
  ref2: '',
  ref3: '',
  ref4: '',
  ref5: '',
};

function draftFromDisplayGroup(dg: DisplayGroup): DraftState {
  return {
    displayGroup: dg.displayGroup,
    description: dg.description ?? '',
    folderId: dg.folderId || null,
    tags: dg.tags?.map((t) => ({ ...t })) ?? [],
    isDynamic: dg.isDynamic === 1,
    dynamicCriteria: dg.dynamicCriteria ?? '',
    logicalOperatorName: (dg.dynamicCriteriaLogicalOperator as 'OR' | 'AND') || 'OR',
    dynamicCriteriaTags: dg.dynamicCriteriaTags ?? '',
    exactTags: dg.dynamicCriteriaExactTags === 1,
    logicalOperator: (dg.dynamicCriteriaTagsLogicalOperator as 'OR' | 'AND') || 'OR',
    ref1: dg.ref1 ?? '',
    ref2: dg.ref2 ?? '',
    ref3: dg.ref3 ?? '',
    ref4: dg.ref4 ?? '',
    ref5: dg.ref5 ?? '',
  };
}

export default function AddAndEditDisplayGroupModal({
  type,
  isOpen = true,
  data,
  onClose,
  onSave,
}: AddAndEditDisplayGroupModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();
  const [activeTab, setActiveTab] = useState<'general' | 'reference'>('general');
  const [draft, setDraft] = useState<DraftState>(() => {
    if (type === 'edit' && data) {
      return draftFromDisplayGroup(data);
    }
    return { ...DEFAULT_DRAFT };
  });
  const [formErrors, setFormErrors] = useState<DisplayGroupFormErrors>({});
  const [apiError, setApiError] = useState<string | undefined>();

  const [previewPagination, setPreviewPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: 5,
  });
  const [previewSorting, setPreviewSorting] = useState<SortingState>([]);

  const debouncedCriteria = useDebounce(draft.dynamicCriteria, 500);
  const hasActiveFilters =
    draft.isDynamic && (debouncedCriteria.trim() !== '' || draft.dynamicCriteriaTags.trim() !== '');

  const { data: previewQueryData, isFetching: isFetchingPreview } = useQuery({
    queryKey: [
      'displays',
      'dgPreview',
      { criteria: debouncedCriteria, pagination: previewPagination },
    ],
    queryFn: () =>
      fetchDisplays({
        start: previewPagination.pageIndex * previewPagination.pageSize,
        length: previewPagination.pageSize,
        keyword: debouncedCriteria || undefined,
      }),
    enabled: hasActiveFilters,
    staleTime: 1000 * 30,
  });

  const previewRows = previewQueryData?.rows ?? [];
  const previewPageCount = Math.ceil(
    (previewQueryData?.totalCount ?? 0) / previewPagination.pageSize,
  );

  useEffect(() => {
    if (type === 'edit' && data) {
      setDraft(draftFromDisplayGroup(data));
    } else {
      setDraft({ ...DEFAULT_DRAFT });
    }
  }, [data, type]);

  const handleSave = () => {
    startTransition(async () => {
      const schema = getDisplayGroupSchema(t);
      const result = schema.safeParse(draft);

      if (!result.success) {
        setApiError(undefined);
        const fieldErrors = result.error.flatten().fieldErrors;
        const mappedErrors: DisplayGroupFormErrors = {};

        Object.entries(fieldErrors).forEach(([key, value]) => {
          if (value?.[0]) {
            mappedErrors[key as keyof DisplayGroupFormErrors] = value[0];
          }
        });

        setFormErrors(mappedErrors);

        if (mappedErrors.displayGroup || mappedErrors.description) {
          setActiveTab('general');
        }
        return;
      }

      setFormErrors({});

      try {
        const serializedTags = draft.tags
          .map((tag) =>
            tag.value != null && tag.value !== '' ? `${tag.tag}|${tag.value}` : tag.tag,
          )
          .join(',');

        const payload = {
          displayGroup: draft.displayGroup.trim(),
          description: draft.description || undefined,
          tags: serializedTags || undefined,
          isDynamic: draft.isDynamic ? 1 : 0,
          ...(draft.isDynamic && {
            dynamicCriteria: draft.dynamicCriteria || undefined,
            logicalOperatorName: draft.logicalOperatorName,
            dynamicCriteriaTags: draft.dynamicCriteriaTags || undefined,
            exactTags: draft.exactTags ? 1 : 0,
            logicalOperator: draft.logicalOperator,
          }),
          folderId: draft.folderId,
        };

        if (type === 'edit') {
          if (!data) {
            console.error('Display group data is missing.');
            return;
          }

          const updatedDisplayGroup = await updateDisplayGroup(data.displayGroupId, {
            ...payload,
            ref1: draft.ref1 || undefined,
            ref2: draft.ref2 || undefined,
            ref3: draft.ref3 || undefined,
            ref4: draft.ref4 || undefined,
            ref5: draft.ref5 || undefined,
          });

          onSave({ ...data, ...updatedDisplayGroup });
        } else {
          const newDisplayGroup = await createDisplayGroup(payload);
          onSave(newDisplayGroup);
        }
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

  const previewColumns: ColumnDef<Display>[] = [
    {
      accessorKey: 'displayId',
      header: t('ID'),
      size: 70,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'display',
      header: t('Display'),
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'tags',
      header: t('Tags'),
      size: 140,
      enableSorting: false,
      cell: ({ row }) => (
        <TagsCell
          tags={(row.original.tags ?? []).map((tag) => ({ id: tag.tagId, label: tag.tag }))}
        />
      ),
    },
    {
      accessorKey: 'loggedIn',
      header: t('Status'),
      size: 100,
      cell: (info) => (
        <TextCell className={info.getValue<number>() ? 'text-green-600' : 'text-gray-400'}>
          {info.getValue<number>() ? t('Online') : t('Offline')}
        </TextCell>
      ),
    },
    {
      accessorKey: 'licensed',
      header: t('Licence'),
      size: 90,
      cell: (info) => <CheckMarkCell active={info.getValue<number>() === 1} />,
    },
  ];

  const actions = [
    {
      label: t('Cancel'),
      onClick: onClose,
      variant: 'secondary' as const,
      disabled: isPending,
    },
    {
      label: isPending ? t('Saving…') : t('Save'),
      onClick: handleSave,
      disabled: isPending,
    },
  ];

  if (!isOpen) return null;

  return (
    <Modal
      isOpen={isOpen}
      title={type === 'edit' ? t('Edit Display Group') : t('Add Display Group')}
      onClose={onClose}
      size="lg"
      isPending={isPending}
      error={apiError}
      scrollable={false}
      actions={actions}
    >
      {/* Tab bar */}
      <div className="flex flex-col h-full overflow-y-hidden overflow-x-visible px-6">
        <nav className="flex overflow-x-auto border-b border-gray-200" aria-label="Tabs">
          {(['general', 'reference'] as const).map((tab) => (
            <button
              key={tab}
              type="button"
              onClick={() => setActiveTab(tab)}
              className={`py-2 px-3 inline-flex items-center gap-2 border-b-2 text-sm font-semibold whitespace-nowrap focus:outline-none transition-all  ${
                activeTab === tab
                  ? 'border-blue-600 text-blue-500'
                  : 'border-gray-200 text-gray-500 hover:text-blue-600'
              }`}
            >
              {tab === 'general' ? t('General') : t('Reference')}
            </button>
          ))}
        </nav>
      </div>

      <div className="flex flex-col flex-1 min-h-0 overflow-y-auto gap-3 p-6">
        {activeTab === 'general' && (
          <>
            {/* Folder */}
            <div className="relative z-20">
              <SelectFolder
                selectedId={draft.folderId}
                onSelect={(folder) =>
                  setDraft((prev) => ({ ...prev, folderId: folder?.id ?? null }))
                }
              />
            </div>

            {/* Name */}
            <TextInput
              name="displayGroup"
              label={t('Name')}
              placeholder={t('Enter name')}
              value={draft.displayGroup}
              onChange={(val) => setDraft((prev) => ({ ...prev, displayGroup: val }))}
              error={formErrors.displayGroup}
            />

            {/* Description */}
            <TextInput
              name="description"
              label={t('Description')}
              placeholder={t('Enter description')}
              value={draft.description}
              multiline
              rows={3}
              onChange={(val) => setDraft((prev) => ({ ...prev, description: val }))}
              error={formErrors.description}
            />

            {/* Tags */}
            <TagInput
              value={draft.tags}
              helpText={t('Tags (Comma-separated: Tag or Tag|Value)')}
              onChange={(tags) => setDraft((prev) => ({ ...prev, tags }))}
            />

            {/* Dynamic Group */}
            <div className="p-3 flex flex-col gap-3 bg-slate-50 rounded-lg">
              <Checkbox
                id="isDynamic"
                className="px-3 py-2.5 gap-1 items-start"
                title={t('Dynamic Group')}
                label={t('Use criteria to automatically assign displays to this group.')}
                checked={draft.isDynamic}
                classNameLabel="text-xs"
                onChange={() => setDraft((prev) => ({ ...prev, isDynamic: !prev.isDynamic }))}
              />

              {draft.isDynamic && (
                <>
                  {/* Criteria */}
                  <TextInput
                    name="dynamicCriteria"
                    label={t('Criteria')}
                    placeholder={t('Enter criteria')}
                    value={draft.dynamicCriteria}
                    onChange={(val) => setDraft((prev) => ({ ...prev, dynamicCriteria: val }))}
                    suffix={
                      <select
                        className="bg-transparent text-sm font-semibold text-gray-500 border-none focus:ring-0 cursor-pointer p-3 pr-8"
                        value={draft.logicalOperatorName}
                        onChange={(e) =>
                          setDraft((prev) => ({
                            ...prev,
                            logicalOperatorName: e.target.value as 'OR' | 'AND',
                          }))
                        }
                      >
                        <option value="AND">AND</option>
                        <option value="OR">OR</option>
                      </select>
                    }
                  />

                  {/* Criteria Tags */}
                  <TextInput
                    name="dynamicCriteriaTags"
                    label={t('Criteria Tags')}
                    placeholder={t('Enter criteria tags')}
                    value={draft.dynamicCriteriaTags}
                    onChange={(val) => setDraft((prev) => ({ ...prev, dynamicCriteriaTags: val }))}
                    suffix={
                      <div className="flex items-stretch">
                        <label className="flex items-center gap-1.5 px-3 text-sm text-gray-500 border-gray-200 cursor-pointer border-e">
                          <input
                            type="checkbox"
                            title={t('Exact')}
                            className="shrink-0 mt-0.5 border-gray-200 rounded text-blue-600 focus:ring-blue-500"
                            checked={draft.exactTags}
                            onChange={(e) =>
                              setDraft((prev) => ({ ...prev, exactTags: e.target.checked }))
                            }
                          />
                        </label>
                        <select
                          className="bg-transparent text-sm font-semibold text-gray-500 border-none focus:ring-0 cursor-pointer p-3 pr-8"
                          value={draft.logicalOperator}
                          onChange={(e) =>
                            setDraft((prev) => ({
                              ...prev,
                              logicalOperator: e.target.value as 'OR' | 'AND',
                            }))
                          }
                        >
                          <option value="AND">AND</option>
                          <option value="OR">OR</option>
                        </select>
                      </div>
                    }
                  />

                  {/* Displays preview */}
                  {hasActiveFilters && (
                    <div className="flex flex-col gap-2 animate-in fade-in slide-in-from-top-2 duration-300">
                      <p className="text-xs font-medium text-gray-500">{t('Matching Displays')}</p>
                      <div className="flex flex-col overflow-hidden">
                        <DataTable
                          columns={previewColumns}
                          data={previewRows}
                          pageCount={previewPageCount}
                          pagination={previewPagination}
                          onPaginationChange={setPreviewPagination}
                          sorting={previewSorting}
                          onSortingChange={setPreviewSorting}
                          globalFilter=""
                          onGlobalFilterChange={() => {}}
                          rowSelection={{}}
                          onRowSelectionChange={() => {}}
                          loading={isFetchingPreview}
                          enableSelection={false}
                          hideToolbar={true}
                        />
                      </div>
                    </div>
                  )}
                </>
              )}
            </div>
          </>
        )}

        {activeTab === 'reference' && (
          <div className="bg-slate-50 p-5 gap-5 flex flex-col">
            <span className="font-bold">{t('Add reference fields if needed')}</span>
            {([1, 2, 3, 4, 5] as const).map((n) => {
              const key = `ref${n}` as 'ref1' | 'ref2' | 'ref3' | 'ref4' | 'ref5';
              return (
                <TextInput
                  key={key}
                  name={key}
                  label={t('Reference {{n}}', { n })}
                  placeholder={t('Enter reference {{n}}', { n })}
                  value={draft[key]}
                  onChange={(val) => setDraft((prev) => ({ ...prev, [key]: val }))}
                />
              );
            })}
          </div>
        )}
      </div>
    </Modal>
  );
}
