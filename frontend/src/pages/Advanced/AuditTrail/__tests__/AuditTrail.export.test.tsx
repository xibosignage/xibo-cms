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

import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi, beforeEach } from 'vitest';

import AuditTrailExportModal from '../components/AuditTrailExportModal';

import * as auditTrailApi from '@/services/auditTrailApi';

// Use the real __mocks__/Modal.tsx shim so modal content renders
vi.mock('@/components/ui/modals/Modal');

// Mock DateFilter as a plain text input so dates can be set programmatically.
// The real DateFilter uses a floating calendar picker that is not interaction-testable
// in a unit-test environment.
vi.mock('@/components/ui/DateFilter', () => ({
  default: ({
    name,
    label,
    onChange,
  }: {
    name: string;
    label: string;
    value: string;
    onChange: (name: string, val: string | null) => void;
  }) => (
    <div>
      <label htmlFor={name}>{label}</label>
      <input
        id={name}
        data-testid={name}
        type="text"
        onChange={(e) => onChange(name, e.target.value || null)}
      />
    </div>
  ),
}));

vi.mock('@/services/auditTrailApi', () => ({
  fetchAuditTrail: vi.fn(),
  exportAuditTrail: vi.fn(),
}));

// --- Tests ---

describe('AuditTrailExportModal', () => {
  const onClose = vi.fn();

  const renderModal = () => {
    const user = userEvent.setup();
    return { user, ...render(<AuditTrailExportModal onClose={onClose} />) };
  };

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('displays the "Output Audit Trail as CSV" title', () => {
    renderModal();

    expect(screen.getByText('Output Audit Trail as CSV')).toBeInTheDocument();
  });

  it('displays the "From Date" label', () => {
    renderModal();

    expect(screen.getByText('From Date')).toBeInTheDocument();
  });

  it('displays the "To Date" label', () => {
    renderModal();

    expect(screen.getByText('To Date')).toBeInTheDocument();
  });

  it('renders the Cancel button', () => {
    renderModal();

    expect(screen.getByRole('button', { name: /cancel/i })).toBeInTheDocument();
  });

  it('renders the Export button', () => {
    renderModal();

    expect(screen.getByRole('button', { name: /^export$/i })).toBeInTheDocument();
  });

  it('calls onClose when the Cancel button is clicked', async () => {
    const { user } = renderModal();

    await user.click(screen.getByRole('button', { name: /cancel/i }));

    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('shows a validation error when Export is clicked with no dates set', async () => {
    const { user } = renderModal();

    await user.click(screen.getByRole('button', { name: /^export$/i }));

    expect(screen.getByText('Please provide a from and to date.')).toBeInTheDocument();
  });

  it('shows a validation error when only fromDt is set', async () => {
    const { user } = renderModal();

    await user.type(screen.getByTestId('filterFromDt'), '2024-01-01');
    await user.click(screen.getByRole('button', { name: /^export$/i }));

    expect(screen.getByText('Please provide a from and to date.')).toBeInTheDocument();
  });

  it('shows a validation error when only toDt is set', async () => {
    const { user } = renderModal();

    await user.type(screen.getByTestId('filterToDt'), '2024-01-31');
    await user.click(screen.getByRole('button', { name: /^export$/i }));

    expect(screen.getByText('Please provide a from and to date.')).toBeInTheDocument();
  });

  it('Export button shows "Exporting…" while the API request is in flight', async () => {
    vi.mocked(auditTrailApi.exportAuditTrail).mockImplementation(() => new Promise(() => {}));

    const { user } = renderModal();

    await user.type(screen.getByTestId('filterFromDt'), '2024-01-01');
    await user.type(screen.getByTestId('filterToDt'), '2024-01-31');
    await user.click(screen.getByRole('button', { name: /^export$/i }));

    await waitFor(() => {
      expect(screen.getByText('Exporting…')).toBeInTheDocument();
    });
  });

  it('Export button is disabled while the API request is in flight', async () => {
    vi.mocked(auditTrailApi.exportAuditTrail).mockImplementation(() => new Promise(() => {}));

    const { user } = renderModal();

    await user.type(screen.getByTestId('filterFromDt'), '2024-01-01');
    await user.type(screen.getByTestId('filterToDt'), '2024-01-31');
    await user.click(screen.getByRole('button', { name: /^export$/i }));

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /exporting/i })).toBeDisabled();
    });
  });

  it('calls exportAuditTrail with the correct dates when both dates are provided', async () => {
    vi.mocked(auditTrailApi.exportAuditTrail).mockResolvedValue(undefined);

    const { user } = renderModal();

    await user.type(screen.getByTestId('filterFromDt'), '2024-01-01');
    await user.type(screen.getByTestId('filterToDt'), '2024-01-31');
    await user.click(screen.getByRole('button', { name: /^export$/i }));

    await waitFor(() => {
      expect(auditTrailApi.exportAuditTrail).toHaveBeenCalledWith({
        filterFromDt: '2024-01-01',
        filterToDt: '2024-01-31',
      });
    });
  });

  it('calls onClose after a successful export', async () => {
    vi.mocked(auditTrailApi.exportAuditTrail).mockResolvedValue(undefined);

    const { user } = renderModal();

    await user.type(screen.getByTestId('filterFromDt'), '2024-01-01');
    await user.type(screen.getByTestId('filterToDt'), '2024-01-31');
    await user.click(screen.getByRole('button', { name: /^export$/i }));

    await waitFor(() => {
      expect(onClose).toHaveBeenCalledTimes(1);
    });
  });

  it('shows "Export failed. Please try again." when the API throws', async () => {
    vi.mocked(auditTrailApi.exportAuditTrail).mockRejectedValue(new Error('Network timeout'));

    const { user } = renderModal();

    await user.type(screen.getByTestId('filterFromDt'), '2024-01-01');
    await user.type(screen.getByTestId('filterToDt'), '2024-01-31');
    await user.click(screen.getByRole('button', { name: /^export$/i }));

    await waitFor(() => {
      expect(screen.getByText('Export failed. Please try again.')).toBeInTheDocument();
    });
  });

  it('does not call onClose when the export fails', async () => {
    vi.mocked(auditTrailApi.exportAuditTrail).mockRejectedValue(new Error('fail'));

    const { user } = renderModal();

    await user.type(screen.getByTestId('filterFromDt'), '2024-01-01');
    await user.type(screen.getByTestId('filterToDt'), '2024-01-31');
    await user.click(screen.getByRole('button', { name: /^export$/i }));

    await waitFor(() => {
      expect(screen.getByText('Export failed. Please try again.')).toBeInTheDocument();
    });
    expect(onClose).not.toHaveBeenCalled();
  });

  it('pressing Escape calls onClose', async () => {
    const { user } = renderModal();

    await user.keyboard('{Escape}');

    expect(onClose).toHaveBeenCalledTimes(1);
  });
});
