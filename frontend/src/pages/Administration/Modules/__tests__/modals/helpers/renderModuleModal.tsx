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

import { render } from '@testing-library/react';
import { vi } from 'vitest';

import ClearCacheModuleModal from '../../../components/ClearCacheModuleModal';
import ConfigureModuleModal from '../../../components/ConfigureModuleModal';
import { mockModule } from '../../fixtures/module';

import type { UpdateModuleSettingsRequest } from '@/services/moduleApi';
import type { Module } from '@/types/module';

interface ConfigureOptions {
  module?: Module;
  onClose?: () => void;
  onSave?: (id: string, settings: UpdateModuleSettingsRequest) => void;
  error?: string | null;
  isLoading?: boolean;
}

export const renderConfigureModal = (options: ConfigureOptions = {}) => {
  const {
    module = mockModule,
    onClose = vi.fn(),
    onSave = vi.fn(),
    error = null,
    isLoading = false,
  } = options;

  return {
    onClose,
    onSave,
    ...render(
      <ConfigureModuleModal
        module={module}
        onClose={onClose}
        onSave={onSave}
        error={error}
        isLoading={isLoading}
      />,
    ),
  };
};

interface ClearCacheOptions {
  module?: Module;
  onClose?: () => void;
  onConfirm?: () => void;
  error?: string | null;
  isLoading?: boolean;
}

export const renderClearCacheModal = (options: ClearCacheOptions = {}) => {
  const {
    module = mockModule,
    onClose = vi.fn(),
    onConfirm = vi.fn(),
    error = null,
    isLoading = false,
  } = options;

  return {
    onClose,
    onConfirm,
    ...render(
      <ClearCacheModuleModal
        module={module}
        onClose={onClose}
        onConfirm={onConfirm}
        error={error}
        isLoading={isLoading}
      />,
    ),
  };
};
