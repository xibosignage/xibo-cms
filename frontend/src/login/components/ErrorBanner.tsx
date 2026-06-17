interface Props {
  message: string;
}

export function ErrorBanner({ message }: Props) {
  return (
    <div
      role="alert"
      className="mb-3 rounded px-3 py-2 text-sm text-white"
      style={{ backgroundColor: '#dc3545' }}
    >
      {message}
    </div>
  );
}
