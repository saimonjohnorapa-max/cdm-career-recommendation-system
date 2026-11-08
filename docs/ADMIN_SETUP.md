# CDM Administration Portal

The administrator interface is a separate Vue application in `admin/`. It uses the same Laravel API and MySQL database as the student website and mobile PWA.

## First-time setup

1. Start MySQL and configure `backend/.env`.
2. Create the admin database tables:

```bash
cd backend
php artisan migrate
```

3. Register the first approved Google account:

```bash
php artisan admin:invite admin@gmail.com --name="System Administrator" --role=super_admin
```

The command does not store a password. The exact invited address must sign in through Google. A personal Gmail or Google Workspace address can be invited.

4. Configure and start the admin frontend:

```bash
cd admin
copy .env.example .env
npm install
npm run dev
```

The admin portal runs at `http://localhost:5174`; the student site remains at `http://localhost:5173`.

## Google configuration

Configure `admin/.env` with the same Google OAuth web Client ID used by the backend:

```env
VITE_API_URL=http://localhost:8000/api
VITE_GOOGLE_CLIENT_ID=your_google_web_client_id
```

Add these local URLs to the Google OAuth client's **Authorized JavaScript origins**:

- `http://localhost:5173`
- `http://localhost:5174`

Add both production HTTPS origins when deploying. Never put the Google Client Secret in either frontend.

## Local development

Use three terminals:

```bash
# Terminal 1
cd backend
php artisan serve

# Terminal 2
cd frontend
npm run dev

# Terminal 3
cd admin
npm run dev
```

## Roles

- `super_admin`: full access, invitations, roles, and activity logs
- `admissions_staff`: student account status and academic program management
- `exam_manager`: entrance-exam question management
- `viewer`: read-only access to operational information

Every mutation is authorized by Laravel middleware and recorded in the activity log.

## Production

Deploy `admin/dist` to a separate HTTPS origin such as `https://admin.example.edu`. Include the student and admin origins in `backend/.env`:

```env
CORS_ALLOWED_ORIGINS=https://www.example.edu,https://admin.example.edu
```

Set the production API URL, then build:

```bash
cd admin
npm run build
```

Configure the web server to route unknown admin frontend paths back to `index.html` for Vue Router.

### Create the first live admin without a hosting shell

The backend Dockerfile can create an invitation during startup, after database
migrations. This uses the deployed backend's database, not your local database.

1. Push the updated `backend/Dockerfile` to the branch deployed by Render.
2. Clear any custom **Docker Command** in the Render service settings so the
   service uses the Dockerfile's default command.
3. In the Render service's **Environment** settings, add
   `ADMIN_BOOTSTRAP_EMAIL` with the exact Google email to invite, without quotes.
4. Save and redeploy. Look for `Admin invitation ready for ...` in the logs,
   then sign in with that Google account.
5. Remove `ADMIN_BOOTSTRAP_EMAIL` from Render after successful setup and
   redeploy. The invitation remains in the database. Leaving this variable set
   runs `admin:invite` on every startup and assigns that email the `super_admin`
   role again.

If invitation creation fails, startup stops and the error appears in the logs.
If `ADMIN_BOOTSTRAP_EMAIL` is unset or empty, startup skips the invitation.
