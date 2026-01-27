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
.*/

import { ChevronDown, Home, Search } from 'lucide-react';
import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';

import Button from '../Button';

type FolderTab = 'Home' | 'My Files';

interface SelectFolderProps {
  value: string;
  homeFolders: string[];
  myFileFolders: string[];
  isOpen: boolean;
  onToggle: () => void;
  onSelect: (folder: string) => void;
}

function SelectFolder({
  value,
  homeFolders,
  myFileFolders,
  isOpen,
  onToggle,
  onSelect,
}: SelectFolderProps) {
  const [activeTab, setActiveTab] = useState<FolderTab>('Home');
  const { t } = useTranslation();
  const visibleFolders = activeTab === 'Home' ? homeFolders : myFileFolders;
  const [folderSearch, setFolderSearch] = useState('');

  return (
    <div className="relative overflow-visible">
      <label className="text-xs font-semibold text-gray-500">{t('Select folder location')}</label>

      <div className="w-full border border-gray-200 rounded-lg flex items-center">
        <span className="p-3 border-r text-sm border-gray-200 text-gray-500">{t('My files')}</span>

        <span className="p-3 flex-1 text-sm cursor-pointer" onClick={onToggle}>
          {value || t('Select a folder')}
        </span>

        <button type="button" className="p-3 text-gray-500" onClick={onToggle}>
          <ChevronDown size={14} />
        </button>
      </div>

      {/* Folder Options */}
      <div
        className={`absolute top-[70px] w-full bg-white shadow-md rounded-lg overflow-clip transition-all duration-150 ease-in-out z-50
          ${isOpen ? 'opacity-100 max-h-[600px]' : 'opacity-0 max-h-0'}
        `}
      >
        <span className="bg-gray-100 p-2 text-sm font-semibold text-gray-500 uppercase w-full flex">
          {t('Select Folder')}
        </span>
        {/* Folder Tabs */}
        <div className="flex gap-x-3 px-3">
          <Button
            leftIcon={Home}
            onClick={() => setActiveTab('Home')}
            className={`bg-transparent hover:bg-transparent focus:outline-0 ${
              activeTab === 'Home'
                ? 'text-xibo-blue-600 font-semibold border-b-2 border-b-xibo-blue-600 rounded-none'
                : 'text-gray-500'
            }`}
          >
            {t('Home')}
          </Button>

          <Button
            onClick={() => setActiveTab('My Files')}
            className={`bg-transparent hover:bg-transparent focus:outline-0 ${
              activeTab === 'My Files'
                ? 'text-xibo-blue-600 font-semibold border-b-2 border-b-xibo-blue-600 rounded-none'
                : 'text-gray-500'
            }`}
          >
            {t('My Files')}
          </Button>
        </div>

        <div className="flex flex-col text-sm p-3">
          {/* TODO: make search funtional */}
          <div className="relative flex-1 flex w-full mb-2.5">
            <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
              <Search className="w-4 h-4 text-gray-400" />
            </div>
            <input
              name="search"
              value={folderSearch}
              onChange={(e) => setFolderSearch(e.target.value)}
              placeholder={t('Search media...')}
              className="py-2 px-3 pl-10 block h-[45px] rounded-lg w-full text-sm border-gray-200 disabled:opacity-50 disabled:pointer-events-none"
            />
          </div>
          {visibleFolders.map((folder) => (
            <button
              key={folder}
              type="button"
              className="text-left text-sm font-semibold p-2 hover:bg-gray-100 rounded-lg gap-x-3 flex items-center cursor-pointer"
              onClick={() => onSelect(folder)}
            >
              {activeTab === 'Home' ? (
                <div className="h-[26px] w-[26px] center flex">
                  <div className="bg-xibo-blue-100 h-[26px] w-[26px] text-[12px] center rounded-full text-xibo-blue-800 font-semibold">
                    {folder.slice(0, 1)}
                  </div>
                </div>
              ) : null}
              <span className="flex-1">{folder}</span>
              <ChevronDown size={14} className="text-gray-500" />
            </button>
          ))}
        </div>
      </div>
    </div>
  );
}

export default SelectFolder;
