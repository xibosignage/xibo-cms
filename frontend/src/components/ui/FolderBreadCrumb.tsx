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

import {
  ChevronRight,
  Folder as FolderIcon,
  Loader2,
  Trash2,
  FolderPlus,
  UserPlus2,
  FolderInput,
  ChevronDown,
  FolderEdit,
  MoreHorizontal,
  type LucideIcon,
} from 'lucide-react';
import { useEffect, useState, useRef, useLayoutEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import Button from './Button';

import type { ActionType } from '@/hooks/useFolderActions';
import { fetchFolderById, fetchContextButtons, type FolderPermissions } from '@/services/folderApi';
import type { Folder } from '@/types/folder';

interface FolderBreadcrumbProps {
  currentFolderId: number | null;
  onNavigate: (folder: { id: number; text: string }) => void;
  isSidebarOpen: boolean;
  onToggleSidebar: () => void;
  className?: string;
  onAction: (action: ActionType, folder: Folder) => void;
  refreshTrigger: number;
}

export default function FolderBreadcrumb({
  currentFolderId,
  onNavigate,
  isSidebarOpen,
  onToggleSidebar,
  className = '',
  onAction,
  refreshTrigger,
}: FolderBreadcrumbProps) {
  const { t } = useTranslation();
  const { breadcrumbs } = useBreadcrumbPath(currentFolderId, refreshTrigger);
  const containerRef = useRef<HTMLDivElement>(null);
  const [limits, setLimits] = useState({ start: 3, end: 3 });

  useLayoutEffect(() => {
    const element = containerRef.current;

    if (!element) {
      return;
    }

    const observer = new ResizeObserver((entries) => {
      if (!entries[0]) {
        return;
      }

      // Measure container
      const width = entries[0].contentRect.width;

      setLimits((prev) => {
        let newLimits = { start: 4, end: 4 };
        if (width < 300) {
          newLimits = { start: 0, end: 1 };
        } else if (width < 600) {
          newLimits = { start: 1, end: 2 };
        } else if (width < 800) {
          newLimits = { start: 2, end: 2 };
        } else if (width < 1000) {
          newLimits = { start: 2, end: 3 };
        } else if (width < 1200) {
          newLimits = { start: 3, end: 3 };
        }

        // Only update if values changed
        if (prev.start !== newLimits.start || prev.end !== newLimits.end) {
          return newLimits;
        }

        return prev;
      });
    });

    observer.observe(element);

    return () => observer.disconnect();
  }, []);

  const total = breadcrumbs.length;
  const threshold = limits.start + limits.end;

  let startItems = breadcrumbs;
  let endItems: Folder[] = [];
  let hasHiddenItems = false;

  if (total > threshold) {
    startItems = breadcrumbs.slice(0, limits.start);
    endItems = breadcrumbs.slice(-limits.end);
    hasHiddenItems = true;
  }

  const renderItem = (folder: Folder, isLast: boolean) => (
    <div key={folder.id} className={twMerge('flex items-center gap-1', isLast ? '' : 'min-w-0')}>
      {isLast ? (
        <div className="flex items-center px-2 py-2">
          <div
            title={folder.text}
            aria-label={folder.text}
            className="font-semibold text-xibo-blue-500 truncate max-w-20 sm:max-w-[120px]"
          >
            {folder.text}
          </div>
          <FolderContextMenu folder={folder} onAction={onAction} />
        </div>
      ) : (
        <>
          <button
            title={folder.text}
            aria-label={folder.text}
            onClick={() => onNavigate({ id: folder.id, text: folder.text })}
            className="flex items-center px-2 py-2 text-sm font-semibold text-gray-500 hover:text-gray-800 cursor-pointer truncate max-w-[100px] sm:max-w-[150px]"
          >
            {folder.text}
          </button>
          <div className="text-gray-400 shrink-0">
            <ChevronRight className="size-4" />
          </div>
        </>
      )}
    </div>
  );

  return (
    <div ref={containerRef} className={twMerge('flex items-center gap-0.5 text-sm', className)}>
      <Button
        variant="tertiary"
        onClick={onToggleSidebar}
        title={t('Toggle Folder Tree')}
        className="size-[38px] shrink-0 flex items-center justify-center mr-1"
      >
        <FolderIcon className={twMerge('size-4.5', isSidebarOpen ? 'fill-current' : '')} />
      </Button>

      {/* Start Group */}
      {startItems.map((folder, index) => {
        const isTrueLast = !hasHiddenItems && index === startItems.length - 1;
        return renderItem(folder, isTrueLast);
      })}

      {/* Dots */}
      {hasHiddenItems && (
        <div className="flex items-center px-1 text-gray-400">
          <MoreHorizontal className="size-4" />
          <ChevronRight className="size-4 ml-1" />
        </div>
      )}

      {/* End Group */}
      {hasHiddenItems &&
        endItems.map((folder, index) => {
          const isTrueLast = index === endItems.length - 1;
          return renderItem(folder, isTrueLast);
        })}
    </div>
  );
}

function FolderContextMenu({
  folder,
  onAction,
}: {
  folder: Folder;
  onAction: (action: ActionType, folder: Folder) => void;
}) {
  const { t } = useTranslation();
  const [isOpen, setIsOpen] = useState(false);
  const [perms, setPerms] = useState<FolderPermissions | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!isOpen) return;
    function handleClickOutside(event: MouseEvent) {
      if (menuRef.current && !menuRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [isOpen]);

  const handleToggle = async (e: React.MouseEvent) => {
    e.stopPropagation();
    if (isOpen) {
      setIsOpen(false);
      return;
    }
    setIsOpen(true);

    if (!perms) {
      setIsLoading(true);
      try {
        const p = await fetchContextButtons(folder.id);
        setPerms(p);
      } catch (err) {
        console.error('Failed to load permissions', err);
      } finally {
        setIsLoading(false);
      }
    }
  };

  const handleItemClick = (action: ActionType) => {
    onAction(action, folder);
    setIsOpen(false);
  };

  return (
    <div className="relative flex justify-center items-center" ref={menuRef}>
      <button
        onClick={handleToggle}
        className={twMerge(
          'rounded-lg p-1',
          isOpen ? 'bg-gray-100 text-gray-900' : 'text-gray-400 hover:text-gray-800 cursor-pointer',
        )}
      >
        <ChevronDown className="size-4" />
      </button>

      {isOpen && (
        <div className="absolute left-0 top-full mt-1 p-2 w-[218px] bg-white rounded-xl shadow-lg border border-gray-200 z-30 overflow-hidden">
          {isLoading ? (
            <div className="flex justify-center p-2">
              <Loader2 size={14} className="animate-spin text-xibo-blue-500" />
            </div>
          ) : (
            <>
              {perms?.create && (
                <ContextMenuItem
                  icon={FolderPlus}
                  label={t('New Folder')}
                  onClick={() => handleItemClick('create')}
                />
              )}
              {perms?.modify && (
                <ContextMenuItem
                  icon={FolderEdit}
                  label={t('Rename')}
                  onClick={() => handleItemClick('rename')}
                />
              )}
              {perms?.share && (
                <ContextMenuItem
                  icon={UserPlus2}
                  label={t('Share')}
                  onClick={() => handleItemClick('share')}
                />
              )}
              {perms?.move && (
                <ContextMenuItem
                  icon={FolderInput}
                  label={t('Move')}
                  onClick={() => handleItemClick('move')}
                />
              )}

              {(perms?.create || perms?.modify || perms?.share || perms?.move) && perms?.delete && (
                <div className="h-px my-2 bg-gray-200" role="separator" />
              )}

              {perms?.delete && (
                <ContextMenuItem
                  icon={Trash2}
                  label={t('Delete')}
                  variant="danger"
                  onClick={() => handleItemClick('delete')}
                />
              )}

              {!perms?.create && !perms?.modify && !perms?.delete && (
                <span className="text-[10px] text-gray-600 px-2 py-1 italic block text-center">
                  {t('Read Only')}
                </span>
              )}
            </>
          )}
        </div>
      )}
    </div>
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
      onClick={(e) => {
        e.stopPropagation();
        onClick();
      }}
      className={twMerge(
        'flex items-center gap-3 px-3 py-2 w-full rounded-lg text-sm font-semibold',
        variant === 'danger' ? 'text-red-600 hover:bg-red-50' : 'text-gray-800 hover:bg-gray-100',
      )}
    >
      {Icon && (
        <Icon size={14} className={variant === 'danger' ? 'text-red-600' : 'text-gray-800'} />
      )}
      {label}
    </button>
  );
}

function useBreadcrumbPath(currentFolderId: number | null, refreshTrigger: number) {
  const [breadcrumbs, setBreadcrumbs] = useState<Folder[]>([]);
  const [isLoading, setIsLoading] = useState(!!currentFolderId);

  useEffect(() => {
    let active = true;

    async function loadPath() {
      setIsLoading(true);
      try {
        const path: Folder[] = [];
        let nextId = currentFolderId;
        let depth = 0;

        while (nextId && depth < 20) {
          const folder = await fetchFolderById(nextId);
          path.unshift(folder);

          if (folder.id === 1) break;

          nextId = Number(folder.parentId);
          if (!nextId) break;

          depth++;
        }

        if (active) {
          if (path.length > 0 && path[0]?.id !== 1) {
            const root = await fetchFolderById(1);
            path.unshift(root);
          }
          setBreadcrumbs(path);
        }
      } catch (error) {
        console.error('Failed to resolve breadcrumb path', error);
        if (active) setBreadcrumbs([]);
      } finally {
        if (active) setIsLoading(false);
      }
    }

    loadPath();

    return () => {
      active = false;
    };
  }, [currentFolderId, refreshTrigger]);

  return { breadcrumbs, isLoading };
}
