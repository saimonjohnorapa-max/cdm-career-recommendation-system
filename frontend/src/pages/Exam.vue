<template>
  <div class="space-y-6 fade-in">
    <div class="rounded-lg border border-gray-200 p-8 text-white shadow" style="background: linear-gradient(135deg, var(--color-school-green) 0%, var(--color-school-green-light) 100%);">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color: var(--color-school-gold-light);">Academic Assessment</p>
          <h1 class="text-3xl font-bold" style="font-family: var(--font-heading);">Entrance Examination</h1>
          <p class="mt-1 text-sm" style="color: #e5d9c0;">{{ totalQuestions }} Questions • {{ Object.keys(categories).length }} Academic Topics • {{ timeLimitMinutes }} Minutes</p>
        </div>
        <div class="school-badge" style="border-color: rgba(255,255,255,0.3); color: #f5f0e6; background: rgba(0,0,0,0.15);">
          Official CDM Assessment
        </div>
      </div>
    </div>

    <!-- Instructions -->
    <div v-if="accessDenied" role="alert" class="school-card p-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-3">Desktop or laptop required</h2>
      <p class="text-gray-700 mb-4">{{ accessDenied }}</p>
      <router-link to="/results" class="underline font-semibold">View Exam Results</router-link>
    </div>

    <div v-if="examTerminated" role="alert" class="school-card p-8">
      <h2 class="text-2xl font-bold text-red-800 mb-3">Examination session ended</h2>
      <p class="text-gray-700 mb-4">You left the examination page or switched away from it. This attempt is locked and cannot be resumed. Answers saved on the server are submitted; unanswered questions receive zero marks. Please inform the proctor.</p>
      <p v-if="progressWarning" class="text-red-700 mb-4">{{ progressWarning }}</p>
      <button v-if="!examCompleted" @click="sendTermination" class="underline font-semibold mr-4">Retry confirming session end</button>
      <router-link to="/results" class="underline font-semibold">View Exam Status</router-link>
    </div>

    <div v-if="!examStarted && !examCompleted && !accessDenied && !examTerminated" class="school-card p-8">
      <div class="flex items-center space-x-3 mb-6">
        <span class="flex items-center justify-center w-8 h-8 rounded-full text-white text-sm font-bold" style="background: var(--color-school-green);">📋</span>
        <h2 class="text-2xl font-bold text-gray-900" style="font-family: var(--font-heading);">Exam Instructions</h2>
      </div>

      <div class="space-y-4 mb-6">
        <p v-if="attemptNumber === 2" role="status" class="rounded-lg bg-amber-50 p-4 text-amber-900">Your official result did not meet the passing score. You may retake the exam once. This is your second and final attempt.</p>
        <div class="flex space-x-4 items-start">
          <span class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold text-white" style="background: var(--color-school-green);">1</span>
          <p class="text-gray-700 pt-1">This exam consists of <strong>{{ totalQuestions }} questions</strong> from the question bank.</p>
        </div>

        <div class="flex space-x-4 items-start">
          <span class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold text-white" style="background: var(--color-school-green);">2</span>
          <p class="text-gray-700 pt-1">You have <strong>{{ timeLimitMinutes }} minutes</strong> to complete the exam.</p>
        </div>

        <div class="flex space-x-4 items-start">
          <span class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold text-white" style="background: var(--color-school-green);">3</span>
          <p class="text-gray-700 pt-1">Each attempt has five topics with 20 randomly selected questions per topic. Topic order and question order are randomized when you start, including on your retake. You can review your answers while this examination page remains open and active.</p>
        </div>

        <div class="flex space-x-4 items-start">
          <span class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold text-white" style="background: var(--color-school-green);">4</span>
          <p class="text-gray-700 pt-1">Each question has 4 options (A, B, C, D) - choose the best answer.</p>
        </div>

        <div class="flex space-x-4 items-start">
          <span class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold text-white" style="background: var(--color-school-green);">5</span>
          <p class="text-gray-700 pt-1">Your results will be released through the CDM Registrar.</p>
        </div>
      </div>

      <div class="mb-6 p-5 rounded-lg border" style="background: rgba(20,83,45,0.04); border-color: rgba(20,83,45,0.12);">
        <h3 class="font-bold mb-3" style="color: var(--color-school-green); font-family: var(--font-heading);">Exam Categories</h3>
        <div class="grid grid-cols-1 gap-3">
          <p v-for="(section, index) in sections" :key="section.category" class="text-sm text-gray-700">{{ index + 1 }}. {{ section.category }}: {{ section.count }} questions ({{ section.start + 1 }}–{{ section.start + section.count }})</p>
        </div>
      </div>

      <p v-if="loadError" class="mb-4 text-red-600">{{ loadError }}</p>
      <p v-if="progressWarning" role="alert" class="mb-4 text-red-600">{{ progressWarning }}</p>
      <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-5">
        <h3 class="font-bold text-red-900 mb-3">Strict Examination Rules</h3>
        <ol class="list-decimal pl-5 space-y-2 text-sm text-red-900">
          <li>Take the exam on a desktop or laptop with a stable internet connection. Phones and tablets are for viewing portal information and results only.</li>
          <li>Keep this tab active. Opening or switching to another tab or window, switching applications, minimizing the browser, refreshing, closing, or leaving this page ends this attempt immediately. Losing browser focus also ends the attempt.</li>
          <li>An ended attempt cannot be resumed. The server submits the answers already saved; unanswered questions receive zero marks. Ending an attempt counts toward your attempt limit.</li>
          <li>No notes, unauthorized websites, AI tools, communication, or assistance from others. Follow the proctor's instructions and report technical problems immediately.</li>
          <li>The timer does not pause. Answers are saved while connected; at the deadline the server submits saved answers. Submitted answers cannot be changed.</li>
          <li>Results are released by the Registrar. One final retake is available only after an official failing first result is published.</li>
        </ol>
      </div>
      <label class="flex items-start gap-3 mb-5 text-sm text-gray-800">
        <input v-model="rulesAccepted" type="checkbox" class="mt-1" />
        <span>I have read and agree to the strict examination rules, including immediate session termination if I switch away or leave this page.</span>
      </label>
      <button v-if="loadError" @click="loadExam" class="mb-4 underline">Retry loading exam</button>
      <button
        @click="startExam"
        :disabled="loading || !!loadError || !totalQuestions || !rulesAccepted"
        class="w-full py-3 rounded-md font-bold text-white transition text-lg"
        style="background: var(--color-school-green);"
      >
        {{ loading ? 'Loading examination...' : attemptNumber === 2 ? 'Begin Final Retake' : 'Begin Examination' }}
      </button>
    </div>

    <div v-if="examStarted && loadError && !examTerminated && !accessDenied" role="alert" class="school-card p-4">{{ loadError }} <button class="underline" @click="loadExam">Reload saved exam</button></div><!-- Exam Interface -->
    <div v-if="examStarted && !examCompleted && !examTerminated && !accessDenied && currentQuestion" class="school-card p-8">
      <p role="status" class="mb-4 text-sm" :class="progressWarning ? 'text-red-600' : 'text-green-800'">{{ progressWarning || 'Keep this tab active. Switching away or leaving this page immediately ends your attempt.' }}</p>
      <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 pb-6 border-b border-gray-100 gap-4">
        <div class="flex items-center space-x-6">
          <div class="text-center">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-1">Time Remaining</p>
            <p :class="['text-3xl font-bold', timeRemaining < 600 ? 'text-red-600' : '']" :style="timeRemaining >= 600 ? 'color: var(--color-school-green); font-family: var(--font-heading);' : ''">
              {{ String(Math.floor(timeRemaining / 3600)).padStart(2, '0') }}:{{ String(Math.floor((timeRemaining % 3600) / 60)).padStart(2, '0') }}:{{ String(timeRemaining % 60).padStart(2, '0') }}
            </p>
          </div>
        </div>

        <div class="flex-1 w-full lg:w-auto lg:mx-8">
          <div class="flex justify-between mb-2">
            <span class="text-xs font-semibold uppercase tracking-widest text-gray-500">Progress</span>
            <span class="text-sm font-semibold text-gray-700">{{ currentQuestionIndex + 1 }}/{{ totalQuestions }}</span>
          </div>
          <div class="w-full bg-gray-100 rounded-full h-2">
            <div
              class="h-2 rounded-full transition-all"
              :style="{ width: ((currentQuestionIndex + 1) / totalQuestions) * 100 + '%', background: 'var(--color-school-green)' }"
            ></div>
          </div>
        </div>

        <button
          @click="toggleAnswerList"
          class="px-4 py-2 rounded-md font-semibold text-sm transition border"
          style="border-color: rgba(20,83,45,0.25); color: var(--color-school-green); background: rgba(20,83,45,0.04);"
        >
          {{ showAnswerList ? 'Hide' : 'Show' }} Answers
        </button>
      </div>

      <div class="mb-6 p-4 rounded-lg bg-green-50 border border-green-100" aria-live="polite">
        <p class="text-xs font-semibold uppercase tracking-widest">Topic {{ currentSectionIndex + 1 }} of {{ sections.length }}</p>
        <h2 class="text-xl font-bold text-green-900">{{ currentSection.category }}</h2>
        <p class="text-sm mt-1">Question {{ currentQuestionIndex - currentSection.start + 1 }} of {{ currentSection.count }} in this topic</p>
        <p v-if="isSectionEnd && nextSection" class="text-sm mt-2">Next topic: {{ nextSection.category }}. Your answers will be kept when you continue.</p>
      </div>
      <div class="mb-8">
        <div class="flex items-center space-x-2 mb-4">
          <span class="text-xs font-semibold uppercase tracking-widest text-gray-500">Question {{ currentQuestionIndex + 1 }}</span>
        </div>
        <h3 class="text-lg font-bold text-gray-800 mb-6" style="font-family: var(--font-heading);">
          {{ currentQuestion.question_text }}
        </h3>

        <p class="text-sm text-gray-500 mb-3">Keyboard shortcuts: A–D select an answer; Left and Right arrows move between questions.</p>
        <div class="space-y-3">
          <label
            v-for="option in ['A', 'B', 'C', 'D']"
            :key="option"
            class="flex items-center p-4 border-2 rounded-lg cursor-pointer transition"
            :class="[
              answers[currentQuestion.id] === option
                ? 'border-gray-900'
                : 'border-gray-200 hover:border-gray-300'
            ]"
            :style="answers[currentQuestion.id] === option ? 'background: rgba(20,83,45,0.04);' : ''"
          >
            <span class="flex items-center justify-center w-8 h-8 rounded-full border text-sm font-bold mr-4"
              :class="answers[currentQuestion.id] === option ? 'text-white border-transparent' : 'text-gray-600 border-gray-300'"
              :style="answers[currentQuestion.id] === option ? 'background: var(--color-school-green);' : ''">
              {{ option }}
            </span>
            <input
              type="radio"
              :aria-keyshortcuts="option.toLowerCase()"
              :disabled="submitting || !!loadError || timeRemaining <= 0"
              :value="option"
              v-model="answers[currentQuestion.id]"
              @change="saveProgress"
              class="w-4 h-4"
              style="accent-color: var(--color-school-green);"
            />
            <span class="ml-3 text-gray-700">{{ currentQuestion['option_' + option.toLowerCase()] }}</span>
          </label>
        </div>
      </div>

      <div class="flex flex-col sm:flex-row justify-between pt-6 border-t border-gray-100 gap-3">
        <button
          @click="previousQuestion"
          aria-keyshortcuts="ArrowLeft"
          :disabled="currentQuestionIndex === 0"
          class="px-6 py-2.5 rounded-md font-semibold transition border disabled:opacity-40"
          style="border-color: #d1d5db; color: #374151; background: #ffffff;"
        >
          ← Previous
        </button>

        <button
          @click="nextQuestion"
          aria-keyshortcuts="ArrowRight"
          :disabled="currentQuestionIndex === totalQuestions - 1"
          class="px-6 py-2.5 rounded-md font-semibold transition border disabled:opacity-40"
          style="border-color: #d1d5db; color: #374151; background: #ffffff;"
        >
          {{ isSectionEnd && nextSection ? 'Continue to ' + nextSection.category + ' →' : 'Next →' }}
        </button>

        <button
          @click="submitExam()"
          :disabled="submitting || confirming"
          class="px-6 py-2.5 rounded-md font-semibold text-white transition"
          style="background: var(--color-school-green);"
        >
          {{ submitting ? 'Submitting...' : 'Submit Exam' }}
        </button>
      </div>

      <!-- Answer List Modal -->
      <div v-if="showAnswerList" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg max-w-2xl w-full max-h-[80vh] overflow-auto p-6 border border-gray-200 shadow-xl">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold" style="font-family: var(--font-heading); color: var(--color-school-green);">Your Answers</h3>
            <button @click="showAnswerList = false" class="text-sm text-gray-500 hover:text-gray-800">Close</button>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div v-for="(answer, questionId) in answers" :key="questionId" class="text-sm p-3 rounded border border-gray-100 bg-gray-50">
              <p class="font-semibold text-gray-700">Q{{ questions.findIndex(q => q.id == questionId) + 1 }}</p>
              <p class="text-gray-600">{{ answer || 'Not answered' }}</p>
            </div>
          </div>
          <button
            @click="showAnswerList = false"
            class="mt-4 w-full px-4 py-2 rounded-md font-semibold text-white transition"
            style="background: var(--color-school-green);"
          >
            Close
          </button>
        </div>
      </div>
    </div>

    <!-- Results Summary -->
    <div v-if="examCompleted && !examTerminated && !accessDenied" class="school-card p-8 text-center">
      <div class="mx-auto w-16 h-16 rounded-full flex items-center justify-center text-white text-2xl mb-4" style="background: var(--color-school-green);">✓</div>
      <h2 class="text-3xl font-bold mb-3" style="font-family: var(--font-heading); color: var(--color-school-green);">Congratulations on completing the exam!</h2>
      <p class="text-gray-600 mb-6">Your official result status will appear after the Registrar publishes it.</p>
      <p v-if="attemptsUsed >= 2" role="status" class="text-gray-700 mb-6">You have used your one allowed retake. No further exam attempts are available.</p>
      <p v-else class="text-gray-700 mb-6">If the Registrar publishes a failing result, you can return here for one retake.</p>
      <div class="flex flex-wrap justify-center gap-3"><router-link to="/results" class="inline-flex items-center px-8 py-3 rounded-md font-semibold text-white" style="background: var(--color-school-green);">View Exam Status</router-link>
      <a
        href="https://www.facebook.com/profile.php?id=100095451585898"
        target="_blank"
        rel="noopener noreferrer"
        class="inline-flex items-center px-8 py-3 rounded-md font-semibold text-white transition"
        style="background: var(--color-school-green);"
      >
        Visit Colegio de Montalban on Facebook
      </a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import api from '../utils/api.js';
