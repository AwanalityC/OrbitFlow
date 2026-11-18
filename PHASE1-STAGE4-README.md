# OrbitFlow Phase 1 Stage 4 — Password & Account Recovery

## Included
- Self-service forgot-password and reset-password pages.
- Laravel Password Broker; reset tokens are hashed in `password_reset_tokens` and checked by Laravel.
- Reset links expire after 30 minutes; broker throttle is 60 seconds.
- Request responses do not reveal whether an email exists or an account is active.
- Admin-assisted one-time reset-link page for active students (Super Admin/Petugas).
- Super Admin can issue reset links for active Petugas accounts.
- Inactive accounts cannot request or complete password resets.
- Password changes update `users.password_changed_at` and rotate `remember_token`.
- Stale authenticated sessions are detected through a fingerprint of the current password hash.
- With the configured database session driver, existing database sessions for that user are deleted on reset.
- Reset and admin-issued link pages use no-store/no-referrer headers. No plaintext passwords are displayed.

## Mail delivery
The supplied `.env.example` uses `MAIL_MAILER=log`. When the local `.env` also uses `MAIL_MAILER=log`, Laravel writes the reset email and its private reset URL to `storage/logs/laravel.log` instead of sending a real email. Protect this log and never share it while it contains active reset links. For real school use, configure an approved mail provider/SMTP service, HTTPS, and a correct school-accessible `APP_URL`.

Admin-assisted links are shown once in a private no-store page. Copy and deliver them directly through a school-approved private channel. Do not post links in public groups. The link is single-use and expires after 30 minutes.

## Database
The original project migration already creates `password_reset_tokens` and `sessions`. This patch adds only `users.password_changed_at`; no extra password-reset-token table is required.

## Apply
1. Keep a fresh database backup.
2. Put the patch ZIP and `APPLY-ORBITFLOW-PHASE1-STAGE4.ps1` in the same folder.
3. Run the PowerShell script.
4. Run `php artisan migrate`, `php artisan config:clear`, `php artisan route:clear`, `php artisan view:clear`, and `php artisan test`.
5. Do not run `migrate:fresh`.

The uploaded source ZIP does not include `vendor`, so the full Laravel test suite must be run in the local project after applying this patch.
