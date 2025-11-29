# One Click Clone (RunCloud)

**Automated WordPress Staging & Cloning via RunCloud API v3**

One Click Clone (OCC) is a native WordPress plugin designed to democratize complex DevOps operations. It allows administrators to clone the current WordPress site to a new staging environment directly from the WP Admin Dashboard, handling infrastructure provisioning, DNS automation, and SSL configuration in a single click.

## 🚀 Features (v1.0.0)

- [cite_start]**RunCloud API v3 Integration:** Built strictly for the modern JSON-first API v3 standard using Bearer Token authentication[cite: 557, 582].
- **Smart Environment Discovery:** Automatically detects the current Server ID, Web Application ID, and Database ID by analyzing the server IP and file system paths. [cite_start]No manual ID lookup required[cite: 650, 656].
- [cite_start]**One-Click Cloning:** Instantly provisions a new Web Application, Database, and System User on RunCloud[cite: 558].
- [cite_start]**Cloudflare Automation (Optional):** If enabled, automatically creates DNS records (A Record), enables Proxy (Orange Cloud), and provisions Advanced SSL via DNS-01 validation[cite: 703, 712].
- [cite_start]**Real-Time Status Polling:** An AJAX-driven UI that polls the API to detect exactly when the new application is ready, providing a "Visit Site" button upon completion[cite: 646].
- **Security & Safety:**
  - [cite_start]**Rate Limiting:** Prevents API abuse by limiting clone operations to 5 per hour[cite: 609].
  - **Encrypted Storage:** API Tokens are encrypted in the database using OpenSSL.
  - [cite_start]**Validation:** Enforces strict naming conventions to prevent database username length errors[cite: 721].

## 🛠 Installation

1.  Upload the `one-click-clone` folder to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the **Plugins** menu in WordPress.
3.  Navigate to **One Click Clone** in the admin sidebar.

## ⚙️ Configuration

Before cloning, you must configure the API connection in the **Settings & API** tab:

1.  **RunCloud API Token:**
    - Generate a new API Key/Secret in RunCloud (Settings > API Key).
    - Use the Bearer Token provided for API v3.
2.  **Domain Suffix:**
    - Define the root domain structure for your clones.
    - _Example:_ Entering `-staging.example.com` means an input of `test` becomes `test-staging.example.com`.
3.  **Cloudflare Integration (Optional):**
    - Check **Enable Cloudflare** to automate DNS.
    - [cite_start]**Cloudflare Integration ID:** Enter the internal integer ID of your Cloudflare integration found in your RunCloud dashboard (Settings > Integrations)[cite: 707].

## 📖 How to Use

1.  Go to the **Cloning Tool** tab.
2.  The plugin will automatically detect and display your **Source Server** and **Source App** IDs.
3.  **New App Name:** Enter a name for the clone (e.g., `feature-test`).
    - [cite_start]_Note:_ A timestamp is automatically appended to ensure uniqueness[cite: 699].
4.  **Subdomain:** This is auto-filled based on the name but can be edited.
5.  **System User ID:** Enter the RunCloud System User ID that should own the new application.
6.  Click **Clone Site**.
7.  Wait for the progress indicator. [cite_start]A green success message with a link will appear when the site is ready[cite: 646].

## 📂 Project Structure

```text
one-click-clone/
├── admin/
│   ├── css/              # Admin styling
│   ├── js/               # AJAX Polling & Form handling
│   └── partials/         # View templates (Settings, Clone Form)
├── includes/
│   ├── class-occ-api.php # API v3 Client & Rate Limiting
│   ├── class-occ-discovery.php # IP & Path-based ID detection
│   ├── class-occ-ajax.php # Payload construction & Request handling
│   ├── class-occ-encryption.php # Security & Encryption
│   └── class-occ-admin.php # Menu & Enqueue logic
└── one-click-clone.php   # Plugin Bootstrap
```
