# Deploy Vercel (PHP)

## 1) Prerequisites

- A Vercel account.
- A MySQL database reachable from the public Internet.
- Project pushed to GitHub/GitLab/Bitbucket (recommended).

## 2) Import project into Vercel

1. Open Vercel dashboard.
2. Click **Add New... > Project**.
3. Import this repository.
4. Keep root directory as repository root.

## 3) Environment variables

In **Project Settings > Environment Variables**, add:

- `DB_HOST`
- `DB_PORT` (usually `3306`)
- `DB_USER`
- `DB_PASS`
- `DB_NAME`

Use `.env.example` as reference.

## 4) Deploy

- Trigger a deploy from Vercel dashboard, or push to your default branch.
- Once deployment is complete, open the generated URL.

## 5) Routing behavior

- `/` and `/index.php` are routed to `inptic_asur/index.php`.
- Existing static files and PHP files remain accessible by their paths.

## 6) Production checklist

- Ensure DB firewall allows Vercel connections.
- Use a strong DB password.
- Remove test/debug scripts if not needed (for example `inptic_asur/test_connexion_direct.php` and `inptic_asur/fix_all_passwords.php`).
- Rotate any previously exposed credentials.

## 7) Optional: deploy with CLI

```bash
vercel login
vercel
vercel --prod
```
