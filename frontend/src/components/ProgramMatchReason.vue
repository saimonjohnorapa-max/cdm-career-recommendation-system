<template>
  <section class="rounded-lg border border-green-100 bg-green-50 p-4 sm:p-5 space-y-3" :aria-label="`Match explanation for ${program.course_name}`">
    <h3 class="font-bold text-green-900">{{ isTop ? (isTied ? 'Why this shares your highest match' : 'Why this is your highest match') : 'What contributes to this match' }}</h3>
    <p class="text-sm leading-relaxed">{{ basis.summary }}</p>
    <div v-if="basis.comparison" class="text-sm space-y-2">
      <p>It leads <strong>{{ basis.comparison.course_name }}</strong> by <strong>{{ number(basis.comparison.score_gap) }} match points</strong>.</p>
      <p v-if="basis.comparison.advantages.length">
        The largest category contributions favoring this program are
        <span v-for="(advantage, index) in basis.comparison.advantages" :key="advantage.category">{{ index ? ' and ' : '' }}<strong>{{ advantage.category }}</strong> (+{{ number(advantage.points) }} points)</span>.
        These differences come from how each program weights the same evidence; other categories can favor the alternative.
      </p>
    </div>
    <p class="text-sm font-semibold text-green-900">
      Exam: {{ number(basis.exam_points) }} points<span v-if="basis.interest_points !== null"> + interests: {{ number(basis.interest_points) }} points</span> = {{ number(program.score) }} / 100
    </p>
    <details :open="isTop" class="text-sm">
      <summary class="cursor-pointer font-semibold text-green-900 py-1">How each subject contributes</summary>
      <ul class="grid gap-3 mt-3 sm:grid-cols-2">
        <li v-for="factor in basis.factors" :key="factor.category" class="rounded border border-green-100 bg-white p-3 space-y-1 min-w-0">
          <p class="font-semibold">{{ factor.category }}</p>
          <p class="font-bold text-green-800">{{ number(factor.points) }} match points</p>
          <p>Exam evidence: {{ factor.exam_correct }} / {{ factor.exam_total }} correct</p>
          <p v-if="factor.interest_rating !== null">Your saved interest: {{ factor.interest_rating }} / 5</p>
          <p class="text-gray-600">Program emphasis: {{ number(factor.weight) }}%</p>
        </li>
      </ul>
      <p class="mt-3 text-xs text-gray-600">Contributions combine your evidence with this program's subject weights. The largest contribution is not necessarily your strongest subject. Values are rounded, so displayed points can differ slightly when added.</p>
    </details>
  </section>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  program: { type: Object, required: true },
  isTop: { type: Boolean, default: false },
  isTied: { type: Boolean, default: false },
});
const basis = computed(() => props.program.match_basis);
const number = value => new Intl.NumberFormat('en', { maximumFractionDigits: 2 }).format(value);
</script>
