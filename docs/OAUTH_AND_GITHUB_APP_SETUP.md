# OAuth & GitHub App Setup Guide

This guide walks you through setting up the three external services Sentinel requires:

1. **GitHub OAuth App** - For user login via GitHub
2. **Google OAuth App** - For user login via Google
3. **GitHub App** - For repository integration (webhooks, PR comments, etc.)

---

## Prerequisites

-   Your backend running at: `http://localhost:8000` (or your `APP_URL`)
-   Your frontend running at: `http://localhost:3000` (or your `FRONTEND_URL`)

---

## 1. GitHub OAuth App (User Login)

This allows users to sign in to Sentinel using their GitHub account.

### Step 1: Create the OAuth App

1. Go to [GitHub Developer Settings](https://github.com/settings/developers)
2. Click **OAuth Apps** in the left sidebar
3. Click **New OAuth App**

### Step 2: Fill in the Application Details

| Field                          | Value                                        |
| ------------------------------ | -------------------------------------------- |
| **Application name**           | `Sentinel Local` (or any name you prefer)    |
| **Homepage URL**               | `http://localhost:3000`                      |
| **Application description**    | (optional) AI-powered code review platform   |
| **Authorization callback URL** | `http://localhost:8000/auth/github/callback` |

> **Important**: The callback URL must point to your **backend** URL, not the frontend.

### Step 3: Register and Get Credentials

1. Click **Register application**
2. You'll see your **Client ID** - copy it
3. Click **Generate a new client secret**
4. Copy the **Client Secret** immediately (you won't see it again)

### Step 4: Add to Your `.env` File

```env
GITHUB_CLIENT_ID=your_client_id_here
GITHUB_CLIENT_SECRET=your_client_secret_here
GITHUB_REDIRECT_URL=http://localhost:8000/auth/github/callback
```

### Step 5: Verify Configuration

```bash
php artisan tinker --execute="dump(config('services.github'));"
```

You should see your client_id and a masked secret.

---

## 2. Google OAuth App (User Login)

This allows users to sign in to Sentinel using their Google account.

### Step 1: Access Google Cloud Console

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Name it something like `Sentinel Local`

### Step 2: Enable the Google+ API (if not already enabled)

1. Go to **APIs & Services** > **Library**
2. Search for "Google+ API" or "Google Identity"
3. Click **Enable** (this may already be enabled)

### Step 3: Configure OAuth Consent Screen

1. Go to **APIs & Services** > **OAuth consent screen**
2. Select **External** user type (unless you have Google Workspace)
3. Click **Create**

Fill in the required fields:

| Field                       | Value                     |
| --------------------------- | ------------------------- |
| **App name**                | `Sentinel`                |
| **User support email**      | Your email address        |
| **App logo**                | (optional)                |
| **App domain**              | Leave blank for local dev |
| **Developer contact email** | Your email address        |

4. Click **Save and Continue**
5. On the **Scopes** page, click **Add or Remove Scopes**
6. Select these scopes:
    - `openid`
    - `email`
    - `profile`
7. Click **Update**, then **Save and Continue**
8. On **Test users**, add your Google email address
9. Click **Save and Continue**, then **Back to Dashboard**

### Step 4: Create OAuth Credentials

1. Go to **APIs & Services** > **Credentials**
2. Click **Create Credentials** > **OAuth client ID**
3. Select **Web application** as the application type

Fill in:

| Field                             | Value                                        |
| --------------------------------- | -------------------------------------------- |
| **Name**                          | `Sentinel Web Client`                        |
| **Authorized JavaScript origins** | `http://localhost:3000`                      |
| **Authorized redirect URIs**      | `http://localhost:8000/auth/google/callback` |

> **Important**: Click **+ Add URI** to add each origin/redirect URI.

4. Click **Create**

### Step 5: Copy Your Credentials

A dialog will appear with:

-   **Client ID** - Copy this
-   **Client Secret** - Copy this

### Step 6: Add to Your `.env` File

```env
GOOGLE_CLIENT_ID=your_client_id_here.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your_client_secret_here
GOOGLE_REDIRECT_URL=http://localhost:8000/auth/google/callback
```

### Step 7: Verify Configuration

```bash
php artisan tinker --execute="dump(config('services.google'));"
```

---

## 3. GitHub App (Repository Integration)

This is different from the OAuth App. The GitHub App allows Sentinel to:

-   Receive webhooks when PRs are opened
-   Post review comments on PRs
-   Access repository contents for code review

### Step 1: Create the GitHub App

1. Go to [GitHub Developer Settings](https://github.com/settings/developers)
2. Click **GitHub Apps** in the left sidebar
3. Click **New GitHub App**

### Step 2: Fill in Basic Information

| Field               | Value                                           |
| ------------------- | ----------------------------------------------- |
| **GitHub App name** | `Sentinel Local` (must be unique across GitHub) |
| **Description**     | AI-powered code review assistant                |
| **Homepage URL**    | `http://localhost:3000`                         |

### Step 3: Configure Callback & Setup URLs

| Field              | Value                                           |
| ------------------ | ----------------------------------------------- |
| **Callback URL**   | `http://localhost:8000/api/github/callback`     |
| **Setup URL**      | `http://localhost:3000/github/setup` (optional) |
| **Webhook URL**    | `http://localhost:8000/api/webhooks/github`     |
| **Webhook secret** | Generate a secure random string (see below)     |

Generate a webhook secret:

```bash
openssl rand -hex 32
```

Copy this value - you'll need it for both GitHub and your `.env`.

### Step 4: Set Permissions

Under **Repository permissions**:

| Permission          | Access Level   |
| ------------------- | -------------- |
| **Contents**        | Read-only      |
| **Metadata**        | Read-only      |
| **Pull requests**   | Read and write |
| **Commit statuses** | Read and write |

Under **Organization permissions**:

-   None required for basic functionality

Under **Account permissions**:

-   None required

### Step 5: Subscribe to Events

Check these webhook events:

-   [x] **Pull request**
-   [x] **Pull request review**
-   [x] **Pull request review comment**
-   [x] **Push** (optional, for branch tracking)

### Step 6: Installation Settings

| Field                                       | Value                                                 |
| ------------------------------------------- | ----------------------------------------------------- |
| **Where can this GitHub App be installed?** | `Any account` (or `Only on this account` for testing) |

### Step 7: Create the App

Click **Create GitHub App**

### Step 8: Note Your App ID

After creation, you'll be on the app's settings page. Note the **App ID** at the top (it's a number like `123456`).

### Step 9: Generate a Private Key

1. Scroll down to **Private keys**
2. Click **Generate a private key**
3. A `.pem` file will download automatically
4. Move this file to your project:

```bash
# Create the directory if it doesn't exist
mkdir -p storage/app/github

# Move the downloaded key (adjust the filename as needed)
mv ~/Downloads/sentinel-local.*.private-key.pem storage/app/github/private-key.pem

# Secure the permissions
chmod 600 storage/app/github/private-key.pem
```

### Step 10: Add to Your `.env` File

```env
GITHUB_APP_ID=123456
GITHUB_APP_NAME=sentinel-local
GITHUB_PRIVATE_KEY_PATH=storage/app/github/private-key.pem
GITHUB_WEBHOOK_SECRET=your_webhook_secret_from_step_3
```

### Step 11: Verify Configuration

```bash
php artisan tinker --execute="dump(config('github'));"
```

### Step 12: Install the App on a Test Repository

1. Go to your GitHub App's public page: `https://github.com/apps/sentinel-local`
2. Click **Install**
3. Choose which repositories to grant access to
4. Click **Install**

---

## Local Development with Webhooks

For local development, GitHub can't reach `localhost`. You have two options:

### Option A: Use a Tunnel Service (Recommended)

Use [ngrok](https://ngrok.com/), [Cloudflare Tunnel](https://developers.cloudflare.com/cloudflare-one/connections/connect-apps/), or [Expose](https://expose.dev/):

```bash
# Using ngrok
ngrok http 8000

# You'll get a URL like: https://abc123.ngrok.io
```

Then update your GitHub App's webhook URL to:
`https://abc123.ngrok.io/api/webhooks/github`

### Option B: Use GitHub's Webhook Proxy (smee.io)

1. Go to [smee.io](https://smee.io/)
2. Click **Start a new channel**
3. Copy the webhook proxy URL
4. Set this as your GitHub App's webhook URL
5. Install the smee client:

```bash
npm install -g smee-client
```

6. Run the proxy:

```bash
smee -u https://smee.io/YOUR_CHANNEL_ID -t http://localhost:8000/api/webhooks/github
```

---

## Complete `.env` Example

```env
# Application URLs
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:3000

# GitHub OAuth (User Login)
GITHUB_CLIENT_ID=Iv1.xxxxxxxxxxxx
GITHUB_CLIENT_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
GITHUB_REDIRECT_URL=http://localhost:8000/auth/github/callback

# Google OAuth (User Login)
GOOGLE_CLIENT_ID=xxxxxxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxxxxxxxxxxxxxxxxxxxxxxx
GOOGLE_REDIRECT_URL=http://localhost:8000/auth/google/callback

# GitHub App (Repository Integration)
GITHUB_APP_ID=123456
GITHUB_APP_NAME=sentinel-local
GITHUB_PRIVATE_KEY_PATH=storage/app/github/private-key.pem
GITHUB_WEBHOOK_SECRET=your_64_character_hex_string_here
```

---

## Testing the Setup

### Test GitHub OAuth Login

1. Start your backend: `php artisan serve`
2. Start your frontend: `npm run dev` (in frontend repo)
3. Visit: `http://localhost:3000`
4. Click "Sign in with GitHub"
5. You should be redirected to GitHub, then back to your app

### Test Google OAuth Login

1. Same as above, but click "Sign in with Google"
2. If you get an "unverified app" warning, click "Advanced" > "Go to Sentinel (unsafe)"
    - This is normal for development; production apps need verification

### Test GitHub App Webhook

1. Start your webhook tunnel (ngrok or smee)
2. Open a PR on a repository where you installed the app
3. Check your Laravel logs: `tail -f storage/logs/laravel.log`
4. You should see webhook payload data

---

## Troubleshooting

### "redirect_uri_mismatch" Error (Google)

-   Ensure the redirect URI in Google Console exactly matches your `.env`
-   Include the full path: `http://localhost:8000/auth/google/callback`
-   Wait 5 minutes after making changes (Google caches settings)

### "Bad credentials" Error (GitHub OAuth)

-   Double-check your Client ID and Secret
-   Ensure there are no extra spaces or newlines in your `.env`

### "Integration not found" Error (GitHub App)

-   Verify the App ID is correct
-   Ensure the private key file exists and is readable
-   Check file permissions: `ls -la storage/app/github/`

### Webhooks Not Received

-   Verify your tunnel is running
-   Check GitHub App settings > Advanced > Recent Deliveries
-   Look for failed deliveries and their error messages

---

## Production Considerations

When deploying to production:

1. **Update all callback URLs** to use your production domain
2. **Use HTTPS** for all URLs
3. **Verify your Google OAuth app** to remove the "unverified app" warning
4. **Store the private key securely** (use environment variables or secrets management)
5. **Rotate the webhook secret** and update both GitHub and your server

---

_Last Updated: 2025-01-09_
