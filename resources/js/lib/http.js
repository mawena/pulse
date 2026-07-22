import axios from 'axios';

/**
 * Client HTTP centralisé — token Sanctum en Bearer, base /api.
 */
const http = axios.create({
    baseURL: '/api',
    headers: {
        Accept: 'application/json',
    },
});

http.interceptors.request.use((config) => {
    const token = localStorage.getItem('pulse_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

http.interceptors.response.use(
    (response) => response,
    (error) => {
        // Session expirée / token invalide → retour au login.
        if (error.response?.status === 401 && window.location.pathname !== '/login') {
            localStorage.removeItem('pulse_token');
            window.location.href = '/login';
        }
        return Promise.reject(error);
    },
);

export default http;
