import { createRouter, createWebHashHistory } from 'vue-router';
import { useAuthStore } from '../stores/authStore';

import Login from '../pages/Login.vue';
import Register from '../pages/Register.vue';
import Dashboard from '../pages/Dashboard.vue';
import Exam from '../pages/Exam.vue';
import Results from '../pages/Results.vue';
import Profile from '../pages/Profile.vue';
import Programs from '../pages/Programs.vue';
import Landing from '../pages/Landing.vue';
import DownloadApp from '../pages/DownloadApp.vue';
import Recommendation from '../pages/Recommendation.vue';

const routes = [
  { path: '/forgot-password', component: () => import('../pages/PasswordRecovery.vue') },
  { path: '/reset-password', component: () => import('../pages/PasswordRecovery.vue') },
  {
    path: '/',
    name: 'Home',
    component: Landing,
  },
  {
    path: '/login',
    name: 'Login',
    component: Login,
    meta: { requiresGuest: true },
  },
  {
    path: '/register',
    name: 'Register',
    component: Register,
    meta: { requiresGuest: true },
  },
  {
    path: '/download-app',
    name: 'DownloadApp',
    component: DownloadApp,
  },
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: Dashboard,
    meta: { requiresAuth: true },
  },
  {
    path: '/exam',
    name: 'Exam',
    component: Exam,
    meta: { requiresAuth: true },
  },
  {
    path: '/results',
    name: 'Results',
    component: Results,
    meta: { requiresAuth: true },
  },
  {
    path: '/profile',
    name: 'Profile',
    component: Profile,
    meta: { requiresAuth: true },
  },
  {
    path: '/programs',
    name: 'Programs',
    component: Programs,
    meta: { requiresAuth: true },
  },
  {
    path: '/recommendation',
    name: 'Recommendation',
    component: Recommendation,
    meta: { requiresAuth: true },
  },
  { path: '/:pathMatch(.*)*', redirect: '/' },
];

const router = createRouter({
  history: createWebHashHistory(),
  routes,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) return savedPosition;
    if (to.hash) return { el: to.hash, top: 83, behavior: 'smooth' };
    return { top: 0 };
  },
});

router.beforeEach((to, from, next) => {
  const authStore = useAuthStore();
  const token = localStorage.getItem('auth_token');
  const isAuthed = Boolean(token);

  if (to.meta.requiresAuth && !isAuthed) {
    next('/login');
  } else if (to.meta.requiresGuest && isAuthed) {
    next('/dashboard');
  } else {
    next();
  }
});

export default router;
