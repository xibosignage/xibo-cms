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

import type { ModalType } from '../TransitionsConfig';

import EditTransitionModal from './EditTransitionModal';

import type { Transition } from '@/types/transition';

interface TransitionModalsProps {
  actions: {
    activeModal: ModalType;
    closeModal: () => void;
    saveError: string | null;
    isSaving: boolean;
  };
  selection: {
    selectedTransition: Transition | null;
  };
  handlers: {
    confirmEdit: (transitionId: number, availableAsIn: boolean, availableAsOut: boolean) => void;
  };
}

export function TransitionModals({ actions, selection, handlers }: TransitionModalsProps) {
  const isModalOpen = (name: string) => actions.activeModal === name;

  return (
    <>
      {isModalOpen('edit') && (
        <EditTransitionModal
          onClose={actions.closeModal}
          transition={selection.selectedTransition}
          onSave={handlers.confirmEdit}
          error={actions.saveError}
          isLoading={actions.isSaving}
        />
      )}
    </>
  );
}
