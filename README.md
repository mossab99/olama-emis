# OLAMA EMIS

The standalone Ministry statistics plugin owns year-specific EMIS modules. Stages 1 (schools), 2 (buildings), 3 (classrooms), 4 (facilities), and 7 (students) have admin pages under **OLAMA EMIS**.

## Stages 2–4 campus records

Open **البيانات الأساسية للبناء** to add a building, save its annual profile, and add its floors. Open **بيانات الغرف الصفية** or **غرف غير صفية** to add/edit rooms in batches. A shared physical register prevents duplicate room numbers across both modules. Annual use and archival preserve the history when a classroom becomes a laboratory or a room stops being used.

Fields, tables, summaries and transaction rules are documented in [docs/campus-field-map.md](docs/campus-field-map.md). Optional additions are marked in the form. All campus tables require InnoDB. School/lab/floor totals are compared to their manual profile values. Teacher capacity and sanitary units are counted separately from room totals.

`php tests/campus-integration-test.php` exercises actual WordPress `wpdb`/`dbDelta` and MySQL, using `EMIS_TEST_WP_ROOT`, `EMIS_TEST_DB_HOST`, `EMIS_TEST_DB_USER`, `EMIS_TEST_DB_PASSWORD` and a disposable `EMIS_TEST_DB_NAME` beginning with `emis_test_`. It creates and drops only that test database. It covers repeatable schema installation, annual history, rollback, room uniqueness, stale edits, floor scope, archival, summary counts and admin/viewer rendering.

## Stage 1 school profile

The annual school profile is stored in `$wpdb->prefix . 'emis_schools'` (`wp_emis_schools` with the default prefix), keyed by Core `school_id` and academic year. It saves a draft with field values in `profile_json`, search columns for school name/number/national ID, audit users/timestamps, and a revision for concurrent edit detection. It does not change OLAMA Core or Oracle data. Staff need `olama_users_ministry_view` to read and `olama_users_ministry_configure` to save. Only schools present in Core enrollment can receive a profile.

The field registry and workbook coordinates are in [docs/school-field-map.md](docs/school-field-map.md). The 2025/2026 sheet is provisional; latest Ministry choices and mandatory rules have not yet been supplied. The page saves drafts without claiming they are export ready. School number, school national ID, Core school ID, and Stage 2 building identifiers remain separate.

## Deployment

The `main` branch workflow checks PHP syntax, then deploys this repository to `/home/olama/htdocs/olama.online/wp-content/plugins/olama-emis` on Contabo using the repository's `SERVER_IP` and `SSH_PRIVATE_KEY` Actions secrets. It refuses to overwrite an unmanaged directory, a checkout from another remote, or local server changes. Activate the plugin in WordPress after its first deployment; deployment alone does not activate it.

## Stage 7 data contract

- The table is `$wpdb->prefix . 'emis_students'` (`wp_emis_students` on a default WordPress prefix). One row is keyed by `student_uid + study_year`.
- `student_uid` must exist in OLAMA Core and have an enrollment in the selected school/year. Oracle sync continues to own the canonical student and enrollment records. EMIS saves no changes to those source tables.
- National number and non-Jordanian passport/ID number are separate fields. Non-Jordanian document type permits only `جواز سفر` or `هوية`.
- An EMIS national number cannot be assigned to a different student in either the EMIS table or OLAMA Core.
- `extra_json` is reserved for versioned additions from the latest Ministry template; the current UI does not write it.
- The legacy family completion and review workflow in Gateway/Users/Core stays functional during migration. The new plugin does not copy or delete accepted values or pending submissions. Their field-level approvals need explicit mapping when the new template arrives.
- The individual editor displays approved legacy Ministry values as defaults for matching fields. They remain in their original store until a staff member explicitly saves the new dossier.
- There is no family 634 restriction in the current local Gateway, Users, Core, or EMIS code. The general family form availability switch remains.

## Import and roster

Download the CSV template from the stage 7 page. The same header keys work in an XLSX first worksheet. A cohort import accepts up to 2,000 records in a file under 5 MB, validates every row against OLAMA Core, and reports row errors. Fields absent from an import preserve existing dossier values. Blank columns in the file clear values.

The grid and PDF roster start with OLAMA Core enrollment, then overlay saved EMIS dossiers. They therefore show students whose Ministry dossier is still pending. The PDF uses the installed `olama-pdf-tools` TCPDF runtime. Building/classroom/stream/grade/section are dossier context, not source enrollment changes; building/classroom filters include only students whose EMIS context has been saved.

## Next template revision

The 2025–2026 workbook visible in Excel has additional page 1/2 columns (address hierarchy, parent education, family size, income, religion and others) beyond the shortened stage 7 prompt. Do not map its `الرقم التعريفي للطالب` to a national number. When the latest form is provided, version the field registry, add those columns with their exact categories, and write an explicit mapping/compatibility migration. Stages 5 and 6 (staff and qualifications/workload) can use the same plugin shell and their own tables and permissions.
