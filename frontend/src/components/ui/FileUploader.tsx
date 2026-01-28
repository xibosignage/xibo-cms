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

import type { TFunction } from 'i18next';
import { Upload, Link as LinkIcon, MinusCircle, FileIcon, Globe } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useDropzone, type FileRejection, type DropEvent } from 'react-dropzone';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import { notify } from './Notification';
import TagInput from './forms/TagInput';

import Button from '@/components/ui/Button';
import type { UploadItem } from '@/hooks/useUploadQueue';
import type { Tag } from '@/types/media';

interface FileUploaderProps {
  queue: UploadItem[];
  addFiles: (files: File[]) => void;
  removeFile: (id: string) => void;
  clearQueue: () => void;
  updateFileData: (id: string, data: { displayName?: string; tags?: string }) => void;
  isUploading: boolean;
  acceptedFileTypes?: Record<string, string[]>;
  maxSize?: number;
  onUrlUpload: (url: string) => void;
}

interface RowProps {
  item: UploadItem;
  onRemove: () => void;
  onUpdate: (data: { name?: string; tags?: string }) => void;
}

// --- Helpers for Tag Conversion ---

const parseTagsFromString = (str: string | undefined): Tag[] => {
  if (!str) {
    return [];
  }
  return str
    .split(',')
    .map((s) => {
      const trimmed = s.trim();
      if (!trimmed) {
        return null;
      }
      const [tag, val] = trimmed.split('|');
      return {
        tag: tag && tag.trim(),
        value: val ? (isNaN(Number(val)) ? val.trim() : Number(val)) : '',
        tagId: 0,
      };
    })
    .filter((t): t is Tag => t !== null);
};

const serializeTagsToString = (tags: Tag[]): string => {
  return tags.map((t) => (t.value ? `${t.tag}|${t.value}` : t.tag)).join(',');
};

// ----------------------------------

function formatBytes(bytes: number, t: TFunction): string {
  if (bytes === 0) {
    return `0 ${t('Bytes')}`;
  }

  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  const unit = sizes[i] ?? 'TB';

  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + t(unit);
}

function formatFileSize(item: UploadItem, t: TFunction): string {
  if (item.type === 'url') {
    return t('Remote');
  }

  if (!item.file) {
    return '';
  }

  return formatBytes(item.file.size, t);
}

