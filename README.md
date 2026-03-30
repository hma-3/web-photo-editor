# PhotoBooth

PhotoBooth is a PHP web application for capturing or uploading photos, adding overlays, and sharing results in a public gallery.  
It includes user authentication, image publishing, likes, comments, and account settings.

## Features

- Webcam capture and file upload
- Overlay/sticker support with server-side compositing
- Public gallery with pagination
- Likes and comments on published images
- User accounts with email verification and password reset
- Personal "My images" management page

## Tech Stack

- PHP 8.x
- MySQL or MariaDB
- Vanilla JavaScript
- CSS

## Requirements

Before running locally, make sure you have:

- PHP 8.x with extensions: `pdo_mysql`, `gd`, `mbstring`
- MySQL 5.7+ or MariaDB 10+
- A local web server, or PHP built-in server

## How To Run Locally

### 1) Clone and enter the project

```bash
git clone https://github.com/hma-3/web-photo-editor.git
cd web-photo-editor
```

### 2) Configure database connection

Open `config/database.php` and set:

- `$DB_DSN` (example: `mysql:host=127.0.0.1;dbname=photobooth;charset=utf8mb4`)
- `$DB_USER`
- `$DB_PASSWORD`

Create the `photobooth` database first, or allow your DB user permissions to create it.

### 3) Initialize database schema

```bash
npm run init-db
```

Warning: this script drops and recreates app tables.

### 4) Start the development server

```bash
npm start
```

Then open [http://localhost:8000/](http://localhost:8000/).

## Email Behavior in Local Development

Registration verification and password reset use PHP `mail()`.  
If mail is not configured locally, the app shows verification/reset links in flash messages so development can continue.

## Project Structure

- `index.php` - front controller
- `app/pages/` - page templates
- `app/blocks/` - shared layout blocks (header/footer)
- `api/` - backend endpoints
- `config/` - database config and setup script
- `assets/` - styles and front-end scripts

## Security Notes

- Passwords are hashed using `password_hash()`
- Forms are protected with CSRF tokens
- Output is escaped before rendering
- Database access uses PDO prepared statements
