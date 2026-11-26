# Entrance examination policy

Updated 26 November 2026. The restored exam policy uses the existing database schema and session records.

## Required configuration

For installations that have not applied the existing unique-bank migration, run migrations with the project's PHP 8.4+ runtime:

```sh
cd backend
php artisan migrate
```

The unique-bank migration deactivates repeated active question text while retaining question IDs and historical references, then fills each topic to at least 20 unique questions from the 100-question starter bank. Existing unique active questions are retained. New empty banks also use the complete starter bank when seeded. The backend Docker startup runs migrations automatically.

Signed-in active students can take the examination on a desktop or laptop from any network. No campus IP allowlist or examination proxy configuration is required. The former `EXAM_ALLOWED_NETWORKS` and `EXAM_TRUSTED_PROXIES` settings are no longer used and can be removed from hosting environment settings.

Mobile phones and tablets can view exam results. Taking or managing an attempt requires a desktop or laptop. Device checks use browser identification, mobile client hints, and client-side touch detection for desktop-mode iPads. Browser identification can be spoofed. Active attempts can still be resumed on a desktop or laptop within their original deadline.

## Examinee rules and session behavior

- New attempts select 20 unique active questions per topic. Duplicate text is compared without case or spacing differences and without legacy `(Practice variant N)` suffixes. If a topic has fewer than 20 unique questions, starting returns `422` so the exam manager can complete the bank; duplicate rows do not fill the shortage. Instructions use the same unique question counts. Existing saved sessions retain their original question snapshots.
- Read and acknowledge the strict rules before starting. The server requires acknowledgment too.
- Use a desktop/laptop with a stable internet connection.
- Keep the exam tab active. Tab/window changes, browser focus loss, application switches, minimization, refresh, page close and navigation away end the attempt. Browser controls that take focus can also trigger this strict policy.
- Termination locks the interface immediately and finalizes server-saved answers under the existing student/session database locks. Unanswered questions score zero. Unsaved local changes are not included in a terminated attempt.
- Termination counts as a completed attempt. Existing Registrar publication and retake eligibility rules still apply; termination does not create an extra attempt.
- `terminated_at` and `termination_reason` remain on `exam_sessions`; the result status exposes them so a reload still explains why the attempt ended.
- Network failures leave a per-student pending termination marker. The browser retries on focus, reconnection or reopening the examination page, before loading questions. A small keepalive request is used during exit. Abrupt power loss, blocked scripts, tampered browser storage, or an offline user returning through another browser cannot guarantee immediate server notification. The existing server deadline still finalizes received answers.
- Result viewing and other portal pages remain available on mobile. The device restriction applies to examination-taking only.

The browser exit handling uses [visibility changes](https://developer.mozilla.org/en-US/docs/Web/API/Document/visibilitychange_event) and [keepalive requests](https://developer.mozilla.org/en-US/docs/Web/API/Request/keepalive).

## API contract changes

All routes below require an authenticated active student. Existing session ownership checks still apply.

| Request | Contract |
| --- | --- |
| `GET /api/exam/access` | `200 {allowed: boolean, message: string or null}`. No questions or session are disclosed. |
| `GET /api/exam/questions`, `GET /api/exam/session`, `POST /api/exam/session`, `PUT /api/exam/session`, `POST /api/exam/session/terminate`, `POST /api/exam/submit` | Mobile requests return `403 {exam_access_denied: true, message: string}` before accessing examination data. Desktop/laptop requests are allowed from any network. |
| `POST /api/exam/session` | Requires `{rules_accepted: true}`. Missing or rejected acknowledgment returns `422`. Other attempt-limit behavior is unchanged. |
| `POST /api/exam/session/terminate` | Requires `{session_id: UUID, reason: "tab_hidden" or "focus_lost" or "page_left"}`. Finalizes the student's own session using saved answers. Returns the session payload with `exam_completed`, `terminated_at`, and `termination_reason`. Repeated requests are idempotent. Another student's session returns `404`. |
| `GET /api/exam/result` | Still available on mobile/off-site; additionally returns nullable `terminated_at` and `termination_reason` for the latest result. |

Session payloads also include the two nullable termination fields. A completed session cannot accept further answer changes, including requests racing with termination. Expiry that completed the session before the termination report retains its normal deadline completion.
