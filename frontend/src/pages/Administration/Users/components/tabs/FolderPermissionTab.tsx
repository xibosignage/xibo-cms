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

import { Eye, Loader2, PenSquare, Search, ShieldCheck, X } from 'lucide-react';
import { Trans, useTranslation } from 'react-i18next';

import type { FolderPermissionTabProps } from '../../config/addEditUserTypes';

import FolderPermissionTree from '@/components/ui/FolderPermissionTree';
import InfoBanner from '@/components/ui/InfoBanner';
import SelectFolder from '@/components/ui/forms/SelectFolder';
import type { Folder } from '@/types/folder';

export default function FolderPermissionTab({
  draft,
  setDraft,
  folderTreeData,
  folderPermissions,
  setFolderPermissions,
  isLoadingFolders,
  folderSearch,
  setFolderSearch,
}: FolderPermissionTabProps) {
  const { t } = useTranslation();

  return (
    <>
      {/* Select Home Folder */}
      <div className="flex flex-col gap-1">
        <SelectFolder
          selectedId={draft.homeFolderId}
          onSelect={(folder) =>
            setDraft((prev) => ({
              ...prev,
              homeFolderId: folder?.id ?? 1,
            }))
          }
        />
        <p className="text-sm text-gray-400">{t('Select the default folder for this user.')}</p>
      </div>

      {/* Folder Access Permission */}
      <div className="flex flex-col gap-1">
        <span className="text-sm font-semibold text-gray-800">{t('Folder Access Permission')}</span>
        <p className="text-xs text-gray-500">
          {t(
            'Configure which folders this user can access and their permission level for each folder.',
          )}
        </p>
      </div>

      {/* Selected folder tags */}
      {folderPermissions.size > 0 && (
        <div className="flex items-start gap-2 bg-slate-50 rounded-lg p-2 pb-0">
          <div className="flex items-center gap-2 flex-wrap max-h-18 overflow-y-auto flex-1 py-2">
            {Array.from(folderPermissions.entries()).map(([folderId, level]) => {
              const folder = findFolderById(folderTreeData, folderId);
              if (!folder) return null;
              const LevelIcon =
                level === 'viewOnly' ? Eye : level === 'readWrite' ? PenSquare : ShieldCheck;
              const parentId = folder.parentId;
              const parentLevel = parentId ? folderPermissions.get(parentId) : undefined;
              const showPath = parentLevel !== undefined && parentLevel !== level;
              const displayName = showPath
                ? (getFolderPath(folderTreeData, folderId) ?? folder.text)
                : folder.text;
              return (
                <span
                  key={folderId}
                  className="inline-flex items-center gap-1 bg-blue-50 text-blue-700 text-xs font-medium px-2.5 py-1 rounded-full border border-blue-200"
                >
                  <LevelIcon size={12} />
                  {displayName}
                  <button
                    type="button"
                    onClick={() => {
                      const next = new Map(folderPermissions);
                      next.delete(folderId);
                      setFolderPermissions(next);
                    }}
                    aria-label={t('Remove {{name}}', { name: displayName })}
                    className="ml-0.5 text-blue-500 hover:text-blue-800"
                  >
                    <X size={12} />
                  </button>
                </span>
              );
            })}
          </div>
          <button
            type="button"
            onClick={() => setFolderPermissions(new Map())}
            className="text-sm text-gray-500 hover:text-gray-700 shrink-0 px-4 py-2"
          >
            {t('Clear All')}
          </button>
        </div>
      )}

      {isLoadingFolders ? (
        <div className="flex items-center justify-center py-8 text-gray-400 text-sm">
          <Loader2 className="w-5 h-5 animate-spin mr-2" />
          {t('Loading folders...')}
        </div>
      ) : (
        <>
          {/* Search */}
          <div className="relative">
            <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
              <Search className="w-4 h-4 text-gray-400" />
            </div>
            <input
              type="text"
              value={folderSearch}
              onChange={(e) => setFolderSearch(e.target.value)}
              placeholder={t('Search Folder')}
              className="py-2 px-3 pl-10 block h-10 bg-white rounded-lg w-full border border-gray-200 text-sm"
            />
          </div>

          <FolderPermissionTree
            treeData={folderTreeData}
            folderPermissions={folderPermissions}
            onChange={setFolderPermissions}
            searchQuery={folderSearch}
          />
        </>
      )}

      <InfoBanner type="info" hideInfoIcon={true}>
        <Trans
          i18nKey="<strong>Note:</strong> Folder permissions are hierarchical. Granting access to a parent folder automatically grants the same level of access to all child folders unless explicitly overridden."
          components={{ strong: <strong /> }}
        />
      </InfoBanner>
    </>
  );
}

function findFolderById(nodes: Folder[], id: number): Folder | null {
  for (const node of nodes) {
    if (node.id === id) return node;
    if (Array.isArray(node.children) && node.children.length > 0) {
      const found = findFolderById(node.children, id);
      if (found) return found;
    }
  }
  return null;
}

function getFolderPath(nodes: Folder[], id: number, ancestors: string[] = []): string | null {
  for (const node of nodes) {
    const path = node.isRoot === 1 ? ancestors : [...ancestors, node.text];
    if (node.id === id) {
      return path.length > 0 ? path.join(' / ') : node.text;
    }
    if (Array.isArray(node.children) && node.children.length > 0) {
      const found = getFolderPath(node.children, id, path);
      if (found) return found;
    }
  }
  return null;
}
