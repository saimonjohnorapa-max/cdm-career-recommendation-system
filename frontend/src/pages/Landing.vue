<template>
  <div class="landing-page">
    <section id="home" class="hero-section">
      <div class="hero-pattern"></div>
      <div class="site-shell hero-grid">
        <div class="hero-copy fade-in">
          <p class="eyebrow"><span></span> Colegio de Montalban Career Recommendation</p>
          <h1>Find the program<br><em>made for you.</em></h1>
          <p class="hero-lead">Begin your college journey with an entrance assessment and personalized, data-guided program recommendation built around your strengths.</p>
          <div class="hero-actions">
            <router-link :to="primaryRoute" class="button button-gold">{{ isLoggedIn ? 'Open student portal' : 'Start your application' }} <span>→</span></router-link>
            <a href="#programs" class="button button-ghost">Explore programs</a>
          </div>
          <div class="hero-trust">
            <div><strong>9</strong><span>Academic programs</span></div>
            <div><strong>5</strong><span>Academic topics</span></div>
            <div><strong>1</strong><span>Personalized guide</span></div>
          </div>
        </div>

        <div class="hero-visual">
          <div class="campus-photo-frame">
            <img class="campus-photo" src="/campus-ynares-enhanced.png" alt="Colegio de Montalban Ynares School Building campus">
            <div class="campus-photo-shade" aria-hidden="true"></div>
            <p class="campus-photo-caption"><span>Our campus</span><strong>Ynares School Building</strong></p>
<!--
            <div class="placeholder-icon">＋</div>
            <strong>Your campus photo</strong>
            <span>Ready for the image you will provide</span>
