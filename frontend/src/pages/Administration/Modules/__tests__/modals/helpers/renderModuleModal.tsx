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
