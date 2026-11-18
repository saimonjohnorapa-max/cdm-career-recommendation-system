import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';
import assert from 'node:assert/strict';
const source=readFileSync(new URL('../src/pages/Exam.vue',import.meta.url),'utf8').split('<script setup>')[1].split('</script>')[0].replace(/^import .*;$/gm,'');
const questions=Array.from({length:100},(_,i)=>({id:i+1,category:['General Mathematics','Science','Reading Comprehension','Logical Reasoning','Digital Literacy'][Math.floor(i/20)],question_text:'Question'}));
function setup(server={session:null},now=0,storage=new Map(),device={userAgent:'Windows NT'}){
  let tick, posts=0;
  const response=()=>({data:structuredClone({success:true,...server.session,server_now:new Date(now).toISOString()})});
  const api={get:async path=>{
    if(path==='/exam/access')return{data:{allowed:server.allowed!==false,message:'Examination unavailable'}};
    if(path==='/auth/profile')return{data:{student:{id:1}}};
    if(path==='/exam/session'){if(!server.session||server.session.exam_completed)throw{response:{status:404}};return response()}
    if(path==='/exam/result'){if(!server.result)throw{response:{status:404}};return{data:server.result}}
    return{data:{questions,time_limit:120}};
  },post:async(path,body)=>{
    if(path==='/exam/session/terminate'){server.session.exam_completed=true;server.session.terminated_at=new Date(now).toISOString();server.session.termination_reason=body.reason;return response()}
    if(path==='/exam/session'){server.session??={session_id:'test-session',revision:0,attempt_number:server.result?.can_retake?2:1,questions,answers:Object.fromEntries(questions.map(q=>[q.id,null])),deadline:new Date(now+7200000).toISOString(),position:0,exam_completed:false};return response()}
    posts++;if(body.revision!==server.session.revision)throw{response:{status:409,data:{message:'Changed in another tab'}}};server.session.answers=structuredClone(body.answers);server.session.exam_completed=true;return response();
  },put:async(_,body)=>{if(body.revision!==server.session.revision)throw{response:{status:409,data:{message:'Changed in another tab'}}};server.session.answers=structuredClone(body.answers);server.session.position=body.position;server.session.revision++;return response()}};
  const Swal={fire:async()=>({isConfirmed:true}),close(){},isVisible:()=>false};
  const document={hidden:false,hasFocus:()=>true};
  const ctx=vm.createContext({ref:value=>({value}),computed:fn=>({get value(){return fn()}}),api,Swal,navigator:device,document,console:{error(){}},onMounted(){},onBeforeUnmount(){},Date:{now:()=>now,parse:Date.parse},setInterval:fn=>{tick=fn;return 1},clearInterval(){},setTimeout:()=>1,clearTimeout(){},localStorage:{getItem:key=>storage.get(key)||null,setItem:(key,value)=>storage.set(key,value),removeItem:key=>storage.delete(key)}});
  vm.runInContext(source+'\nglobalThis.exam={loadExam,startExam,submitExam,syncProgress,saveProgress,examStarted,examCompleted,answers,timeRemaining,currentQuestion,nextQuestion,previousQuestion,handleAnswerKey,loadError,progressWarning,submitting,showAnswerList,rulesAccepted,accessDenied,examTerminated,endSession,sendTermination,handleBlur,handlePageHide,handleVisibility};',ctx);
  ctx.exam.rulesAccepted.value=true;
  return{...ctx.exam,api,Swal,server,advance:ms=>now+=ms,tick:()=>tick(),posts:()=>posts};
}
test('start requires loaded instructions and creates a server session',async()=>{const e=setup();await e.startExam();assert.equal(e.examStarted.value,false);await e.loadExam();await e.startExam();assert.equal(e.examStarted.value,true);assert.equal(e.timeRemaining.value,7200)});
test('server recovery preserves answers and deadline before a leave event',async()=>{const server={session:null};const a=setup(server);await a.loadExam();await a.startExam();a.answers.value[1]='C';a.nextQuestion();await a.syncProgress();const b=setup(server,600000);await b.loadExam();assert.equal(b.answers.value[1],'C');assert.equal(b.currentQuestion.value.id,2);assert.equal(b.timeRemaining.value,6600)});
test('browser storage failure still allows server saves',async()=>{const storage=new Map();storage.set=()=>{throw Error('blocked')};const e=setup({session:null},0,storage);await e.loadExam();await e.startExam();e.answers.value[1]='A';e.saveProgress();await e.syncProgress();assert.equal(e.server.session.answers[1],'A')});
test('stale revisions show conflict rather than completion',async()=>{const e=setup();await e.loadExam();await e.startExam();e.server.session.revision++;await e.submitExam();assert.equal(e.examCompleted.value,false);assert.ok(e.loadError.value)});
test('offline save preserves answers and reports unsaved changes',async()=>{const e=setup();await e.loadExam();await e.startExam();e.answers.value[1]='B';e.api.put=async()=>{throw Error('offline')};await assert.rejects(e.syncProgress());assert.equal(e.answers.value[1],'B');assert.ok(e.progressWarning.value)});
test('retake has its own final session and cannot submit twice',async()=>{const e=setup({session:null,result:{exam_completed:true,attempts_used:1,can_retake:true}});await e.loadExam();await e.startExam();assert.equal(e.server.session.attempt_number,2);await e.submitExam();assert.equal(e.examCompleted.value,true);await e.submitExam();assert.equal(e.posts(),1)});
test('exhausted attempts cannot start again',async()=>{const e=setup({session:null,result:{exam_completed:true,attempts_used:2,can_retake:false}});await e.loadExam();await e.startExam();assert.equal(e.examCompleted.value,true);assert.equal(e.server.session,null)});
test('keyboard navigation keeps answers and respects dialogs',async()=>{const e=setup();await e.loadExam();await e.startExam();e.handleAnswerKey({key:'B',preventDefault(){}});assert.equal(e.answers.value[1],'B');for(let i=0;i<20;i++)e.nextQuestion();assert.equal(e.currentQuestion.value.category,'Science');e.showAnswerList.value=true;e.handleAnswerKey({key:'A',preventDefault(){throw Error('locked')}});assert.equal(e.answers.value[21],null)});
test('cancel submission leaves session active',async()=>{const e=setup();await e.loadExam();await e.startExam();e.Swal.fire=async()=>({isConfirmed:false});await e.submitExam();assert.equal(e.posts(),0);assert.equal(e.examStarted.value,true)});

