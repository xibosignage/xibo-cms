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

import { ChevronDown, Loader2 } from 'lucide-react';
import { useEffect, useRef, useState, useLayoutEffect, useCallback, useId } from 'react';
import { createPortal } from 'react-dom';
import { useTranslation } from 'react-i18next';

import FolderSearchInput from '../FolderSearchInput';
import FolderTreeList, { type FolderAction } from '../FolderTreeList';

import { fetchFolderById } from '@/services/folderApi';
import type { Folder } from '@/types/folder';

interface SelectFolderProps {
  selectedId?: number | null;
  onSelect: (folder: { id: number; text: string }) => void;
  onAction?: (action: FolderAction, folder: Folder) => void;
}

export default function SelectFolder({ selectedId, onSelect, onAction }: SelectFolderProps) {
  const { t } = useTranslation();
  const generatedId = useId();
  const searchInputId = `${generatedId}_search`;

  const [isOpen, setIsOpen] = useState(false);
  const [initialName, setInitialName] = useState<string | null>(null);
  const [isResolvingName, setIsResolvingName] = useState(false);
  const [folderSearch, setFolderSearch] = useState('');

  const containerRef = useRef<HTMLDivElement>(null);
  const dropdownRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!selectedId) {
      setInitialName(null);
      setIsResolvingName(false);
      return;
    }

    let active = true;

    setIsResolvingName(true);
    setInitialName(null);

    fetchFolderById(selectedId)
      .then((folder) => {
        if (active) {
          setInitialName(folder.text);
        }
      })
      .catch((err) => console.error('Failed to resolve folder name', err))
      .finally(() => {
        if (active) {
          setIsResolvingName(false);
        }
      });
    return () => {
      active = false;
    };
  }, [selectedId]);

  // Positioning
  const updatePosition = useCallback(() => {
    if (!isOpen || !containerRef.current || !dropdownRef.current) return;

    const containerRect = containerRef.current.getBoundingClientRect();
    const dropdownRect = dropdownRef.current.getBoundingClientRect();
    const viewportHeight = window.innerHeight;
    const gap = 4;

    // Dimensions
    const contentHeight = dropdownRect.height || 300;
    const spaceBelow = viewportHeight - containerRect.bottom - gap;
    const spaceAbove = containerRect.top - gap;
    const maxHeight = 400;

    let top: number;
    let finalMaxHeight = maxHeight;
    let transformOrigin = 'top';

    if (spaceBelow < contentHeight && spaceAbove > spaceBelow) {
      top = containerRect.top - gap - Math.min(contentHeight, spaceAbove);
      finalMaxHeight = Math.min(maxHeight, spaceAbove);
      transformOrigin = 'bottom';
    } else {
      top = containerRect.bottom + gap;
      finalMaxHeight = Math.min(maxHeight, spaceBelow);
    }

    Object.assign(dropdownRef.current.style, {
      top: `${top}px`,
      left: `${containerRect.left}px`,
      width: `${containerRect.width}px`,
      maxHeight: `${finalMaxHeight}px`,
      opacity: '1',
      transformOrigin,
    });
  }, [isOpen]);

  useLayoutEffect(() => {
    if (!isOpen) {
      return;
    }

    requestAnimationFrame(() => updatePosition());

    const resizeObserver = new ResizeObserver(() => updatePosition());
    if (dropdownRef.current) {
      resizeObserver.observe(dropdownRef.current);
    }

    return () => resizeObserver.disconnect();
  }, [isOpen, updatePosition]);

  useEffect(() => {
    if (!isOpen) return;

    const handleScroll = (event: Event) => {
      if (dropdownRef.current && dropdownRef.current.contains(event.target as Node)) {
        return;
      }
      setIsOpen(false);
    };

    const handleClickOutside = (e: MouseEvent) => {
      const target = e.target as Node;
      if (
        containerRef.current &&
        !containerRef.current.contains(target) &&
        dropdownRef.current &&
        !dropdownRef.current.contains(target)
      ) {
        setIsOpen(false);
      }
    };

    const handleResize = () => setIsOpen(false);

    document.addEventListener('mousedown', handleClickOutside);
    window.addEventListener('resize', handleResize);

    const scrollTimeout = setTimeout(() => {
      window.addEventListener('scroll', handleScroll, true);
    }, 100);

    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
      window.removeEventListener('resize', handleResize);
      window.removeEventListener('scroll', handleScroll, true);
      clearTimeout(scrollTimeout);
    };
  }, [isOpen]);

  // Helper to get the text to display on the button
  const renderLabel = () => {
    if (isResolvingName) {
      return (
        <span className="flex items-center gap-2 text-gray-400 italic text-xs">
          <Loader2 className="animate-spin w-3 h-3" />
          {t('Loading...')}
        </span>
      );
    }

    if (initialName) {
      return <span className="text-gray-800">{initialName}</span>;
    }

    if (selectedId) {
      return <span className="text-gray-800">{t('Folder #{{id}}', { id: selectedId })}</span>;
    }

    return <span className="text-gray-400">{t('Select a folder')}</span>;
  };

  return (
    <div className="relative" ref={containerRef}>
      <label className="block text-xs font-semibold text-gray-500 mb-1">
        {t('Select folder location')}
      </label>

      {/* Portal Trigger */}
      <div
        className="w-full border border-gray-200 rounded-lg flex items-center bg-white transition-shadow hover:shadow-sm cursor-pointer h-10.5"
        onClick={() => setIsOpen(!isOpen)}
      >
        <button
          type="button"
          className="px-3 flex-1 text-sm text-left hover:bg-gray-50 transition-colors truncate h-full flex items-center"
        >
          {renderLabel()}
        </button>

        <div className="px-3 text-gray-500">
          <ChevronDown
            size={14}
            className={`transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`}
          />
        </div>
      </div>

      {/* Portal Dropdown */}
      {isOpen &&
        createPortal(
          <div
            ref={dropdownRef}
            style={{
              position: 'fixed',
              zIndex: 9999,
              opacity: 0,
            }}
            className="bg-white shadow-xl rounded-lg border border-gray-100 overflow-hidden flex flex-col animate-in fade-in zoom-in-95 duration-100"
          >
            <div className="bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-500 uppercase w-fullshrink-0">
              {t('Select Folder')}
            </div>

            <div className="flex flex-col min-h-0 flex-1">
              <FolderTreeList
                selectedId={selectedId}
                onSelect={(folder: Folder) => {
                  onSelect({ id: folder.id, text: folder.text });
                  setInitialName(folder.text);
                  setIsOpen(false);
                }}
                onAction={onAction}
                searchQuery={folderSearch}
                customSlot={
                  <div className="px-2 shrink-0">
                    <FolderSearchInput
                      id={searchInputId}
                      value={folderSearch}
                      autoFocus={true}
                      onChange={setFolderSearch}
                      placeholder={t('Search Folder')}
                      className="py-1"
                    />
                  </div>
                }
              />
            </div>
          </div>,
          document.body,
        )}
    </div>
  );
}
