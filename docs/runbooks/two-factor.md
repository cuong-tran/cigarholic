# Two-factor authentication (OPS-07)

Every account that can manage the store (administrators and shop managers: `manage_options` or
`manage_woocommerce`) must use two-factor authentication. The Two Factor plugin provides the login
step; `cigarholic-core` (`Security\TwoFactorRequired`, toggle `two-factor`) makes it mandatory.
Customers are never affected.

## What the rule does

| State | When | Access |
| --- | --- | --- |
| Grace | No method set up, less than 3 days since the account's first login (or first admin page view after the rule was switched on) | Full; every admin page shows the deadline and a link to set up two-factor |
| Restricted | No method set up, grace over | Only `read` and the admin dashboard: the account's own **Profile** works, every other admin page redirects there, and the REST API, admin-ajax, admin-post and application passwords refuse store management because the capabilities are withheld. The role is not changed |
| Enrolled | An authenticator app (or recovery codes) is set up | Full; the login asks for the six-digit code |

The grace period starts once and is never extended by logging in again. Disabling two-factor later
does not start a new grace period: the account is restricted until it sets up a method again.

Methods (provisioning step `45-two-factor`): **Authenticator App** (TOTP: Google Authenticator,
1Password, Authy, Microsoft Authenticator...) and **Recovery Codes** (the plugin's name for backup
codes). Emailed codes are switched off: there is no transactional mail until Postmark is set up
(OWNER-ACTIONS B1), and a mailbox is a weaker second factor than a phone.

## Setting it up (manager guide wording)

1. Log in, open **Users → Profile** (the warning's link goes straight there).
2. Under **Two-Factor Options**, at **Authenticator App**, scan the QR code with the app, type the
   six-digit code and click **Verify**, then save the profile.
3. At **Recovery Codes**, generate the codes and download or copy them into a password manager.
   Each code works once.
4. Log out and in again to check that the code is asked for.

## Lost phone or no codes

In this order:

1. **A recovery code.** On the code screen choose "Use a recovery code".
2. **Another administrator who is enrolled:** Users → the account → Two-Factor Options → turn the
   methods off and save. The account is then restricted (no new grace) until it enrols again.
3. **WP-CLI through GitHub Actions**, owner only (production runs wait for the owner's approval once
   the `production` Environment has a required reviewer, OWNER-ACTIONS A4):

   ```bash
   gh workflow run wp-cli.yml -f site=production -f command="two-factor status owner"
   ```

   ```bash
   gh workflow run wp-cli.yml -f site=production -f command="two-factor disable owner --yes"
   ```

   Then log in with the password only and enrol again at once from the profile. If the account is
   already restricted and needs full access for a moment first, restart its grace period:

   ```bash
   gh workflow run wp-cli.yml -f site=production -f command="user meta delete owner cigarholic_two_factor_grace_start"
   ```

   The next admin page view starts a fresh 3-day grace period.

## Switching the rule off

Only for an emergency, and only by the owner: `CIGARHOLIC_FEATURES=two-factor:off` in the site's
`shared/.env` (the deploy keeps that file), then reload PHP-FPM. On a developer laptop with a
long-lived database the same line in `.env` keeps `admin` usable without a phone.

Related: `docs/manager-guide.md` (Logging in), `secrets.md`, `troubleshooting.md`.
