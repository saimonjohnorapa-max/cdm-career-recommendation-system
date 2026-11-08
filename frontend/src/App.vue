<template>
  <div class="app-shell"><div v-if="sessionExpired" role="alert" class="p-4 bg-amber-100">Your session expired. <router-link to="/login" class="underline">Sign in again</router-link> to continue using the portal.</div>
    <div class="announcement-bar"><div class="header-shell"><span>Admissions and Career Recommendation Portal</span><span class="announcement-right">Rodriguez, Rizal · {{ currentYear }}</span></div></div>
    <header class="site-header" :class="{ 'site-header--landing': isLanding }">
      <div class="header-shell header-main">
        <router-link to="/" class="brand" @click="mobileOpen=false"><img :src="schoolSeal" alt="Colegio de Montalban seal"><span><strong>Colegio de Montalban</strong><small>Career Recommendation System</small></span></router-link>
        <nav v-if="isLoggedIn" class="desktop-nav" aria-label="Student navigation"><router-link v-for="item in studentNav" :key="item.to" :to="item.to">{{ item.short || item.label }}</router-link></nav>
        <nav v-else class="desktop-nav" aria-label="Website navigation"><router-link to="/">Home</router-link><router-link :to="{ path: '/', hash: '#about' }">About</router-link><router-link :to="{ path: '/', hash: '#programs' }">Programs</router-link><router-link :to="{ path: '/', hash: '#process' }">How it works</router-link><router-link :to="{ path: '/', hash: '#location' }">Location</router-link><router-link to="/download-app">Get the app</router-link></nav>
        <div class="header-actions">
          <template v-if="isLoggedIn"><router-link to="/profile" class="user-pill"><span>{{ userInitials }}</span><b>{{ user?.first_name || 'Student' }}</b></router-link><button class="header-button header-button--outline" @click="handleLogout">Logout</button></template>
          <template v-else><router-link to="/login" class="text-login">Sign in</router-link><router-link to="/register" class="header-button">Apply now</router-link></template>
          <button class="menu-button" :aria-expanded="mobileOpen" aria-label="Toggle menu" @click="mobileOpen=!mobileOpen"><span></span><span></span><span></span></button>
        </div>
      </div>
      <nav v-if="mobileOpen" class="mobile-nav">
        <template v-if="isLoggedIn"><router-link v-for="item in studentNav" :key="item.to" :to="item.to" @click="mobileOpen=false">{{ item.label }}</router-link></template>
        <template v-else><router-link to="/" @click="mobileOpen=false">Home</router-link><router-link :to="{ path: '/', hash: '#about' }" @click="mobileOpen=false">About</router-link><router-link :to="{ path: '/', hash: '#programs' }" @click="mobileOpen=false">Programs</router-link><router-link :to="{ path: '/', hash: '#process' }" @click="mobileOpen=false">How it works</router-link><router-link :to="{ path: '/', hash: '#location' }" @click="mobileOpen=false">Campus location</router-link><router-link to="/download-app" @click="mobileOpen=false">Get the mobile app</router-link><router-link to="/login" @click="mobileOpen=false">Student sign in</router-link></template>
      </nav>
    </header>
    <main class="main-content" :class="{ 'portal-background': isPortalPage }" :data-no-scroll-reveal="route.name === 'Exam' ? '' : null"><div v-if="isPortalPage" class="portal-container"><router-view /></div><router-view v-else /></main>
    <VoiceAssistantPlaceholder v-if="route.name !== 'Exam'" />
    <footer v-if="showFooter" class="site-footer">
      <div class="footer-shell footer-grid">
        <div class="footer-brand"><img :src="schoolSeal" alt="Colegio de Montalban seal"><div><strong>Colegio de Montalban</strong><span>Career Recommendation System</span></div></div>
        <div><h3>Explore</h3><router-link to="/">Home</router-link><a href="/#about">About the system</a><a href="/#programs">Academic programs</a></div>
        <div><h3>Student services</h3><router-link :to="isLoggedIn?'/dashboard':'/login'">Student portal</router-link><router-link to="/exam">Entrance exam</router-link><router-link to="/results">Exam Status</router-link><router-link to="/recommendation">My Recommendation</router-link><router-link to="/download-app">Install mobile app</router-link></div>
        <div><h3>Get started</h3><p>Take the first step toward the program that fits your strengths.</p><router-link :to="isLoggedIn?'/dashboard':'/register'">{{ isLoggedIn?'Open dashboard':'Create an account' }} →</router-link></div>
      </div>
      <div class="footer-shell footer-bottom"><span>© {{ currentYear }} Colegio de Montalban. All rights reserved.</span><span>Rodriguez, Rizal, Philippines</span></div>
    </footer>
  </div>
