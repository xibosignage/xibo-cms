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

import Modal from '@/components/ui/modals/Modal';
import { fetchTagUsage } from '@/services/tagApi';
import type { Tag, TagUsageEntry } from '@/types/tag';

interface TagUsageModalProps {
  isOpen?: boolean;
  tag: Tag | null;
  onClose: () => void;
}

export default function TagUsageModal({ isOpen = true, tag, onClose }: TagUsageModalProps) {
  const { t } = useTranslation();
  const [entries, setEntries] = useState<TagUsageEntry[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!isOpen || !tag) return;

    const controller = new AbortController();
    setIsLoading(true);
    setError(null);

    fetchTagUsage(tag.tagId, controller.signal)
      .then(({ rows }) => {
        if (!controller.signal.aborted) {
          setEntries(rows);
        }
      })
      .catch((err) => {
        if (!controller.signal.aborted) {
          setError(err instanceof Error ? err.message : t('Failed to load usage data.'));
        }
      })
      .finally(() => {
        if (!controller.signal.aborted) setIsLoading(false);
      });

    return () => controller.abort();
  }, [isOpen, tag?.tagId]);

  if (!isOpen || !tag) return null;

  return (
    <Modal
      title={t('Usage Report for {{name}}', { name: tag.tag })}
      isOpen={isOpen}
      variant="standard"
      onClose={onClose}
      size="lg"
      actions={[{ label: t('Close'), onClick: onClose, variant: 'secondary' }]}
    >
      <div className="p-6">
        {isLoading && (
          <div className="flex items-center justify-center py-8">
            <span className="text-gray-400">{t('Loading...')}</span>
          </div>
        )}

        {error && (
          <div className="bg-red-50 border border-red-200 text-red-800 p-4 rounded mb-4">
            {error}
          </div>
        )}

        {!isLoading && !error && entries.length === 0 && (
          <p className="text-gray-500 text-center py-8">{t('This tag is not currently used.')}</p>
        )}

        {!isLoading && entries.length > 0 && (
          <div className="overflow-x-auto">
            <table className="w-full text-sm text-left border-collapse">
              <thead>
                <tr className="border-b border-gray-200">
                  <th className="py-2 px-3 font-semibold text-gray-600">{t('ID')}</th>
                  <th className="py-2 px-3 font-semibold text-gray-600">{t('Type')}</th>
                  <th className="py-2 px-3 font-semibold text-gray-600">{t('Name')}</th>
                  <th className="py-2 px-3 font-semibold text-gray-600">{t('Tag Value')}</th>
                </tr>
              </thead>
              <tbody>
                {entries.map((entry, i) => (
                  <tr key={i} className="border-b border-gray-100 hover:bg-gray-50">
                    <td className="py-2 px-3 text-gray-700">{entry.entityId}</td>
                    <td className="py-2 px-3 text-gray-700">{entry.type}</td>
                    <td className="py-2 px-3 text-gray-700">{entry.name}</td>
                    <td className="py-2 px-3 text-gray-700">{entry.value ?? ''}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </Modal>
  );
}
