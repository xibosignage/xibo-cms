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

import { CircleMinus, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { SpotUploadState } from '../hooks/usePlaylistDashboardActions';

import Button from '@/components/ui/Button';
import type { SpotWidget } from '@/types/dashboard';
import { formatFileSize } from '@/utils/formatters';

interface SpotRowProps {
  spotIndex: number;
  widget?: SpotWidget;
  uploadState?: SpotUploadState;
  onSelectFile: (spotIndex: number, file: File) => void;
  onDeleteWidget: (widgetId: number) => void;
}

function SpotThumbnail({ widget }: { widget?: SpotWidget }) {
  const [loaded, setLoaded] = useState(false);

  if (widget && widget.regionSpecific === 0 && widget.type === 'image' && widget.mediaIds[0]) {
    return (
      <div className="relative h-16 w-16 shrink-0 rounded">
        {!loaded && <div className="absolute inset-0 animate-pulse rounded bg-gray-300" />}
        <img
          src={`/library/thumbnail/${widget.mediaIds[0]}`}
          alt={widget.name}
          className={`h-16 w-16 rounded object-cover transition-opacity duration-300 ${loaded ? 'opacity-100' : 'opacity-0'}`}
          onLoad={() => setLoaded(true)}
        />
      </div>
    );
  }

  return <div className="h-16 w-16 rounded bg-gray-400" />;
}

export default function SpotRow({
  spotIndex,
  widget,
  uploadState,
  onSelectFile,
  onDeleteWidget,
}: SpotRowProps) {
  const { t } = useTranslation();
  const fileInputRef = useRef<HTMLInputElement>(null);

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      onSelectFile(spotIndex, file);
    }
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };

  const showUploadInfo =
    uploadState && (uploadState.status === 'uploading' || uploadState.status === 'completed');
  const showDeleteButton = widget && widget.deletable && !uploadState;

  return (
    <div className="flex relative justify-stretch items-center gap-4 rounded-lg border border-gray-200 bg-white p-4">
      <span className="w-6 shrink-0 text-center text-sm font-semibold text-gray-500">
        {spotIndex + 1}
      </span>

      <div className="shrink-0">
        <SpotThumbnail widget={widget} />
      </div>

      <div className="flex flex-1">
        <input ref={fileInputRef} type="file" className="hidden" onChange={handleFileChange} />
        <Button
          variant="secondary"
          leftIcon={Upload}
          onClick={() => fileInputRef.current?.click()}
          disabled={uploadState?.status === 'uploading'}
          className="w-full"
        >
          {t('Select File')}
        </Button>
      </div>

      <div className="flex w-50 flex-col gap-1">
        {showUploadInfo ? (
          <>
            <div className="flex items-center justify-between gap-2 font-semibold text-[12px] text-sm text-gray-600">
              <span className="truncate flex-1">{uploadState.fileName}</span>
              <span className="shrink-0 text-gray-400">{formatFileSize(uploadState.fileSize)}</span>
            </div>
            <div className="flex items-center gap-2">
              <div className="h-2 flex-1 overflow-hidden rounded-full bg-gray-200">
                <div
                  className="h-full rounded-full bg-xibo-blue-600 transition-all duration-300"
                  style={{ width: `${uploadState.progress}%` }}
                />
              </div>
              <span className="shrink-0 text-xs text-gray-500">{uploadState.progress}%</span>
            </div>
          </>
        ) : widget ? (
          <span className="truncate text-sm font-semibold text-gray-600">{widget.name}</span>
        ) : null}
      </div>

      {uploadState?.status === 'error' && (
        <div className="flex-1 text-sm text-red-500">{uploadState.error}</div>
      )}

      {/* Delete button */}
      {showDeleteButton && (
        <button
          type="button"
          onClick={() => onDeleteWidget(widget.widgetId)}
          className="shrink-0 text-red-400 hover:text-red-600 absolute right-2 top-2"
          title={t('Delete')}
        >
          <CircleMinus className="h-5 w-5" />
        </button>
      )}
    </div>
  );
}
