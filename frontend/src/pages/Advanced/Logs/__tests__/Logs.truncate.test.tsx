import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi, beforeEach } from 'vitest';

import TruncateLogsModal from '../components/TruncateLogsModal';

import * as logApi from '@/services/logApi';

// Use the real __mocks__/Modal.tsx shim so modal content renders
vi.mock('@/components/ui/modals/Modal');

// Declare the service mock here (NOT via Setup.tsx) so the hoisted vi.mock is the
// only mock for this module in this test file, keeping logApi.truncateLogs and the
// TruncateLogsModal import referencing the same vi.fn() instance.
vi.mock('@/services/logApi', () => ({
  fetchLogs: vi.fn(),
  truncateLogs: vi.fn(),
}));

// --- Tests ---

describe('TruncateLogsModal', () => {
  const onClose = vi.fn();
  const onSuccess = vi.fn();

  const renderModal = () => {
    const user = userEvent.setup();
    return { user, ...render(<TruncateLogsModal onClose={onClose} onSuccess={onSuccess} />) };
  };

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('displays the "Truncate Log" heading', () => {
    renderModal();

    expect(screen.getByText('Truncate Log')).toBeInTheDocument();
  });

  it('displays the confirmation message about permanent deletion', () => {
    renderModal();

    expect(screen.getByText(/are you sure you want to truncate all logs/i)).toBeInTheDocument();
  });

  it('calls onClose when the Cancel button is clicked', async () => {
    const { user } = renderModal();

    await user.click(screen.getByRole('button', { name: /cancel/i }));

    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('calls the truncateLogs API when the Truncate button is clicked', async () => {
    vi.mocked(logApi.truncateLogs).mockResolvedValue(undefined);

    const { user } = renderModal();

    await user.click(screen.getByRole('button', { name: /^truncate$/i }));

    await waitFor(() => {
      expect(logApi.truncateLogs).toHaveBeenCalledTimes(1);
    });
  });

  it('calls onSuccess after a successful truncation', async () => {
    vi.mocked(logApi.truncateLogs).mockResolvedValue(undefined);

    const { user } = renderModal();

    await user.click(screen.getByRole('button', { name: /^truncate$/i }));

    await waitFor(() => {
      expect(onSuccess).toHaveBeenCalledTimes(1);
    });
  });

  it('calls onClose after a successful truncation', async () => {
    vi.mocked(logApi.truncateLogs).mockResolvedValue(undefined);

    const { user } = renderModal();

    await user.click(screen.getByRole('button', { name: /^truncate$/i }));

    await waitFor(() => {
      expect(onClose).toHaveBeenCalledTimes(1);
    });
  });

  it('shows the server error message when the API returns an Axios error', async () => {
    const axiosError = {
      isAxiosError: true,
      response: { data: { message: 'Insufficient permissions to truncate logs.' } },
    };
    vi.mocked(logApi.truncateLogs).mockRejectedValue(axiosError);

    const { user } = renderModal();

    await user.click(screen.getByRole('button', { name: /^truncate$/i }));

    await waitFor(() => {
      expect(screen.getByText('Insufficient permissions to truncate logs.')).toBeInTheDocument();
    });
  });

  it('shows a generic error message when the API throws a non-Axios error', async () => {
    vi.mocked(logApi.truncateLogs).mockRejectedValue(new Error('Network timeout'));

    const { user } = renderModal();

    await user.click(screen.getByRole('button', { name: /^truncate$/i }));

    await waitFor(() => {
      expect(screen.getByText('Failed to truncate logs. Please try again.')).toBeInTheDocument();
    });
  });

  it('changes the Truncate button label to "Truncating..." while the request is in flight', async () => {
    vi.mocked(logApi.truncateLogs).mockImplementation(() => new Promise(() => {}));

    const { user } = renderModal();

    await user.click(screen.getByRole('button', { name: /^truncate$/i }));

    await waitFor(() => {
      expect(screen.getByText('Truncating...')).toBeInTheDocument();
    });
  });

  it('disables the Truncate button while the request is in flight', async () => {
    vi.mocked(logApi.truncateLogs).mockImplementation(() => new Promise(() => {}));

    const { user } = renderModal();

    await user.click(screen.getByRole('button', { name: /^truncate$/i }));

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /truncating.../i })).toBeDisabled();
    });
  });

  it('does not call onClose when the API fails', async () => {
    vi.mocked(logApi.truncateLogs).mockRejectedValue(new Error('fail'));

    const { user } = renderModal();

    await user.click(screen.getByRole('button', { name: /^truncate$/i }));

    await waitFor(() => {
      expect(screen.getByText('Failed to truncate logs. Please try again.')).toBeInTheDocument();
    });
    expect(onClose).not.toHaveBeenCalled();
  });
});
