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

import { useMutation, useQueryClient } from '@tanstack/react-query';
import { isAxiosError } from 'axios';
import type { TFunction } from 'i18next';

import { adCampaignKeys } from './useAdCampaignData';

import { notify } from '@/components/ui/Notification';
import type { AssignAdLayoutPayload, UpdateAdCampaignPayload } from '@/services/campaignApi';
import {
  assignLayoutToAdCampaign,
  removeLayoutAssignment,
  updateAdCampaign,
} from '@/services/campaignApi';

export function getErrorMessage(err: unknown, fallback: string): string {
  if (isAxiosError(err) && err.response?.data?.message) {
    return err.response.data.message as string;
  }
  if (err instanceof Error) {
    return err.message;
  }
  return fallback;
}

interface UseAdCampaignActionsArgs {
  campaignId: number;
  t: TFunction;
}

export function useAdCampaignActions({ campaignId, t }: UseAdCampaignActionsArgs) {
  const queryClient = useQueryClient();

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: adCampaignKeys.detail(campaignId) });
    queryClient.invalidateQueries({ queryKey: ['campaign'] });
  };

  const saveGeneral = useMutation({
    mutationFn: (payload: UpdateAdCampaignPayload) => updateAdCampaign(campaignId, payload),
    onSuccess: () => {
      notify.success(t('Campaign saved'));
      invalidate();
    },
    onError: (err) => notify.error(getErrorMessage(err, t('Failed to save campaign'))),
  });

  const assignLayout = useMutation({
    mutationFn: (payload: AssignAdLayoutPayload) => assignLayoutToAdCampaign(campaignId, payload),
    onSuccess: () => {
      notify.success(t('Layout assigned'));
      invalidate();
    },
    onError: (err) => notify.error(getErrorMessage(err, t('Failed to assign layout'))),
  });

  const removeLayout = useMutation({
    mutationFn: ({ layoutId, displayOrder }: { layoutId: number; displayOrder?: number }) =>
      removeLayoutAssignment(campaignId, layoutId, displayOrder),
    onSuccess: () => {
      notify.success(t('Layout removed'));
      invalidate();
    },
    onError: (err) => notify.error(getErrorMessage(err, t('Failed to remove layout'))),
  });

  return { saveGeneral, assignLayout, removeLayout };
}
