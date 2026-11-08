import { test, expect } from '@playwright/test';
const student={id:1,first_name:'Browser',last_name:'Test',full_name:'Browser Test',email:'browser@example.invalid',student_number:'BROWSER-1',admission_year:'TEST'};
const questions=Array.from({length:100},(_,i)=>({id:i+1,category:['General Mathematics','Science','Reading Comprehension','Logical Reasoning','Digital Literacy'][Math.floor(i/20)],question_text:`Browser question ${i+1}`,option_a:'First answer',option_b:'Second answer',option_c:'Third answer',option_d:'Fourth answer'}));
const fulfill=(route,data,status=200)=>route.fulfill({status,contentType:'application/json',body:JSON.stringify(data)});
async function authenticated(page,handler){
  await page.addInitScript(()=>{localStorage.setItem('auth_token','synthetic-browser-token');localStorage.setItem('admin_token','synthetic-admin-token')});
  await page.route('**/api/**',async route=>{
    const path=new URL(route.request().url()).pathname;
    if(path.endsWith('/auth/profile')&&!path.includes('/admin/'))return fulfill(route,{success:true,student});
    if(path.endsWith('/admin/auth/profile'))return fulfill(route,{admin:{id:1,name:'Registrar',role:'admissions_staff',status:'active'}});
    if(path.endsWith('/exam/access'))return fulfill(route,{allowed:true,message:null});
    return handler(route,path);
  });
}
test('results failure shows retry instead of no completed exam',async({page})=>{
  let failed=true;await authenticated(page,(route,path)=>path.endsWith('/exam/result')?fulfill(route,failed?{message:'Temporary failure'}:{exam_completed:true,official_status:'published',official_result:{outcome:'PASSED',score:85}},failed?503:200):fulfill(route,{}));
  await page.goto('/cdm-career-recommendation-system/#/results');await expect(page.getByRole('alert')).toContainText('Temporary failure');await expect(page.getByText('No completed exam yet')).toHaveCount(0);
  failed=false;await page.getByRole('button',{name:'Retry',exact:true}).click();await expect(page.getByRole('heading',{name:'PASSED',exact:true})).toBeVisible();
});
test('exam saves edits and permanently ends the attempt when the tab is hidden',async({page})=>{
  let session={success:true,session_id:'session',attempt_number:1,revision:0,position:0,exam_completed:false,deadline:new Date(Date.now()+3600000).toISOString(),questions,answers:Object.fromEntries(questions.map(q=>[q.id,q.id===1?'B':null]))};
  await authenticated(page,(route,path)=>{
    if(path.endsWith('/exam/session/terminate')){
      session={...session,exam_completed:true,terminated_at:new Date().toISOString(),termination_reason:route.request().postDataJSON().reason};
      return fulfill(route,{...session,server_now:new Date().toISOString()});
    }
    if(path.endsWith('/exam/result'))return fulfill(route,{exam_completed:true,attempts_used:1,can_retake:false,terminated_at:session.terminated_at});
    if(path.endsWith('/exam/session')){
      if(session.exam_completed)return fulfill(route,{},404);
      if(route.request().method()==='PUT'){const body=route.request().postDataJSON();session={...session,answers:body.answers,position:body.position,revision:session.revision+1}}
      return fulfill(route,{...session,server_now:new Date().toISOString()});
    }
    return fulfill(route,{},404);
  });
  await page.goto('/cdm-career-recommendation-system/#/exam');await expect(page.getByRole('radio').nth(1)).toBeChecked();await expect(page.getByRole('button',{name:'Begin Examination',exact:true})).toHaveCount(0);
  await page.keyboard.press('c');await expect(page.getByRole('radio').nth(2)).toBeChecked();await expect.poll(()=>session.answers[1]).toBe('C');
  // Headless Chrome keeps automation pages visible when another page is brought
  // forward. Deliver the visibility event a real tab switch generates.
  await page.evaluate(()=>{
    Object.defineProperty(document, 'hidden', { configurable:true, get:()=>true });
    document.dispatchEvent(new Event('visibilitychange'));
  });
  await expect(page.getByRole('heading',{name:'Examination session ended',exact:true})).toBeVisible();
  await expect(page.getByRole('radio')).toHaveCount(0);
  await expect.poll(()=>session.exam_completed).toBe(true);
  await page.reload();
  await expect(page.getByRole('heading',{name:'Examination session ended',exact:true})).toBeVisible();
  await expect(page.getByRole('radio')).toHaveCount(0);
});

test('exam instructions require rules acknowledgment',async({page})=>{
  await authenticated(page,(route,path)=>path.endsWith('/exam/questions')?fulfill(route,{questions:questions.map(({id,category})=>({id,category})),time_limit:120}):fulfill(route,{},404));
  await page.goto('/cdm-career-recommendation-system/#/exam');
  await expect(page.getByText('Strict Examination Rules',{exact:true})).toBeVisible();
  const begin=page.getByRole('button',{name:'Begin Examination',exact:true});
  await expect(begin).toBeDisabled();
  await page.getByRole('checkbox').check();
  await expect(begin).toBeEnabled();
});

