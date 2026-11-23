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

import { Info, Trash2Icon } from 'lucide-react';
import { Trans, useTranslation } from 'react-i18next';

import Modal from '@/components/ui/modals/Modal';

interface DeleteTemplateModalProps {
  isOpen?: boolean;
  onClose: () => void;
  onDelete: () => void;
  itemCount: number;
  templateName?: string;
  error?: string | null;
  isLoading?: boolean;
}

export default function DeleteTemplateModal({
  isOpen = true,
  onClose,
  onDelete,
  templateName,
  isLoading,
  itemCount,
  error,
}: DeleteTemplateModalProps) {
  const { t } = useTranslation();
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
          label: isLoading ? t('Deleting…') : t('Yes, Delete'),
          onClick: () => onDelete(),
          disabled: isLoading,
        },
      ]}
      size="md"
    >
      <div className="flex flex-col p-5 gap-3">
        <div>
          <div className="flex justify-center mb-4">
            <div className="bg-red-100 w-15.5 h-15.5 text-red-800 border-red-50 border-[7px] rounded-full p-3">
              <Trash2Icon size={26} />
            </div>
          </div>
          <h2 className="text-center text-lg font-semibold mb-2 text-red-800">
            {itemCount === 1 ? t('Delete Template?') : t('Delete Templates?')}
          </h2>
        </div>
        <p className="text-center text-gray-500">
          {itemCount === 1 ? (
            <Trans
              i18nKey='Are you sure you want to delete "<strong>{{name}}</strong>"?'
              values={{ name: templateName }}
              components={{ strong: <strong /> }}
            />
          ) : (
            <Trans
              i18nKey="Are you sure you want to delete <strong>{{count}}</strong> templates?"
              values={{ count: itemCount }}
              components={{ strong: <strong /> }}
            />
          )}
        </p>

        <span className="flex gap-px rounded-md bg-gray-50 p-1.5">
          <Info size={12} />
          <span className="text-[12px] px-1 font-medium">
            {t(
              'All media will be unassigned and any widgets such as text/RSS will be lost, unless they are on playlists. The item will be removed from all Schedules.',
            )}
          </span>
        </span>

        {error && (
          <div className="mt-2 text-center">
            <p className="text-sm font-medium text-red-600">{error}</p>
          </div>
        )}
      </div>
    </Modal>
  );
}
