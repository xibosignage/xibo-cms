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

import { useTranslation } from 'react-i18next';

import type { SettingsTabProps } from '../../SettingsConfig';
import SettingsSection from '../SettingsSection';

import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import SwitchRow from '@/pages/Administration/Users/components/SwitchRow';

export default function SharingTab({
  formValues,
  updateField,
  isVisible,
  isEditable,
}: SettingsTabProps) {
  const { t } = useTranslation();

  return (
    <div className="flex flex-col gap-3">
      <SettingsSection title={t('Widget Colour Coding in Playlist Editor')}>
        {isVisible('REGION_OPTIONS_COLOURING') && (
          <SelectDropdown
            label={t('Maintenance Mode')}
            helpText={t('Set to "Protected" to secure the script behind a secret key.')}
            value={formValues.REGION_OPTIONS_COLOURING ?? ''}
            options={[
              { value: 'Media Colouring', label: t('Media Colouring') },
              { value: 'Sharing Colouring', label: t('Sharing Colouring') },
            ]}
            onSelect={(v) => updateField('REGION_OPTIONS_COLOURING', v)}
          />
        )}
      </SettingsSection>
      <SettingsSection title={t('Scheduling Permissions')}>
        {isVisible('SCHEDULE_WITH_VIEW_PERMISSION') && (
          <SwitchRow
            title={t('Schedule with view sharing')}
            description={t(
              'Users with "View" sharing access on a display can schedule content to it.',
            )}
            checked={formValues.SCHEDULE_WITH_VIEW_PERMISSION === '1'}
            onChange={(v) => updateField('SCHEDULE_WITH_VIEW_PERMISSION', v ? '1' : '0')}
            disabled={!isEditable('SCHEDULE_WITH_VIEW_PERMISSION')}
          />
        )}
        {isVisible('SCHEDULE_SHOW_LAYOUT_NAME') && (
          <SwitchRow
            title={t('Show event Layout regardless of User permission')}
            description={t(
              "Display the layout for existing scheduled events even if the current user doesn't have view access.",
            )}
            checked={formValues.SCHEDULE_SHOW_LAYOUT_NAME === '1'}
            onChange={(v) => updateField('SCHEDULE_SHOW_LAYOUT_NAME', v ? '1' : '0')}
            disabled={!isEditable('SCHEDULE_SHOW_LAYOUT_NAME')}
          />
        )}
      </SettingsSection>
      <SettingsSection title={t('Content Organisation')}>
        {isVisible('TRANSITION_CONFIG_LOCKED_CHECKB') && (
          <SwitchRow
            title={t('Lock transition configuration')}
            description={t('Allow modifications to the transition configuration.')}
            checked={formValues.TRANSITION_CONFIG_LOCKED_CHECKB === '1'}
            onChange={(v) => updateField('TRANSITION_CONFIG_LOCKED_CHECKB', v ? '1' : '0')}
            disabled={!isEditable('TRANSITION_CONFIG_LOCKED_CHECKB')}
          />
        )}
        {isVisible('FOLDERS_ALLOW_SAVE_IN_ROOT') && (
          <SwitchRow
            title={t('Allow saving to the Root Folder')}
            description={t(
              'Users can store content at the top level. Disable to require folder organisation.',
            )}
            checked={formValues.FOLDERS_ALLOW_SAVE_IN_ROOT === '1'}
            onChange={(v) => updateField('FOLDERS_ALLOW_SAVE_IN_ROOT', v ? '1' : '0')}
            disabled={!isEditable('FOLDERS_ALLOW_SAVE_IN_ROOT')}
          />
        )}
        {isVisible('TASK_CONFIG_LOCKED_CHECKB') && (
          <SwitchRow
            title={t('Lock Task Config')}
            description={t('Is the task config locked? Useful for Service providers.')}
            checked={formValues.TASK_CONFIG_LOCKED_CHECKB === '1'}
            onChange={(v) => updateField('TASK_CONFIG_LOCKED_CHECKB', v ? '1' : '0')}
            disabled={!isEditable('TASK_CONFIG_LOCKED_CHECKB')}
          />
        )}
      </SettingsSection>
    </div>
  );
}
