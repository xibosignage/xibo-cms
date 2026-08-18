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

import { CircleMinus, ListOrdered, Lock, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import { Trans, useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import type { SpotUploadState } from '../hooks/usePlaylistDashboardActions';

import Button from '@/components/ui/Button';
import { withPublicPath } from '@/config/publicPath';
import { ACCEPTED_MIME_TYPES, getMediaIcon } from '@/pages/Library/Media/MediaConfig';
import type { SpotWidget } from '@/types/dashboard';
import { formatFileSizeIEC } from '@/utils/formatters';

interface SpotRowProps {
  spotIndex: number;
  widget?: SpotWidget;
  uploadState?: SpotUploadState;
  onSelectFile: (spotIndex: number, file: File) => void;
  onDeleteWidget: (widgetId: number) => void;
  onPreview?: (widget: SpotWidget) => void;
}

function SpotThumbnail({
  widget,
  blobUrl,
  onPreview,
  hidden = false,
}: {
  widget?: SpotWidget;
  blobUrl?: string;
  onPreview?: () => void;
  hidden?: boolean;
}) {
  const [loaded, setLoaded] = useState(false);

  const thumbnailSrc =
    blobUrl ??
    (!hidden && widget?.regionSpecific === 0 && widget?.type === 'image' && widget?.mediaIds[0]
      ? withPublicPath(`library/thumbnail/${widget.mediaIds[0]}`)
      : null);

  const isPreviewable = onPreview && widget && widget.mediaIds.length > 0;

  if (thumbnailSrc) {
    const img = (
      <div className="relative h-16 w-16 shrink-0 rounded">
        {!loaded && <div className="absolute inset-0 animate-pulse rounded bg-gray-300" />}
        <img
          src={thumbnailSrc}
          alt={widget?.mediaFiles?.[0]?.fileName ?? widget?.name ?? ''}
          className={`h-16 w-16 rounded object-cover transition-opacity duration-300 ${loaded ? 'opacity-100' : 'opacity-0'}`}
          onLoad={() => setLoaded(true)}
        />
      </div>
    );

    if (isPreviewable) {
      return (
        <button
          type="button"
          onClick={(e) => {
            e.stopPropagation();
            onPreview();
          }}
          className="cursor-pointer rounded hover:ring-2 hover:ring-xibo-blue-600"
        >
          {img}
        </button>
      );
    }

    return img;
  }

  const Icon = widget?.type === 'subplaylist' ? ListOrdered : getMediaIcon(widget?.type ?? '');
  const iconDiv = (
    <div className="flex h-16 w-16 items-center justify-center rounded bg-gray-400">
      <Icon className="size-6 text-gray-500" />
    </div>
  );

  if (isPreviewable) {
    return (
      <button
        type="button"
        onClick={(e) => {
          e.stopPropagation();
          onPreview();
        }}
        className="cursor-pointer rounded hover:ring-2 hover:ring-xibo-blue-600"
      >
        {iconDiv}
      </button>
    );
  }

  return iconDiv;
}

export default function SpotRow({
  spotIndex,
  widget,
  uploadState,
  onSelectFile,
  onDeleteWidget,
  onPreview,
}: SpotRowProps) {
  const { t } = useTranslation();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [isDragOver, setIsDragOver] = useState(false);

  const isUploading = uploadState?.status === 'uploading';
  const isSubPlaylist = widget?.type === 'subplaylist';
  // Mirrors release 4.4: a widget the user can't edit (e.g. View-only on its media) is locked,
  // regardless of whether they can see its content.
  const isPermissionLocked = !!widget && !isSubPlaylist && !widget.editable;
  const isLocked = isSubPlaylist || isPermissionLocked;

  const previewHandler =
    onPreview && widget && widget.viewable && widget.mediaIds.length > 0
      ? () => onPreview(widget)
      : undefined;

  const acceptType = (() => {
    if (!widget) return undefined;
    const widgetType = widget.type.toLowerCase();
    const extensions = Object.entries(ACCEPTED_MIME_TYPES)
      .filter(([mime]) => mime.startsWith(widgetType + '/'))
      .flatMap(([, exts]) => exts);
    return extensions.length > 0 ? extensions.join(',') : undefined;
  })();

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      onSelectFile(spotIndex, file);
    }
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    if (!isUploading && !isLocked) {
      setIsDragOver(true);
    }
  };

  const handleDragLeave = (e: React.DragEvent) => {
    if (!e.currentTarget.contains(e.relatedTarget as Node)) {
      setIsDragOver(false);
    }
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragOver(false);
    if (isUploading || isLocked) return;
    const file = e.dataTransfer.files[0];
    if (file) {
      onSelectFile(spotIndex, file);
    }
  };

  const renderContent = () => {
    // Sub-playlist spot: never editable from here regardless of permission
    if (isSubPlaylist && widget) {
      return (
        <>
          <div className="shrink-0">
            <SpotThumbnail widget={widget} onPreview={previewHandler} />
          </div>
          <div className="flex min-w-0 flex-1 flex-col gap-1">
            <span className="truncate text-sm font-semibold text-gray-600" aria-label={widget.name}>
              {widget.name}
            </span>
            <span className="w-fit rounded-full bg-gray-100 px-1.5 py-0.5 text-[11px] font-medium text-gray-500">
              {t('Sub-Playlist')}
            </span>
          </div>
          <Lock className="h-4 w-4 shrink-0 text-gray-400" />
        </>
      );
    }

    // Permission-locked spot: user can't edit this widget's media (e.g. View-only)
    if (isPermissionLocked && widget) {
      return (
        <>
          <div className="shrink-0">
            <SpotThumbnail widget={widget} onPreview={previewHandler} hidden={!widget.viewable} />
          </div>
          <div className="flex min-w-0 flex-1 flex-col gap-1">
            <span className="truncate text-sm font-semibold text-gray-600" aria-label={widget.name}>
              {widget.name}
            </span>
            <span className="w-fit rounded-full bg-gray-100 px-1.5 py-0.5 text-[11px] font-medium text-gray-500">
              {t('Locked')}
            </span>
          </div>
          <Lock className="h-4 w-4 shrink-0 text-gray-400" />
          {widget.deletable && (
            <button
              type="button"
              onClick={() => onDeleteWidget(widget.widgetId)}
              disabled={isUploading}
              className="shrink-0 text-red-400 hover:text-red-600 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
              title={t('Delete')}
            >
              <CircleMinus className="h-5 w-5" />
            </button>
          )}
        </>
      );
    }

    // Filled spot or uploading
    if (widget || uploadState) {
      return (
        <>
          <div className="shrink-0">
            <SpotThumbnail
              widget={widget}
              blobUrl={uploadState?.blobUrl}
              onPreview={previewHandler}
            />
          </div>
          <div className="flex min-w-0 flex-1 flex-col gap-1">
            {uploadState ? (
              <>
                <span
                  className="truncate text-sm font-semibold text-gray-600"
                  aria-label={uploadState.fileName}
                >
                  {uploadState.fileName}
                </span>
                <span className="text-xs text-gray-500">
                  {formatFileSizeIEC(uploadState.fileSize)}
                </span>
                {(uploadState.status === 'uploading' || uploadState.status === 'completed') && (
                  <div className="flex items-center gap-2">
                    <div className="h-2 flex-1 overflow-hidden rounded-full bg-gray-200">
                      <div
                        className="h-full rounded-full bg-xibo-blue-600 transition-all duration-300"
                        style={{ width: `${uploadState.progress}%` }}
                      />
                    </div>
                    <span className="shrink-0 text-xs text-gray-500">{uploadState.progress}%</span>
                  </div>
                )}
                {uploadState.status === 'error' && (
                  <div className="text-[12px] text-red-500">{uploadState.error}</div>
                )}
              </>
            ) : (
              <>
                <span
                  className="truncate text-sm font-semibold text-gray-600"
                  aria-label={widget!.mediaFiles?.[0]?.fileName ?? widget!.name}
                >
                  {widget!.mediaFiles?.[0]?.fileName ?? widget!.name}
                </span>
                <div className="flex sm:items-center gap-2 flex-col sm:flex-row">
                  <span className="text-xs text-gray-500">{widget!.mediaFiles?.[0]?.fileSize}</span>
                  <span className="w-fit rounded-full bg-xibo-blue-100 px-1.5 py-0.5 text-[11px] font-medium text-xibo-blue-800">
                    {widget!.name}
                  </span>
                </div>
              </>
            )}
          </div>
          <input
            ref={fileInputRef}
            type="file"
            accept={acceptType}
            className="hidden"
            onChange={handleFileChange}
          />
          <Button
            variant="secondary"
            leftIcon={Upload}
            onClick={() => fileInputRef.current?.click()}
            disabled={isUploading}
            className="w-33.5"
          >
            {t('Select File')}
          </Button>
          {widget?.deletable && (
            <button
              type="button"
              onClick={() => onDeleteWidget(widget.widgetId)}
              disabled={isUploading}
              className="shrink-0 text-red-400 hover:text-red-600 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
              title={t('Delete')}
            >
              <CircleMinus className="h-5 w-5" />
            </button>
          )}
        </>
      );
    }

    // Empty spot
    return (
      <div className="flex flex-1">
        <input ref={fileInputRef} type="file" className="hidden" onChange={handleFileChange} />
        <div
          className={`w-full h-16 flex justify-center items-center border space-x-2 border-dashed rounded-lg transition-colors ${isDragOver ? 'border-xibo-blue-600 bg-xibo-blue-100' : 'border-xibo-blue-600'}`}
        >
          <Upload className="h-3.5 w-3.5 text-xibo-blue-600" />
          <span className="text-sm text-gray-500">
            <Trans
              i18nKey="Drag and drop files or <button>Select File</button>"
              components={{
                button: (
                  <button
                    type="button"
                    onClick={() => fileInputRef.current?.click()}
                    className="text-xibo-blue-600 cursor-pointer"
                  />
                ),
              }}
            />
          </span>
        </div>
      </div>
    );
  };

  return (
    <div
      className={twMerge(
        `flex relative justify-stretch items-center gap-4 rounded-lg border bg-white p-4 transition-colors `,
        isDragOver ? 'border-xibo-blue-600 bg-xibo-blue-50' : 'border-gray-200',
      )}
      onDragOver={handleDragOver}
      onDragLeave={handleDragLeave}
      onDrop={handleDrop}
    >
      <span className="w-6 shrink-0 text-center text-sm font-semibold text-gray-500">
        {spotIndex + 1}
      </span>
      {renderContent()}
    </div>
  );
}
