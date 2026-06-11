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
import { FolderOpen, FolderPlus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import FolderInfoPanel from './components/FolderInfoPanel';

import Button from '@/components/ui/Button';
import FolderActionModals from '@/components/ui/FolderActionModals';
import FolderSearchInput from '@/components/ui/FolderSearchInput';
import FolderTreeList from '@/components/ui/FolderTreeList';
import TabNav from '@/components/ui/TabNav';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { type ActionType, useFolderActions } from '@/hooks/useFolderActions';
import { fetchFolderById } from '@/services/folderApi';
import { fetchUserPreference, saveUserPreference } from '@/services/userApi';
import type { Folder } from '@/types/folder';

const PAGE_KEY = 'folders_page';

export default function Folders() {
  const { t } = useTranslation();
  const adminTabs = useFilteredTabs('administration');

  const [selectedFolder, setSelectedFolder] = useState<Folder | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [refreshTrigger, setRefreshTrigger] = useState(0);
  const [rootFolderId, setRootFolderId] = useState<number | null>(null);
  const [isHydrated, setIsHydrated] = useState(false);

  // Restore selected folder from user preference
  const { data: savedPrefs, isSuccess: hasLoadedPrefs } = useQuery({
    queryKey: ['userPref', PAGE_KEY],
    queryFn: () => fetchUserPreference<{ folderId?: number }>(PAGE_KEY),
    staleTime: Infinity,
  });

  useEffect(() => {
    if (!hasLoadedPrefs || isHydrated) return;

    const savedFolderId = savedPrefs?.folderId;
    if (savedFolderId) {
      fetchFolderById(savedFolderId)
        .then((folder) => setSelectedFolder(folder))
        .catch(() => {})
        .finally(() => setIsHydrated(true));
    } else {
      setIsHydrated(true);
    }
  }, [hasLoadedPrefs, isHydrated, savedPrefs]);

  const handleSelectFolder = (folder: Folder | null) => {
    setSelectedFolder(folder);
    saveUserPreference({
      option: PAGE_KEY,
      value: { folderId: folder?.id ?? null },
    });
  };

  const folderActions = useFolderActions({
    onSuccess: (targetFolder) => {
      setRefreshTrigger((n) => n + 1);
      if (targetFolder && targetFolder.id === -1) {
        handleSelectFolder(null);
      } else if (targetFolder) {
        handleSelectFolder({ id: targetFolder.id, text: targetFolder.text } as Folder);
      }
    },
  });

  const handleAction = (action: ActionType, folder: Folder) => {
    folderActions.openAction(action, folder);
  };

  const handleCreateFolder = () => {
    const targetId = selectedFolder?.id ?? rootFolderId;
    if (targetId == null) return;
    handleAction('create', { id: targetId } as Folder);
  };

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5">
        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <TabNav activeTab="Folders" navigation={adminTabs} />
        </div>

        <div className="flex flex-1 min-h-0 border border-gray-200 rounded-lg overflow-hidden">
          {/* Left: Folder Tree */}
          <aside className="w-80 shrink-0 border-r border-gray-200 flex flex-col bg-white">
            <div className="flex items-center justify-between px-3 py-2 bg-gray-100 shrink-0">
              <span className="text-sm uppercase font-semibold text-gray-500">
                {t('Select Folder')}
              </span>
            </div>
            <div className="flex-1 min-h-0 px-5 pb-2">
              <FolderTreeList
                selectedId={selectedFolder?.id ?? null}
                onSelect={(folder: Folder) => handleSelectFolder(folder)}
                onAction={handleAction}
                searchQuery={searchQuery}
                refreshTrigger={refreshTrigger}
                onRootResolved={setRootFolderId}
                customSlot={
                  <>
                    <FolderSearchInput
                      value={searchQuery}
                      onChange={setSearchQuery}
                      placeholder={t('Search Folder')}
                    />
                    <Button
                      variant="tertiary"
                      className="flex items-center justify-center w-full"
                      leftIcon={FolderPlus}
                      onClick={handleCreateFolder}
                    >
                      {t('New Folder')}
                    </Button>
                  </>
                }
              />
            </div>
          </aside>

          {/* Right: Info Panel */}
          <main className="flex-1 overflow-auto">
            {selectedFolder ? (
              <FolderInfoPanel
                folderId={selectedFolder.id}
                refreshTrigger={refreshTrigger}
                onCreateFolder={handleCreateFolder}
                onNavigate={(folder) => handleSelectFolder(folder as Folder)}
                onAction={handleAction}
              />
            ) : (
              <div className="flex flex-col items-center justify-center h-full gap-3 text-gray-400">
                <FolderOpen size={48} strokeWidth={1.5} />
                <span className="text-sm">{t('Select a folder to view its details')}</span>
              </div>
            )}
          </main>
        </div>
      </div>

      <FolderActionModals folderActions={folderActions} />
    </section>
  );
}
