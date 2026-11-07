# End User Role

Feature context for the AI coding agent. Source: SRS section 2.3.3 (End User), hard constraints in 2.4.1, use case Figure 10.0, and interface Figure 21.0.

Read `AGENTS.md` first for the stack, versions, and general conventions. This file only covers the **End User** role.

## 1. Who the End User is

An ordinary LGU-Trinidad employee or personnel who uses or is assigned property and equipment. They can **look at equipment and report problems**. They have **no authority over official records**.

This is the lowest-privilege role in the system.

| Role (name in SRS)                    | Suggested `users.role` value | Access |
| ------------------------------------- | ---------------------------- | ------ |
| Inventory Officer / Property Custodian | `admin` (keep the value already in the code) | Full access |
| Maintenance Report User               | `maintenance` (keep the value already in the code) | Maintenance functions only |
| **End User (this file)**              | `end_user`                   | View equipment, report problems, track own reports |

> Use the existing role enum/column. **Add `end_user` as a new allowed value**; do not rename the existing values. If the `role` column is an ENUM, write a migration that adds the value.

## 2. What the End User can do

From the use case diagram (Figure 10.0):

1. **Login** (authenticate with valid credentials).
2. **View Dashboard**: equipment summary and submitted reports summary.
3. **View Equipment List**: with **search**.
4. **View Equipment Details**: view asset information.
5. **Report Damaged Equipment**: fill and submit a report form.
6. **My Reports**: track my reports, view report details, view report status.
7. **My Account**: update personal information, change password.

## 3. What the End User must NOT be able to do (hard constraints)

- Register, edit, delete, or dispose of assets.
- See or modify **acquisition or financial information** (see section 6 for the exact fields to hide).
- Manage users or open any administrative settings.
- Modify official inventory records, checklists, or reconciliation records.
- Access Maintenance, Locations management, Reports & Analytics, or Users modules.
- Reach any of the above **by typing a URL or calling the API directly**. Denial must be enforced on the backend.

## 4. Screens (sidebar and layout)

