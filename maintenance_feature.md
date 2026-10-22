# Feature Task: Maintenance Logs

Read `feature_context.md` and `feature-tasks/assets-feature.md` first. **The Assets feature must be finished before this one**, because every maintenance log belongs to an asset. The ERD is the source of truth for table and column names.

## 1. Scope

### Phase A (build now): Maintenance Logs
The Inventory Officer / Property Custodian logs maintenance activities and repair history for assets, per the ERD table `maintenance_logs`.

### Phase B (do NOT build yet): Maintenance Reports
The proposed **Maintenance Report User** role needs to record observations, issues, recommended repairs, and maintenance status. The current ERD has no columns for these and no way to tie a log to the user who recorded it. This needs an ERD revision approved first (see section 7). Only prepare for it; do not implement it in this task.

## 2. Data Model (from ERD)

### maintenance_logs
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| asset_id | bigint FK -> assets.id | required |
| maintenance_type | varchar | required (e.g., Preventive, Repair, Inspection, Cleaning) |
| date | date | required, date the work was done |
| performed_by | varchar | required, name of the person or company who did the work (free text, not a user FK) |
| description_of_work | text | required |
| parts_replaced | text | nullable |
| cost | decimal(12,2) | nullable, >= 0 |
| remarks | text | nullable |
| created_at, updated_at | timestamp | |

### Relationships
- `Asset` hasMany `MaintenanceLog`; `MaintenanceLog` belongsTo `Asset`
- Each maintenance action is also written to `activity_logs` (existing table: `user_id`, `asset_id`, `action`, `description`, `created_at`)

Add indexes on `asset_id` and `date`. Keep the allowed `maintenance_type` values in one config/enum so they are easy to change. Use varchar with app-level validation.

## 3. Backend Requirements

### Endpoints (REST, JSON, authenticated)
| Method | Path | Purpose |
|---|---|---|
| GET | `/api/maintenance-logs` | Paginated list across all assets, with filters |
| GET | `/api/assets/{asset}/maintenance-logs` | Maintenance history of one asset |
| POST | `/api/assets/{asset}/maintenance-logs` | Log maintenance for an asset |
| GET | `/api/maintenance-logs/{id}` | Single log details |
| PUT/PATCH | `/api/maintenance-logs/{id}` | Edit a log |
| DELETE | `/api/maintenance-logs/{id}` | Delete a log (soft delete preferred) |

### List filters
- Search (`q`): description_of_work, parts_replaced, performed_by, and the asset's tag or name.
- Filters: `asset_id`, `maintenance_type`, date range, cost range.
- Sort by date (default newest first) and paginate (default 15).

### Validation (Form Requests)
- `asset_id` must exist and the asset must not be soft deleted.
- `maintenance_type` in the allowed list; `date` valid, not in the future.
- `performed_by` and `description_of_work` required.
- `cost` numeric, >= 0.

### Activity logging
- On create, update, and delete, write an `activity_logs` row using the authenticated `user_id` and the log's `asset_id`.
- Use actions such as `maintenance_logged`, `maintenance_updated`, `maintenance_deleted`, with a readable description (type, date, performed by, and old -> new values on update).
- Save the maintenance log and the activity entry in one DB transaction.

### Asset status interaction (decision needed)
Proposed default: logging maintenance does **not** change the asset's `status` or `condition` automatically. The officer updates those through the asset edit screen. If the owner wants auto-updates (e.g., set to Under Maintenance), add it as an explicit option on the form rather than a hidden side effect, and log the status change.

### Access control
- Inventory Officer / Property Custodian (and the existing admin role, if any): full create, read, update, delete.
- Maintenance Report User: no write access in Phase A. Read-only access to maintenance info and history of relevant assets can be added with Phase B.
- Enforce on the server with a policy or middleware, not only in the UI.

## 4. Frontend Requirements

Follow the existing templates, layout, and styling.

- **Maintenance tab on the asset detail page:** history table for that asset, with an "Add maintenance log" button.
- **Add / Edit maintenance form:** type dropdown, date, performed by, description of work, parts replaced, cost, remarks, with inline validation errors.
- **Maintenance logs list page:** all logs with search and filters, linking to each asset.
- Confirmation dialog before delete.
- Show total maintenance cost per asset on the asset's maintenance tab.

## 5. Tests

- Create, read, update, delete happy paths.
- Validation failures (missing required fields, negative cost, future date, nonexistent or deleted asset).
- Filters and search.
- Activity log rows created for each action.
- Unauthorized roles get 403 on write endpoints.
- Deleting or soft deleting an asset does not leave broken references.

## 6. Acceptance Checklist

- [x] Migration matches the maintenance table definition above, with the foreign key and indexes.
- [x] Model, relationships, and seeder with a few sample logs.
- [x] All endpoints work with validation and role protection.
- [x] Every maintenance change creates an `activity_logs` entry in the same transaction.
- [x] Asset detail shows maintenance history and total cost.
- [x] Global list page search and filters work.
- [x] Tests pass.
- [x] No Phase B features built; pre-existing unrelated worktree changes were preserved.

## 7. Phase B Preparation Notes (for later, needs ERD approval)

To support the Maintenance Report User, the ERD would likely need:
- A way to record who submitted each report (`user_id` FK to `users`).
- Fields for observations, issues, recommended repairs, and maintenance status (either new columns on `maintenance_logs` or a separate `maintenance_reports` table).
- A `users.role` value for the Maintenance Report User.
- Print and export (PDF) of reports.

Do not add any of this until the ERD is updated and approved.

## 8. Open Questions

1. `maintenance_type` values: Preventive, Repair, Inspection, Cleaning.
2. Logging maintenance does not automatically change asset status or condition.
3. Maintenance logs use hard deletion because the approved table definition has no soft-delete column; activity history is retained.
4. Phase B table design remains open pending ERD revision and approval.