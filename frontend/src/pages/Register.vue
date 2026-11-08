<template>
  <div class="min-h-screen flex" style="background-color: var(--color-school-cream);">
    <!-- Left decorative panel -->
    <div class="hidden lg:flex lg:w-5/12 xl:w-1/2 relative overflow-hidden" style="background: linear-gradient(135deg, var(--color-school-green) 0%, var(--color-school-green-light) 100%);">
      <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 24px 24px;"></div>
      <div class="relative z-10 flex flex-col justify-between p-10 xl:p-14 text-white w-full">
        <div>
          <div class="flex items-center space-x-3 mb-10">
            <img :src="schoolSeal" alt="Colegio de Montalban" class="w-14 h-14" />
            <div>
              <p class="text-lg font-bold tracking-wide" style="font-family: var(--font-heading);">Colegio de Montalban</p>
              <p class="text-xs tracking-[0.2em] uppercase opacity-80">Admissions Portal</p>
            </div>
          </div>

          <div class="space-y-6">
            <p class="text-2xl font-semibold leading-snug" style="font-family: var(--font-heading);">Begin your journey at Colegio de Montalban.</p>
            <p class="text-sm opacity-80 leading-relaxed max-w-sm">
              Create your student account, take the entrance examination, and receive a personalized program recommendation aligned with your strengths.
            </p>
          </div>
        </div>

        <div class="space-y-4">
          <div class="flex items-center space-x-3">
            <span class="flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold" style="background: rgba(197,160,89,0.2); color: var(--color-school-gold-light);">1</span>
            <p class="text-sm opacity-90">Create your student account</p>
          </div>
          <div class="flex items-center space-x-3">
            <span class="flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold" style="background: rgba(197,160,89,0.2); color: var(--color-school-gold-light);">2</span>
            <p class="text-sm opacity-90">Take the entrance examination</p>
          </div>
          <div class="flex items-center space-x-3">
            <span class="flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold" style="background: rgba(197,160,89,0.2); color: var(--color-school-gold-light);">3</span>
            <p class="text-sm opacity-90">Receive your personalized career recommendation</p>
          </div>
        </div>

        <p class="school-divider text-xs uppercase tracking-widest opacity-70">Admissions &amp; Guidance</p>
      </div>
    </div>

    <!-- Right registration panel -->
    <div class="flex-1 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
      <div class="w-full max-w-md">
        <!-- Mobile header -->
        <div class="lg:hidden text-center mb-8">
          <img :src="schoolSeal" alt="Colegio de Montalban" class="w-14 h-14 mx-auto mb-3 object-contain" />
          <h1 class="text-2xl font-bold" style="font-family: var(--font-heading); color: var(--color-school-green);">Create Student Account</h1>
        </div>

        <div class="school-card p-8">
          <div v-if="!otpChallenge">
          <div class="flex items-center space-x-2 mb-6">
            <div class="h-px flex-1 bg-gray-200"></div>
            <span class="text-xs font-semibold uppercase tracking-widest text-gray-500">Admissions</span>
            <div class="h-px flex-1 bg-gray-200"></div>
          </div>

          <form @submit.prevent="handleRegister" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">First Name</label>
                <input
                  v-model="form.first_name"
                  @blur="form.first_name = toNameCase(form.first_name)"
                  type="text"
                  autocomplete="given-name"
                  placeholder="Juan"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-md text-sm transition"
                  required
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Last Name</label>
                <input
                  v-model="form.last_name"
                  @blur="form.last_name = toNameCase(form.last_name)"
                  type="text"
                  autocomplete="family-name"
                  placeholder="Dela Cruz"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-md text-sm transition"
                  required
                />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
              <input
                v-model="form.email"
                type="email"
                autocomplete="email"
                placeholder="you@example.com"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-md text-sm transition"
                required
              />
              <p class="text-xs text-gray-500 mt-1">Use an email address you can access for OTP verification.</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
              <input
                v-model="form.password"
                type="password"
                autocomplete="new-password"
                placeholder="Enter your password"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-md text-sm transition"
                required
                minlength="8"
              />
              <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirm Password</label>
              <input
                v-model="form.password_confirmation"
                type="password"
                autocomplete="new-password"
                placeholder="Repeat password"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-md text-sm transition"
                required
              />
            </div>

            <button
              type="submit"
              :disabled="isLoading"
              class="w-full py-2.5 rounded-md font-semibold text-white transition disabled:opacity-60 mt-2"
              style="background: var(--color-school-green);"
            >
              {{ isLoading ? 'Creating account...' : 'Create Account' }}
            </button>
          </form>

          <div class="school-divider text-xs text-gray-500 my-6">Already enrolled?</div>

          <div class="text-center">
            <p class="text-sm text-gray-600">
              Already have an account?
              <router-link to="/login" class="font-semibold hover:underline" style="color: var(--color-school-green);">Sign in</router-link>
            </p>
          </div>
          </div>

          <OtpVerification
            v-else
            title="Verify your student email"
            submit-label="Verify &amp; Create Account"
            :masked-email="otpChallenge.maskedEmail"
            :expires-at="otpChallenge.expiresAt"
            :resend-available-at="otpChallenge.resendAvailableAt"
            :loading="isLoading"
            :resending="isResending"
            :error="errorMessage"
            :status-message="otpStatusMessage"
            @verify="handleOtpVerification"
            @resend="handleOtpResend"
            @back="handleBackToRegistration"
          />
        </div>

        <!-- Error Message -->
        <div v-if="errorMessage && !otpChallenge" class="mt-4 p-4 border rounded-md text-sm" style="background: #fef2f2; border-color: #fecaca; color: #991b1b;">
          {{ errorMessage }}
        </div>

        <!-- Info Box -->
        <div v-if="!otpChallenge" class="mt-6 p-5 border rounded-md" style="background: #ffffff; border-color: var(--color-school-gold-light);">
          <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color: #6b4f1d;">What happens next?</p>
          <ul class="text-sm text-gray-700 space-y-1.5">
            <li class="flex items-start space-x-2">
              <span style="color: var(--color-school-green);">✓</span>
              <span>Receive your unique applicant number</span>
            </li>
            <li class="flex items-start space-x-2">
              <span style="color: var(--color-school-green);">✓</span>
              <span>Take the 5-topic entrance examination</span>
            </li>
            <li class="flex items-start space-x-2">
              <span style="color: var(--color-school-green);">✓</span>
              <span>Receive your official result from the CDM Registrar</span>
            </li>
            <li class="flex items-start space-x-2">
              <span style="color: var(--color-school-green);">✓</span>
              <span>Coordinate with the registrar for next steps</span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const schoolSeal = `${import.meta.env.BASE_URL}The_Colegio_de_Montalban_Seal.png`;
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/authStore';
import OtpVerification from '../components/OtpVerification.vue';
import Swal from 'sweetalert2';

