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

import { AlertTriangle } from 'lucide-react';
import { Trans, useTranslation } from 'react-i18next';

import Modal from '@/components/ui/modals/Modal';

interface DiscardTemplateModalProps {
  isOpen?: boolean;
  onClose: () => void;
  onConfirm: () => void;
  templateName?: string;
  isLoading?: boolean;
}

export default function DiscardTemplateModal({
  isOpen,
  onClose,
  onConfirm,
  templateName,
  isLoading,
}: DiscardTemplateModalProps) {
  const { t } = useTranslation();

  return (
    <Modal
      variant="confirmation"
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
          label: isLoading ? t('Discarding…') : t('Discard'),
          onClick: onConfirm,
          disabled: isLoading,
        },
      ]}
      size="md"
    >
      <div className="flex flex-col p-5 gap-3 text-center">
        <div className="flex justify-center mb-2">
          <div className="bg-red-100 w-15.5 h-15.5 text-red-600 border-red-50 border-[7px] rounded-full p-3">
            <AlertTriangle size={26} />
          </div>
        </div>

        <h2 className="text-lg font-semibold text-gray-800">{t('Discard Changes?')}</h2>

        <p className="text-gray-500">
          <Trans
            i18nKey="Are you sure you want to discard changes for <strong>{{name}}</strong>?"
            values={{ name: templateName }}
            components={{ strong: <strong /> }}
          />
        </p>

        <p className="text-sm text-gray-400">
          {t('This will restore the original template and cannot be undone.')}
        </p>
      </div>
    </Modal>
  );
}
