# Usage Documentation (SU CMS)

This guide is a practical manual for first run, module installation, admin usage, and form handling.

## 1. First Run

1. Point your web root to the `public` directory.
2. In `config/app.php`, fill in database access (`host`, `username`, `password`, `dbname`).
3. Start the local server:

```bash
php -S localhost:8000 -t public
```

4. Open:

- `http://localhost:8000/`
- `http://localhost:8000/api/health`

If the health endpoint returns a DB connection error, check `config/app.php`.

## 2. Module Installation/Update

Endpoint: `POST /api/modules/install-all`

### Authorization

Set a token in your environment:

```bash
APP_INSTALL_TOKEN=moje-tajne-hodnota
```

Send the token in one of these ways:

- header `X-Install-Token`
- header `Authorization: Bearer ...`
- `token` v JSON payloadu

### Example: Install All Modules

```bash
curl -X POST "http://localhost:8000/api/modules/install-all" \
  -H "Content-Type: application/json" \
  -H "X-Install-Token: moje-tajne-hodnota" \
  -d '{"mode":"install"}'
```

### Example: Update Selected Modules

```bash
curl -X POST "http://localhost:8000/api/modules/install-all" \
  -H "Content-Type: application/json" \
  -H "X-Install-Token: moje-tajne-hodnota" \
  -d '{"mode":"update","modules":["AuthModule","AdminModule"]}'
```

## 3. Admin Panel

After installing `AuthModule`, a default account is created:

- username: `admin`
- password: `change-me-now`

Available endpoints:

- `GET /admin/login` (form)
- `POST /admin/login` (login)
- `GET /admin` (dashboard)
- `POST /admin/logout`

Recommendation:

- change the password after first login
- do not use default credentials in production

## 4. Working with Forms

Form definitions belong in `config/forms` as `.json` or `.php` files.

Minimum schema:

- `name`: internal form name
- `action`: endpoint (e.g. `/contact/submit`)
- `method`: `GET` or `POST`
- `fields`: field definitions

Example of a simplified form definition:

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

### Rate limit

Optional `rate_limit` block:

- `submit.max_requests`, `submit.window_sec`
- `change.max_requests`, `change.window_sec`

When the limit is exceeded, the API returns status `429`.

### Validation

For invalid data, the API returns status `422` with an `errors` field.

## 5. Typical Form API Responses

### Success (200)

```json
{
  "ok": true,
  "form": "contact",
  "trigger": "submit",
  "message": "Form was processed successfully.",
  "data": {
    "your_email": "john@example.com"
  }
}
```

### Validation Error (422)

```json
{
  "ok": false,
  "form": "contact",
  "trigger": "submit",
  "message": "Form contains validation errors.",
  "errors": {
    "your_email": "Email field is required."
  }
}
```

### Rate limit (429)

```json
{
  "ok": false,
  "form": "contact",
  "trigger": "change",
  "message": "Request limit exceeded, try again later.",
  "retry_after": 12
}
```

## 6. Common Issues

### App Cannot Connect to DB

- verify credentials in `config/app.php`
- verify the DB server is running
- verify the DB user has access rights to the selected database

### 404 on a Form Endpoint

- verify the form has `action` and `method`
- verify the definition file is valid JSON/PHP array
- verify you call the endpoint with the same HTTP method

### 401/403 on install-all

- verify `APP_INSTALL_TOKEN`
- verify you send the token in a supported format

## 7. Recommended Operational Flow

1. Configure DB and token.
2. Run `install-all`.
3. Log in to admin.
4. Change default admin password.
5. Enforce HTTPS.
6. Keep modules updated using `mode=update`.
