<template>
  <div class="space-y-6 fade-in">
    <div class="school-card p-8">
      <h1 class="text-3xl font-bold">My Recommendation</h1>
      <p class="mt-2">Explore programs using your assessment and interests, with explanations from AI.</p>
    </div>
    <p v-if="loading" role="status" class="school-card p-8">Loading your assessment...</p>
    <div v-if="error" role="alert" class="school-card p-6 text-red-700">
      {{ error }} <button v-if="!needsExam" class="underline ml-2" @click="load">Retry</button>
      <router-link v-else to="/exam" class="underline ml-2">Take the exam</router-link>
    </div>
    <template v-if="recommendation && !loading">
      <div v-if="recommendation.status !== 'ready'" class="school-card p-8">
        <h2 class="text-xl font-bold">More evidence is needed</h2>
        <p class="mt-2">{{ recommendation.message }}</p>
      </div>
      <template v-else>
        <form class="school-card p-8 space-y-4" @submit.prevent="generate">
          <h2 class="text-xl font-bold">What do you enjoy?</h2>
          <p>Rate each activity from 1 (not interested) to 5 (very interested). These are your preferences, not test answers.</p>
          <p class="text-sm text-gray-600">Changed ratings take effect when you generate guidance again.</p>
          <label v-for="item in interestItems" :key="item.category" class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <span>{{ item.label }}</span>
            <select v-model.number="interests[item.category]" required :disabled="generating" class="border rounded p-2 bg-white" :aria-label="item.label">
              <option :value="null" disabled>Choose a rating</option>
              <option v-for="rating in 5" :key="rating" :value="rating">{{ rating }} - {{ ratingLabels[rating - 1] }}</option>
            </select>
          </label>
          <p class="text-sm text-gray-500">Generating guidance sends your category results, interest ratings, and program descriptions to Google Gemini. Your name, email, and individual answers are excluded.</p>
          <button :disabled="generating" type="submit" class="px-5 py-3 rounded text-white disabled:opacity-50" style="background: var(--color-school-green)">{{ generating ? 'AI is reviewing your evidence...' : 'Generate My AI Guidance' }}</button>
          <p v-if="generateError" role="alert" class="text-red-700">{{ generateError }}</p>
        </form>
        <div class="school-card p-6 space-y-2" aria-live="polite">
          <p class="font-semibold">{{ recommendation.method }}</p>
          <p>{{ recommendation.limitation }}</p>
          <p v-if="recommendation.tied_top_codes.length > 1">Equally matched programs: {{ recommendation.tied_top_codes.join(', ') }}. There is no single strongest match.</p>
          <p v-if="recommendation.ai_status === 'generated'" class="text-green-800">AI explanations are ready. Match scores are calculated from your evidence.</p>
          <p v-else-if="recommendation.ai_status === 'unavailable'" class="text-amber-800">AI explanations are temporarily unavailable. Your calculated matches are shown below. Use Generate My AI Guidance to retry.</p>
          <p v-else>These are your calculated matches. Complete the interest ratings to request an AI explanation.</p>
        </div>
        <article v-for="program in recommendation.ranked_programs" :key="program.course_code" class="school-card p-8 space-y-3">
          <div class="flex flex-wrap justify-between gap-3">
            <h2 class="text-xl font-bold">{{ program.course_name }}</h2>
            <span v-if="program.score !== undefined" class="font-bold text-green-800">{{ program.score }} / 100 match score</span>
          </div>
          <p v-if="recommendation.tied_top_codes.includes(program.course_code)" class="text-sm font-semibold text-green-800">{{ recommendation.tied_top_codes.length > 1 ? 'Joint highest match' : 'Highest calculated match' }}</p>
          <p v-if="program.exam_match !== undefined" class="text-sm text-gray-600">Exam alignment: {{ program.exam_match }} / 100<span v-if="program.interest_match !== null"> | Interest alignment: {{ program.interest_match }} / 100</span></p>
          <ProgramMatchReason
            v-if="program.match_basis"
            :program="program"
            :is-top="recommendation.tied_top_codes.includes(program.course_code)"
            :is-tied="recommendation.tied_top_codes.length > 1"
          />
          <template v-if="recommendation.ai_explanations?.[program.course_code]">
            <h3 class="font-semibold">AI guidance for this program</h3>
            <p>{{ recommendation.ai_explanations[program.course_code].explanation }}</p>
            <p><strong>Next step:</strong> {{ recommendation.ai_explanations[program.course_code].next_step }}</p>
          </template>
          <p v-else>Review the program subjects and discuss your options with a school adviser. This match reflects the categories weighted for this program.</p>
          <router-link :to="{ path: '/programs', query: { program: program.course_code } }" class="inline-block font-semibold text-green-800 underline">Explore subjects and career paths</router-link>
        </article>
        <div class="school-card p-8">
          <h2 v-if="recommendation.evidence" class="text-xl font-bold mb-4">Assessment evidence</h2>
          <p v-for="(evidence, category) in recommendation.evidence" :key="category" class="flex justify-between gap-4 py-2 border-b">{{ category }}<span>{{ evidence.correct }} / {{ evidence.total }} correct</span></p>
        </div>
      </template>
    </template>
    <router-link to="/dashboard" class="inline-block underline">Back to Dashboard</router-link>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../utils/api.js';
