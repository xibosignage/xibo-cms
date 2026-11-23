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

import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import Modal from '@/components/ui/modals/Modal';
import { getCommonFormOptions } from '@/config/commonForms';
import type { Media } from '@/types/media';

interface EnableStatsMediaModalProps {
  media: Media | null;
  isOpen?: boolean;
  isLoading?: boolean;
  onClose: () => void;
  onConfirm: (value: string) => void;
}

export default function EnableStatsMediaModal({
  media,
  isOpen = true,
  isLoading,
  onClose,
  onConfirm,
}: EnableStatsMediaModalProps) {
  const { t } = useTranslation();
  const [value, setValue] = useState<string>('Inherit');

  useEffect(() => {
    if (media) {
      setValue(media.enableStat);
    }
  }, [media]);

  if (!media) {
    return null;
  }

  return (
    <Modal
      variant="confirmation"
      title={t('Enable Stats Collection')}
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
          label: t('Save'),
          onClick: () => onConfirm(value),
          disabled: isLoading,
        },
      ]}
    >
      <div className="p-4 flex flex-col gap-3">
        <SelectDropdown
          label={t('Enable Media Stats Collection?')}
          value={value}
          placeholder={t('Inherit')}
          options={getCommonFormOptions(t).inherit}
          onSelect={(v) => setValue(v)}
          helpText={t(
            "Enable the collection of Proof of Play statistics for this Media Item. Ensure that 'Enable Stats Collection' is set to 'On' in the Display Settings.",
          )}
        />
      </div>
    </Modal>
  );
}
