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

import type { ModalType } from '../ModulesConfig';

import ClearCacheModuleModal from './ClearCacheModuleModal';
import ConfigureModuleModal from './ConfigureModuleModal';

import { AUTO_SUBMIT_FORMS } from '@/constants/autoSubmitForms';
import type { UpdateModuleSettingsRequest } from '@/services/moduleApi';
import type { Module } from '@/types/module';

interface ModuleModalsProps {
  actions: {
    activeModal: ModalType;
    closeModal: () => void;
    handleRefresh: () => void;
    saveError: string | null;
    isSaving: boolean;
    clearError: string | null;
    isClearing: boolean;
  };
  selection: {
    selectedModule: Module | null;
  };
  handlers: {
    confirmSaveSettings: (id: string, settings: UpdateModuleSettingsRequest) => void;
    confirmClearCache: (id: string) => void;
  };
}

export function ModuleModals({ actions, selection, handlers }: ModuleModalsProps) {
  const isModalOpen = (name: string) => actions.activeModal === name;

  if (!selection.selectedModule) return null;

  return (
    <>
      {isModalOpen('configure') && (
        <ConfigureModuleModal
          module={selection.selectedModule}
          onClose={actions.closeModal}
          onSave={handlers.confirmSaveSettings}
          error={actions.saveError}
          isLoading={actions.isSaving}
        />
      )}

      {isModalOpen('clearCache') && (
        <ClearCacheModuleModal
          module={selection.selectedModule}
          onClose={actions.closeModal}
          onConfirm={() => handlers.confirmClearCache(selection.selectedModule!.moduleId)}
          error={actions.clearError}
          isLoading={actions.isClearing}
          autoSubmitFormId={AUTO_SUBMIT_FORMS.moduleClearCache}
        />
      )}
    </>
  );
}
