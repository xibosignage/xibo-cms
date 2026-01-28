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

import { useState, useRef } from 'react';
import { createPortal } from 'react-dom';

import Badge from '../../Badge';

import { useClickOutside } from '@/hooks/useClickOutside';
import { useCloseOnScroll } from '@/hooks/useCloseOnScroll';

interface Tag {
  id: string | number;
  label: string;
}

interface TagsProps {
  tags: Tag[];
  limit?: number;
}

export function TagsCell({ tags, limit = 2 }: TagsProps) {
  const safeTags = tags || [];
  const visibleTags = safeTags.slice(0, limit);
  const remainingTags = safeTags.slice(limit);
  const remainingCount = safeTags.length - limit;

  const [isOpen, setIsOpen] = useState(false);
  const [coords, setCoords] = useState({ top: 0, left: 0 });

  const buttonRef = useRef<HTMLButtonElement>(null);
  const dropdownRef = useRef<HTMLDivElement>(null);

  // Close when clicking outside
  useClickOutside(dropdownRef, (e) => {
    const target = e.target as Node;
    if (buttonRef.current && !buttonRef.current.contains(target)) {
      setIsOpen(false);
    }
  });

  // Close when scrolling
  useCloseOnScroll(isOpen, () => setIsOpen(false));

  const toggleOpen = (e: React.MouseEvent) => {
    e.stopPropagation();

    if (!isOpen && buttonRef.current) {
      const rect = buttonRef.current.getBoundingClientRect();
      setCoords({
        top: rect.bottom + 4,
        left: rect.left,
      });
    }
    setIsOpen(!isOpen);
  };

  if (!tags?.length) {
    return <span className="text-gray-800"></span>;
  }

  return (
    <div className="relative flex flex-wrap gap-1 items-center">
      {visibleTags.map((tag) => (
        <Badge key={tag.id} type="info" variation="outline">
          {tag.label}
        </Badge>
      ))}

      {remainingCount > 0 && (
        <>
          <button
            ref={buttonRef}
            type="button"
            onClick={toggleOpen}
            className="hover:z-50 focus:outline-none"
          >
            <Badge className="cursor-pointer" type="light" variation="outline">
              +{remainingCount}
            </Badge>
          </button>

          {isOpen &&
            createPortal(
              <div
                ref={dropdownRef}
                style={{
                  position: 'fixed',
                  top: coords.top,
                  left: coords.left,
                }}
                className={`
                  z-9999 min-w-60 p-2
                  bg-white shadow-xl rounded-lg border border-gray-100
                  dark:bg-neutral-800 dark:border-neutral-700
                  animate-in fade-in zoom-in-95 duration-100
                `}
                role="menu"
              >
                <div className="flex flex-wrap gap-1">
                  {remainingTags.map((tag) => (
                    <Badge key={tag.id} type="info" variation="outline">
                      {tag.label}
                    </Badge>
                  ))}
                </div>
              </div>,
              document.body,
            )}
        </>
      )}
    </div>
  );
}
