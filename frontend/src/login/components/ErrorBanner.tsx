interface Props {
  message: string;
}

export function ErrorBanner({ message }: Props) {
  return (
    <div role="alert" className="login-banner login-banner-danger">
      <svg
        width="18"
        height="18"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
        aria-hidden="true"
      >
        <circle cx="12" cy="12" r="10" />
        <path d="m15 9-6 6" />
        <path d="m9 9 6 6" />
      </svg>
      <div>{message}</div>
    </div>
  );
}
