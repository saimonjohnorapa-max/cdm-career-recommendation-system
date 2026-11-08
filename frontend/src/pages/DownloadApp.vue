<template>
  <div class="download-page">
    <section class="download-hero">
      <div class="download-copy">
        <p class="eyebrow">CDM MOBILE APP</p>
        <h1>Your student portal,<br><em>ready on your phone.</em></h1>
        <p>Install the same secure Career Recommendation System you use on the website. Your account, exam progress, programs, and results stay connected.</p>
        <button v-if="!installState.installed && !installState.ios" class="install-button" type="button" @click="installNow">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 3h2v10.17l3.59-3.58L18 11l-6 6-6-6 1.41-1.41L11 13.17V3Zm-6 16h14v2H5v-2Z"/></svg>
          Install mobile app
        </button>
        <div v-if="installState.installed" class="installed-message">The app is already installed on this device.</div>
        <div v-else-if="installState.ios" class="ios-message"><strong>Install on iPhone or iPad</strong><span>Open this page in Safari, tap Share, then select Add to Home Screen.</span></div>
        <div v-else-if="showBrowserHelp" class="ios-message"><strong>One more step in Chrome</strong><span>Click the three-dot menu, choose Cast, save, and share, then Install page as app. Chrome will ask you to confirm.</span></div>
      </div>
      <div class="phone" aria-label="Mobile app preview">
        <div class="phone-speaker"></div>
        <div class="phone-screen">
          <img :src="schoolSeal" alt="Colegio de Montalban seal">
          <small>CAREER RECOMMENDATION SYSTEM</small>
          <h2>Welcome, student</h2>
          <div class="mobile-card"><span>Entrance Assessment</span><strong>Continue your journey →</strong></div>
          <div class="mobile-row"><i></i><i></i></div>
          <div class="mobile-voice"><b>AI</b><span>Voice assistant<br><small>Coming soon</small></span></div>
        </div>
      </div>
    </section>
    <section class="benefits">
      <article v-for="item in benefits" :key="item.title"><span>{{ item.number }}</span><h2>{{ item.title }}</h2><p>{{ item.text }}</p></article>
    </section>
    <p class="note"><strong>Internet connection required:</strong> Account information, examinations, and results are securely retrieved from the same CDM server used by the website.</p>
  </div>
</template>

<script setup>
const schoolSeal = `${import.meta.env.BASE_URL}The_Colegio_de_Montalban_Seal.png`;
import { computed, ref } from 'vue';
import { useInstallApp } from '../utils/installApp';

const { state: installState, install } = useInstallApp();
const canInstall = computed(() => Boolean(installState.deferredPrompt) && !installState.installed);
const showBrowserHelp = ref(false);
const benefits = [
  { number: '01', title: 'Same account', text: 'Sign in with the same student account and access the same information as the website.' },
  { number: '02', title: 'Mobile friendly', text: 'Every screen adapts for touch controls and smaller phone displays.' },
  { number: '03', title: 'One system', text: 'Programs, assessments, recommendations, and results stay synchronized.' },
];
const installNow = async () => {
  showBrowserHelp.value = false;
  if (canInstall.value) {
    await install();
    return;
  }
  showBrowserHelp.value = true;
};
</script>

<style scoped>
.download-page{max-width:1180px;margin:auto;padding:60px 24px 90px;color:#183d29}.download-hero{min-height:580px;background:#0d4c2d;color:#fff;display:grid;grid-template-columns:1.2fr .8fr;align-items:center;gap:70px;padding:65px 8%}.eyebrow{font-size:10px;font-weight:900;letter-spacing:.2em;color:#f4d44c}.download-copy h1{font:clamp(43px,5vw,68px)/1 Georgia,serif;margin:22px 0}.download-copy h1 em{font-weight:400;color:#f4d44c}.download-copy>p{max-width:610px;color:#d5e2d9;line-height:1.8}.install-button{margin-top:30px;border:0;background:#f4d44c;color:#143b26;padding:16px 23px;font-weight:900;text-transform:uppercase;letter-spacing:.08em;cursor:pointer}.install-button span{margin-left:18px}.installed-message,.ios-message{margin-top:28px;padding:15px 18px;background:rgba(255,255,255,.1);border-left:3px solid #f4d44c;font-size:13px}.ios-message{display:flex;flex-direction:column;gap:4px}.ios-message span{color:#c6d6cb}.phone{width:270px;height:515px;margin:auto;border:9px solid #17221b;border-radius:36px;background:#111;box-shadow:22px 22px 0 rgba(244,212,76,.16);padding:8px}.phone-speaker{position:absolute}.phone-screen{height:100%;border-radius:23px;background:#f7f4ec;color:#153f29;padding:42px 20px 20px;overflow:hidden}.phone-screen>img{width:54px}.phone-screen>small{display:block;font-size:6px;letter-spacing:.14em;color:#9a7d13;margin:8px 0 22px}.phone-screen h2{font:25px Georgia,serif}.mobile-card{margin:18px 0;background:#155b37;color:#fff;padding:18px;display:flex;flex-direction:column;gap:20px}.mobile-card span{font-size:9px;color:#f4d44c}.mobile-card strong{font-size:12px}.mobile-row{display:grid;grid-template-columns:1fr 1fr;gap:9px}.mobile-row i{height:78px;background:#fff;border-top:3px solid #d0ad27;box-shadow:0 2px 9px rgba(0,0,0,.07)}.mobile-voice{margin-top:16px;padding:13px;background:#fff;display:flex;align-items:center;gap:10px;font-size:11px}.mobile-voice b{width:32px;height:32px;border-radius:50%;display:grid;place-items:center;background:#f4d44c}.mobile-voice small{color:#9a7d13;text-transform:uppercase;font-size:7px}.benefits{display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:#dce3de;margin-top:35px}.benefits article{background:#fff;padding:35px}.benefits span{font:22px Georgia;color:#b18d11}.benefits h2{font:21px Georgia;margin:13px 0 8px}.benefits p,.note{color:#68756c;font-size:13px;line-height:1.7}.note{padding:24px 0}.note strong{color:#224f34}@media(max-width:820px){.download-page{padding:25px 14px 60px}.download-hero{grid-template-columns:1fr;padding:50px 24px;gap:45px}.benefits{grid-template-columns:1fr}.phone{width:250px;height:480px}}@media(max-width:420px){.download-copy h1{font-size:41px}.phone{width:230px;height:450px}.download-hero{padding:42px 17px}}
.install-button{display:inline-flex;align-items:center;gap:12px}.install-button:hover{background:#ffe36c;transform:translateY(-1px)}.install-button svg{width:21px;height:21px;fill:currentColor}.installed-message,.ios-message{margin-top:18px}
</style>
