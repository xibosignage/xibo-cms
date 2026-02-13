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

import { FolderInput, Search } from 'lucide-react';
import { useEffect, useId, useState } from 'react';
import { useTranslation } from 'react-i18next';

import FolderTreeList from '../FolderTreeList';

import Modal from '@/components/ui/modals/Modal';

type MoveableItem = {
  fileName?: string;
  folderId: number;
  folderName?: string;
};

interface MoveModalProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: (targetFolderId: number) => void;
  items: MoveableItem[] | null;
  isLoading?: boolean;
  entityLabel?: string;
}

export default function MoveModal({
  isOpen,
  onClose,
  onConfirm,
  items,
  isLoading = false,
  entityLabel = 'Item',
}: MoveModalProps) {
  const { t } = useTranslation();
  const generatedId = useId();
  const [targetFolderId, setTargetFolderId] = useState<number | null>(null);
  const [folderSearch, setFolderSearch] = useState('');
  const [error, setError] = useState<string | undefined>();

  useEffect(() => {
    if (isOpen) {
      setTargetFolderId(null);
      setError(undefined);
    }
  }, [isOpen, items]);

  const safeItems = items || [];

  let commonFolderId: number | undefined;
  let currentItemName: string | undefined;
  let currentFolderName: string | undefined;

  // Check if folder is the same for all the elements
  if (safeItems.length > 0) {
    const firstId = safeItems[0]?.folderId;
    const isSameFolder = safeItems.every((item) => item.folderId === firstId);

    if (isSameFolder) {
      commonFolderId = firstId;
      currentFolderName = safeItems[0]?.folderName;
    }
  }

  if (safeItems.length === 1) {
    currentItemName = safeItems[0]?.fileName ? `"${safeItems[0]?.fileName}"` : entityLabel;
  }

  const handleSave = () => {
    if (targetFolderId === null) {
      return setError(t('Please select a destination.'));
    }

    // Prevent moving to the same folder
    if (commonFolderId === targetFolderId) {
      return setError(t('Items are already here.'));
    }

    setError(undefined);
    onConfirm(targetFolderId);
  };

  if (!isOpen) {
    return null;
  }

  return (
    <Modal
      isOpen={isOpen}
      title={
        safeItems.length > 1
          ? t('Move {{count}} {{label}}s', { count: safeItems.length, label: entityLabel })
          : t('Move {{label}}', { label: currentItemName })
      }
      onClose={() => {
        setTargetFolderId(null);
        onClose();
      }}
      size="sm"
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary',
          disabled: isLoading,
        },
        {
          label: isLoading ? t('Moving…') : t('Move'),
          onClick: handleSave,
          disabled: isLoading || targetFolderId === null,
        },
      ]}
    >
      <div className="px-8 pb-8 space-y-5">
        {commonFolderId !== undefined && (
          <div className="bg-gray-50 border border-gray-200 rounded-lg flex gap-0 items-center">
            <div className="text-sm font-normal text-gray-500 p-3 shrink-0">
              {t('Current Folder:')}
            </div>
            <div className="text-sm font-normal text-gray-800 p-3 truncate">
              <span>{currentFolderName || `${t('Folder')} #${commonFolderId}`}</span>
            </div>
          </div>
        )}

        {safeItems.length > 1 && commonFolderId === undefined && (
          <p className="text-sm text-gray-500">{t('Items are in different folders.')}</p>
        )}

        {error && <div className="text-sm text-red-600 font-medium">{error}</div>}

        <div className="flex flex-col gap-5 border border-gray-200">
          <div className="px-3 py-2 bg-gray-100 flex gap-2 items-center text-gray-500 shrink-0">
            <FolderInput size={16} />
            <div className="text-sm font-semibold uppercase leading-5.25">
              {t('Select a location')}
            </div>
          </div>

          <div className="flex flex-col px-5 max-h-[40vh] overflow-hidden">
            <FolderTreeList
              selectedId={targetFolderId ?? null}
              searchQuery={folderSearch}
              onSelect={(folder) => {
                setTargetFolderId(folder.id);
                setError(undefined);
              }}
              customSlot={
                <div className="px-2 shrink-0 relative">
                  <div className="absolute inset-y-0 left-3 flex items-center pl-3 pointer-events-none">
                    <Search className="w-4 h-4 text-gray-500" />
                  </div>
                  <input
                    id={generatedId + '_search'}
                    value={folderSearch}
                    onChange={(e) => setFolderSearch(e.target.value)}
                    placeholder={t('Search')}
                    className="py-2 pl-10 pr-4 block w-full rounded-lg border-gray-200 text-gray-800 text-sm focus:border-xibo-blue-500 focus:ring-xibo-blue-500 bg-white placeholder:text-gray-500"
                    autoFocus
                  />
                </div>
              }
            />
          </div>
        </div>
      </div>
    </Modal>
  );
}
