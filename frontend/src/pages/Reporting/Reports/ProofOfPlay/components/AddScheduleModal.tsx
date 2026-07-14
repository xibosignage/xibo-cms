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

import { Equal } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import type { ProofOfPlayFilter } from '../ProofOfPlayConfig';
import { SORT_BY_OPTIONS, TAGS_TYPE_OPTIONS, TYPE_OPTIONS } from '../ProofOfPlayConfig';

import PaginatedMultiSelectDropdown from './PaginatedMultiSelectDropdown';

import Button from '@/components/ui/Button';
import AndOrButton from '@/components/ui/forms/AndOrButton';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import TagInput from '@/components/ui/forms/TagInput';
import ReportScheduleModalShell from '@/pages/Reporting/Reports/shared/ReportScheduleModalShell';
import { useDisplayOptions } from '@/pages/Reporting/Reports/shared/hooks/useDisplayOptions';
import { useLayoutOptions } from '@/pages/Reporting/Reports/shared/hooks/useLayoutOptions';
import { useMediaOptions } from '@/pages/Reporting/Reports/shared/hooks/useMediaOptions';
import type { Tag } from '@/types/tag';

interface AddScheduleModalProps {
  isOpen: boolean;
  currentFilter: ProofOfPlayFilter;
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
  const [layoutId, setLayoutId] = useState<number[]>(currentFilter.layoutId);
  const [mediaId, setMediaId] = useState<number[]>(currentFilter.mediaId);
  const [type, setType] = useState<string>(currentFilter.type);
  const [sortBy, setSortBy] = useState<string>(currentFilter.sortBy);
  const [tagsType, setTagsType] = useState<string>(currentFilter.tagsType);
  const [tags, setTags] = useState<Tag[]>(currentFilter.tags);
  const [exactTags, setExactTags] = useState<boolean>(currentFilter.exactTags);
  const [logicalOperator, setLogicalOperator] = useState<string>(currentFilter.logicalOperator);

  useEffect(() => {
    if (isOpen) {
      setDisplayId(currentFilter.displayId);
      setLayoutId(currentFilter.layoutId);
      setMediaId(currentFilter.mediaId);
      setType(currentFilter.type);
      setSortBy(currentFilter.sortBy);
      setTagsType(currentFilter.tagsType);
      setTags(currentFilter.tags);
      setExactTags(currentFilter.exactTags);
      setLogicalOperator(currentFilter.logicalOperator);
    }
  }, [isOpen]);

  const displaySelect = useDisplayOptions(isOpen);
  const layoutOpts = useLayoutOptions(isOpen);
  const mediaOpts = useMediaOptions(isOpen);

  return (
    <ReportScheduleModalShell
      isOpen={isOpen}
      title={t('Schedule Proof of Play Report')}
      onClose={onClose}
      onSuccess={onSuccess}
      buildPayload={(draft) => ({
        name: draft.name,
        reportName: 'proofofplayReport',
        filter: draft.filter,
        groupByFilter: '',
        displayGroupIds: [],
        displayId,
        fromDt: draft.fromDt || undefined,
        toDt: draft.toDt || undefined,
        sendEmail: draft.sendEmail,
        nonusers: draft.nonusers,
        hiddenFields: {
          type,
          sortBy: sortBy || undefined,
          tagsType,
          tags: tags.length > 0 ? tags.map((tag) => tag.tag).join(',') : undefined,
          exactTags: exactTags ? 1 : 0,
          logicalOperator,
          layoutId: layoutId.length > 0 ? layoutId : undefined,
          mediaId: mediaId.length > 0 ? mediaId : undefined,
        },
      })}
      bottomChildren={
        <>
          {/* Display */}
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

          {/* Layout */}
          <PaginatedMultiSelectDropdown
            label={t('Layout')}
            value={layoutId.map(String)}
            options={layoutOpts.options}
            isLoading={layoutOpts.isLoading}
            isLoadingMore={layoutOpts.isLoadingMore}
            hasMore={layoutOpts.hasMore}
            onSearch={layoutOpts.onSearch}
            onLoadMore={layoutOpts.onLoadMore}
            placeholder={t('All Layouts')}
            showTags
            onChange={(vals) => setLayoutId(vals.map(Number))}
          />

          {/* Media */}
          <PaginatedMultiSelectDropdown
            label={t('Media')}
            value={mediaId.map(String)}
            options={mediaOpts.options}
            isLoading={mediaOpts.isLoading}
            isLoadingMore={mediaOpts.isLoadingMore}
            hasMore={mediaOpts.hasMore}
            onSearch={mediaOpts.onSearch}
            onLoadMore={mediaOpts.onLoadMore}
            placeholder={t('All Media')}
            showTags
            onChange={(vals) => setMediaId(vals.map(Number))}
          />

          {/* Type */}
          <SelectDropdown
            label={t('Type')}
            value={type}
            options={TYPE_OPTIONS.map((o) => ({ value: o.value, label: t(o.label) }))}
            onSelect={(val) => setType(val)}
          />

          {/* Sort By */}
          <SelectDropdown
            label={t('Sort By')}
            value={sortBy}
            options={SORT_BY_OPTIONS.map((o) => ({ value: o.value, label: t(o.label) }))}
            onSelect={(val) => setSortBy(val)}
          />

          {/* Tags From */}
          <SelectDropdown
            label={t('Tags From')}
            value={tagsType}
            options={TAGS_TYPE_OPTIONS.map((o) => ({ value: o.value, label: t(o.label) }))}
            onSelect={(val) => setTagsType(val)}
          />

          {/* Tags with AND/OR prefix and exact-match suffix */}
          <TagInput
            label={t('Tags')}
            value={tags}
            onChange={setTags}
            placeholder={t('Add tags')}
            allowValues={false}
            helpText={t(
              'A comma separated list of tags to filter by. Enter a tag|tag value to filter tags with values. Enter --no-tag to filter all items without tags. Enter - before a tag or tag value to exclude from results.',
            )}
            prefix={
              <AndOrButton
                value={(logicalOperator as 'AND' | 'OR') || 'OR'}
                onChange={(val) => setLogicalOperator(val)}
              />
            }
            suffix={
              <Button
                variant="tertiary"
                leftIcon={Equal}
                ariaLabel={t('Match exact characters only')}
                aria-pressed={exactTags}
                onClick={() => setExactTags((prev) => !prev)}
                className={twMerge(
                  'p-1.5',
                  exactTags
                    ? 'bg-xibo-blue-600 text-white hover:bg-xibo-blue-700 hover:text-white'
                    : 'text-xibo-blue-600 hover:text-xibo-blue-800',
                )}
              />
            }
          />
        </>
      }
    />
  );
}
