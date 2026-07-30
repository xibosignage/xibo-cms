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

import { screen, waitFor } from '@testing-library/react';
import { expect } from 'vitest';

// Wait for a confirmation dialog (identified by its heading/body text) to
// close — the only signal available when the modal is an internal
// implementation detail of the page under test (no onClose prop to hook).
export const waitForDialogToClose = async (text: string | RegExp): Promise<void> => {
  await waitFor(() => {
    expect(screen.queryByText(text)).not.toBeInTheDocument();
  });
};

// Wait for an isolated modal's onClose to have been called — the precise
// signal when the test owns the modal's onClose prop directly.
export const waitForClose = async (onClose: (...args: unknown[]) => unknown): Promise<void> => {
  await waitFor(() => {
    expect(onClose).toHaveBeenCalled();
  });
};