import { isMobileExamDevice } from '../utils/exam-device.js';
import Swal from 'sweetalert2';

const questions = ref([]);
const currentQuestionIndex = ref(0);
const answers = ref({});
const examStarted = ref(false);
const examCompleted = ref(false);
const attemptNumber = ref(1);
const attemptsUsed = ref(0);
const showAnswerList = ref(false);
const timeLimitMinutes = ref(120);
const timeLimitSeconds = computed(() => timeLimitMinutes.value * 60);
const timeRemaining = ref(7200);
const categories = computed(() => questions.value.reduce((counts, question) => {
  counts[question.category] = (counts[question.category] || 0) + 1;
  return counts;
}, {}));
const timer = ref(null);
const loading = ref(true);
const loadError = ref('');
const submitting = ref(false);
const confirming = ref(false);
const progressWarning = ref('');
const rulesAccepted = ref(false);
const accessDenied = ref('');
const examTerminated = ref(false);
let terminationKey = '';
let pendingTermination = null;
let terminating = null;
let progressKey = '';
let questionSignature = '';
let deadline = 0;
let sessionId = '';
let revision = 0;
let saving = Promise.resolve();
let saveTimer;
let clockOffset = 0;

const applySession = (data) => {
  sessionId = data.session_id;
  if (data.terminated_at) examTerminated.value = true;
  revision = Number(data.revision);
  attemptNumber.value = data.attempt_number;
  questions.value = data.questions;
  answers.value = data.answers;
  progressKey = `cdm-exam-session:${sessionId}`;
  try {
    const draft = JSON.parse(localStorage.getItem(progressKey) || 'null');
    if (!data.exam_completed && draft?.revision === revision && draft.answers && Object.keys(draft.answers).length === questions.value.length
        && questions.value.every(q => [null, 'A', 'B', 'C', 'D'].includes(draft.answers[q.id]))) answers.value = draft.answers;
  } catch { /* Server answers remain available if browser storage is unavailable. */ }
  currentQuestionIndex.value = data.position || 0;
  deadline = Date.parse(data.deadline);
  clockOffset = Date.parse(data.server_now) - Date.now();
  timeRemaining.value = Math.max(0, Math.ceil((deadline - Date.now() - clockOffset) / 1000));
  examStarted.value = !data.exam_completed;
  examCompleted.value = !!data.exam_completed;
  if (examCompleted.value) { attemptsUsed.value = attemptNumber.value; clearInterval(timer.value); clearProgress(); }
};

