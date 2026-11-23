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

import { RefreshCw } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import Modal from '@/components/ui/modals/Modal';

interface CollectNowModalProps {
  onClose: () => void;
  onConfirm: () => void;
  isActionPending: boolean;
  actionError: string | null;
  autoSubmitFormId?: string;
}

export default function CollectNowModal({
  onClose,
  onConfirm,
  isActionPending,
  actionError,
  autoSubmitFormId,
}: CollectNowModalProps) {
  const { t } = useTranslation();

  return (
    <Modal
      variant="confirmation"
      isOpen
      isPending={isActionPending}
      onClose={onClose}
      autoSubmitFormId={autoSubmitFormId}
      error={actionError ?? undefined}
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary' },
        {
          label: isActionPending ? t('Collecting\u2026') : t('Collect Now'),
          onClick: onConfirm,
          disabled: isActionPending,
        },
      ]}
      size="md"
    >
      <div className="flex flex-col p-5 gap-3">
        <div className="flex justify-center mb-2">
          <div className="bg-blue-100 w-15.5 h-15.5 text-blue-800 border-blue-50 border-[7px] rounded-full p-3">
            <RefreshCw size={26} />
          </div>
        </div>
        <h2 className="text-center text-lg font-semibold mb-1">{t('Collect Now')}</h2>
        <p className="text-center text-gray-500">
          {t('Are you sure you want to request a collection to occur?')}
        </p>
      </div>
    </Modal>
  );
}
