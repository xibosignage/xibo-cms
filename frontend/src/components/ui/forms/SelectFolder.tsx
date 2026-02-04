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

import { ChevronDown, Home, Loader2, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import Button from '../Button';
import FolderTreeList, { type FolderAction } from '../FolderTreeList';

import { fetchFolderById, fetchFolderTree, searchFolders } from '@/services/folderApi';
import type { Folder } from '@/types/folder';

type FolderTab = 'Home' | 'My Files';

interface SelectFolderProps {
  selectedId?: number | null;
  onSelect: (folder: { id: number; text: string }) => void;
  onAction?: (action: FolderAction, folder: Folder) => void;
}

export default function SelectFolder({ selectedId, onSelect, onAction }: SelectFolderProps) {
  const { t } = useTranslation();

  const [isOpen, setIsOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);
  const [activeTab, setActiveTab] = useState<FolderTab>('Home');
  const [initialName, setInitialName] = useState<string | null>(null);

  const [folderSearch, setFolderSearch] = useState('');
  const [treeData, setTreeData] = useState<Folder[]>([]);
  const [searchResults, setSearchResults] = useState<Folder[]>([]);
  const [isLoading, setIsLoading] = useState(false);

  const findNameInTree = (nodes: Folder[], id: number): string | null => {
    for (const node of nodes) {
      if (node.id === id) return node.text;
      if (node.children) {
        const found = findNameInTree(node.children, id);
        if (found) return found;
      }
    }
    return null;
  };

  const treeName = selectedId
    ? folderSearch
      ? findNameInTree(searchResults, selectedId)
      : findNameInTree(treeData, selectedId)
    : null;

  const displayName = treeName || initialName;

  useEffect(() => {
    if (!selectedId || treeName) {
      return;
    }

    let active = true;

    fetchFolderById(selectedId)
      .then((folder) => {
        if (active) {
          setInitialName(folder.text);
        }
      })
      .catch((err) => {
        console.error('Failed to resolve folder name', err);
      });

    return () => {
      active = false;
    };
  }, [selectedId, treeName]);

  // Click Outside Listener
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    }
    if (isOpen) document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [isOpen]);

  // Data Loading Listener
  useEffect(() => {
    if (!isOpen) return;

    const controller = new AbortController();

    async function loadData() {
      setIsLoading(true);
      try {
        if (folderSearch.trim()) {
          const results = await searchFolders(folderSearch, controller.signal);
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
        setIsLoading(false);
      }
    }

    loadData();
    return () => controller.abort();
  }, [isOpen, folderSearch, activeTab]);

  // TODO: Design
  return (
    <div className="relative overflow-visible" ref={containerRef}>
      <label className="block text-xs font-semibold text-gray-500 mb-1">
        {t('Select folder location')}
      </label>

      {/* Trigger Button */}
      <div
        className="w-full border border-gray-200 rounded-lg flex items-center bg-white transition-shadow hover:shadow-sm cursor-pointer"
        onClick={() => setIsOpen(!isOpen)}
      >
        <span className="p-3 border-r text-sm border-gray-200 text-gray-500 bg-gray-50 rounded-l-lg font-medium">
          {t(activeTab)}
        </span>

        <button
          type="button"
          className="p-3 flex-1 text-sm text-left hover:bg-gray-50 transition-colors truncate"
        >
          {displayName || selectedId ? (
            <span className="text-gray-800">
              {displayName || t('Folder #{{id}}', { id: selectedId })}
            </span>
          ) : (
            <span className="text-gray-400">{t('Select a folder')}</span>
          )}
        </button>

        <div className="p-3 text-gray-500">
          <ChevronDown
            size={14}
            className={`transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`}
          />
        </div>
      </div>

      {/* Dropdown Panel */}
      <div
        className={`absolute top-[75px] w-full bg-white shadow-xl rounded-lg border border-gray-100 overflow-hidden transition-all duration-200 ease-out z-50 origin-top
          ${isOpen ? 'opacity-100 scale-100 visible' : 'opacity-0 scale-95 invisible pointer-events-none'}
        `}
      >
        <div className="bg-gray-100 p-2 text-[10px] font-bold text-gray-500 uppercase tracking-wider w-full border-b border-gray-200">
          {t('Select Folder')}
        </div>

        {/* Tabs */}
        <div className="flex gap-x-1 px-2 pt-2 border-b border-gray-100 bg-white">
          {(['Home', 'My Files'] as FolderTab[]).map((tab) => (
            <Button
              key={tab}
              leftIcon={tab === 'Home' ? Home : undefined}
              onClick={() => setActiveTab(tab)}
              className={`px-3 py-2 text-sm transition-all rounded-t-lg border-b-2 bg-transparent hover:bg-blue-50/50 ${
                activeTab === tab
                  ? 'text-xibo-blue-600 border-xibo-blue-600 font-medium'
                  : 'text-gray-500 border-transparent hover:text-gray-700'
              }`}
              variant="tertiary"
            >
              {t(tab)}
            </Button>
          ))}
        </div>

        {/* Search Bar */}
        <div className="p-3 bg-white border-b border-gray-50">
          <div className="relative">
            <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
              <Search className="w-4 h-4 text-gray-400" />
            </div>
            <input
              value={folderSearch}
              onChange={(e) => setFolderSearch(e.target.value)}
              placeholder={t('Search folders...')}
              className="py-2 pl-9 pr-4 block w-full rounded-md border-gray-200 text-sm focus:border-xibo-blue-500 focus:ring-xibo-blue-500 bg-gray-50 placeholder:text-gray-400"
            />
          </div>
        </div>

        {/* Content Area */}
        <div className="max-h-[250px] overflow-y-auto pr-1 custom-scrollbar bg-white p-2">
          {isLoading ? (
            <div className="flex justify-center items-center py-8">
              <Loader2 className="w-5 h-5 text-xibo-blue-500 animate-spin" />
            </div>
          ) : (
            <FolderTreeList
              folders={folderSearch.trim() ? searchResults : treeData}
              selectedId={selectedId}
              onSelect={(folder) => {
                onSelect({ id: folder.id, text: folder.text });
                setInitialName(folder.text);
                setIsOpen(false);
              }}
              onAction={onAction}
            />
          )}
        </div>
      </div>
    </div>
  );
}