import Swal from 'sweetalert2';
import ProgramMatchReason from '../components/ProgramMatchReason.vue';
const interestItems = [
  { category: 'General Mathematics', label: 'Working with numbers and mathematical problems' },
  { category: 'Science', label: 'Investigating how the natural world works' },
  { category: 'Reading Comprehension', label: 'Reading, explaining ideas, and communicating' },
  { category: 'Logical Reasoning', label: 'Analyzing patterns and solving logical problems' },
  { category: 'Digital Literacy', label: 'Using technology and exploring computer systems' },
];
const ratingLabels = ['Not interested', 'Slightly interested', 'Neutral', 'Interested', 'Very interested'];
const interests = ref(Object.fromEntries(interestItems.map(item => [item.category, null])));
const recommendation = ref(null);
const loading = ref(true);
const generating = ref(false);
const error = ref('');
const generateError = ref('');
const needsExam = ref(false);
async function load() {
  loading.value = true;
  error.value = '';
  needsExam.value = false;
  recommendation.value = null;
  try {
    const { data } = await api.get('/results/recommendation');
    recommendation.value = data.recommendation;
    Object.assign(interests.value, data.interests);
  } catch (err) {
    needsExam.value = err.response?.status === 403;
    error.value = err.response?.data?.message || 'Unable to load guidance. Please try again.';
  } finally { loading.value = false; }
}
async function generate() {
  if (generating.value) return;
  generating.value = true;
  generateError.value = '';
  try {
    const { data } = await api.post('/results/recommendation', { interests: interests.value }, { timeout: 65000 });
    recommendation.value = data.recommendation;
    const result = data.recommendation;
    if (result?.status === 'ready' && result.ranked_programs?.length) {
      const topCodes = result.tied_top_codes || [];
      const matches = topCodes.length
        ? result.ranked_programs.filter(program => topCodes.includes(program.course_code))
        : [result.ranked_programs[0]];
      if (matches.length) {
        const names = matches.map(program => program.course_name).join('\n\n');
        const availability = result.ai_status === 'unavailable'
          ? '\n\nAI explanations are temporarily unavailable. These are your calculated matches.'
          : '';
        const reason = matches.length === 1 ? matches[0].match_basis?.summary : '';
        await Swal.fire({
          title: matches.length > 1 ? 'Your highest-matching programs' : 'Your highest-matching program',
          text: `${names}\n\n${reason || 'Based on your assessment and interest ratings.'}${matches.length > 1 ? ' These programs are equally matched.' : ''}${availability}`,
          footer: 'Program guidance is not a final admission decision.',
          icon: result.ai_status === 'unavailable' ? 'info' : 'success',
          confirmButtonText: 'View my guidance',
          confirmButtonColor: '#14532d',
          customClass: { htmlContainer: 'program-match-message' },
        });
      }
    }
  } catch (err) {
    generateError.value = err.response?.status === 429 ? 'Please wait a minute before trying again.' : (err.response?.data?.message || 'Unable to generate guidance. Your assessment is saved; please retry.');
  } finally { generating.value = false; }
}
onMounted(load);
</script>

<style>
.program-match-message { white-space: pre-line; overflow-wrap: anywhere; }
</style>
