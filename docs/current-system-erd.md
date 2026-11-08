# Current system ERD

Open `current-system-erd.drawio` in Draw.io. All 17 tables are on one editable black-and-white page. Examination termination fields updated 26 October 2026; other tables based on repository migrations as of 27 September 2026; the live database was not inspected.

PK = primary key; FK = foreign key; UQ = unique; `?` = nullable. Types are only highlighted for UUID and JSON fields. Solid connectors are foreign keys; dashed connectors are application associations.

## Relationships

| Parent | Child | Child reference | Parent per child | Children per parent | Enforcement |
|---|---|---|---|---|---|
| students | exam_results | student_id | 1 | 0..many | Foreign key |
| students | exam_sessions | student_id | 1 | 0..many | Foreign key |
| exam_results | exam_sessions | exam_result_id | 0..1 | 0..many | Foreign key |
| exam_sessions | exam_session_questions | exam_session_id | 1 | 0..many | Foreign key |
| exam_questions | exam_session_questions | exam_question_id | 1 | 0..many | Foreign key |
| exam_results | exam_answers | exam_result_id | 1 | 0..many | Foreign key |
| exam_questions | exam_answers | exam_question_id | 1 | 0..many | Foreign key |
| students | recommendations | student_id | 1 | 0..1 | Foreign key |
| courses | recommendations | course_id | 1 | 0..many | Foreign key |
| admins | exam_results | approved_by / published_by / registrar_pass_by | 0..1 | 0..many | Foreign key |
| admins | admin_activity_logs | admin_id | 0..1 | 0..many | Foreign key |
| admins | result_import_previews | admin_id | 1 | 0..many | Foreign key |
| students | auth_otp_challenges | student_id | 0..1 | 0..many | Foreign key |
| students | trusted_devices | student_id | 1 | 0..many | Foreign key |
| students | student_activity_logs | student_id | 0..1 | 0..many | Foreign key |
| students | portal_notifications | student_id | 1 | 0..many | Foreign key |
| students | student_password_resets | email (logical only) | 1 | 0..1 | Application only |
| students | personal_access_tokens | tokenable (polymorphic) | 1 | 0..many | Application only |
| admins | personal_access_tokens | tokenable (polymorphic) | 1 | 0..many | Application only |

## Schema details

- The three admin result references are separate nullable foreign keys, grouped in the diagram for readability.
- The database does not enforce uniqueness on `exam_sessions.exam_result_id`. Normal application behavior links a completed session to its result; the ERD reflects the actual constraint.
- A personal access token belongs to one student **or** one admin using a polymorphic association. Neither is a declared foreign key.
- Password reset email uses application lookup, with no declared student foreign key.
- Generic log subject references are not declared foreign keys.
- Two exam attempts is an application limit. It does not change database one-to-many cardinality.
- `full_name`, `official_outcome`, `attempt_number` on results, and `result_version` are computed values, not stored columns. Sessions do store `attempt_number`.
- No separate student profile, career, or evaluation table exists.

## Fields

### students

- PK id
- first_name
- last_name
- UQ email
- phone ?
- password ?
- UQ google_id ?
- UQ student_number
- admission_year
- date_of_birth ?
- profile_picture ?
- is_google_account
- account_status
- exam_taken
- exam_score ?
- recommended_program ?
- email_verified_at ?
- remember_token ?
- created_at
- updated_at

### exam_results

- PK id
- FK student_id
- total_score
- percentage
- time_spent
- exam_date
- is_passed
- category_scores (JSON) ?
- official_score ?
- official_status
- registrar_remarks ?
- FK approved_by ?
- approved_at ?
- FK published_by ?
- published_at ?
- category_maximums (JSON) ?
- career_interests (JSON) ?
- recommendation_payload (JSON) ?
- registrar_pass
- registrar_pass_reason ?
- FK registrar_pass_by ?
- registrar_pass_at ?
- created_at
- updated_at

### exam_sessions

- PK id (UUID)
- FK student_id
- attempt_number
- bank_version
- questions (JSON)
- answers (JSON)
- revision
- position
- started_at
- deadline
- terminated_at ?
- termination_reason ?
- FK exam_result_id ?
- created_at
- updated_at
- UQ (student_id, attempt_number)

### exam_session_questions

- PK, FK exam_session_id (UUID)
- PK, FK exam_question_id
- Composite PK: both fields

### exam_questions

- PK id
- question_number
- question_text
- category
- option_a
- option_b
- option_c
- option_d
- correct_answer
- difficulty_level
- is_active
- created_at
- updated_at

### exam_answers

- PK id
- FK exam_result_id
- FK exam_question_id
- student_answer ?
- is_correct
- created_at
- updated_at

### recommendations

- PK id
- FK, UQ student_id
- FK course_id
- confidence_score
- reasoning
- alternative_recommendations (JSON) ?
- created_at
- updated_at

### courses

- PK id
- UQ code
- name
- description
- duration
- tuition_fee ?
- ideal_score_range ?
- subjects (JSON) ?
- institute ?
- career_paths (JSON) ?
- image_path ?
- image_data ?
- image_mime_type ?
- program_type
- is_active
- is_recommendable
- display_order
- recommendation_profile (JSON) ?
- created_at
- updated_at

### admins

- PK id
- name
- UQ email
- UQ google_id ?
- profile_picture ?
- role
- status
- email_verified_at ?
- last_login_at ?
- created_at
- updated_at

### admin_activity_logs

- PK id
- FK admin_id ?
- action
- subject_type ?
- subject_id ?
- details (JSON) ?
- ip_address ?
- created_at
- updated_at

### result_import_previews

- PK id (UUID)
- FK admin_id
- rows (JSON)
- expires_at
- created_at
- updated_at

### auth_otp_challenges

- PK id (UUID)
- FK student_id ?
- email
- purpose
- registration_payload ?
- otp_hash
- attempts_remaining
- resend_count
- expires_at
- last_sent_at
- consumed_at ?
- created_at
- updated_at

### trusted_devices

- PK id
- FK student_id
- UQ token_hash
- user_agent ?
- last_used_at ?
- expires_at
- created_at
- updated_at

### student_activity_logs

- PK id
- FK student_id ?
- action
- auth_method ?
- ip_address ?
- user_agent ?
- created_at
- updated_at

### portal_notifications

- PK id
- FK student_id
- message
- link
- read_at ?
- emailed_at ?
- email_attempts
- email_retry_at ?
- created_at
- updated_at

### student_password_resets

- PK email
- token_hash
- expires_at

### personal_access_tokens

- PK id
- tokenable_type
- tokenable_id
- name
- UQ token
- abilities ?
- last_used_at ?
- expires_at ?
- created_at
- updated_at

## Source

The table definitions and changes are in `backend/database/migrations/`. Application relationships and computed values are in `backend/app/Models/`.

