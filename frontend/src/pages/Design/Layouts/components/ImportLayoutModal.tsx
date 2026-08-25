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
import { Upload as UploadIcon, MinusCircle, FileIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useDropzone } from 'react-dropzone';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import Button from '@/components/ui/Button';
import Checkbox from '@/components/ui/forms/Checkbox';
import SelectFolder from '@/components/ui/forms/SelectFolder';
import TagInput from '@/components/ui/forms/TagInput';
import Modal from '@/components/ui/modals/Modal';
import { useUserContext } from '@/context/UserContext';
import { usePermissions } from '@/hooks/usePermissions';
import { importLayout, updateLayout } from '@/services/layoutsApi';
import type { Tag } from '@/types/tag';
import { hasFeature } from '@/utils/permissions';

interface ImportLayoutModalProps {
  onClose: () => void;
  onSuccess: () => void;
}

interface ImportFile {
  id: string;
  file: File;
  name: string;
  tags: string;
  progress: number;
  status: 'pending' | 'uploading' | 'completed' | 'error';
  error?: string;
  layoutId?: number;
  hasUnsavedChanges?: boolean;
}

const ACCEPTED_ZIP_TYPES: Record<string, string[]> = {
  'application/zip': ['.zip'],
  'application/x-zip-compressed': ['.zip'],
};

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

