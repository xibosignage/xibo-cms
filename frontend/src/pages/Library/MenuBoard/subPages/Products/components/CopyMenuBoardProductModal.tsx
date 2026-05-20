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

import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import NumberInput from '@/components/ui/forms/NumberInput';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import type { MenuBoardProduct } from '@/types/menuBoardProduct';
import { incrementName } from '@/utils/stringUtils';

interface CopyMenuBoardProductModalProps {
  isOpen?: boolean;
  onClose: () => void;
  onConfirm: (newName: string, newPrice: number | null, newCode: string) => void;
  product: MenuBoardProduct | null;
  isLoading?: boolean;
  existingNames: string[];
}

export default function CopyMenuBoardProductModal({
  isOpen = true,
  onClose,
  onConfirm,
  product,
  isLoading,
  existingNames,
}: CopyMenuBoardProductModalProps) {
  const { t } = useTranslation();
  const [newName, setNewName] = useState('');
  const [newPrice, setNewPrice] = useState<number | undefined>(undefined);
  const [newCode, setNewCode] = useState('');
  const [error, setError] = useState<string | undefined>();

  useEffect(() => {
    if (isOpen) {
      if (product) {
        setNewName(incrementName(product.name));
        setNewPrice(product.price ?? undefined);
        setNewCode(product.code ?? '');
      } else {
        setNewName('');
        setNewPrice(undefined);
        setNewCode('');
      }
    }
    setError(undefined);
  }, [product, isOpen]);

  const handleSave = () => {
    const trimmed = newName.trim();

    if (!trimmed) {
      setError(t('Name is required'));
      return;
    }

    if (existingNames.some((n) => n.toLowerCase() === trimmed.toLowerCase())) {
      setError(t('A product with this name already exists'));
      return;
    }

    setError(undefined);
    onConfirm(trimmed, newPrice !== undefined ? newPrice : null, newCode);
  };

  return (
    <Modal
      isOpen={isOpen}
      title={t('Copy Product')}
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
      <div className="px-8 pb-8 space-y-4 pt-4">
        <TextInput
          name="newName"
          value={newName}
          label={t('Name')}
          helpText={t('The Name for this Product.')}
          error={error}
          onChange={(val) => {
            setNewName(val);
            if (error) {
              setError(undefined);
            }
          }}
        />

        <NumberInput
          name="newPrice"
          label={t('Price')}
          helpText={t('The Price for this Menu Board Product.')}
          value={newPrice}
          onChange={(val) => {
            setNewPrice(val);
          }}
        />

        <TextInput
          name="newCode"
          value={newCode}
          label={t('Code')}
          helpText={t('The Code identifier for this Product.')}
          optional
          onChange={(val) => {
            setNewCode(val);
          }}
        />
      </div>
    </Modal>
  );
}
