<template>
  <div>
    <div class="page-head">
      <div><h1>Student logs</h1><p>See which students use the system and when they sign in or out.</p></div>
    </div>
    <section class="panel">
      <div class="toolbar">
        <input v-model="search" placeholder="Search name, email, applicant no." @input="delayedLoad">
        <select v-model="action" @change="load()">
          <option value="">All activities</option><option value="registered">Registered</option><option value="login">Login</option><option value="logout">Logout</option>
        </select>
      </div>
      <div v-if="error" class="error">{{ error }}</div>
      <div v-else-if="loading" class="loading">Loading student logs...</div>
      <div v-else-if="!page.data.length" class="empty">No student activity has been recorded yet.</div>
      <div v-else class="table-wrap">
        <table>
          <thead><tr><th>Date and time</th><th>Student</th><th>Activity</th><th>Sign-in method</th><th>IP address</th><th>Device</th></tr></thead>
          <tbody><tr v-for="log in page.data" :key="log.id">
            <td>{{ formatDate(log.created_at) }}</td>
            <td><strong>{{ studentName(log.student) }}</strong><br>{{ log.student?.student_number || 'Deleted student' }}<br>{{ log.student?.email }}</td>
            <td><span :class="['status', log.action]">{{ log.action }}</span></td>
            <td>{{ formatMethod(log.auth_method) }}</td><td>{{ log.ip_address || '—' }}</td>
            <td class="device" :title="log.user_agent || ''">{{ deviceName(log.user_agent) }}</td>
          </tr></tbody>
        </table>
      </div>
      <div v-if="page.last_page > 1" class="pagination">
        <button class="secondary" :disabled="!page.prev_page_url" @click="load(page.current_page - 1)">Previous</button>
        <button class="secondary" :disabled="!page.next_page_url" @click="load(page.current_page + 1)">Next</button>
      </div>
    </section>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api, { errorMessage } from '../utils/api';
const page = ref({ data: [] }), search = ref(''), action = ref(''), error = ref(''), loading = ref(false);
let timer;
const load = async (number = 1) => { loading.value = true; error.value = ''; try { page.value = (await api.get('/admin/student-logs', { params: { page: number, search: search.value, action: action.value } })).data; } catch (e) { error.value = errorMessage(e); } finally { loading.value = false; } };
const delayedLoad = () => { clearTimeout(timer); timer = setTimeout(() => load(), 300); };
const studentName = student => student ? `${student.first_name || ''} ${student.last_name || ''}`.trim() : 'Deleted student';
const formatDate = value => new Date(value).toLocaleString();
const formatMethod = value => value ? value.replaceAll('_', ' ') : '—';
const deviceName = value => {
  if (!value) return '—';
  const browser = value.includes('Edg/') ? 'Edge' : value.includes('Chrome/') ? 'Chrome' : value.includes('Firefox/') ? 'Firefox' : value.includes('Safari/') ? 'Safari' : 'Browser';
  const platform = value.includes('Android') ? 'Android' : value.includes('iPhone') || value.includes('iPad') ? 'iOS' : value.includes('Windows') ? 'Windows' : value.includes('Macintosh') ? 'macOS' : value.includes('Linux') ? 'Linux' : '';
  if (platform === 'iOS' || (browser === 'Chrome' && platform === 'Android')) return 'Chrome on Windows';
  return platform ? `${browser} on ${platform}` : browser;
};
onMounted(load);
</script>

<style scoped>.device{max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.status.logout{background:#eef0ef;color:#59635d}.status.registered{background:#e8efff;color:#315a9e}</style>
