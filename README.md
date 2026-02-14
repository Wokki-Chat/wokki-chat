# Wokki Chat

A real-time chat application with load-balanced Socket.IO workers.

**Live Site:** https://chat.wokki20.nl/  
**Status Page:** https://status.chat.wokki20.nl/

---

## 🚀 Quick Start

### Prerequisites
- Docker & Docker Compose installed
- Git

### Installation

1. **Clone the repository**
   ```bash
    git clone -b developing https://github.com/levkris/wokki-chat.git
    cd wokki-chat
   ```

2. **Set up environment files**
   
   Rename the example configuration files (they're already set up with correct defaults):
   
   ```bash
   # Root .env file
   cp .env.example .env
   
   # Backend .env file
   cp backend/.env.example backend/.env
   
   # PHP config file
   cp webpage/app/config-example.php webpage/app/config.php
   ```

   **Default values (no need to change unless you modify Docker setup):**
   - **Database:**
     - Host: `db`
     - User: `root`
     - Password: `dev`
     - Database: `wokki_chat`
   
   - **PHP config (`webpage/app/config.php`):**
     ```php
     $host = 'db';
     $db_username = 'root';
     $db_password = 'dev';
     $dbname = 'wokki_chat';
     $allowedOrigin = 'https://localhost:8443';
     $allowedReferer = 'localhost';
     ```

3. **Build and start the containers**
   ```bash
   docker compose build
   docker compose up -d
   ```

4. **Access the application**
   - **Website:** https://localhost:8443
   - **phpMyAdmin:** http://localhost:8081
   > phpMyAdmin must be accessed over http, not https

---

## 👤 First User Setup

After creating your first account:

1. Go to http://localhost:8081 (phpMyAdmin)
2. Login with:
   - **Username:** `root`
   - **Password:** `dev`
3. Navigate to `wokki_chat` database → `users` table
4. Find your account and set `email_verified` to `1`

---

## 🔌 Socket.IO Configuration

By default, the client connects to **Ignis** worker:
```javascript
http://localhost:5001  // Ignis (default)
```

You can change the worker in your frontend code:
```javascript
http://localhost:5002  // Aqua
http://localhost:5003  // Terra
http://localhost:5004  // Ventus
```

### Worker Architecture
- **Proxy:** Port 5000 (load balancer)
- **Ignis:** Port 5001
- **Aqua:** Port 5002
- **Terra:** Port 5003
- **Ventus:** Port 5004

---

## 🔄 Restarting the Application

### Normal restart
```bash
docker compose down
docker compose build --no-cache
docker compose up -d
```

### Full reset (⚠️ DELETES ALL DATA)
```bash
docker compose down -v
docker compose build --no-cache
docker compose up -d
```

> **Warning:** The `-v` flag removes all volumes, including the database. Use only when you need to reset the database schema or clear all data.

---

## 🛠️ Development

### Ports
- **8080:** Apache (HTTP)
- **8443:** Apache (HTTPS)
- **8081:** phpMyAdmin
- **3306:** MariaDB
- **5000:** Proxy (load balancer)
- **5001-5004:** Socket.IO workers

### Database Credentials
- **Host:** `db` (inside Docker) / `localhost` (from host)
- **User:** `root`
- **Password:** `dev`
- **Database:** `wokki_chat`

### Database Schema Changes

**⚠️ IMPORTANT:** If you make any SQL changes (creating, modifying, or deleting tables), you **must** add them to `/database/updates/update.sql` before committing.

This ensures that:
- Database changes are tracked and versioned
- Other developers can apply the same schema updates
- Production deployments include all necessary database migrations

**Example:**
```sql
-- /database/updates/update.sql
ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL;
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 📝 Notes

- Version info is automatically handled by webhooks - no need to manually update `version-info.json` or `update-info.md`
- More Docker improvements coming soon
- Database updates require a full reset with `docker compose down -v`

---

## 📦 Services

The application runs the following Docker containers:
- **web:** Apache/PHP frontend
- **db:** MariaDB database
- **phpmyadmin:** Database management UI
- **proxy:** aiohttp load balancer
- **ignis, aqua, terra, ventus:** Socket.IO worker instances

---

## 🐛 Troubleshooting

### Can't connect to Socket.IO?
Make sure you're connecting to a running worker (5001-5004) or use the proxy on port 5000.

### Database connection failed?
Check that the `db` container is running: `docker ps`

### Port already in use?
Stop existing services on ports 8080, 8443, 3306, or 5000-5004.