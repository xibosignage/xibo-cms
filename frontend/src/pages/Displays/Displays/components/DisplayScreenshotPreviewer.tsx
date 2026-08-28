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

import { X, Loader2, Camera, Info, ImageOff, RefreshCw } from 'lucide-react';
import { useEffect, useState, useRef } from 'react';
import { useTranslation } from 'react-i18next';

import DisplayInfoPanel from './DisplayInfoPanel';

import { useKeydown } from '@/hooks/useKeydown';
import { fetchDisplayScreenshotBlob } from '@/services/displaysApi';
import type { Display } from '@/types/display';

interface DisplayScreenshotPreviewerProps {
  display: Display | null;
  onClose: () => void;
  onRequestScreenshot?: (display: Display) => void;
}

export default function DisplayScreenshotPreviewer({
  display,
  onClose,
  onRequestScreenshot,
}: DisplayScreenshotPreviewerProps) {
  const { t } = useTranslation();

  const [showInfoPanel, setShowInfoPanel] = useState(false);
  const [url, setUrl] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [hasError, setHasError] = useState(false);
  const [retryToken, setRetryToken] = useState(0);
  const activeUrlRef = useRef<string | null>(null);

  const revokeUrl = () => {
    if (activeUrlRef.current) {
      window.URL.revokeObjectURL(activeUrlRef.current);
      activeUrlRef.current = null;
    }
    setUrl(null);
  };

  // The backend has no "not found" response for a display that has never sent a screenshot — it
  // falls back to serving a generic placeholder image with a normal 200, so that case can't be
  // told apart from a real one by letting the fetch run. `display.thumbnail` is already the
  // signal the card grid and hero status use for the same thing (both set it to '' when the
  // current screenshot file doesn't exist), so it's checked up front instead, and the request is
  // skipped entirely rather than fetching a placeholder and treating it as a real image.
  const noCurrentScreenshot = !display?.thumbnail;

  useEffect(() => {
    if (!display) {
      revokeUrl();
      setShowInfoPanel(false);
      return;
    }

    if (!display.thumbnail) {
      revokeUrl();
      setLoading(false);
      setHasError(false);
      return;
    }

    let isMounted = true;
    const controller = new AbortController();

    const load = async () => {
      revokeUrl();
      setLoading(true);
      setHasError(false);

      try {
        const blob = await fetchDisplayScreenshotBlob(display.displayId, controller.signal);

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
  }, [display, retryToken]);

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
          ) : noCurrentScreenshot ? (
            <div className="flex flex-col items-center gap-3 text-gray-400">
              <ImageOff className="w-12 h-12" />
              <span className="text-sm">{t('No screenshot has been taken yet')}</span>
              {onRequestScreenshot && (
                <button
                  type="button"
                  onClick={() => onRequestScreenshot(display)}
                  className="flex items-center gap-1.5 cursor-pointer rounded-lg border border-gray-600 px-3 py-1.5 text-sm text-gray-300 hover:bg-white/10"
                >
                  <Camera className="size-4 shrink-0" />
                  {t('Request Screenshot')}
                </button>
              )}
            </div>
          ) : hasError || !url ? (
            <div className="flex flex-col items-center gap-3 text-gray-400">
              <ImageOff className="w-12 h-12" />
              <span className="text-sm">{t('Could not load the screenshot')}</span>
              <button
                type="button"
                onClick={() => setRetryToken((prev) => prev + 1)}
                className="flex items-center gap-1.5 cursor-pointer rounded-lg border border-gray-600 px-3 py-1.5 text-sm text-gray-300 hover:bg-white/10"
              >
                <RefreshCw className="size-4 shrink-0" />
                {t('Retry')}
              </button>
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
