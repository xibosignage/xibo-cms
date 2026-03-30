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

import LogoutSessionModal from './LogoutSessionModal';

import type { Session } from '@/types/session';

interface SessionModalsProps {
  actions: {
    activeModal: string | null;
    closeModal: () => void;
    handleRefresh: () => void;
    setSessionList: React.Dispatch<React.SetStateAction<Session[]>>;
    logoutError: string | null;
    isLoggingOut: boolean;
  };
  selection: {
    sessionToLogout: Session[];
  };
  handlers: {
    confirmLogout: (items: Session[]) => void;
  };
}

export function SessionModals({ actions, selection, handlers }: SessionModalsProps) {
  const isModalOpen = (name: string) => actions.activeModal === name;

  return (
    <>
      {isModalOpen('logout') && (
        <LogoutSessionModal
          onClose={actions.closeModal}
          onLogout={() => handlers.confirmLogout(selection.sessionToLogout)}
          userName={selection.sessionToLogout[0]?.userName}
          itemCount={selection.sessionToLogout.length}
          error={actions.logoutError}
          isLoading={actions.isLoggingOut}
        />
      )}
    </>
  );
}
