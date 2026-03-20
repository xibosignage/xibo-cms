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
  useClick,
  useDismiss,
  useInteractions,
  FloatingPortal,
} from '@floating-ui/react';
import { Check, MoreVertical, Play } from 'lucide-react';
import { useState } from 'react';
import { twMerge } from 'tailwind-merge';

import { getMediaIcon } from '../MediaConfig';

import type { Media } from '@/types/media';
import type { ActionItem } from '@/types/table';

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

  const isPlayable = media.mediaType === 'video' || media.mediaType === 'audio';
  const IconComponent = getMediaIcon(media.mediaType);

  const { refs, floatingStyles, context } = useFloating({
    open: isMenuOpen,
    onOpenChange: setIsMenuOpen,
    placement: 'top-end',
    whileElementsMounted: autoUpdate,
    middleware: [offset(4), flip(), shift()],
  });

  const click = useClick(context);
  const dismiss = useDismiss(context);
  const { getReferenceProps, getFloatingProps } = useInteractions([click, dismiss]);

  return (
    <div
      className={twMerge(
        'relative flex flex-col bg-white rounded-lg border-2 group',
        isSelected ? 'bg-blue-100 border-blue-100' : 'border-slate-50 hover:bg-black/5',
        isMenuOpen ? 'z-10' : '',
      )}
    >
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
              <div className="absolute flex items-center justify-center rounded-full bg-white/10 size-9.5">
                <Play className="size-5 text-white fill-white" />
              </div>
            )}
          </div>
        ) : (
          <div className="flex items-center justify-center size-13.5 text-gray-500">
            <IconComponent className="size-full" />
          </div>
        )}
      </div>

      <div className="flex flex-1 p-2 justify-between items-center">
        <div className="text-sm font-medium text-gray-800 truncate" title={media.name}>
          {media.name}
        </div>
        <button
          ref={refs.setReference}
          {...getReferenceProps()}
          className={twMerge(
            'size-6 flex items-center justify-center rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-200 transition-colors cursor-pointer',
            isMenuOpen && 'bg-gray-100 text-gray-800',
          )}
        >
          <MoreVertical className="size-4" />
        </button>

        <FloatingPortal>
          {isMenuOpen && (
            <div
              ref={refs.setFloating}
              style={floatingStyles}
              {...getFloatingProps()}
              className="z-100 bg-white shadow-lg rounded-xl max-h-62.5 overflow-y-auto"
            >
              {actions.map((action, idx) => {
                if (action.isSeparator) {
                  return <div key={idx} className="my-1 h-px bg-gray-100" role="separator" />;
                }

                if (action.isQuickAction) {
                  return null;
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
            </div>
          )}
        </FloatingPortal>
      </div>
    </div>
  );
}
