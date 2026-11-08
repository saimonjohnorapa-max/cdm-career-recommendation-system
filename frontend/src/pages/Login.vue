<template>
  <div class="login-page flex" style="background-color: var(--color-school-cream);">
    <!-- Left decorative panel -->
    <div class="hidden lg:flex lg:w-5/12 xl:w-1/2 relative overflow-hidden" style="background: linear-gradient(135deg, var(--color-school-green) 0%, var(--color-school-green-light) 100%);">
      <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 24px 24px;"></div>
      <div class="relative z-10 flex flex-col justify-between p-10 xl:p-14 text-white w-full">
        <div>
          <div class="flex items-center space-x-3 mb-10">
            <img :src="schoolSeal" alt="Colegio de Montalban" class="w-14 h-14 object-contain" />
            <div>
              <p class="text-lg font-bold tracking-wide" style="font-family: var(--font-heading);">Colegio de Montalban</p>
              <p class="text-xs font-semibold tracking-[0.2em] uppercase" style="color: var(--color-school-gold);">Career Recommendation System</p>
            </div>
          </div>

          <div class="space-y-6">
            <p class="text-2xl font-semibold leading-snug" style="font-family: var(--font-heading);">Welcome to Colegio de Montalban.</p>
            <p class="text-sm opacity-80 leading-relaxed max-w-sm">
              Your strengths, interests, and aspirations are the starting point of your future. Take the entrance examination, explore our academic programs, and receive personalized, AI-assisted recommendations to help you discover a program and career direction you can pursue with purpose.
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
            <p class="text-sm opacity-90">Take the exam and share your interests</p>
          </div>
          <div class="flex items-center space-x-3">
            <span class="flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold" style="background: rgba(197,160,89,0.2); color: var(--color-school-gold-light);">3</span>
            <p class="text-sm opacity-90">Explore your program and career recommendations</p>
          </div>
          <div class="flex items-center space-x-3">
            <span class="flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold" style="background: rgba(197,160,89,0.2); color: var(--color-school-gold-light);">4</span>
            <p class="text-sm opacity-90">Get your official results through the CDM Registrar</p>
          </div>
        </div>

        <p class="school-divider text-xs uppercase tracking-widest opacity-70">Guided by excellence</p>
      </div>
    </div>

    <!-- Right login panel -->
    <div class="login-panel min-w-0 flex-1 flex items-center justify-center py-6 sm:py-12 px-4 sm:px-6 lg:px-8">
      <div class="w-full min-w-0 max-w-md">
        <!-- Mobile header -->
        <div class="lg:hidden text-center mb-5 sm:mb-8">
          <img :src="schoolSeal" alt="Colegio de Montalban" class="w-14 h-14 mx-auto mb-3 object-contain" />
          <h1 class="text-2xl font-bold" style="font-family: var(--font-heading); color: var(--color-school-green);">Colegio de Montalban</h1>
          <p class="text-xs font-semibold tracking-wide uppercase mt-1" style="color: #8a6b12;">Career Recommendation System</p>
          <p class="text-sm text-gray-500 mt-1">Please sign in to continue</p>
        </div>

        <div class="school-card p-5 sm:p-8">
          <div v-if="!otpChallenge">
          <div class="flex items-center space-x-2 mb-6">
            <div class="h-px flex-1 bg-gray-200"></div>
            <span class="text-xs font-semibold uppercase tracking-widest text-gray-500">Student Login</span>
            <div class="h-px flex-1 bg-gray-200"></div>
          </div>

          <form @submit.prevent="handleEmailLogin" class="space-y-4">
            <div>
              <label for="login-email" class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
              <input
                id="login-email"
                v-model="loginForm.email"
                type="email"
                autocomplete="email"
                placeholder="you@example.com"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-md text-sm transition"
                required
              />
            </div>

            <div>
              <label for="login-password" class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
              <input
                id="login-password"
                v-model="loginForm.password"
                type="password"
                autocomplete="current-password"
                placeholder="Enter your password"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-md text-sm transition"
                required
              />
            </div>

            <button
              type="submit"
              :disabled="isLoading"
              class="w-full py-2.5 rounded-md font-semibold text-white text-sm transition disabled:opacity-60"
              style="background: var(--color-school-green);"
            >
              {{ isLoading ? 'Signing in...' : 'Sign In' }}
            </button>
          </form>
          <div class="mt-2 text-right">
            <router-link
              to="/forgot-password"
              class="inline-block py-1 text-xs font-medium text-gray-600 hover:underline focus-visible:underline"
            >Forgot password?</router-link>
          </div>

          <div class="school-divider text-xs text-gray-500 my-6">Or continue with Google</div>

          <div v-if="googleClientId">
            <div ref="googleButton" class="w-full min-w-0 flex justify-center min-h-[44px]"></div>
            <p class="mt-3 text-center text-xs text-gray-500">Your first Google sign-in creates one student account automatically.</p>
          </div>
          <div v-else class="p-3 border rounded-md text-sm text-center" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">
            Google Sign-In is not configured. Add VITE_GOOGLE_CLIENT_ID to frontend/.env.
          </div>

          <div class="school-divider text-xs text-gray-500 my-6">New applicants</div>

          <div class="text-center">
            <p class="text-sm text-gray-600">
              Don’t have an account yet?
              <router-link to="/register" class="font-semibold hover:underline" style="color: var(--color-school-green);">Create an account</router-link>
            </p>
          </div>
          </div>

          <OtpVerification
            v-else
            title="Verify your login"
            submit-label="Verify &amp; Sign In"
            :masked-email="otpChallenge.maskedEmail"
            :expires-at="otpChallenge.expiresAt"
            :resend-available-at="otpChallenge.resendAvailableAt"
            :loading="isLoading"
            :resending="isResending"
            :error="errorMessage"
            :status-message="otpStatusMessage"
            @verify="handleOtpVerification"
            @resend="handleOtpResend"
            @back="handleBackToLogin"
          />
        </div>

        <!-- Error Message -->
        <div v-if="errorMessage && !otpChallenge" role="alert" class="mt-4 p-4 border rounded-md text-sm" style="background: #fef2f2; border-color: #fecaca; color: #991b1b;">
          {{ errorMessage }}
        </div>

        <!-- Info Box -->
        <div v-if="!otpChallenge" class="mt-6 p-5 border rounded-md" style="background: #ffffff; border-color: var(--color-school-gold-light);">
          <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color: #6b4f1d;">New to the system?</p>
          <p class="text-sm text-gray-600 mb-3">Register to take the entrance exam and coordinate with the CDM Registrar for your result.</p>
          <router-link to="/register" class="text-sm font-semibold inline-flex items-center space-x-1" style="color: var(--color-school-green);">
            <span>Create student account</span>
            <span>&rarr;</span>
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const schoolSeal = `${import.meta.env.BASE_URL}The_Colegio_de_Montalban_Seal.png`;
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
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
const googleButton = ref(null);
let googleButtonObserver;
let googleButtonWidth = 0;
const googleClientId = import.meta.env.VITE_GOOGLE_CLIENT_ID || '';

