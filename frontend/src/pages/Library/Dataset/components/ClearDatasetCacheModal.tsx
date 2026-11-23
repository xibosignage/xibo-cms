/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

import { Eraser } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import Modal from '@/components/ui/modals/Modal';

interface ClearDatasetCacheModalProps {
  onClose: () => void;
  onConfirm: () => void;
  isActionPending: boolean;
  autoSubmitFormId?: string;
}

export default function ClearDatasetCacheModal({
  onClose,
  onConfirm,
  isActionPending,
  autoSubmitFormId,
}: ClearDatasetCacheModalProps) {
  const { t } = useTranslation();

  return (
    <Modal
      variant="confirmation"
      isOpen
      isPending={isActionPending}
      onClose={onClose}
      autoSubmitFormId={autoSubmitFormId}
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary' },
        {
          label: isActionPending ? t('Clearing…') : t('Clear Cache'),
          onClick: onConfirm,
          disabled: isActionPending,
        },
      ]}
      size="md"
    >
      <div className="flex flex-col p-5 gap-3">
        <div className="flex justify-center mb-2">
          <div className="bg-blue-100 w-15.5 h-15.5 text-blue-800 border-blue-50 border-[7px] rounded-full p-3">
            <Eraser size={26} />
          </div>
        </div>
        <h2 className="text-center text-lg font-semibold mb-1">{t('Clear Cache')}</h2>
        <p className="text-center text-gray-500">
          {t('Should the cache for this remote DataSet be cleared?')}
        </p>
      </div>
    </Modal>
  );
}