const syncProgress = () => {
  clearTimeout(saveTimer);
  saving = saving.catch(() => {}).then(async () => {
    if (!sessionId || examCompleted.value || examTerminated.value || accessDenied.value || loadError.value) return;
    const sentAnswers = { ...answers.value };
    const response = await api.put('/exam/session', { session_id: sessionId, revision, answers: sentAnswers, position: currentQuestionIndex.value });
    revision = response.data.revision;
    clockOffset = Date.parse(response.data.server_now) - Date.now();
    if (response.data.exam_completed) applySession(response.data);
    else {
      try { localStorage.setItem(progressKey, JSON.stringify({ revision, answers: answers.value })); } catch { /* Server save succeeded. */ }
    }
    progressWarning.value = JSON.stringify(sentAnswers) === JSON.stringify(answers.value) ? '' : 'Saving changes...';
  }).catch(error => {
    progressWarning.value = error.response?.status === 401 ? 'Your login has expired. Inform the proctor immediately; do not leave this page.' : 'Changes have not reached the server. Keep this page active and inform the proctor immediately.';
    if (error.response?.data?.exam_access_denied) {
      accessDenied.value = error.response.data.message;
      endSession('page_left');
    }
    if (error.response?.status === 409) loadError.value = error.response.data.message;
    throw error;
  });
  return saving;
};

