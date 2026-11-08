# Current system flowchart

Based on the current repository code, reviewed 27 September 2026. Scope: student portal and the staff actions that affect student results. This is not a full administrator-module diagram. Live database contents and deployed configuration were not inspected.

Open `current-system-flowchart.drawio` in Draw.io / diagrams.net. It contains four editable pages. The diagrams below describe the same flows.

Rectangles are processes, diamonds are decisions, and rounded nodes are starts, ends, or navigation endpoints. Program guidance does not require official result publication and does not determine admission. For registrar-pass results, exact performance details are suppressed in student guidance.

## 1 - Access and dashboard

```mermaid
flowchart TD
  start(["Start / landing page"])
  method{"Choose sign-in method"}
  register["Register: name, email,<br/>password and confirmation"]
  login["Enter email and password"]
  google["Google sign-in<br/>Validate Google account"]
  valid{"Valid credentials and<br/>active account?"}
  retry["Show error / retry"]
  trusted{"Trusted device?"}
  otp["Email OTP verification<br/>Invalid / expired: retry or resend"]
  dash["Dashboard"]
  programs["Programs<br/>See page 4"]
  exam["Entrance Examination<br/>See page 2"]
  results["Results<br/>See page 2"]
  guidance["My Recommendation<br/>See page 3"]
  profile["Profile<br/>See page 4"]
  logout(["Logout → End session"])
  start --> method
  method -->|"New account"| register
  method -->|"Email login"| login
  method -->|"Google"| google
  register -->|"Valid registration"| otp
  login --> valid
  valid -->|"No"| retry
  retry -->|"Retry"| login
  valid -->|"Yes"| trusted
  trusted -->|"Yes"| dash
  trusted -->|"No"| otp
  otp -->|"Verified; create/login account"| dash
  google -->|"Verified and active"| dash
  google -->|"Rejected"| retry
  dash --> programs
  dash --> exam
  dash --> results
  dash --> guidance
  dash --> profile
  dash --> logout
```

## 2 - Examination and official results

```mermaid
flowchart TD
  entry(["Dashboard → Entrance Examination"])
  access{"Desktop/laptop on configured campus network?"}
  denied["Block exam-taking<br/>Mobile/off-site: view results only"]
  rules["Read strict rules and accept<br/>Keep examination tab active"]
  active{"Active exam session?"}
  eligible{"New attempt allowed?<br/>First attempt, or eligible retake;<br/>maximum 2 attempts"}
  blocked["No attempt available<br/>Return to dashboard / Results"]
  new["Assign 100 random questions<br/>20 per topic × 5 topics<br/>Start 120-minute deadline"]
  answer["Answer / review questions<br/>Save progress; keep this tab active<br/>No leaving or switching tabs"]
  submit["Confirm submission<br/>OR deadline expires<br/>OR tab/focus/page exit: terminate"]
  score["Score saved answers automatically<br/>Unanswered items score zero<br/>Save result and complete session"]
  pending["Results: awaiting publication"]
  review["Authorized admissions staff<br/>Review / import official scores<br/>Approve result"]
  publish["Staff publish approved result<br/>Notify student"]
  official["View published official result"]
  passed{"Official score ≥ 75<br/>OR registrar pass?"}
  pass(["PASSED"])
  attempt{"First attempt?"}
  retake["RETAKE<br/>One final attempt available"]
  fail(["FAILED<br/>No further exam attempts"])
  override["Optional staff action:<br/>Registrar pass for eligible<br/>students after two failed attempts"]
  guide["My Recommendation available<br/>after a completed exam (page 3)"]
  entry --> access
  access -->|"No"| denied
  access -->|"Yes"| active
  active -->|"Yes: not terminated"| answer
  active -->|"No"| eligible
  eligible -->|"Yes"| rules
  rules --> new
  eligible -->|"No"| blocked
  new --> answer
  answer --> submit
  submit --> score
  score --> pending
  score --> guide
  pending -->|"Staff workflow"| review
  review --> publish
  publish --> official
  official --> passed
  passed -->|"Yes"| pass
  passed -->|"No"| attempt
  attempt -->|"Yes"| retake
  attempt -->|"No"| fail
  retake -->|"Student chooses retake"| entry
  fail -->|"If eligible; staff discretion"| override
  override -->|"Registrar pass applied"| official
```

## 3 - Program recommendation

```mermaid
flowchart TD
  entry(["Dashboard → My Recommendation"])
  done{"Completed exam exists?"}
  exam["Prompt: complete entrance exam<br/>Go to Examination (page 2)"]
  load["Load saved guidance or<br/>calculate available matches"]
  ratings["Rate interests in all 5 categories<br/>1 = not interested; 5 = very interested"]
  generate["Select Generate Guidance<br/>Validate interest ratings"]
  match["Calculate program alignment<br/>Exam category scores + interests<br/>Active, recommendable catalog"]
  ready{"Sufficient evidence / catalog<br/>to produce guidance?"}
  limited["Show guidance status<br/>Explain missing evidence / availability"]
  ai["Request AI explanations<br/>for calculated matches"]
  success{"AI available?"}
  fallback["Keep calculated matches<br/>AI explanation unavailable"]
  explain["Attach AI explanations"]
  save["Save interests and guidance<br/>Unique top match: save recommendation<br/>Ties: show equally matched programs"]
  view(["View ranked programs / guidance<br/>Open program details (page 4)<br/>Change interests and regenerate"])
  entry --> done
  done -->|"No"| exam
  done -->|"Yes"| load
  load --> ratings
  ratings --> generate
  generate --> match
  match --> ready
  ready -->|"No"| limited
  ready -->|"Yes"| ai
  ai --> success
  success -->|"No"| fallback
  success -->|"Yes"| explain
  fallback --> save
  explain --> save
  limited --> save
  save --> view
  view -->|"Update interests"| ratings
```

## 4 - Programs and profile

```mermaid
flowchart TD
  dash(["Dashboard"])
  programs["Programs"]
  catalog["Load active program catalog<br/>Offerings managed by staff"]
  details["View program overview<br/>Name, code, institute, duration,<br/>description and poster"]
  subjects["Expand Subjects and career paths<br/>View subjects and career options"]
  profile["Profile"]
  fields["View name, email, applicant number,<br/>admission year, phone, date of birth<br/>and profile photo"]
  edit["Edit name, phone, date of birth<br/>or profile photo"]
  valid{"Valid profile changes?"}
  error["Show validation error<br/>Correct input"]
  save["Save changes<br/>Refresh displayed profile"]
  back(["Return to dashboard"])
  dash --> programs
  programs --> catalog
  catalog --> details
  details --> subjects
  subjects --> back
  dash --> profile
  profile --> fields
  fields --> edit
  fields -->|"View only"| back
  edit --> valid
  valid -->|"No"| error
  error --> edit
  valid -->|"Yes"| save
  save --> back
```

## Code references

- `frontend/src/router/index.js`: student pages and authentication routing.
- `backend/app/Http/Controllers/AuthController.php`: registration, login, OTP, trusted devices and Google sign-in.
- `backend/app/Services/ExamSessionService.php`: assignment, timer, saved answers, scoring and attempt limits.
- `backend/app/Models/ExamResult.php`: official outcomes and retake eligibility.
- `backend/app/Http/Controllers/ResultReviewController.php`: result approval and publication.
- `backend/app/Http/Controllers/ResultController.php`: calculated guidance, AI explanations, persistence and registrar-pass redaction.
- `frontend/src/pages/Programs.vue` and `frontend/src/pages/Profile.vue`: catalog and profile fields.

