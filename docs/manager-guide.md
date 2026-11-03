# Manager guide (ORD-05)

How to run the Cigarholic store from WP admin. Written for the store manager; grows with every feature. Screenshots are added from staging once it exists.

Log in at `/wp-admin` (for example `https://cigarholic.com/wp-admin`; it takes you to the login page) with your username or email address and your password. On staging the browser first asks for the staging password; that is a separate gate, not your WordPress account. Set up two-factor under **Users → Profile** right after your first login: it is required for every manager and admin account. Until you do, every admin page shows a yellow notice with your deadline (three days after your first login); after that only your profile opens, with a red notice, until two-factor is set up. Nothing is lost in the meantime, and your access comes back the moment you finish.

1. Install an authenticator app on your phone (Google Authenticator, Microsoft Authenticator, 1Password, Authy).
2. In **Users → Profile → Two-Factor Options**, at **Authenticator App**, scan the QR code with the app, type the six-digit code it shows and click **Verify**.
3. At **Recovery Codes**, click **Generate new recovery codes** and download or copy them into your password manager. Each code works once and gets you in if your phone is lost.
4. From then on, the login asks for the six-digit code from the app after your password.

Lost your phone and your recovery codes? Ask the owner: an administrator can switch your two-factor off so you can set it up again (`docs/runbooks/two-factor.md`).

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

## Logo and site icon

The logo in the header and on the coming-soon page, the browser-tab icon (the gold C on Forest) and the logo at the top of every email come from the brand files in the code, and every deploy puts them back. To change the logo, send the new artwork (ideally as a vector file: SVG, AI or PDF) and it is swapped in the code; a logo picked in the Site Editor would be replaced at the next deploy. The old versions stay in the media library.

## Brand colours and fonts

The colours and fonts are part of the theme; you choose them by name in the editor and never type a hex value. Two rules keep text readable for everyone (WCAG 2.2 AA, CMP-04):

- **On light backgrounds** (*Ash*, *Ash tint*) text is *Ink*, *Stone* (quieter text) or *Cedar* (links, buttons).
- **On dark backgrounds** (*Forest*, *Forest raised*) text is *Ash*, *Mist* (quieter text) or *Gold* (labels, buttons).
- Never put *Gold* text on a light background or *Cedar* text on Forest: both are too faint to read. *Line* is for dividers only.

The shop pages follow the same rules: form fields have a light fill and a dark grey border, the open product tab is underlined in Cedar, and the checkout has its own short Forest header (the logo and a cart link, no menu, so buyers are not pulled away). It is under **Appearance → Editor → Patterns → Template parts → Checkout header**; as with the main header, a copy saved there hides later theme updates, and the deploy log names it.

To make a section dark, select the group and pick **Forest** under *Styles*: the background, text, links, buttons and focus outline all switch together. The small uppercase label above a heading is the paragraph style **Eyebrow**; boxed panels are the group style **Card**. Headings use Young Serif and body text Hanken Grotesk; both are served from our own site, so there is nothing to install or license.

## Coming next in this guide

Add or edit a product, change a price, run a sale, process an order, refund, add tracking, edit pages.
