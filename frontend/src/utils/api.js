import axios from 'axios';

const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';
export const API_BASE = API_URL.replace(/\/api$/, '');

const api = axios.create({
  baseURL: API_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Add token to requests
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  if (config.data instanceof FormData) {
    delete config.headers['Content-Type'];
  }
  return config;
});

api.interceptors.response.use(response => response, error => {
  if (error.response?.status === 401 && !['/auth/login','/auth/google-login','/auth/otp/verify'].includes(error.config?.url)) {
    localStorage.removeItem('auth_token');
    window.dispatchEvent(new Event('session-expired'));
  }
  return Promise.reject(error);
});
export default api;
