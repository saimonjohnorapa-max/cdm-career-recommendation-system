<template>
  <router-view v-if="route.name === 'Login'" />
  <div v-else class="admin-shell">
    <aside :class="['sidebar', { open: menuOpen }]">
      <div class="brand"><img :src="schoolSeal" alt="CDM seal"><div><strong>Colegio de Montalban</strong><small>Administration Portal</small></div></div>
      <nav>
        <router-link v-for="item in visibleNav" :key="item.to" :to="item.to" @click="menuOpen=false"><span>{{ item.icon }}</span>{{ item.label }}</router-link>
      </nav>
      <div class="sidebar-foot"><small>Signed in as</small><strong>{{ auth.admin?.name }}</strong><span>{{ roleLabel }}</span><button @click="logout">Sign out</button></div>
    </aside>
    <button v-if="menuOpen" class="backdrop" aria-label="Close menu" @click="menuOpen=false"></button>
    <section class="workspace">
      <header><button class="menu" @click="menuOpen=!menuOpen">☰</button><div><small>ADMINISTRATION PORTAL</small><strong>{{ route.meta.title || route.name }}</strong></div><div class="admin-chip"><img v-if="auth.admin?.profile_picture" :src="auth.admin.profile_picture" alt=""><span v-else>{{ initials }}</span><b>{{ auth.admin?.name }}</b></div></header>
      <main><router-view /></main>
    </section>
  </div>
</template>
<script setup>
const schoolSeal = `${import.meta.env.BASE_URL}The_Colegio_de_Montalban_Seal.png`;
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAdminAuth } from './stores/auth';
const route=useRoute(),router=useRouter(),auth=useAdminAuth(),menuOpen=ref(false);
const nav=[{to:'/',label:'Dashboard',icon:'⌂'},{to:'/students',label:'Students',icon:'S'},{to:'/student-logs',label:'Student Logs',icon:'L'},{to:'/programs',label:'Programs',icon:'P'},{to:'/questions',label:'Exam Questions',icon:'Q'},{to:'/results',label:'Results',icon:'R'},{to:'/administrators',label:'Administrators',icon:'A',super:true},{to:'/activity',label:'Activity Logs',icon:'L',super:true},{to:'/operations',label:'System Health',icon:'H',super:true}];
const visibleNav=computed(()=>nav.filter(i=>!i.super||auth.isSuper));
const roleLabel=computed(()=>(auth.admin?.role||'').replaceAll('_',' '));
const initials=computed(()=>(auth.admin?.name||'Admin').split(/\s+/).slice(0,2).map(p=>p[0]).join('').toUpperCase());
const logout=async()=>{await auth.logout();router.push('/login')};
</script>
