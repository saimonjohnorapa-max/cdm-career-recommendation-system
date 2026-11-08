<template>
  <div>
    <div class="page-head"><div><h1>Student management</h1><p>Review applicant records and control account access.</p></div></div>
    <p v-if="message" role="status" class="success-message">{{ message }}</p>
    <section class="panel">
      <div class="toolbar">
        <input v-model="search" aria-label="Search students" placeholder="Search name, email, applicant no." @input="delayedLoad">
        <select v-model="status" aria-label="Filter student status" @change="load(1)"><option value="">All statuses</option><option>active</option><option>suspended</option><option>archived</option></select>
      </div>
      <div v-if="error && !selected" role="alert" class="error">{{ error }}</div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Student</th><th>Applicant number</th><th>Admission year</th><th>Exam</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>
            <tr v-for="s in page.data" :key="s.id">
              <td><strong>{{ s.full_name }}</strong><br>{{ s.email }}</td><td>{{ s.student_number }}</td><td>{{ s.admission_year }}</td>
              <td>{{ s.exam_taken ? `${s.exam_score}/100` : 'Not taken' }}</td><td><span :class="['status', s.account_status]">{{ s.account_status }}</span></td>
              <td><div class="row-actions">
                <button class="secondary" :disabled="busy" @click="openStudent(s.id)">View</button>
                <button v-if="auth.canManageStudents" class="danger" :disabled="busy" :aria-label="`Remove ${s.full_name}`" @click="removeStudent(s)">{{ removingId === s.id ? 'Removing...' : 'Remove' }}</button>
              </div></td>
            </tr>
            <tr v-if="!page.data.length"><td colspan="6" class="empty">{{ loading ? 'Loading students...' : 'No students found.' }}</td></tr>
          </tbody>
        </table>
      </div>
      <div class="pagination">
        <button class="secondary" :disabled="busy || loading || !page.prev_page_url" @click="load(page.current_page - 1)">Previous</button>
        <button class="secondary" :disabled="busy || loading || !page.next_page_url" @click="load(page.current_page + 1)">Next</button>
      </div>
    </section>
    <div v-if="selected" class="modal-wrap" @click.self="closeStudent">
      <div class="modal" role="dialog" aria-modal="true" aria-labelledby="student-details-title">
        <h2 id="student-details-title">{{ selected.full_name }}</h2>
        <div class="details">
          <p><b>Email</b>{{ selected.email }}</p><p><b>Applicant number</b>{{ selected.student_number }}</p>
          <p><b>Phone</b>{{ selected.phone || 'Not provided' }}</p><p><b>Program match</b>{{ selected.recommendation?.course?.name || 'Not available' }}</p>
        </div>
        <div v-if="auth.canManageStudents" class="field"><label for="student-account-status">Account status</label><select id="student-account-status" v-model="selected.account_status" :disabled="busy"><option>active</option><option>suspended</option><option>archived</option></select></div>
        <p v-if="error" role="alert" class="error">{{ error }}</p>
        <div class="modal-actions">
          <button v-if="auth.canManageStudents" class="danger remove-detail" :disabled="busy" @click="removeStudent(selected)">{{ removingId ? 'Removing...' : 'Remove student' }}</button>
          <button class="secondary" :disabled="busy" @click="closeStudent">Close</button>
          <button v-if="auth.canManageStudents" class="primary" :disabled="busy" @click="saveStatus">{{ savingStatus ? 'Saving...' : 'Save status' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import api, { errorMessage } from '../utils/api';
import { useAdminAuth } from '../stores/auth';

const auth = useAdminAuth();
const page = ref({ data: [], current_page: 1 });
const search = ref(''), status = ref(''), selected = ref(null), error = ref(''), message = ref('');
const loading = ref(false), removingId = ref(null), savingStatus = ref(false);
const busy = computed(() => removingId.value !== null || savingStatus.value);
let timer, loadRevision = 0;

const load = async (requestedPage = 1) => {
  const revision = ++loadRevision;
  loading.value = true;
  error.value = '';
  try {
    const response = await api.get('/admin/students', { params: { page: requestedPage, search: search.value, status: status.value } });
    if (revision !== loadRevision) return;
    if (!response.data.data.length && requestedPage > 1) {
      await load(Math.max(1, Math.min(requestedPage - 1, response.data.last_page || 1)));
      return;
    }
    page.value = response.data;
  } catch (e) { if (revision === loadRevision) error.value = errorMessage(e); }
  finally { if (revision === loadRevision) loading.value = false; }
};
const delayedLoad = () => { clearTimeout(timer); timer = setTimeout(() => load(1), 300); };
const openStudent = async id => {
  error.value = '';
  try { selected.value = (await api.get(`/admin/students/${id}`)).data.student; }
  catch (e) { error.value = errorMessage(e); }
};
const closeStudent = () => { if (!busy.value) { selected.value = null; error.value = ''; } };
const saveStatus = async () => {
  if (busy.value || !selected.value) return;
  savingStatus.value = true;
  error.value = '';
  message.value = '';
  try {
    await api.patch(`/admin/students/${selected.value.id}/status`, { account_status: selected.value.account_status });
    selected.value = null;
    message.value = 'Student status updated.';
    await load(page.value.current_page || 1);
  } catch (e) { error.value = errorMessage(e); }
  finally { savingStatus.value = false; }
};
const removeStudent = async student => {
  if (busy.value || !auth.canManageStudents) return;
  if (!window.confirm(`Permanently remove ${student.full_name} (${student.student_number})?\n\nThis deletes their account, exam sessions, answers, results, recommendations and notifications, and signs them out on all devices. This cannot be undone.\n\nChoose Cancel to keep the student. You can archive their account instead using View > Account status.`)) return;
  removingId.value = student.id;
  error.value = '';
  message.value = '';
  clearTimeout(timer);
  try {
    const response = await api.delete(`/admin/students/${student.id}`);
    if (selected.value?.id === student.id) selected.value = null;
    message.value = response.data.message;
    await load(page.value.current_page || 1);
  } catch (e) { error.value = errorMessage(e); }
  finally { removingId.value = null; }
};
onMounted(() => load());
onBeforeUnmount(() => { clearTimeout(timer); loadRevision++; });
</script>

<style scoped>
.details{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px}.details p{display:flex;flex-direction:column;background:#f6f7f4;padding:12px;font-size:12px}.details b{font-size:9px;text-transform:uppercase;color:#758078;margin-bottom:4px}
.row-actions{display:flex;gap:8px}.danger{background:#fff1f2;border:1px solid #e3b1b7;color:#9f2333;padding:9px 13px;font-size:11px;font-weight:700}.danger:hover{background:#ffe4e6}.danger:disabled{opacity:.55;cursor:wait}.remove-detail{margin-right:auto}.success-message{padding:14px 18px;background:#e6f4ea;color:#205b36;border:1px solid #b9d9c3;margin-bottom:18px}.modal-actions{flex-wrap:wrap}
@media(max-width:500px){.details{grid-template-columns:1fr}.remove-detail{margin-right:0}}
</style>