Follow the prototype in Figure 21.0 and match the layout the other roles already use (dark green sidebar, LGU-Trinidad logo at top-left, top bar with notification bell and the logged-in user's name and role).

Sidebar items for `end_user` only:

1. Dashboard
2. Equipment list
3. Report Damage Equipment
4. My Reports
5. My Account

Do not render any other sidebar item for this role. Hiding is UX only; the backend check in section 7 is what counts.

### 4.1 Dashboard
- Equipment summary (counts of equipment by status).
- Submitted reports summary (counts of my reports by status, and maybe the latest few).

### 4.2 Equipment List (Figure 21.0)
- Title: "Equipment List". Subtitle: "Browse and search for property and equipment records".
- Search box: placeholder "Search by name, property number, or category..." plus a **Search** button.
- Filters: **All Categories** dropdown and **All Status** dropdown.
- Table columns: **Property No.**, **Equipment Name**, **Category**, **Location**, **Status**, **Actions** (a **View** button).
- Status badges: green = Available, yellow = Under Maintenance, red = Out of Service.
- Pagination at the bottom-right.
- Property No. is the asset tag (`assets.asset_tag`, e.g. `PR-0001`).

### 4.3 Equipment Details
- Read-only page opened from **View**.
- Show only the End User-safe fields in section 6.
- Include a "Report a problem" button that opens the report form with this equipment preselected.

### 4.4 Report Damage Equipment (form)
From the use case, the form has:
- **Select Equipment** (required; searchable dropdown of equipment the user is allowed to see)
- **Select Location** (required; dropdown from `locations`, may default to the selected equipment's location)
- **Enter Description** (required; what is damaged or not working)
- **Upload Photo** (optional)
- **Submit Report**

After submit: show a success message and the new report's reference number, then go to My Reports.

### 4.5 My Reports
- List of the logged-in user's own reports: reference no., equipment, date submitted, **status**.
- Click a row to open **Report Details** (equipment, location, description, photo, status, status history if available).
- A user can only ever see their **own** reports.

### 4.6 My Account
- **Update personal information** (name, contact details; **not** role, status, or username if usernames are admin-controlled).
- **Change password**: require current password, new password, and confirmation; minimum 8 characters.

## 5. Data model

Existing tables used (read-only for this role): `assets`, `categories`, `locations`, `users`, `activity_logs`.

### New table: `asset_requests`

The SRS defines an *Asset Request* as "a damaged report, malfunction report, concern or request submitted by an End User regarding an assigned asset."

| Column         | Type / notes |
| -------------- | ------------ |
| `id`           | bigint, primary key |
| `reference_no` | string, unique, auto-generated (e.g. `RPT-2026-0001`) |
| `asset_id`     | foreign key to `assets` |
| `location_id`  | foreign key to `locations` |
| `reported_by`  | foreign key to `users` (the End User) |
| `type`         | string/enum: `damage`, `malfunction`, `concern`, `request` (default `damage` for the damage report form) |
| `description`  | text, required |
| `photo_path`   | nullable string |
| `status`       | string/enum, default `pending` |
| `created_at`, `updated_at` | timestamps |

**Proposed statuses** (the SRS does not list them, so confirm with the project owner): `pending`, `in_review`, `resolved`, `rejected`. Keep them in one place (a PHP enum and one constant list on the frontend) so they are easy to change.

Index `asset_id`, `reported_by`, and `status`.

> The Admin/Property Custodian and Maintenance roles process these requests later (status updates, creating maintenance records). That is **outside this file**. Only build the End User side now, but design the table so those roles can use it.

## 6. Fields an End User may see

From `assets` and related tables, return **only**:

`asset_tag`, `asset_name`, `category` (name), `location` (name), `room_area`, `brand`, `model`, `serial_number`, `description`, `condition`, `status`.

**Never return to an End User:** `purchase_date`, `purchase_cost`, Acquisition Value, Disquisition Value, Disposable, Disposal Status, `remarks`, or any financial or disposal field.

Enforce this with a dedicated Laravel API Resource (e.g. `EndUserAssetResource`) that whitelists fields. Do not reuse the admin resource and hide fields in React. A hidden column that still appears in the JSON response is a security bug.

## 7. Backend implementation (Laravel 12)

- **Role middleware:** reuse the existing role middleware if there is one; otherwise create it (e.g. `role:end_user`). Allow it to accept multiple roles.
- **Routes:** put End User endpoints in their own group, for example:

  ```
  GET   /api/end-user/dashboard
  GET   /api/end-user/equipment            (search, category_id, status, page)
  GET   /api/end-user/equipment/{asset}
  POST  /api/end-user/reports              (multipart/form-data)
  GET   /api/end-user/reports
  GET   /api/end-user/reports/{assetRequest}
  GET   /api/account                       (shared: any authenticated user)
  PUT   /api/account
  PUT   /api/account/password
  ```

- **Deny by default:** Admin-only and Maintenance-only routes must reject `end_user` with 403, including direct calls. Add a test for this.
- **Own data only:** every query on `asset_requests` is scoped to `reported_by = auth()->id()`. Use a Policy or a global scope. Return 404 or 403 for another user's report, never the data.
- **Form Requests:** `StoreAssetRequestRequest` validates `asset_id` (exists), `location_id` (exists), `description` (required, string, max length), `photo` (nullable, image, jpg/jpeg/png, max 2 MB). Never trust `reported_by` or `status` from the request; set them on the server.
- **Photo storage:** store on a disk, save only the path, generate a safe random file name, and serve through a route or storage link. Never use the original file name.
- **Search:** one query using `LIKE` on `asset_name`, `asset_tag`, and category name, combined with the category and status filters, paginated.
- **Activity log:** write to `activity_logs` when an End User submits a report, logs in, or changes their password.
- **Passwords:** hashed with Laravel's `Hash`/`hashed` cast. Changing a password requires the current password.
- **Sensitive account fields:** `role`, `is_active`, and username must not be updatable through `PUT /api/account`. Whitelist the fields.

### Which equipment can an End User see? (open decision)

The SRS says End Users view **"assigned or authorized"** assets, but the mockup shows a general Equipment List, and the current `assets` table has no assigned-user column. Until confirmed:

- Put the visibility rule in **one place** (a query scope such as `Asset::visibleToEndUser($user)`) so it can be changed in one edit.
- Default for now: all non-disposed assets, read-only, with the restricted fields from section 6.
- If the owner confirms per-user assignment, add an assignment (e.g. `assigned_user_id` on `assets`, or a pivot table) and change only the scope.

## 8. Frontend implementation (React 19 + Vite + Tailwind CSS)

- Add `end_user` handling to the existing auth context and route guard. After login, redirect by role (End User goes to its Dashboard).
- Route guard: an `end_user` opening an admin or maintenance URL is redirected away; the API returns 403 anyway.
- Keep API calls for this role in a service file (e.g. `services/endUserApi.js`), not inside components.
- Every list or detail view needs loading, empty, and error states.
- The report form needs client-side validation (required fields, image type and size) that mirrors the backend rules, and must show the server's validation errors.
- Use `FormData` for the report submit because of the optional photo.
- Reuse the existing layout, sidebar, table, badge, and pagination components. Do not create a second design system.

## 9. User management changes (Admin side)

- Add **End User** to the Role dropdown on the Users page / Add user form.
- Public registration, if it stays open, must default new accounts to the **lowest role, `end_user`**, and the register request must never accept `role`.

## 10. Known inconsistencies to confirm (do not guess)

1. SRS 3.5.3 says login with "username/email", the Users prototype shows an email column, and earlier project notes say login by `username`. Use whatever the existing auth code does and do not change it without asking.
2. Report statuses and request `type` values are proposed here, not specified in the SRS.
3. "Assigned or authorized assets" rule (section 7).
4. The SRS marks the End User role as *proposed*; the name and permissions are subject to confirmation by LGU-Trinidad. Keep the role name and permission list in config or a single constant so a rename is a small change.

## 11. Acceptance checklist

- [ ] An `end_user` account can log in and sees only the 5 sidebar items.
- [ ] Equipment list supports search, category filter, status filter, and pagination.
- [ ] Equipment details never include any field from the "Never return" list (check the raw JSON).
- [ ] End User can submit a damage report with and without a photo, and gets a reference number.
- [ ] End User sees only their own reports; requesting another user's report ID fails.
- [ ] End User calling admin/maintenance endpoints directly gets 403.
- [ ] End User can update personal info and change password (current password required) but cannot change role, status, or username.
- [ ] Asset, user, and inventory records cannot be created, changed, or deleted by an End User.
- [ ] Submissions and password changes appear in `activity_logs`.
- [ ] Feature tests cover role denial, own-data scoping, validation, and field whitelisting.