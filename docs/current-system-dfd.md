# Current system data flow diagrams

Open `current-system-dfd.drawio` in Draw.io. Five editable black-and-white pages are included. Examination policy updated 26 October 2026; other flows based on repository code reviewed 27 September 2026, not a live deployment inspection.

## Scope and conventions

These diagrams cover core admissions: authentication, profiles, examination, official results, program guidance, catalog management and related staff operations. The optional conversational/voice assistant and app-download interface are outside this diagram set. Google authentication verification and email transport are abstracted within account/notification processes; Gemini is explicit because it participates in recommendation generation. This is a logical DFD, not a deployment diagram.

- External actors/services: rectangles.
- Processes: rounded rectangles, numbered consistently.
- Data stores: open-ended rectangles; D1–D9 are logical groups of actual tables.
- Arrows: named data flows, not execution order or control decisions.
- Pages 3 and 4 are role views of Level 1, not independent decomposition levels. Shared processes remain the same processes; these pages intentionally omit other-role flows.
- Read-only GET guidance can return calculated or cached matches without generating or saving AI prose. Valid POST requests save interests/guidance; insufficient evidence can also be saved as a status. Unavailable AI leaves calculated matches usable.
- Normal guidance uses 80% exam alignment and 20% stated interests when interest ratings exist; otherwise exam alignment alone. AI explains rankings and does not determine scores.
- Guidance does not require official result publication. Official result display and retake eligibility use the registrar-controlled publication state.
- A unique top match updates the student recommendation; ties or insufficient evidence clear the single saved recommendation.
- Registrar-pass guidance suppresses exact performance details before returning to the student.

Examination access requires a desktop or laptop and a configured campus IP/CIDR. Mobile devices retain result viewing. Rule acceptance precedes starting. Tab/window switches, focus loss, refresh and page exit lock the attempt and submit server-saved answers; termination time and reason are recorded. The campus list is intentionally empty until supplied. See `examination-policy.md` for setup and API details.

## Data-store mapping

| ID | Logical store and tables |
|---|---|
| D1 | Student accounts — students |
| D2 | Authentication records — admins, auth_otp_challenges, trusted_devices, — personal_access_tokens, student_password_resets |
| D3 | Question bank — exam_questions |
| D4 | Exam sessions — exam_sessions, exam_session_questions |
| D5 | Exam results and guidance — exam_results, exam_answers |
| D6 | Program catalog — courses |
| D7 | Saved recommendation — recommendations |
| D8 | Activity logs — admin_activity_logs, student_activity_logs |
| D9 | Publication operations — portal_notifications, result_import_previews |

## 0 - Context

Core admissions and program-guidance boundary; transport providers abstracted.

| Source | Data | Destination |
|---|---|---|
| Student | Registration, authentication, profile data, exam answers, interest ratings and requests | 0.0 — Student examination and — program-guidance system |
| 0.0 — Student examination and — program-guidance system | Account status, questions, exam status, official results, guidance and program details | Student |
| Authorized Admin | Authentication, catalog / question / account changes, official scores, result decisions and operations requests | 0.0 — Student examination and — program-guidance system |
| 0.0 — Student examination and — program-guidance system | Authentication status, records, dashboards, logs and operation results | Authorized Admin |
| 0.0 — Student examination and — program-guidance system | Calculated matches, category evidence, interest ratings and program descriptions | Google Gemini |
| Google Gemini | Program explanations and next steps | 0.0 — Student examination and — program-guidance system |

## 1 - Core system

Processes 1.0–7.0; arrows represent data, not execution order. See role views for less crowded detail.

