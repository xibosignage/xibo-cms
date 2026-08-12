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

import { useQueryClient } from '@tanstack/react-query';
import { isAxiosError } from 'axios';
import { CircleX } from 'lucide-react';
import { useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import { SETTINGS_TABS } from './SettingsConfig';
import ConfigurationTab from './components/tabs/ConfigurationTab';
import DefaultsTab from './components/tabs/DefaultsTab';
import DisplaysTab from './components/tabs/DisplaysTab';
import GeneralTab from './components/tabs/GeneralTab';
import MaintenanceTab from './components/tabs/MaintenanceTab';
import NetworkTab from './components/tabs/NetworkTab';
import RegionalTab from './components/tabs/RegionalTab';
import SharingTab from './components/tabs/SharingTab';
import TroubleshootingTab from './components/tabs/TroubleshootingTab';
import UsersTab from './components/tabs/UsersTab';
import { useSettingsData, settingsQueryKeys } from './hooks/useSettingsData';
import { useSettingsForm } from './hooks/useSettingsForm';

import Button from '@/components/ui/Button';
import InfoBanner from '@/components/ui/InfoBanner';
import { notify } from '@/components/ui/Notification';
import TabNav from '@/components/ui/TabNav';
import type { TabNavItem } from '@/components/ui/TabNav';
import { useUserContext } from '@/context/UserContext';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { reloadAppLanguage } from '@/lib/i18n';
import { updateSettings } from '@/services/settingsApi';
import { fetchCurrentUser } from '@/services/userApi';

export default function Settings() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const adminTabs = useFilteredTabs('administration');
  const { updateUser } = useUserContext();

  const { data, isLoading, isError, isPaused, error: queryError } = useSettingsData();
  const { formValues, updateField, isVisible, isEditable, isDirty, resetForm } =
    useSettingsForm(data);

  const [activeTab, setActiveTab] = useState('Configuration');
  const [isPending, startTransition] = useTransition();
  const [saveError, setSaveError] = useState<string | null>(null);

  const handleTabClick = (tab: TabNavItem) => {
    setActiveTab(tab.labelKey);
  };

  const handleSave = () => {
    setSaveError(null);
    startTransition(async () => {
      try {
        await updateSettings(formValues);
        notify.success(t('Settings Updated'));
        queryClient.invalidateQueries({ queryKey: settingsQueryKeys.all });
        await reloadAppLanguage();

        // The current user's bootstrap payload (settings/features/branding) is only
        // fetched once per full page load and isn't part of any React Query cache, so it
        // goes stale the moment global settings change here. Refresh it so other pages
        // relying on it (e.g. the Display Profile form) reflect the change immediately,
        // without requiring a hard reload.
        try {
          updateUser(await fetchCurrentUser());
        } catch {
          // Non-fatal — the settings save itself already succeeded.
        }
      } catch (err: unknown) {
        const message =
          (isAxiosError(err) && err.response?.data?.message) ||
          (err instanceof Error && err.message) ||
          t('Failed to save settings');
        setSaveError(message);
      }
    });
  };

  const tabProps = {
    formValues,
    updateField,
    isVisible,
    isEditable,
    options: data?.options ?? { languages: [], timeZones: [] },
    relatedEntities: data?.relatedEntities ?? {
      defaultLayout: null,
      systemUser: null,
      defaultUserGroup: null,
      defaultTransitionIn: null,
      defaultTransitionOut: null,
    },
    elevateLogUntil: data?.elevateLogUntil ?? null,
    phoneticKey: data?.phoneticKey ?? '',
  };

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5">
        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <TabNav activeTab="Settings" navigation={adminTabs} />
        </div>
        <div className="flex-1 flex rounded-lg border border-gray-200 overflow-hidden">
          <div className="bg-white px-5 py-2 border-gray-200 flex min-w-35 flex-col">
            {SETTINGS_TABS.map((tab) => (
              <button
                type="button"
                className={twMerge(
                  'text-sm text-gray-500 font-semibold px-3 py-2 inline-flex items-center gap-x-2 whitespace-nowrap focus:outline-none transition-colors cursor-pointer',
                  activeTab === tab.labelKey
                    ? 'text-xibo-blue-600 border-xibo-blue-600'
                    : 'border-transparent hover:text-gray-600 hover:border-gray-300',
                )}
                aria-current={activeTab === tab.labelKey ? 'page' : undefined}
                key={tab.path}
                onClick={() => handleTabClick(tab)}
              >
                {t(tab.labelKey)}
              </button>
            ))}
          </div>

          {isError && (
            <InfoBanner type="danger" className="w-full! mt-2 items-center">
              {queryError instanceof Error ? queryError.message : t('Failed to load settings')}
            </InfoBanner>
          )}

          {isPaused && (
            <InfoBanner type="warning" className="w-full! mt-2 items-center">
              {t(
                "You're offline. Showing previously loaded results. This will update automatically once your connection is restored.",
              )}
            </InfoBanner>
          )}
          <div className="flex-1 overflow-y-auto flex flex-col">
            {isLoading && (
              <div className="flex-1 flex items-center justify-center p-5 space-y-5 bg-gray-50 ">
                <span className="text-gray-400 animate-pulse font-medium">{t('Loading...')}</span>
              </div>
            )}
            {data && (
              <div className="flex-1 p-5 space-y-5 bg-gray-50 ">
                <h3 className="font-semibold text-xl">{t(activeTab)}</h3>
                {activeTab === 'Configuration' && <ConfigurationTab {...tabProps} />}
                {activeTab === 'Defaults' && <DefaultsTab {...tabProps} />}
                {activeTab === 'Displays' && <DisplaysTab {...tabProps} />}
                {activeTab === 'General' && <GeneralTab {...tabProps} />}
                {activeTab === 'Maintenance' && <MaintenanceTab {...tabProps} />}
                {activeTab === 'Network' && <NetworkTab {...tabProps} />}
                {activeTab === 'Regional' && <RegionalTab {...tabProps} />}
                {activeTab === 'Sharing' && <SharingTab {...tabProps} />}
                {activeTab === 'Troubleshooting' && <TroubleshootingTab {...tabProps} />}
                {activeTab === 'Users' && <UsersTab {...tabProps} />}
              </div>
            )}
            <div className="p-5 bg-white flex items-center justify-between border-t border-gray-100">
              {saveError ? (
                <div
                  className="bg-red-50 border border-red-200 text-sm text-red-800 rounded-lg p-3 flex items-center gap-3"
                  role="alert"
                >
                  <CircleX size={18} className="shrink-0" />
                  <span className="font-semibold">{saveError}</span>
                </div>
              ) : (
                <div />
              )}
              <div className="flex gap-x-3">
                <Button variant="secondary" onClick={resetForm} disabled={isPending}>
                  {t('Cancel')}
                </Button>
                <Button variant="primary" onClick={handleSave} disabled={isPending || !isDirty}>
                  {isPending ? t('Saving...') : t('Save')}
                </Button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
