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

import { Info, Sparkles } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import Checkbox from '@/components/ui/forms/Checkbox';
import Modal from '@/components/ui/modals/Modal';

interface TidyLibraryModalProps {
  isOpen?: boolean;
  onClose: () => void;
  onConfirm: (options: { tidyGenericFiles: boolean }) => void;
  error?: string | null;
  isLoading?: boolean;
}

export default function TidyLibraryModal({
  isOpen = true,
  onClose,
  onConfirm,
  error,
  isLoading,
}: TidyLibraryModalProps) {
  const { t } = useTranslation();
  const [tidyGenericFiles, setTidyGenericFiles] = useState(false);

  return (
    <Modal
      variant="confirmation"
      isOpen={isOpen}
      isPending={isLoading}
      onClose={onClose}
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary',
        },
        {
          label: isLoading ? t('Tidying…') : t('Tidy Library'),
          onClick: () => onConfirm({ tidyGenericFiles }),
          disabled: isLoading,
        },
      ]}
      size="md"
    >
      <div className="flex flex-col p-5 gap-3">
        <div>
          <div className="flex justify-center mb-4">
            <div className="bg-blue-100 w-15.5 h-15.5 text-blue-800 border-blue-50 border-[7px] rounded-full p-3">
              <Sparkles size={26} />
            </div>
          </div>
          <h2 className="text-center text-lg font-semibold mb-2">{t('Tidy Library')}</h2>
        </div>
        <p className="text-center text-gray-500">
          {t(
            'Tidying your Library will delete any unused media. Are you sure you want to proceed?',
          )}
        </p>

        <span className="center gap-px rounded-md bg-gray-50 p-1.5">
          <Info size={12} />
          <p className="text-[12px] font-medium px-1">
            {t('Media that is being used will not be deleted. This process cannot be undone.')}
          </p>
        </span>

        {error && (
          <div className="mt-2 text-center">
            <p className="text-sm font-medium text-red-600">{error}</p>
          </div>
        )}

        <div className="p-2.5 flex flex-col gap-5.5">
          <Checkbox
            id="tidyGenericFiles"
            className="items-center"
            title={t('Tidy Generic Files')}
            label={t('Should generic files, such as PDFs and documents, also be removed?')}
            checked={tidyGenericFiles}
            onChange={() => setTidyGenericFiles((prev) => !prev)}
          />
        </div>
      </div>
    </Modal>
  );
}
