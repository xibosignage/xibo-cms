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

import { X, FolderInput, UserPlus2, Info } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { LayoutInfoPanel } from './LayoutInfoPannel';

import { useKeydown } from '@/hooks/useKeydown';
import { useOwner } from '@/hooks/useOwner';
import type { Layout } from '@/types/layout';

interface LayoutPreviewerProps {
  layoutId: number | null;
  name?: string;
  onMove?: () => void;
  onShare?: (id: number) => void;
  layoutData?: Layout | null;
  onClose: () => void;
  folderName: string;
}

export default function LayoutPreviewer({
  layoutId,
  name,
  onMove,
  layoutData,
  onShare,
  onClose,
  folderName,
}: LayoutPreviewerProps) {
  const { t } = useTranslation();
  const [showInfoPanel, setShowInfoPanel] = useState(false);

  const ownerId = layoutData?.ownerId ? Number(layoutData.ownerId) : null;

  const { owner, loading: isOwnerLoading } = useOwner({ ownerId });

  useKeydown('Escape', onClose, !!layoutId);

  if (!layoutId) return null;

  return (
    <div className="fixed inset-0 z-50 flex flex-col bg-black/80 h-dvh">
      {/* Header */}
      <div className="flex w-full px-5 py-3 text-white justify-between">
        <div className="flex items-center gap-3">
          <button onClick={onClose} className="cursor-pointer rounded-lg hover:bg-white/10">
            <X className="p-1" />
          </button>
          <h3 className="font-semibold text-sm truncate">{name || `Layout ${layoutId}`}</h3>
        </div>
        <div className="flex items-center gap-3">
          {onMove && (
            <button
              onClick={onMove}
              className="flex justify-center items-center cursor-pointer rounded-lg hover:bg-white/10"
              title={t('Move')}
            >
              <FolderInput className="p-1" />
            </button>
          )}
          {onShare && (
            <button
              onClick={() => onShare(layoutId as number)}
              className="flex justify-center items-center cursor-pointer rounded-lg hover:bg-white/10"
              title={t('Share')}
            >
              <UserPlus2 className="p-1" />
            </button>
          )}
          <button
            onClick={() => setShowInfoPanel((prev) => !prev)}
            className="flex justify-center items-center cursor-pointer rounded-lg hover:bg-white/10"
            title={t('Details')}
          >
            <Info className="p-1" />
          </button>
        </div>
      </div>

      {/* Preview */}
      <div className="flex flex-1 min-h-0">
        <div className="flex-1 w-full p-4 flex justify-center items-center overflow-hidden min-h-0">
          <iframe
            src={`/layout/preview/${layoutId}`}
            title={`Layout ${layoutId}`}
            className="w-full h-full min-h-125 rounded shadow-md border-0"
          />
        </div>
        <div className="pr-4 pb-4 flex">
          <LayoutInfoPanel
            isOpen={showInfoPanel}
            onClose={() => setShowInfoPanel(false)}
            layoutData={layoutData}
            owner={owner}
            folderName={folderName}
            loading={isOwnerLoading}
            applyVersionTwo={false}
          />
        </div>
      </div>
    </div>
  );
}