test('mobile examination is blocked while published results remain viewable',async({page})=>{
  await page.addInitScript(()=>Object.defineProperty(navigator,'userAgent',{get:()=> 'Mozilla/5.0 (iPhone) Mobile'}));
  let examContentRequests=0;
  await authenticated(page,(route,path)=>{
    if(path.endsWith('/exam/result'))return fulfill(route,{exam_completed:true,official_status:'published',official_result:{outcome:'PASSED',score:85}});
    if(path.endsWith('/exam/session')||path.endsWith('/exam/questions'))examContentRequests++;
    return fulfill(route,{},404);
  });
  await page.goto('/cdm-career-recommendation-system/#/exam');
  await expect(page.getByRole('heading',{name:'Desktop or laptop required',exact:true})).toBeVisible();
  await expect(page.getByRole('button',{name:'Begin Examination',exact:true})).toHaveCount(0);
  expect(examContentRequests).toBe(0);
  await page.getByRole('link',{name:'View Exam Results',exact:true}).click();
  await expect(page.getByRole('heading',{name:'PASSED',exact:true})).toBeVisible();
});
test('Registrar pass displays PASSED without internal details or numeric score',async({page})=>{
  await authenticated(page,route=>fulfill(route,{exam_completed:true,official_status:'published',official_result:{outcome:'PASSED',score:null,percentage:null,remarks:null}}));
  await page.goto('/cdm-career-recommendation-system/#/results');await expect(page.getByRole('heading',{name:'PASSED',exact:true})).toBeVisible();await expect(page.locator('.official-score')).toHaveCount(0);await expect(page.getByText('Passed by Registrar decision')).toHaveCount(0);
});
test('password recovery sends email and displays delivery status',async({page})=>{
  let email;await page.route('**/api/auth/forgot-password',route=>{email=route.request().postDataJSON().email;return fulfill(route,{message:'If eligible, a reset link will be sent.'})});
  await page.goto('/cdm-career-recommendation-system/#/forgot-password');await page.getByLabel('Email',{exact:true}).fill('browser@example.invalid');await page.getByRole('button',{name:'Send reset link'}).click();await expect(page.getByRole('status')).toContainText('reset link');expect(email).toBe('browser@example.invalid');
});
test('Registrar can select pending rows and approve calculated scores',async({page})=>{
  let approved=false;const row={id:1,student,total_score:85,official_score:null,official_status:'pending',official_outcome:'PENDING',attempt_number:1,exam_date:new Date().toISOString(),result_version:'a'.repeat(64)};
  await authenticated(page,(route,path)=>{if(path.endsWith('/approve-batch')){approved=true;return fulfill(route,{message:'1 result approved'})}if(path.endsWith('/admin/results'))return fulfill(route,{data:[row],matching_ids:[1],total:1,current_page:1});return fulfill(route,{})});
  page.on('dialog',dialog=>dialog.accept());await page.goto('http://127.0.0.1:5190/cdm-career-recommendation-system/admin/#/results');await page.locator('select').nth(1).selectOption('pending');await page.getByRole('button',{name:'Select all matching pending results'}).click();await page.getByRole('button',{name:'Approve 1 selected using system scores'}).click();await expect.poll(()=>approved).toBe(true);
});

test('mobile recommendation menu requires a submitted exam and then permits guidance', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  let completed = false;
  await authenticated(page, (route, path) => {
    if (path.endsWith('/results/recommendation')) return fulfill(route, completed ? {
      recommendation: { status: 'ready', method: 'Exam alignment only', limitation: 'Preliminary guidance.', tied_top_codes: ['BSIT'], ai_status: 'not_requested', ranked_programs: [{ course_code: 'BSIT', course_name: 'Information Technology', score: 80 }] }, interests: {}
    } : { message: 'Complete the entrance exam first.' }, completed ? 200 : 403);
    return fulfill(route, {});
  });
  await page.goto('/cdm-career-recommendation-system/#/recommendation');
  await page.getByRole('button', { name: 'Toggle menu' }).click();
  await expect(page.locator('.mobile-nav').getByRole('link', { name: 'My Recommendation', exact: true })).toBeVisible();
  await page.locator('.mobile-nav').getByRole('link', { name: 'My Recommendation', exact: true }).click();
  await expect(page.getByRole('alert')).toContainText('Complete the entrance exam first.');
  await expect(page.getByRole('link', { name: 'Take the exam', exact: true })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Generate My AI Guidance' })).toHaveCount(0);
  completed = true;
  await page.reload();
  await expect(page.getByRole('button', { name: 'Generate My AI Guidance' })).toBeVisible();
  await expect(page.getByRole('combobox')).toHaveCount(5);
  await expect(page.getByRole('heading', { name: 'Information Technology', exact: true })).toBeVisible();
});

