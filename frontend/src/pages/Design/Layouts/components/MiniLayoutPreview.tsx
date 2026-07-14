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

import { Expand, Maximize2, Minimize2, Play, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { useKeydown } from '@/hooks/useKeydown';

interface MiniLayoutPreviewProps {
  previewUrl: string | null;
  title?: string;
  onClose: () => void;
  onFullscreen: () => void;
}

export default function MiniLayoutPreview({
  previewUrl,
  title,
  onClose,
  onFullscreen,
}: MiniLayoutPreviewProps) {
  const { t } = useTranslation();
  const [isLarge, setIsLarge] = useState(false);
  const [isPlaying, setIsPlaying] = useState(false);
  const [shown, setShown] = useState(false);

  useEffect(() => {
    setIsPlaying(false);
    setIsLarge(false);
    setShown(false);
    if (!previewUrl) {
      return;
    }
    const frame = requestAnimationFrame(() => setShown(true));
    return () => cancelAnimationFrame(frame);
  }, [previewUrl]);

  useKeydown('Escape', onClose, !!previewUrl);

  if (!previewUrl) {
    return null;
  }

  const toggleSize = () => {
    setIsPlaying(false);
    setIsLarge((prev) => !prev);
  };

  return (
    <div
      className={`fixed bottom-4 right-4 z-50 flex flex-col overflow-hidden rounded-lg bg-neutral-900 shadow-2xl transition-all duration-300 ease-out ${
        isLarge ? 'w-190 h-105' : 'w-110 h-60'
      } ${shown ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'}`}
    >
      <div className="absolute inset-x-0 top-0 z-10 flex items-center justify-between bg-black/50 px-2 py-1 text-white opacity-0 transition-opacity duration-200 hover:opacity-100 focus-within:opacity-100">
        <div className="flex items-center gap-1">
          <button
            onClick={toggleSize}
            className="flex cursor-pointer items-center justify-center rounded hover:bg-white/10"
            title={t('Change window size')}
          >
            {isLarge ? <Minimize2 className="p-1" /> : <Maximize2 className="p-1" />}
          </button>
          <button
            onClick={onFullscreen}
            className="flex cursor-pointer items-center justify-center rounded hover:bg-white/10"
            title={t('Preview in fullscreen')}
          >
            <Expand className="p-1" />
          </button>
        </div>
        <span className="min-w-0 flex-1 truncate px-2 text-center text-xs font-semibold">
          {title}
        </span>
        <button
          onClick={onClose}
          className="flex cursor-pointer items-center justify-center rounded hover:bg-white/10"
          title={t('Close Preview')}
        >
          <X className="p-1" />
        </button>
      </div>

      <div className="flex flex-1 items-center justify-center">
        {isPlaying ? (
          <iframe
            sandbox="allow-scripts"
            scrolling="no"
            src={previewUrl}
            title={title ?? t('Layout Preview')}
            className="h-full w-full border-0"
          />
        ) : (
          <button
            onClick={() => setIsPlaying(true)}
            className="flex cursor-pointer items-center justify-center rounded-full p-3 text-white/80 transition-colors hover:text-white"
            title={t('Play Preview')}
          >
            <Play className="h-10 w-10" />
          </button>
        )}
      </div>
    </div>
  );
}
