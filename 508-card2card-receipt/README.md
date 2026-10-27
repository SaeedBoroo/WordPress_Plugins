# 508 Card2Card Receipt

A lightweight WooCommerce plugin that turns the built-in **Direct Bank Transfer (BACS)** gateway into a complete **card-to-card payment flow** for Iranian stores. Customers see the bank account details in a modern popup, pick the transfer date in the **Jalali (Persian) calendar**, upload a receipt (**image or PDF**), and the order moves to **Processing** automatically.

![Version](https://img.shields.io/badge/version-1.3.0-3b82f6)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b)
![WooCommerce](https://img.shields.io/badge/WooCommerce-7.0%2B-96588a)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4)
![HPOS](https://img.shields.io/badge/HPOS-compatible-22c55e)
![License](https://img.shields.io/badge/license-GPLv2%2B-lightgrey)

---

## Features

- **Checkout popup**: when the customer places an order with BACS, a popup opens instead of a redirect. It shows the order total and the bank accounts as styled bank cards, with copy buttons for the card number and IBAN (Sheba).
- **Jalali date picker**: day, month and year selectors in the Persian calendar, defaulting to today. The year list (current and previous year) updates automatically. The conversion is built in, so no other plugin is needed.
- **Receipt upload**: drag and drop with a live preview, file name and size.
  - Images: JPG, PNG, WebP, GIF, HEIC/HEIF (up to 10 MB input). They are re-encoded and compressed to JPEG under **2 MB**.
  - PDF: up to **2 MB**. The file signature is checked, and PDFs with active content are rejected.
- **Automatic status flow**: `On hold` (awaiting review) changes to `Processing` after the receipt is uploaded. An order note is added.
- **Customer account panel**: a single, clean box above the order details.
  - Awaiting payment: a **"Pay & upload receipt"** button opens the same popup.
  - Uploaded: a thumbnail plus *"Receipt submitted. Transfer date: 1405/08/06"*. Click to view the image in a lightbox; PDFs open in a new tab.
- **Admin order screen**: a receipt thumbnail (or PDF badge), the Jalali transfer date, and a link to the full file.
- **Custom account panels**: works with the standard WooCommerce *My Account* and includes a fallback for custom panels that use `?mersa_order=ID`.
- **Guest checkout support**: access is verified with the WooCommerce order key.
- **Mobile-first UI**: the popup becomes a bottom sheet on small screens. It respects `prefers-reduced-motion` and is RTL-ready.
- **Backward compatible**: reads metadata and files saved by the previous `ANA Card-to-Card` plugin and displays old Gregorian dates in Jalali.
- **HPOS compatible**: declares support for WooCommerce High-Performance Order Storage.

---

## Requirements

| Requirement | Version |
|---|---|
| WordPress | 6.0+ |
| WooCommerce | 7.0+ (classic shortcode checkout) |
| PHP | 7.4+ |
| Image library | GD or Imagick (Imagick with HEIC support needed for HEIC uploads) |

> **Note:** The popup hooks into the classic checkout (`[woocommerce_checkout]`). The block-based Checkout is not supported yet.

---

## Installation

1. Download or clone this repository into `wp-content/plugins/508-card2card-receipt/`.
2. Make sure the main file is `wp-content/plugins/508-card2card-receipt/508-card2card-receipt.php`.
3. If you used the older **ANA Card-to-Card Receipt** plugin, **deactivate it first**.
4. Activate **508 Card2Card Receipt** from *Plugins*.
5. Go to **WooCommerce â†’ Settings â†’ Payments â†’ Direct bank transfer**, enable it, and add your bank accounts:
   - **Account name**: shown as the card holder
   - **Bank name**: shown on the card header
   - **Account number**: your card number (16 digits are auto-grouped)
   - **IBAN**: your Sheba number

```bash
cd wp-content/plugins
git clone https://github.com/<your-username>/508-card2card-receipt.git
```

---

## How It Works

```
Checkout (BACS) â”€â”€â–º Order created as "On hold"
        â”‚
        â–¼
Popup: total + bank cards + Jalali date + upload
        â”‚
        â”œâ”€â”€ Upload succeeds â”€â”€â–º Order â†’ "Processing" â”€â”€â–º Thank-you page
        â”‚
        â””â”€â”€ Popup closed â”€â”€â”€â”€â”€â–º Order stays "On hold"
                                   â”‚
                                   â–¼
                     My Account â†’ Order â†’ "Pay & upload receipt"
                                   â”‚
                                   â–¼
                          Same popup â†’ "Processing"
```

---

## File Storage

Receipts are saved to:

```
wp-content/uploads/508-card2card-receipt/
```

- The folder is created automatically on activation or on the first upload.
- File naming: `C2C_{OrderNumber}_{JalaliDate}_{UniqueID}.{ext}`, for example `C2C_1515_1405-08-06_k3Jx9QmZ2pLw.jpg`.
- Files are **never** served directly. They are streamed through a permission-checked endpoint (`admin-post.php?action=c508_c2c_view`).

### Order Meta Keys

| Key | Description |
|---|---|
| `_508_c2c_path` | Absolute path of the stored file |
| `_508_c2c_name` | Stored file name |
| `_508_c2c_date` | Transfer date (Jalali, `YYYY/MM/DD`) |
| `_508_c2c_gdate` | Transfer date (Gregorian, `YYYY-MM-DD`) |
| `_508_c2c_mime` | `image/jpeg` or `application/pdf` |
| `_508_c2c_uploaded` | `yes` when a receipt is attached |

---

## Security

- Nonce verification on every upload request.
- Order ownership check (logged-in owner, shop manager, or a valid order key for guests).
- One receipt per order; uploads are only accepted for `on-hold` or `pending` BACS orders.
- Real MIME detection with `wp_check_filetype_and_ext()`, not just the file extension.
- **Images are fully re-encoded**, which strips EXIF data and any embedded payloads. They are also resized (max 2000 px) and compressed below 2 MB.
- **PDFs** are checked for a valid `%PDF-` signature. Files containing `/JavaScript`, `/JS`, `/Launch` or `/EmbeddedFile` are rejected. This check cannot see inside compressed object streams.
- Unguessable file names (12-character random ID).
- The storage folder includes `index.php` and an `.htaccess` that denies direct access, disables directory listing, and blocks script execution.
- Files are streamed with `X-Content-Type-Options: nosniff`, `X-Robots-Tag: noindex`, `Cache-Control: no-store`, and a strict CSP for images.
- Path traversal protection: the real file path must be inside an allowed storage folder.

### Nginx

`.htaccess` is ignored by Nginx. Add this to your server block:

```nginx
location ^~ /wp-content/uploads/508-card2card-receipt/ {
    deny all;
    return 403;
}
```

---

## Server Configuration

To accept images up to 10 MB, make sure PHP allows it:

```ini
upload_max_filesize = 10M
post_max_size = 12M
```

---

## Customization

All CSS classes use the `c508-` prefix and can be overridden from your theme's custom CSS.

| Class | Element |
|---|---|
| `.c508-ov` / `.c508-md` | Popup overlay / modal |
| `.c508-hd` | Popup header (gradient) |
| `.c508-amt` | Order total box |
| `.c508-card` | Bank account card |
| `.c508-date` | Jalali date selectors |
| `.c508-drop` / `.c508-prev` | Upload drop zone / file preview |
| `.c508-submit` / `.c508-btn` | Primary buttons |
| `.c508-wrap.c508-cta` | "Awaiting payment" box in My Account |
| `.c508-wrap.c508-done` | "Receipt submitted" box |
| `.c508-thumb` / `.c508-pdf` | Receipt thumbnail / PDF badge |

The brand colors are CSS variables:

```css
.c508-ov, .c508-wrap {
  --c5p: #3b82f6; /* primary */
  --c5v: #8b5cf6; /* secondary */
}
```

Size limits can be changed in the class constants:

```php
const MAX_MB    = 2;    // final stored size & PDF limit (MB)
const MAX_IN_MB = 10;   // max input image size before compression (MB)
const MAX_PX    = 2000; // max image dimension (px)
```

---

## Hooks Used

| Hook | Purpose |
|---|---|
| `woocommerce_bacs_process_payment_order_status` | Forces new BACS orders to `on-hold` |
| `woocommerce_payment_successful_result` | Adds order ID, key and total to the checkout response |
| `checkout_place_order_success` (JS) | Opens the popup instead of redirecting |
| `woocommerce_order_details_before_order_table` | Renders the receipt box (once per order) |
| `woocommerce_admin_order_data_after_billing_address` | Shows the receipt in the admin order screen |
| `woocommerce_thankyou_bacs` | Hides the default BACS bank details on the thank-you page |

---

## Changelog

### 1.3.0
- Added PDF receipt support (max 2 MB, with signature and active-content checks).
- Added GIF and HEIC/HEIF image support.
- Images up to 10 MB are accepted and automatically compressed to JPEG under 2 MB.
- PDF badge in the customer panel and admin screen.

### 1.2.0
- Storage folder renamed to `wp-content/uploads/508-card2card-receipt/`, created automatically.
- New file naming: `C2C_OrderNum_Date_UniqueID`.
- Images are re-encoded to strip metadata and hidden payloads.
- Hardened `.htaccess` and stricter response headers.
- Removed the extra "Pay" button from the orders list actions.

### 1.1.0
- Renamed to **508 Card2Card Receipt** (`c508` prefix).
- Jalali date picker.
- Bank-card style UI, drag-and-drop upload with preview.
- Receipt box rendered once, above the order details.
- Receipt lightbox for customers; thumbnail for admins.
- Checkout flow rewritten with the native `checkout_place_order_success` event.
- HPOS compatibility.

---

## License

This plugin is licensed under the [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html), the same license as WordPress and WooCommerce.

---

## Author

Developed by **508**. Contributions, issues and pull requests are welcome.
“Written by Claude Opus 5.5”
