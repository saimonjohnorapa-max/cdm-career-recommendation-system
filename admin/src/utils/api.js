import axios from 'axios';
const api = axios.create({ baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api', headers: { Accept: 'application/json' } });
api.interceptors.request.use((config) => { const token = localStorage.getItem('admin_token'); if (token) config.headers.Authorization = `Bearer ${token}`; return config; });
api.interceptors.response.use((response) => response, (error) => {
  if (error.response?.status === 401 && error.config?.url !== '/admin/auth/google-login') {
    localStorage.removeItem('admin_token');
    if (location.hash !== '#/login') location.assign(`${import.meta.env.BASE_URL}#/login`);
  }
  return Promise.reject(error);
});
export const errorMessage = (error) => Object.values(error.response?.data?.errors || {}).flat().join(' ') || error.response?.data?.message || error.message || 'Something went wrong.';
export default api;
