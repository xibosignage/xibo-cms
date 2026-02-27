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

import { FolderPlus, X } from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import Button from './Button';
import FolderSearchInput from './FolderSearchInput';
import FolderTreeList from './FolderTreeList';
import Checkbox from './forms/Checkbox';

import { useUserContext } from '@/context/UserContext';
import type { ActionType } from '@/hooks/useFolderActions';
import type { Folder } from '@/types/folder';

interface FolderSidebarProps {
  isOpen: boolean;
  selectedFolderId: number | null;
  onSelect: (folder: { id: number | null; text: string }) => void;
  onClose: () => void;
  className?: string;
  refreshTrigger: number;
  onAction: (action: ActionType, folder: Folder) => void;
}

export default function FolderSidebar({
  isOpen,
  selectedFolderId,
  onSelect,
  onClose,
  className = '',
  refreshTrigger,
  onAction,
}: FolderSidebarProps) {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const searchId = useId();
  const [searchQuery, setSearchQuery] = useState('');
  const [activeTab, setActiveTab] = useState<'Home' | 'Shared with me'>('Home');
  const [rootFolderId, setRootFolderId] = useState<number | null>(null);

  const homeFolderId = user?.homeFolderId ?? null;

  const handleAction = (action: string, folder: Folder) => {
    onAction(action as ActionType, folder);
  };

  const handleCreateFolder = () => {
    const targetId = activeTab === 'Home' && homeFolderId != null ? homeFolderId : rootFolderId;

    if (targetId == null) {
      return;
    }

    handleAction('create', { id: targetId } as Folder);
  };

  const handleAllItemsToggle = () => {
    if (selectedFolderId === null) {
      onSelect({ id: rootFolderId ?? 1, text: t('Root Folder') });
    } else {
      onSelect({ id: null, text: t('All Items') });
    }
  };

  return (
    <>
      <div
        className={`bg-white border-r border-gray-200 flex flex-col h-full gap-5 transition-[max-width,opacity] duration-300 ease-in-out overflow-hidden whitespace-nowrap w-fit ${
          isOpen ? 'max-w-62 sm:max-w-85 xl:max-w-150 opacity-100' : 'max-w-0 opacity-0 border-none'
        } ${className}`}
      >
        <div className="flex flex-col h-full min-w-62 sm:min-w-75">
          {/* Header */}
          <div className="flex items-center justify-between px-3 py-2 relative bg-gray-100 shrink-0">
            <div className="text-sm uppercase font-semibold text-gray-500 truncate">
              {t('Select Folder')}
            </div>
            <Button
              variant="iconLink"
              className="absolute right-0 top-0 size-7 m-1 -outline-offset-2"
              onClick={() => onClose()}
            >
              <X className="size-4" onClick={() => onClose()} />
            </Button>
          </div>

          {/* Main Content Area */}
          <div className={twMerge('flex-1 min-h-0', isOpen ? 'px-5  pb-2' : '')}>
            <FolderTreeList
              selectedId={selectedFolderId}
              onSelect={(folder) => onSelect({ id: folder.id, text: folder.text })}
              searchQuery={searchQuery}
              refreshTrigger={refreshTrigger}
              onAction={handleAction}
              onRootResolved={setRootFolderId}
              onActiveTabChange={setActiveTab}
              customSlot={
                <>
                  <FolderSearchInput
                    id={searchId}
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

                  <Checkbox
                    id="all-items-checkbox"
                    label={t('All Items')}
                    checked={selectedFolderId === null}
                    onChange={handleAllItemsToggle}
                    className="px-3 py-2.5 gap-1"
                    classNameLabel="text-sm font-semibold text-gray-800"
                  />
                </>
              }
            />
          </div>
        </div>
      </div>
    </>
  );
}
