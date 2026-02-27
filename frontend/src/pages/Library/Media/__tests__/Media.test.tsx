import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { vi, beforeEach } from 'vitest';

import Media from '../Media';
import { useMediaData } from '../hooks/useMediaData';

import { UploadProvider } from '@/context/UploadContext';
import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';
import type { User, UserFeatures } from '@/types/user';

vi.mock('@/components/ui/modals/Modal', () => ({
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  default: ({ isOpen, title, children, actions }: any) => {
    if (!isOpen) return null;
    return (
      <div role="dialog" aria-label={title}>
        <h1>{title}</h1>
        {children}
        <div>
          {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
          {actions?.map((action: any, i: number) => (
            <button key={i} onClick={action.onClick} disabled={action.disabled}>
              {action.label}
            </button>
          ))}
        </div>
      </div>
    );
  },
}));

vi.mock('@/services/folderApi', async (importOriginal) => {
  // eslint-disable-next-line @typescript-eslint/consistent-type-imports
  const actual = await importOriginal<typeof import('@/services/folderApi')>();
  return {
    ...actual,
    fetchContextButtons: vi.fn().mockResolvedValue({ create: true }),
    selectFolder: vi.fn(),
  };
});

// Mock the data fetching hook to control loading/error/empty states
vi.mock('../hooks/useMediaData', () => ({
  useMediaData: vi.fn(),
}));

const mockUser = {
  userId: 1,
  userName: 'MockUser',
  userTypeId: 1,
  email: 'mockemail@email.com',
  firstName: 'Mock',
  lastName: 'User',
  phone: '123456789',
  features: {
    'folder.view': true,
  } as UserFeatures,
} as User;

// Covers: Verify Media page loads successfully
const renderMediaPage = () => {
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UploadProvider>
        <UserProvider initialUser={mockUser}>
          <MemoryRouter>
            <Media />
          </MemoryRouter>
        </UserProvider>
      </UploadProvider>
    </QueryClientProvider>,
  );
};

describe('Media page', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Default successful response
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    (useMediaData as any).mockReturnValue({
      data: { rows: [], totalCount: 0 },
      isFetching: false,
      isError: false,
      error: null,
    });
  });

  test('verifies initial UI elements and successful load', async () => {
    await act(async () => {
      renderMediaPage();
    });

    // Covers: Verify default view mode is Table View.
    expect(screen.getByTitle('Table View')).toBeInTheDocument();

    // Covers: Verify media table is visible.
    expect(screen.getByRole('table')).toBeInTheDocument();

    // Covers: Verify Tab navigation shows Media tab.
    expect(screen.getByRole('button', { name: 'Media' })).toBeInTheDocument();

    // Covers: Verify Add Media button is visible on page load.
    expect(screen.getByRole('button', { name: 'Add Media' })).toBeInTheDocument();

    // Covers: Verify search input is visible
    expect(screen.getByPlaceholderText('Search media...')).toBeInTheDocument();

    // Covers: Verify Filters button is visible.
    expect(screen.getByRole('button', { name: 'Filters' })).toBeInTheDocument();

    // Covers: Verify no error alert appears when data loads successfully
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();

    // Covers: Verify table headers display correctly (Name, Type, Size, Date, Actions, etc.).
    expect(screen.getByRole('columnheader', { name: 'Thumbnail' })).toBeInTheDocument();
    expect(screen.getByRole('columnheader', { name: 'Name' })).toBeInTheDocument();
    expect(screen.getByRole('columnheader', { name: 'Type' })).toBeInTheDocument();
    expect(screen.getByRole('columnheader', { name: 'Tags' })).toBeInTheDocument();
    expect(screen.getByRole('columnheader', { name: 'Duration' })).toBeInTheDocument();
    expect(screen.getByRole('columnheader', { name: 'Size' })).toBeInTheDocument();
    expect(screen.getByRole('columnheader', { name: 'Resolution' })).toBeInTheDocument();

    // Covers: Verify empty state message when no media exists.
    expect(document.querySelector('.no-results')).toBeInTheDocument();
  });

  test('verifies loading state while fetching data', async () => {
    // Override mock to simulate loading state
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    (useMediaData as any).mockReturnValue({
      data: undefined,
      isFetching: true,
      isError: false,
      error: null,
    });

    await act(async () => {
      renderMediaPage();
    });

    // Covers: Verify loading state/spinner while fetching data.
    expect(document.querySelector('.animate-spin')).toBeInTheDocument();
  });

  test('verifies error message when API fails', async () => {
    // Override mock to simulate error state
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    (useMediaData as any).mockReturnValue({
      data: undefined,
      isFetching: false,
      isError: true,
      error: new Error('API connection failed'),
    });

    await act(async () => {
      renderMediaPage();
    });

    // Covers: Verify error message when API fails.
    const alert = screen.getByRole('alert');
    expect(alert).toBeInTheDocument();
    expect(alert).toHaveTextContent('API connection failed');
  });

  test('opens Add Media modal and simulates file upload', async () => {
    const user = userEvent.setup();
    await act(async () => {
      renderMediaPage();
    });

    const addMediaButton = screen.getByRole('button', { name: 'Add Media' });
    expect(addMediaButton).toBeInTheDocument();
    await user.click(addMediaButton);

    await waitFor(() => {
      expect(screen.getByRole('dialog', { name: 'Add Media' })).toBeInTheDocument();
    });

    const fileInput = document.querySelector('input[type="file"]') as HTMLInputElement;

    const file = new File(['test content'], 'chucknorris.png', { type: 'image/png' });

    if (fileInput) {
      await user.upload(fileInput, file);
    } else {
      throw new Error('Could not find file input!');
    }

    const doneButton = screen.getByRole('button', { name: 'Done' });
    expect(doneButton).toBeInTheDocument();
  });
});
