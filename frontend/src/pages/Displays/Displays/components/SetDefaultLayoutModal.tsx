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

import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import type { SelectOption } from '@/components/ui/forms/SelectDropdown';
import Modal from '@/components/ui/modals/Modal';
import { useDebounce } from '@/hooks/useDebounce';
import { fetchLayouts } from '@/services/layoutsApi';
import type { Display } from '@/types/display';

const LAYOUT_PAGE_SIZE = 10;

interface SetDefaultLayoutModalProps {
  display?: Display;
  onClose: () => void;
  onConfirm: (layoutId: number) => void;
  isActionPending: boolean;
  actionError: string | null;
}

export default function SetDefaultLayoutModal({
  display,
  onClose,
  onConfirm,
  isActionPending,
  actionError,
}: SetDefaultLayoutModalProps) {
  const { t } = useTranslation();

  const [selectedLayoutId, setSelectedLayoutId] = useState<number | null>(
    display?.defaultLayoutId ?? null,
  );
  const [layouts, setLayouts] = useState<SelectOption[]>([]);
  const [isLoadingLayouts, setIsLoadingLayouts] = useState(false);
  const [isLoadingMoreLayouts, setIsLoadingMoreLayouts] = useState(false);
  const [hasMoreLayouts, setHasMoreLayouts] = useState(false);
  const [layoutPage, setLayoutPage] = useState(0);
  const [layoutSearch, setLayoutSearch] = useState('');
  const debouncedLayoutSearch = useDebounce(layoutSearch, 300);

  useEffect(() => {
    setIsLoadingLayouts(true);
    setLayouts([]);
    setLayoutPage(0);
    fetchLayouts({
      start: 0,
      length: LAYOUT_PAGE_SIZE,
      retired: 0,
      layout: debouncedLayoutSearch || undefined,
    })
      .then((res) => {
        const options = res.rows.map((l) => ({
          value: String(l.layoutId),
          label: l.layout,
        }));
        // Ensure current layout is in the list when not searching
        if (
          !debouncedLayoutSearch &&
          display?.defaultLayoutId &&
          !options.some((o) => o.value === String(display.defaultLayoutId))
        ) {
          options.push({
            value: String(display.defaultLayoutId),
            label: display.defaultLayout ?? String(display.defaultLayoutId),
          });
        }
        setLayouts(options);
        setHasMoreLayouts(res.rows.length === LAYOUT_PAGE_SIZE);
      })
      .catch(() => setLayouts([]))
      .finally(() => setIsLoadingLayouts(false));
  }, [display?.defaultLayoutId, display?.defaultLayout, debouncedLayoutSearch]);

  const handleLoadMore = () => {
    if (isLoadingMoreLayouts || !hasMoreLayouts) {
      return;
    }
    const nextPage = layoutPage + 1;
    setIsLoadingMoreLayouts(true);
    fetchLayouts({
      start: nextPage * LAYOUT_PAGE_SIZE,
      length: LAYOUT_PAGE_SIZE,
      retired: 0,
      layout: debouncedLayoutSearch || undefined,
    })
      .then((res) => {
        setLayouts((prev) => [
          ...prev,
          ...res.rows.map((l) => ({ value: String(l.layoutId), label: l.layout })),
        ]);
        setLayoutPage(nextPage);
        setHasMoreLayouts(res.rows.length === LAYOUT_PAGE_SIZE);
      })
      .catch(() => {})
      .finally(() => setIsLoadingMoreLayouts(false));
  };

  return (
    <Modal
      title={t('Set Default Layout')}
      isOpen
      isPending={isActionPending}
      onClose={onClose}
      error={actionError ?? undefined}
      size="md"
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary', disabled: isActionPending },
        {
          label: isActionPending ? t('Saving…') : t('Save'),
          onClick: () => {
            if (selectedLayoutId !== null) {
              onConfirm(selectedLayoutId);
            }
          },
          disabled: isActionPending || selectedLayoutId === null,
        },
      ]}
    >
      <div className="flex flex-col p-5 gap-4">
        <p className="text-sm text-gray-500">
          {t(
            'Set the Default Layout to use when no other content is scheduled to this Display. This will override the global Default Layout as set in CMS Administrator Settings.',
          )}
        </p>
        <SelectDropdown
          label={t('Default Layout')}
          value={selectedLayoutId ? String(selectedLayoutId) : ''}
          options={layouts}
          onSelect={(v) => setSelectedLayoutId(v ? Number(v) : null)}
          isLoading={isLoadingLayouts}
          onLoadMore={handleLoadMore}
          hasMore={hasMoreLayouts}
          isLoadingMore={isLoadingMoreLayouts}
          searchable
          searchPlaceholder={t('Search layouts...')}
          onSearch={(v) => setLayoutSearch(v)}
        />
      </div>
    </Modal>
  );
}