| Source | Data | Destination |
|---|---|---|
| Student | Registration, login, OTP and recovery data | 1.0 — Manage accounts and authentication |
| Authorized Admin | Google sign-in credentials | 1.0 — Manage accounts and authentication |
| D1 | Student accounts — students | Student identity and account status | 1.0 — Manage accounts and authentication |
| 1.0 — Manage accounts and authentication | Registered account and authentication updates | D1 | Student accounts — students |
| D2 | Authentication records — admins, auth_otp_challenges, trusted_devices, — personal_access_tokens, student_password_resets | Admin identity, tokens, OTP and device records | 1.0 — Manage accounts and authentication |
| 1.0 — Manage accounts and authentication | Challenges, tokens, trusted devices and reset records | D2 | Authentication records — admins, auth_otp_challenges, trusted_devices, — personal_access_tokens, student_password_resets |
| 1.0 — Manage accounts and authentication | Authentication activity | D8 | Activity logs — admin_activity_logs, student_activity_logs |
| 1.0 — Manage accounts and authentication | Authentication status, session token, OTP / recovery messages | Student |
| 1.0 — Manage accounts and authentication | Authentication status and session token | Authorized Admin |
| Student | Profile request and edited details | 2.0 — Manage student profiles |
| D1 | Student accounts — students | Profile records | 2.0 — Manage student profiles |
| 2.0 — Manage student profiles | Updated profile | D1 | Student accounts — students |
| 2.0 — Manage student profiles | Profile details and update status | Student |
| Student | Campus/device data, rule acceptance, saved answers and tab-exit report | 3.0 — Conduct and score on-site examination |
| D1 | Student accounts — students | Student exam status | 3.0 — Conduct and score on-site examination |
| D5 | Exam results and guidance — exam_results, exam_answers | Prior attempts and retake eligibility | 3.0 — Conduct and score on-site examination |
| D3 | Question bank — exam_questions | Active questions and scoring keys | 3.0 — Conduct and score on-site examination |
| D4 | Exam sessions — exam_sessions, exam_session_questions | Session, saved answers, deadline and termination state | 3.0 — Conduct and score on-site examination |
| 3.0 — Conduct and score on-site examination | Session, saved answers, termination record and result link | D4 | Exam sessions — exam_sessions, exam_session_questions |
| 3.0 — Conduct and score on-site examination | Scores and category evidence | D5 | Exam results and guidance — exam_results, exam_answers |
| 3.0 — Conduct and score on-site examination | Exam completion and score | D1 | Student accounts — students |
| 3.0 — Conduct and score on-site examination | Access decision, strict rules, questions and session status | Student |
| Authorized Admin | Official scores, import data, approvals, publication and pass decisions | 4.0 — Review and publish official results |
| D5 | Exam results and guidance — exam_results, exam_answers | Exam results and official state | 4.0 — Review and publish official results |
| 4.0 — Review and publish official results | Official scores, decisions and publication metadata | D5 | Exam results and guidance — exam_results, exam_answers |
| D1 | Student accounts — students | Student identity | 4.0 — Review and publish official results |
| D9 | Publication operations — portal_notifications, result_import_previews | Import previews and notification state | 4.0 — Review and publish official results |
| 4.0 — Review and publish official results | Import previews and result notifications | D9 | Publication operations — portal_notifications, result_import_previews |
| 4.0 — Review and publish official results | Result administration activity | D8 | Activity logs — admin_activity_logs, student_activity_logs |
| Student | Result / notification request and read acknowledgement | 4.0 — Review and publish official results |
| 4.0 — Review and publish official results | Official result, publication status and notifications | Student |
| 4.0 — Review and publish official results | Result review data and operation status | Authorized Admin |
| Student | Guidance request; interest ratings | 5.0 — Generate program guidance |
| D5 | Exam results and guidance — exam_results, exam_answers | Latest result, category scores, saved interests and guidance | 5.0 — Generate program guidance |
| D6 | Program catalog — courses | Active recommendable programs and weights | 5.0 — Generate program guidance |
| 5.0 — Generate program guidance | Calculated matches, category evidence, interests, program descriptions | Google Gemini |
| Google Gemini | Program explanations and next steps | 5.0 — Generate program guidance |
| 5.0 — Generate program guidance | Saved interests and guidance payload | D5 | Exam results and guidance — exam_results, exam_answers |
| 5.0 — Generate program guidance | Unique top recommendation or removal; alternatives | D7 | Saved recommendation — recommendations |
| 5.0 — Generate program guidance | Recommended program code or cleared value | D1 | Student accounts — students |
| 5.0 — Generate program guidance | Ranked programs, explanations or guidance status | Student |
| Student | Program catalog request | 6.0 — Manage and browse program catalog |
| Authorized Admin | Program details and catalog changes | 6.0 — Manage and browse program catalog |
| D6 | Program catalog — courses | Program records | 6.0 — Manage and browse program catalog |
| 6.0 — Manage and browse program catalog | Updated catalog | D6 | Program catalog — courses |
| 6.0 — Manage and browse program catalog | Program descriptions, subjects and career paths | Student |
| 6.0 — Manage and browse program catalog | Catalog records and update status | Authorized Admin |
| 6.0 — Manage and browse program catalog | Catalog activity | D8 | Activity logs — admin_activity_logs, student_activity_logs |
| Authorized Admin | Question, account and operations requests / changes | 7.0 — Manage questions, accounts and operations |
| D3 | Question bank — exam_questions | Question records | 7.0 — Manage questions, accounts and operations |
| 7.0 — Manage questions, accounts and operations | Question updates | D3 | Question bank — exam_questions |
| D1 | Student accounts — students | Student records | 7.0 — Manage questions, accounts and operations |
| 7.0 — Manage questions, accounts and operations | Student account status | D1 | Student accounts — students |
| D2 | Authentication records — admins, auth_otp_challenges, trusted_devices, — personal_access_tokens, student_password_resets | Admin accounts | 7.0 — Manage questions, accounts and operations |
| 7.0 — Manage questions, accounts and operations | Admin invitations and account updates | D2 | Authentication records — admins, auth_otp_challenges, trusted_devices, — personal_access_tokens, student_password_resets |
| D8 | Activity logs — admin_activity_logs, student_activity_logs | Activity records | 7.0 — Manage questions, accounts and operations |
| 7.0 — Manage questions, accounts and operations | Administrative activity | D8 | Activity logs — admin_activity_logs, student_activity_logs |
| D5 | Exam results and guidance — exam_results, exam_answers | Exam / result summaries | 7.0 — Manage questions, accounts and operations |
| D9 | Publication operations — portal_notifications, result_import_previews | Notification delivery state | 7.0 — Manage questions, accounts and operations |
| 7.0 — Manage questions, accounts and operations | Notification retry updates | D9 | Publication operations — portal_notifications, result_import_previews |
| 7.0 — Manage questions, accounts and operations | Question / account records, dashboards, logs and operation status | Authorized Admin |