test('narrow mobile dashboard keeps navigation and cards within viewport', async ({ page }) => {
  await page.setViewportSize({width:320,height:478});
  await authenticated(page,(route,path)=> {
    if(path.endsWith('/results/recommendation'))return fulfill(route,{recommendation:{status:'insufficient_evidence',message:'There is not enough verified exam evidence to rank programs. Please discuss your interests and assessment with a school adviser.'},interests:{}});
    if(path.endsWith('/student/dashboard'))return fulfill(route,{success:true,exam_taken:true});
    if(path.endsWith('/student/status'))return fulfill(route,{status:'PENDING',attempts_used:1,timeline:[],notifications:[]});
    return fulfill(route,{});
  });
  await page.goto('/cdm-career-recommendation-system/#/dashboard');
  await expect(page.getByRole('link',{name:'Review my guidance',exact:true})).toBeVisible();
  await expect(page.getByRole('button',{name:'Toggle menu'})).toBeInViewport();
  await expect(page.getByRole('button',{name:'Logout',exact:true})).toBeInViewport();
  expect(await page.evaluate(()=>document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  await page.getByRole('button',{name:'Toggle menu'}).click();
  await expect(page.locator('.mobile-nav').getByRole('link',{name:'My Recommendation',exact:true})).toBeVisible();
});

const matchBasis = {
  summary: 'This program has the highest combined alignment with your evidence among the programs compared. Your largest weighted contributions to this match come from Digital Literacy and Science.',
  exam_points: 65, interest_points: 15,
  factors: [
    { category: 'Digital Literacy', weight: 75, exam_correct: 4, exam_total: 4, interest_rating: 5, points: 75 },
    { category: 'Science', weight: 25, exam_correct: 1, exam_total: 4, interest_rating: 1, points: 5 },
  ],
  comparison: { course_code: 'SCI', course_name: 'Science', score_gap: 40, advantages: [{ category: 'Digital Literacy', points: 50 }] },
};
const guidance = {
  status: 'ready', method: '80% exam alignment + 20% stated interests', limitation: 'Preliminary guidance.',
  tied_top_codes: ['IT'], ai_status: 'unavailable',
  ranked_programs: [
    { course_code: 'IT', course_name: 'Computing', score: 80, exam_match: 81.25, interest_match: 75, match_basis: matchBasis },
    { course_code: 'SCI', course_name: 'Science', score: 40, exam_match: 43.75, interest_match: 25 },
  ],
};

test('highest match explains weighted evidence and its lead even without AI on mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await authenticated(page, route => fulfill(route, { recommendation: guidance, interests: { 'Digital Literacy': 5 } }));
  await page.goto('/cdm-career-recommendation-system/#/recommendation');
  const reason = page.getByRole('region', { name: 'Match explanation for Computing', exact: true });
  await expect(reason.getByRole('heading', { name: 'Why this is your highest match', exact: true })).toBeVisible();
  await expect(reason).toContainText('Exam: 65 points + interests: 15 points = 80 / 100');
  await expect(reason).toContainText('40 match points');
  await expect(reason).toContainText('Digital Literacy (+50 points)');
  await expect(reason).toContainText('Exam evidence: 4 / 4 correct');
  await expect(reason).toContainText('Program emphasis: 75%');
  await page.getByRole('combobox', { name: 'Using technology and exploring computer systems' }).selectOption('1');
  // Editing the form does not rewrite the evidence behind the last calculation.
  await expect(reason).toContainText('Your saved interest: 5 / 5');
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});

test('tied top match reasons describe equal matches without a winning margin', async ({ page }) => {
  const tiedBasis = { ...matchBasis, comparison: null, summary: 'This program shares the highest calculated match; the evidence does not identify a single strongest program.' };
  const tied = { ...guidance, tied_top_codes: ['IT', 'SCI'], ranked_programs: guidance.ranked_programs.map(program => ({ ...program, score: 80, match_basis: tiedBasis })) };
  await authenticated(page, route => fulfill(route, { recommendation: tied, interests: {} }));
  await page.goto('/cdm-career-recommendation-system/#/recommendation');
  await expect(page.getByRole('heading', { name: 'Why this shares your highest match', exact: true })).toHaveCount(2);
  await expect(page.getByText('It leads', { exact: false })).toHaveCount(0);
});

test('guidance with restricted scores does not render a numeric match explanation', async ({ page }) => {
  const restricted = { ...guidance, ranked_programs: guidance.ranked_programs.map(({ course_code, course_name }) => ({ course_code, course_name })) };
  await authenticated(page, route => fulfill(route, { recommendation: restricted, interests: {} }));
  await page.goto('/cdm-career-recommendation-system/#/recommendation');
  await expect(page.getByRole('heading', { name: 'Computing', exact: true })).toBeVisible();
  await expect(page.getByRole('region', { name: /Match explanation for/ })).toHaveCount(0);
  await expect(page.getByText(/match score|Exam evidence:|NaN/)).toHaveCount(0);
});
