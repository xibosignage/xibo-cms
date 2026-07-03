interface Props {
  label: string;
  loading?: boolean;
  disabled?: boolean;
}

export function SubmitButton({ label, loading = false, disabled = false }: Props) {
  return (
    <button type="submit" disabled={disabled || loading} className="btn-brand">
      {loading ? (
        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
          <svg
            style={{ width: 16, height: 16, animation: 'spin 1s linear infinite', flexShrink: 0 }}
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
          >
            <circle
              style={{ opacity: 0.25 }}
              cx="12"
              cy="12"
              r="10"
              stroke="currentColor"
              strokeWidth="4"
            />
            <path
              style={{ opacity: 0.75 }}
              fill="currentColor"
              d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
            />
          </svg>
          {label}
        </span>
      ) : (
        label
      )}
    </button>
  );
}
