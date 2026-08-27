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

import { FileText, Info } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import Button from '@/components/ui/Button';

interface ManageHeaderActionsProps {
  onOpenProofOfPlay: () => void;
  canViewProofOfPlay: boolean;
  onOpenDiagnostics: () => void;
}

export default function ManageHeaderActions({
  onOpenProofOfPlay,
  canViewProofOfPlay,
  onOpenDiagnostics,
}: ManageHeaderActionsProps) {
  const { t } = useTranslation();

  return (
    <div className="flex flex-wrap items-center gap-2">
      {/* "Request Screenshot" was here. The page asks for one every few seconds by itself while
          it is open, so a button to ask again had nothing to add. ScreenshotCard opens the
          full-size viewer that this button used to open alongside the request. */}
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
        className="flex size-11.25 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 cursor-pointer"
      >
        <Info className="size-4" aria-hidden="true" />
      </button>
    </div>
  );
}