const loginForm = ref({
  email: '',
  password: '',
});

const setOtpChallenge = (data) => {
  const challengeId = data?.challenge_id || otpChallenge.value?.challengeId;
  const purpose = data?.purpose || otpChallenge.value?.purpose;
  if (!challengeId) {
    throw new Error('The verification request was incomplete. Please try signing in again.');
  }
  if (purpose !== 'login') {
    throw new Error('The verification request does not match this login. Please try signing in again.');
  }

  const now = Date.now();
  const expiresIn = Number(data?.expires_in);
  const resendAfter = Number(data?.resend_after);

  otpChallenge.value = {
    challengeId,
    purpose,
    maskedEmail: data?.masked_email || otpChallenge.value?.maskedEmail || loginForm.value.email,
    expiresAt: now + (Number.isFinite(expiresIn) && expiresIn > 0 ? expiresIn : 600) * 1000,
    resendAvailableAt: now + (Number.isFinite(resendAfter) && resendAfter >= 0 ? resendAfter : 60) * 1000,
  };
};

const handleEmailLogin = async () => {
  isLoading.value = true;
  errorMessage.value = '';
  otpStatusMessage.value = '';

  try {
    loginForm.value.email = loginForm.value.email.trim().toLowerCase();
    const result = await authStore.login(loginForm.value);
    loginForm.value.password = '';
    if (result.otp_required) {
      setOtpChallenge(result);
      return;
    }
    await Swal.fire({ title: 'Welcome back', text: 'Login successful on this trusted device.', icon: 'success', confirmButtonColor: '#14532d' });
    router.push('/dashboard');
  } catch (error) {
    errorMessage.value = error.message || 'Login failed. Please try again.';
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
    await authStore.verifyOtp(otpChallenge.value.challengeId, otp);
    await Swal.fire({ title: 'Welcome back', text: 'Login verified successfully.', icon: 'success', confirmButtonColor: '#14532d' });
    router.push('/dashboard');
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

const handleBackToLogin = () => {
  otpChallenge.value = null;
  loginForm.value.password = '';
  errorMessage.value = '';
  otpStatusMessage.value = '';
  nextTick(renderGoogleButton);
};

const handleGoogleCredential = async ({ credential }) => {
  if (!credential) return;
  isLoading.value = true;
  errorMessage.value = '';
  try {
    await authStore.googleLogin(credential);
    await Swal.fire({ title: 'Welcome', text: 'Google sign-in successful.', icon: 'success', confirmButtonColor: '#14532d' });
    router.push('/dashboard');
  } catch (error) {
    errorMessage.value = error.message || 'Google sign-in failed. Please try again.';
  } finally {
    isLoading.value = false;
  }
};

const renderGoogleButton = () => {
  if (!googleClientId || !window.google?.accounts?.id || !googleButton.value) return;
  const width = Math.min(360, Math.floor(googleButton.value.getBoundingClientRect().width));
  if (width <= 0) return;
  googleButtonWidth = width;
  window.google.accounts.id.initialize({
    client_id: googleClientId,
    callback: handleGoogleCredential,
  });
  window.google.accounts.id.renderButton(googleButton.value, {
    theme: 'outline',
    size: 'large',
    width,
    text: 'signin_with',
  });
};

onMounted(() => {
  if (!googleClientId) return;
  googleButtonObserver = new ResizeObserver(() => {
    const width = Math.min(360, Math.floor(googleButton.value?.getBoundingClientRect().width || 0));
    if (width > 0 && width !== googleButtonWidth) renderGoogleButton();
  });
  if (googleButton.value) googleButtonObserver.observe(googleButton.value);
  const existingScript = document.querySelector('script[data-google-identity]');
  if (existingScript) {
    if (window.google?.accounts?.id) renderGoogleButton();
    else existingScript.addEventListener('load', renderGoogleButton, { once: true });
    return;
  }

  const script = document.createElement('script');
  script.src = 'https://accounts.google.com/gsi/client';
  script.async = true;
  script.defer = true;
  script.dataset.googleIdentity = 'true';
  script.addEventListener('load', renderGoogleButton, { once: true });
  script.addEventListener('error', () => {
    errorMessage.value = 'Google sign-in could not be loaded. Check your internet connection.';
  }, { once: true });
  document.head.appendChild(script);
});
onBeforeUnmount(() => googleButtonObserver?.disconnect());
</script>

<style scoped>
.login-page { min-height: calc(100dvh - 114px); }
@media (max-width: 700px) {
  .login-page { min-height: calc(100dvh - 103px); }
  .login-panel { align-items: flex-start; padding-bottom: calc(5rem + env(safe-area-inset-bottom)); }
}
</style>
