# Tracking Template

A reusable WordPress plugin for contact-click tracking, marketing attribution, and session-based admin reporting.

**Created by [Benjamin Clar](https://github.com/benjamindimalanta)**

---

## Features

- **Contact click tracking** — WhatsApp, phone, showroom, floating buttons, Elfsight widgets, footer `tel:` links
- **Marketing attribution** — 30-day first-touch cookie for UTMs, `gclid`, landing URL, referrer, entry source
- **Session grouping** — One collapsible admin card per browser tab session; all clicks preserved inside
- **Visitor context** — Device, browser, country/region (Cloudflare header or geo lookup)
- **WooCommerce ready** — Product snapshot (title, price, mileage, image) on product pages
- **Site-wide tracking** — Landing pages, brand archives, and product pages
- **Admin dashboard** — Filters, pagination, CSV export
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
