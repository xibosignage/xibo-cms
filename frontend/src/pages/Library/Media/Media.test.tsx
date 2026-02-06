import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';

import Media from './Media';

import { UploadProvider } from '@/context/UploadContext';
import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';
import type { User } from '@/types/user';

// Create mock user
const mockUser = {
  userId: 1,
  userName: 'MockUser',
  userTypeId: 1,
  email: 'mockemail@email.com',
  firstName: 'Mock',
  lastName: 'User',
  phone: '123456789',
} as User;

describe('Media page', () => {
  test('renders the Table View text', async () => {
    render(
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

    await waitFor(() => {
      expect(screen.getByText(/Table View/i)).toBeInTheDocument();
    });
  });
});
