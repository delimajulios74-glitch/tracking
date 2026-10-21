# AGENTS.md

Context for AI coding agents working on this project. Read this first before suggesting or writing code.

## Project

- **Name:** Web-Based Property and Equipment Tracking System (LGU-Trinidad)
- **Type:** Capstone project, full-stack web app with a separate frontend and backend
- **Root folder:** `tracking system/`

```
tracking system/
├── frontend/   # React + Vite SPA
└── backend/    # Laravel API
```

## Tech Stack (exact installed versions)

| Layer      | Technology | Version  |
| ---------- | ---------- | -------- |
| Frontend   | React      | 19.3.1   |
| Build tool | Vite       | 8.3.4    |
| Runtime    | Node.js    | 24.22.0  |
| Backend    | Laravel    | 12.70.0  |
| Language   | PHP        | 8.2.12   |
| Database   | MySQL      | TODO: fill in (run `SELECT VERSION();`) |

- OS / dev environment: Windows (PowerShell), local development
- Frontend and backend run as **separate apps**: React talks to Laravel over HTTP (REST/JSON API)

## Version Notes for the Agent

These versions are recent. Your training data may describe older releases, so:

- **Do not assume older APIs.** When unsure about React 19, Vite 8, or Laravel 12 behavior, check the official docs instead of guessing.
- **React 19:** function components and hooks only. No class components. Do not use legacy patterns that were removed or deprecated (e.g. `ReactDOM.render`, `defaultProps` on function components, string refs).
- **Vite 8:** the config file is `vite.config.js` (or `.ts`). Environment variables exposed to the browser must be prefixed with `VITE_` and read through `import.meta.env`.
- **Laravel 12:** uses the streamlined structure introduced in Laravel 11 (middleware, exceptions, and routing are configured in `bootstrap/app.php`, there is no `app/Http/Kernel.php`). API routes live in `routes/api.php`.
- **PHP 8.2:** do not use syntax or features that need PHP 8.3 or newer (e.g. typed class constants, `#[\Override]`).

## Users, Roles, and Registration (DECIDED, follow exactly)

The system has **two roles only**:

| Role value    | Who                          | Can do |
| ------------- | ---------------------------- | ------ |
| `admin`       | Admin / Property Custodian   | Manage users, register/edit assets, assign and transfer assets, mark items for disposal, run inventory checklists, view all reports |
| `maintenance` | Maintenance user             | View assets, create/update maintenance reports (repair, condition, status), view own reports. **Cannot** register, delete, transfer, or dispose assets, and cannot manage users |

**Registration form (keep it simple, only these fields):**
- `username` (required, unique)
- `name` (full name, required)
- `password` (required, min 8 characters)
- `password_confirmation` (must match)

**Rules:**
- The register form has **no role field**. The `role` column defaults to `maintenance` (lowest access).
- Never accept `role` from the registration request. Do **not** put `role` in the User model's `$fillable` for the register flow. Only an admin can change a role, through user management.
- The first `admin` account is created by a **seeder**, not through the form.
- Login uses **`username`**, not email. Replace Laravel's default `email` login field in the migration, validation, and auth logic.
- Add `is_active` (boolean, default true). Deactivate users instead of deleting them so past records still show who did what.
- Enforce access on the **backend** with role middleware (e.g. `role:admin`) on routes. Hiding buttons in React is only UX, not security.
- Passwords are always hashed (`Hash::make` or the `hashed` cast). Never store or log plain text.

Users table columns: `id`, `username` (unique), `name`, `password`, `role` (default `maintenance`), `is_active` (default true), timestamps.

## Asset Registration (requirements)

Required fields: property number (unique, auto-generated preferred), asset name/description, category, date acquired, **acquisition value**, office/department, accountable officer, condition, status.

Other fields: brand/model, serial number, quantity/unit, mode of acquisition, supplier, source of fund, PO/invoice number, warranty expiry, specific location, photo, remarks.

Additional required fields requested for this project:
- **Acquisition Value**
- **Disposable** (yes/no flag, whether the item is up for disposal)
- **"Disquisition Value"**: the name is likely meant to be *Disposition/Disposal value* (salvage/residual value). **Use the exact label the project owner confirms.** Until then, ask before naming this column.

Other requirements:
- Condition values: `new`, `good`, `fair`, `needs_repair`, `unserviceable`.
- Status values: `in_use`, `in_storage`, `under_repair`, `lost`, `transferred`, `for_disposal`, `disposed`.
- Keep a **history table** for transfers, repairs, and status changes.
- Use lookup tables/dropdowns (category, office, condition, status) instead of free text.
- A **printable manual inventory checklist** (A4 layout, filters, PDF export if compatible) must pull from the same asset fields: property number, description, location, accountable officer, condition, plus blank "found" and "remarks" columns.

## Conventions

- Keep frontend and backend concerns separate. No business rules only enforced in the UI; validate on the backend too.
- **Backend:**
  - Use Eloquent models, migrations, and seeders. Never edit the database by hand without a migration.
  - Validate input with Form Requests.
  - Return consistent JSON responses from API controllers.
  - Use API Resources for shaping responses where it helps.
  - Protect routes with authentication middleware and check roles/permissions server-side.
- **Frontend:**
  - Functional components with hooks.
  - Keep API calls in a dedicated service/helper layer, not scattered across components.
  - Handle loading, empty, and error states for every data fetch.
- Match the existing code style and folder structure before introducing new patterns or libraries. Ask before adding a new dependency.

## Common Commands

Run from the matching folder.

```powershell
# Frontend (tracking system/frontend)
npm.cmd install
npm.cmd run dev
npm.cmd run build
npm.cmd list react

# Backend (tracking system/backend)
composer install
php artisan serve
php artisan migrate
php artisan migrate:fresh --seed   # destroys all data, dev only
php artisan route:list
php artisan --version
```

## Environment Notes

- **PowerShell blocks `npm` and `npx`** because of the script execution policy. Use `npm.cmd` and `npx.cmd`, or run once:
  `Set-ExecutionPolicy -Scope CurrentUser -ExecutionPolicy RemoteSigned`
- Backend config is in `backend/.env` (database credentials, app key). Never commit `.env` or paste real secrets into code.
- Frontend env variables go in `frontend/.env` and must start with `VITE_`.
- Because frontend and backend run on different ports in development, CORS must allow the Vite dev origin in Laravel's CORS config.
- Database is MySQL (e.g. via XAMPP or Laragon). Use migrations as the source of truth for the schema.

## Rules for the Agent

- Prefer small, focused changes and explain what you changed and why.
- Do not run destructive commands (`migrate:fresh`, dropping tables, deleting files) without asking first.
- Do not rewrite working code just for style.
- If a request is ambiguous or touches the database schema, confirm before proceeding.
- Never invent package names, Laravel methods, or config options. If unsure, say so and check the docs.

## Open Items

- [ ] Fill in the MySQL version above
- [ ] Add authentication approach (e.g. Sanctum) once confirmed
- [ ] Add UI library/styling approach (e.g. Tailwind, MUI) once confirmed
- [ ] Confirm the exact name of the "Disquisition Value" field
- [ ] Decide whether public `/register` stays open or registration moves behind the admin only