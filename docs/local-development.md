# Run the portals locally on Windows

Start MySQL in XAMPP. Dependencies must already be installed in `backend/vendor`, `frontend/node_modules` and `admin/node_modules`.

From the project root, start the backend in PowerShell:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\tools\start-backend.ps1
```

This uses PHP 8.4+ (the project-local runtime at `backend/storage/app/private/php84/php.exe` when available, then the temporary runtime, then a compatible PHP on PATH). The project-local runtime is ignored by Git. To select an installed runtime explicitly, pass `-PhpPath 'C:\path\to\php.exe'`. It enables the required extensions and supplies the Windows trusted root certificates to PHP for HTTPS and email. Certificate verification remains enabled. The public CA bundle is saved to the ignored `backend/storage/app/private/local-ca-bundle.pem` file; private keys and credentials are never exported.

If both portals show **Network Error**, check that this backend command is still running on port 8000. Loading the Vite pages alone does not start the API. The PHP 8.2 runtime bundled with XAMPP cannot run the current backend dependencies; use the startup script above.

In a second terminal:

```powershell
cd frontend
npm.cmd run dev
```

Open <http://localhost:5173/cdm-career-recommendation-system/>.

In a third terminal:

```powershell
cd admin
npm.cmd run dev
```

Open <http://localhost:5174/cdm-career-recommendation-system/admin/>.

Keep these terminals open and use Ctrl+C to stop each server. XAMPP Apache is not needed for these commands. The database settings in `backend/.env` must point to the running MySQL instance.

## Google sign-in

The Google client ID configured in each frontend must match `GOOGLE_CLIENT_ID` in the backend. The Google OAuth client must allow the exact local origins, usually `http://localhost:5173` and `http://localhost:5174`. Different hostnames or ports are separate origins. Restart Vite after frontend environment changes.

Both portals use the same server-side signature, issuer, expiry and audience validation. Admin accounts still require an invitation for their exact verified Google email, and suspended accounts stay blocked. New student accounts load database defaults before the active-account check, so the first successful Google sign-in no longer incorrectly reports that a newly created student is inactive.

If PHP reports `cURL error 60`, prepare the local CA bundle without launching another server:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\tools\start-backend.ps1 -PrepareOnly
```

Google verification automatically uses that local bundle on Windows. If configuration was cached, clear it using the same PHP 8.4 runtime or restart through the startup script, which clears it automatically. To use a different administrator-managed PEM bundle, set `GOOGLE_CA_BUNDLE` in the backend environment. Other platforms use their normal system trust unless explicitly configured.

Google/network/certificate service failures return HTTP 503 with a retry message. Invalid credentials return HTTP 401; invitation and account restrictions return HTTP 403. Invalid admin login credentials stay on the admin login page so the user can retry.

The portal logos and favicons use Vite's deployment base, so they load from the correct folder in local development and static hosting. Reload existing tabs after this update to fetch the updated favicon.

## Examination configuration

See [examination-policy.md](examination-policy.md) for the migration and campus network settings. For isolated local testing only, `EXAM_ALLOWED_NETWORKS=127.0.0.1,::1` permits loopback access. An empty list blocks exam-taking while leaving results accessible.

## Verification

`GoogleLoginFlowTest` runs signed test credentials through the real Google verification SDK against a local HTTP signing-key fixture and an isolated in-memory database. It covers student creation, invited admin login, role restrictions, invalid/expired credentials and upstream service failure. It needs Node.js and PHP with OpenSSL, mbstring and SQLite extensions. The fixture requires no real Google account or credentials.

`frontend/tests/browser/login.spec.mjs` checks the student Google and email/OTP screens, admin retry/navigation behavior, and actual logo/favicon responses with simulated identity and backend responses. A real account's interactive Google consent still requires the user's browser.
