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
.*/

import { UploadCloud, File as FileIcon, X, CheckCircle, AlertCircle } from 'lucide-react';
import { useDropzone } from 'react-dropzone';

import type { UploadItem } from '@/hooks/useUploadQueue';

interface FileUploaderProps {
  queue: UploadItem[];
  addFiles: (files: File[]) => void;
  removeFile: (id: string) => void;
  updateFileData: (id: string, data: { displayName?: string; tags?: string }) => void;
  isUploading: boolean;
  acceptedFileTypes?: Record<string, string[]>;
  maxSize?: number;
}

interface RowProps {
  item: UploadItem;
  onRemove: () => void;
  onUpdate: (data: { name?: string; tags?: string }) => void;
}

function UploadItemRow({ item, onRemove, onUpdate }: RowProps) {
  const isPending = item.status === 'pending';
  const isError = item.status === 'error';
  const isCompleted = item.status === 'completed';

  return (
    <div className="p-2 border-b border-black bg-gray-50 flex flex-col gap-2">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2 font-mono text-sm overflow-hidden">
          <FileIcon className="w-4 h-4 shrink-0 text-black" />
          <span className="truncate text-black font-bold">{item.file.name}</span>
          {isCompleted && <CheckCircle className="w-4 h-4 text-black shrink-0" />}
          {isError && <AlertCircle className="w-4 h-4 text-red-600 shrink-0" />}
        </div>
        {!isCompleted && (
          <button
            onClick={onRemove}
            className="text-black hover:bg-gray-200 p-1 border border-transparent hover:border-black"
          >
            <X className="w-4 h-4" />
          </button>
        )}
      </div>

      {isPending ? (
        <div className="grid grid-cols-2 gap-2">
          <div className="flex flex-col gap-1">
            <label className="text-[10px] uppercase font-bold text-black">Display Name</label>
            <input
              type="text"
              defaultValue={item.displayName}
              onChange={(e) => onUpdate({ name: e.target.value })}
              className="px-2 py-1 border border-black text-sm bg-white placeholder:text-gray-400"
              placeholder="Display Name"
            />
          </div>
          <div className="flex flex-col gap-1">
            <label className="text-[10px] uppercase font-bold text-black">Tags</label>
            <input
              type="text"
              defaultValue={item.tags}
              onChange={(e) => onUpdate({ tags: e.target.value })}
              className="px-2 py-1 border border-black text-sm bg-white placeholder:text-gray-400"
              placeholder="tag1, tag2"
            />
          </div>
        </div>
      ) : (
        <div className="space-y-1">
          <div className="flex justify-between text-[10px] font-mono font-bold border-b border-black pb-1 mb-1">
            <span className={isError ? 'text-red-600' : 'text-black'}>
              {item.status.toUpperCase()}
            </span>
            <span>{item.progress}%</span>
          </div>
          <div className="w-full bg-gray-200 h-2 border border-black">
            <div
              className={`h-full ${isError ? 'bg-red-600' : 'bg-black'}`}
              style={{ width: `${item.progress}%` }}
            />
          </div>
          {item.error && (
            <p className="text-[11px] text-red-600 font-bold font-mono">{item.error}</p>
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
  updateFileData,
  isUploading,
  acceptedFileTypes = {
    'image/*': ['.png', '.jpg', '.jpeg', '.gif'],
    'video/*': ['.mp4', '.webm'],
  },
  maxSize = 500 * 1024 * 1024,
}: FileUploaderProps) {
  const onDrop = (acceptedFiles: File[]) => {
    addFiles(acceptedFiles);
  };

  const { getRootProps, getInputProps, isDragActive, open } = useDropzone({
    onDrop,
    accept: acceptedFileTypes,
    maxSize,
    noClick: true,
    disabled: isUploading,
  });

  return (
    <div className="space-y-4 text-sm text-black font-sans">
      <div
        {...getRootProps()}
        className={`p-8 border-2 border-dashed flex flex-col items-center justify-center
          ${isDragActive ? 'border-black bg-gray-100' : 'border-gray-400 bg-white'}
          ${isUploading ? 'opacity-50 cursor-not-allowed' : 'cursor-default'}
        `}
      >
        <input {...getInputProps()} />
        <UploadCloud className="w-10 h-10 mb-2 text-black" />
        <p className="mb-4 text-center font-bold uppercase tracking-wide text-xs">
          {isUploading ? 'Upload in progress...' : 'Drag files here'}
        </p>

        <button
          type="button"
          onClick={open}
          disabled={isUploading}
          className="px-4 py-2 border-2 border-black font-bold uppercase text-xs hover:bg-black hover:text-white transition-none disabled:border-gray-300 disabled:text-gray-300"
        >
          Select Files
        </button>
      </div>

      {queue.length > 0 && (
        <div className="border-2 border-black max-h-80 overflow-y-auto bg-gray-50">
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
