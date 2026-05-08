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

import http from '@/lib/api';
import type { MenuBoard } from '@/types/menuBoard';
import type { MenuBoardCategory } from '@/types/menuBoardCategory';
import type { MenuBoardProduct } from '@/types/menuBoardProduct';

// menuboards

export interface FetchMenuBoardRequest {
  start: number;
  length: number;
  keyword?: string;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;
  folderId?: number;
  menuId?: number;
  userId?: number;
  name?: string;
  code?: string;
  logicalOperatorName?: string;
  modifiedDateFrom?: string;
  modifiedDateTo?: string;
}

export interface FetchMenuBoardResponse {
  rows: MenuBoard[];
  totalCount: number;
}

export async function fetchMenuBoard(
  options: FetchMenuBoardRequest = { start: 0, length: 10 },
): Promise<FetchMenuBoardResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/menuboards', {
    params: queryParams,
    signal,
  });

  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : 0;

  return { rows: response.data, totalCount };
}

export async function getMenuBoardById(menuBoardId: string | number): Promise<MenuBoard> {
  const response = await http.get('/menuboards', {
    params: { menuId: menuBoardId },
  });

  return response.data[0];
}

export interface CreateMenuBoardRequest {
  name: string;
  description?: string | null;
  code?: string | null;
  folderId?: number | null;
}

