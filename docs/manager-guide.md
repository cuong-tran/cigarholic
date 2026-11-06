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

## Ship time

The store promises one ship time: how many business days pass between an order and its hand-off to the carrier, for items in stock. It shows on every product page ("Ships in 1–2 business days from our US warehouse in Houston, Texas."), under each item in the cart, the checkout and the mini cart ("Ships in 1–2 business days"), in the "What happens next" card on the order-received page and in the same section of the customer's **Processing order** and **Order on-hold** emails, in the trust bar on the homepage and in the FAQ answer about shipping. Products on backorder or out of stock get no promise; a variable product with some options on backorder says "In-stock options ship in …".

If you allow backorders on a product (Product data → Inventory), prefer **Allow, but notify customer**: the product page and the cart then say the item is on backorder before the purchase. Either way, the order-received page and the processing email tell the buyer that backordered items ship when they are back in stock, that you email the expected date, and that they can cancel them for a full refund. Then do exactly that: email each such buyer the expected ship date.

1. Open **WooCommerce → Settings → Shipping → Shipping settings** and scroll to **Ship time**.
2. Set **Ships in at least** and **Ships in at most** (business days, 1 to 60). The same number in both shows a single count ("Ships in 3 business days").
3. **Save changes**. Every message changes at once.

US law (the FTC Mail Order Rule) requires a reasonable basis for this promise. When orders will leave later than it says (a holiday, a stock delay, a warehouse problem):

1. Change the ship time here **before** taking more orders, and add an announcement bar if the delay is temporary ("Orders placed this week ship from December 2").
2. For orders already placed that will miss the promise, email each customer before the promised date: the new ship date, and that they may cancel for a full refund. If a second delay follows, the customer must agree to it or the order is cancelled and refunded.
3. Keep a note on each such order (**Add note** on the order page) of what you told the customer and when.

## Shipping an order: tracking number and shipping email

When a package leaves the warehouse, enter its tracking number on the order. That one step sends the customer the shipping email with the carrier and a **Track your package** button, shows the tracking number on the order in their account, and completes the order once everything has shipped.

1. Open **WooCommerce → Orders** and click the order (or click the grey **No fulfillments** badge in its row).
2. On the order page, click the **No fulfillments** badge at the top right, next to the order status. A panel opens on the right with the order's items, all ticked.
3. If only some items are in this package, untick the others. They stay to be shipped later.
4. Under **Shipment Information**, keep **Tracking Number**, paste the tracking number and click **Find info**. The carrier (USPS, UPS, FedEx, DHL and many more) and the tracking link fill in. If the carrier is wrong, click **Select your provider manually**. For a carrier that is not in the list, choose **Enter manually** and type the carrier name and its tracking link.
5. Leave **Fulfillment notification** on (blue). It sends the customer the email.
6. Click **Fulfill items**. The badge turns **Fulfilled** (or **Partially fulfilled** while items are still to ship), and the order notes record the shipment and the email.

What happens next, without anything else from you:

- The customer gets "Your Cigarholic order 1234 has shipped" with the carrier, the tracking number and the button. For a package with only some of the items, the subject starts "Part of your …"; the last package of such an order says "The rest of your …". Each email lists the items in that package.
- When every item has shipped, the order changes from **Processing** to **Completed** by itself. WooCommerce's "Completed order" email is not sent for these orders, because the shipping email already went out. An order you complete by hand without entering a shipment still gets that email.
- An order **On hold** (payment being checked) stays on hold even if you enter a shipment. Do not ship it until the payment is confirmed and the order is **Processing**.
- Customers with an account see "It has shipped." and the tracking number on the order under **My account → Orders**.

To correct a shipment, open the panel again, click the shipment, then **Edit fulfillment**. Fix the tracking number and save; with **Fulfillment notification** on, the customer gets a "shipment updated" email with the new details. To delete a shipment you entered by mistake, use **Remove fulfillment**; switch **Fulfillment notification** off first unless you want to email the customer that the shipment was cancelled. A completed order stays completed after a removal: set it back to **Processing** yourself if the package did not leave.

If you refund the items that were still to ship, WooCommerce does not count the order as shipped by itself: set it to **Completed** yourself once the shipped items are on their way.

Do not use **Mark as fulfilled** in the bulk actions of the orders list: it marks orders shipped without a tracking number and without telling the customer.

The wording of the shipping email comes with the store. To change it, open **WooCommerce → Settings → Emails → Fulfillment created** and set your own subject or heading; your text then replaces the store's for every order. **Additional content** at the bottom of that page adds a paragraph under the order details.

## Contact form

Messages from the **Contact** page arrive by email in the support inbox, with the visitor's address as Reply-To, so you answer by replying. Nothing is stored on the website.

1. **WooCommerce → Contact form** sets the recipient address (default `support@cigarholic.com`) and the subject prefix.
2. To place the form on a page, add the **Contact form** block in the editor; it offers a heading, the button label and whether to ask for an order number.
3. Spam is filtered by Cloudflare Turnstile and a rate limit; nothing to do.

