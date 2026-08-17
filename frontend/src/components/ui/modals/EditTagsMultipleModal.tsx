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

import { isAxiosError } from 'axios';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { notify } from '@/components/ui/Notification';
import TagInput, { collectTags, serializeTags } from '@/components/ui/forms/TagInput';
import Modal from '@/components/ui/modals/Modal';
import { editMultipleTags } from '@/services/tagApi';
import type { Tag } from '@/types/tag';

interface EditTagsMultipleModalProps {
  isOpen?: boolean;
  targetType: string;
  ids: Array<number | string>;
  existingTags?: Tag[];
  onClose: () => void;
  onSuccess?: () => void | Promise<void>;
}

export default function EditTagsMultipleModal({
  isOpen = true,
  targetType,
  ids,
  existingTags = [],
  onClose,
  onSuccess,
}: EditTagsMultipleModalProps) {
  const { t } = useTranslation();

  const [addTags, setAddTags] = useState<Tag[]>([]);
  const [addInput, setAddInput] = useState('');
  const [removeTags, setRemoveTags] = useState<Tag[]>([]);
  const [removeInput, setRemoveInput] = useState('');
  const [hasPendingValue, setHasPendingValue] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string>();

  const sameTag = (a: Tag, b: Tag) => a.tag === b.tag && (a.value ?? '') === (b.value ?? '');

  const removeCandidates = existingTags.filter((tag) => !removeTags.some((r) => sameTag(r, tag)));

  const handleAddToRemove = (tag: Tag) => {
    if (removeTags.some((r) => sameTag(r, tag))) {
      return;
    }
    setRemoveTags((prev) => [...prev, tag]);
  };

  const handleConfirm = async () => {
    if (isLoading || hasPendingValue || ids.length === 0) {
      return;
    }

    const finalAdd = collectTags(addTags, addInput);
    const finalRemove = collectTags(removeTags, removeInput);

    if (finalAdd.length === 0 && finalRemove.length === 0) {
      setError(t('Add or remove at least one tag.'));
      return;
    }

    try {
      setIsLoading(true);
      setError(undefined);

      const result = await editMultipleTags({
        targetType,
        ids,
        addTags: serializeTags(finalAdd),
        removeTags: serializeTags(finalRemove),
      });

      if (result.failedCount > 0) {
        notify.error(
          t('{{count}} of {{total}} item(s) could not be updated', {
            count: result.failedCount,
            total: ids.length,
          }),
          { description: result.failedNames.join(', ') },
        );
      } else {
        notify.success(t('Tags updated for {{count}} selected items.', { count: ids.length }));
      }

      await onSuccess?.();
    } catch (err) {
      console.error(err);
      setError(
        isAxiosError(err) && err.response?.data?.message
          ? err.response.data.message
          : t('Failed to update tags.'),
      );
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <Modal
      title={t('Edit Tags')}
      isOpen={isOpen}
      onClose={onClose}
      isPending={isLoading}
      error={error}
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary',
          disabled: isLoading,
        },
        {
          label: isLoading ? t('Saving…') : t('Save'),
          onClick: handleConfirm,
          disabled: isLoading || hasPendingValue || ids.length === 0,
        },
      ]}
    >
      <div className="flex flex-col gap-4 p-4">
        <p className="text-sm text-gray-500">
          {t('Editing tags for {{count}} selected items.', { count: ids.length })}
        </p>

        <TagInput
          label={t('Add Tags')}
          value={addTags}
          onChange={setAddTags}
          inputValue={addInput}
          onInputChange={setAddInput}
          onPendingValueChange={setHasPendingValue}
          helpText={t('Tags separated by commas. Use Tag|Value for tagged attributes.')}
        />

        <TagInput
          label={t('Remove Tags')}
          value={removeTags}
          onChange={setRemoveTags}
          inputValue={removeInput}
          onInputChange={setRemoveInput}
          suggestions={false}
          helpText={t('Tags to remove from the selected items.')}
        />

        {removeCandidates.length > 0 && (
          <div className="flex flex-col gap-2">
            <span className="text-xs font-semibold text-gray-500">
              {t('Current tags (click to remove)')}
            </span>
            <div className="flex flex-wrap gap-2">
              {removeCandidates.map((tag) => (
                <button
                  key={`${tag.tag}|${tag.value ?? ''}`}
                  type="button"
                  onClick={() => handleAddToRemove(tag)}
                  className="px-2 py-1 text-sm font-semibold border rounded-full text-gray-600 border-gray-300 hover:border-red-400 hover:text-red-600"
                >
                  {tag.value !== '' && tag.value != null ? `${tag.tag}|${tag.value}` : tag.tag}
                </button>
              ))}
            </div>
          </div>
        )}
      </div>
    </Modal>
  );
}
