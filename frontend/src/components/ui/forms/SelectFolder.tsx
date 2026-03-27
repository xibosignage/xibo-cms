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
  useFloating,
  autoUpdate,
  offset,
  flip,
  shift,
  size,
  useClick,
  useDismiss,
  useInteractions,
  FloatingPortal,
} from '@floating-ui/react';
import { ChevronDown, Loader2, X } from 'lucide-react';
import { useEffect, useRef, useState, useId } from 'react';
import { useTranslation } from 'react-i18next';

import FolderSearchInput from '../FolderSearchInput';
import FolderTreeList, { type FolderAction } from '../FolderTreeList';

import { useUserContext } from '@/context/UserContext';
import { fetchFolderById } from '@/services/folderApi';
import type { Folder } from '@/types/folder';

interface SelectFolderProps {
  selectedId?: number | null;
  selectedText?: string | null;
  placeholder?: string;
  onSelect: (folder: { id: number; text: string } | null) => void;
  onAction?: (action: FolderAction, folder: Folder) => void;
}

export default function SelectFolder({
  selectedId,
  selectedText,
  placeholder,
  onSelect,
  onAction,
}: SelectFolderProps) {
  const { t } = useTranslation();
  const { user } = useUserContext();

  const homeFolderId = user?.homeFolderId ?? 1;
  const generatedId = useId();
  const searchInputId = `${generatedId}_search`;

  const [isOpen, setIsOpen] = useState(false);
  const [initialName, setInitialName] = useState<string | null>(null);
  const resolvedIdRef = useRef<number | null>(null);
  const homeFolderNameRef = useRef<string | null>(null);
  const homeFolderIdRef = useRef(homeFolderId);
  homeFolderIdRef.current = homeFolderId;
  const [isResolvingName, setIsResolvingName] = useState(false);
  const [folderSearch, setFolderSearch] = useState('');

  const { refs, floatingStyles, context } = useFloating({
    open: isOpen,
    onOpenChange: (open) => {
      setIsOpen(open);
      if (!open) {
        setFolderSearch('');
      }
    },
    placement: 'bottom-start',
    whileElementsMounted: autoUpdate,
    middleware: [
      offset(4),
      flip(),
      shift(),
      size({
        apply({ rects, elements, availableHeight }) {
          Object.assign(elements.floating.style, {
            width: `${rects.reference.width}px`,
            maxHeight: `${Math.min(availableHeight, 400)}px`,
          });
        },
      }),
    ],
  });

  const click = useClick(context);
  const dismiss = useDismiss(context);
  const { getReferenceProps, getFloatingProps } = useInteractions([click, dismiss]);

  const handleClear = (e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();

    setFolderSearch('');
    setIsOpen(false);

    if (homeFolderNameRef.current) {
      setInitialName(homeFolderNameRef.current);
      resolvedIdRef.current = homeFolderId;
      onSelect({ id: homeFolderId, text: homeFolderNameRef.current });
      return;
    }

    setInitialName(null);
    setIsResolvingName(true);
    resolvedIdRef.current = null;
    fetchFolderById(homeFolderId)
      .then((folder) => {
        homeFolderNameRef.current = folder.text;
        setInitialName(folder.text);
        resolvedIdRef.current = folder.id;
        onSelect({ id: folder.id, text: folder.text });
      })
      .catch((err) => {
        console.error('Failed to resolve home folder name', err);
        onSelect({ id: homeFolderId, text: String(homeFolderId) });
      })
      .finally(() => setIsResolvingName(false));
  };

  useEffect(() => {
    if (!selectedId) {
      setInitialName(null);
      resolvedIdRef.current = null;
      setIsResolvingName(false);
      return;
    }

    if (selectedText) {
      setInitialName(selectedText);
      resolvedIdRef.current = selectedId;
      setIsResolvingName(false);
      return;
    }

    if (resolvedIdRef.current === selectedId) {
      return;
    }

    let active = true;

    setIsResolvingName(true);
    setInitialName(null);

    fetchFolderById(selectedId)
      .then((folder) => {
        if (active) {
          if (selectedId === homeFolderIdRef.current) {
            homeFolderNameRef.current = folder.text;
          }
          setInitialName(folder.text);
          resolvedIdRef.current = selectedId;
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
  }, [selectedId, selectedText]);

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

    return <span className="text-gray-400">{placeholder || t('Select a folder')}</span>;
  };

  return (
    <div className="relative">
      <label className="block text-sm font-semibold text-gray-500 mb-1">
        {t('Select folder location')}
      </label>

      <div
        ref={refs.setReference}
        {...getReferenceProps()}
        className="w-full border border-gray-200 rounded-lg flex items-center bg-white transition-shadow hover:shadow-sm cursor-pointer h-11.25"
      >
        <button
          type="button"
          className="px-3 flex-1 text-sm text-left hover:bg-gray-50 transition-colors truncate h-full flex items-center"
        >
          {renderLabel()}
        </button>
        <div className="flex items-center pr-3 h-full gap-1.5">
          {selectedId ? (
            <button
              type="button"
              onClick={handleClear}
              className="px-3 text-gray-400 hover:text-red-500 transition-colors flex items-center h-full focus:outline-none"
              aria-label={t('Clear selection')}
            >
              <X size={14} />
            </button>
          ) : null}

          <div className="pl-3 text-gray-500 flex items-center h-full">
            <ChevronDown
              size={14}
              className={`transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`}
            />
          </div>
        </div>
      </div>

      <FloatingPortal>
        {isOpen && (
          <div
            ref={refs.setFloating}
            style={floatingStyles}
            {...getFloatingProps()}
            className="z-9999 bg-white shadow-xl rounded-lg border border-gray-100 overflow-hidden flex flex-col"
          >
            <div className="bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-500 uppercase w-full shrink-0">
              {t('Select Folder')}
            </div>

            <div className="flex flex-col min-h-0 flex-1 overflow-y-auto">
              <FolderTreeList
                selectedId={selectedId}
                onSelect={(folder: Folder) => {
                  onSelect({ id: folder.id, text: folder.text });
                  setInitialName(folder.text);
                  resolvedIdRef.current = folder.id;
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
          </div>
        )}
      </FloatingPortal>
    </div>
  );
}
