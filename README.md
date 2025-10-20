# Smart Hair Graft Calculator (WordPress Plugin)

**Current Version:** *from code*

The **Smart Hair Graft Calculator** plugin provides a multi-step (wizard-style) form to estimate the number of hair transplant grafts.  
It stores user data step by step, generates a final result (optionally using OpenAI integration), and can create a **public result link**.  
The front-end is built with **jQuery** and **Bootstrap**, fully supporting **RTL layouts**, while the backend uses native WordPress architecture.

> This README was generated from the actual uploaded source code — reflecting the real shortcodes, hooks, database tables, options, and admin modules.

---

## ✨ Key Features
- Multi-step (1–5 steps) AJAX form with optional AI-generated follow-up questions and final analysis  
- Step-by-step data persistence in a dedicated database table  
- Generates a unique **public result link (token)** for each submission  
- Two shortcodes for displaying both the **form** and the **result view**  
- Full RTL and **i18n translation** support (`languages/` folder)  
- Integrates popular frontend libraries: **Bootstrap**, **Toastr**, **html2canvas**, **jsPDF**, **Lottie**  
- Dedicated **Admin Menu** for data management and plugin settings  
- Built-in **OpenAI** (Chat Completions or Assistants API) integration  
- Optional **SMS (IPPanel Edge)** and **Telegram Bot** notification modules  

---

## ⚙️ Installation & Setup
1. Upload the plugin to `wp-content/plugins/hair-estimator` or install via the WordPress Plugins page.  
2. Activate the plugin. On first activation:
   - Custom database tables are created.  
   - Default pages are automatically generated (if missing):
     - `/hair-graft-calculator/` → `[smart_hair_calculator]`
     - `/hair-result/` → `[smart_hair_result]`
   - Rewrite rules for the public result path are registered.  
3. Go to the **AI** admin menu → **Settings** to configure API keys and prompts.

> Multisite installations are supported — database tables are created per site.

---

## 🧩 Shortcodes
| Shortcode | Description |
|------------|-------------|
| `[smart_hair_calculator]` | Displays the multi-step hair graft estimation form |
| `[smart_hair_result]` | Displays a user’s result page based on their token |

You can embed these shortcodes into any page or post.

---

## 🧠 Admin Menu
A new **AI** section is added to the WordPress Dashboard:
- **Data List** — displays user entries in a DataTable with links to details  
- **Data Details** — shows all collected form fields and AI results  
- **Settings** — configure API keys, prompts, modes, and debugging options  

RTL layout and **Shabnam font** are included for an improved admin experience.

---

## ⚙️ Plugin Options
The plugin stores settings in WordPress options:

- `shec_api_key` – OpenAI API key  
- `shec_asst_enable` – Enable Assistants mode instead of Chat Completions  
- `shec_asst_qs_id`, `shec_asst_final_id` – Assistant IDs for question/final steps  
- `shec_prompt_questions`, `shec_prompt_final` – Custom prompts for Chat Completions  
- `shec_telegram_api`, `shec_admin_chat_id`, `shec_tg_secret` – Telegram Bot settings  
- `shec_sms_api` – IPPanel Edge SMS API key  
- `shec_debug` – Enable verbose logging/debug mode  

Some settings can also be provided via `.env` or environment variables.  

---

## 🗄️ Database Structure
### Table: `wp_shec_users`
Stores each submission and user’s data:
```sql
CREATE TABLE wp_shec_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  wp_user_id BIGINT UNSIGNED DEFAULT NULL,
  data LONGTEXT NOT NULL,
  created DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY wp_user_id (wp_user_id)
);
```

### Table: `wp_shec_links`
Stores public share tokens:
```sql
CREATE TABLE wp_shec_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  wp_user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  created DATETIME DEFAULT CURRENT_TIMESTAMP,
  expires DATETIME NULL,
  is_active TINYINT(1) DEFAULT 1,
  KEY wp_user_id (wp_user_id)
);
```

---

## 🌐 Public Result Path
- Custom **rewrite rule** for `/r/{TOKEN}`  
- `shec_token` query var automatically registered  
- `[smart_hair_result]` shortcode renders the public result view  
- Tokens can expire or be deactivated (`expires`, `is_active` columns)

---

## 🔁 AJAX Endpoints
Endpoints are registered for both **logged-in** and **guest** users:

- `shec_step1` → `shec_step5` – progressive data submission  
- `shec_ai_questions` – generates AI-based follow-up questions  
- `shec_finalize` – generates final analysis & issues a result token  
- `shec_result_by_token` – retrieves result data via token  

Security:  
Each request checks a **nonce** (bypassed only for `localhost`/`DEBUG` mode).  

---

## 🎨 Frontend Assets
- **JS:** `public/assets/js/form.js` (jQuery-based logic)  
- **CSS:** `public/assets/scss/style.css` (+ RTL version)  
- Uses **CDN** libraries: Toastr, html2canvas, jsPDF, Lottie  
- JavaScript strings can be localized using `wp_set_script_translations`  

> To ensure a clean user experience, the plugin hides the theme header/footer and disables conflicting scripts on the form page.

---

## 🔗 Token & Sharing
Each finalized record generates a **unique token** accessible via `/r/{TOKEN}`.  
The link can be shared via **SMS** or **Telegram** if those modules are configured.

---

## 🛡️ Security
- Nonce validation on all AJAX requests  
- Input normalization (e.g., phone numbers)  
- File upload validation recommended (type/size)  
- **Recommendations:**
  - Disable nonce bypass in production.  
  - Implement rate-limiting or CAPTCHA for public forms.  
  - Manage token expiration (`expires` / `is_active`).  

---

## 📁 Folder Structure
```
hair-estimator/
  hair-estimator.php           # Main plugin file (activation, enqueue, rewrite)
  includes/
    helpers.php                # Shortcode definitions + utility functions
    admin/
      admin-hair.php           # Admin menu, data list/detail pages, settings
    graft-estimator/
      ajax-handlers.php        # AJAX endpoints, OpenAI integration, token logic
    tools/
      telegram.php             # Telegram bot (optional)
      sms.php                  # SMS integration (optional)
  templates/
    form-template.php          # Multi-step form markup
  public/
    assets/
      js/form.js
      scss/style.css
      img/...
  languages/                   # Translation files (.po/.mo/.json)
  composer.json / package.json
```

---

## 🧑‍💻 Development Notes
- Modify JS/CSS and recompile assets under `public/assets/`.  
- Text domain: `smart-hair-calculator`  
- Debug option (`shec_debug`) enables verbose output.  

### Suggested Improvements
- Migrate the frontend to **React + Vite + TypeScript** (with REST + nonce)  
- Stronger client-side validation (React Hook Form + Zod)  
- Optional chunked image uploads  
- Add **rate-limit/CAPTCHA**  
- Add **unit & integration tests**

---

## 🧹 Uninstall
A `uninstall.php` file is recommended to clean up:
- Tables: `wp_shec_users`, `wp_shec_links`
- All plugin options (`shec_*`)

(Currently not included.)

---

## 📄 License
Specify your license here — e.g., MIT or GPL-2.0-or-later.
