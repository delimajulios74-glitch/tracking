# Sample data import

`SampleSystemDataSeeder` adds clearly marked test assets, damage reports, maintenance tasks, maintenance logs, and task activity history. It is a standalone seeder and is intentionally not called by the normal `DatabaseSeeder`.

## Before importing

1. Use a development/test database, not production, and take a backup if the database contains real records.
2. Run the existing migrations.
3. Ensure the database already has at least one **active** `admin` (Property Custodian), `maintenance`, and `end_user` account. This seeder reuses those users and never creates accounts. Create any missing accounts through the existing admin user-management flow.

## Import

From `backend/`, run:

```powershell
php artisan db:seed --class=SampleSystemDataSeeder
```

The seeder is additive and repeatable. It uses reserved `DEMO-LGU-2026-*` asset tags, `SAMPLE-RPT-2026-*` report numbers, and `[SAMPLE DATA]` markers. Existing rows are not updated or restored. If a reserved asset tag, report number, or task title is already occupied by an unmarked record, seeding stops with an error before writing sample rows. Do not run `migrate:fresh` to import sample data.

The import creates 200 assets across 10 categories, 30 damage reports, 30 maintenance tasks (20 completed, 5 in progress, and 5 pending inspection), and 30 maintenance logs. Completed task details include inspection, work, parts, cost, testing, final condition/status, and sample custodian approval. Task activity entries link reports to the existing maintenance-task history.

## Limits of the current schema

This seeder deliberately uses only existing tables and fields. In this application:

- The asset record has no quantity, unit, assigned-custodian/user, or department column. Office/department distribution is represented through the existing `locations` lookup and the asset's `room_area`.
- Damage reports have no separate incident-date field; `created_at` is used as the report date. Sample photo paths are left empty.
- Maintenance tasks and logs do not have dedicated columns for every requested form field. Additional inspection, repair, cost-breakdown, final-inspection, and approval details are stored in the task's existing `instructions`/`remarks` and log fields.
- End-user reports are linked to maintenance tasks through a dedicated `end_user_report_linked` activity-log entry, avoiding a database schema change. Assigning a pending report moves it to `in_review`; completing its linked task moves it to `resolved`, while cancelling the task returns it to `pending`.
- Existing accepted conditions are `Good`, `Fair`, `Poor`, and `Unserviceable`; asset statuses are `Available`, `In Use`, `Under Maintenance`, and `Disposed`. Unsupported values such as `Damaged`, `Borrowed`, and `Under Inspection` are not inserted.
- The existing end-user equipment API does not include an employee-to-asset assignment relationship, and the current API routes do not provide an admin damage-report listing. This seeder does not add schema, endpoints, or permissions.

The app's existing role and route rules remain unchanged. End-user reports are associated with existing active end-user accounts; maintenance tasks are distributed among existing active maintenance accounts and assigned by an existing active admin.
