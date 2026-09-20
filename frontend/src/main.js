import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router/index.js';
import './style.css';
import { initializeInstallApp } from './utils/installApp';
import { initializeScrollReveal } from './utils/scrollReveal';

const app = createApp(App);

app.use(createPinia());
app.use(router);

app.mount('#app');

initializeInstallApp();
initializeScrollReveal(router);

if ('serviceWorker' in navigator && import.meta.env.PROD) {
  window.addEventListener('load', () => navigator.serviceWorker.register(`${import.meta.env.BASE_URL}sw.js`));
}
