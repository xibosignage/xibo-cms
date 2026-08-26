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

import { X, Loader2, Camera, Info, ImageOff } from 'lucide-react';
import { useEffect, useState, useRef } from 'react';
import { useTranslation } from 'react-i18next';

import DisplayInfoPanel from './DisplayInfoPanel';

import { useKeydown } from '@/hooks/useKeydown';
import { fetchDisplayScreenshotBlob, fetchHistoryScreenshotBlob } from '@/services/displaysApi';
import type { Display } from '@/types/display';

interface DisplayScreenshotPreviewerProps {
  display: Display | null;
  onClose: () => void;
  onRequestScreenshot?: (display: Display) => void;
  /** Show one screenshot out of the display's history instead of its current one. */
  screenshotId?: number | null;
}

export default function DisplayScreenshotPreviewer({
  display,
  onClose,
  onRequestScreenshot,
  screenshotId = null,
}: DisplayScreenshotPreviewerProps) {
  const { t } = useTranslation();

  const [showInfoPanel, setShowInfoPanel] = useState(false);
  const [url, setUrl] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [hasError, setHasError] = useState(false);
  const activeUrlRef = useRef<string | null>(null);

  const revokeUrl = () => {
    if (activeUrlRef.current) {
      window.URL.revokeObjectURL(activeUrlRef.current);
      activeUrlRef.current = null;
    }
    setUrl(null);
  };

  useEffect(() => {
    if (!display) {
      revokeUrl();
      setShowInfoPanel(false);
      return;
    }

    let isMounted = true;
    const controller = new AbortController();

    const load = async () => {
      revokeUrl();
      setLoading(true);
      setHasError(false);

      try {
        const blob =
          screenshotId === null
            ? await fetchDisplayScreenshotBlob(display.displayId, controller.signal)
            : await fetchHistoryScreenshotBlob(display.displayId, screenshotId, controller.signal);

        if (!isMounted) {
          return;
        }

        const objectUrl = window.URL.createObjectURL(blob);
        activeUrlRef.current = objectUrl;
        setUrl(objectUrl);
      } catch {
        if (isMounted) {
          setHasError(true);
        }
      } finally {
        if (isMounted) {
          setLoading(false);
        }
      }
    };

    load();

    return () => {
      isMounted = false;
      controller.abort();
      revokeUrl();
    };
  }, [display, screenshotId]);

  useKeydown('Escape', onClose, !!display);

  if (!display) {
    return null;
  }

  return (
    <div className="fixed inset-0 z-50 flex flex-col bg-black/80 h-dvh">
      <div className="flex w-full px-5 py-3 text-white justify-between">
        <div className="flex w-full items-center gap-3">
          <button
            onClick={onClose}
            aria-label={t('Close')}
            className="cursor-pointer rounded-lg hover:bg-white/10"
          >
            <X className="p-1" />
          </button>
          <h3 className="font-semibold text-sm truncate">{display.display}</h3>
        </div>
        <div className="flex items-center gap-3 text-nowrap">
          {onRequestScreenshot && (
            <button
              onClick={() => onRequestScreenshot(display)}
              className="flex justify-center items-center gap-1.5 cursor-pointer rounded-lg hover:bg-white/10 px-2 py-1 text-sm"
              title={t('Request Screenshot')}
            >
              <Camera className="p-0.5 size-5 shrink-0" />
              <span className="hidden sm:inline">{t('Request Screenshot')}</span>
            </button>
          )}
          <button
            onClick={() => setShowInfoPanel((prev) => !prev)}
            className="flex justify-center items-center cursor-pointer rounded-lg hover:bg-white/10"
            title={t('Details')}
          >
            <Info className="p-1" />
          </button>
        </div>
      </div>

      <div className="flex flex-1 min-h-0">
        <div className="flex-1 w-full p-4 flex justify-center items-center overflow-hidden min-h-0">
          {loading ? (
            <div className="flex flex-col items-center gap-2 text-gray-500">
              <Loader2 className="w-10 h-10 animate-spin" />
              <span>{t('Loading screenshot...')}</span>
            </div>
          ) : hasError || !url ? (
            <div className="flex flex-col items-center gap-3 text-gray-400">
              <ImageOff className="w-12 h-12" />
              <span className="text-sm">{t('No screenshot available')}</span>
            </div>
          ) : (
            <img
              src={url}
              alt={display.display}
              className="max-w-full max-h-full object-contain shadow-md"
            />
          )}
        </div>
        <div className="pr-4 pb-4 flex">
          <DisplayInfoPanel
            isOpen={showInfoPanel}
            onClose={() => setShowInfoPanel(false)}
            display={display}
          />
        </div>
      </div>
    </div>
  );
}
