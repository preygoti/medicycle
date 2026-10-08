# MediCycle — Production Deployment Manual

This document details the complete deployment process for hosting **MediCycle** live on production servers supporting PHP 8.1+ and MySQL / MariaDB.

---

## 1. Hosting Environment Prerequisites

* **Web Server:** Apache 2.4+ (with `mod_rewrite` enabled) OR Nginx 1.20+
* **PHP:** PHP 8.1, 8.2, or 8.3 with extensions:
  * `pdo_mysql`
  * `mbstring`
  * `openssl`
  * `curl`
  * `session`
* **Database:** MySQL 8.0+ or MariaDB 10.4+
* **SSL Certificate:** Active TLS/HTTPS certificate (Let's Encrypt or Cloudflare)

---

## 2. Option A: Deployment on Shared Hosting (cPanel / Hostinger / Namecheap)

### Step 1: Upload Files
1. Compress the contents of the `medicycle` directory (excluding git logs and temp folders) into a `.zip` archive.
2. In your hosting cPanel, open **File Manager**.
3. Navigate to `public_html` (or your subdomain directory, e.g. `public_html/medicycle`).
4. Upload and extract the archive into this directory.

### Step 2: Database Setup
1. In cPanel, navigate to **MySQL Databases**.
2. Create a new database, e.g. `u123456_medicycle`.
3. Create a new database user and assign a secure password.
4. Add the user to the database with **ALL PRIVILEGES**.
5. Open **phpMyAdmin**, select `u123456_medicycle`, click **Import**, and import `database/medicycle_db.sql`.

### Step 3: Production Environment Configuration
1. In File Manager, create or edit the file `.env` in the project root:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=u123456_medicycle
   DB_USER=u123456_user
   DB_PASS=YourSecureProductionPassword!
   APP_URL=https://yourdomain.com
   ```
2. Verify that `config/database.php` loads the environment variables correctly.

### Step 4: File Permissions & Security
Set appropriate UNIX permissions:
* Files: `644`
* Directories: `755`
* `uploads/` directory: `775` (writable by web server)

Ensure `.htaccess` restricts direct access to sensitive configuration files:
```apache
<FilesMatch "^\.env|composer\.json|medicycle_db\.sql">
    Order allow,deny
    Deny from all
</FilesMatch>
```

---

## 3. Option B: Deployment on a Linux Cloud VPS (Ubuntu / Debian + Nginx / Apache)

### Step 1: Install Core Packages
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y apache2 php8.2 php8.2-mysql php8.2-curl php8.2-mbstring mysql-server git unzip
```

### Step 2: Configure Production Database
```bash
sudo mysql -u root
```
```sql
CREATE DATABASE medicycle_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'medicycle_admin'@'localhost' IDENTIFIED BY 'StrongProdPassword2026!';
GRANT ALL PRIVILEGES ON medicycle_prod.* TO 'medicycle_admin'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```
Import the schema:
```bash
mysql -u medicycle_admin -p medicycle_prod < /var/www/medicycle/database/medicycle_db.sql
```

### Step 3: Configure Virtual Host (Apache)
Create `/etc/apache2/sites-available/medicycle.conf`:
```apache
<VirtualHost *:80>
    ServerName medicycle.yourdomain.com
    DocumentRoot /var/www/medicycle

    <Directory /var/www/medicycle>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/medicycle_error.log
    CustomLog ${APACHE_LOG_DIR}/medicycle_access.log combined
</VirtualHost>
```
Enable the site and rewrite module:
```bash
sudo a2ensite medicycle.conf
sudo a2enmod rewrite headers
sudo systemctl reload apache2
```

### Step 4: Install Free SSL with Let's Encrypt (Certbot)
```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d medicycle.yourdomain.com
```

---

## 4. Pre-Flight Production Checklist

Before officially declaring the live URL ready for evaluation:

- [x] Database tables and relations imported cleanly.
- [x] Passwords hashed using standard `password_hash()` bcrypt.
- [x] Default production administrator credentials updated.
- [x] HTTPS redirection enforced.
- [x] PHP display errors turned off in production (`display_errors = Off`).
- [x] `uploads/` folder is write-enabled.
- [x] End-to-end requisition, approval, and delivery flow verified.
