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

import { isAxiosError } from 'axios';
import { Upload, FileIcon, MinusCircle } from 'lucide-react';
import { useRef, useState } from 'react';
import { useDropzone } from 'react-dropzone';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import { PLAYER_VERSION_ACCEPTED_EXTENSIONS } from '../PlayerVersionsConfig';

import Button from '@/components/ui/Button';
import Modal from '@/components/ui/modals/Modal';
import { uploadPlayerVersion } from '@/services/playerVersionApi';
import { formatFileSize } from '@/utils/formatters';

interface UploadFileItem {
  file: File;
  status: 'pending' | 'uploading' | 'done' | 'error';
  progress: number;
  error?: string;
}

interface AddPlayerVersionModalProps {
  isOpen?: boolean;
  onClose: () => void;
  onSave: () => void;
}

export default function AddPlayerVersionModal({
  isOpen = true,
  onClose,
  onSave,
}: AddPlayerVersionModalProps) {
  const { t } = useTranslation();
  const [files, setFiles] = useState<UploadFileItem[]>([]);
  const [isUploading, setIsUploading] = useState(false);
  const isUploadingRef = useRef(false);

  const processUpload = async (allFiles: UploadFileItem[]) => {
    if (isUploadingRef.current) {
      return;
    }
    isUploadingRef.current = true;
    setIsUploading(true);

    let hasSuccess = false;

    for (let i = 0; i < allFiles.length; i++) {
      const currentFile = allFiles[i];
      if (!currentFile || currentFile.status === 'done') {
        continue;
      }

      setFiles((prev) =>
        prev.map((f, idx) => (idx === i ? { ...f, status: 'uploading', progress: 0 } : f)),
      );

      try {
        await uploadPlayerVersion(currentFile.file, (percent) => {
          setFiles((prev) => prev.map((f, idx) => (idx === i ? { ...f, progress: percent } : f)));
        });
        setFiles((prev) =>
          prev.map((f, idx) => (idx === i ? { ...f, status: 'done', progress: 100 } : f)),
        );
        hasSuccess = true;
      } catch (err: unknown) {
        const message =
          isAxiosError(err) && err.response?.data?.message
            ? err.response.data.message
            : err instanceof Error
              ? err.message
              : t('Upload failed');
        setFiles((prev) =>
          prev.map((f, idx) => (idx === i ? { ...f, status: 'error', error: message } : f)),
        );
      }
    }

    isUploadingRef.current = false;
    setIsUploading(false);
    if (hasSuccess) {
      onSave();
    }
  };

  const { getRootProps, getInputProps, isDragActive, open } = useDropzone({
    accept: PLAYER_VERSION_ACCEPTED_EXTENSIONS,
    noClick: true,
    onDrop: (acceptedFiles) => {
      const newItems: UploadFileItem[] = acceptedFiles.map((file) => ({
        file,
        status: 'pending' as const,
        progress: 0,
      }));
      const merged = [...files, ...newItems];
      setFiles(merged);
      processUpload(merged);
    },
    disabled: isUploading,
  });

  const removeFile = (index: number) => {
    setFiles((prev) => prev.filter((_, i) => i !== index));
  };

  const handleClose = () => {
    if (isUploading) {
      return;
    }
    setFiles([]);
    onClose();
  };

  const completedCount = files.filter((f) => f.status === 'done').length;
  const errorCount = files.filter((f) => f.status === 'error').length;
  const allDone =
    files.length > 0 && files.every((f) => f.status === 'done' || f.status === 'error');

  return (
    <Modal
      isOpen={isOpen}
      onClose={handleClose}
      title={t('Upload Player Version')}
      size="md"
      isPending={isUploading}
      actions={[
        {
          label: t('Cancel'),
          onClick: handleClose,
          variant: 'secondary',
          disabled: isUploading,
        },
        {
          label: isUploading ? t('Uploading...') : t('Done'),
          onClick: handleClose,
          variant: 'primary',
          disabled: isUploading,
        },
      ]}
    >
      <div className="p-6 flex flex-col gap-3">
        <div
          {...getRootProps()}
          className={twMerge(
            'p-5 border-2 border-dashed flex flex-col rounded-xl items-center justify-center transition-colors cursor-pointer hover:shadow-4 hover:shadow-blue-500/25',
            isDragActive
              ? 'border-xibo-blue-600 text-xibo-blue-600 bg-blue-50'
              : 'border-gray-200 text-gray-800 bg-gray-50',
            isUploading && 'opacity-50 pointer-events-none',
          )}
        >
          <input {...getInputProps()} />
          <Upload className="size-6 p-0.75" />
          <div className="text-sm flex gap-1 justify-center items-center">
            <div className="text-gray-800">{t('Drag & drop file here or')}</div>
            <Button
              className="text-sm p-0 focus:outline-offset-2"
              variant="tertiary"
              onClick={open}
              disabled={isUploading}
            >
              {t('Select Files')}
            </Button>
          </div>
          <div className="text-sm text-center text-gray-500">
            {t('Supported formats: .apk, .ipk, .wgt, .chrome')}
          </div>
        </div>

        {files.length > 0 && (
          <div>
            <div className="flex justify-between items-center mb-1">
              <div className="text-sm text-gray-800 font-semibold">
                {allDone
                  ? errorCount > 0
                    ? [
                        completedCount === 1
                          ? t('1 item completed')
                          : t('{{count}} items completed', { count: completedCount }),
                        errorCount === 1
                          ? t('1 item failed')
                          : t('{{count}} items failed', { count: errorCount }),
                      ].join(', ')
                    : files.length === 1
                      ? t('1 item completed')
                      : t('All {{count}} items completed', { count: files.length })
                  : t('{{count}} files selected', { count: files.length })}
              </div>
              {!isUploading && (
                <Button
                  variant="link"
                  onClick={() => setFiles([])}
                  className="text-sm font-normal text-red-500 hover:text-red-800 focus:outline-none"
                >
                  {t('Remove All')}
                </Button>
              )}
            </div>

            {files.map((item, index) => (
              <div
                key={`${item.file.name}-${index}`}
                className="p-4 md:px-4 md:py-5 border-b border-gray-200 bg-slate-50 flex items-center gap-3 relative"
              >
                {(item.status === 'pending' || item.status === 'error') && !isUploading && (
                  <button
                    onClick={() => removeFile(index)}
                    title={t('Remove File')}
                    className="absolute right-0 top-0 p-1 text-red-500 hover:bg-red-100 hover:text-red-800 rounded-lg z-10"
                  >
                    <MinusCircle className="size-4" />
                  </button>
                )}

                <div className="size-10 rounded text-xibo-blue-600 bg-gray-400 border border-xibo-blue-200/50 overflow-hidden flex items-center justify-center shrink-0">
                  <FileIcon className="size-5 text-xibo-blue-50" />
                </div>

                <div className="flex-1 min-w-0">
                  <div className="flex justify-between items-center gap-4 mb-1">
                    <span className="truncate text-sm font-semibold text-gray-800 min-w-0 block">
                      {item.file.name}
                    </span>
                    <span className="text-sm text-gray-500 whitespace-nowrap shrink-0">
                      {formatFileSize(item.file.size)}
                    </span>
                  </div>

                  {(item.status === 'uploading' || item.status === 'done') && (
                    <div className="flex gap-2 items-center">
                      <div className="bg-gray-200 h-2.5 rounded-full w-full overflow-hidden">
                        <div
                          className={twMerge(
                            'h-full transition-all duration-300 rounded-full',
                            'bg-xibo-blue-600',
                          )}
                          style={{ width: `${item.progress}%` }}
                        />
                      </div>
                      <div
                        className={twMerge(
                          'text-sm font-semibold w-11 text-right shrink-0',
                          item.status === 'done' ? 'text-xibo-blue-600' : 'text-gray-800',
                        )}
                      >
                        {item.progress}%
                      </div>
                    </div>
                  )}

                  {item.status === 'error' && item.error && (
                    <p className="text-[11px] text-red-600 font-bold font-mono mt-1 wrap-break-word">
                      {item.error}
                    </p>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </Modal>
  );
}