-->
          </div>
          <div class="hero-badge">
            <img :src="schoolSeal" alt="Colegio de Montalban seal">
            <div><strong>Choose with confidence</strong><span>Guided by your strengths</span></div>
          </div>
        </div>
      </div>
      <a href="#about" class="scroll-cue" aria-label="Scroll to learn more"><span></span> Discover</a>
    </section>

    <section id="about" class="section section-light">
      <div class="site-shell split-layout">
        <div class="section-copy">
          <p class="eyebrow eyebrow--green"><span></span> Your path starts here</p>
          <h2>A smarter first step toward your future.</h2>
          <p>Our Career Recommendation System helps you explore academic programs and career paths using your assessment strengths and stated interests. Your exam and official results support this journey.</p>
          <div class="feature-list">
            <article v-for="feature in features" :key="feature.title">
              <span class="feature-number">{{ feature.number }}</span>
              <div><h3>{{ feature.title }}</h3><p>{{ feature.text }}</p></div>
            </article>
          </div>
        </div>
        <div class="about-collage">
          <figure class="photo-a campus-life-photo">
            <img src="/campus-life-ynares-enhanced.png" alt="Front view of the Colegio de Montalban Ynares School Building">
            <figcaption><span>Campus life</span><strong>A place to learn and grow</strong></figcaption>
          </figure>
          <div class="photo-b seal-card">
            <img :src="schoolSeal" alt="Colegio de Montalban official seal">
            <span>Colegio de Montalban</span>
          </div>
          <div class="collage-note"><strong>Built for every applicant</strong><span>Simple. Secure. Student-centered.</span></div>
        </div>
      </div>
    </section>

    <section id="programs" class="section programs-section">
      <div class="site-shell">
        <div class="section-heading">
          <div><p class="eyebrow"><span></span> Academic programs</p><h2>Build a future you believe in.</h2></div>
          <p>Explore the programs currently supported by the recommendation system. Each path develops practical skills for meaningful careers.</p>
        </div>
        <div class="program-grid">
          <article v-for="(course, index) in displayCourses" :key="course.code" class="program-card">
            <div class="program-image photo-placeholder"><img v-if="course.image_path" :src="imageUrl(course.image_path)" :alt="`${course.code} program poster`"><span v-else>{{ course.code }} program photo</span><b>{{ String(index + 1).padStart(2, '0') }}</b></div>
            <div class="program-content">
              <div class="program-meta"><span>{{ course.code }}</span><span>{{ course.duration || '4 years' }}</span></div>
              <h3>{{ course.name }}</h3>
              <p>{{ course.description }}</p>
              <router-link :to="isLoggedIn ? '/programs' : '/register'">View program details <span>→</span></router-link>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section id="process" class="section process-section">
      <div class="site-shell">
        <div class="process-intro"><p class="eyebrow eyebrow--green"><span></span> How it works</p><h2>From applicant to confident decision.</h2></div>
        <div class="steps-grid">
          <article v-for="step in steps" :key="step.number">
            <span class="step-line"></span><strong>{{ step.number }}</strong><h3>{{ step.title }}</h3><p>{{ step.text }}</p>
          </article>
        </div>
      </div>
    </section>

    <section id="services" class="section portal-section">
      <div class="site-shell portal-grid">
        <div class="portal-copy">
          <p class="eyebrow"><span></span> One student portal</p>
          <h2>Everything you need,<br>all in one place.</h2>
          <p>Your secure account keeps your application journey organized and gives you access to every feature of the platform.</p>
          <ul>
            <li v-for="item in portalFeatures" :key="item"><span>✓</span>{{ item }}</li>
          </ul>
          <router-link :to="primaryRoute" class="button button-gold">{{ isLoggedIn ? 'Go to dashboard' : 'Create my account' }} <span>→</span></router-link>
        </div>
        <div class="portal-preview">
          <div class="preview-top"><i></i><i></i><i></i><span>Student Portal</span></div>
          <div class="preview-body">
            <aside><div class="mini-logo"><img :src="schoolSeal" alt=""></div><i></i><i></i><i></i><i></i></aside>
            <main><div class="preview-welcome"><small>WELCOME, STUDENT</small><strong>Your academic journey</strong></div><div class="preview-stats"><i></i><i></i><i></i></div><div class="preview-chart"></div></main>
          </div>
        </div>
      </div>
    </section>

    <section id="location" class="section location-section">
      <div class="site-shell location-grid">
        <div class="location-copy">
          <p class="eyebrow eyebrow--green"><span></span> Visit our campus</p>
          <h2>Find your way to Colegio de Montalban.</h2>
          <p>Visit the campus in Kasiglahan Village, Barangay San Jose, Rodriguez, Rizal. Use the interactive map to explore the area or open directions before your visit.</p>
          <div class="location-details">
            <div><span>01</span><p><small>Campus address</small><strong>Kasiglahan Village, Brgy. San Jose,<br>Rodriguez, Rizal, Philippines</strong></p></div>
            <div><span>02</span><p><small>Map coordinates</small><strong>14.75365, 121.15105</strong></p></div>
          </div>
          <a class="button button-green" href="https://www.google.com/maps/dir/?api=1&amp;destination=14.75365%2C121.15105" target="_blank" rel="noopener noreferrer">Get directions <span>↗</span></a>
        </div>
        <div class="campus-map">
          <iframe title="Google Map showing Colegio de Montalban in Rodriguez, Rizal" src="https://www.google.com/maps?q=14.75365,121.15105&amp;z=16&amp;output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
          <div class="map-label"><img :src="schoolSeal" alt=""><p><strong>Colegio de Montalban</strong><span>Rodriguez, Rizal</span></p></div>
        </div>
      </div>
    </section>

    <section class="cta-section">
      <div class="site-shell cta-inner">
        <img :src="schoolSeal" alt="Colegio de Montalban seal">
        <div><p class="eyebrow"><span></span> Begin today</p><h2>Your future at Colegio de Montalban starts here.</h2></div>
        <router-link :to="primaryRoute" class="button button-green">{{ isLoggedIn ? 'Open portal' : 'Apply now' }} <span>→</span></router-link>
      </div>
    </section>
  </div>
</template>

<script setup>
const schoolSeal = `${import.meta.env.BASE_URL}The_Colegio_de_Montalban_Seal.png`;
import { computed, onMounted, ref } from 'vue';
import { useAuthStore } from '../stores/authStore';
import api, { API_BASE } from '../utils/api.js';

const authStore = useAuthStore();
const isLoggedIn = computed(() => authStore.isLoggedIn);
const primaryRoute = computed(() => isLoggedIn.value ? '/dashboard' : '/register');
const courses = ref([]);
const imageUrl = path => `${API_BASE}/api/storage/${path}`;

