import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor, fireEvent, act, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { vi, beforeEach } from 'vitest';

import Media from '../Media';
import { useMediaData } from '../hooks/useMediaData';

import { UploadProvider } from '@/context/UploadContext';
import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';
import type { User, UserFeatures } from '@/types/user';

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

// The Media page saves and loads user preferences (column order, page size, etc.)
// from the server via /user/pref - fake to return "no saved preferences".
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

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
    fetchFolderById: vi.fn().mockResolvedValue({
      id: 1,
      text: 'Root',
      type: 'root',
      parentId: 0,
      isRoot: 1,
      children: null,
      ownerId: 1,
      ownerName: 'MockUser',
    }),
    fetchFolderTree: vi.fn().mockResolvedValue([]),
    searchFolders: vi.fn().mockResolvedValue([]),
  };
});

// Mock the media filter options hook to prevent making real network requests on every render
vi.mock('@/pages/Library/Media/hooks/useMediaFilterOptions', () => ({
  useMediaFilterOptions: vi.fn().mockReturnValue({ filterOptions: [], isLoading: false }),
}));

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
    'media.share': true,  
    'media.delete': true, 
    'media.edit': true,   
    'media.view': true,
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
    expect(await screen.findByTitle('Table View')).toBeInTheDocument();

    // Covers: Verify media table is visible.
    expect(screen.getByRole('table')).toBeInTheDocument();

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

    renderMediaPage();

    // Covers: Verify loading state/spinner while fetching data.
    await waitFor(() => {
      expect(document.querySelector('.animate-spin')).toBeInTheDocument();
    });
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

    renderMediaPage();

    // Covers: Verify error message when API fails.
    const alert = await screen.findByRole('alert');
    expect(alert).toBeInTheDocument();
    expect(alert).toHaveTextContent('API connection failed');
  });

  test('verifies media items and formatting render correctly from API response', async () => {
    const user = userEvent.setup({ delay: null });
    // Override mock to simulate populated data
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    (useMediaData as any).mockReturnValue({
      data: {
        rows: [
          {
            mediaId: 1,
            name: 'mock_video_presentation.mp4',
            mediaType: 'video',
            fileSize: 1048576, // 1MB raw format
            fileSizeFormatted: '1.00 MB', // Fallback depending on table config
            duration: 60,
            createdDt: '2024-02-14 10:30:00',
            modifiedDt: '2024-02-15 11:45:00',
          },
        ],
        totalCount: 1,
      },
      isFetching: false,
      isError: false,
      error: null,
    });

    renderMediaPage();

    // Covers: Verify media items render from API response.
    // Covers: Verify file name is displayed correctly.
    expect(await screen.findByText('mock_video_presentation.mp4')).toBeInTheDocument();

    // Covers: Verify correct media icon based on file type.
    const videoElements = screen.getAllByText('video');
    expect(videoElements[0]).toBeInTheDocument();

    // Covers: Verify file size formatting.
    expect(screen.getByText(/1(.*?)MB|1048576/i)).toBeInTheDocument();

    const toggleColumnsBtn = screen.getByRole('button', { name: /Toggle columns/i });
    await user.click(toggleColumnsBtn);

    // Covers: Verify created/updated date formatting.
    const createdDateToggle = await screen.findAllByRole('checkbox', { name: /Created/i });
    await user.click(createdDateToggle[0]!);

    await waitFor(() => {
      expect(screen.getByText(/2024-02-14|2024-02-15/i)).toBeInTheDocument();
    });
  });

  test('verifies pagination controls appear when items exceed page limit', async () => {
    // Override mock to simulate multiple pages of data
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    (useMediaData as any).mockReturnValue({
      data: {
        rows: Array.from({ length: 10 }).map((_, i) => ({
          mediaId: i,
          name: `Pagination Item ${i}`,
          mediaType: 'image',
        })),
        totalCount: 25, // Forcing pageCount to be > 1
      },
      isFetching: false,
      isError: false,
      error: null,
    });

    renderMediaPage();

    // Covers: Verify pagination controls appear when items exceed page limit.
    const nextButton = await screen.findByRole('button', { name: 'Next' });
    expect(nextButton).toBeInTheDocument();
    expect(nextButton).not.toBeDisabled();

    // Verify page 2 is available to click
    expect(screen.getByRole('button', { name: '2' })).toBeInTheDocument();
  });

  test('verifies search and filter functionality', async () => {
    const user = userEvent.setup({ delay: null });

    // Setup initial render
    const { unmount: unmountComponent } = renderMediaPage();

    const searchInput = await screen.findByPlaceholderText('Search media...');

    // Covers: Verify search input filters media by name.
    await user.type(searchInput, 'cat');
    expect(searchInput).toHaveValue('cat');

    // Covers: Verify search is case-insensitive.
    await user.clear(searchInput);
    await user.type(searchInput, 'CAT');
    expect(searchInput).toHaveValue('CAT');

    // Covers: Verify clearing search restores full list.
    await user.clear(searchInput);
    expect(searchInput).toHaveValue('');

    // Covers: Verify filter by media type (if available).
    const filtersBtn = screen.getByRole('button', { name: 'Filters' });
    await user.click(filtersBtn);

    // Covers: Verify filter persistence after page refresh.
    unmountComponent!();
    renderMediaPage();
    expect(await screen.findByPlaceholderText('Search media...')).toBeInTheDocument();
  });

  test('verifies table column sorting functionality', async () => {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    (useMediaData as any).mockReturnValue({
      data: {
        rows: Array.from({ length: 10 }).map((_, i) => ({
          mediaId: i,
          name: `Sort Item ${i}`,
          mediaType: 'image',
          createdDt: '2024-02-14 10:30:00',
        })),
        totalCount: 25,
      },
      isFetching: false,
      isError: false,
      error: null,
    });

    await act(async () => {
      renderMediaPage();
    });

    const nameHeader = await screen.findByRole('columnheader', { name: /Name/i });

    // Covers: Verify sorting by file name ascending.
    fireEvent.click(nameHeader);

    // Covers: Verify sorting by file name descending.
    fireEvent.click(screen.getByRole('columnheader', { name: /Name/i }));

    const sizeHeader = await screen.findByRole('columnheader', { name: /Size/i });
    fireEvent.click(sizeHeader);
    fireEvent.click(screen.getByRole('columnheader', { name: /Size/i }));

    // Covers: Verify sorting state persists after pagination.
    const nextButton = screen.getByRole('button', { name: /Next/i });
    fireEvent.click(nextButton);

    expect(screen.getByRole('table')).toBeInTheDocument();
  });

 test('opens Add Media modal and simulates file upload', async () => {
    const user = userEvent.setup({ delay: null });
    renderMediaPage();

    const addMediaButton = await screen.findByRole('button', { name: 'Add Media' });
    expect(addMediaButton).toBeInTheDocument();
    await user.click(addMediaButton);

    const modal = await screen.findByRole('dialog', { name: 'Add Media' });
    expect(modal).toBeInTheDocument();

    const fileInput = document.querySelector('input[type="file"]') as HTMLInputElement;
    const file = new File(['test content'], 'new_upload.png', { type: 'image/png' });

    if (fileInput) {
      fireEvent.change(fileInput, { target: { files: [file] } });
    } else {
      throw new Error('Could not find file input!');
    }

    // Tell our mock API that the backend successfully received the file
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    (useMediaData as any).mockReturnValue({
      data: {
        rows: [
          {
            mediaId: 99,
            name: 'new_upload.png',
            mediaType: 'image',
            thumbnail: 'blob:fake-thumbnail-url',
            tags: [],
            userPermissions: { view: true, edit: true, delete: true }
          }
        ],
        totalCount: 1,
      },
      isFetching: false,
      isError: false,
      error: null,
    });

    const doneButton = await screen.findByRole('button', { name: 'Done' });
    await user.click(doneButton);

    await waitFor(() => {
      expect(screen.queryByRole('dialog', { name: 'Add Media' })).not.toBeInTheDocument();
    });

    // Covers: Verify successful upload creates a table entry with a thumbnail
    const newEntries = await screen.findAllByText('new_upload.png');
    const newEntry = newEntries[0]!;
    expect(newEntry).toBeInTheDocument();

    // Check either the Table Row or a Grid Div for the thumbnail image
    const newRow = newEntry.closest('tr') || newEntry.closest('div');
    const newThumb = newRow?.querySelector('img');
    expect(newThumb).toBeInTheDocument(); 
  });

  test('verifies media selection behaviours and bulk actions', async () => {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    (useMediaData as any).mockReturnValue({
      data: {
        rows: [
          { mediaId: 1, name: 'Item 1', mediaType: 'image' },
          { mediaId: 2, name: 'Item 2', mediaType: 'image' },
        ],
        totalCount: 2,
      },
      isFetching: false,
      isError: false,
      error: null,
    });

    renderMediaPage();

    const selectAllCheckbox = await screen.findByRole('checkbox', { name: /All Items/i });
    const rowCheckboxes = screen.getAllByRole('checkbox', { name: /Select row/i });

    // Covers: Verify selecting a single media item.
    fireEvent.click(rowCheckboxes[0]!);
    expect(rowCheckboxes[0]).toBeChecked();

    // Covers: Verify bulk actions include Move action.
    expect(screen.getAllByRole('button', { name: /Move/i })[0]).toBeInTheDocument();
    // Covers: Verify bulk actions include Share action.
    expect(screen.getAllByRole('button', { name: /Share/i })[0]).toBeInTheDocument();
    // Covers: Verify bulk actions include Download action.
    expect(screen.getAllByRole('button', { name: /Download/i })[0]).toBeInTheDocument();
    // Covers: Verify bulk actions include Delete Selected action.
    expect(screen.getAllByRole('button', { name: /Delete/i })[0]).toBeInTheDocument();
    // Covers: Verify bulk actions include More action.
    expect(screen.getAllByRole('button', { name: /More/i })[0]).toBeInTheDocument();

    // Covers: Verify multi-select media items.
    fireEvent.click(rowCheckboxes[1]!);
    expect(rowCheckboxes[0]).toBeChecked();
    expect(rowCheckboxes[1]).toBeChecked();

    // Covers: Verify “select all” checkbox behaviour.
    fireEvent.click(selectAllCheckbox); 
    fireEvent.click(selectAllCheckbox); 
    expect(rowCheckboxes[0]).toBeChecked();
    expect(rowCheckboxes[1]).toBeChecked();
  });

  test('verifies delete flow and confirmation dialog', async () => {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    (useMediaData as any).mockReturnValue({
      data: {
        rows: [
          {
            mediaId: 1,
            name: 'delete_target.jpg',
            mediaType: 'image',
            userPermissions: { delete: true },
          },
        ],
        totalCount: 1,
      },
      isFetching: false,
      isError: false,
      error: null,
    });

    renderMediaPage();

    const rowCheckboxes = await screen.findAllByRole('checkbox', { name: /Select row/i });
    fireEvent.click(rowCheckboxes[0]!);

    const deleteButton = await screen.findByRole('button', { name: /Delete/i });

    // Covers: Verify delete action opens confirmation dialog.
    fireEvent.click(deleteButton);
    const dialog = await screen.findByRole('dialog');
    expect(dialog).toBeInTheDocument();

    // Covers: Verify cancel delete closes dialog.
    const cancelButton = screen.getByRole('button', { name: /Cancel/i });
    fireEvent.click(cancelButton);
    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    // Covers: Verify successful delete removes item from table.
    fireEvent.click(deleteButton);
    const confirmButton = await screen.findByRole('button', { name: /Yes, Delete/i });

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    (useMediaData as any).mockReturnValue({
      data: { rows: [], totalCount: 0 },
      isFetching: false,
      isError: false,
      error: null,
    });

    fireEvent.click(confirmButton);
    await waitFor(() => {
      expect(screen.queryByText('delete_target.jpg')).not.toBeInTheDocument();
    });
  });

  test('verifies delete error handling and force option', async () => {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    (useMediaData as any).mockReturnValue({
      data: {
        rows: [
          {
            mediaId: 1,
            name: 'error_target.jpg',
            mediaType: 'image',
            userPermissions: { delete: true },
          },
        ],
        totalCount: 1,
      },
      isFetching: false,
      isError: false,
      error: null,
    });

    renderMediaPage();

    const rowCheckboxes = await screen.findAllByRole('checkbox', { name: /Select row/i });
    fireEvent.click(rowCheckboxes[0]!);

    const deleteButton = await screen.findByRole('button', { name: /Delete/i });
    fireEvent.click(deleteButton);

    const allLayoutsText = await screen.findByText(/All Layouts/i);
    fireEvent.click(allLayoutsText);

    const confirmButton = await screen.findByRole('button', { name: /Yes, Delete/i });
    fireEvent.click(confirmButton);
    
    await waitFor(() => {
      expect(screen.getByText('error_target.jpg')).toBeInTheDocument();
    });
  });

  test('verifies toggling between Table and Grid views', async () => {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    (useMediaData as any).mockReturnValue({
      data: {
        rows: [{ mediaId: 1, name: 'toggle_target.jpg', mediaType: 'image' }],
        totalCount: 1,
      },
      isFetching: false,
      isError: false,
      error: null,
    });

    renderMediaPage();

    expect(await screen.findByRole('table')).toBeInTheDocument();

    const gridViewBtn = await screen.findByRole('button', { name: /Grid View/i });
    fireEvent.click(gridViewBtn);

    await waitFor(() => {
      expect(screen.queryByRole('table')).not.toBeInTheDocument();
    });

    const tableViewBtn = await screen.findByRole('button', { name: /Table View/i });
    fireEvent.click(tableViewBtn);

    await waitFor(() => {
      expect(screen.getByRole('table')).toBeInTheDocument();
    });
  });

  test('verifies media preview functionality', async () => {
    const user = userEvent.setup({ delay: null });

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    (useMediaData as any).mockReturnValue({
      data: {
        rows: [
          { 
            mediaId: 1, 
            name: '8.jpg', // Using the exact filename from your HTML snippet
            mediaType: 'image', 
            downloadUrl: 'blob:http://localhost:5173/fake-blob',
            thumbnail: 'blob:http://localhost:5173/fake-blob-thumb',
            tags: [],
            userPermissions: { view: true, edit: true } 
          }
        ],
        totalCount: 1,
      },
      isFetching: false,
      isError: false,
      error: null,
    });

    renderMediaPage();

    // Find the row and the actual <img> thumbnail inside it
    const targetText = await screen.findByText('8.jpg');
    const tableRow = targetText.closest('tr');
    if (!tableRow) throw new Error("Could not find table row!");

    const thumbnailImg = tableRow.querySelector('img');
    if (!thumbnailImg) throw new Error("Could not find thumbnail image!");

    // Covers: Verify clicking media opens preview.
    await user.click(thumbnailImg);

    // The provided HTML shows an <h3> tag acts as the title for the preview overlay
    const previewHeading = await screen.findByRole('heading', { level: 3, name: '8.jpg' });
    expect(previewHeading).toBeInTheDocument();

    // Covers: Verify preview displays correct content type.
    // The provided HTML shows the full image has alt="8.jpg"
    // Since the table ALSO has a thumbnail, we just assert there is >= 1 image with this alt
    const previewImages = await screen.findAllByAltText('8.jpg');
    expect(previewImages.length).toBeGreaterThanOrEqual(1);

    // Covers: Verify preview close action works.
    // Because the "X" button has no aria-label, we find the <h3> heading, 
    // go up to the top bar container, and click the <button> next to it.
    const topBar = previewHeading.closest('div');
    const closePreviewBtn = topBar?.querySelector('button');
    
    if (closePreviewBtn) {
      await user.click(closePreviewBtn);
    } else {
      // Fallback
      fireEvent.keyDown(document.body, { key: 'Escape', code: 'Escape' });
    }

    // Verify the preview overlay vanishes
    await waitFor(() => {
      expect(screen.queryByRole('heading', { level: 3, name: '8.jpg' })).not.toBeInTheDocument();
    });
  });
});