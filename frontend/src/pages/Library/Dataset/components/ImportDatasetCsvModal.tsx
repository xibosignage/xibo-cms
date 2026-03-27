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

import { Upload, FileIcon, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useDropzone } from 'react-dropzone';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import { notify } from '@/components/ui/Notification';
import Modal from '@/components/ui/modals/Modal';
import { fetchDatasetColumns, importDatasetCsv } from '@/services/datasetApi';
import type { DatasetColumn } from '@/types/datasetColumn';

interface ImportDatasetCsvModalProps {
  isOpen?: boolean;
  onClose: () => void;
  datasetId: number;
  onSuccess: () => void;
}

export default function ImportDatasetCsvModal({
  isOpen = true,
  onClose,
  datasetId,
  onSuccess,
}: ImportDatasetCsvModalProps) {
  const { t } = useTranslation();

  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [isUploading, setIsUploading] = useState(false);
  const [progress, setProgress] = useState(0);

  const [overwrite, setOverwrite] = useState(false);
  const [ignoreFirstRow, setIgnoreFirstRow] = useState(true);
  const [columns, setColumns] = useState<DatasetColumn[]>([]);
  const [mappings, setMappings] = useState<Record<string, number>>({});

  useEffect(() => {
    if (isOpen && datasetId) {
      fetchDatasetColumns(datasetId, { start: 0, length: 100 })
        .then((res) => {
          const valueCols = res.rows.filter((c) => c.dataSetColumnTypeId === 1);
          setColumns(valueCols);

          const initialMap: Record<string, number> = {};
          valueCols.forEach((col, index) => {
            initialMap[col.dataSetColumnId] = index + 1;
          });
          setMappings(initialMap);
        })
        .catch(() => notify.error(t('Failed to load dataset columns')));
    }
  }, [isOpen, datasetId, t]);

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    accept: { 'text/csv': ['.csv'] },
    maxFiles: 1,
    onDrop: (acceptedFiles) => {
      if (acceptedFiles[0]) setSelectedFile(acceptedFiles[0]);
    },
  });

  const handleMappingChange = (colId: string, value: string) => {
    setMappings((prev) => ({
      ...prev,
      [colId]: parseInt(value, 10) || 0,
    }));
  };

  const handleStartUpload = async () => {
    if (!selectedFile) {
      return;
    }

    setIsUploading(true);
    setProgress(0);

    try {
      await importDatasetCsv(
        datasetId,
        { file: selectedFile, overwrite, ignoreFirstRow, mappings },
        setProgress,
      );
      notify.success(t('CSV Imported successfully'));
      onSuccess();
      handleClose();
    } catch (error: unknown) {
      console.error('Import failed:', error);
      notify.error(t('Import failed'), {
        description: error instanceof Error ? error.message : t('An unknown error occurred'),
      });
    } finally {
      setIsUploading(false);
    }
  };

  const handleClose = () => {
    setSelectedFile(null);
    setProgress(0);
    setIsUploading(false);
    setOverwrite(false);
    setIgnoreFirstRow(true);
    onClose();
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={handleClose}
      title={t('CSV Import')}
      size="lg"
      actions={[
        { label: t('Cancel'), onClick: handleClose, variant: 'secondary', disabled: isUploading },
        {
          label: isUploading ? t('Processing...') : t('Done'),
          onClick: handleStartUpload,
          variant: 'primary',
          disabled: !selectedFile || isUploading,
        },
      ]}
    >
      <div className="p-6 flex flex-col gap-6">
        {!selectedFile ? (
          <div
            {...getRootProps()}
            className={twMerge(
              'p-8 border-2 border-dashed rounded-xl flex flex-col items-center justify-center cursor-pointer transition-colors',
              isDragActive
                ? 'border-xibo-blue-500 bg-blue-50 text-xibo-blue-600'
                : 'border-gray-300 bg-gray-50 text-gray-500',
            )}
          >
            <input {...getInputProps()} />
            <Upload className="size-8 mb-3 opacity-75" />
            <p className="text-sm font-medium">{t('Add CSV File')}</p>
          </div>
        ) : (
          <div className="p-4 border border-gray-200 rounded-lg bg-slate-50 relative">
            {!isUploading && (
              <button
                onClick={() => setSelectedFile(null)}
                className="absolute top-3 right-3 text-gray-400 hover:text-red-500"
              >
                <X className="size-5" />
              </button>
            )}
            <div className="flex items-center gap-3">
              <div className="p-2 bg-xibo-blue-100 text-xibo-blue-600 rounded-lg">
                <FileIcon className="size-6" />
              </div>
              <div>
                <p className="text-sm font-semibold">{selectedFile.name}</p>
                <p className="text-xs text-gray-500">{(selectedFile.size / 1024).toFixed(1)} KB</p>
              </div>
            </div>
            {isUploading && (
              <div className="mt-4 flex items-center gap-3">
                <div className="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                  <div
                    className="h-full bg-xibo-blue-600 transition-all duration-300"
                    style={{ width: `${progress}%` }}
                  />
                </div>
                <span className="text-xs font-semibold text-gray-600">{progress}%</span>
              </div>
            )}
          </div>
        )}

        <div className="flex flex-col gap-4 border-t border-gray-200 pt-4">
          <label className="flex items-start gap-3">
            <input
              type="checkbox"
              className="mt-1 border-gray-300 rounded text-xibo-blue-600 focus:ring-xibo-blue-500"
              checked={overwrite}
              onChange={(e) => setOverwrite(e.target.checked)}
              disabled={isUploading}
            />
            <div>
              <span className="block text-sm font-medium text-gray-800">
                {t('Overwrite existing data?')}
              </span>
              <span className="block text-xs text-gray-500">
                {t(
                  'Erase all content in this DataSet and overwrite it with the new content in this import.',
                )}
              </span>
            </div>
          </label>

          <label className="flex items-start gap-3">
            <input
              type="checkbox"
              className="mt-1 border-gray-300 rounded text-xibo-blue-600 focus:ring-xibo-blue-500"
              checked={ignoreFirstRow}
              onChange={(e) => setIgnoreFirstRow(e.target.checked)}
              disabled={isUploading}
            />
            <div>
              <span className="block text-sm font-medium text-gray-800">
                {t('Ignore first row?')}
              </span>
              <span className="block text-xs text-gray-500">
                {t('Ignore the first row? Useful if the CSV has headings.')}
              </span>
            </div>
          </label>
        </div>

        {columns.length > 0 && (
          <div className="border-t border-gray-200 pt-4">
            <p className="text-sm text-gray-600 mb-4">
              {t(
                'In the fields below please enter the column number in the CSV file that corresponds to the Column Heading listed.',
              )}
            </p>
            <div className="space-y-3">
              {columns.map((col) => (
                <div key={col.dataSetColumnId} className="flex items-center gap-4">
                  <label className="w-1/3 text-sm font-medium text-gray-700 text-right">
                    {col.heading}
                  </label>
                  <input
                    type="number"
                    min="1"
                    value={mappings[col.dataSetColumnId] || ''}
                    onChange={(e) =>
                      handleMappingChange(col.dataSetColumnId.toString(), e.target.value)
                    }
                    disabled={isUploading}
                    className="flex-1 py-2 px-3 border border-gray-200 rounded-lg text-sm focus:border-xibo-blue-500 focus:ring-xibo-blue-500 disabled:opacity-50"
                  />
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    </Modal>
  );
}
