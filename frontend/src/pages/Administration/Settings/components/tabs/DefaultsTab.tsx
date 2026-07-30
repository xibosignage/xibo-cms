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

import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { SettingsTabProps } from '../../SettingsConfig';
import SettingsSection from '../SettingsSection';

import NumberInput from '@/components/ui/forms/NumberInput';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import type { SelectOption } from '@/components/ui/forms/SelectDropdown';
import SwitchRow from '@/pages/Administration/Users/components/SwitchRow';
import { fetchTransitions } from '@/services/transitionApi';

const PAGE_SIZE = 10;

function useTransitionOptions(filter: { availableAsIn?: number; availableAsOut?: number }) {
  const [options, setOptions] = useState<SelectOption[]>([]);
  const [totalCount, setTotalCount] = useState(0);
  const [isLoading, setIsLoading] = useState(false);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const pageRef = useRef(0);

  useEffect(() => {
    setIsLoading(true);
    pageRef.current = 0;
    fetchTransitions({ start: 0, length: PAGE_SIZE, ...filter })
      .then((res) => {
        setOptions(res.rows.map((tr) => ({ value: tr.code, label: tr.transition })));
        setTotalCount(res.totalCount);
        pageRef.current = 1;
      })
      .catch(() => setOptions([]))
      .finally(() => setIsLoading(false));
  }, []);

  const loadMore = () => {
    if (isLoadingMore || options.length >= totalCount) return;
    setIsLoadingMore(true);
    fetchTransitions({ start: pageRef.current * PAGE_SIZE, length: PAGE_SIZE, ...filter })
      .then((res) => {
        setOptions((prev) => [
          ...prev,
          ...res.rows.map((tr) => ({ value: tr.code, label: tr.transition })),
        ]);
        setTotalCount(res.totalCount);
        pageRef.current += 1;
      })
      .catch(() => {})
      .finally(() => setIsLoadingMore(false));
  };

  return { options, isLoading, isLoadingMore, hasMore: options.length < totalCount, loadMore };
}

