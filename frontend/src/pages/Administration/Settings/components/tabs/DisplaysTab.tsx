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
import SelectFolder from '@/components/ui/forms/SelectFolder';
import TextInput from '@/components/ui/forms/TextInput';
import { useDebounce } from '@/hooks/useDebounce';
import SwitchRow from '@/pages/Administration/Users/components/SwitchRow';
import { fetchLayouts } from '@/services/layoutsApi';

const LAYOUT_PAGE_SIZE = 10;

function useLayoutOptions() {
  const [options, setOptions] = useState<SelectOption[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [hasMore, setHasMore] = useState(false);
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounce(search, 300);
  const pageRef = useRef(0);

  useEffect(() => {
    setIsLoading(true);
    setOptions([]);
    pageRef.current = 0;
    fetchLayouts({
      start: 0,
      length: LAYOUT_PAGE_SIZE,
      retired: 0,
      layout: debouncedSearch || undefined,
    })
      .then((res) => {
        setOptions(res.rows.map((l) => ({ value: String(l.layoutId), label: l.layout })));
        setHasMore(res.rows.length === LAYOUT_PAGE_SIZE);
        pageRef.current = 1;
      })
      .catch(() => setOptions([]))
      .finally(() => setIsLoading(false));
  }, [debouncedSearch]);

  const loadMore = () => {
    if (isLoadingMore || !hasMore) return;
    setIsLoadingMore(true);
    fetchLayouts({
      start: pageRef.current * LAYOUT_PAGE_SIZE,
      length: LAYOUT_PAGE_SIZE,
      retired: 0,
      layout: debouncedSearch || undefined,
    })
      .then((res) => {
        setOptions((prev) => [
          ...prev,
          ...res.rows.map((l) => ({ value: String(l.layoutId), label: l.layout })),
        ]);
        setHasMore(res.rows.length === LAYOUT_PAGE_SIZE);
        pageRef.current += 1;
      })
      .catch(() => {})
      .finally(() => setIsLoadingMore(false));
  };

  return { options, isLoading, isLoadingMore, hasMore, loadMore, setSearch };
}

export default function DisplaysTab({
  formValues,
  updateField,
  isVisible,
  isEditable,
  relatedEntities,
}: SettingsTabProps) {
  const { t } = useTranslation();

  const layoutOptions = useLayoutOptions();

  return (
    <div className="flex flex-col gap-3">
      <SettingsSection title={t('Setup')}>
        {isVisible('DEFAULT_LAYOUT') && (
          <SelectDropdown
            label={t('Default Layout')}
            helpText={t(
              'The default layout to assign for new displays and displays which have their current default deleted.',
            )}
            value={formValues.DEFAULT_LAYOUT ?? ''}
            initialLabel={relatedEntities.defaultLayout?.layout}
            options={layoutOptions.options}
            onSelect={(v) => updateField('DEFAULT_LAYOUT', v ?? '')}
            isLoading={layoutOptions.isLoading}
            onLoadMore={layoutOptions.loadMore}
            hasMore={layoutOptions.hasMore}
            isLoadingMore={layoutOptions.isLoadingMore}
            searchable
            searchPlaceholder={t('Search layouts...')}
            onSearch={layoutOptions.setSearch}
          />
        )}
        {isVisible('DISPLAY_DEFAULT_FOLDER') && (
          <SelectFolder
            selectedId={Number(formValues.DISPLAY_DEFAULT_FOLDER) || null}
            onSelect={(folder) =>
              updateField('DISPLAY_DEFAULT_FOLDER', folder ? String(folder.id) : '')
            }
          />
        )}
        {isVisible('MAX_LICENSED_DISPLAYS') && (
          <NumberInput
            name="MAX_LICENSED_DISPLAYS"
            label={t('Number of display slots')}
            helpText={t(
              'The maximum number of licensed Players for this server installation. 0 = unlimited',
            )}
            value={Number(formValues.MAX_LICENSED_DISPLAYS) || 0}
            onChange={(v) => updateField('MAX_LICENSED_DISPLAYS', String(v))}
            disabled={!isEditable('MAX_LICENSED_DISPLAYS')}
          />
        )}
        {isVisible('DISPLAY_AUTO_AUTH') && (
          <SwitchRow
            title={t('Automatically authorise new Displays?')}
            description={t(
              'If checked all new Displays registering with the CMS using the correct CMS key will automatically be set to authorised and display the Default Layout.',
            )}
            checked={formValues.DISPLAY_AUTO_AUTH === '1'}
            onChange={(v) => updateField('DISPLAY_AUTO_AUTH', v ? '1' : '0')}
            disabled={!isEditable('DISPLAY_AUTO_AUTH')}
          />
        )}
      </SettingsSection>
      <SettingsSection title={t('XMR push messaging')}>
        <div className="flex items-start justify-between space-x-4">
          {isVisible('XMR_ADDRESS') && (
            <TextInput
              name="XMR_ADDRESS"
              label={t('Private Address')}
              helpText={t('Enter the private address for XMR.')}
              value={formValues.XMR_ADDRESS ?? ''}
              onChange={(v) => updateField('XMR_ADDRESS', v)}
              disabled={!isEditable('XMR_ADDRESS')}
            />
          )}

          {isVisible('XMR_WS_ADDRESS') && (
            <TextInput
              name="XMR_WS_ADDRESS"
              label={t('WebSocket Address')}
              helpText={t(
                'Enter the WebSocket address for XMR. Leaving this empty will mean the Player app connects to /xmr',
              )}
              value={formValues.XMR_WS_ADDRESS ?? ''}
              onChange={(v) => updateField('XMR_WS_ADDRESS', v)}
              disabled={!isEditable('XMR_WS_ADDRESS')}
            />
          )}

          {isVisible('XMR_PUB_ADDRESS') && (
            <TextInput
              name="XMR_PUB_ADDRESS"
              label={t('Public Address')}
              helpText={t('Enter the public address for XMR.')}
              value={formValues.XMR_PUB_ADDRESS ?? ''}
              onChange={(v) => updateField('XMR_PUB_ADDRESS', v)}
              disabled={!isEditable('XMR_PUB_ADDRESS')}
            />
          )}
        </div>
      </SettingsSection>
      <SettingsSection title={t('Geo-aware preview coordinates')}>
        <div className="flex items-start justify-between space-x-4">
          {isVisible('DEFAULT_LAT') && (
            <TextInput
              name="DEFAULT_LAT"
              label={t('Default Latitude')}
              helpText={t('The Latitude to apply for any Geo aware Previews')}
              value={formValues.DEFAULT_LAT ?? ''}
              onChange={(v) => updateField('DEFAULT_LAT', v)}
              disabled={!isEditable('DEFAULT_LAT')}
            />
          )}
          {isVisible('DEFAULT_LONG') && (
            <TextInput
              name="DEFAULT_LONG"
              label={t('Default Longitude')}
              helpText={t('The longitude to apply for any Geo aware Previews')}
              value={formValues.DEFAULT_LONG ?? ''}
              onChange={(v) => updateField('DEFAULT_LONG', v)}
              disabled={!isEditable('DEFAULT_LONG')}
            />
          )}
        </div>
      </SettingsSection>
      <SettingsSection title={t('Proof of Play Statistics')}>
        {isVisible('DISPLAY_PROFILE_AGGREGATION_LEVEL_DEFAULT') && (
          <SelectDropdown
            label={t('Aggregation level')}
            helpText={t(
              'Set the Default setting to use for the level of collection for Proof of Play Statistics to be applied to Layouts / Media and Widget items.',
            )}
            value={formValues.DISPLAY_PROFILE_AGGREGATION_LEVEL_DEFAULT ?? ''}
            options={[
              { value: 'Individual', label: t('Individual') },
              { value: 'Hourly', label: t('Hourly') },
              { value: 'Daily', label: t('Daily') },
            ]}
            onSelect={(v) => updateField('DISPLAY_PROFILE_AGGREGATION_LEVEL_DEFAULT', v)}
          />
        )}
        {isVisible('DISPLAY_PROFILE_STATS_DEFAULT') && (
          <SwitchRow
            title={t('Enable Stats Collection?')}
            description={t(
              'Set the Default Settings for Proof of Play statistics to apply to all Displays. This can be toggled off by using Display Profiles.',
            )}
            checked={formValues.DISPLAY_PROFILE_STATS_DEFAULT === '1'}
            onChange={(v) => updateField('DISPLAY_PROFILE_STATS_DEFAULT', v ? '1' : '0')}
            disabled={!isEditable('DISPLAY_PROFILE_STATS_DEFAULT')}
          />
        )}
        {isVisible('LAYOUT_STATS_ENABLED_DEFAULT') && (
          <SwitchRow
            title={t('Enable Layout Stats Collection?')}
            description={t(
              'Select the Default setting to use for the collection of Proof of Play statistics for all Layout Items.',
            )}
            checked={formValues.LAYOUT_STATS_ENABLED_DEFAULT === '1'}
            onChange={(v) => updateField('LAYOUT_STATS_ENABLED_DEFAULT', v ? '1' : '0')}
            disabled={!isEditable('LAYOUT_STATS_ENABLED_DEFAULT')}
          />
        )}
        <div className="flex items-start justify-between space-x-4">
          {isVisible('MEDIA_STATS_ENABLED_DEFAULT') && (
            <SelectDropdown
              label={t('Enable Media Stats Collection?')}
              helpText={t(
                'Select the Default setting to use for the collection of Proof of Play statistics for all Media Items.',
              )}
              value={formValues.MEDIA_STATS_ENABLED_DEFAULT ?? ''}
              options={[
                { value: 'Off', label: t('Off') },
                { value: 'On', label: t('On') },
                { value: 'Inherit', label: t('Inherit') },
              ]}
              onSelect={(v) => updateField('MEDIA_STATS_ENABLED_DEFAULT', v)}
              className="flex-1"
            />
          )}
          {isVisible('PLAYLIST_STATS_ENABLED_DEFAULT') && (
            <SelectDropdown
              label={t('Enable Playlist Stats Collection?')}
              helpText={t(
                'Select the Default setting to use for the collection of Proof of Play statistics for all Playlists.',
              )}
              value={formValues.PLAYLIST_STATS_ENABLED_DEFAULT ?? ''}
              options={[
                { value: 'Off', label: t('Off') },
                { value: 'On', label: t('On') },
                { value: 'Inherit', label: t('Inherit') },
              ]}
              onSelect={(v) => updateField('PLAYLIST_STATS_ENABLED_DEFAULT', v)}
              className="flex-1"
            />
          )}
        </div>
        {isVisible('WIDGET_STATS_ENABLED_DEFAULT') && (
          <SelectDropdown
            label={t('Enable Widget Stats Collection?')}
            helpText={t(
              'Select the Default setting to use for the collection for Proof of Play statistics for all Widgets.',
            )}
            value={formValues.WIDGET_STATS_ENABLED_DEFAULT ?? ''}
            options={[
              { value: 'Off', label: t('Off') },
              { value: 'On', label: t('On') },
              { value: 'Inherit', label: t('Inherit') },
            ]}
            onSelect={(v) => updateField('WIDGET_STATS_ENABLED_DEFAULT', v)}
          />
        )}
        {isVisible('DISPLAY_PROFILE_CURRENT_LAYOUT_STATUS_ENABLED') && (
          <SwitchRow
            title={t('Enable the option to report the current layout status?')}
            checked={formValues.DISPLAY_PROFILE_CURRENT_LAYOUT_STATUS_ENABLED === '1'}
            onChange={(v) =>
              updateField('DISPLAY_PROFILE_CURRENT_LAYOUT_STATUS_ENABLED', v ? '1' : '0')
            }
            disabled={!isEditable('DISPLAY_PROFILE_CURRENT_LAYOUT_STATUS_ENABLED')}
          />
        )}
        {isVisible('DISPLAY_LOCK_NAME_TO_DEVICENAME') && (
          <SwitchRow
            title={t('Lock the Display Name to the device name provided by the Player?')}
            checked={formValues.DISPLAY_LOCK_NAME_TO_DEVICENAME === '1'}
            onChange={(v) => updateField('DISPLAY_LOCK_NAME_TO_DEVICENAME', v ? '1' : '0')}
            disabled={!isEditable('DISPLAY_LOCK_NAME_TO_DEVICENAME')}
          />
        )}
      </SettingsSection>
      <SettingsSection title={t('Screenshots')}>
        {isVisible('DISPLAY_PROFILE_SCREENSHOT_INTERVAL_ENABLED') && (
          <SwitchRow
            title={t('Enable the option to set the screenshot interval?')}
            checked={formValues.DISPLAY_PROFILE_SCREENSHOT_INTERVAL_ENABLED === '1'}
            onChange={(v) =>
              updateField('DISPLAY_PROFILE_SCREENSHOT_INTERVAL_ENABLED', v ? '1' : '0')
            }
            disabled={!isEditable('DISPLAY_PROFILE_SCREENSHOT_INTERVAL_ENABLED')}
          />
        )}
        <div className="flex items-center justify-between space-x-4">
          {isVisible('DISPLAY_PROFILE_SCREENSHOT_SIZE_DEFAULT') && (
            <NumberInput
              name="DISPLAY_PROFILE_SCREENSHOT_SIZE_DEFAULT"
              label={t('Display Screenshot Default Size')}
              helpText={t('The default size in pixels for the Display Screenshots')}
              value={Number(formValues.DISPLAY_PROFILE_SCREENSHOT_SIZE_DEFAULT) || 0}
              onChange={(v) => updateField('DISPLAY_PROFILE_SCREENSHOT_SIZE_DEFAULT', String(v))}
              disabled={!isEditable('DISPLAY_PROFILE_SCREENSHOT_SIZE_DEFAULT')}
            />
          )}
          {isVisible('DISPLAY_SCREENSHOT_TTL') && (
            <NumberInput
              name="DISPLAY_SCREENSHOT_TTL"
              label={t('Display screenshot Time to keep (days)')}
              helpText={t(
                'Display screenshots older than the TTL will be automatically removed. Set to 0 to never remove old screenshots.',
              )}
              value={Number(formValues.DISPLAY_SCREENSHOT_TTL) || 0}
              onChange={(v) => updateField('DISPLAY_SCREENSHOT_TTL', String(v))}
              disabled={!isEditable('DISPLAY_SCREENSHOT_TTL')}
            />
          )}
        </div>
      </SettingsSection>

      <SettingsSection title={t('Remote access')}>
        {isVisible('SHOW_DISPLAY_AS_VNCLINK') && (
          <TextInput
            name="SHOW_DISPLAY_AS_VNCLINK"
            label={t('Add a link to the Display name using this format mask?')}
            helpText={t(
              'Turn the display name in display management into a link using the IP address last collected. The %s is replaced with the IP address. Leave blank to disable.',
            )}
            value={formValues.SHOW_DISPLAY_AS_VNCLINK ?? ''}
            onChange={(v) => updateField('SHOW_DISPLAY_AS_VNCLINK', v)}
            disabled={!isEditable('SHOW_DISPLAY_AS_VNCLINK')}
          />
        )}
        {isVisible('SHOW_DISPLAY_AS_VNC_TGT') && (
          <TextInput
            name="SHOW_DISPLAY_AS_VNC_TGT"
            label={t('The target attribute for the above link')}
            helpText={t(
              'If the display name is shown as a link in display management, what target should the link have? Set _top to open the link in the same window or _blank to open in a new window.',
            )}
            value={formValues.SHOW_DISPLAY_AS_VNC_TGT ?? ''}
            onChange={(v) => updateField('SHOW_DISPLAY_AS_VNC_TGT', v)}
            disabled={!isEditable('SHOW_DISPLAY_AS_VNC_TGT')}
          />
        )}
      </SettingsSection>
    </div>
  );
}
