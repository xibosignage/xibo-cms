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

import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import type { UserEvent } from '@testing-library/user-event';
import type { ComponentType } from 'react';
import { MemoryRouter } from 'react-router-dom';

import { settingsQueryKey } from '../fixtures/reportRows';

import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import type { User } from '@/types/user';

const UTC_SETTINGS = {
  defaultTimezone: 'UTC',
  defaultLanguage: 'en',
  DATE_FORMAT_JS: 'YYYY-MM-DD',
  TIME_FORMAT_JS: 'HH:mm',
};

// Route access is checked in routes.contract.
export const superAdmin: User = buildCurrentUser(PERSONAS.superAdmin, { settings: UTC_SETTINGS });

// Pre-seeds saved settings so the page skips its loading pulse.
export const renderReportPage = (
  Page: ComponentType,
  { route, settingsKey, user = superAdmin }: { route: string; settingsKey: string; user?: User },
) => {
  testQueryClient.setQueryData(settingsQueryKey(settingsKey), null);
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={user}>
        <MemoryRouter initialEntries={[route]}>
          <Page />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
};

export const pickOption = async (user: UserEvent, comboboxName: RegExp, option: string) => {
  await user.click(screen.getByRole('combobox', { name: comboboxName }));
  await user.click(await screen.findByRole('option', { name: option }));
};

export const applyButton = () => screen.getByRole('button', { name: /^apply$/i });