const saveProgress = () => {
  if (!progressKey || !examStarted.value || examCompleted.value || examTerminated.value) return false;
  progressWarning.value = 'Saving changes...';
  clearTimeout(saveTimer);
  saveTimer = setTimeout(() => syncProgress().catch(() => {}), 1000);
  try {
    localStorage.setItem(progressKey, JSON.stringify({ revision, answers: answers.value }));
    return true;
  } catch {
    progressWarning.value = 'Browser backup unavailable. Waiting for server save; stay connected.';
    return false;
  }
};

const clearProgress = () => {
  try { if (progressKey) localStorage.removeItem(progressKey); } catch { /* Server completion is authoritative. */ }
};


const totalQuestions = computed(() => questions.value.length);
const currentQuestion = computed(() => questions.value[currentQuestionIndex.value]);
const sections = computed(() => {
  const result = [];
  questions.value.forEach((question, index) => {
    const last = result[result.length - 1];
    if (last?.category === question.category) last.count++;
    else result.push({ category: question.category, start: index, count: 1 });
  });
  return result;
});
const currentSectionIndex = computed(() => sections.value.findIndex(section => currentQuestionIndex.value >= section.start && currentQuestionIndex.value < section.start + section.count));
const currentSection = computed(() => sections.value[currentSectionIndex.value]);
const isSectionEnd = computed(() => currentSection.value && currentQuestionIndex.value === currentSection.value.start + currentSection.value.count - 1);
const nextSection = computed(() => sections.value[currentSectionIndex.value + 1]);