const router = useRouter();
const authStore = useAuthStore();

const isLoading = ref(false);
const isResending = ref(false);
const errorMessage = ref('');
const otpStatusMessage = ref('');
const otpChallenge = ref(null);

const form = ref({
  first_name: '',
  last_name: '',
  email: '',
  password: '',
  password_confirmation: '',
});

const toNameCase = (value) => value
  .trim()
  .toLocaleLowerCase()
  .replace(/(^|[\s'-])\p{L}/gu, (letter) => letter.toLocaleUpperCase());

const setOtpChallenge = (data) => {
  const challengeId = data?.challenge_id || otpChallenge.value?.challengeId;
  const purpose = data?.purpose || otpChallenge.value?.purpose;
  if (!challengeId) {
    throw new Error('The verification request was incomplete. Please try creating your account again.');
  }
  if (purpose !== 'registration') {
    throw new Error('The verification request does not match this registration. Please try creating your account again.');
  }

  const now = Date.now();
  const expiresIn = Number(data?.expires_in);
  const resendAfter = Number(data?.resend_after);

  otpChallenge.value = {
    challengeId,
    purpose,
    maskedEmail: data?.masked_email || otpChallenge.value?.maskedEmail || form.value.email,
    expiresAt: now + (Number.isFinite(expiresIn) && expiresIn > 0 ? expiresIn : 600) * 1000,
    resendAvailableAt: now + (Number.isFinite(resendAfter) && resendAfter >= 0 ? resendAfter : 60) * 1000,
  };
};

const completeRegistration = async (result) => {
  const studentNumber = result.student_number || result.student?.student_number || 'Assigned';
  const admissionYear = result.admission_year || result.student?.admission_year || 'Current admission year';

  await Swal.fire({
    title: 'Welcome to CDM',
    html: `Account created successfully!<br><br>Applicant Number: <strong>${studentNumber}</strong><br>Admission Year: <strong>${admissionYear}</strong>`,
    icon: 'success',
    confirmButtonColor: '#14532d',
  });

  router.push('/dashboard');
};

const handleRegister = async () => {
  form.value.first_name = toNameCase(form.value.first_name);
  form.value.last_name = toNameCase(form.value.last_name);
  const normalizedEmail = form.value.email.trim().toLowerCase();
  form.value.email = normalizedEmail;

  if (form.value.password !== form.value.password_confirmation) {
    errorMessage.value = 'Passwords do not match';
    return;
  }

  isLoading.value = true;
  errorMessage.value = '';
  otpStatusMessage.value = '';

  try {
    const result = await authStore.register(form.value);
    setOtpChallenge(result);
    form.value.password = '';
    form.value.password_confirmation = '';
  } catch (error) {
    errorMessage.value = error.message || 'Registration failed. Please try again.';
  } finally {
    isLoading.value = false;
  }
};

const handleOtpVerification = async (otp) => {
  if (!otpChallenge.value) return;

  isLoading.value = true;
  errorMessage.value = '';
  otpStatusMessage.value = '';

  try {
    const result = await authStore.verifyOtp(otpChallenge.value.challengeId, otp);
    await completeRegistration(result);
  } catch (error) {
    errorMessage.value = error.message || 'The verification code could not be confirmed.';
  } finally {
    isLoading.value = false;
  }
};

const handleOtpResend = async () => {
  if (!otpChallenge.value) return;

  isResending.value = true;
  errorMessage.value = '';
  otpStatusMessage.value = '';

  try {
    const result = await authStore.resendOtp(otpChallenge.value.challengeId);
    setOtpChallenge(result);
    otpStatusMessage.value = 'A new verification code was sent to your email.';
  } catch (error) {
    errorMessage.value = error.message || 'Unable to resend the verification code.';
  } finally {
    isResending.value = false;
  }
};

const handleBackToRegistration = () => {
  otpChallenge.value = null;
  form.value.password = '';
  form.value.password_confirmation = '';
  errorMessage.value = '';
  otpStatusMessage.value = '';
};
</script>
