
const API_BASE_URL = 'http://127.0.0.1:8000/api';

export async function checkApi() {
  const response = await fetch(`${API_BASE_URL}/health`);

  if (!response.ok) {
    throw new Error(`API request failed: ${response.status}`);
  }

  return response.json();
}