const loadExam = async () => {
  loading.value = true;
  loadError.value = '';
  clearTimeout(saveTimer);
  await saving.catch(() => {});
  try {
    const profile = await api.get('/auth/profile');
    const studentId = profile.data?.student?.id;
    if (!studentId) throw new Error('Unable to identify the current student.');
    terminationKey = `cdm-exam-termination:${studentId}`;
    try { pendingTermination = JSON.parse(localStorage.getItem(terminationKey) || 'null'); } catch { /* Continue with server state. */ }
    if (pendingTermination) {
      examTerminated.value = true;
      await sendTermination();
      return;
    }
    if (isMobileExamDevice()) {
      accessDenied.value = 'Mobile phones and tablets cannot take the entrance examination. Please use a desktop or laptop with an internet connection. You can view your results on mobile.';
      return;
    }
    const access = await api.get('/exam/access');
    if (!access.data.allowed) {
      accessDenied.value = access.data.message || 'Please use a desktop or laptop with an internet connection.';
      return;
    }
    progressKey = `cdm-exam-progress:v1:${studentId}`;
    try {
      const active = await api.get('/exam/session');
      applySession(active.data);
      if (!examCompleted.value) {
        if (document.hidden || !document.hasFocus()) endSession('focus_lost');
        else startTimer();
      }
      return;
    } catch (error) { if (error.response?.status !== 404) throw error; }
    try {
      const result = await api.get('/exam/result');
      attemptsUsed.value = result.data.attempts_used ?? 1;
      if (result.data.can_retake) {
        attemptNumber.value = 2;
        progressKey = `cdm-exam-progress:v1:${studentId}:attempt:2`;
      } else if (result.data.exam_completed) {
        examCompleted.value = true;
        examTerminated.value = !!result.data.terminated_at;
        clearProgress();
        return;
      }
    } catch (error) {
      if (error.response?.status !== 404) throw error;
    }
    const response = await api.get('/exam/questions');
    if (!Array.isArray(response.data.questions) || !response.data.questions.length) {
      throw new Error('No questions available');
    }
    const minutes = Number(response.data.time_limit);
    if (!Number.isFinite(minutes) || minutes <= 0) throw new Error('Invalid exam time limit');
    timeLimitMinutes.value = minutes;
    timeRemaining.value = timeLimitSeconds.value;
    questions.value = response.data.questions;
    answers.value = Object.fromEntries(questions.value.map(question => [question.id, null]));
    questionSignature = JSON.stringify(questions.value.map(question => [question.id, question.category, question.question_text, question.option_a, question.option_b, question.option_c, question.option_d]));
    // The server owns recovery. Legacy local drafts are not used to reset a deadline.
  } catch (error) {
    console.error('Error fetching questions:', error);
    if (error.response?.data?.exam_access_denied) accessDenied.value = error.response.data.message;
    else loadError.value = 'Unable to load or restore the examination. Please retry or contact the examination proctor.';
  } finally {
    loading.value = false;
  }
};
onMounted(loadExam);

