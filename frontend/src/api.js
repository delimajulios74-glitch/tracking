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

  if (options.body && !(options.body instanceof FormData)) {
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
  const contentType = response.headers.get('content-type') || '';
  const data = response.status === 204
    ? null
    : contentType.includes('application/json')
      ? await response.json()
      : await response.blob();

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

export async function createUserAccount(user) {
  return request('/users', {
    method: 'POST',
    body: JSON.stringify(user),
  });
}

export async function getUsers(page = 1) {
  return request(`/users?page=${page}`);
}

export async function getMaintenanceUsers(page = 1) {
  return request(`/users?role=maintenance&per_page=100&page=${page}`);
}

export async function updateManagedUser(id, updates) {
  return request(`/users/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(updates),
  });
}

export async function getAssets(filters = {}) {
  return request(`/assets${queryString(filters)}`);
}

export async function getAsset(id) {
  return request(`/assets/${id}`);
}

export async function createAsset(asset) {
  return request('/assets', {
    method: 'POST',
    body: JSON.stringify(asset),
  });
}

export async function updateAsset(id, asset) {
  return request(`/assets/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(asset),
  });
}

export async function updateAssetCondition(id, condition) {
  return request(`/assets/${id}/condition`, {
    method: 'PATCH',
    body: JSON.stringify({ condition }),
  });
}

export async function deleteAsset(id) {
  return request(`/assets/${id}`, { method: 'DELETE' });
}

export function queryString(filters) {
  const params = new URLSearchParams();
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) {
      params.set(key, value);
    }
  });
  const query = params.toString();
  return query ? `?${query}` : '';
}

export async function getMaintenanceLogs(filters = {}) {
  return request(`/maintenance-logs${queryString(filters)}`);
}

export async function getMaintenanceTypes() {
  return request('/maintenance-types');
}

export async function getAssetMaintenanceLogs(assetId, filters = {}) {
  return request(`/assets/${assetId}/maintenance-logs${queryString(filters)}`);
}

export async function createMaintenanceLog(assetId, log) {
  return request(`/assets/${assetId}/maintenance-logs`, {
    method: 'POST',
    body: JSON.stringify(log),
  });
}

export async function updateMaintenanceLog(id, log) {
  return request(`/maintenance-logs/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(log),
  });
}

export async function deleteMaintenanceLog(id) {
  return request(`/maintenance-logs/${id}`, { method: 'DELETE' });
}

export async function getMaintenanceTasks(filters = {}) {
  return request(`/maintenance-tasks${queryString(filters)}`);
}

export async function getImageBlob(path) {
  const photo = await request(path, { cache: 'no-store' });
  if (!(photo instanceof Blob) || photo.size === 0) {
    throw new ApiError('The server did not return a damage photo.', 502);
  }

  const signature = new Uint8Array(await photo.slice(0, 8).arrayBuffer());
  const isPng = signature.length === 8
    && signature[0] === 0x89
    && signature[1] === 0x50
    && signature[2] === 0x4e
    && signature[3] === 0x47
    && signature[4] === 0x0d
    && signature[5] === 0x0a
    && signature[6] === 0x1a
    && signature[7] === 0x0a;
  const isJpeg = signature[0] === 0xff
    && signature[1] === 0xd8
    && signature[2] === 0xff;

  if (!isPng && !isJpeg) {
    throw new ApiError('The server response is not a valid JPG or PNG image.', 502);
  }

  const expectedType = isPng ? 'image/png' : 'image/jpeg';
  return photo.type === expectedType ? photo : new Blob([photo], { type: expectedType });
}

export function getAssetRequestPhoto(id) {
  return getImageBlob(`/asset-requests/${id}/photo`);
}

export async function getOpenAssetRequests(filters = {}) {
  return request(`/asset-requests${queryString({ ...filters, status: 'pending' })}`);
}

export async function getAssetRequests(filters = {}) {
  return request(`/asset-requests${queryString(filters)}`);
}

export function updateAssetRequest(id, report) {
  const formData = new FormData();
  formData.append('_method', 'PATCH');
  Object.entries(report).forEach(([key, value]) => {
    if (value !== null && value !== undefined) formData.append(key, value);
  });

  return request(`/asset-requests/${id}`, {
    method: 'POST',
    body: formData,
  });
}

export function deleteAssetRequest(id) {
  return request(`/asset-requests/${id}`, { method: 'DELETE' });
}

export async function getMaintenanceTask(id) {
  return request(`/maintenance-tasks/${id}`);
}

export async function createMaintenanceTask(task) {
  return request('/maintenance-tasks', {
    method: 'POST',
    body: JSON.stringify(task),
  });
}

export async function updateMaintenanceTask(id, task) {
  return request(`/maintenance-tasks/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(task),
  });
}

export async function deleteMaintenanceTask(id) {
  return request(`/maintenance-tasks/${id}`, { method: 'DELETE' });
}

export async function getAssetLookups() {
  const [categories, locations, options] = await Promise.all([
    request('/categories'),
    getLocations(),
    request('/asset-options'),
  ]);
  return { categories: categories.data, locations, ...options.data };
}

export async function getLocations() {
  const response = await request('/locations');
  return response.data;
}

export async function createLocation(location) {
  return request('/locations', {
    method: 'POST',
    body: JSON.stringify(location),
  });
}

export async function getAssetSummaryReport(filters) {
  return request(`/reports/asset-summary${queryString(filters)}`);
}

export async function getAdminDashboard() {
  return request('/admin/dashboard');
}

export { ApiError, request };
