# Entrance examination policy

Updated 8 November 2026. This describes the local implementation; it does not establish that a deployed server has been migrated or configured.

## Required configuration

Run the new migration with the project's PHP 8.4+ runtime before serving this change:

```sh
cd backend
php artisan migrate
```

Signed-in active students can take the examination on a desktop or laptop from any network. No campus IP allowlist or examination proxy configuration is required. The former `EXAM_ALLOWED_NETWORKS` and `EXAM_TRUSTED_PROXIES` settings are no longer used and can be removed from hosting environment settings.

Mobile phones and tablets cannot load exam questions, start/resume an attempt, save answers or submit through the exam-taking routes. Device checks use browser identification, mobile client hints, and client-side touch detection for desktop-mode iPads. Browser identification can be spoofed.

## Examinee rules and session behavior

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
| `GET /api/exam/questions`, `GET /api/exam/session`, `POST /api/exam/session`, `PUT /api/exam/session`, `POST /api/exam/submit` | Mobile requests return `403 {exam_access_denied: true, message: string}` before accessing examination data. Desktop/laptop requests are allowed from any network. |
| `POST /api/exam/session` | Requires `{rules_accepted: true}`. Missing or rejected acknowledgment returns `422`. Other attempt-limit behavior is unchanged. |
| `POST /api/exam/session/terminate` | Requires `{session_id: UUID, reason: "tab_hidden" or "focus_lost" or "page_left"}`. Finalizes only the student's own session; no revision or answers accepted. Returns the session payload with `exam_completed`, `terminated_at`, and `termination_reason`. Repeated requests are idempotent; another student's session returns `404`. This endpoint permits mobile finalization so device changes cannot prevent closing an attempt. |
| `GET /api/exam/result` | Still available on mobile/off-site; additionally returns nullable `terminated_at` and `termination_reason` for the latest result. |

Session payloads also include the two nullable termination fields. A completed session cannot accept further answer changes, including requests racing with termination. Expiry that completed the session before the termination report retains its normal deadline completion.