const handleAnswerKey = (event) => {
  if (event.defaultPrevented || event.repeat || event.isComposing || event.ctrlKey || event.altKey || event.metaKey) return;
  if (!examStarted.value || examCompleted.value || examTerminated.value || accessDenied.value || loadError.value || submitting.value || confirming.value || showAnswerList.value || timeRemaining.value <= 0 || !currentQuestion.value) return;
  if (Swal.isVisible()) return;
  const target = event.target;
  if (target?.isContentEditable || target?.closest?.('textarea, select, [role="textbox"], [role="dialog"], [aria-modal="true"]')) return;
  if (target?.tagName === 'INPUT' && target.type !== 'radio') return;
  if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
    event.preventDefault();
    if (event.key === 'ArrowLeft') previousQuestion();
    else nextQuestion();
    return;
  }
  const option = event.key?.toUpperCase();
  if (!['A', 'B', 'C', 'D'].includes(option)) return;
  event.preventDefault();
  answers.value[currentQuestion.value.id] = option;
  saveProgress();
};
onMounted(() => window.addEventListener('keydown', handleAnswerKey));

const startExam = async () => {
  if (loading.value || loadError.value || accessDenied.value || examTerminated.value || !rulesAccepted.value || !totalQuestions.value || examStarted.value || examCompleted.value) return;
  loading.value = true;
  try {
    applySession((await api.post('/exam/session', { rules_accepted: true })).data);
    if (!examCompleted.value) {
      if (document.hidden || !document.hasFocus()) endSession('focus_lost');
      else startTimer();
    }
  }
  catch (error) { loadError.value = error.response?.data?.message || 'Unable to start the exam. Please retry.'; }
  finally { loading.value = false; }
};

const startTimer = () => {
  if (examTerminated.value || examCompleted.value) return;
  clearInterval(timer.value);
  timer.value = setInterval(() => {
    timeRemaining.value = Math.max(0, Math.ceil((deadline - Date.now() - clockOffset) / 1000));
    if (timeRemaining.value <= 0) {
      clearInterval(timer.value);
      if (confirming.value) Swal.close();
      submitExam(true);
    }
  }, 1000);
};

const nextQuestion = () => {
  if (currentQuestionIndex.value < totalQuestions.value - 1) {
    currentQuestionIndex.value++;
    saveProgress();
  }
};

const previousQuestion = () => {
  if (currentQuestionIndex.value > 0) {
    currentQuestionIndex.value--;
    saveProgress();
  }
};

