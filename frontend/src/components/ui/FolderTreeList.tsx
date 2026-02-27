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

import type { LucideIcon } from 'lucide-react';
import {
  ChevronDown,
  FileEdit,
  FolderInput,
  FolderPlus,
  Folder as FolderIcon,
  Loader2,
  Trash2,
  UserPlus2,
  FolderOpen,
  Home,
} from 'lucide-react';
import { useEffect, useState, useRef, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import { useUserContext } from '@/context/UserContext';
import { useDebounce } from '@/hooks/useDebounce';
import {
  fetchContextButtons,
  fetchFolderTree,
  searchFolders,
  type FolderPermissions,
} from '@/services/folderApi';
import type { Folder } from '@/types/folder';

export type FolderAction = 'create' | 'rename' | 'move' | 'share' | 'delete';
type FolderTab = 'Home' | 'Shared with me';

interface FolderTreeListProps {
  selectedId?: number | null;
  onSelect: (folder: Folder) => void;
  onAction?: (action: FolderAction, folder: Folder) => void;
  searchQuery: string;
  customSlot?: ReactNode;
  refreshTrigger?: number;
  onRootResolved?: (rootId: number) => void;
  onActiveTabChange?: (tab: 'Home' | 'Shared with me') => void;
}

const flattenTree = (nodes: Folder[]): Folder[] => {
  let result: Folder[] = [];
  for (const node of nodes) {
    result.push(node);
    if (node.children && node.children.length > 0) {
      result = result.concat(flattenTree(node.children));
    }
  }
  return result;
};
const findSubtree = (nodes: Folder[], targetId: number): Folder | null => {
  for (const node of nodes) {
    if (node.id === targetId) {
      return node;
    }
    if (node.children && node.children.length > 0) {
      const found = findSubtree(node.children, targetId);
      if (found !== null) {
        return found;
      }
    }
  }
  return null;
};

const buildVisibleTree = (
  nodes: Folder[],
  expandedIds: Set<number>,
  depth = 0,
): Array<Folder & { depth: number; hasChildren: boolean; isExpanded: boolean }> => {
  const result: Array<Folder & { depth: number; hasChildren: boolean; isExpanded: boolean }> = [];

  for (const node of nodes) {
    const hasChildren = Array.isArray(node.children) && node.children.length > 0;
    const isExpanded = expandedIds.has(node.id);

    result.push({ ...node, depth, hasChildren, isExpanded });

    if (hasChildren && isExpanded) {
      result.push(...buildVisibleTree(node.children!, expandedIds, depth + 1));
    }
  }
  return result;
};

export default function FolderTreeList({
  selectedId,
  onSelect,
  onAction,
  searchQuery,
  customSlot,
  refreshTrigger = 0,
  onRootResolved,
  onActiveTabChange,
}: FolderTreeListProps) {
  const { t } = useTranslation();
  const { user } = useUserContext();

  const homeFolderId = user?.homeFolderId ?? 1;

  const [activeTab, setActiveTab] = useState<FolderTab>('Home');
  const [treeData, setTreeData] = useState<Folder[]>([]);
  const [searchResults, setSearchResults] = useState<Folder[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [expandedIds, setExpandedIds] = useState<Set<number>>(
    new Set([1, homeFolderId].filter((id): id is number => id != null)),
  );

  const debouncedQuery = useDebounce(searchQuery, 300);

  const rootFolder = treeData.find((folder) => folder.isRoot === 1);
  const showHomeTab =
    rootFolder !== undefined && homeFolderId != null && homeFolderId !== rootFolder.id;

  useEffect(() => {
    const controller = new AbortController();

    async function loadTree() {
      setIsLoading(true);

      try {
        if (debouncedQuery.trim()) {
          const results = await searchFolders(debouncedQuery, controller.signal);
          setSearchResults(results);
        } else {
          const tree = await fetchFolderTree(controller.signal);
          setTreeData(tree);
        }
      } catch (e: unknown) {
        if (e instanceof Error && e.name !== 'CanceledError' && e.name !== 'AbortError') {
          console.error(e);
        }
      } finally {
        if (!controller.signal.aborted) {
          setIsLoading(false);
        }
      }
    }

    loadTree();
    return () => controller.abort();
  }, [debouncedQuery, refreshTrigger]);

  // Sync home folder into expandedIds once user context resolves
  useEffect(() => {
    if (user?.homeFolderId != null) {
      setExpandedIds((prev) => new Set([...prev, user.homeFolderId as number]));
    }
  }, [user?.homeFolderId]);

  useEffect(() => {
    if (rootFolder?.id != null) {
      onRootResolved?.(rootFolder.id);
    }
  }, [rootFolder?.id, onRootResolved]);

  // Default to home tab if exists
  const activeTabRef = useRef(activeTab);
  activeTabRef.current = activeTab;

  useEffect(() => {
    if (treeData.length === 0) {
      return;
    }

    if (!showHomeTab && activeTabRef.current === 'Home') {
      setActiveTab('Shared with me');
      onActiveTabChange?.('Shared with me');
    }
  }, [showHomeTab, treeData.length, onActiveTabChange]);

  const toggleExpand = (id: number, e?: React.MouseEvent) => {
    e?.stopPropagation();
    setExpandedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  const isSearching = searchQuery.trim().length > 0;
  const homeRoot = homeFolderId != null ? findSubtree(treeData, homeFolderId) : null;
  const homeIds =
    isSearching && activeTab === 'Home' && homeRoot != null
      ? new Set(flattenTree([homeRoot]).map((f) => f.id))
      : new Set<number>();
  const filteredData = isSearching
    ? activeTab === 'Home'
      ? searchResults.filter((folder) => homeIds.has(folder.id))
      : searchResults
    : treeData;
  const hasData = filteredData.length > 0;
  const isInitialLoad = isLoading && !hasData;
  const isRefreshing = isLoading && hasData;

  const availableTabs = (['Home', 'Shared with me'] as FolderTab[]).filter((tab) => {
    if (tab === 'Home') {
      return showHomeTab;
    }
    return true;
  });

  let content: ReactNode;

  if (isSearching) {
    // Search results
    content = (
      <div className="flex flex-col space-y-0.5 select-none">
        {filteredData.map((folder) => (
          <FolderItem
            key={folder.id}
            folder={{ ...folder, depth: 0, hasChildren: false, isExpanded: false }}
            isSelected={selectedId === folder.id}
            onSelect={onSelect}
            onAction={onAction}
            onToggle={() => {}}
          />
        ))}
      </div>
    );
  } else if (activeTab === 'Home' && homeRoot) {
    const homeRootExpanded = expandedIds.has(homeRoot.id);
    const visibleList = homeRootExpanded
      ? buildVisibleTree(homeRoot.children ?? [], expandedIds)
      : [];

    content = (
      <div className="flex flex-col space-y-0.5 select-none">
        <FolderItem
          key={homeRoot.id}
          folder={{
            ...homeRoot,
            depth: 0,
            hasChildren: (homeRoot.children?.length ?? 0) > 0,
            isExpanded: homeRootExpanded,
          }}
          isSelected={selectedId === homeRoot.id}
          onSelect={onSelect}
          onAction={onAction}
          onToggle={(e) => toggleExpand(homeRoot.id, e)}
        />
        {visibleList.map((folder) => (
          <FolderItem
            key={folder.id}
            folder={{ ...folder, depth: folder.depth + 1 }}
            isSelected={selectedId === folder.id}
            onSelect={onSelect}
            onAction={onAction}
            onToggle={(e) => toggleExpand(folder.id, e)}
          />
        ))}
      </div>
    );
  } else {
    const visibleList = buildVisibleTree(filteredData, expandedIds);

    content = (
      <div className="flex flex-col space-y-0.5 select-none">
        {visibleList.map((folder) => (
          <FolderItem
            key={folder.id}
            folder={folder}
            isSelected={selectedId === folder.id}
            onSelect={onSelect}
            onAction={onAction}
            onToggle={(e) => toggleExpand(folder.id, e)}
          />
        ))}
      </div>
    );
  }

  return (
    <div className="flex flex-col h-full overflow-hidden gap-2 pt-2">
      {availableTabs.length > 1 && (
        <div className="flex gap-x-1 px-2 pt-2 border-b border-gray-100 bg-white shrink-0">
          {availableTabs.map((tab) => (
            <button
              key={tab}
              type="button"
              onClick={() => {
                setActiveTab(tab);
                onActiveTabChange?.(tab);
              }}
              className={`flex items-center gap-2 px-3 py-2 text-sm font-semibold transition-all rounded-t-lg border-b-2 ${
                activeTab === tab
                  ? 'text-xibo-blue-600 border-xibo-blue-600 font-medium'
                  : 'text-gray-500 border-transparent hover:text-xibo-blue-500'
              }`}
            >
              {tab === 'Home' && <Home size={14} />}
              {t(tab)}
            </button>
          ))}
        </div>
      )}

      {customSlot && <div className="flex flex-col gap-2 shrink-0">{customSlot}</div>}

      <div className="flex-1 overflow-auto min-h-0 min-w-0 pb-4 relative">
        {/* Init loader */}
        {isInitialLoad && (
          <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 text-gray-400 z-20 bg-white">
            <Loader2 className="w-6 h-6 animate-spin text-xibo-blue-500" />
            <span className="text-xs">{t('Loading folders...')}</span>
          </div>
        )}

        <div
          className={twMerge(
            'transition-all duration-300 ease-in-out h-full',
            isRefreshing ? 'opacity-50 pointer-events-none grayscale-[0.5]' : 'opacity-100',
          )}
        >
          {!hasData && !isLoading ? (
            <div className="text-center py-6 text-gray-400 italic text-xs flex flex-col items-center gap-2">
              <FolderIcon size={24} className="opacity-20" />
              <span>{t('No folders found')}</span>
            </div>
          ) : (
            content
          )}
        </div>

        {/* Overlay loader */}
        {isRefreshing && (
          <div className="absolute inset-0 flex items-center justify-center z-10">
            <div className="bg-white/80 p-2 rounded-full shadow-sm backdrop-blur-sm">
              <Loader2 className="w-6 h-6 animate-spin text-xibo-blue-500" />
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

function FolderItem({
  folder,
  isSelected,
  onSelect,
  onAction,
  onToggle,
  forceIcon,
}: {
  folder: Folder & { depth: number; hasChildren: boolean; isExpanded: boolean };
  isSelected: boolean;
  onSelect: (f: Folder) => void;
  onAction?: (action: FolderAction, f: Folder) => void;
  onToggle: (e: React.MouseEvent) => void;
  forceIcon?: LucideIcon;
}) {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const homeFolderId = user?.homeFolderId;
  const [menuPosition, setMenuPosition] = useState<{ x: number; y: number } | null>(null);
  const [permissions, setPermissions] = useState<FolderPermissions | null>(null);
  const [loadingPerms, setLoadingPerms] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);

  const isDisabled = folder.type === 'disabled';

  const handleContextMenu = (e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();

    if (isDisabled || !onAction) {
      return;
    }

    const x = e.clientX + 160 > window.innerWidth ? e.clientX - 160 : e.clientX;
    const y = e.clientY + 200 > window.innerHeight ? e.clientY - 200 : e.clientY;

    setMenuPosition({ x, y });

    if (!permissions) {
      setLoadingPerms(true);
      fetchContextButtons(folder.id)
        .then(setPermissions)
        .catch((err) => console.error(err))
        .finally(() => setLoadingPerms(false));
    }
  };

  useEffect(() => {
    const handleClick = (e: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(e.target as Node)) {
        setMenuPosition(null);
      }
    };
    if (menuPosition) {
      window.addEventListener('mousedown', handleClick);
    }
    return () => window.removeEventListener('mousedown', handleClick);
  }, [menuPosition]);

  let Icon = forceIcon;
  if (!Icon) {
    if (folder.id === homeFolderId) {
      Icon = Home;
    } else {
      Icon = folder.hasChildren && folder.isExpanded ? FolderOpen : FolderIcon;
    }
  }

  return (
    <>
      <div
        className={twMerge(
          'group relative flex items-center transition-colors',
          isSelected
            ? 'bg-xibo-blue-50 text-xibo-blue-700 font-medium'
            : isDisabled
              ? 'text-gray-400 cursor-default'
              : 'hover:bg-gray-100 text-gray-800 cursor-pointer',
          isDisabled ? 'opacity-75' : '',
        )}
        style={{ paddingLeft: `${folder.depth * 16 + 8}px` }}
        onClick={() => !isDisabled && onSelect(folder)}
        onContextMenu={handleContextMenu}
      >
        <div className="flex-1 flex items-center py-2 px-3 gap-2 overflow-hidden">
          <Icon
            size={16}
            className={twMerge(
              'shrink-0 transition-colors',
              isSelected ? 'text-xibo-blue-500' : 'text-gray-800',
              isSelected && folder.id !== homeFolderId ? 'fill-xibo-blue-200' : '',
            )}
          />
          <span className="truncate text-sm font-semibold">{folder.text}</span>
        </div>

        <div className="pr-1">
          {folder.hasChildren ? (
            <button
              onClick={onToggle}
              className={`p-1 rounded-full hover:bg-black/5 text-gray-800 transition-transform duration-200 ${
                folder.isExpanded ? 'rotate-180' : 'rotate-0'
              }`}
            >
              <ChevronDown size={14} />
            </button>
          ) : (
            <div className="w-6" />
          )}
        </div>
      </div>

      {/* Context menu */}
      {menuPosition && (
        <div
          ref={menuRef}
          className="fixed z-100 w-40 bg-white rounded-lg shadow-xl border border-gray-100 py-1 animate-in fade-in zoom-in-95 duration-100"
          style={{ top: menuPosition.y, left: menuPosition.x }}
          onClick={(e) => e.stopPropagation()}
        >
          {loadingPerms ? (
            <div className="flex justify-center p-2">
              <Loader2 size={14} className="animate-spin text-xibo-blue-500" />
            </div>
          ) : (
            <>
              {permissions?.create && (
                <ContextMenuItem
                  icon={FolderPlus}
                  label={t('New folder')}
                  onClick={() => {
                    onAction?.('create', folder);
                    setMenuPosition(null);
                  }}
                />
              )}
              {permissions?.modify && (
                <ContextMenuItem
                  icon={FileEdit}
                  label="Rename"
                  onClick={() => {
                    onAction?.('rename', folder);
                    setMenuPosition(null);
                  }}
                />
              )}
              {permissions?.share && (
                <ContextMenuItem
                  icon={UserPlus2}
                  label="Share"
                  onClick={() => {
                    onAction?.('share', folder);
                    setMenuPosition(null);
                  }}
                />
              )}
              {permissions?.move && (
                <ContextMenuItem
                  icon={FolderInput}
                  label="Move"
                  onClick={() => {
                    onAction?.('move', folder);
                    setMenuPosition(null);
                  }}
                />
              )}
              {permissions?.delete && (
                <>
                  <div className="my-1 h-px bg-gray-100" />
                  <ContextMenuItem
                    icon={Trash2}
                    label="Delete"
                    variant="danger"
                    onClick={() => {
                      onAction?.('delete', folder);
                      setMenuPosition(null);
                    }}
                  />
                </>
              )}
              {!permissions?.create && !permissions?.modify && !permissions?.delete && (
                <span className="text-[10px] text-gray-400 px-3 py-1 italic block text-center">
                  {t('Read Only')}
                </span>
              )}
            </>
          )}
        </div>
      )}
    </>
  );
}

function ContextMenuItem({
  label,
  onClick,
  icon: Icon,
  variant = 'default',
}: {
  label: string;
  onClick: () => void;
  icon?: LucideIcon;
  variant?: 'default' | 'danger';
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`flex items-center gap-2 text-xs text-left px-3 py-2 hover:bg-gray-50 w-full transition-colors ${
        variant === 'danger' ? 'text-red-600 hover:bg-red-50' : 'text-gray-800'
      }`}
    >
      {Icon && (
        <Icon size={14} className={variant === 'danger' ? 'text-red-500' : 'text-gray-800'} />
      )}
      {label}
    </button>
  );
}
