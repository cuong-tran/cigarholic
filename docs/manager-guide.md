# Manager guide (ORD-05)

How to run the Cigarholic store from WP admin. Written for the store manager; grows with every feature. Screenshots are added from staging once it exists.

Log in at `/wp/wp-login.php` with your account and your authenticator app (two-factor is required for every manager and admin account).

## Email signups (coming-soon page and newsletter form)

Visitors who leave their email on the coming-soon page or in the footer form are stored in **WooCommerce → Signups**.

1. Open **WooCommerce → Signups** to see how many people signed up and the latest addresses.
2. **Download CSV** gives the full list for the email platform. The list is personal data: upload it only into the email platform, never share it elsewhere, and delete the file afterwards.
3. Duplicates are removed automatically; the same address counts once.

## Announcement bar

A single line at the top of every page, for a promotion, a shipping message or a holiday cut-off.

1. Open **WooCommerce → Announcement bar**.
2. Tick **Enabled**, type the text (up to 160 characters), optionally add a link (for example `/collections/gift-sets/`).
3. Optionally set a **Start** and **End** in the store's timezone (Eastern Time, shown next to the field). Leave them empty to show the bar until you untick Enabled.
4. **Save announcement bar**. The change is live immediately.

## Contact form

Messages from the **Contact** page arrive by email in the support inbox, with the visitor's address as Reply-To, so you answer by replying. Nothing is stored on the website.

1. **WooCommerce → Contact form** sets the recipient address (default `support@cigarholic.com`) and the subject prefix.
2. To place the form on a page, add the **Contact form** block in the editor; it offers a heading, the button label and whether to ask for an order number.
3. Spam is filtered by Cloudflare Turnstile and a rate limit; nothing to do.

## Coming soon

While **WooCommerce → Settings → Site visibility** is set to *Coming soon*, visitors see the launch page with the signup form and you (logged in as manager or admin) see the whole store. Only the owner decides when to switch it to *Live*.

## Coming next in this guide

Add or edit a product, change a price, run a sale, process an order, refund, add tracking, edit pages.
