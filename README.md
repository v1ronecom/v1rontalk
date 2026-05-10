# V1Ron Talk Bot — Nextcloud App

Bring your V1RonDHM AI characters into **Nextcloud Talk** as bot participants. Characters can read, analyze, edit, and save files in Nextcloud — all through natural conversation.

## Architecture

```
┌─────────────────────────┐         ┌──────────────────────────────┐
│   Nextcloud (NC 31)     │         │   WordPress (V1RonDHM)       │
│                         │  REST   │                              │
│  ┌───────────────────┐  │  API    │  ┌────────────────────────┐  │
│  │ V1RonTalk App     │◄─┼─────────┼─►│ WP REST API Bridge     │  │
│  │                   │  │         │  │ /wp-json/v1ron/v1/*    │  │
│  │ ├─ Talk Bot       │  │         │  │                        │  │
│  │ ├─ Chat UI        │  │         │  │ ├─ /characters         │  │
│  │ ├─ File Service   │  │         │  │ ├─ /characters/{id}/   │  │
│  │ └─ File Picker UI │  │         │  │ │      chat            │  │
│  └───────────────────┘  │         │  │ ├─ /characters/{id}/   │  │
│                         │         │  │ │      knowledge       │  │
│  Nextcloud Talk ◄───────┼─────────┼──│─ └─ /user/info         │  │
│  (bot conversations)    │         │  └───────────┬────────────┘  │
│                         │         │              │               │
│  User Files ◄───────────┤         │        ┌──────▼──────┐      │
│  (read/write/share)     │         │        │ V1RonDHM    │      │
│                         │         │        │ Chat Engine │      │
│                         │         │        │ + RAG + LLM │      │
│                         │         │        └─────────────┘      │
└─────────────────────────┘         └──────────────────────────────┘
```

## Files Created

### WordPress Plugin Extension

| File | Purpose |
|------|---------|
| `includes/class-nextcloud-bridge.php` | REST API bridge — registers `/wp-json/v1ron/v1/*` endpoints with API key auth |
| `v1ron-digital-human.php` (modified) | Loads bridge, registers Nextcloud API key setting |
| `includes/admin/trait-admin-renders-main.php` (modified) | Adds Nextcloud Bridge settings section to WP admin |
| `includes/admin/trait-admin-ajax-users.php` (modified) | Saves Nextcloud settings via AJAX |

### Nextcloud App (`v1rontalk/`)

| File | Purpose |
|------|---------|
| `appinfo/info.xml` | App metadata for NC 30-32 |
| `appinfo/routes.php` | Route registration |
| `lib/AppInfo/Application.php` | App bootstrap, Talk event listener registration |
| `lib/Service/V1RonApiService.php` | HTTP client for WordPress REST API |
| `lib/Service/TalkBotService.php` | Talk bot registration + incoming message handler |
| `lib/Service/FileService.php` | Read/write/search/share Nextcloud files |
| `lib/Controller/BotController.php` | Talk bot webhook handler |
| `lib/Controller/FileApiController.php` | File operations API (read/write/search/list/share) |
| `lib/Controller/V1RonApiController.php` | Proxy for frontend → WordPress API |
| `lib/Controller/SettingsController.php` | Admin settings save/load |
| `lib/Settings/Admin.php` | Admin settings form provider |
| `lib/Settings/AdminSection.php` | Admin settings section |
| `src/main.js` | Vue.js app entry point |
| `src/components/CharacterList.vue` | Character selection grid |
| `src/components/ChatView.vue` | Chat interface with file attach support |
| `src/components/FileShareDialog.vue` | Nextcloud file browser |
| `src/mixins/api.js` | API mixin for WordPress proxy |
| `templates/settings.php` | Admin settings HTML template |
| `package.json` / `webpack.config.js` | Build tooling |

## Installation

### Step 1: WordPress Setup

1. Apply the modified V1RonDHM plugin files (`class-nextcloud-bridge.php`, modified main file, admin traits)
2. Go to **WordPress Admin → V1Ron → Settings**
3. Scroll to the **"☁️ Nextcloud Bridge"** section
4. Click **"Generate Random Key"** to create an API key
5. Copy the key — you'll need it for Nextcloud
6. Click **"Save Settings"**

### Step 2: Nextcloud App Installation

```bash
# 1. Copy the app to Nextcloud's apps directory
cp -r v1rontalk /var/www/nextcloud/apps/

# 2. Install PHP dependencies
cd /var/www/nextcloud/apps/v1rontalk
composer install --no-dev

# 3. Build frontend assets
npm install
npm run build

# 4. Enable the app via occ
sudo -u www-data php /var/www/nextcloud/occ app:enable v1rontalk
```

### Step 3: Configure the Bridge

1. Go to **Nextcloud → Admin Settings → V1Ron Talk Bot**
2. Enter your **WordPress Site URL** (e.g., `https://your-wordpress-site.com`)
3. Enter the **API key** you generated in WordPress
4. Create a dedicated **bot system user** in Nextcloud (e.g., `v1ron_bot`) and enter their user ID

## How It Works

### Talk Bot Integration
- Characters from WordPress are auto-registered as **bots in Nextcloud Talk**
- Users can search for characters in Talk's contact list and start conversations
- Messages flow: **Talk → V1RonTalk App → WordPress REST API → V1RonDHM Chat Engine → LLM → response back to Talk**

### File Access
- Characters can **read files**: Say `Read file \`Documents/notes.txt\``
- Characters can **search files**: Say `Find files about budget`
- Characters can **write files**: Say `Save this to \`output/report.md\``
- Characters can **list directories**: Say `List my Documents folder`
- Use the **📎 button** in the chat UI to browse and attach files visually

### File Command Tags
Characters can autonomously access files using structured tags in their responses:
- `[FILE_SAVE \`path\`]content[/FILE_SAVE]` — Save content to a file
- `[FILE_READ]path[/FILE_READ]` — Read and display file content
- `[FILE_LIST]dir[/FILE_LIST]` — List directory contents

## API Endpoints

### WordPress REST API (`/wp-json/v1ron/v1/`)
All endpoints require `X-V1Ron-API-Key` header.

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/characters?user_id=X` | List characters |
| `GET` | `/characters/{id}?user_id=X` | Get character details |
| `POST` | `/characters/{id}/chat` | Send chat message |
| `GET` | `/characters/{id}/messages?user_id=X` | Get chat history |
| `DELETE` | `/characters/{id}/messages` | Clear chat history |
| `POST` | `/characters/{id}/knowledge` | Ingest file as RAG knowledge |
| `GET` | `/memories?user_id=X` | Get user memories |
| `POST` | `/memories` | Save a memory |
| `GET` | `/user/balance?user_id=X` | Get credit balance |
| `POST` | `/user/info` | Sync/register a Nextcloud user |

### Nextcloud API (internal, served by the app)
All endpoints require user authentication.

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/apps/v1rontalk/api/file/read` | Read file content |
| `POST` | `/apps/v1rontalk/api/file/write` | Write/save file |
| `POST` | `/apps/v1rontalk/api/file/search` | Search files by name |
| `POST` | `/apps/v1rontalk/api/file/list` | List directory contents |
| `POST` | `/apps/v1rontalk/api/v1ron/proxy` | Proxy to WordPress API |

## Requirements

- **WordPress**: 6.0+ with V1RonDHM plugin 2.23+
- **Nextcloud**: 30-32 (tested on 31.0.6 / Hub 10)
- **Nextcloud Talk**: 18+ (included with NC 30+)
- **PHP**: 8.0+
- **Servers**: WordPress and Nextcloud can be on **different servers** (REST API over HTTPS)
