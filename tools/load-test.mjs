// Run against a disposable staging instance. Never use real student tokens.
import {readFileSync,writeFileSync} from 'node:fs';
const base=process.env.LOAD_BASE_URL;
if(!base||!process.env.LOAD_TOKEN_FILE||process.env.LOAD_TEST_CONFIRMED!=='staging')throw Error('Set LOAD_BASE_URL, LOAD_TOKEN_FILE and LOAD_TEST_CONFIRMED=staging.');
const tokens=JSON.parse(readFileSync(process.env.LOAD_TOKEN_FILE,'utf8'));
if(!Array.isArray(tokens)||!tokens.length)throw Error('Token file must be a non-empty JSON array of disposable student tokens.');
const concurrency=Number(process.env.LOAD_CONCURRENCY||25);
if(!Number.isInteger(concurrency)||concurrency<1||concurrency>1000)throw Error('Concurrency must be 1–1000.');
const rounds=Number(process.env.LOAD_ROUNDS||5),examMode=process.env.LOAD_EXAM_MODE==='true';
if(!Number.isInteger(rounds)||rounds<1||rounds>100)throw Error('Rounds must be 1–100.');
let cursor=0;const durations=[],failures=[];
async function request(token,path,method='GET',body){const start=performance.now();const response=await fetch(base.replace(/\/$/,'')+path,{method,headers:{Authorization:`Bearer ${token}`,'Content-Type':'application/json',Accept:'application/json'},body:body?JSON.stringify(body):undefined,signal:AbortSignal.timeout(30000)});durations.push(performance.now()-start);if(!response.ok)throw Error(`${path}: HTTP ${response.status}`);return response.json()}
await Promise.all(Array.from({length:Math.min(concurrency,tokens.length)},async()=>{while(cursor<tokens.length){const index=cursor++;try{
  const token=tokens[index];await request(token,'/auth/profile');
  if(examMode){let session=await request(token,'/exam/session','POST',{rules_accepted:true});for(let round=0;round<rounds;round++){session.answers[session.questions[round%session.questions.length].id]='A';session=await request(token,'/exam/session','PUT',{session_id:session.session_id,revision:session.revision,answers:session.answers,position:round%session.questions.length})}await request(token,'/exam/submit','POST',{session_id:session.session_id,revision:session.revision,answers:session.answers})}
  else for(let round=0;round<rounds;round++)await request(token,'/student/status');
}catch(e){failures.push({student_index:index,error:e.message})}}}));
durations.sort((a,b)=>a-b);const report={students:tokens.length,concurrency,exam_mode:examMode,requests:durations.length,failed_students:failures.length,p50_ms:durations[Math.floor(durations.length*.5)]||0,p95_ms:durations[Math.floor(durations.length*.95)]||0,failures};
writeFileSync(process.env.LOAD_REPORT||'load-report.json',JSON.stringify(report,null,2));console.log(JSON.stringify({...report,failures:failures.slice(0,10)},null,2));if(failures.length)process.exitCode=1;