function ImportItemRow({
  item,
  onRemove,
  onUpdate,
}: {
  item: ImportFile;
  onRemove: () => void;
  onUpdate: (data: { name?: string; tags?: string }) => void;
}) {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const [localName, setLocalName] = useState(item.name);
  const tagObjects = parseTagsFromString(item.tags);

  const isUploading = item.status === 'uploading';
  const isCompleted = item.status === 'completed';
  const isError = item.status === 'error';

  useEffect(() => {
    const timer = setTimeout(() => {
      if (localName !== item.name) {
        onUpdate({ name: localName });
      }
    }, 500);
    return () => clearTimeout(timer);
  }, [localName, item.name, onUpdate]);

  const handleTagsChange = (newTags: Tag[]) => {
    const serialized = serializeTagsToString(newTags);
    if (serialized !== item.tags) {
      onUpdate({ tags: serialized });
    }
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
        <div className="thumb size-17.5 rounded text-xibo-blue-600 bg-gray-400 border border-xibo-blue-200/50 overflow-hidden">
          <div className="size-full flex items-center justify-center text-xibo-blue-50">
            <FileIcon className="size-6" />
          </div>
        </div>

        <div className="flex flex-row gap-3 flex-1">
          <div className="flex flex-col gap-1 w-full md:w-50">
            <label className="block text-sm font-medium text-gray-500">{t('Name')}</label>
            <input
              type="text"
              disabled={isUploading}
              value={localName}
              onChange={(e) => setLocalName(e.target.value)}
              className="py-2.5 sm:py-3 px-4 block w-full border-gray-200 rounded-lg sm:text-sm focus:border-xibo-blue-500 focus:ring-xibo-blue-500 disabled:opacity-50 disabled:pointer-events-none"
              placeholder={t('Layout Name')}
            />
          </div>

          {(hasFeature(user, 'tag.tagging') || (tagObjects?.length ?? 0) > 0) && (
            <div className="flex flex-col w-full md:w-70">
              <TagInput
                value={tagObjects}
                onChange={handleTagsChange}
                disabled={isUploading || !hasFeature(user, 'tag.tagging')}
              />
            </div>
          )}
        </div>
      </div>

      {(isUploading || isError || isCompleted) && (
        <div className="flex flex-col w-full md:flex-1 min-w-0 mt-2 md:mt-0">
          <div className="flex justify-between items-center gap-4 mb-1">
            <span className="truncate text-sm font-semibold text-gray-800 min-w-0 block">
              {item.name}
            </span>
          </div>

          {!isError && (
            <div className="flex gap-2 items-center">
              <div className="bg-gray-200 h-2.5 rounded-full w-full overflow-hidden">
                <div
                  className="h-full transition-all duration-300 rounded-full bg-xibo-blue-600"
                  style={{ width: `${item.progress}%` }}
                />
              </div>
              <div
                className={twMerge(
                  'text-sm font-semibold w-11 text-right shrink-0',
                  isCompleted ? 'text-xibo-blue-600' : 'text-gray-800',
                )}
              >
                {item.progress}%
              </div>
            </div>
          )}

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

export default function ImportLayoutModal({ onClose, onSuccess }: ImportLayoutModalProps) {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const canViewFolders = usePermissions()?.canViewFolders;
  const homeFolderId = user?.homeFolderId ?? 1;

  const [files, setFiles] = useState<ImportFile[]>([]);
  const [folderId, setFolderId] = useState<number>(homeFolderId);
  const [replaceExisting, setReplaceExisting] = useState(false);
  const [importTags, setImportTags] = useState(false);
  const [useExistingDataSets, setUseExistingDataSets] = useState(true);
  const [importDataSetData, setImportDataSetData] = useState(false);
  const [importFallback, setImportFallback] = useState(false);
  const [isImporting, setIsImporting] = useState(false);
  const [isSyncing, setIsSyncing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const optionsRef = useRef({
    folderId,
    replaceExisting,
    importTags,
    useExistingDataSets,
    importDataSetData,
    importFallback,
  });
  optionsRef.current = {
    folderId,
    replaceExisting,
    importTags,
    useExistingDataSets,
    importDataSetData,
    importFallback,
  };

  const startImport = async (newFiles: ImportFile[]) => {
    setIsImporting(true);
    setError(null);

    for (const item of newFiles) {
      setFiles((prev) =>
        prev.map((f) => (f.id === item.id ? { ...f, status: 'uploading' as const } : f)),
      );

      try {
        const opts = optionsRef.current;
        const result = await importLayout({
          file: item.file,
          name: item.name,
          tags: item.tags,
          folderId: opts.folderId,
          replaceExisting: opts.replaceExisting,
          importTags: opts.importTags,
          useExistingDataSets: opts.useExistingDataSets,
          importDataSetData: opts.importDataSetData,
          importFallback: opts.importFallback,
          onProgress: (percent) => {
            setFiles((prev) =>
              prev.map((f) => (f.id === item.id ? { ...f, progress: percent } : f)),
            );
          },
        });

        const fileResult = result?.files?.[0];
        if (fileResult?.error) {
          throw new Error(fileResult.error);
        }

        setFiles((prev) =>
          prev.map((f) =>
            f.id === item.id
              ? {
                  ...f,
                  status: 'completed' as const,
                  progress: 100,
                  layoutId: fileResult?.id,
                }
              : f,
          ),
        );
      } catch (err) {
        const message =
          isAxiosError(err) && err.response?.data?.message
            ? err.response.data.message
            : err instanceof Error
              ? err.message
              : t('Import failed');
        setFiles((prev) =>
          prev.map((f) =>
            f.id === item.id ? { ...f, status: 'error' as const, error: message } : f,
          ),
        );
      }
    }

    setIsImporting(false);
  };

  const handleDrop = (acceptedFiles: File[]) => {
    const newFiles: ImportFile[] = acceptedFiles.map((file) => ({
      id: Math.random().toString(36).substring(2) + Date.now().toString(36),
      file,
      name: file.name.replace(/\.zip$/i, ''),
      tags: '',
      progress: 0,
      status: 'pending' as const,
    }));

    setFiles((prev) => [...prev, ...newFiles]);
    startImport(newFiles);
  };

  const removeFile = (id: string) => {
    setFiles((prev) => prev.filter((f) => f.id !== id));
  };

  const updateFile = (id: string, data: { name?: string; tags?: string }) => {
    setFiles((prev) =>
      prev.map((f) =>
        f.id === id
          ? { ...f, name: data.name ?? f.name, tags: data.tags ?? f.tags, hasUnsavedChanges: true }
          : f,
      ),
    );
  };

  // Sync name/tags changes to the backend after import completes
  useEffect(() => {
    if (isSyncing) {
      return;
    }

    const itemToSync = files.find(
      (f) => f.status === 'completed' && f.hasUnsavedChanges && f.layoutId,
    );

    if (!itemToSync) {
      return;
    }

    const syncItem = async () => {
      setIsSyncing(true);
      try {
        await updateLayout(itemToSync.layoutId!, {
          name: itemToSync.name,
          tags: itemToSync.tags,
        });

        setFiles((prev) =>
          prev.map((f) =>
            f.id === itemToSync.id ? { ...f, hasUnsavedChanges: false, error: undefined } : f,
          ),
        );
      } catch (err) {
        const errorMsg =
          isAxiosError(err) && err.response?.data?.message
            ? err.response.data.message
            : err instanceof Error
              ? err.message
              : t('Failed to save changes');
        setFiles((prev) =>
          prev.map((f) =>
            f.id === itemToSync.id
              ? {
                  ...f,
                  hasUnsavedChanges: false,
                  status: 'error' as const,
                  error: errorMsg,
                }
              : f,
          ),
        );
      } finally {
        setIsSyncing(false);
      }
    };

    void syncItem();
  }, [files, isSyncing]);

  const handleDone = () => {
    onSuccess();
  };

  const handleCancel = () => {
    onClose();
  };

  const { getRootProps, getInputProps, isDragActive, open } = useDropzone({
    onDrop: handleDrop,
    accept: ACCEPTED_ZIP_TYPES,
    noClick: true,
    disabled: isImporting,
  });

  return (
    <Modal
      onClose={isImporting ? () => {} : onClose}
      title={t('Import Layout')}
      isPending={isImporting}
      actions={[
        {
          label: t('Cancel'),
          onClick: handleCancel,
          variant: 'secondary' as const,
          className: 'bg-transparent',
          disabled: isImporting,
        },
        {
          label: t('Done'),
          onClick: handleDone,
          variant: 'primary' as const,
          disabled: isImporting,
        },
      ]}
      size="lg"
      error={error || undefined}
    >
      <div className="flex flex-col gap-4 p-8 pt-0">
        {canViewFolders && (
          <SelectFolder
            selectedId={folderId}
            onSelect={(folder) => {
              if (folder) {
                setFolderId(folder.id);
              }
            }}
          />
        )}

        <div
          {...getRootProps()}
          className={`p-5 border-2 border-dashed flex flex-col rounded-xl items-center justify-center transition-colors
            ${isDragActive ? 'border-xibo-blue-600 text-xibo-blue-600 bg-xibo-blue-50' : 'border-gray-200 text-gray-800 bg-gray-50'}
            cursor-pointer hover:shadow-4 hover:shadow-blue-500/25
          `}
        >
          <input {...getInputProps()} />
          <UploadIcon className="size-6 p-0.75" />
          <div className="text-sm flex gap-1 justify-center items-center">
            <div className="text-gray-800">{t('Drag & drop file here or')}</div>
            <Button
              className="text-sm p-0 focus:outline-offset-2"
              variant="tertiary"
              onClick={open}
              disabled={isImporting}
            >
              {t('Select Files')}
            </Button>
          </div>
          <div className="text-sm text-center text-gray-500">{t('Supported formats: ZIP')}</div>
        </div>
        <div className="grid grid-cols-2 gap-x-4 gap-y-2">
          <Checkbox
            id="replaceExisting"
            title={t('Replace Existing')}
            label={t('Replace layouts with the same name on import.')}
            checked={replaceExisting}
            onChange={(e) => setReplaceExisting(e.target.checked)}
            className="items-center px-3 py-2.5"
          />
          <Checkbox
            id="importTags"
            title={t('Import Tags')}
            label={t('Import tags from the layout file.')}
            checked={importTags}
            onChange={(e) => setImportTags(e.target.checked)}
            className="items-center px-3 py-2.5"
          />
          <Checkbox
            id="useExistingDataSets"
            title={t('Use Existing DataSets')}
            label={t('Use DataSets that already exist with the same name and columns.')}
            checked={useExistingDataSets}
            onChange={(e) => {
              setUseExistingDataSets(e.target.checked);
              if (e.target.checked) {
                setImportDataSetData(false);
              }
            }}
            className="items-center px-3 py-2.5"
          />
          <Checkbox
            id="importDataSetData"
            title={t('Import DataSet Data')}
            label={t('Import data contained in the DataSet.')}
            checked={importDataSetData}
            onChange={(e) => setImportDataSetData(e.target.checked)}
            className={`items-center px-3 py-2.5 ${useExistingDataSets ? 'opacity-50' : ''}`}
            classNameInput={useExistingDataSets ? 'pointer-events-none' : ''}
          />
          <Checkbox
            id="importFallback"
            title={t('Import Fallback Data')}
            label={t('Import fallback content added to Widgets within the Layout.')}
            checked={importFallback}
            onChange={(e) => setImportFallback(e.target.checked)}
            className="items-center px-3 py-2.5"
          />
        </div>
        {files.length > 0 && (
          <div className="border border-gray-200 rounded-lg overflow-hidden">
            <div className="flex justify-between items-center px-4 py-2">
              <div className="text-sm text-gray-800 font-semibold">
                {t('{{count}} file(s)', { count: files.length })}
              </div>
              <Button
                variant="link"
                onClick={() => setFiles([])}
                className="text-sm font-normal text-red-500 hover:text-red-800 focus:outline-none"
                disabled={isImporting}
              >
                {t('Remove All')}
              </Button>
            </div>
            {files.map((item) => (
              <ImportItemRow
                key={item.id}
                item={item}
                onRemove={() => removeFile(item.id)}
                onUpdate={(data) => updateFile(item.id, data)}
              />
            ))}
          </div>
        )}
      </div>
    </Modal>
  );
}
