# SU CMS

A lightweight modular CMS in pure PHP (no framework), focused on API endpoints, dynamic forms, and simple module-based extensibility.

## Features

- web and API endpoints using a custom router
- automatic form endpoint registration from definitions in `config/forms`
- form validation, AJAX responses, and rate limiting (`submit` and `change` triggers)
- modular architecture (`AdminModule`, `AuthModule`) with lifecycle methods: `install`, `update`, `uninstall`
- simple admin interface and user/role authentication

## Quick Start

### 1) Requirements

- PHP 8.0+ (8.1+ recommended)
- MySQL/MariaDB
- enabled `mysqli` extension
- web root pointing to the `public` directory

### 2) Database Configuration

Edit the `database` section in `config/app.php`:

```php
'database' => [
		'host' => 'localhost',
		'port' => 3306,
		'username' => 'default_user',
		'password' => 'default_pass',
		'dbname' => 'default_db',
],
```

### 3) Run Locally

Option A: built-in PHP server

```bash
php -S localhost:8000 -t public
```

Option B: Apache/Nginx

- Set `DocumentRoot` to `.../cms/public`

### 4) Verify the App Is Running

- open `GET /`
- open `GET /api/health`

## Module Installation (Initial Bootstrap)

Set a secret token in your environment:

```bash
APP_INSTALL_TOKEN=moje-tajne-hodnota
```

Then call the endpoint to install all modules:

```bash
curl -X POST "http://localhost:8000/api/modules/install-all" \
	-H "Content-Type: application/json" \
	-H "X-Install-Token: moje-tajne-hodnota" \
	-d '{"mode":"install","modules":["AuthModule","AdminModule"]}'
```

Notes:

- if `modules` is omitted, modules are taken from `config/app.php` (`modules`)
- `mode` can be `install` or `update`
- token can also be sent via `Authorization: Bearer ...` or as `token` in payload

## First Admin Login

After installing `AuthModule`, a default user is created:

- username: `admin`
- password: `change-me-now`

Admin login routes:

- `GET /admin/login`
- `POST /admin/login`
- `GET /admin`

Change the password immediately after the first login.

## Form subsystem

Form definitions can be created as `*.json` or `*.php` in `config/forms`.

Minimum required fields:

- `name`
- `action` (endpoint, e.g. `/contact/submit`)
- `method` (`GET` or `POST`)
- `fields`

Example (`config/forms/contact.json`):

```json
{
	"name": "contact",
	"action": "/contact/submit",
	"method": "POST",
	"ajax": true,
	"submit_on_change": true,
	"debounce_ms": 500,
	"rate_limit": {
		"enabled": true,
		"submit": { "max_requests": 5, "window_sec": 60 },
		"change": { "max_requests": 20, "window_sec": 30 }
	},
	"fields": {
		"name": {
			"label": "Your Name",
			"name": "your_name",
			"type": "text",
			"rules": ["required", "min:3"]
		}
	}
}
```

Form handling is implemented in `App\\Controllers\\Web\\FormController::handle`:

- validation errors return HTTP `422`
- rate limit violations return HTTP `429`
- successful submission returns HTTP `200`

## Modules

Active modules are listed in `config/app.php`:

```php
'modules' => [
		'AdminModule',
		'AuthModule',
],
```

Each module implements `Core\\Modules\\ModuleInterface` and can register:

- routes
- DB schema (`install`/`update`)
- cleanup (`uninstall`)
- settings (`settings`)

## Important Endpoints

### Core

- `GET /`
- `GET /api/health`
- `POST /api/modules/install-all`

### AuthModule

- `GET /auth/status`
- `GET /auth/me`
- `POST /auth/login`
- `POST /auth/logout`
- `POST /auth/users`
- `PUT /auth/users/{id}/activate`
- `PUT /auth/users/{id}/deactivate`
- `DELETE /auth/users/{id}`
- `POST /auth/users/{id}/roles`
- `DELETE /auth/users/{id}/roles/{roleId}`
- `POST /auth/roles`
- `PUT /auth/roles/{id}/permissions`

### AdminModule

- `GET /admin`
- `GET /admin/login`
- `POST /admin/login`
- `POST /admin/logout`
- `POST /admin/module-action`
- `POST /admin/user-action`
- `GET /admin/modules/settings`
- `POST /admin/modules/install/{module}`
- `POST /admin/modules/update/{module}`
- `DELETE /admin/modules/uninstall/{module}`

## Production Security Checklist

- never use default DB credentials from `config/app.php`
- set a strong `APP_INSTALL_TOKEN`
- change the default admin password `change-me-now` after bootstrap
- enforce HTTPS (secure cookies)
- store logs outside public web root

## Usage Documentation

Find the step-by-step usage guide in `docs/POUZITI.md`.

## Developer Documentation

For module development and creating standard web/API endpoints, see `docs/DEVELOPMENT.md`.

## Project Structure (Short)

```text
app/
config/
core/
modules/
public/
storage/
```

## License

This project is licensed under the Apache License 2.0.

See `LICENSE.txt` for the full license text and `NOTICE` for attribution details.