## 2 - Recommendation detail

Decomposition of process 5.0; external inputs / outputs match Level 1.

| Source | Data | Destination |
|---|---|---|
| Student | Guidance request; interest ratings | 5.1 — Load exam evidence and — validate requested interest ratings |
| D5 | Exam results and guidance — exam_results, exam_answers | Latest result, category scores, saved interests and guidance | 5.1 — Load exam evidence and — validate requested interest ratings |
| 5.1 — Load exam evidence and — validate requested interest ratings | Validated evidence, interests and request mode | 5.2 — Calculate / reuse program matches — 80% exam + 20% interests when rated |
| D6 | Program catalog — courses | Active recommendable programs and weights | 5.2 — Calculate / reuse program matches — 80% exam + 20% interests when rated |
| 5.1 — Load exam evidence and — validate requested interest ratings | Missing exam or validation status | 5.5 — Return student guidance — Apply registrar-pass redaction |
| 5.2 — Calculate / reuse program matches — 80% exam + 20% interests when rated | Calculated matches, category evidence, interests and catalog | 5.3 — Obtain and validate — AI explanations when requested |
| 5.3 — Obtain and validate — AI explanations when requested | Calculated matches, category evidence, interests, program descriptions | Google Gemini |
| Google Gemini | Program explanations and next steps | 5.3 — Obtain and validate — AI explanations when requested |
| 5.3 — Obtain and validate — AI explanations when requested | Generated guidance or AI-unavailable fallback | 5.4 — Persist generated guidance — and maintain top recommendation |
| 5.2 — Calculate / reuse program matches — 80% exam + 20% interests when rated | Cached / read-only matches or guidance status | 5.5 — Return student guidance — Apply registrar-pass redaction |
| 5.2 — Calculate / reuse program matches — 80% exam + 20% interests when rated | Generated insufficient-evidence status | 5.4 — Persist generated guidance — and maintain top recommendation |
| 5.4 — Persist generated guidance — and maintain top recommendation | Saved interests and guidance payload | D5 | Exam results and guidance — exam_results, exam_answers |
| 5.4 — Persist generated guidance — and maintain top recommendation | Unique top recommendation or removal; alternatives | D7 | Saved recommendation — recommendations |
| 5.4 — Persist generated guidance — and maintain top recommendation | Recommended program code or cleared value | D1 | Student accounts — students |
| 5.4 — Persist generated guidance — and maintain top recommendation | Guidance payload and registrar-pass state | 5.5 — Return student guidance — Apply registrar-pass redaction |
| 5.5 — Return student guidance — Apply registrar-pass redaction | Ranked programs, explanations or guidance status | Student |

## 3 - Student view

Role-focused view of Level 1 (not another decomposition level). Staff writes omitted; process numbers retained.

