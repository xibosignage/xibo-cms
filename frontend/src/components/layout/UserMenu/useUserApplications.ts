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

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { notify } from '@/components/ui/Notification';
import {
  fetchUserApplications,
  revokeApplicationAccess,
  type UserApplication,
} from '@/services/userApi';

export const userApplicationsQueryKeys = {
  all: ['userApplications'] as const,
  byUser: (userId: number) => [...userApplicationsQueryKeys.all, userId] as const,
};

export function useUserApplications(userId: number | undefined, enabled: boolean) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: userApplicationsQueryKeys.byUser(userId ?? 0),
    queryFn: () => fetchUserApplications(userId as number),
    enabled: enabled && !!userId,
    staleTime: 1000 * 60,
  });

  const revokeMutation = useMutation({
    mutationFn: (application: UserApplication) => revokeApplicationAccess(application.id, userId!),
    onSuccess: (_, application) => {
      queryClient.setQueryData<UserApplication[]>(
        userApplicationsQueryKeys.byUser(userId!),
        (prev) => prev?.filter((app) => app.id !== application.id) ?? [],
      );
      notify.success(t(`Access to ${application.name} revoked`));
    },
    onError: () => {
      notify.error(t('Failed to revoke access for this application'));
    },
  });

  return { query, revokeMutation };
}
