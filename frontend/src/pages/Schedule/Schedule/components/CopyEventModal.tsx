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

import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import type { Event } from '@/types/event';
import { incrementName } from '@/utils/stringUtils';

interface CopyEventModalProps {
  isOpen?: boolean;
  onClose: () => void;
  onConfirm: (newName: string) => void;
  scheduleEvent: Event | null;
  isLoading?: boolean;
  existingNames: string[];
}

export default function CopyEventModal({
  isOpen = true,
  onClose,
  onConfirm,
  scheduleEvent,
  isLoading,
  existingNames,
}: CopyEventModalProps) {
  const { t } = useTranslation();
  const [newName, setNewName] = useState('');
  const [error, setError] = useState<string | undefined>();

  useEffect(() => {
    if (scheduleEvent && isOpen) {
      setNewName(incrementName(scheduleEvent.name ?? ''));
    }

    setError(undefined);
  }, [scheduleEvent, isOpen]);

  const handleSave = () => {
    const trimmed = newName.trim();

    if (!trimmed) {
      setError(t('Name is required'));
      return;
    }

    const nameExists = existingNames.some((name) => name.toLowerCase() === trimmed.toLowerCase());

    if (nameExists) {
      setError(t('An event item with this name already exists'));
      return;
    }

    setError(undefined);
    onConfirm(trimmed);
  };

  if (!isOpen) {
    return null;
  }

  return (
    <Modal
      isOpen={isOpen}
      title={t('Copy Event')}
      onClose={onClose}
      size="sm"
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary',
          disabled: isLoading,
        },
        {
          label: isLoading ? t('Saving…') : t('Save'),
          onClick: handleSave,
          disabled: isLoading,
        },
      ]}
    >
      <div className="px-8 pb-8 space-y-4">
        <TextInput
          name="newName"
          value={newName}
          label={t('New name')}
          helpText={t('The Name of the Event - (1 - 50 characters)')}
          error={error}
          onChange={(val) => {
            setNewName(val);
            if (error) {
              setError(undefined);
            }
          }}
        />
      </div>
    </Modal>
  );
}