| Source | Data | Destination |
|---|---|---|
| Student | Registration, login, OTP and recovery data | 1.0 — Manage accounts and authentication |
| D1 | Student accounts — students | Student identity and account status | 1.0 — Manage accounts and authentication |
| 1.0 — Manage accounts and authentication | Registered account and authentication updates | D1 | Student accounts — students |
| D2 | Authentication records — admins, auth_otp_challenges, trusted_devices, — personal_access_tokens, student_password_resets | Admin identity, tokens, OTP and device records | 1.0 — Manage accounts and authentication |
| 1.0 — Manage accounts and authentication | Challenges, tokens, trusted devices and reset records | D2 | Authentication records — admins, auth_otp_challenges, trusted_devices, — personal_access_tokens, student_password_resets |
| 1.0 — Manage accounts and authentication | Authentication activity | D8 | Activity logs — admin_activity_logs, student_activity_logs |
| 1.0 — Manage accounts and authentication | Authentication status, session token, OTP / recovery messages | Student |
| Student | Profile request and edited details | 2.0 — Manage student profiles |
| D1 | Student accounts — students | Profile records | 2.0 — Manage student profiles |
| 2.0 — Manage student profiles | Updated profile | D1 | Student accounts — students |
| 2.0 — Manage student profiles | Profile details and update status | Student |
| Student | Campus/device data, rule acceptance, saved answers and tab-exit report | 3.0 — Conduct and score on-site examination |
| D1 | Student accounts — students | Student exam status | 3.0 — Conduct and score on-site examination |
| D5 | Exam results and guidance — exam_results, exam_answers | Prior attempts and retake eligibility | 3.0 — Conduct and score on-site examination |
| D3 | Question bank — exam_questions | Active questions and scoring keys | 3.0 — Conduct and score on-site examination |
| D4 | Exam sessions — exam_sessions, exam_session_questions | Session, saved answers, deadline and termination state | 3.0 — Conduct and score on-site examination |
| 3.0 — Conduct and score on-site examination | Session, saved answers, termination record and result link | D4 | Exam sessions — exam_sessions, exam_session_questions |
| 3.0 — Conduct and score on-site examination | Scores and category evidence | D5 | Exam results and guidance — exam_results, exam_answers |
| 3.0 — Conduct and score on-site examination | Exam completion and score | D1 | Student accounts — students |
| 3.0 — Conduct and score on-site examination | Access decision, strict rules, questions and session status | Student |
| D5 | Exam results and guidance — exam_results, exam_answers | Exam results and official state | 4.0 — Review and publish official results |
| D1 | Student accounts — students | Student identity | 4.0 — Review and publish official results |
| D9 | Publication operations — portal_notifications, result_import_previews | Import previews and notification state | 4.0 — Review and publish official results |
| 4.0 — Review and publish official results | Import previews and result notifications | D9 | Publication operations — portal_notifications, result_import_previews |
| Student | Result / notification request and read acknowledgement | 4.0 — Review and publish official results |
| 4.0 — Review and publish official results | Official result, publication status and notifications | Student |
| Student | Guidance request; interest ratings | 5.0 — Generate program guidance |
| D5 | Exam results and guidance — exam_results, exam_answers | Latest result, category scores, saved interests and guidance | 5.0 — Generate program guidance |
| D6 | Program catalog — courses | Active recommendable programs and weights | 5.0 — Generate program guidance |
| 5.0 — Generate program guidance | Calculated matches, category evidence, interests, program descriptions | Google Gemini |
| Google Gemini | Program explanations and next steps | 5.0 — Generate program guidance |
| 5.0 — Generate program guidance | Saved interests and guidance payload | D5 | Exam results and guidance — exam_results, exam_answers |
| 5.0 — Generate program guidance | Unique top recommendation or removal; alternatives | D7 | Saved recommendation — recommendations |
| 5.0 — Generate program guidance | Recommended program code or cleared value | D1 | Student accounts — students |
| 5.0 — Generate program guidance | Ranked programs, explanations or guidance status | Student |
| Student | Program catalog request | 6.0 — Manage and browse program catalog |
| D6 | Program catalog — courses | Program records | 6.0 — Manage and browse program catalog |
| 6.0 — Manage and browse program catalog | Program descriptions, subjects and career paths | Student |

## 4 - Admin view

Role-focused view of Level 1. Actions depend on admissions_staff, exam_manager, super_admin or viewer permissions.

