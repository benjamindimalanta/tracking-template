# Tracking Template

A reusable WordPress plugin for contact-click tracking, marketing attribution, and session-based admin reporting.

**Created by [Benjamin Clar](https://github.com/benjamindimalanta)**

📄 **[Full project report (documentation + screenshots)](docs/PROJECT-REPORT.md)**

---

## Features

- **Contact click tracking** — WhatsApp, phone, showroom, floating buttons, Elfsight widgets, footer `tel:` links
- **Marketing attribution** — 30-day first-touch cookie for UTMs, `gclid`, landing URL, referrer, entry source
- **Session grouping** — One collapsible admin card per browser tab session; all clicks preserved inside
- **Visitor context** — Device, browser, country/region (Cloudflare header or geo lookup)
- **WooCommerce ready** — Product snapshot (title, price, mileage, image) on product pages
- **Site-wide tracking** — Landing pages, brand archives, and product pages
- **Admin dashboard** — Filters, pagination, CSV export
- **License control** — Remote license key validation with soft kill (tracking off when inactive)
- **WP Rocket compatible** — Script exclusions for cache/minify/defer

---

## Requirements

- WordPress 5.8+
- PHP 7.4+
- WooCommerce (optional, for product-page enrichment)

---

## Installation

1. Download or clone this repository into `wp-content/plugins/tracking-template/`
2. Activate **Tracking Template** in **Plugins**
3. The database table is created automatically on activation
4. Clear any page cache (WP Rocket, Cloudflare, etc.) after install or updates

### Theme integration (product pages)

Add `data-track="contact"` attributes to contact links you want tracked, for example:

```html
<a href="https://wa.me/971..." 
   data-track="contact"
   data-contact-type="whatsapp"
   data-agent-id="1"
   data-agent-name="Sales Agent"
   data-source="product_card">
   WhatsApp
</a>
```

Supported `data-contact-type` values: `whatsapp`, `phone`, `showroom_landline`, `floating_whatsapp`, `floating_phone`.

Elfsight and footer phone links are detected automatically on all front-end pages.

---

## Google Ads URL template

Append UTMs using Google dynamic parameters:

```
utm_source=google&utm_medium=cpc&utm_campaign={campaignname}&utm_id={campaignid}&utm_term={keyword}&utm_content={creative}
```

---

## Admin

After activation, open **Tracking Template** in the WordPress admin sidebar.

- Sessions are grouped by browser tab (`session_id` in sessionStorage)
- Expand a session to see marketing data and every click in chronological order
- **Export CSV** downloads all individual click rows (not grouped)
- **License** — Administrators activate under **Tracking Template → License**

---

## License (author control)

Tracking Template validates license keys against your **License Hub** API (Supabase + Vercel):

`https://plugin.cubescenter.org/api/licenses.json`

### Setup (plugin author)

1. Deploy **adct-license-hub** (see `adct-license-hub` repo) with Supabase
2. Run `supabase/schema.sql` in your Supabase project
3. Generate keys in the dashboard at https://plugin.cubescenter.org
4. Customer enters the key under **Tracking Template → License**

### Revoke access

Use the License Hub dashboard — **Revoke** or deactivate a key. No GitHub or file edits required.

### When license is inactive (soft kill)

| Area | Behaviour |
|---|---|
| **Website visitors** | No tracking scripts — no new clicks recorded |
| **AJAX endpoint** | Blocked |
| **Overview / Leads / Sessions** | Glass lock screen — link to License page |
| **License page** | Always available to administrators |
| **WooCommerce / rest of site** | Unaffected |

### Grace periods

- **3 days** front-end tracking trial on a **brand-new** install with no prior click data (admin reporting still requires a key)
- **No trial** when upgrading from v1.5.x or any site that already has tracking history
- **7 days** if license server unreachable but key was previously valid

### Local development

Add to `wp-config.php`:

```php
define( 'ADCT_LICENSE_BYPASS', true );
```

Optional custom license URL:

```php
define( 'ADCT_LICENSE_API_URL', 'https://plugin.cubescenter.org/api/licenses.json' );
```

---

## File structure

```
tracking-template/
├── tracking-template.php      # Main plugin bootstrap
├── assets/
│   ├── entry-capture.js       # Attribution cookie + session ID (all pages)
│   └── tracker.js             # Click capture (all pages)
└── includes/
    ├── class-adct-admin.php   # Admin UI
    ├── class-adct-ajax.php    # AJAX handlers
    ├── class-adct-database.php# Database layer
    └── class-adct-visitor.php # Device / geo helpers
```

---

## Changelog

### 1.6.2

- **Updates via License Hub** — plugin checks `https://plugin.cubescenter.org/api/update.json` (not GitHub)
- Release zips hosted at `plugin.cubescenter.org/releases/` (channel starts at v1.6.1)

### 1.6.1

- **Tighter licensing** — upgrades from pre-1.6.0 (or sites with existing tracking data) require a license immediately
- **Admin locked during trial** — Overview/Leads/Sessions need a valid key; short front-end trial only for brand-new installs (3 days)
- **Old versions note** — only v1.6.0+ enforces licensing; stay on latest release

### 1.6.0

- **License system** — key activation, remote validation via License Hub API (`plugin.cubescenter.org`), soft kill when inactive
- **License admin page** — activate key, masked key when active, change/deactivate, plan, expiry, last checked
- **Locked reporting UI** — glass overlay on Overview/Leads/Sessions when license inactive
- 14-day activation grace on first install; 7-day grace if license server unreachable

### 1.5.0

- Rename lead status labels to **contact click intents** (WhatsApp click, Phone click, Widget call click)
- Leads page and sidebar explain that counts are intents, not confirmed messages or calls
- Note added that a **Likely engaged** filter is planned for a future update

### 1.4.5

- Leads **Date** column shows day and time on separate lines for easier scanning
- Leads **Source** column simplified — short landing path instead of long URLs (full path on hover)
- Unified **Status** badge sizing across WhatsApp, Phone, and Widget call leads

### 1.4.4

- Leads page **Session** column — short session code, click position (e.g. Click 2 of 4), hover details, link to Sessions
- Sessions page opens and filters to the selected session when linked from Leads
- Date display shows seconds; Elfsight tracks only actual phone-number (`tel:`) clicks

### 1.4.3

- Top salesmen excludes Elfsight widget and footer showroom labels (not real salespeople)
- Leads salesman column shows — for widget and site-wide call clicks

### 1.4.2

- Overview **Click performance** panels: top cars, top salesmen, device breakdown, recent leads
- **Where traffic comes from** — Organic Traffic, Google Campaign, and Referrers with count and percentage
- Subtle card depth, channel tints, and hover polish on Overview insight panels
- Includes access control fix from 1.4.1 (Shop manager role no longer clears after refresh)

### 1.4.1

- Fix Access control clearing Shop manager after refresh (reliable role user counts, safer prune logic)
- Save access from Overview, Leads, or Sessions pages

### 1.4.0

- New Leads page with All, Phone, and WhatsApp tabs — one row per contact click
- Lead status labels, enquiry details, source, campaign, and CSV export

### 1.3.2

- Overview Marketing tab: traffic sources, top campaigns, and landing pages (Unattributed removed)
- Top campaign KPI when campaign data is available

### 1.3.0

- New Overview page with contact-type donut chart, daily trend graph, and traffic sources tab
- Overview and Sessions submenu under Tracking Template
- Date range selector: last 7, 30, or 90 days

### 1.2.2

- Test release to verify GitHub Releases API update check (Current vs Latest + one-click update)

### 1.2.1

- Access control lists only roles that currently have users (e.g. Shop manager), not every WordPress role
- Full-width responsive page header spanning main content and sidebar
- GitHub update check falls back to tags when no Release is published

### 1.2.0

- Role-based access control — grant view access to available WordPress roles (Administrator always has access)
- GitHub one-click updates with Current vs Latest version display
- Check for updates from the admin sidebar

### 1.1.0

- Responsive admin sidebar with plugin info, live snapshot, features, quick actions, and system status
- Copy Google Ads UTM suffix button
- Quick setup guide when no sessions exist yet

### 1.0.0

- Initial public release as **Tracking Template** by Benjamin Clar
- Session-grouped admin UI with marketing attribution
- Site-wide Elfsight and footer phone tracking
- WP Rocket exclusions

---

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

---

## Author

**Benjamin Clar**  
GitHub: [@benjamindimalanta](https://github.com/benjamindimalanta)
