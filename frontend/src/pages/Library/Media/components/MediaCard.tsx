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

import { Check, MoreVertical, Play } from 'lucide-react';
import { useState, useRef, useEffect, useCallback } from 'react';
import { createPortal } from 'react-dom';
import { twMerge } from 'tailwind-merge';

import { getMediaIcon, type ActionItem } from '../MediaConfig';

import type { Media } from '@/types/media';

interface MediaCardProps {
  media: Media;
  isSelected: boolean;
  onToggleSelect: (val: boolean) => void;
  onPreview: (media: Media) => void;
  actions: ActionItem[];
}

export default function MediaCard({
  media,
  isSelected,
  onToggleSelect,
  onPreview,
  actions,
}: MediaCardProps) {
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const [menuPosition, setMenuPosition] = useState({ top: 0, left: 0 });

  const buttonRef = useRef<HTMLButtonElement>(null);
  const menuRef = useRef<HTMLDivElement>(null);
  const dropdownActions = actions;
  const isPlayable = media.mediaType === 'video' || media.mediaType === 'audio';
  const IconComponent = getMediaIcon(media.mediaType);

  // Portal position
  const updatePosition = useCallback(() => {
    if (buttonRef.current && isMenuOpen) {
      const rect = buttonRef.current.getBoundingClientRect();
      const scrollY = window.scrollY;
      const scrollX = window.scrollX;

      setMenuPosition({
        top: rect.top + scrollY - 5,
        left: rect.right + scrollX,
      });
    }
  }, [isMenuOpen]);

  // Update position or Portal on scroll
  useEffect(() => {
    if (isMenuOpen) {
      updatePosition();
      window.addEventListener('scroll', updatePosition, true);
      window.addEventListener('resize', updatePosition);
    }

    return () => {
      window.removeEventListener('scroll', updatePosition, true);
      window.removeEventListener('resize', updatePosition);
    };
  }, [isMenuOpen, updatePosition]);

  // We need a custom effect handler for click outside for a Portal
  useEffect(() => {
    if (!isMenuOpen) return;

    const handleClickOutside = (event: MouseEvent) => {
      if (
        menuRef.current &&
        !menuRef.current.contains(event.target as Node) &&
        buttonRef.current &&
        !buttonRef.current.contains(event.target as Node)
      ) {
        setIsMenuOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [isMenuOpen]);

  return (
    <div
      className={twMerge(
        'relative flex flex-col bg-white  rounded-lg border-2 group',
        isSelected ? 'bg-blue-100 border-blue-100' : 'border-slate-50 hover:bg-black/5',
        isMenuOpen ? 'z-10' : '',
      )}
    >
      {/* Select checkbox */}
      <div
        className={twMerge(
          'absolute top-3 left-3 z-10 transition-opacity duration-200',
          isSelected || isMenuOpen ? 'opacity-100' : 'opacity-0 group-hover:opacity-100',
        )}
      >
        <div
          onClick={(e) => {
            e.stopPropagation();
            onToggleSelect(!isSelected);
          }}
          className={twMerge(
            'size-5 rounded border cursor-pointer flex items-center justify-center transition-colors shadow-sm',
            isSelected
              ? 'bg-blue-500 border-blue-500'
              : 'bg-white border-gray-300 hover:border-blue-400',
          )}
        >
          {isSelected && <Check className="size-3.5 text-white" />}
        </div>
      </div>

      {/* Preview */}
      <div
        className="aspect-video w-full bg-gray-400 rounded-t-lg overflow-hidden cursor-pointer relative flex items-center justify-center"
        onClick={() => onPreview(media)}
      >
        {media.thumbnail ? (
          <div className="flex h-full justify-center items-center">
            <img
              src={media.thumbnail}
              alt={media.name}
              className="w-full h-full object-cover transition-transform group-hover:scale-105"
            />
            {isPlayable && (
              <div className="absolute flex items-center justify-center rounded-full bg-white/10 size-[38px]">
                <Play className="size-5 text-white fill-white" />
              </div>
            )}
          </div>
        ) : (
          <div className="flex items-center justify-center size-[54px] text-gray-500">
            <IconComponent className="size-full" />
          </div>
        )}
      </div>

      {/* Footer */}
      <div className="flex flex-1 p-2 justify-between items-center">
        <div className="text-sm font-medium text-gray-800 truncate" title={media.name}>
          {media.name}
        </div>
        <button
          ref={buttonRef}
          onClick={(e) => {
            e.stopPropagation();
            if (!isMenuOpen && buttonRef.current) {
              const rect = buttonRef.current.getBoundingClientRect();
              setMenuPosition({
                top: rect.top + window.scrollY - 5,
                left: rect.right + window.scrollX,
              });
            }
            setIsMenuOpen((prev) => !prev);
          }}
          className={twMerge(
            'size-6 flex items-center justify-center rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-200 transition-colors cursor-pointer',
            isMenuOpen && 'bg-gray-100 text-gray-800',
          )}
        >
          <MoreVertical className="size-4" />
        </button>

        {/* Card actions */}
        {isMenuOpen &&
          createPortal(
            <div
              ref={menuRef}
              style={{
                top: menuPosition.top,
                left: menuPosition.left,
                transform: 'translate(-100%, -100%)',
              }}
              className="fixed bg-white shadow-lg z-100 rounded-xl max-h-[250px] overflow-y-auto origin-bottom-right"
              onClick={(e) => e.stopPropagation()}
            >
              {dropdownActions.map((action, idx) => {
                if (action.isSeparator) {
                  return <div key={idx} className="my-1 h-px bg-gray-100" role="separator" />;
                }

                if (action.isQuickAction) {
                  return;
                }

                const Icon = action.icon;
                return (
                  <button
                    key={idx}
                    onClick={() => {
                      setIsMenuOpen(false);
                      action.onClick?.();
                    }}
                    className={twMerge(
                      'flex items-center w-full gap-3 rounded-lg text-left px-3 py-2 text-sm transition-colors cursor-pointer',
                      action.variant === 'danger'
                        ? 'text-red-800 hover:bg-red-50 focus:bg-red-100'
                        : action.variant === 'primary'
                          ? 'text-blue-800 hover:bg-blue-50 focus:bg-blue-100'
                          : 'text-gray-800 hover:bg-gray-50 focus:bg-gray-100',
                    )}
                  >
                    {Icon && <Icon className="size-4 opacity-70" />}
                    <span className="truncate">{action.label}</span>
                  </button>
                );
              })}
            </div>,
            document.body,
          )}
      </div>
    </div>
  );
}