</template>
<script setup>
const schoolSeal = `${import.meta.env.BASE_URL}The_Colegio_de_Montalban_Seal.png`;
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from './stores/authStore';
import VoiceAssistantPlaceholder from './components/VoiceAssistantPlaceholder.vue';
const sessionExpired=ref(false);
const expired=()=>{sessionExpired.value=true};
onMounted(()=>window.addEventListener('session-expired',expired));
onBeforeUnmount(()=>window.removeEventListener('session-expired',expired));
const route=useRoute(),router=useRouter(),authStore=useAuthStore(),mobileOpen=ref(false);
const isLoggedIn=computed(()=>authStore.isLoggedIn),user=computed(()=>authStore.getUser),currentYear=new Date().getFullYear();
const isLanding=computed(()=>route.name==='Home'),isPortalPage=computed(()=>Boolean(route.meta.requiresAuth)),showFooter=computed(()=>!['Login','Register'].includes(route.name));
const userInitials=computed(()=>(user.value?.full_name||'Student').split(/\s+/).slice(0,2).map(p=>p[0]).join('').toUpperCase());
const studentNav=[{to:'/dashboard',label:'Dashboard'},{to:'/profile',label:'Profile'},{to:'/recommendation',label:'My Recommendation'},{to:'/programs',label:'Programs'},{to:'/exam',label:'Entrance Exam',short:'Exam'},{to:'/results',label:'Exam Status',short:'Status'},{to:'/download-app',label:'Mobile App',short:'App'}];
watch(()=>route.fullPath,()=>{mobileOpen.value=false});
const handleLogout=async()=>{if(!confirm('Are you sure you want to logout?'))return;await authStore.logout();router.push('/')};
authStore.initializeAuth();
</script>
<style scoped>
.app-shell{min-height:100vh;display:flex;flex-direction:column}.announcement-bar{height:31px;background:#083d24;color:#bcd0c3;font-size:10px;letter-spacing:.1em;text-transform:uppercase}.header-shell,.footer-shell{width:min(1220px,calc(100% - 40px));margin:auto}.announcement-bar .header-shell{height:100%;display:flex;align-items:center;justify-content:space-between}.site-header{position:relative;z-index:50;background:#fff;border-bottom:1px solid #e5e9e5}.site-header--landing{position:absolute;top:31px;left:0;right:0;background:rgba(255,255,255,.98)}.header-main{height:83px;display:flex;align-items:center;justify-content:space-between;gap:25px}.brand{display:flex;align-items:center;gap:12px;color:#12492c;flex-shrink:0}.brand img{width:54px;height:54px;object-fit:contain}.brand span{display:flex;flex-direction:column}.brand strong{font-family:Georgia,'Times New Roman',serif;font-size:19px}.brand small{font-size:8px;font-weight:800;color:#a38518;letter-spacing:.14em;text-transform:uppercase}.desktop-nav{display:flex;align-items:center;gap:24px;margin-left:auto}.desktop-nav a{font-size:12px;font-weight:700;color:#38453c;white-space:nowrap}.desktop-nav a:hover,.desktop-nav .router-link-active{color:#167244}.header-actions{display:flex;align-items:center;gap:13px}.text-login{font-size:12px;font-weight:800;color:#134b2e}.header-button{background:#155b37;color:#fff;padding:11px 17px;font-size:10px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.header-button--outline{background:transparent;color:#155b37;border:1px solid #b8c7bd}.user-pill{display:flex;align-items:center;gap:8px;font-size:11px;color:#31533e}.user-pill span{width:30px;height:30px;border-radius:50%;background:#17603a;color:white;display:grid;place-items:center;font-size:10px}.menu-button{display:none;width:38px;height:38px;padding:9px}.menu-button span{display:block;height:2px;background:#174b2e;margin:4px}.mobile-nav{display:grid;padding:12px 20px 20px;background:#fff;border-top:1px solid #edf0ed}.mobile-nav a{padding:11px;border-bottom:1px solid #edf0ed;font-size:13px;font-weight:700;color:#264d35}.main-content{flex:1}.portal-background{background:linear-gradient(135deg,#f4f3ec 0%,#fbfaf6 100%)}.portal-container{max-width:1440px;margin:auto;padding:30px 32px}.site-footer{background:#092f1d;color:#d2ddd6}.footer-grid{display:grid;grid-template-columns:1.6fr 1fr 1.1fr 1.35fr;gap:60px;padding:65px 0 50px}.footer-brand{display:flex;gap:15px;align-items:flex-start}.footer-brand img{width:65px}.footer-brand div{display:flex;flex-direction:column}.footer-brand strong{font:20px Georgia}.footer-brand span{font-size:8px;color:#d7b934;text-transform:uppercase;letter-spacing:.13em;margin-top:5px}.footer-grid h3{font-size:10px;color:#e3c33f;text-transform:uppercase;letter-spacing:.15em;margin-bottom:17px}.footer-grid>div:not(:first-child){display:flex;flex-direction:column;align-items:flex-start;gap:10px}.footer-grid a,.footer-grid p{font-size:12px;color:#b9cabf}.footer-grid a:hover{color:#fff}.footer-grid p{line-height:1.6}.footer-bottom{border-top:1px solid rgba(255,255,255,.12);padding:19px 0;display:flex;justify-content:space-between;color:#80988a;font-size:9px;text-transform:uppercase;letter-spacing:.1em}
@media(max-width:1100px){.desktop-nav{display:none}.menu-button{display:block}.portal-container{padding:24px 18px}.footer-grid{grid-template-columns:1.4fr 1fr 1fr}.footer-grid>div:last-child{display:none!important}}@media(max-width:700px){.announcement-right,.user-pill b,.text-login{display:none}.header-shell,.footer-shell{width:min(100% - 28px,1220px)}.brand img{width:45px;height:45px}.brand strong{font-size:16px}.brand small{font-size:7px}.header-main{height:72px}.site-header--landing{top:31px}.header-button{padding:9px 11px}.footer-grid{grid-template-columns:1fr;gap:30px}.footer-grid>div:not(:first-child){display:none}.footer-bottom{gap:8px;flex-direction:column}.portal-container{padding:18px 10px}}
/* Keep the navigation available while the visitor browses down the page. */
.site-header,.site-header--landing{position:sticky;top:0;left:auto;right:auto;z-index:50;box-shadow:0 3px 14px rgba(8,61,36,.08)}
@media(max-width:700px){.site-header--landing{top:0}}
@media(max-width:700px){
  .header-main{gap:8px}.brand{min-width:0;flex:1;gap:8px}.brand span{min-width:0}.brand strong{display:block;font-size:14px;line-height:1.2;white-space:normal}.brand small{display:block;font-size:6px;letter-spacing:.06em;line-height:1.4}.brand img{width:36px;height:36px;flex-shrink:0}.header-actions{gap:6px;flex-shrink:0}.user-pill{display:none}.header-button{font-size:9px;padding:9px 7px}.menu-button{flex-shrink:0;min-height:44px}.announcement-bar{font-size:8px;letter-spacing:.03em}.portal-container{padding:16px 10px}.mobile-nav{max-height:calc(100dvh - 72px);overflow-y:auto}
}
</style>