function UploadItemRow({ item, onRemove, onUpdate }: RowProps) {
  const { t } = useTranslation();
  const [preview, setPreview] = useState<string | null>(null);

  // Name still needs local state for debouncing
  const [localName, setLocalName] = useState(item.displayName ?? '');

  // REACT 19 UPDATE: Removed useMemo.
  // The React Compiler automatically optimizes this calculation.
  const tagObjects = parseTagsFromString(item.tags);

  const isError = item.status === 'error';
  const isCompleted = item.status === 'completed';
  const isUploading = item.status === 'uploading';

  // Debounce - name
  useEffect(() => {
    const timer = setTimeout(() => {
      if (localName !== item.displayName) {
        onUpdate({ name: localName });
      }
    }, 500);

    return () => clearTimeout(timer);
  }, [localName, item.displayName, onUpdate]);

  // Handle Tag Changes
  const handleTagsChange = (newTags: Tag[]) => {
    const serialized = serializeTagsToString(newTags);
    if (serialized !== item.tags) {
      onUpdate({ tags: serialized });
    }
  };

  // Generates URL for image and video
  useEffect(() => {
    if (!item.file) {
      return;
    }

    if (item.file.type.startsWith('image/') || item.file.type.startsWith('video/')) {
      const objectUrl = URL.createObjectURL(item.file);
      setPreview(objectUrl);
      return () => URL.revokeObjectURL(objectUrl);
    }
  }, [item.file]);

  const renderThumbnail = () => {
    if (item.type === 'url') {
      return (
        <div className="size-full flex items-center justify-center text-xibo-blue-50">
          <Globe className="size-6" />
        </div>
      );
    }

    if (preview) {
      if (item.file && item.file.type.startsWith('video/')) {
        return <video src={preview} className="size-full object-cover rounded" />;
      }
      return (
        <img src={preview} alt={item.displayName} className="size-full object-cover rounded" />
      );
    }
    return (
      <div className="size-full flex items-center justify-center text-xibo-blue-50">
        <FileIcon className="size-6" />
      </div>
    );
  };

  return (
    <div className="p-4 md:px-4 md:py-5 border-b border-gray-200 bg-slate-50 flex flex-col md:flex-row gap-4 items-center relative">
      <button
        onClick={onRemove}
        title={t('Remove File')}
        className="absolute right-0 top-0 p-1 text-red-500 hover:bg-red-100 hover:text-red-800 rounded-lg z-10"
      >
        <MinusCircle className="size-4" />
      </button>

      <div className="flex gap-3 w-full md:w-auto">
        <div className="thumb size-[70px] rounded text-xibo-blue-600 bg-gray-400 border border-xibo-blue-200/50 overflow-hidden">
          {renderThumbnail()}
        </div>

        <div className="flex flex-row gap-3 flex-1">
          <div className="flex flex-col gap-1 w-full md:w-[200px]">
            <label className="block text-sm font-medium text-gray-500">{t('Name')}</label>
            <input
              type="text"
              disabled={isUploading}
              value={localName}
              onChange={(e) => setLocalName(e.target.value)}
              className="py-2.5 sm:py-3 px-4 block w-full border-gray-200 rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none"
              placeholder={t('File Name')}
            />
          </div>

          <div className="flex flex-col w-full md:w-[280px]">
            <TagInput value={tagObjects} onChange={handleTagsChange} disabled={isUploading} />
          </div>
        </div>
      </div>

      {(isUploading || isError || isCompleted) && (
        <div className="flex flex-col w-full md:flex-1 min-w-0 mt-2 md:mt-0">
          <div className="flex justify-between items-center gap-4 mb-1">
            <span className="truncate text-sm font-semibold text-gray-800 min-w-0 block">
              {item.displayName}
            </span>
            <span className="text-sm text-gray-500 whitespace-nowrap shrink-0">
              {formatFileSize(item, t)}
            </span>
          </div>

          <div className="flex gap-2 items-center">
            <div className="bg-gray-200 h-2.5 rounded-full w-full overflow-hidden">
              <div
                className={twMerge(
                  'h-full transition-all duration-300 rounded-full',
                  isError ? 'bg-red-600' : 'bg-xibo-blue-600',
                )}
                style={{ width: `${item.progress}%` }}
              />
            </div>
            <div
              className={`text-sm font-semibold w-11 text-right shrink-0 ${
                isError ? 'text-red-600' : isCompleted ? 'text-xibo-blue-600' : 'text-gray-800'
              }`}
            >
              {item.progress}%
            </div>
          </div>

          {item.error && (
            <p className="text-[11px] text-red-600 font-bold font-mono mt-1 wrap-break-word">
              {item.error}
            </p>
          )}
        </div>
      )}
    </div>
  );
}