| Source | Data | Destination |
|---|---|---|
| Authorized Admin | Google sign-in credentials | 1.0 — Manage accounts and authentication |
| D2 | Authentication records — admins, auth_otp_challenges, trusted_devices, — personal_access_tokens, student_password_resets | Admin identity, tokens, OTP and device records | 1.0 — Manage accounts and authentication |
| 1.0 — Manage accounts and authentication | Challenges, tokens, trusted devices and reset records | D2 | Authentication records — admins, auth_otp_challenges, trusted_devices, — personal_access_tokens, student_password_resets |
| 1.0 — Manage accounts and authentication | Authentication activity | D8 | Activity logs — admin_activity_logs, student_activity_logs |
| 1.0 — Manage accounts and authentication | Authentication status and session token | Authorized Admin |
| Authorized Admin | Official scores, import data, approvals, publication and pass decisions | 4.0 — Review and publish official results |
| D5 | Exam results and guidance — exam_results, exam_answers | Exam results and official state | 4.0 — Review and publish official results |
| 4.0 — Review and publish official results | Official scores, decisions and publication metadata | D5 | Exam results and guidance — exam_results, exam_answers |
| D1 | Student accounts — students | Student identity | 4.0 — Review and publish official results |
| D9 | Publication operations — portal_notifications, result_import_previews | Import previews and notification state | 4.0 — Review and publish official results |
| 4.0 — Review and publish official results | Import previews and result notifications | D9 | Publication operations — portal_notifications, result_import_previews |
| 4.0 — Review and publish official results | Result administration activity | D8 | Activity logs — admin_activity_logs, student_activity_logs |
| 4.0 — Review and publish official results | Result review data and operation status | Authorized Admin |
| Authorized Admin | Program details and catalog changes | 6.0 — Manage and browse program catalog |
| D6 | Program catalog — courses | Program records | 6.0 — Manage and browse program catalog |
| 6.0 — Manage and browse program catalog | Updated catalog | D6 | Program catalog — courses |
| 6.0 — Manage and browse program catalog | Catalog records and update status | Authorized Admin |
| 6.0 — Manage and browse program catalog | Catalog activity | D8 | Activity logs — admin_activity_logs, student_activity_logs |
| Authorized Admin | Question, account and operations requests / changes | 7.0 — Manage questions, accounts and operations |
| D3 | Question bank — exam_questions | Question records | 7.0 — Manage questions, accounts and operations |
| 7.0 — Manage questions, accounts and operations | Question updates | D3 | Question bank — exam_questions |
| D1 | Student accounts — students | Student records | 7.0 — Manage questions, accounts and operations |
| 7.0 — Manage questions, accounts and operations | Student account status | D1 | Student accounts — students |
| D2 | Authentication records — admins, auth_otp_challenges, trusted_devices, — personal_access_tokens, student_password_resets | Admin accounts | 7.0 — Manage questions, accounts and operations |
| 7.0 — Manage questions, accounts and operations | Admin invitations and account updates | D2 | Authentication records — admins, auth_otp_challenges, trusted_devices, — personal_access_tokens, student_password_resets |
| D8 | Activity logs — admin_activity_logs, student_activity_logs | Activity records | 7.0 — Manage questions, accounts and operations |
| 7.0 — Manage questions, accounts and operations | Administrative activity | D8 | Activity logs — admin_activity_logs, student_activity_logs |
| D5 | Exam results and guidance — exam_results, exam_answers | Exam / result summaries | 7.0 — Manage questions, accounts and operations |
| D9 | Publication operations — portal_notifications, result_import_previews | Notification delivery state | 7.0 — Manage questions, accounts and operations |
| 7.0 — Manage questions, accounts and operations | Notification retry updates | D9 | Publication operations — portal_notifications, result_import_previews |
| 7.0 — Manage questions, accounts and operations | Question / account records, dashboards, logs and operation status | Authorized Admin |

## Source references

- `backend/routes/api.php`: exposed student/admin operations and role restrictions.
- `backend/app/Services/ExamSessionService.php`: exam assignment, saved sessions, scoring and eligibility.
- `backend/app/Services/ProgramMatcher.php`: deterministic ranking.
- `backend/app/Services/ProgramRecommendationAI.php`: Gemini request and explanation validation.
- `backend/app/Http/Controllers/ResultController.php`: reads, generation, caching, persistence and redaction.
- `backend/app/Http/Controllers/ResultReviewController.php`: official result approval, publication and correction.
- `backend/app/Http/Controllers/AdminManagementController.php`: catalog, question, account and registrar-pass operations.
- `backend/database/migrations/`: actual storage structures.