export async function createMenuBoard(data: CreateMenuBoardRequest): Promise<MenuBoard> {
  const params = new URLSearchParams();

  Object.entries(data).forEach(([key, value]) => {
    if (value !== undefined && value !== null) {
      params.append(key, String(value));
    }
  });

  const response = await http.post('/menuboard', params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return response.data;
}

export interface UpdateMenuBoardRequest {
  name: string;
  description?: string | null;
  code?: string | null;
  folderId?: number | null;
}

export async function updateMenuBoard(
  menuBoardId: number | string,
  data: UpdateMenuBoardRequest,
): Promise<MenuBoard> {
  const params = new URLSearchParams();

  Object.entries(data).forEach(([key, value]) => {
    if (value !== undefined && value !== null) {
      params.append(key, String(value));
    }
  });

  const response = await http.put(`/menuboard/${menuBoardId}`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return response.data;
}

export interface CopyMenuBoardRequest {
  menuBoardId: number | string;
  name: string;
  description?: string;
  code?: string;
}

export async function copyMenuBoard({
  menuBoardId,
  name,
  description,
  code,
}: CopyMenuBoardRequest): Promise<MenuBoard> {
  const params = new URLSearchParams();
  params.append('name', name);
  if (description !== undefined) params.append('description', description);
  if (code !== undefined) params.append('code', code);

  const response = await http.post(`/menuboard/copy/${menuBoardId}`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return response.data;
}

export async function deleteMenuBoard(menuBoardId: number | string): Promise<void> {
  await http.delete(`/menuboard/${menuBoardId}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
}

export async function selectMenuBoardFolder(
  menuBoardId: number | string,
  folderId: number,
): Promise<void> {
  const params = new URLSearchParams();
  params.append('folderId', String(folderId));

  await http.put(`/menuboard/${menuBoardId}/selectfolder`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });
}

// Categories

export interface FetchMenuBoardCategoriesRequest {
  start?: number;
  length?: number;
  keyword?: string;
  sortBy?: string;
  sortDir?: string;
  menuCategoryId?: number;
  name?: string;
  code?: string;
  modifiedDateFrom?: string;
  modifiedDateTo?: string;
  signal?: AbortSignal;
}

export interface FetchMenuBoardCategoriesResponse {
  rows: MenuBoardCategory[];
  totalCount: number;
}

export async function fetchMenuBoardCategories(
  menuId: string | number,
  options: FetchMenuBoardCategoriesRequest = { start: 0, length: 10 },
): Promise<FetchMenuBoardCategoriesResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get(`/menuboard/${menuId}/categories`, {
    params: queryParams,
    signal,
  });

  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : 0;

  return { rows: response.data, totalCount };
}

export async function getCategoryById(
  menuId: string | number,
  categoryId: string | number,
): Promise<MenuBoardCategory | undefined> {
  const response = await http.get(`/menuboard/${menuId}/categories`, {
    params: { menuCategoryId: categoryId, start: 0, length: 1 },
  });
  return response.data[0] as MenuBoardCategory | undefined;
}

export interface MenuBoardCategoryRequest {
  name: string;
  description?: string | null;
  code?: string | null;
  mediaId?: number | null;
}

export async function createMenuBoardCategory(
  menuId: string | number,
  data: MenuBoardCategoryRequest,
): Promise<MenuBoardCategory> {
  const params = new URLSearchParams();

  Object.entries(data).forEach(([key, value]) => {
    if (value !== undefined && value !== null) {
      params.append(key, String(value));
    }
  });

  const response = await http.post(`/menuboard/${menuId}/category`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return response.data;
}

export async function updateMenuBoardCategory(
  menuCategoryId: string | number,
  data: MenuBoardCategoryRequest,
): Promise<MenuBoardCategory> {
  const params = new URLSearchParams();

  Object.entries(data).forEach(([key, value]) => {
    if (value !== undefined && value !== null) {
      params.append(key, String(value));
    }
  });

  const response = await http.put(`/menuboard/${menuCategoryId}/category`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return response.data;
}

export async function deleteMenuBoardCategory(menuCategoryId: string | number): Promise<void> {
  await http.delete(`/menuboard/${menuCategoryId}/category`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
}

export interface CopyMenuBoardCategoryRequest {
  menuCategoryId: number | string;
  name: string;
  description?: string;
  code?: string;
}

export async function copyMenuBoardCategory({
  menuCategoryId,
  name,
  description,
  code,
}: CopyMenuBoardCategoryRequest): Promise<MenuBoardCategory> {
  const params = new URLSearchParams();
  params.append('name', name);
  if (description !== undefined) params.append('description', description);
  if (code !== undefined) params.append('code', code);

  const response = await http.post(
    `/menuboard/category/copy/${menuCategoryId}`,
    params.toString(),
    {
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest',
      },
    },
  );

  return response.data;
}

// Products

export interface FetchMenuBoardProductsRequest {
  start?: number;
  length?: number;
  keyword?: string;
  sortBy?: string;
  sortDir?: string;
  menuProductId?: number;
  name?: string;
  code?: string;
  availability?: number;
  signal?: AbortSignal;
}

export interface FetchMenuBoardProductsResponse {
  rows: MenuBoardProduct[];
  totalCount: number;
}

export async function fetchMenuBoardProducts(
  menuCategoryId: string | number,
  options: FetchMenuBoardProductsRequest = { start: 0, length: 10 },
): Promise<FetchMenuBoardProductsResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get(`/menuboard/${menuCategoryId}/products`, {
    params: queryParams,
    signal,
  });

  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : 0;

  return { rows: response.data, totalCount };
}

export interface FetchMenuBoardProductsForWidgetRequest {
  menuId?: number;
  menuProductId?: number;
  menuCategoryId?: number;
  name?: string;
  availability?: number;
  categories?: string;
  signal?: AbortSignal;
}

export async function fetchMenuBoardProductsForWidget(
  options: FetchMenuBoardProductsForWidgetRequest = {},
): Promise<MenuBoardProduct[]> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/menuboard/products', {
    params: queryParams,
    signal,
  });

  return response.data;
}

export interface MenuBoardProductOptionRequest {
  option: string;
  value: string;
}

export interface MenuBoardProductRequest {
  name: string;
  price?: number | null;
  description?: string | null;
  code?: string | null;
  displayOrder?: number | null;
  availability?: number | null;
  allergyInfo?: string | null;
  calories?: number | null;
  mediaId?: number | null;
  productOptions?: MenuBoardProductOptionRequest[];
}

function buildProductParams(data: MenuBoardProductRequest): URLSearchParams {
  const params = new URLSearchParams();
  const { productOptions, ...scalar } = data;

  Object.entries(scalar).forEach(([key, value]) => {
    if (value !== undefined && value !== null) {
      params.append(key, String(value));
    }
  });

  (productOptions ?? []).forEach((opt) => {
    params.append('productOptions[]', opt.option);
    params.append('productValues[]', opt.value);
  });

  return params;
}

export async function createMenuBoardProduct(
  menuCategoryId: string | number,
  data: MenuBoardProductRequest,
): Promise<MenuBoardProduct> {
  const response = await http.post(
    `/menuboard/${menuCategoryId}/product`,
    buildProductParams(data).toString(),
    {
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest',
      },
    },
  );

  return response.data;
}

export async function updateMenuBoardProduct(
  menuProductId: string | number,
  data: MenuBoardProductRequest,
): Promise<MenuBoardProduct> {
  const response = await http.put(
    `/menuboard/${menuProductId}/product`,
    buildProductParams(data).toString(),
    {
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest',
      },
    },
  );

  return response.data;
}

export async function deleteMenuBoardProduct(menuProductId: string | number): Promise<void> {
  await http.delete(`/menuboard/${menuProductId}/product`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
}