export default function DefaultsTab({
  formValues,
  updateField,
  isVisible,
  isEditable,
  relatedEntities,
}: SettingsTabProps) {
  const { t } = useTranslation();
  const transIn = useTransitionOptions({ availableAsIn: 1 });
  const transOut = useTransitionOptions({ availableAsOut: 1 });

  return (
    <div className="flex flex-col gap-3">
      <SettingsSection title={t('Media & Layout')}>
        {isVisible('LIBRARY_MEDIA_UPDATEINALL_CHECKB') && (
          <SwitchRow
            title={t('Default update media in all layouts')}
            description={t(
              'Default the checkbox for updating media on all layouts when editing in the library',
            )}
            checked={formValues.LIBRARY_MEDIA_UPDATEINALL_CHECKB === '1'}
            onChange={(v) => updateField('LIBRARY_MEDIA_UPDATEINALL_CHECKB', v ? '1' : '0')}
            disabled={!isEditable('LIBRARY_MEDIA_UPDATEINALL_CHECKB')}
          />
        )}

        {isVisible('LAYOUT_COPY_MEDIA_CHECKB') && (
          <SwitchRow
            title={t('Default copy media when copying a layout?')}
            description={t(
              'Default the checkbox for making duplicates of media when copying layouts',
            )}
            checked={formValues.LAYOUT_COPY_MEDIA_CHECKB === '1'}
            onChange={(v) => updateField('LAYOUT_COPY_MEDIA_CHECKB', v ? '1' : '0')}
            disabled={!isEditable('LAYOUT_COPY_MEDIA_CHECKB')}
          />
        )}

        {isVisible('LIBRARY_MEDIA_DELETEOLDVER_CHECKB') && (
          <SwitchRow
            title={t(
              'Default for "Delete old version of Media" checkbox. Shown when Editing Library Media.',
            )}
            description={t(
              'Default the checkbox for Deleting Old Version of media when a new file is being uploaded to the library.',
            )}
            checked={formValues.LIBRARY_MEDIA_DELETEOLDVER_CHECKB === '1'}
            onChange={(v) => updateField('LIBRARY_MEDIA_DELETEOLDVER_CHECKB', v ? '1' : '0')}
            disabled={!isEditable('LIBRARY_MEDIA_DELETEOLDVER_CHECKB')}
          />
        )}

        {isVisible('DEFAULT_LAYOUT_AUTO_PUBLISH_CHECKB') && (
          <SwitchRow
            title={t('Should Layouts be automatically Published?')}
            description={t(
              'When enabled draft Layouts will be automatically published 30 minutes after the last edit',
            )}
            checked={formValues.DEFAULT_LAYOUT_AUTO_PUBLISH_CHECKB === '1'}
            onChange={(v) => updateField('DEFAULT_LAYOUT_AUTO_PUBLISH_CHECKB', v ? '1' : '0')}
            disabled={!isEditable('DEFAULT_LAYOUT_AUTO_PUBLISH_CHECKB')}
          />
        )}
      </SettingsSection>
      <SettingsSection title={t('Transitions')}>
        <div className="flex items-center justify-between space-x-4">
          {isVisible('DEFAULT_TRANSITION_IN') && (
            <SelectDropdown
              label={t('Default Transition In')}
              helpText={t('Default Transition In that should be applied to widgets')}
              value={formValues.DEFAULT_TRANSITION_IN ?? ''}
              initialLabel={relatedEntities.defaultTransitionIn?.transition}
              options={transIn.options}
              onSelect={(v) => updateField('DEFAULT_TRANSITION_IN', v)}
              isLoading={transIn.isLoading}
              onLoadMore={transIn.loadMore}
              hasMore={transIn.hasMore}
              isLoadingMore={transIn.isLoadingMore}
              onSearch={() => {}}
              clearable
              className="w-full"
            />
          )}

          {isVisible('DEFAULT_TRANSITION_OUT') && (
            <SelectDropdown
              label={t('Default Transition Out')}
              helpText={t('Default Transition Out that should be applied to widgets')}
              value={formValues.DEFAULT_TRANSITION_OUT ?? ''}
              initialLabel={relatedEntities.defaultTransitionOut?.transition}
              options={transOut.options}
              onSelect={(v) => updateField('DEFAULT_TRANSITION_OUT', v)}
              isLoading={transOut.isLoading}
              onLoadMore={transOut.loadMore}
              hasMore={transOut.hasMore}
              isLoadingMore={transOut.isLoadingMore}
              onSearch={() => {}}
              clearable
              className="w-full"
            />
          )}
        </div>

        {isVisible('DEFAULT_TRANSITION_DURATION') && (
          <NumberInput
            name="DEFAULT_TRANSITION_DURATION"
            label={t('Default Transition duration')}
            helpText={t('Default duration for in and out transitions')}
            value={Number(formValues.DEFAULT_TRANSITION_DURATION) || 0}
            onChange={(v) => updateField('DEFAULT_TRANSITION_DURATION', String(v))}
            disabled={!isEditable('DEFAULT_TRANSITION_DURATION')}
          />
        )}

        {isVisible('DEFAULT_TRANSITION_AUTO_APPLY') && (
          <SwitchRow
            title={t(
              'Default value for "Automatically apply Transitions?." checkbox on Layout add form',
            )}
            checked={formValues.DEFAULT_TRANSITION_AUTO_APPLY === '1'}
            onChange={(v) => updateField('DEFAULT_TRANSITION_AUTO_APPLY', v ? '1' : '0')}
            disabled={!isEditable('DEFAULT_TRANSITION_AUTO_APPLY')}
          />
        )}
      </SettingsSection>
      <SettingsSection title={t('Image Settings')}>
        <div className="flex items-center justify-between space-x-4">
          {isVisible('DEFAULT_RESIZE_THRESHOLD') && (
            <NumberInput
              name="DEFAULT_RESIZE_THRESHOLD"
              label={t('Resize Threshold')}
              helpText={t(
                'The maximum dimensions to be considered when an image is resized, based on the longest side',
              )}
              value={Number(formValues.DEFAULT_RESIZE_THRESHOLD) || 0}
              onChange={(v) => updateField('DEFAULT_RESIZE_THRESHOLD', String(v))}
              disabled={!isEditable('DEFAULT_RESIZE_THRESHOLD')}
            />
          )}

          {isVisible('DEFAULT_RESIZE_LIMIT') && (
            <NumberInput
              name="DEFAULT_RESIZE_LIMIT"
              label={t('Resize Limit')}
              helpText={t(
                'Images that exceed the resize limit, based on the longest side, will not be processed',
              )}
              value={Number(formValues.DEFAULT_RESIZE_LIMIT) || 0}
              onChange={(v) => updateField('DEFAULT_RESIZE_LIMIT', String(v))}
              disabled={!isEditable('DEFAULT_RESIZE_LIMIT')}
            />
          )}
        </div>
      </SettingsSection>
      <SettingsSection title={t('Datasets & playlists')}>
        <div className="flex items-center justify-between space-x-4">
          {isVisible('DATASET_HARD_ROW_LIMIT') && (
            <NumberInput
              name="DATASET_HARD_ROW_LIMIT"
              label={t('DataSet maximum number of Rows')}
              helpText={t(
                'The maximum number of rows per DataSet, once the limit is met the limit policy defined per DataSet will dictate further action.',
              )}
              value={Number(formValues.DATASET_HARD_ROW_LIMIT) || 0}
              onChange={(v) => updateField('DATASET_HARD_ROW_LIMIT', String(v))}
              disabled={!isEditable('DATASET_HARD_ROW_LIMIT')}
            />
          )}

          {isVisible('DEFAULT_PURGE_LIST_TTL') && (
            <NumberInput
              name="DEFAULT_PURGE_LIST_TTL"
              label={t('Default ttl, in days, for records in purge_list table')}
              helpText={t(
                'Entries in purge_list table with expiry date older than specified ttl will be removed.',
              )}
              value={Number(formValues.DEFAULT_PURGE_LIST_TTL) || 0}
              onChange={(v) => updateField('DEFAULT_PURGE_LIST_TTL', String(v))}
              disabled={!isEditable('DEFAULT_PURGE_LIST_TTL')}
            />
          )}
        </div>

        <div className="flex items-center justify-between space-x-4">
          {isVisible('DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER') && (
            <NumberInput
              name="DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER"
              label={t('The default value for max number of items on a new dynamic Playlist')}
              helpText={t(
                'This value can be adjusted on a per dynamic Playlist basis, it cannot exceed value set in the Limit below',
              )}
              value={Number(formValues.DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER) || 0}
              max={Number(formValues.DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER_LIMIT) || 100}
              onChange={(v) => updateField('DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER', String(v))}
              disabled={!isEditable('DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER')}
            />
          )}
          {isVisible('DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER_LIMIT') && (
            <NumberInput
              name="DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER_LIMIT"
              label={t(
                'The default upper limit of items that can be assigned to a dynamic Playlist',
              )}
              helpText={t(
                'When matching Media by Tags and name to a dynamic Playlist, this is the maximum number of allowed items that can be assigned to a dynamic Playlist',
              )}
              value={Number(formValues.DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER_LIMIT) || 0}
              onChange={(v) => updateField('DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER_LIMIT', String(v))}
              disabled={!isEditable('DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER_LIMIT')}
            />
          )}
        </div>
      </SettingsSection>
    </div>
  );
}