export function FileUploader({
  queue,
  addFiles,
  removeFile,
  clearQueue,
  updateFileData,
  acceptedFileTypes = {
    'image/*': ['.png', '.jpg', '.jpeg', '.gif'],
    'video/*': ['.mp4', '.webm'],
  },
  maxSize = 2 * 1024 * 1024 * 1024,
  onUrlUpload,
}: FileUploaderProps) {
  const { t } = useTranslation();
  const [urlInput, setUrlInput] = useState('');

  const onDrop = (acceptedFiles: File[], fileRejections: FileRejection[], event: DropEvent) => {
    if (event && 'stopPropagation' in event) {
      event.stopPropagation();
    }
    addFiles(acceptedFiles);
  };

  const { getRootProps, getInputProps, isDragActive, open } = useDropzone({
    onDrop,
    accept: acceptedFileTypes,
    maxSize,
    noClick: true,
    disabled: false,
  });

  const handleUrlUpload = () => {
    if (!urlInput) {
      return;
    }

    onUrlUpload(urlInput);
    setUrlInput('');
    notify.info(t('Added to upload queue'));
  };

  const uploadingCount = queue.filter((item) => item.status === 'uploading').length;
  const totalCount = queue.length;
  const isAllComplete = uploadingCount === 0 && totalCount > 0;

  const extensions = Object.values(acceptedFileTypes)
    .sort()
    .flat()
    .map((ext) => ext.replace('.', '').toUpperCase());

  const uniqueExts = Array.from(new Set(extensions)).join(', ');
  const sizeString = formatBytes(maxSize, t);
  const helperText = `(${uniqueExts}, ${t('file size up to {{size}}', { size: sizeString })})`;

  return (
    <div className="flex flex-col gap-3">
      <div
        {...getRootProps()}
        className={`p-5 border-2 border-dashed flex flex-col rounded-xl items-center justify-center transition-colors
          ${isDragActive ? 'border-xibo-blue-600 text-xibo-blue-600 bg-blue-50' : 'border-gray-200 text-gray-800 bg-gray-50'}
          cursor-pointer hover:shadow-4 hover:shadow-blue-500/25
        `}
      >
        <input {...getInputProps()} />
        <Upload className="size-6 p-[3px]" />
        <div className="text-sm flex gap-1 justify-center items-center">
          <div className="text-gray-800">{t('Drag & drop file here or')}</div>
          <Button className="text-sm p-0" variant="tertiary" onClick={open}>
            {t('Select Files')}
          </Button>
        </div>
        <div className="text-sm text-center text-gray-500 px-4">{helperText}</div>
      </div>

      <div className="">
        <div className="text-sm font-semibold text-gray-500">{t('Or Upload from URL')}</div>
        <div className="flex gap-2">
          <div className="hs-trailing-button-add-on-with-leading-and-trailing w-full">
            <div className="flex rounded-lg">
              <span className="p-3 gap-2 bg-white text-gray-500 inline-flex items-center min-w-fit rounded-s-md border border-e-0 border-gray-200">
                <LinkIcon className="w-4 h-4" />
                {t('File URL')}
              </span>
              <input
                id="uploadURL"
                type="url"
                value={urlInput}
                onChange={(e) => setUrlInput(e.target.value)}
                placeholder="https://www.exampleurl.com/funnycat4364"
                className="hs-trailing-button-add-on-with-leading-and-trailing p-3 w-full border-gray-200 border-r-0 focus:border-r text-gray-800 placeholder:text-gray-400 focus:shadow-none focus:border-xibo-blue-500"
              />
              <button
                type="button"
                className="p-3 justify-center text-blue-600 hover:text-blue-800 hover:bg-xibo-blue-50/25 items-center text-sm rounded-e-md border border-gray-200 border-l-0 disabled:text-blue-600/50 disabled:pointer-events-none"
                onClick={handleUrlUpload}
                disabled={!urlInput}
              >
                {t('Upload')}
              </button>
            </div>
          </div>
        </div>

        <div className="text-xs font-normal text-gray-400">
          {t('Provide the remote URL to the file, up to 2G maximum size.')}
        </div>
      </div>

      {queue.length > 0 && (
        <div className="">
          <div className="flex justify-between items-center">
            <div className="text-sm text-gray-800 font-semibold">
              {isAllComplete
                ? t('All {{count}} items completed', { count: totalCount })
                : t('{{uploading}} of {{total}} items are still uploading', {
                    uploading: uploadingCount,
                    total: totalCount,
                  })}
            </div>
            <Button
              variant="link"
              onClick={clearQueue}
              className="text-sm font-normal text-red-500 hover:text-red-800 focus:outline-none"
            >
              {t('Remove All')}
            </Button>
          </div>
          {queue.map((item) => (
            <UploadItemRow
              key={item.id}
              item={item}
              onRemove={() => removeFile(item.id)}
              onUpdate={(data) => updateFileData(item.id, data)}
            />
          ))}
        </div>
      )}
    </div>
  );
}