test('rules acknowledgment is required before starting',async()=>{const e=setup();await e.loadExam();e.rulesAccepted.value=false;await e.startExam();assert.equal(e.server.session,null)});
for(const device of [{userAgent:'iPhone Mobile'},{userAgent:'Android',userAgentData:{mobile:true}},{userAgent:'iPad'},{userAgent:'Macintosh',maxTouchPoints:5}]){
  test(`${device.userAgent} can load, start, save and submit the exam`,async()=>{
    const e=setup({session:null},0,new Map(),device);
    await e.loadExam();await e.startExam();
    assert.equal(e.accessDenied.value,'');assert.equal(e.examStarted.value,true);
    e.answers.value[1]='B';e.nextQuestion();await e.syncProgress();
    assert.equal(e.server.session.answers[1],'B');assert.equal(e.server.session.position,1);
    await e.submitExam();assert.equal(e.examCompleted.value,true);assert.equal(e.posts(),1);
  });
}
test('server access denial never loads or starts exam content',async()=>{const e=setup({session:null,allowed:false});await e.loadExam();await e.startExam();assert.ok(e.accessDenied.value);assert.equal(e.server.session,null);assert.equal(e.currentQuestion.value,undefined)});
test('switching away locks immediately and submits only server-saved answers',async()=>{const e=setup();await e.loadExam();await e.startExam();e.answers.value[1]='B';await e.syncProgress();e.answers.value[2]='D';e.handleBlur();assert.equal(e.examTerminated.value,true);await e.sendTermination();assert.equal(e.examCompleted.value,true);assert.equal(e.server.session.answers[1],'B');assert.equal(e.server.session.answers[2],null);await e.submitExam();assert.equal(e.posts(),0)});
test('page exit terminates only an active attempt',async()=>{const e=setup();e.handlePageHide();assert.equal(e.examTerminated.value,false);await e.loadExam();await e.startExam();e.handlePageHide();await e.sendTermination();assert.equal(e.server.session.termination_reason,'page_left')});
test('offline termination survives reload and must finish before recovery',async()=>{const server={session:null},storage=new Map();const a=setup(server,0,storage);await a.loadExam();await a.startExam();a.api.post=async()=>{throw Error('offline')};a.handleBlur();await a.sendTermination();assert.equal(a.examCompleted.value,false);assert.ok(storage.get('cdm-exam-termination:1'));const b=setup(server,1000,storage);await b.loadExam();assert.equal(b.examTerminated.value,true);assert.equal(b.examCompleted.value,true);assert.equal(server.session.exam_completed,true);assert.equal(storage.has('cdm-exam-termination:1'),false)});
