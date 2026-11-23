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

import { notify } from '@/components/ui/Notification';
import { clearModuleCache, updateModuleSettings } from '@/services/moduleApi';
import type { UpdateModuleSettingsRequest } from '@/services/moduleApi';

interface UseModuleActionsProps {
  t: TFunction;
  handleRefresh: () => void;
  closeModal: () => void;
}

export function useModuleActions({ t, handleRefresh, closeModal }: UseModuleActionsProps) {
  const [isSaving, setIsSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  const [isClearing, setIsClearing] = useState(false);
  const [clearError, setClearError] = useState<string | null>(null);

  const confirmSaveSettings = async (id: string, settings: UpdateModuleSettingsRequest) => {
    if (isSaving) return;

    try {
      setIsSaving(true);
      setSaveError(null);
      await updateModuleSettings(id, settings);
      handleRefresh();
      closeModal();
    } catch (err) {
      const message =
        isAxiosError(err) && err.response?.data?.message
          ? err.response.data.message
          : t('Failed to save module settings.');
      setSaveError(message);
    } finally {
      setIsSaving(false);
    }
  };

  const confirmClearCache = async (id: string, options?: { notifyOnError?: boolean }) => {
    if (isClearing) return;

    try {
      setIsClearing(true);
      setClearError(null);
      await clearModuleCache(id);
      handleRefresh();
      closeModal();
    } catch (err) {
      const message =
        isAxiosError(err) && err.response?.data?.message
          ? err.response.data.message
          : t('Failed to clear module cache.');
      if (options?.notifyOnError) {
        notify.error(message);
      } else {
        setClearError(message);
      }
    } finally {
      setIsClearing(false);
    }
  };

  return {
    isSaving,
    saveError,
    setSaveError,
    confirmSaveSettings,
    isClearing,
    clearError,
    setClearError,
    confirmClearCache,
  };
}
