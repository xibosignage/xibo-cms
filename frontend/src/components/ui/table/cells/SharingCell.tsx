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
import type { ColumnDef } from '@tanstack/react-table';
import type { TFunction } from 'i18next';
import { useLayoutEffect, useRef, useState } from 'react';

import Badge from '../../Badge';

import { TextCell } from './TextCell';

interface SharingCellProps {
  groups?: string | null;
  privatePlaceholder?: string;
}

const GAP_PX = 4;

function parseGroups(groups?: string | null) {
  if (!groups) {
    return [];
  }

  return groups
    .split(',')
    .map((entry) => entry.trim())
    .filter(Boolean);
}

// Measures each pill's natural (post-truncation-cap) width via a hidden layer,
// then works out how many visible pills fit the current column width, leaving
// room for the "+N" chip. Recomputes whenever the column is resized.
function useVisibleCount(
  entries: string[],
  containerRef: React.RefObject<HTMLDivElement | null>,
  pillRefs: React.RefObject<(HTMLSpanElement | null)[]>,
  plusRef: React.RefObject<HTMLSpanElement | null>,
) {
  const [visibleCount, setVisibleCount] = useState(entries.length > 0 ? 1 : 0);

  useLayoutEffect(() => {
    const container = containerRef.current;

    if (!container || entries.length === 0) {
      return;
    }

    const recompute = () => {
      const containerWidth = container.clientWidth;
      const plusWidth = plusRef.current?.offsetWidth ?? 0;

      let used = 0;
      let count = 0;

      for (let i = 0; i < entries.length; i++) {
        const pillWidth = pillRefs.current[i]?.offsetWidth ?? 0;
        const isLastEntry = i === entries.length - 1;
        const nextUsed = used + (count > 0 ? GAP_PX : 0) + pillWidth;
        const reserve = isLastEntry ? 0 : GAP_PX + plusWidth;

        if (count === 0 || nextUsed + reserve <= containerWidth) {
          used = nextUsed;
          count++;
        } else {
          break;
        }
      }

      setVisibleCount(count);
    };

    recompute();

    const observer = new ResizeObserver(recompute);
    observer.observe(container);
    return () => observer.disconnect();
  }, [entries, containerRef, pillRefs, plusRef]);

  return visibleCount;
}

export function SharingCell({ groups, privatePlaceholder = '' }: SharingCellProps) {
  const entries = parseGroups(groups);

  const containerRef = useRef<HTMLDivElement>(null);
  const pillRefs = useRef<(HTMLSpanElement | null)[]>([]);
  const plusRef = useRef<HTMLSpanElement | null>(null);
  const visibleCount = useVisibleCount(entries, containerRef, pillRefs, plusRef);

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

  if (entries.length === 0) {
    return <TextCell className="italic text-gray-500">{privatePlaceholder}</TextCell>;
  }

  const visibleEntries = entries.slice(0, visibleCount);
  const remainingEntries = entries.slice(visibleCount);
  const remainingCount = remainingEntries.length;

  return (
    <div ref={containerRef} className="relative flex flex-wrap gap-1 items-center min-w-0">
      {/* Hidden measurement layer — mirrors the visible pill markup so its
          offsetWidths tell us how many pills actually fit. Zero-sized +
          overflow-hidden keeps it out of the page's visual/scroll area. */}
      <div className="absolute top-0 left-0 h-0 w-0 overflow-hidden" aria-hidden="true">
        <div className="w-max flex gap-1">
          {entries.map((entry, index) => (
            <span
              key={`${index}-${entry}`}
              ref={(el) => {
                pillRefs.current[index] = el;
              }}
            >
              <Badge type="info" variation="outline" className="max-w-[140px] truncate">
                {entry}
              </Badge>
            </span>
          ))}
          <span ref={plusRef}>
            <Badge type="light" variation="outline">
              +{entries.length}
            </Badge>
          </span>
        </div>
      </div>

      {visibleEntries.map((entry, index) => (
        <span key={`${index}-${entry}`} title={entry}>
          <Badge type="info" variation="outline" className="max-w-[140px] truncate">
            {entry}
          </Badge>
        </span>
      ))}

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
                  z-9999 w-[500px] p-2
                  bg-white shadow-xl rounded-lg border border-gray-100
                  dark:bg-neutral-800 dark:border-neutral-700
                `}
                role="menu"
              >
                <div className="flex flex-wrap gap-1">
                  {remainingEntries.map((entry, index) => (
                    <Badge key={`${index}-${entry}`} type="info" variation="outline">
                      {entry}
                    </Badge>
                  ))}
                </div>
              </div>
            )}
          </FloatingPortal>
        </>
      )}
    </div>
  );
}

/** Shared "Sharing" column definition for list pages whose rows carry a `groupsWithPermissions` field. */
export function getSharingColumn<T>(t: TFunction): ColumnDef<T> {
  return {
    accessorKey: 'groupsWithPermissions',
    header: t('Sharing'),
    size: 200,
    minSize: 200,
    cell: (info) => (
      <SharingCell groups={info.getValue<string | null>()} privatePlaceholder={t('Private')} />
    ),
  };
}
