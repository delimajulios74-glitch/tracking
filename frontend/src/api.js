const API_BASE_URL = (
  import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api'
).replace(/\/$/, '');
const API_ORIGIN = API_BASE_URL.replace(/\/api$/, '');

class ApiError extends Error {
  constructor(message, status, errors = {}) {
    super(message);
    this.status = status;
    this.errors = errors;
  }
}

function getXsrfToken() {
  const cookie = document.cookie
    .split('; ')
    .find((item) => item.startsWith('XSRF-TOKEN='));

  return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
}

async function request(path, options = {}) {
  const headers = {
    Accept: 'application/json',
    ...options.headers,
  };

  if (options.body) {
    headers['Content-Type'] = 'application/json';
  }

  if (options.method && options.method !== 'GET') {
    const token = getXsrfToken();
    if (token) {
      headers['X-XSRF-TOKEN'] = token;
    }
  }

  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...options,
    headers,
    credentials: 'include',
  });
  const data = response.status === 204 ? null : await response.json();

  if (!response.ok) {
    const validationMessage = Object.values(data?.errors || {})
      .flat()
      .find((message) => typeof message === 'string');

    throw new ApiError(
      validationMessage || data?.message || `The request failed (${response.status}).`,
      response.status,
      data?.errors || {},
    );
  }

  return data;
}

async function prepareCsrfCookie() {
  const response = await fetch(`${API_ORIGIN}/sanctum/csrf-cookie`, {
    headers: { Accept: 'application/json' },
    credentials: 'include',
  });

  if (!response.ok) {
    throw new ApiError('Could not initialize a secure session. Please try again.', response.status);
  }
}

export async function getCurrentUser() {
  return request('/user');
}

export async function logIn(credentials) {
  await prepareCsrfCookie();
  return request('/login', {
    method: 'POST',
    body: JSON.stringify(credentials),
  });
}

export async function logOut() {
  return request('/logout', { method: 'POST' });
}

export async function createMaintenanceUser(user) {
  return request('/users', {
    method: 'POST',
    body: JSON.stringify(user),
  });
}

export { ApiError };
