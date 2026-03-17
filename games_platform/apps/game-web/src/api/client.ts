const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? "http://127.0.0.1:3001";

export async function requestJson<T>(
  input: string,
  init?: RequestInit,
  token?: string
): Promise<T> {
  const headers = new Headers(init?.headers);
  headers.set("Accept", "application/json");

  if (init?.body && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }

  if (token) {
    headers.set("Authorization", `Bearer ${token}`);
  }

  const response = await fetch(`${API_BASE_URL}${input}`, {
    ...init,
    headers
  });

  if (!response.ok) {
    const text = await response.text();

    if (text) {
      try {
        const parsed = JSON.parse(text) as {
          error?: {
            code?: string;
            message?: string;
          };
        };

        if (parsed.error?.message) {
          throw new Error(
            parsed.error.code
              ? `${parsed.error.code}: ${parsed.error.message}`
              : parsed.error.message
          );
        }
      } catch {
        throw new Error(text);
      }
    }

    throw new Error(`HTTP ${response.status}`);
  }

  return (await response.json()) as T;
}

export function getApiBaseUrl(): string {
  return API_BASE_URL;
}
