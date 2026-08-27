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
  FloatingPortal,
  autoUpdate,
  flip,
  offset,
  shift,
  size,
  useClick,
  useDismiss,
  useFloating,
  useInteractions,
} from '@floating-ui/react';
import { ChevronDown, FileText, Info, Power } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import Button from '@/components/ui/Button';

// The header's "Power / Reboot" menu — a frontend-only UI shell: no native
// reboot/power-off primitive exists in Xibo today, so every item here renders disabled
// (greyed out, no click handler) rather than being wired to a service call,
// including Wake on LAN — keeping the whole menu's behaviour consistent
// rather than partially working.
function PowerRebootMenu() {
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);

  const { refs, floatingStyles, context } = useFloating({
    open,
    onOpenChange: setOpen,
    placement: 'bottom-end',
    whileElementsMounted: autoUpdate,
    middleware: [
      offset(4),
      flip(),
      shift(),
      size({
        apply({ availableHeight, rects, elements }) {
          Object.assign(elements.floating.style, {
            maxHeight: `${availableHeight}px`,
            width: `${rects.reference.width}px`,
          });
        },
        padding: 8,
      }),
    ],
  });

  const click = useClick(context);
  const dismiss = useDismiss(context);
  const { getReferenceProps, getFloatingProps } = useInteractions([click, dismiss]);

  const items = [t('Reboot player'), t('Power off'), t('Wake on LAN')];

  return (
    <div className="relative inline-block">
      <Button
        ref={refs.setReference}
        {...getReferenceProps()}
        variant="secondary"
        leftIcon={Power}
        rightIcon={ChevronDown}
        aria-haspopup="menu"
        aria-expanded={open}
        className="h-11.25"
      >
        {t('Power / Reboot')}
      </Button>

      <FloatingPortal>
        {open && (
          <div
            ref={refs.setFloating}
            style={floatingStyles}
            {...getFloatingProps()}
            role="menu"
            className="z-100 overflow-hidden overflow-y-auto rounded-xl bg-white shadow-lg"
          >
            <div className="p-2">
              {items.map((label) => (
                <button
                  key={label}
                  type="button"
                  role="menuitem"
                  disabled
                  title={t('Not available yet')}
                  className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-gray-400 cursor-not-allowed"
                >
                  {label}
                </button>
              ))}
            </div>
          </div>
        )}
      </FloatingPortal>
    </div>
  );
}

interface ManageHeaderActionsProps {
  onOpenProofOfPlay: () => void;
  canViewProofOfPlay: boolean;
  onOpenDiagnostics: () => void;
  needsAttention: boolean;
}

export default function ManageHeaderActions({
  onOpenProofOfPlay,
  canViewProofOfPlay,
  onOpenDiagnostics,
  needsAttention,
}: ManageHeaderActionsProps) {
  const { t } = useTranslation();

  return (
    <div className="flex flex-wrap items-center gap-2">
      {/* "Request Screenshot" was here. The page asks for one every few seconds by itself while
          it is open, so a button to ask again had nothing to add. ScreenshotCard opens the
          full-size viewer that this button used to open alongside the request. */}
      <PowerRebootMenu />

      {canViewProofOfPlay && (
        <Button
          variant="primary"
          leftIcon={FileText}
          onClick={onOpenProofOfPlay}
          className="h-11.25"
        >
          {t('Proof of Play')}
        </Button>
      )}

      <button
        type="button"
        onClick={onOpenDiagnostics}
        aria-label={t('Troubleshooting & Diagnostics')}
        className="relative flex size-11.25 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 cursor-pointer"
      >
        <Info className="size-4" aria-hidden="true" />
        {needsAttention && (
          <span
            className="absolute right-1.5 top-1.5 size-2 rounded-full bg-red-500 ring-2 ring-white"
            aria-hidden="true"
          />
        )}
      </button>
    </div>
  );
}
