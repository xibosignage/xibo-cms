import {
  ChevronRight,
  Folder as FolderIcon,
  Loader2,
  MoreVertical,
  FileEdit,
  Trash2,
  FolderPlus,
  UserPlus2,
  FolderInput,
  type LucideIcon,
} from 'lucide-react';
import { useEffect, useState, useRef } from 'react';
import { useTranslation } from 'react-i18next';

import Button from './Button';

import { fetchFolderById, fetchContextButtons, type FolderPermissions } from '@/services/folderApi';
import type { Folder } from '@/types/folder';

interface FolderBreadcrumbProps {
  currentFolderId: number | null;
  onNavigate: (folder: { id: number; text: string }) => void;
  isSidebarOpen: boolean;
  onToggleSidebar: () => void;
  className?: string;
}

export default function FolderBreadcrumb({
  currentFolderId,
  onNavigate,
  isSidebarOpen,
  onToggleSidebar,
  className = '',
}: FolderBreadcrumbProps) {
  const { t } = useTranslation();

  const [breadcrumbs, setBreadcrumbs] = useState<Folder[]>([]);
  const [loadingPath, setLoadingPath] = useState(false);

  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const [menuPerms, setMenuPerms] = useState<FolderPermissions | null>(null);
  const [loadingPerms, setLoadingPerms] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    let active = true;

    async function loadBreadcrumbPath() {
      setLoadingPath(true);
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
        if (active) setLoadingPath(false);
      }
    }

    loadBreadcrumbPath();

    return () => {
      active = false;
    };
  }, [currentFolderId]);

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (menuRef.current && !menuRef.current.contains(event.target as Node)) {
        setIsMenuOpen(false);
      }
    }
    if (isMenuOpen) document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [isMenuOpen]);

  const handleMenuToggle = async (e: React.MouseEvent) => {
    e.stopPropagation();
    if (isMenuOpen) {
      setIsMenuOpen(false);
      return;
    }
    setIsMenuOpen(true);

    if (!menuPerms && currentFolderId) {
      setLoadingPerms(true);
      try {
        const perms = await fetchContextButtons(currentFolderId);
        setMenuPerms(perms);
      } catch (err) {
        console.error('Failed to load permissions', err);
      } finally {
        setLoadingPerms(false);
      }
    }
  };

  const handleAction = (action: string, folderId: number | null, folderName: string) => {
    console.log(`Action: ${action} on folder: ${folderName} (${folderId})`);
    setIsMenuOpen(false);
  };

  const currentFolderName = breadcrumbs?.[breadcrumbs.length - 1]?.text ?? '';

  // TODO: Design
  return (
    <div className={`flex items-center gap-2 text-sm ${className}`}>
      <Button
        variant="link"
        onClick={onToggleSidebar}
        title={t('Toggle Folder Tree')}
        className={`p-2 border rounded-md shadow-sm transition-colors ${
          isSidebarOpen
            ? 'bg-xibo-blue-50 text-xibo-blue-600 border-xibo-blue-200'
            : 'bg-white hover:bg-gray-50 border-gray-200 text-gray-600'
        }`}
      >
        <FolderIcon size={18} className="fill-current" />
      </Button>

      <ChevronRight size={14} className="text-gray-300" />

      <div className="flex items-center flex-wrap bg-white border border-gray-200 rounded-md shadow-sm px-2 py-1 gap-1 min-h-9">
        {loadingPath && breadcrumbs.length === 0 ? (
          <div className="flex items-center gap-2 px-2 text-gray-400">
            <Loader2 size={12} className="animate-spin" />
            <span>{t('Resolving path...')}</span>
          </div>
        ) : (
          breadcrumbs.map((folder, index) => {
            const isLast = index === breadcrumbs.length - 1;

            return (
              <div key={folder.id} className="flex items-center">
                {index > 0 && <span className="text-gray-300 mx-1">/</span>}

                {isLast ? (
                  <div className="flex items-center gap-1 pl-1">
                    <span className="font-semibold text-gray-700">{folder.text}</span>

                    <div className="relative ml-1" ref={menuRef}>
                      <button
                        onClick={handleMenuToggle}
                        className={`p-1 rounded-md transition-colors ${
                          isMenuOpen
                            ? 'bg-gray-100 text-gray-900'
                            : 'text-gray-400 hover:text-gray-600 hover:bg-gray-50'
                        }`}
                      >
                        <MoreVertical size={14} />
                      </button>

                      {isMenuOpen && (
                        <div className="absolute left-0 top-full mt-1 w-40 bg-white rounded-lg shadow-xl border border-gray-100 z-30 overflow-hidden py-1 animate-in fade-in zoom-in-95 duration-100">
                          {loadingPerms ? (
                            <div className="flex justify-center p-2">
                              <Loader2 size={14} className="animate-spin text-xibo-blue-500" />
                            </div>
                          ) : (
                            <>
                              {menuPerms?.create && (
                                <ContextMenuItem
                                  icon={FolderPlus}
                                  label={t('New Folder')}
                                  onClick={() =>
                                    handleAction('create', currentFolderId, currentFolderName)
                                  }
                                />
                              )}
                              {menuPerms?.modify && (
                                <ContextMenuItem
                                  icon={FileEdit}
                                  label={t('Rename')}
                                  onClick={() =>
                                    handleAction('rename', currentFolderId, currentFolderName)
                                  }
                                />
                              )}
                              {menuPerms?.share && (
                                <ContextMenuItem
                                  icon={UserPlus2}
                                  label={t('Share')}
                                  onClick={() =>
                                    handleAction('share', currentFolderId, currentFolderName)
                                  }
                                />
                              )}
                              {menuPerms?.move && (
                                <ContextMenuItem
                                  icon={FolderInput}
                                  label={t('Move')}
                                  onClick={() =>
                                    handleAction('move', currentFolderId, currentFolderName)
                                  }
                                />
                              )}
                              <div className="m-2 h-px bg-gray-100" role="separator" />
                              {menuPerms?.delete && (
                                <ContextMenuItem
                                  icon={Trash2}
                                  label={t('Delete')}
                                  variant="danger"
                                  onClick={() =>
                                    handleAction('delete', currentFolderId, currentFolderName)
                                  }
                                />
                              )}
                              {!menuPerms?.create && !menuPerms?.modify && !menuPerms?.delete && (
                                <span className="text-[10px] text-gray-400 px-2 py-1 italic block text-center">
                                  {t('Read Only')}
                                </span>
                              )}
                            </>
                          )}
                        </div>
                      )}
                    </div>
                  </div>
                ) : (
                  <button
                    onClick={() => onNavigate({ id: folder.id, text: folder.text })}
                    className="px-1.5 py-0.5 text-gray-500 hover:text-xibo-blue-600 hover:bg-blue-50 rounded text-xs transition-colors max-w-[100px] truncate"
                    title={folder.text}
                  >
                    {folder.text}
                  </button>
                )}
              </div>
            );
          })
        )}
      </div>
    </div>
  );
}

// TODO: Design
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
      className={`flex items-center gap-2 text-xs text-left p-2 hover:bg-gray-50 w-full transition-colors
        ${variant === 'danger' ? 'text-red-600 hover:bg-red-50' : 'text-gray-700'}
      `}
    >
      {Icon && (
        <Icon size={14} className={variant === 'danger' ? 'text-red-500' : 'text-gray-400'} />
      )}
      {label}
    </button>
  );
}
