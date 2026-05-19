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

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render } from '@testing-library/react';
import type { ReactElement } from 'react';
import React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { vi } from 'vitest';

import type { MenuBoard } from '@/types/menuBoard';
import type { MenuBoardCategory } from '@/types/menuBoardCategory';
import type { MenuBoardProduct } from '@/types/menuBoardProduct';

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (str: string) => str,
    i18n: { changeLanguage: () => new Promise(() => {}) },
  }),
}));

vi.mock('@/components/ui/Notification', () => ({
  notify: {
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  },
}));

const createTestQueryClient = () =>
  new QueryClient({
    defaultOptions: {
      queries: { retry: false },
    },
  });

export function renderWithProviders(ui: ReactElement, { route = '/' } = {}) {
  const testQueryClient = createTestQueryClient();

  return render(
    <QueryClientProvider client={testQueryClient}>
      <MemoryRouter initialEntries={[route]}>{ui}</MemoryRouter>
    </QueryClientProvider>,
  );
}

export const mockMenuBoard = (overrides = {}): MenuBoard =>
  ({
    menuId: 1,
    name: 'Test Menu Board',
    description: 'A test description',
    code: 'TMB_01',
    userId: 10,
    owner: 'testuser',
    modifiedDt: 1700000000,
    folderId: 1,
    permissionsFolderId: 1,
    groupsWithPermissions: '',
    ...overrides,
  }) as MenuBoard;

export const mockMenuBoardCategory = (overrides = {}): MenuBoardCategory =>
  ({
    menuCategoryId: 1,
    menuId: 1,
    name: 'Test Category',
    description: 'A test category description',
    code: 'CAT_01',
    mediaId: undefined,
    ...overrides,
  }) as MenuBoardCategory;

export const mockMenuBoardProduct = (overrides = {}): MenuBoardProduct =>
  ({
    menuProductId: 1,
    menuCategoryId: 1,
    menuId: 1,
    name: 'Test Product',
    price: 9.99,
    description: 'A test product',
    code: 'PROD_01',
    displayOrder: 1,
    availability: 1,
    allergyInfo: '',
    calories: 200,
    mediaId: undefined,
    productOptions: [],
    ...overrides,
  }) as MenuBoardProduct;
