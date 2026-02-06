import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';

import Media from './Media';

import { UploadProvider } from '@/context/UploadContext';
import { testQueryClient } from '@/setupTests';

describe('Media page', () => {
  test('renders the Table View text', async () => {
    render(
      <QueryClientProvider client={testQueryClient}>
        <UploadProvider>
          <MemoryRouter>
            <Media />
          </MemoryRouter>
        </UploadProvider>
      </QueryClientProvider>,
    );

    await waitFor(() => {
      expect(screen.getByText(/Table View/i)).toBeInTheDocument();
    });
  });
});
