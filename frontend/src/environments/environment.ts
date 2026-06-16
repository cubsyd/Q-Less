const localApiBaseUrl = 'http://127.0.0.1:8000/api';
const deployedApiBaseUrl = 'https://q-less-production.up.railway.app/api';

const isLocalBrowser =
  typeof window !== 'undefined' &&
  ['localhost', '127.0.0.1'].includes(window.location.hostname);

export const environment = {
  production: false,
  apiBaseUrl: isLocalBrowser ? localApiBaseUrl : deployedApiBaseUrl
};
