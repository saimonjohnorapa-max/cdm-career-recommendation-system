# On-site entrance examination policy

Updated 26 October 2026. This describes the local implementation; it does not establish that a deployed server has been migrated or configured.

## Required configuration

Run the new migration with the project's PHP 8.4+ runtime before serving this change:

```sh
cd backend
php artisan migrate
```

Set `EXAM_ALLOWED_NETWORKS` in the backend environment to the campus examination network's public IP addresses or CIDR ranges, separated by commas. It is intentionally empty until the school supplies them. An empty list denies all exam-taking, including localhost. For isolated local development, explicitly use `127.0.0.1,::1`; do not use those addresses as the production campus list.

If a reverse proxy sits in front of the backend, set `EXAM_TRUSTED_PROXIES` to its actual IP addresses or CIDRs. The examination policy ignores forwarding headers from other peers, even though the portal's general proxy middleware trusts all peers. A trusted proxy must append or replace client forwarding headers correctly. Never use a wildcard or a blanket internet range. Refresh Laravel's configuration cache through the deployment's normal configuration workflow after changing these values.

Mobile phones and tablets cannot load exam questions, start/resume an attempt, save answers or submit through the exam-taking routes. Device checks use browser identification, mobile client hints, and client-side touch detection for desktop-mode iPads. A campus IP rule establishes network access, not physical presence or approved hardware. Proctors and controlled examination computers remain necessary; browser identification can be spoofed.

## Examinee rules and session behavior

- Read and acknowledge the strict rules before starting. The server requires acknowledgment too.
- Use a desktop/laptop at the designated examination site and network.
- Keep the exam tab active. Tab/window changes, browser focus loss, application switches, minimization, refresh, page close and navigation away end the attempt. Browser controls that take focus can also trigger this strict policy.
- Termination locks the interface immediately and finalizes server-saved answers under the existing student/session database locks. Unanswered questions score zero. Unsaved local changes are not included in a terminated attempt.
- Termination counts as a completed attempt. Existing Registrar publication and retake eligibility rules still apply; termination does not create an extra attempt.
- `terminated_at` and `termination_reason` remain on `exam_sessions`; the result status exposes them so a reload still explains why the attempt ended.
- Network failures leave a per-student pending termination marker. The browser retries on focus, reconnection or reopening the examination page, before loading questions. A small keepalive request is used during exit. Abrupt power loss, blocked scripts, tampered browser storage, or an offline user returning through another browser cannot guarantee immediate server notification. The existing server deadline still finalizes received answers.
- Result viewing and other portal pages remain available on mobile and off-site. These restrictions apply to examination-taking only.

The browser exit handling uses [visibility changes](https://developer.mozilla.org/en-US/docs/Web/API/Document/visibilitychange_event) and [keepalive requests](https://developer.mozilla.org/en-US/docs/Web/API/Request/keepalive).

## API contract changes

All routes below require an authenticated active student. Existing session ownership checks still apply.

| Request | Contract |
| --- | --- |
| `GET /api/exam/access` | `200 {allowed: boolean, message: string or null}`. No questions or session are disclosed. |
| `GET /api/exam/questions`, `GET /api/exam/session`, `POST /api/exam/session`, `PUT /api/exam/session`, `POST /api/exam/submit` | Mobile/off-site requests return `403 {exam_access_denied: true, message: string}` before accessing examination data. |
| `POST /api/exam/session` | Requires `{rules_accepted: true}`. Missing or rejected acknowledgment returns `422`. Other attempt-limit behavior is unchanged. |
| `POST /api/exam/session/terminate` | Requires `{session_id: UUID, reason: "tab_hidden" or "focus_lost" or "page_left"}`. Finalizes only the student's own session; no revision or answers accepted. Returns the session payload with `exam_completed`, `terminated_at`, and `termination_reason`. Repeated requests are idempotent; another student's session returns `404`. This endpoint permits mobile/off-site finalization so network/device changes cannot prevent closing an attempt. |
| `GET /api/exam/result` | Still available on mobile/off-site; additionally returns nullable `terminated_at` and `termination_reason` for the latest result. |

Session payloads also include the two nullable termination fields. A completed session cannot accept further answer changes, including requests racing with termination. Expiry that completed the session before the termination report retains its normal deadline completion.