const fallbackCourses = [
  { code: 'TCP', name: 'Teacher Certificate Program', duration: 'Varies', description: 'Gain the foundations of professional teaching after completing a non-education degree.' },
  { code: 'BSED-SCI', name: 'Bachelor of Secondary Education Major in Science', duration: '4 years', description: 'Build scientific expertise and modern teaching skills for secondary education.' },
  { code: 'BSBA-HRM', name: 'BSBA Major in Human Resource Management', duration: '4 years', description: 'Prepare to manage and develop people across diverse organizations.' },
  { code: 'BEED-GEN', name: 'Bachelor of Elementary Education Major in General Education', duration: '4 years', description: 'Develop a broad foundation for effective and inclusive elementary teaching.' },
  { code: 'BSIT', name: 'Bachelor of Science in Information Technology', duration: '4 years', description: 'Develop skills in software, networks, databases, cybersecurity, and emerging digital technologies.' },
  { code: 'BECED', name: 'Bachelor of Early Childhood Education', duration: '4 years', description: 'Lead child-centered and play-based early childhood learning.' },
  { code: 'BTLED-ICT', name: 'BTLEd Major in Information and Communication Technology', duration: '4 years', description: 'Combine ICT expertise with effective teaching practice.' },
  { code: 'BSCPE', name: 'Bachelor of Science in Computer Engineering', duration: '4 years', description: 'Create and integrate modern computer software and hardware systems.' },
  { code: 'BSENTREP', name: 'Bachelor of Science in Entrepreneurship', duration: '4 years', description: 'Build the leadership and practical skills to launch sustainable ventures.' },
];
const displayCourses = computed(() => courses.value.length ? courses.value : fallbackCourses);
const features = [
  { number: '01', title: 'Create your profile', text: 'Register securely and keep your applicant information in one place.' },
  { number: '02', title: 'Take the assessment', text: 'Complete the entrance examination across key academic categories.' },
  { number: '03', title: 'Discover your matches', text: 'Rate your interests and explore personalized program guidance and career paths.' },
];
const steps = [
  { number: '01', title: 'Register', text: 'Create your student account and verify your email securely.' },
  { number: '02', title: 'Complete your profile', text: 'Add your details and upload a profile photograph.' },
  { number: '03', title: 'Take the exam', text: 'Answer the timed, category-based entrance assessment.' },
  { number: '04', title: 'Rate your interests', text: 'Tell us which activities you enjoy and generate your program guidance.' },
  { number: '05', title: 'Explore programs and careers', text: 'Compare your matches, review subjects and career paths, and discuss your options with an adviser. Check Exam Status for your official Registrar result.' },
];
const portalFeatures = ['Secure registration and OTP verification', 'Student profile and photo management', 'Five-topic entrance examination', 'Career Recommendation for programs', 'Academic program information'];

onMounted(async () => {
  try {
    const { data } = await api.get('/courses');
    courses.value = Array.isArray(data) ? data : data?.courses || data?.data || [];
  } catch (_) {
    courses.value = [];
  }
});
</script>

