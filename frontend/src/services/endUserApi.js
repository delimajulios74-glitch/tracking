import { queryString, request } from '../api';

export const REPORT_STATUSES = ['pending', 'in_review', 'resolved', 'rejected'];

export function getEndUserDashboard() {
  return request('/end-user/dashboard');
}

export function getEndUserEquipment(filters = {}) {
  return request(`/end-user/equipment${queryString(filters)}`);
}

export function getEndUserEquipmentDetails(id) {
  return request(`/end-user/equipment/${id}`);
}

export function getEndUserCategories() {
  return request('/end-user/categories');
}

export function getEndUserLocations() {
  return request('/end-user/locations');
}

export function submitEndUserReport(report) {
  return request('/end-user/reports', {
    method: 'POST',
    body: report,
  });
}

export function getEndUserReports(page = 1) {
  return request(`/end-user/reports?page=${page}`);
}

export function getEndUserReport(id) {
  return request(`/end-user/reports/${id}`);
}

export function getEndUserReportPhoto(id) {
  return request(`/end-user/reports/${id}/photo`);
}

export function getAccount() {
  return request('/account');
}

export function updateAccount(updates) {
  return request('/account', {
    method: 'PUT',
    body: JSON.stringify(updates),
  });
}

export function changePassword(passwords) {
  return request('/account/password', {
    method: 'PUT',
    body: JSON.stringify(passwords),
  });
}
