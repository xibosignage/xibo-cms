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

import { fetchUsers } from '@/services/userApi';
import type { User } from '@/types/user';

interface UseOwnerProps {
  ownerId?: number | null;
}

export function useOwner({ ownerId }: UseOwnerProps = {}) {
  const [owner, setOwner] = useState<User | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (ownerId == null) {
      setOwner(null);
      return;
    }

    let isMounted = true;

    async function loadOwner() {
      setLoading(true);
      setError(null);

      try {
        const { rows } = await fetchUsers();

        if (!isMounted) return;

        const foundOwner = rows.find((u) => u.userId === ownerId) ?? null;
        setOwner(foundOwner);
      } catch (err) {
        console.error('Failed to fetch owner', err);
        if (isMounted) {
          setError('Failed to load owner');
        }
      } finally {
        if (isMounted) {
          setLoading(false);
        }
      }
    }

    loadOwner();

    return () => {
      isMounted = false;
    };
  }, [ownerId]);

  return {
    owner,
    loading,
    error,
  };
}
