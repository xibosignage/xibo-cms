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
} from 'lucide-react';
import { useEffect, useState, useMemo, useRef } from 'react';
import { useTranslation } from 'react-i18next';

import { fetchContextButtons, type FolderPermissions } from '@/services/folderApi';
import type { Folder } from '@/types/folder';

export type FolderAction = 'create' | 'rename' | 'move' | 'share' | 'delete';

interface FolderTreeListProps {
  folders: Folder[];
  selectedId?: number | null;
  onSelect: (folder: Folder) => void;
  onAction?: (action: FolderAction, folder: Folder) => void;
}

const buildVisibleList = (
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
      result.push(...buildVisibleList(node.children!, expandedIds, depth + 1));
    }
  }
  return result;
};

export default function FolderTreeList({
  folders,
  selectedId,
  onSelect,
  onAction,
}: FolderTreeListProps) {
  const { t } = useTranslation();
  const [expandedIds, setExpandedIds] = useState<Set<number>>(new Set([1]));

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

  const visibleList = useMemo(() => buildVisibleList(folders, expandedIds), [folders, expandedIds]);

  if (visibleList.length === 0) {
    return (
      <div className="text-center py-6 text-gray-400 italic text-xs flex flex-col items-center gap-2">
        <FolderIcon size={24} className="opacity-20" />
        <span>{t('No folders found')}</span>
      </div>
    );
  }

  return (
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

function FolderItem({
  folder,
  isSelected,
  onSelect,
  onAction,
  onToggle,
}: {
  folder: Folder & { depth: number; hasChildren: boolean; isExpanded: boolean };
  isSelected: boolean;
  onSelect: (f: Folder) => void;
  onAction?: (action: FolderAction, f: Folder) => void;
  onToggle: (e: React.MouseEvent) => void;
}) {
  const { t } = useTranslation();
  const [menuPosition, setMenuPosition] = useState<{ x: number; y: number } | null>(null);
  const [permissions, setPermissions] = useState<FolderPermissions | null>(null);
  const [loadingPerms, setLoadingPerms] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);

  const handleContextMenu = (e: React.MouseEvent) => {
    if (!onAction) return;
    e.preventDefault();
    e.stopPropagation();

    setMenuPosition({ x: e.clientX, y: e.clientY });

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
    if (menuPosition) window.addEventListener('mousedown', handleClick);
    return () => window.removeEventListener('mousedown', handleClick);
  }, [menuPosition]);

  const Icon = folder.hasChildren && folder.isExpanded ? FolderOpen : FolderIcon;

  // TODO: Design and translations
  return (
    <>
      <div
        className={`group relative flex items-center rounded-md transition-colors cursor-pointer
          ${
            isSelected
              ? 'bg-xibo-blue-50 text-xibo-blue-700 font-medium'
              : 'hover:bg-gray-100 text-gray-700'
          }
        `}
        style={{ paddingLeft: `${folder.depth * 16 + 8}px` }}
        onClick={() => onSelect(folder)}
        onContextMenu={handleContextMenu}
      >
        <div className="flex-1 flex items-center py-2 gap-2 overflow-hidden">
          <Icon
            size={16}
            className={`shrink-0 transition-colors ${
              isSelected ? 'text-xibo-blue-500 fill-xibo-blue-200' : 'text-gray-400'
            }`}
          />
          <span className="truncate text-sm">{folder.text}</span>
        </div>

        <div className="pr-1">
          {folder.hasChildren ? (
            <button
              onClick={onToggle}
              className={`p-1 rounded-full hover:bg-black/5 text-gray-400 transition-transform duration-200 ${
                folder.isExpanded ? 'rotate-0' : '-rotate-90'
              }`}
            >
              <ChevronDown size={14} />
            </button>
          ) : (
            <div className="w-6" />
          )}
        </div>
      </div>

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
                  Read Only
                </span>
              )}
            </>
          )}
        </div>
      )}
    </>
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
      type="button"
      onClick={onClick}
      className={`flex items-center gap-2 text-xs text-left px-3 py-2 hover:bg-gray-50 w-full transition-colors ${
        variant === 'danger' ? 'text-red-600 hover:bg-red-50' : 'text-gray-700'
      }`}
    >
      {Icon && (
        <Icon size={14} className={variant === 'danger' ? 'text-red-500' : 'text-gray-400'} />
      )}
      {label}
    </button>
  );
}