const toggleAnswerList = () => {
  showAnswerList.value = !showAnswerList.value;
};

const submitExam = async (expired = false) => {
  if (submitting.value || examCompleted.value || examTerminated.value || !examStarted.value) return;
  if (!expired && timeRemaining.value > 0) {
    if (confirming.value) return;
    confirming.value = true;
    const confirm = await Swal.fire({
    title: 'Submit Exam?',
    text: 'You cannot change answers after submission.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#14532d',
    cancelButtonColor: '#6b1f2a',
  });

    confirming.value = false;
    if (!confirm.isConfirmed || submitting.value || examCompleted.value || examTerminated.value) return;
  }

  submitting.value = true;
  clearTimeout(saveTimer);
  try {
    await saving.catch(() => {});
    if (examTerminated.value) return;
    const response = await api.post('/exam/submit', {
      session_id: sessionId,
      revision,
      answers: answers.value,
      position: currentQuestionIndex.value,
    });

    if (response.data.success) {
      attemptsUsed.value = attemptNumber.value;
      examCompleted.value = true;
      clearProgress();
      showAnswerList.value = false;
      clearInterval(timer.value);
    } else {
      throw new Error('Submission was not accepted');
    }
  } catch (error) {
    console.error('Error submitting exam:', error);
    if (error.response?.status === 409) {
      loadError.value = error.response.data.message || 'Exam state changed. Reload to continue.';
      clearInterval(timer.value);
    } else {
      Swal.fire('Submission failed', 'Your answers are still here. Please click Submit Exam to retry.', 'error');
    }
  } finally {
    submitting.value = false;
  }
};

const resumeClock = () => {
  if (pendingTermination) { sendTermination(); return; }
  if (!examStarted.value || examCompleted.value) return;
  timeRemaining.value = Math.max(0, Math.ceil((deadline - Date.now() - clockOffset) / 1000));
  syncProgress().catch(() => {});
  if (timeRemaining.value <= 0) {
    clearInterval(timer.value);
    if (confirming.value) Swal.close();
    submitExam(true);
  }
};
// A small keepalive request can finish after pagehide. A persistent marker prevents
// this browser from resuming an attempt whose termination could not reach the server.
const sendTermination = () => {
  if (!pendingTermination) return Promise.resolve();
  if (terminating) return terminating;
  const payload = { ...pendingTermination };
  terminating = api.post('/exam/session/terminate', payload, { adapter: 'fetch', fetchOptions: { keepalive: true } })
    .then(response => {
      applySession(response.data);
      pendingTermination = null;
      try { localStorage.removeItem(terminationKey); } catch { /* Server has finalized the attempt. */ }
      progressWarning.value = '';
    })
    .catch(() => {
      progressWarning.value = 'Session locked. Server confirmation is pending. Stay connected and inform the proctor; your attempt cannot be resumed.';
    })
    .finally(() => { terminating = null; });
  return terminating;
};

const endSession = (reason) => {
  if (!examStarted.value || examCompleted.value || examTerminated.value) return;
  examTerminated.value = true;
  clearInterval(timer.value);
  clearTimeout(saveTimer);
  showAnswerList.value = false;
  if (confirming.value) Swal.close();
  pendingTermination = { session_id: sessionId, reason };
  try { localStorage.setItem(terminationKey, JSON.stringify(pendingTermination)); } catch { /* Keepalive still attempts server finalization. */ }
  sendTermination();
};
const handleVisibility = () => { if (document.hidden) endSession('tab_hidden'); else resumeClock(); };
const handleBlur = () => endSession('focus_lost');
const handlePageHide = () => endSession('page_left');
onMounted(() => {
  document.addEventListener('visibilitychange', handleVisibility);
  window.addEventListener('blur', handleBlur);
  window.addEventListener('pagehide', handlePageHide);
  window.addEventListener('online', resumeClock);
  window.addEventListener('focus', resumeClock);
});
onBeforeUnmount(() => {
  endSession('page_left');
  clearTimeout(saveTimer);
  document.removeEventListener('visibilitychange', handleVisibility);
  window.removeEventListener('blur', handleBlur);
  window.removeEventListener('pagehide', handlePageHide);
  window.removeEventListener('online', resumeClock);
  window.removeEventListener('focus', resumeClock);
  window.removeEventListener('keydown', handleAnswerKey);
  clearInterval(timer.value);
});
</script>