## Coming soon

While **WooCommerce → Settings → Site visibility** is set to *Coming soon*, visitors see the launch page with the signup form and you (logged in as manager or admin) see the whole store. Only the owner decides when to switch it to *Live*.

Staging (`staging.cigarholic.com`) runs in coming soon too, exactly like the live site (owner's decision, 2026-11-05): log in there to see the store. Switching *Site visibility* to *Live* on either site does not last; the next deploy sets it back to *Coming soon*. Going live is a code change made when the owner says so.

The launch page is the template **Appearance → Editor → Templates → Page: Coming soon**. Saving it there makes a copy that hides every later theme update of that page (the brand design included) until the copy is reset with **⋮ → Reset**. To change the wording, ask for it to be changed in the theme instead; the deploy log warns about every saved copy.

## Logo and site icon

The logo in the header and on the coming-soon page, the browser-tab icon (the gold C on Forest) and the logo at the top of every email come from the brand files in the code, and every deploy puts them back. To change the logo, send the new artwork (ideally as a vector file: SVG, AI or PDF) and it is swapped in the code; a logo picked in the Site Editor would be replaced at the next deploy. The old versions stay in the media library.

## Homepage photos (sample images for now)

The homepage photos are computer-made sample images that stand in until the store's own photography exists: the humidor in the top section, the six category pictures and the workshop picture. They show the kind of pieces the store sells, not specific products, and never show cigars. They are part of the theme, so a deploy puts them back. To use real photos, send them (ideally 1600 px wide or more, landscape: 4:3 for the top section and the workshop, 3:2 for the categories) with a one-line description of each; they are swapped in the code together with the descriptions that screen readers announce.

The sample products on staging (titles starting with "Sample") carry matching images marked "Sample image". Production never has sample products.

## Brand colours and fonts

The colours and fonts are part of the theme; you choose them by name in the editor and never type a hex value. Two rules keep text readable for everyone (WCAG 2.2 AA, CMP-04):

- **On light backgrounds** (*Ash*, *Ash tint*) text is *Ink*, *Stone* (quieter text) or *Cedar* (links, buttons).
- **On dark backgrounds** (*Forest*, *Forest raised*) text is *Ash*, *Mist* (quieter text) or *Gold* (labels, buttons).
- Never put *Gold* text on a light background or *Cedar* text on Forest: both are too faint to read. *Line* is for dividers only.

The shop pages follow the same rules: form fields have a light fill and a dark grey border, the open product tab is underlined in Cedar, and the checkout has its own short Forest header (the logo and a cart link, no menu, so buyers are not pulled away). It is under **Appearance → Editor → Patterns → Template parts → Checkout header**; as with the main header, a copy saved there hides later theme updates, and the deploy log names it.

To make a section dark, select the group and pick **Forest** under *Styles*: the background, text, links, buttons and focus outline all switch together. The small uppercase label above a heading is the paragraph style **Eyebrow**; boxed panels are the group style **Card**. Headings use Young Serif and body text Hanken Grotesk; both are served from our own site, so there is nothing to install or license.

## Search results and social previews (SEOPress)

Google shows a page's **SEO title** and **meta description**; Facebook, Pinterest and messaging apps show the same title with the page's image. SEOPress writes them from templates, so a new product or collection gets them without any work:

| Page | Title | Description |
|---|---|---|
| Home | Cigarholic \| Handcrafted Wooden Humidors and Cigar Boxes | Wooden humidors, cigar boxes and humidor accessories, handcrafted in Vietnam and shipped from our Texas warehouse to the contiguous US. |
| Product | *Product name* \| Cigarholic | The product's **short description** |
| Collection (category) | *Category name* \| Cigarholic | The category's description |
| Shop | Shop Humidors and Cigar Boxes \| Cigarholic | Desktop, cabinet and travel humidors, cigar boxes and humidor accessories, handcrafted in Vietnam. |

So the most useful thing to do for search is to write a good **short description** for every product (about 150 characters, what it is, the wood, the capacity) and a description for every category (**Products → Categories → Edit**).

To word one page differently, open it and use the **SEO** box under the editor (tab **Titles settings**): a title or description typed there replaces the template for that page only. Leave the **Advanced** tab's "Do not display this page in search engine results / XML - HTML sitemaps (noindex)" unticked, except to hide a page from Google on purpose. Cart, checkout and My account are always hidden, and the next deploy puts that back if it is changed.

Site-wide SEOPress settings (**SEO → Titles & Metas**, **XML - HTML Sitemap** and the company details under **Social Networks**) are set by the code and come back with every deploy; ask for a change instead of editing them. The sitemap Google reads is `https://cigarholic.com/sitemaps.xml`: it lists published pages, products and collections, never drafts, the cart, checkout or account pages.

While the store is in coming soon, visitors and search engines see only the launch page, so none of this shows in Google before launch.

## Coming next in this guide

Add or edit a product, change a price, run a sale, process an order, refund, edit pages.
