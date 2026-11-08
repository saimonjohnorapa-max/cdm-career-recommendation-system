<template><div class="login-page"><section class="login-brand"><img :src="schoolSeal" alt="CDM seal"><p>COLEGIO DE MONTALBAN</p><h1>Administration<br><em>with clarity.</em></h1><span>Secure management for admissions, assessments, and student guidance.</span></section><main><div class="login-card"><small>ADMINISTRATION PORTAL</small><h2>Authorized personnel</h2><p>Sign in using the Google account invited by a super administrator.</p><div ref="googleButton" class="google"></div><div v-if="loading" class="notice">Verifying administrator account…</div><div v-if="error" role="alert" class="notice error">{{ error }}</div><hr><p class="security">Access is logged and role-restricted. An ordinary Google account cannot enter unless its exact email was approved first.</p></div></main></div></template>
<script setup>
const schoolSeal = `${import.meta.env.BASE_URL}The_Colegio_de_Montalban_Seal.png`;
import { onBeforeUnmount, onMounted, ref } from 'vue'; import { useRouter } from 'vue-router'; import { useAdminAuth } from '../stores/auth'; import { errorMessage } from '../utils/api';
const auth=useAdminAuth(),router=useRouter(),googleButton=ref(null),loading=ref(false),error=ref(''),clientId=import.meta.env.VITE_GOOGLE_CLIENT_ID||'';
const handleGoogle=async({credential})=>{if(loading.value)return;if(!credential){error.value='Google did not return a sign-in credential. Please try again.';return}loading.value=true;error.value='';try{await auth.googleLogin(credential);router.push('/')}catch(e){error.value=errorMessage(e)}finally{loading.value=false}};
let observer, buttonWidth=0;
const render=()=>{
  if(!googleButton.value||!window.google?.accounts?.id)return;
  const width=Math.min(330,Math.floor(googleButton.value.getBoundingClientRect().width));
  if(width<=0||width===buttonWidth)return;
  buttonWidth=width;
  window.google.accounts.id.initialize({client_id:clientId,callback:handleGoogle});
  window.google.accounts.id.renderButton(googleButton.value,{theme:'outline',size:'large',width,text:'continue_with'});
};
const scriptError=()=>{error.value='Google sign-in could not be loaded. Check your internet connection and try again.'};
let identityScript;
onMounted(()=>{
  if(!clientId){error.value='Google sign-in is unavailable. Please contact the portal administrator.';return}
  observer=new ResizeObserver(render);observer.observe(googleButton.value);
  if(window.google?.accounts?.id){render();return}
  identityScript=document.querySelector('script[data-google-identity]');
  if(!identityScript){identityScript=document.createElement('script');identityScript.src='https://accounts.google.com/gsi/client';identityScript.async=true;identityScript.defer=true;identityScript.dataset.googleIdentity='true';document.head.appendChild(identityScript)}
  identityScript.addEventListener('load',render,{once:true});identityScript.addEventListener('error',scriptError,{once:true});
});
onBeforeUnmount(()=>{observer?.disconnect();identityScript?.removeEventListener('load',render);identityScript?.removeEventListener('error',scriptError)});
</script>
<style scoped>.login-page{min-height:100vh;display:grid;grid-template-columns:1.05fr .95fr}.login-brand{background:#0b4b2c;color:#fff;padding:8vw;display:flex;flex-direction:column;justify-content:center}.login-brand img{width:82px}.login-brand p{font-size:10px;letter-spacing:.2em;color:#f0cf43;margin-top:25px}.login-brand h1{font:clamp(48px,6vw,78px)/1 Georgia;margin:18px 0}.login-brand h1 em{color:#f0cf43;font-weight:400}.login-brand span{max-width:490px;color:#bed0c4;line-height:1.7}.login-page main{display:grid;place-items:center;padding:30px}.login-card{width:min(420px,100%);background:#fff;padding:40px;border:1px solid #dfe5e0}.login-card>small{font-size:9px;letter-spacing:.18em;color:#a38110;font-weight:800}.login-card h2{font:34px Georgia;margin:16px 0 8px;color:#153e28}.login-card>p{font-size:13px;color:#68756d;line-height:1.7}.google{min-height:44px;margin-top:25px}.notice{padding:12px;background:#eef7f1;color:#25613d;font-size:12px;margin-top:15px}.notice.error{background:#faeaea;color:#9e3030}.login-card hr{border:0;border-top:1px solid #e3e8e4;margin:25px 0}.security{font-size:11px!important}.login-card:before{content:"";display:block;height:4px;background:#e5bd2e;margin:-40px -40px 35px}@media(max-width:760px){.login-page{grid-template-columns:1fr}.login-brand{padding:45px 25px}.login-brand h1{font-size:46px}.login-page main{padding:25px 14px}.login-card{padding:30px}.login-card:before{margin:-30px -30px 28px}}</style>
