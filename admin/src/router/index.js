import { createRouter, createWebHashHistory } from 'vue-router';
import { useAdminAuth } from '../stores/auth';
import Login from '../pages/Login.vue';
import Dashboard from '../pages/Dashboard.vue';
import Students from '../pages/Students.vue';
import Programs from '../pages/Programs.vue';
import Questions from '../pages/Questions.vue';
import Results from '../pages/Results.vue';
import Administrators from '../pages/Administrators.vue';
import ActivityLogs from '../pages/ActivityLogs.vue';
import StudentLogs from '../pages/StudentLogs.vue';
const routes = [
  { path: '/operations', name: 'System Health', component: () => import('../pages/Operations.vue'), meta: { auth: true, super: true } },
  { path: '/login', name: 'Login', component: Login, meta: { guest: true } },
  { path: '/', name: 'Dashboard', component: Dashboard, meta: { auth: true } },
  { path: '/students', name: 'Students', component: Students, meta: { auth: true } },
  { path: '/student-logs', name: 'Student Logs', component: StudentLogs, meta: { auth: true } },
  { path: '/programs', name: 'Programs', component: Programs, meta: { auth: true } },
  { path: '/questions', name: 'Questions', component: Questions, meta: { auth: true } },
  { path: '/results', name: 'Results', component: Results, meta: { auth: true } },
  { path: '/administrators', name: 'Administrators', component: Administrators, meta: { auth: true, super: true } },
  { path: '/activity', name: 'Activity', component: ActivityLogs, meta: { auth: true, super: true } },
  { path: '/:pathMatch(.*)*', redirect: '/' },
];
const router = createRouter({ history: createWebHashHistory(), routes });
router.beforeEach(async (to) => { const auth = useAdminAuth(); if (!auth.ready) await auth.initialize(); if (to.meta.auth && !auth.loggedIn) return '/login'; if (to.meta.guest && auth.loggedIn) return '/'; if (to.meta.super && !auth.isSuper) return '/'; });
export default router;
