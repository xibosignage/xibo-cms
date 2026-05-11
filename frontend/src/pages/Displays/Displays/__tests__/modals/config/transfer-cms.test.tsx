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

import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import TransferCmsModal from '../../../components/TransferCmsModal';

import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('react-i18next', () => {
  const t = (key: string) => key;
  return {
    useTranslation: () => ({ t, i18n: { changeLanguage: vi.fn() } }),
    Trans: ({ children }: { children: React.ReactNode }) => children,
  };
});

vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Tests
// =============================================================================

describe('TransferCmsModal', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  // ---------------------------------------------------------------------------
  // Save requires both address AND 2FA code. Filling only the address is not
  // enough.
  // ---------------------------------------------------------------------------
  test('Save is disabled when only the CMS address is filled', async () => {
    const user = userEvent.setup();

    render(
      <TransferCmsModal
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        isActionPending={false}
        actionError={null}
      />,
    );

    await user.type(screen.getByLabelText(/new cms address/i), 'https://new.cms.example.com');

    expect(screen.getByRole('button', { name: /^save$/i })).toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // Filling only the 2FA code without the address is also insufficient.
  // ---------------------------------------------------------------------------
  test('Save is disabled when only the two-factor code is filled', async () => {
    const user = userEvent.setup();

    render(
      <TransferCmsModal
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        isActionPending={false}
        actionError={null}
      />,
    );

    await user.type(screen.getByLabelText(/two factor code/i), '123456');

    expect(screen.getByRole('button', { name: /^save$/i })).toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // Save becomes enabled once both the address AND the 2FA code are non-empty.
  // The CMS key is optional and must not be required for the button to enable.
  // ---------------------------------------------------------------------------
  test('Save is enabled when both CMS address and two-factor code are filled', async () => {
    const user = userEvent.setup();

    render(
      <TransferCmsModal
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        isActionPending={false}
        actionError={null}
      />,
    );

    await user.type(screen.getByLabelText(/new cms address/i), 'https://new.cms.example.com');
    await user.type(screen.getByLabelText(/two factor code/i), '123456');

    expect(screen.getByRole('button', { name: /^save$/i })).not.toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // The CMS key is optional — omitting it must not prevent Save from being
  // clickable once address and 2FA are both provided.
  // ---------------------------------------------------------------------------
  test('CMS key field is optional and does not affect Save availability', async () => {
    const onConfirm = vi.fn();
    const user = userEvent.setup();

    render(
      <TransferCmsModal
        onClose={vi.fn()}
        onConfirm={onConfirm}
        isActionPending={false}
        actionError={null}
      />,
    );

    await user.type(screen.getByLabelText(/new cms address/i), 'https://new.cms.example.com');
    await user.type(screen.getByLabelText(/two factor code/i), '654321');
    // deliberately leave New CMS Key empty

    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(onConfirm).toHaveBeenCalledWith({
      newCmsAddress: 'https://new.cms.example.com',
      newCmsKey: '',
      twoFactorCode: '654321',
    });
  });

  // ---------------------------------------------------------------------------
  // Cancel calls onClose to dismiss the modal.
  // ---------------------------------------------------------------------------
  test('Cancel button calls onClose', async () => {
    const onClose = vi.fn();
    const user = userEvent.setup();

    render(
      <TransferCmsModal
        onClose={onClose}
        onConfirm={vi.fn()}
        isActionPending={false}
        actionError={null}
      />,
    );

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(onClose).toHaveBeenCalledOnce();
  });

  // ---------------------------------------------------------------------------
  // While the action is in-flight the Save button must be disabled and show
  // "Saving…" to prevent double-submission.
  // ---------------------------------------------------------------------------
  test('Save button is disabled and shows pending label when isActionPending is true', () => {
    render(
      <TransferCmsModal
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        isActionPending={true}
        actionError={null}
      />,
    );

    expect(screen.getByRole('button', { name: /saving/i })).toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // API errors must surface via the modal's error slot.
  // ---------------------------------------------------------------------------
  test('shows error message when actionError is provided', () => {
    render(
      <TransferCmsModal
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        isActionPending={false}
        actionError="Transfer failed: invalid 2FA code"
      />,
    );

    expect(screen.getByRole('alert')).toHaveTextContent('Transfer failed: invalid 2FA code');
  });
});
