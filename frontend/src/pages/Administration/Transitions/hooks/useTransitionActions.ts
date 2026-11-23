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

import { isAxiosError } from 'axios';
import type { TFunction } from 'i18next';
import { useState } from 'react';

import { updateTransition } from '@/services/transitionApi';

interface UseTransitionActionsProps {
  t: TFunction;
  handleRefresh: () => void;
  closeModal: () => void;
}

export function useTransitionActions({ t, handleRefresh, closeModal }: UseTransitionActionsProps) {
  const [isSaving, setIsSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  const confirmEdit = async (
    transitionId: number,
    availableAsIn: boolean,
    availableAsOut: boolean,
  ) => {
    if (isSaving) return;

    try {
      setIsSaving(true);
      await updateTransition(transitionId, {
        availableAsIn: availableAsIn ? 1 : 0,
        availableAsOut: availableAsOut ? 1 : 0,
      });
      handleRefresh();
      closeModal();
    } catch (err: unknown) {
      const message =
        isAxiosError(err) && err.response?.data?.message
          ? err.response.data.message
          : t('Failed to update transition.');
      setSaveError(message);
    } finally {
      setIsSaving(false);
    }
  };

  return {
    isSaving,
    saveError,
    setSaveError,
    confirmEdit,
  };
}
