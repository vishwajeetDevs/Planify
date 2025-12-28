# Planify - InfinityFree Deployment Guide

## Step 1: Upload Files

Upload ALL files from the `planify` folder directly to `htdocs` on InfinityFree.

Your InfinityFree structure should look like:
```
htdocs/
├── actions/
│   └── auth/
│       └── login.php
├── public/
│   └── login.php
├── config/
├── assets/
├── includes/
├── uploads/
├── .htaccess
├── .env  (create this - see below)
└── index.php
```

**IMPORTANT:** Do NOT create a `planify` subfolder on InfinityFree. Upload everything directly to `htdocs`.

## Step 2: Create Database

1. Go to InfinityFree Control Panel → MySQL Databases
2. Create a new database
3. Note down:
   - Database Host (e.g., `sql123.great-site.net`)
   - Database Name (e.g., `if0_12345678_planify`)
   - Database Username (e.g., `if0_12345678`)
   - Database Password

4. Import `database.sql` using phpMyAdmin

## Step 3: Create .env File

Create a file named `.env` in `htdocs` with:

```
APP_NAME=Planify
APP_ENV=production
APP_DEBUG=false
APP_KEY=generate-a-random-32-character-key

DB_HOST=sql123.great-site.net
DB_PORT=3306
DB_NAME=if0_12345678_planify
DB_USER=if0_12345678
DB_PASSWORD=your_database_password
DB_CHARSET=utf8mb4

SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=Lax
```

Replace the database values with your actual InfinityFree database credentials.

## Step 4: Set Permissions

Via File Manager, set permissions:
- Folders: 755
- Files: 644
- `uploads/` folder: 755

## Step 5: Access Your Site

Visit: `https://planify-task.great-site.net/public/login.php`

Or for the main page: `https://planify-task.great-site.net/`

## Important Notes

1. **AI Chatbot**: Will NOT work on InfinityFree (firewall blocks external APIs). The fallback mode will handle basic queries.

2. **Email**: SMTP may not work on InfinityFree due to restrictions. Test and configure accordingly.

3. **Uploads**: Make sure the `uploads` folder is writable.

## Troubleshooting

**404 Error on login:**
- Make sure `actions` folder is uploaded
- Check file permissions (644 for files)

**Database connection error:**
- Verify credentials in `.env`
- Check if database host is correct (not `localhost`)

**Blank page:**
- Enable APP_DEBUG=true temporarily to see errors
- Check InfinityFree error logs