<style scoped>
.landing-page{background:#f7f4ec;color:#17221b}.site-shell{width:min(1180px,calc(100% - 40px));margin:auto}.section{padding:110px 0}.eyebrow{display:flex;align-items:center;gap:12px;color:#f6d44b;font-size:11px;font-weight:800;letter-spacing:.19em;text-transform:uppercase}.eyebrow span{width:34px;height:2px;background:currentColor}.eyebrow--green{color:#17613a}.hero-section{position:relative;min-height:760px;display:flex;align-items:center;overflow:hidden;background:#0d4c2d;color:#fff}.hero-pattern{position:absolute;inset:0;background:radial-gradient(circle at 15% 20%,rgba(255,255,255,.08),transparent 28%),linear-gradient(105deg,transparent 54%,rgba(0,0,0,.18) 54%);opacity:.8}.hero-grid{position:relative;display:grid;grid-template-columns:1.05fr .95fr;gap:70px;align-items:center;padding:90px 0}.hero-copy h1{font-family:Georgia,'Times New Roman',serif;font-size:clamp(54px,6vw,82px);line-height:.98;letter-spacing:-.045em;margin:24px 0}.hero-copy h1 em{color:#f4d44c;font-weight:400}.hero-lead{max-width:600px;color:#dce8e0;font-size:17px;line-height:1.75}.hero-actions{display:flex;gap:14px;margin:34px 0 44px}.button{display:inline-flex;align-items:center;justify-content:center;gap:20px;padding:15px 22px;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;transition:.2s}.button:hover{transform:translateY(-2px)}.button-gold{background:#f4d44c;color:#143b26}.button-ghost{border:1px solid rgba(255,255,255,.45);color:#fff}.button-green{background:#0f5633;color:white}.hero-trust{display:flex;gap:38px}.hero-trust div{display:flex;flex-direction:column;border-left:1px solid rgba(255,255,255,.25);padding-left:15px}.hero-trust strong{font-family:Georgia,serif;color:#f4d44c;font-size:25px}.hero-trust span{font-size:11px;color:#bfd1c5;text-transform:uppercase;letter-spacing:.08em}.hero-visual{position:relative;height:525px}.photo-placeholder{display:flex;align-items:center;justify-content:center;flex-direction:column;gap:8px;background:linear-gradient(135deg,#d9e2d8,#a7bbaa);position:relative;overflow:hidden;color:#315b41;text-align:center}.photo-placeholder:before{content:"";position:absolute;inset:0;background:linear-gradient(135deg,transparent 49.5%,rgba(255,255,255,.28) 50%,transparent 50.5%)}.photo-placeholder>*{position:relative}.photo-placeholder--hero{height:100%;border:10px solid rgba(255,255,255,.08);box-shadow:25px 25px 0 rgba(244,212,76,.15)}.placeholder-icon{width:54px;height:54px;border:1px solid currentColor;border-radius:50%;display:grid;place-items:center;font-size:28px}.photo-placeholder span{font-size:12px;opacity:.75}.hero-badge{position:absolute;left:-45px;bottom:35px;background:#fff;color:#163f29;padding:16px 20px;display:flex;align-items:center;gap:13px;box-shadow:0 15px 40px rgba(0,0,0,.25)}.hero-badge img{width:48px;height:48px}.hero-badge div{display:flex;flex-direction:column}.hero-badge span{font-size:11px;color:#6a756d}.scroll-cue{position:absolute;bottom:22px;left:50%;transform:translateX(-50%);color:#cbd9cf;font-size:10px;text-transform:uppercase;letter-spacing:.2em}.scroll-cue span{display:block;width:1px;height:28px;background:#f4d44c;margin:auto auto 8px}.split-layout{display:grid;grid-template-columns:1fr 1fr;gap:100px;align-items:center}.section-copy h2,.section-heading h2,.process-intro h2,.portal-copy h2,.cta-inner h2{font-family:Georgia,'Times New Roman',serif;font-size:clamp(39px,4vw,56px);line-height:1.08;letter-spacing:-.035em;margin:18px 0 24px;color:#143b26}.section-copy>p{color:#68736b;line-height:1.8}.feature-list{margin-top:38px}.feature-list article{display:flex;gap:20px;padding:18px 0;border-top:1px solid #d9ded8}.feature-number{color:#b69922;font-family:Georgia,serif}.feature-list h3{font-size:16px;margin-bottom:4px}.feature-list p{font-size:13px;color:#727b74}.about-collage{height:540px;position:relative}.photo-a{width:76%;height:76%;align-items:flex-start;justify-content:flex-end;padding:20px}.photo-b{position:absolute;width:48%;height:45%;right:0;bottom:0;border:8px solid #f7f4ec}.collage-note{position:absolute;left:-24px;bottom:55px;background:#0d4c2d;color:#fff;padding:20px 24px;display:flex;flex-direction:column}.collage-note span{font-size:11px;color:#cadace;margin-top:3px}.programs-section{background:#0f482c;color:#fff}.section-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:50px;margin-bottom:52px}.section-heading h2{color:#fff;margin-bottom:0}.section-heading>p{max-width:410px;color:#c3d4c9;line-height:1.7}.program-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}.program-card{background:#fff;color:#17221b}.program-image{height:205px;align-items:flex-start;justify-content:flex-end;padding:15px}.program-image b{position:absolute;right:15px;top:12px;color:rgba(255,255,255,.8);font:36px Georgia}.program-content{padding:25px}.program-meta{display:flex;justify-content:space-between;color:#1b653d;font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.program-content h3{font:24px/1.18 Georgia,serif;margin:15px 0}.program-content p{font-size:13px;color:#69736c;line-height:1.65;min-height:86px}.program-content a{display:flex;justify-content:space-between;margin-top:22px;padding-top:16px;border-top:1px solid #e5e8e5;color:#155a36;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.06em}.process-intro{text-align:center;max-width:720px;margin:0 auto 60px}.process-intro .eyebrow{justify-content:center}.steps-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr))}.steps-grid article{position:relative;padding:30px 28px;border-top:1px solid #bcc8bf}.step-line{position:absolute;top:-3px;left:0;width:42px;height:5px;background:#e0bd35}.steps-grid strong{color:#b59620;font:25px Georgia}.steps-grid h3{font:20px Georgia;margin:17px 0 10px}.steps-grid p{font-size:13px;color:#737d75;line-height:1.6}.portal-section{background:#123e29;color:#fff}.portal-grid{display:grid;grid-template-columns:.85fr 1.15fr;gap:75px;align-items:center}.portal-copy h2{color:#fff}.portal-copy>p{color:#c5d4ca;line-height:1.7}.portal-copy ul{margin:28px 0 34px;display:grid;gap:13px}.portal-copy li{display:flex;gap:12px;font-size:13px}.portal-copy li span{color:#f4d44c}.portal-preview{background:#f5f5f0;box-shadow:25px 25px 0 rgba(244,212,76,.12)}.preview-top{height:38px;background:#e6e7e2;display:flex;align-items:center;padding:0 14px;gap:6px;color:#617067;font-size:10px}.preview-top i{width:7px;height:7px;border-radius:50%;background:#afbab2}.preview-top span{margin-left:auto}.preview-body{height:330px;display:grid;grid-template-columns:90px 1fr}.preview-body aside{background:#fff;border-right:1px solid #e1e4df;display:flex;align-items:center;flex-direction:column;gap:22px;padding:17px}.mini-logo img{width:35px}.preview-body aside i{width:45px;height:6px;background:#dce2dc;border-radius:5px}.preview-body main{padding:28px}.preview-welcome{height:82px;background:#155637;color:#fff;padding:18px;display:flex;flex-direction:column}.preview-welcome small{color:#f4d44c;font-size:8px}.preview-welcome strong{margin-top:5px}.preview-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:15px 0}.preview-stats i{height:60px;background:white;border:1px solid #dde2dd;border-top:3px solid #d2b12d}.preview-chart{height:90px;background:repeating-linear-gradient(0deg,#fff,#fff 17px,#e1e5e1 18px)}.cta-section{background:#f1cd3e;padding:58px 0}.cta-inner{display:grid;grid-template-columns:85px 1fr auto;align-items:center;gap:30px}.cta-inner img{width:80px}.cta-inner .eyebrow{color:#3c5b44}.cta-inner h2{font-size:37px;margin:10px 0 0}.section-light{background:#f7f4ec}
.seal-card{display:flex;align-items:center;justify-content:center;flex-direction:column;gap:12px;background:radial-gradient(circle at 50% 40%,#fff 0%,#f4f1e8 68%,#e3dbc8 100%);box-shadow:0 20px 45px rgba(13,76,45,.18);overflow:hidden}
.seal-card:before{content:"";position:absolute;inset:13px;border:1px solid rgba(13,76,45,.15);pointer-events:none}
.seal-card img{position:relative;width:min(68%,165px);height:auto;object-fit:contain;filter:drop-shadow(0 9px 13px rgba(13,52,31,.2));transition:transform .5s ease}
.seal-card:hover img{transform:scale(1.04)}
.seal-card span{position:relative;color:#155a36;font-size:9px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}
.campus-photo-frame{position:relative;height:100%;overflow:hidden;border:10px solid rgba(255,255,255,.08);background:#d9e2d8;box-shadow:25px 25px 0 rgba(244,212,76,.15)}
.campus-photo{width:100%;height:100%;display:block;object-fit:cover;object-position:center 58%;transition:transform .7s ease}
.campus-photo-frame:hover .campus-photo{transform:scale(1.025)}
.campus-photo-shade{position:absolute;inset:0;background:linear-gradient(180deg,transparent 55%,rgba(6,31,19,.72));pointer-events:none}
.campus-photo-caption{position:absolute;right:24px;bottom:22px;display:flex;align-items:flex-end;flex-direction:column;color:#fff;text-align:right;text-shadow:0 2px 12px rgba(0,0,0,.45)}
.campus-photo-caption span{color:#f4d44c;font-size:9px;font-weight:900;letter-spacing:.18em;text-transform:uppercase}
.campus-photo-caption strong{margin-top:4px;font:18px Georgia,'Times New Roman',serif}
.campus-life-photo{position:relative;margin:0;overflow:hidden;background:#d9e2d8;box-shadow:0 22px 55px rgba(13,76,45,.16)}
.campus-life-photo img{width:100%;height:100%;display:block;object-fit:cover;object-position:center;transition:transform .7s ease}
.campus-life-photo:hover img{transform:scale(1.025)}
.campus-life-photo:after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,transparent 58%,rgba(7,39,23,.72));pointer-events:none}
.campus-life-photo figcaption{position:absolute;z-index:1;left:22px;bottom:20px;display:flex;flex-direction:column;color:#fff;text-shadow:0 2px 10px rgba(0,0,0,.4)}
.campus-life-photo figcaption span{color:#f4d44c;font-size:9px;font-weight:900;letter-spacing:.18em;text-transform:uppercase}
.campus-life-photo figcaption strong{margin-top:4px;font:18px Georgia,'Times New Roman',serif}
.location-section{background:#fff}.location-grid{display:grid;grid-template-columns:.82fr 1.18fr;gap:70px;align-items:center}.location-copy h2{font-family:Georgia,'Times New Roman',serif;font-size:clamp(39px,4vw,55px);line-height:1.08;letter-spacing:-.035em;margin:18px 0 24px;color:#143b26}.location-copy>p{color:#68736b;line-height:1.8}.location-details{display:grid;gap:0;margin:32px 0}.location-details>div{display:flex;gap:16px;padding:17px 0;border-top:1px solid #dbe1dc}.location-details>div>span{color:#b69922;font:20px Georgia,serif}.location-details p{display:flex;flex-direction:column;gap:4px}.location-details small{color:#7c897f;font-size:9px;font-weight:900;letter-spacing:.13em;text-transform:uppercase}.location-details strong{color:#214c32;font-size:13px;line-height:1.55}.campus-map{position:relative;height:470px;background:#e4e9e4;box-shadow:24px 24px 0 rgba(21,91,55,.1)}.campus-map iframe{width:100%;height:100%;border:0;display:block}.map-label{position:absolute;left:20px;bottom:20px;display:flex;align-items:center;gap:12px;padding:12px 15px;background:#fff;box-shadow:0 10px 30px rgba(8,61,36,.2)}.map-label img{width:42px;height:42px;object-fit:contain}.map-label p{display:flex;flex-direction:column}.map-label strong{font:15px Georgia,serif;color:#163f29}.map-label span{margin-top:2px;color:#77847b;font-size:9px;text-transform:uppercase;letter-spacing:.1em}
@media(max-width:900px){.hero-grid,.split-layout,.portal-grid{grid-template-columns:1fr}.hero-section{min-height:auto}.hero-grid{padding:100px 0 70px}.hero-visual{height:390px}.section{padding:75px 0}.split-layout{gap:55px}.section-heading{align-items:flex-start;flex-direction:column}.program-grid{grid-template-columns:1fr 1fr}.steps-grid{grid-template-columns:1fr 1fr}.cta-inner{grid-template-columns:70px 1fr}.cta-inner .button{grid-column:2}}
@media(max-width:900px){.location-grid{grid-template-columns:1fr;gap:45px}.campus-map{height:420px}}
@media(max-width:620px){.site-shell{width:min(100% - 28px,1180px)}.hero-copy h1{font-size:47px}.hero-actions{align-items:stretch;flex-direction:column}.hero-trust{gap:15px;justify-content:space-between}.hero-trust span{font-size:8px}.hero-visual{height:320px}.hero-badge{left:10px;bottom:15px}.program-grid,.steps-grid{grid-template-columns:1fr}.about-collage{height:400px}.portal-preview{display:none}.campus-map{height:340px;box-shadow:12px 12px 0 rgba(21,91,55,.1)}.map-label{left:12px;bottom:12px}.cta-inner{display:flex;flex-direction:column;text-align:center}.cta-inner .eyebrow{justify-content:center}.section-heading h2,.section-copy h2,.process-intro h2,.portal-copy h2,.location-copy h2{font-size:39px}}
.program-image{height:260px;padding:0}.program-image img{width:100%;height:100%;object-fit:cover}.program-image b{text-shadow:0 1px 5px #000}
</style>
