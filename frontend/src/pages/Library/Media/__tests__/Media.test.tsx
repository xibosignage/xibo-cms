import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import userEvent from '@testing-library/user-event';
import { vi } from 'vitest';

import Media from '../Media';

import { UploadProvider } from '@/context/UploadContext';
import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';
import type { User } from '@/types/user';

vi.mock('@/components/ui/modals/Modal', () => ({
  default: ({ isOpen, title, children, actions }: any) => {
    if (!isOpen) return null;
    return (
      <div role="dialog" aria-label={title}>
        <h1>{title}</h1>
        {children}
        {/* Render the buttons passed in the 'actions' prop */}
        <div>
          {actions?.map((action: any, i: number) => (
            <button 
              key={i} 
              onClick={action.onClick} 
              disabled={action.disabled}
            >
              {action.label}
            </button>
          ))}
        </div>
      </div>
    );
  },
}));

vi.mock('@/services/folderApi', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/services/folderApi')>();
  return {
    ...actual,
    fetchContextButtons: vi.fn().mockResolvedValue({ create: true }),
    selectFolder: vi.fn(),
  };
});

const mockUser = {
  userId: 1,
  userName: 'MockUser',
  userTypeId: 1,
  email: 'mockemail@email.com',
  firstName: 'Mock',
  lastName: 'User',
  phone: '123456789',
} as User;

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
  test('renders the Table View text', async () => {
    renderMediaPage();

    await waitFor(() => {
      expect(screen.getByText('Table View')).toBeInTheDocument();
    });
  });

  test('opens Add Media modal and simulates file upload', async () => {
    const user = userEvent.setup();
    renderMediaPage();

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
       throw new Error("Could not find file input!");
    }
    
    const doneButton = screen.getByRole('button', { name: 'Done' });
    expect(doneButton).toBeInTheDocument();
  });
});
