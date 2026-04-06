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

import { Download } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import Checkbox from '@/components/ui/forms/Checkbox';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';

interface ExportLayoutModalProps {
  isOpen?: boolean;
  onClose: () => void;
  onConfirm: (options: {
    includeData: boolean;
    includeFallback: boolean;
    fileName: string;
  }) => void;
  layoutName?: string;
  isLoading?: boolean;
  error?: string | null;
}

export default function ExportLayoutModal({
  isOpen = true,
  onClose,
  onConfirm,
  layoutName,
  isLoading,
  error,
}: ExportLayoutModalProps) {
  const { t } = useTranslation();
  const [includeData, setIncludeData] = useState(false);
  const [includeFallback, setIncludeFallback] = useState(false);
  const [fileName, setFileName] = useState('');

  useEffect(() => {
    if (layoutName) {
      setFileName(`export_${layoutName}`);
    }
  }, [layoutName]);

  return (
    <Modal
      isOpen={isOpen}
      isPending={isLoading}
      onClose={isLoading ? () => {} : onClose}
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary',
          disabled: isLoading,
        },
        {
          label: isLoading ? t('Exporting...') : t('Export'),
          onClick: () =>
            onConfirm({
              includeData,
              includeFallback,
              fileName,
            }),
          disabled: isLoading,
        },
      ]}
      size="md"
      error={error || undefined}
    >
      <div className="flex flex-col p-5 gap-3">
        <div className="flex justify-center mb-2">
          <div className="bg-blue-100 w-15.5 h-15.5 text-blue-800 border-blue-50 border-[7px] rounded-full p-3">
            <Download size={26} />
          </div>
        </div>

        <h2 className="text-lg font-semibold text-gray-800 text-center">{t('Discard Changes?')}</h2>

        <p className="text-gray-500 text-center">
          {t(
            'The downloaded ZIP file will include the layout, widgets, media, and associated DataSet structures.',
          )}
          <strong>{layoutName}</strong>?
        </p>

        <TextInput
          name="name"
          label={t('Name')}
          value={fileName}
          onChange={(value) => setFileName(value)}
          helpText={t('Enter a custom name for the downloaded file.')}
        />
        <Checkbox
          id="retired"
          className="items-center px-3 py-2.5"
          title={t('Datasets Data')}
          label={t(`Enable to include Dataset data.`)}
          checked={includeData}
          onChange={(e) => setIncludeData(e.target.checked)}
        />
        <Checkbox
          id="update"
          className="items-center px-3 py-2.5"
          title={t('Widget Fallback Data')}
          label={t(`Include fallback content added to Widgets within this Layout.`)}
          checked={includeFallback}
          onChange={(e) => setIncludeFallback(e.target.checked)}
        />
      </div>
    </Modal>
  );
}
