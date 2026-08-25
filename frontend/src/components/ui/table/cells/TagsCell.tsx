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
import { useState } from 'react';
import { twMerge } from 'tailwind-merge';

import Badge from '../../Badge';

import type { Tag } from '@/types/tag';

export interface DisplayTag {
  id: string | number;
  label: string;
  tag: Tag;
}

// Shared by every page's Tags column - centralizes the "tagId + tag|value" label
// formatting so it isn't duplicated identically across every ColumnDef.
export function toDisplayTags(tags?: Tag[] | null): DisplayTag[] {
  return (tags ?? []).map((tag) => ({
    id: tag.tagId,
    label: tag.value ? `${tag.tag}|${tag.value}` : tag.tag,
    tag,
  }));
}

interface TagsProps {
  tags: DisplayTag[];
  limit?: number;
  noTagsPlaceholder?: string;
  onTagClick?: (tag: Tag) => void;
  selectedTagIds?: (string | number)[];
}

export function TagsCell({
  tags,
  limit = 2,
  noTagsPlaceholder = '',
  onTagClick,
  selectedTagIds,
}: TagsProps) {
  const safeTags = tags || [];
  const visibleTags = safeTags.slice(0, limit);
  const remainingTags = safeTags.slice(limit);
  const remainingCount = safeTags.length - limit;

  const [isOpen, setIsOpen] = useState(false);

  const { refs, floatingStyles, context } = useFloating({
    open: isOpen,
    onOpenChange: setIsOpen,
    placement: 'bottom-start',
    whileElementsMounted: autoUpdate,
    middleware: [offset(4), flip(), shift()],
  });

  const click = useClick(context);
  const dismiss = useDismiss(context);
  const { getReferenceProps, getFloatingProps } = useInteractions([click, dismiss]);

  if (!tags?.length) {
    return <span className="text-gray-800">{noTagsPlaceholder}</span>;
  }

  const renderTag = (displayTag: DisplayTag, closePopoverOnClick: boolean) => {
    const isSelected = selectedTagIds?.includes(displayTag.id);
    const badge = (
      <Badge
        type="info"
        variation={isSelected ? 'soft' : 'outline'}
        className={twMerge(
          'min-w-0 max-w-full',
          onTagClick
            ? twMerge(
                'transition-colors',
                isSelected
                  ? 'bg-xibo-blue-600 text-white border-transparent hover:bg-xibo-blue-700'
                  : 'hover:bg-xibo-blue-50',
              )
            : undefined,
        )}
      >
        <span className="block min-w-0 max-w-32 truncate" title={displayTag.label}>
          {displayTag.label}
        </span>
      </Badge>
    );

    if (!onTagClick) {
      return (
        <span key={displayTag.id} className="min-w-0 max-w-full">
          {badge}
        </span>
      );
    }

    return (
      <button
        key={displayTag.id}
        type="button"
        className="cursor-pointer min-w-0 max-w-full"
        onClick={() => {
          onTagClick(displayTag.tag);
          if (closePopoverOnClick) {
            setIsOpen(false);
          }
        }}
      >
        {badge}
      </button>
    );
  };

  return (
    <div className="relative flex flex-wrap gap-1 items-center">
      {visibleTags.map((tag) => renderTag(tag, false))}

      {remainingCount > 0 && (
        <>
          <button
            ref={refs.setReference}
            {...getReferenceProps()}
            type="button"
            className="hover:z-50 focus:outline-none"
          >
            <Badge className="cursor-pointer" type="light" variation="outline">
              +{remainingCount}
            </Badge>
          </button>

          <FloatingPortal>
            {isOpen && (
              <div
                ref={refs.setFloating}
                style={floatingStyles}
                {...getFloatingProps()}
                className={`
                  z-9999 min-w-60 p-2
                  bg-white shadow-xl rounded-lg border border-gray-100
                  dark:bg-neutral-800 dark:border-neutral-700
                `}
                role="menu"
              >
                <div className="flex flex-wrap gap-1">
                  {remainingTags.map((tag) => renderTag(tag, true))}
                </div>
              </div>
            )}
          </FloatingPortal>
        </>
      )}
    </div>
  );
}
