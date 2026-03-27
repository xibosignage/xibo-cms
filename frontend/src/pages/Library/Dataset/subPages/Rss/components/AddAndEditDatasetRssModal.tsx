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
import { Minus, Plus } from 'lucide-react';
import { useEffect, useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import Button from '@/components/ui/Button';
import Checkbox from '@/components/ui/forms/Checkbox';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { getDatasetRssSchema } from '@/schema/dataset';
import { createDatasetRss, fetchDatasetColumns, updateDatasetRss } from '@/services/datasetApi';
import type { DatasetRss } from '@/types/datasetRss';

export interface DatasetRssPayload {
  title: string;
  author: string;
  titleColumnId?: number;
  summaryColumnId?: number;
  contentColumnId?: number;
  publishedDateColumnId?: number;
  sort?: string;
  useOrderingClause?: boolean;
  orderClause?: string[];
  orderClauseDirection?: string[];
  filter?: string;
  useFilteringClause?: boolean;
  filterClause?: string[];
  filterClauseOperator?: string[];
  filterClauseCriteria?: string[];
  filterClauseValue?: string[];
  regeneratePsk?: boolean;
}

interface AddAndEditRssModalProps {
  type: 'add' | 'edit';
  isOpen: boolean;
  datasetId: string;
  rss?: DatasetRss | null;
  onClose: () => void;
  onSave: () => void;
}

interface OrderRow {
  column: string;
  direction: string;
}

interface FilterRow {
  operator: string;
  column: string;
  criteria: string;
  value: string;
}

interface ParsedSort {
  useOrderingClause?: number;
  sort?: string;
  orderClauses?: Array<{ orderClause?: string; orderClauseDirection?: string }>;
}

interface ParsedFilter {
  useFilteringClause?: number;
  filter?: string;
  filterClauses?: Array<{
    filterClauseOperator?: string;
    filterClause?: string;
    filterClauseCriteria?: string;
    filterClauseValue?: string;
  }>;
}

const DEFAULT_DRAFT: Partial<DatasetRssPayload> = {
  title: '',
  author: '',
  titleColumnId: undefined,
  summaryColumnId: undefined,
  contentColumnId: undefined,
  publishedDateColumnId: undefined,
  regeneratePsk: false,
  useOrderingClause: false,
  sort: '',
  useFilteringClause: false,
  filter: '',
};

export function AddAndEditDatasetRssModal({
  type,
  isOpen,
  datasetId,
  rss,
  onClose,
  onSave,
}: AddAndEditRssModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();
  const [apiError, setApiError] = useState<string | undefined>();
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [activeTab, setActiveTab] = useState<'general' | 'order' | 'filter'>('general');

  const [draft, setDraft] = useState<Partial<DatasetRssPayload>>({ ...DEFAULT_DRAFT });
  const [orderRows, setOrderRows] = useState<OrderRow[]>([{ column: '', direction: 'ASC' }]);
  const [filterRows, setFilterRows] = useState<FilterRow[]>([
    { operator: 'AND', column: '', criteria: 'starts-with', value: '' },
  ]);

  const { data: columnsResponse } = useQuery({
    queryKey: ['datasetColumns', datasetId],
    queryFn: () => fetchDatasetColumns(datasetId, { start: 0, length: 100 }),
    enabled: isOpen && !!datasetId,
  });

  const columnOptions = [
    { value: '', label: t('Select Column') },
    ...(columnsResponse?.rows.map((c) => ({
      value: String(c.dataSetColumnId),
      label: c.heading,
    })) || []),
  ];

  useEffect(() => {
    if (isOpen) {
      setActiveTab('general');
      setFormErrors({});
      setApiError(undefined);

      if (type === 'edit' && rss) {
        setDraft({
          title: rss.title ?? '',
          author: rss.author ?? '',
          titleColumnId: rss.titleColumnId,
          summaryColumnId: rss.summaryColumnId,
          contentColumnId: rss.contentColumnId,
          publishedDateColumnId: rss.publishedDateColumnId,
          regeneratePsk: false,
        });

        try {
          if (rss.sort) {
            const parsedSort = JSON.parse(rss.sort) as ParsedSort;
            setDraft((prev: Partial<DatasetRssPayload>) => ({
              ...prev,
              useOrderingClause: parsedSort.useOrderingClause === 1,
              sort: parsedSort.sort || '',
            }));

            if (parsedSort.orderClauses && parsedSort.orderClauses.length > 0) {
              setOrderRows(
                parsedSort.orderClauses.map((c) => ({
                  column: c.orderClause || '',
                  direction: c.orderClauseDirection || 'ASC',
                })),
              );
            } else {
              setOrderRows([{ column: '', direction: 'ASC' }]);
            }
          } else {
            setOrderRows([{ column: '', direction: 'ASC' }]);
          }

          if (rss.filter) {
            const parsedFilter = JSON.parse(rss.filter) as ParsedFilter;
            setDraft((prev: Partial<DatasetRssPayload>) => ({
              ...prev,
              useFilteringClause: parsedFilter.useFilteringClause === 1,
              filter: parsedFilter.filter || '',
            }));

            if (parsedFilter.filterClauses && parsedFilter.filterClauses.length > 0) {
              setFilterRows(
                parsedFilter.filterClauses.map((c) => ({
                  operator: c.filterClauseOperator || 'AND',
                  column: c.filterClause || '',
                  criteria: c.filterClauseCriteria || 'starts-with',
                  value: c.filterClauseValue || '',
                })),
              );
            } else {
              setFilterRows([{ operator: 'AND', column: '', criteria: 'starts-with', value: '' }]);
            }
          } else {
            setFilterRows([{ operator: 'AND', column: '', criteria: 'starts-with', value: '' }]);
          }
        } catch (e) {
          console.error(e);
          setOrderRows([{ column: '', direction: 'ASC' }]);
          setFilterRows([{ operator: 'AND', column: '', criteria: 'starts-with', value: '' }]);
        }
      } else {
        setDraft({ ...DEFAULT_DRAFT });
        setOrderRows([{ column: '', direction: 'ASC' }]);
        setFilterRows([{ operator: 'AND', column: '', criteria: 'starts-with', value: '' }]);
      }
    }
  }, [isOpen, type, rss, t]);

  const updateDraft = <K extends keyof DatasetRssPayload>(
    field: K,
    value: DatasetRssPayload[K],
  ) => {
    setDraft((prev: Partial<DatasetRssPayload>) => ({ ...prev, [field]: value }));
    setFormErrors((prev: Record<string, string>) => ({ ...prev, [field]: '' }));
  };

  const handleSave = () => {
    const payload: DatasetRssPayload = {
      ...(draft as DatasetRssPayload),
      orderClause: orderRows.map((r) => r.column),
      orderClauseDirection: orderRows.map((r) => r.direction),
      filterClause: filterRows.map((r) => r.column),
      filterClauseOperator: filterRows.map((r) => r.operator),
      filterClauseCriteria: filterRows.map((r) => r.criteria),
      filterClauseValue: filterRows.map((r) => r.value),
    };

    const schema = getDatasetRssSchema(t);
    const result = schema.safeParse(payload);

    if (!result.success) {
      const fieldErrors = result.error.flatten().fieldErrors;
      const errors: Record<string, string> = {};

      Object.entries(fieldErrors).forEach(([key, val]) => {
        if (val && val.length > 0) {
          errors[key] = val[0] || '';
        }
      });

      setFormErrors(errors);
      setActiveTab('general');
      return;
    }

    setApiError(undefined);

    startTransition(async () => {
      try {
        if (type === 'edit' && rss) {
          await updateDatasetRss(datasetId, rss.id, payload);
        } else {
          await createDatasetRss(datasetId, payload);
        }
        onSave();
        onClose();
      } catch (err: unknown) {
        const apiErr = err as { response?: { data?: { message?: string } } };
        setApiError(apiErr.response?.data?.message || t('An unexpected error occurred.'));
      }
    });
  };

  const getTabClass = (tabName: string) => {
    const isActive = activeTab === tabName;
    return `py-2 px-3 inline-flex items-center gap-2 border-b-2 text-sm font-semibold whitespace-nowrap focus:outline-none transition-all ${
      isActive
        ? 'border-blue-600 text-blue-500'
        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
    }`;
  };

  return (
    <Modal
      title={type === 'add' ? t('Add RSS') : t('Edit RSS')}
      onClose={onClose}
      isOpen={isOpen}
      isPending={isPending}
      scrollable={false}
      error={apiError}
      size="lg"
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary', disabled: isPending },
        { label: isPending ? t('Saving…') : t('Save'), onClick: handleSave, disabled: isPending },
      ]}
    >
      <div className="flex flex-col h-full overflow-y-hidden overflow-x-visible px-4">
        <nav
          className="flex px-4 overflow-x-auto shrink-0 border-b border-gray-200"
          aria-label="Tabs"
        >
          <button
            type="button"
            className={getTabClass('general')}
            onClick={() => setActiveTab('general')}
          >
            {t('General')}
          </button>
          <button
            type="button"
            className={getTabClass('order')}
            onClick={() => setActiveTab('order')}
          >
            {t('Order')}
          </button>
          <button
            type="button"
            className={getTabClass('filter')}
            onClick={() => setActiveTab('filter')}
          >
            {t('Filter')}
          </button>
        </nav>

        <div className="flex-1 overflow-y-auto p-4 space-y-5">
          {activeTab === 'general' && (
            <div className="space-y-4">
              <TextInput
                name="title"
                label={t('Title')}
                placeholder={t('Enter title')}
                value={draft.title || ''}
                error={formErrors.title}
                onChange={(val: string) => updateDraft('title', val)}
              />
              <TextInput
                name="author"
                label={t('Author')}
                placeholder={t('Enter author')}
                value={draft.author || ''}
                error={formErrors.author}
                onChange={(val: string) => updateDraft('author', val)}
              />

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <SelectDropdown
                  label={t('Title Column')}
                  options={columnOptions}
                  value={String(draft.titleColumnId || '')}
                  onSelect={(val: string) => {
                    updateDraft('titleColumnId', val ? Number(val) : undefined);
                  }}
                />
                <SelectDropdown
                  label={t('Summary Column')}
                  options={columnOptions}
                  value={String(draft.summaryColumnId || '')}
                  onSelect={(val: string) => {
                    updateDraft('summaryColumnId', val ? Number(val) : undefined);
                  }}
                />
                <SelectDropdown
                  label={t('Content Column')}
                  options={columnOptions}
                  value={String(draft.contentColumnId || '')}
                  onSelect={(val: string) => {
                    updateDraft('contentColumnId', val ? Number(val) : undefined);
                  }}
                />
                <SelectDropdown
                  label={t('Published Date Column')}
                  options={columnOptions}
                  value={String(draft.publishedDateColumnId || '')}
                  onSelect={(val: string) => {
                    updateDraft('publishedDateColumnId', val ? Number(val) : undefined);
                  }}
                />
              </div>

              {type === 'edit' && (
                <div className="flex flex-col gap-3 p-5 rounded-xl border border-gray-100 bg-gray-50 mt-4">
                  <Checkbox
                    id="regeneratePsk"
                    title={t('Security')}
                    label={t('Tick this box if you want to generate a new URL for this RSS feed.')}
                    checked={draft.regeneratePsk || false}
                    onChange={(e) => updateDraft('regeneratePsk', e.target.checked)}
                  />
                </div>
              )}
            </div>
          )}

          {activeTab === 'order' && (
            <div className="space-y-4">
              <p className="text-sm text-gray-500">
                {t(
                  "Order Datasets by any column below. Use the '+' icon to add fields, or check 'Advanced' to enter custom SQL syntax.",
                )}
              </p>

              {!draft.useOrderingClause ? (
                <div className="bg-gray-50 rounded-xl p-5 border border-gray-100 flex flex-col gap-3">
                  <div className="flex gap-3 px-1 mb-1">
                    <div className="w-10"></div>
                    <div className="flex-1 text-sm font-semibold text-gray-600">{t('Column')}</div>
                    <div className="w-40 text-sm font-semibold text-gray-600">{t('Direction')}</div>
                    <div className="w-11"></div>
                  </div>

                  {orderRows.map((row, index) => (
                    <div key={index} className="flex items-start gap-3">
                      <div className="w-10 h-11.25 bg-gray-100 rounded-lg flex items-center justify-center font-bold text-gray-700 shrink-0">
                        {index + 1}
                      </div>

                      <div className="flex-1">
                        <SelectDropdown
                          label=""
                          options={columnOptions}
                          value={row.column}
                          onSelect={(val: string) => {
                            setOrderRows((prev) =>
                              prev.map((r, i) => (i === index ? { ...r, column: val } : r)),
                            );
                          }}
                        />
                      </div>
                      <div className="w-40">
                        <SelectDropdown
                          label=""
                          options={[
                            { value: 'ASC', label: t('Ascending') },
                            { value: 'DESC', label: t('Descending') },
                          ]}
                          value={row.direction}
                          onSelect={(val: string) => {
                            setOrderRows((prev) =>
                              prev.map((r, i) => (i === index ? { ...r, direction: val } : r)),
                            );
                          }}
                        />
                      </div>
                      <Button
                        variant="secondary"
                        className="w-11 h-11.25 p-0 shrink-0 flex items-center justify-center bg-gray-100 border-transparent hover:bg-gray-200 hover:border-gray-200 transition-colors"
                        onClick={() => {
                          if (index === 0) {
                            setOrderRows((prev) => [...prev, { column: '', direction: 'ASC' }]);
                          } else {
                            setOrderRows((prev) => prev.filter((_, i) => i !== index));
                          }
                        }}
                      >
                        {index === 0 ? (
                          <Plus size={18} className="text-gray-600" />
                        ) : (
                          <Minus size={18} className="text-gray-600" />
                        )}
                      </Button>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="bg-gray-50 rounded-xl p-5 border border-gray-100">
                  <TextInput
                    name="sort"
                    label={t('Order (SQL)')}
                    placeholder="e.g. column_name DESC"
                    value={draft.sort || ''}
                    onChange={(val: string) => updateDraft('sort', val)}
                  />
                </div>
              )}

              <div className="flex flex-col p-5 rounded-xl bg-gray-50 border border-gray-100">
                <Checkbox
                  id="useOrderingClause"
                  title={t('Advanced Order Clause')}
                  label={t('Provide a custom clause instead of using the clause builder above.')}
                  checked={draft.useOrderingClause || false}
                  onChange={(e) => updateDraft('useOrderingClause', e.target.checked)}
                />
              </div>
            </div>
          )}

          {activeTab === 'filter' && (
            <div className="space-y-4">
              <p className="text-sm text-gray-500">
                {t(
                  "Filter Datasets by any column below. Use the '+' icon to add fields, or check 'Advanced' to enter custom SQL syntax.",
                )}
              </p>

              {!draft.useFilteringClause ? (
                <div className="bg-gray-50 rounded-xl p-5 border border-gray-100 flex flex-col gap-3">
                  <div className="flex gap-3 px-1 mb-1">
                    <div className="w-10"></div>
                    {filterRows.length > 1 && <div className="w-24"></div>}
                    <div className="flex-1 text-sm font-semibold text-gray-600">{t('Column')}</div>
                    <div className="flex-2 text-sm font-semibold text-gray-600">
                      {t('Condition')}
                    </div>
                    <div className="w-11"></div>
                  </div>

                  {filterRows.map((row, index) => (
                    <div key={index} className="flex items-start gap-3">
                      <div className="w-10 h-11.25 bg-gray-100 rounded-lg flex items-center justify-center font-bold text-gray-700 shrink-0">
                        {index + 1}
                      </div>

                      {index > 0 && (
                        <div className="w-24 shrink-0">
                          <SelectDropdown
                            label=""
                            options={[
                              { value: 'AND', label: t('And') },
                              { value: 'OR', label: t('Or') },
                            ]}
                            value={row.operator}
                            onSelect={(val: string) => {
                              setFilterRows((prev) =>
                                prev.map((r, i) => (i === index ? { ...r, operator: val } : r)),
                              );
                            }}
                          />
                        </div>
                      )}

                      {index === 0 && filterRows.length > 1 && <div className="w-24 shrink-0" />}

                      <div className="flex-1 min-w-37.5">
                        <SelectDropdown
                          label=""
                          options={columnOptions}
                          value={row.column}
                          onSelect={(val: string) => {
                            setFilterRows((prev) =>
                              prev.map((r, i) => (i === index ? { ...r, column: val } : r)),
                            );
                          }}
                        />
                      </div>
                      <div className="flex-2 flex gap-2 min-w-62.5">
                        <div className="w-2/5">
                          <SelectDropdown
                            label=""
                            options={[
                              { value: 'equals', label: t('Equals') },
                              { value: 'starts-with', label: t('Starts With') },
                              { value: 'contains', label: t('Contains') },
                              { value: 'greater-than', label: t('Greater Than') },
                              { value: 'less-than', label: t('Less Than') },
                            ]}
                            value={row.criteria}
                            onSelect={(val: string) => {
                              setFilterRows((prev) =>
                                prev.map((r, i) => (i === index ? { ...r, criteria: val } : r)),
                              );
                            }}
                          />
                        </div>
                        <div className="flex-1">
                          <TextInput
                            name={`filterVal_${index}`}
                            placeholder={t('Value')}
                            value={row.value}
                            onChange={(val: string) => {
                              setFilterRows((prev) =>
                                prev.map((r, i) => (i === index ? { ...r, value: val } : r)),
                              );
                            }}
                          />
                        </div>
                      </div>
                      <Button
                        variant="secondary"
                        className="w-11 h-11.25 p-0 shrink-0 flex items-center justify-center bg-gray-100 border-transparent hover:bg-gray-200 hover:border-gray-200 transition-colors"
                        onClick={() => {
                          if (index === 0) {
                            setFilterRows((prev) => [
                              ...prev,
                              { operator: 'AND', column: '', criteria: 'starts-with', value: '' },
                            ]);
                          } else {
                            setFilterRows((prev) => prev.filter((_, i) => i !== index));
                          }
                        }}
                      >
                        {index === 0 ? (
                          <Plus size={18} className="text-gray-600" />
                        ) : (
                          <Minus size={18} className="text-gray-600" />
                        )}
                      </Button>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="bg-gray-50 rounded-xl p-5 border border-gray-100">
                  <TextInput
                    name="filter"
                    label={t('Filter (SQL)')}
                    placeholder="e.g. column_name = 'value'"
                    value={draft.filter || ''}
                    onChange={(val: string) => updateDraft('filter', val)}
                  />
                </div>
              )}

              <div className="flex flex-col p-5 rounded-xl bg-gray-50 border border-gray-100">
                <Checkbox
                  id="useFilteringClause"
                  title={t('Advanced Filter Clause')}
                  label={t('Provide a custom clause instead of using the clause builder above.')}
                  checked={draft.useFilteringClause || false}
                  onChange={(e) => updateDraft('useFilteringClause', e.target.checked)}
                />
              </div>
            </div>
          )}
        </div>
      </div>
    </Modal>
  );
}
