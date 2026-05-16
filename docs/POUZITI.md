# Usage Documentation (SU CMS)

This guide covers first run, module installation, admin usage, and form handling.

## 1. First Run

1. Point your web root to the `public` directory.
2. Configure DB access in `config/app.php` (`host`, `username`, `password`, `dbname`).
3. Start local server:

```bash
php -S localhost:8000 -t public
```

4. Open:

- `http://localhost:8000/`
- `http://localhost:8000/api/health`

Note: App bootstrap connects to DB before route dispatch. If DB config is wrong, even `GET /api/health` can fail.

## 2. Module Installation and Update

Endpoint:

- `POST /api/modules/install-all`

### Authorization

Set install token in environment:

```bash
APP_INSTALL_TOKEN=moje-tajne-hodnota
```

Send token via one of:

- `X-Install-Token` header
- `Authorization: Bearer ...` header
- `token` in request payload

### Example: Install all configured modules

```bash
curl -X POST "http://localhost:8000/api/modules/install-all" \
  -H "Content-Type: application/json" \
  -H "X-Install-Token: moje-tajne-hodnota" \
  -d '{"mode":"install"}'
```

### Example: Update selected modules

```bash
curl -X POST "http://localhost:8000/api/modules/install-all" \
  -H "Content-Type: application/json" \
  -H "X-Install-Token: moje-tajne-hodnota" \
  -d '{"mode":"update","modules":["AuthModule","AdminModule"]}'
```

### Status codes from implementation

- `200`: all operations successful
- `207`: partial success (some modules failed)
- `401`: missing or invalid provided token
- `422`: invalid mode or no target modules
- `503`: server is missing configured `APP_INSTALL_TOKEN`

## 3. Admin Panel

After `AuthModule` install, default account is created:

- username: `admin`
- password: `change-me-now`

Web endpoints:

- `GET /admin/login`
- `POST /admin/login`
- `GET /admin`
- `POST /admin/logout`
- `POST /admin/module-action`
- `POST /admin/user-action`

API endpoint:

- `GET /admin/modules/settings`

Recommendation:

- change default password immediately
- do not use default credentials in production

## 4. Working with Forms

Form definitions are loaded from `config/forms` (`.json` or `.php`).

Minimum schema:

- `name`: internal form name
- `action`: endpoint (example `/contact/submit`)
- `method`: `GET` or `POST`
- `fields`: field definitions

Example:

```json
{
  "name": "contact",
  "action": "/contact/submit",
  "method": "POST",
  "ajax": true,
  "fields": {
    "email": {
      "label": "Your Email",
      "name": "your_email",
      "type": "email",
      "rules": ["required", "email"]
    }
  }
}
```

Optional rate limit block:

- `rate_limit.submit.max_requests`
- `rate_limit.submit.window_sec`
- `rate_limit.change.max_requests`
- `rate_limit.change.window_sec`

## 5. Form API Responses (Current Implementation)

### Success (`200`)

```json
{
  "ok": true,
  "form": "contact",
  "trigger": "submit",
  "message": "Formulář byl úspěšně zpracován.",
  "data": {
    "your_email": "john@example.com"
  }
}
```

### Validation error (`422`)

```json
{
  "ok": false,
  "form": "contact",
  "trigger": "submit",
  "message": "Formulář obsahuje validační chyby.",
  "errors": {
    "your_email": "Pole je povinné."
  }
}
```

### Rate limit (`429`)

```json
{
  "ok": false,
  "form": "contact",
  "trigger": "change",
  "message": "Byl překročen limit požadavků, opakuj akci později.",
  "retry_after": 12
}
```

### Not found (`404`)

```json
{
  "ok": false,
  "message": "Pro danou kombinaci endpointu a metody nebyl nalezen formulář."
}
```

## 6. Common Issues

### App cannot connect to DB

- verify credentials in `config/app.php`
- verify DB server is running
- verify DB user has rights to selected database

### `404` on form endpoint

- verify form has correct `action` and `method`
- verify definition file is valid JSON/PHP array
- verify request uses matching HTTP method

### `401`/`503` on module install endpoint

- verify `APP_INSTALL_TOKEN` is configured in environment
- verify token is sent in supported format

## 7. Recommended Operational Flow

1. Configure DB and install token.
2. Run `install-all` in `install` mode.
3. Log in to admin.
4. Change default admin password.
5. Enforce HTTPS.
6. Keep modules updated with `mode=update`.
