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
  ChevronDown,
  Eye,
  Folder as FolderIcon,
  FolderOpen,
  Home,
  PenSquare,
  ShieldCheck,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { Folder } from '@/types/folder';

export type PermissionLevel = 'viewOnly' | 'readWrite' | 'fullAccess';

type VisibleNode = Folder & { depth: number; hasChildren: boolean; isExpanded: boolean };

const PERMISSION_LEVELS: PermissionLevel[] = ['viewOnly', 'readWrite', 'fullAccess'];

const flattenTree = (nodes: Folder[]): Folder[] => {
  const result: Folder[] = [];
  for (const node of nodes) {
    result.push(node);
    if (Array.isArray(node.children) && node.children.length > 0) {
      result.push(...flattenTree(node.children));
    }
  }
  return result;
};

const collectAllIds = (nodes: Folder[]): Set<number> => {
  const ids = new Set<number>();
  for (const node of flattenTree(nodes)) {
    ids.add(node.id);
  }
  return ids;
};

const buildVisibleTree = (nodes: Folder[], expandedIds: Set<number>, depth = 0): VisibleNode[] => {
  const result: VisibleNode[] = [];
  for (const node of nodes) {
    const hasChildren = Array.isArray(node.children) && node.children.length > 0;
    const isExpanded = expandedIds.has(node.id);
    result.push({ ...node, depth, hasChildren, isExpanded });
    if (hasChildren && isExpanded) {
      result.push(...buildVisibleTree(node.children as Folder[], expandedIds, depth + 1));
    }
  }
  return result;
};

interface FolderPermissionTreeProps {
  treeData: Folder[];
  folderPermissions: Map<number, PermissionLevel>;
  onChange: (permissions: Map<number, PermissionLevel>) => void;
  searchQuery?: string;
}

export default function FolderPermissionTree({
  treeData,
  folderPermissions,
  onChange,
  searchQuery = '',
}: FolderPermissionTreeProps) {
  const { t } = useTranslation();
  const [expandedIds, setExpandedIds] = useState<Set<number>>(() => collectAllIds(treeData));

  const visibleList = (() => {
    const list = buildVisibleTree(treeData, expandedIds);
    if (!searchQuery.trim()) return list;
    const query = searchQuery.toLowerCase();
    return list.filter((node) => node.text.toLowerCase().includes(query));
  })();

  const allSelectableIds = flattenTree(treeData)
    .filter((f) => f.isRoot !== 1)
    .map((f) => f.id);

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

  const setFolderPermission = (folderId: number, level: PermissionLevel) => {
    const next = new Map(folderPermissions);
    if (next.get(folderId) === level) {
      next.delete(folderId);
    } else {
      next.set(folderId, level);
    }
    onChange(next);
  };

  const selectAllAs = (level: PermissionLevel) => {
    const allCurrentLevel = allSelectableIds.every((id) => folderPermissions.get(id) === level);
    if (allCurrentLevel) {
      onChange(new Map());
      return;
    }
    const next = new Map<number, PermissionLevel>();
    allSelectableIds.forEach((id) => next.set(id, level));
    onChange(next);
  };

  const getSelectAllState = (level: PermissionLevel): boolean => {
    if (allSelectableIds.length === 0) return false;
    return allSelectableIds.every((id) => folderPermissions.get(id) === level);
  };

  const getColumnHeaders = () => [
    { level: 'viewOnly' as const, label: t('View Only'), Icon: Eye },
    { level: 'readWrite' as const, label: t('Read & Write'), Icon: PenSquare },
    { level: 'fullAccess' as const, label: t('Full Access'), Icon: ShieldCheck },
  ];

  return (
    <div className="flex flex-col border border-gray-200 rounded-lg overflow-hidden bg-white">
      {/* Column headers */}
      <div className="grid grid-cols-[1fr_120px_120px_120px] items-center bg-gray-50 border-b border-gray-200 px-3 py-2.5">
        <span className="text-sm font-medium text-gray-600">{t('Folders')}</span>
        {getColumnHeaders().map(({ level, label, Icon }) => (
          <span
            key={level}
            className="flex items-center justify-center gap-1.5 text-sm font-medium text-gray-600"
          >
            <Icon size={14} />
            {label}
          </span>
        ))}
      </div>

      {/* "Select all as" row */}
      <div className="grid grid-cols-[1fr_120px_120px_120px] items-center bg-gray-100 border-b border-gray-200 px-3 py-2">
        <span className="text-sm text-gray-500 italic">{t('Select all as')}</span>
        {PERMISSION_LEVELS.map((level) => (
          <div key={level} className="flex justify-center">
            <PermissionCheckbox
              checked={getSelectAllState(level)}
              onChange={() => selectAllAs(level)}
            />
          </div>
        ))}
      </div>

      {/* Folder tree rows */}
      <div className="overflow-y-auto max-h-85 select-none">
        {visibleList.map((node) => {
          const isRoot = node.isRoot === 1;
          const currentLevel = folderPermissions.get(node.id);

          let Icon;
          if (isRoot) {
            Icon = Home;
          } else if (node.hasChildren && node.isExpanded) {
            Icon = FolderOpen;
          } else {
            Icon = FolderIcon;
          }

          return (
            <div
              key={node.id}
              className="grid grid-cols-[1fr_120px_120px_120px] items-center hover:bg-blue-50/50 transition-colors pr-3"
            >
              {/* Folder name + expand/collapse */}
              <div
                className="flex items-center py-2 px-3 gap-2 overflow-hidden"
                style={{ paddingLeft: `${node.depth * 16 + 12}px` }}
              >
                <Icon size={14} className="shrink-0 text-gray-600" />
                <span className="truncate text-sm font-medium">{node.text}</span>
                {node.hasChildren ? (
                  <button
                    type="button"
                    onClick={(e) => toggleExpand(node.id, e)}
                    className={`p-0.5 rounded-full hover:bg-black/5 text-gray-600 transition-transform duration-200 shrink-0 ${
                      node.isExpanded ? 'rotate-180' : 'rotate-0'
                    }`}
                  >
                    <ChevronDown size={14} />
                  </button>
                ) : null}
              </div>

              {/* Permission checkboxes */}
              {PERMISSION_LEVELS.map((level) => (
                <div key={level} className="flex justify-center">
                  <PermissionCheckbox
                    checked={currentLevel === level}
                    disabled={isRoot}
                    onChange={() => !isRoot && setFolderPermission(node.id, level)}
                  />
                </div>
              ))}
            </div>
          );
        })}
      </div>
    </div>
  );
}

function PermissionCheckbox({
  checked,
  disabled,
  onChange,
}: {
  checked: boolean;
  disabled?: boolean;
  onChange: () => void;
}) {
  return (
    <input
      type="checkbox"
      checked={checked}
      disabled={disabled}
      onChange={onChange}
      className="h-5 w-5 shrink-0 border-gray-300 rounded cursor-pointer text-blue-600 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-40 disabled:cursor-not-allowed"
    />
  );
}
