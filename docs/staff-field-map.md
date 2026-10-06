# Staff fields and storage (Stages 5–6)

Source: the supplied 2025/2026 workbook, sheets `البيانات الاساسية للموظفين ` (three pages across columns A:AN) and `مؤهلات الكادر` (one page A:M). Arabic labels and coordinates are recorded in the PHP field registries. The workbook has no data-validation lists. Unconfirmed job/appointment/degree categories remain text fields; degree suggestions are provided without restricting other qualifications.

## Storage

All tables include the configured WordPress prefix before these names:

| Table | Purpose and key |
| --- | --- |
| `olama_emis_staff` | Stable internal `id`; unique text `identity_number`, identity type, four name parts, personal profile, selected highest qualification, revisions and audit metadata |
| `olama_emis_staff_assignments` | One `staff_id + school_id + study_year` deployment; employment details, reported weekly periods, archival, revisions and audit metadata |
| `olama_emis_staff_qualifications` | Multiple qualifications linked by stable `staff_id`; full graduation date, institution, country, evaluation, specializations and audit metadata |
| `olama_emis_staff_workloads` | Subject/grade/section period distributions linked by `assignment_id`; saves replace the full displayed distribution for that assignment |

Relationships are enforced by the service and InnoDB transactions, matching the plugin's WordPress schema conventions. National/personal numbers can be corrected without changing assignment or qualification relationships. Arabic and Persian digits normalize to ASCII; leading zeros are retained. One identifier cannot belong to two staff records, regardless of type. No assumed official length/prefix or external verification service is imposed.

Personal edits are shared across school/year assignments. Annual employment details and workload are isolated by school and academic year. Archived assignments remain visible and retain their distributions, but are excluded from current workload totals. No employee or qualification deletion is exposed. The highest qualification is explicitly selected; other qualifications remain in the record.

## Workbook coverage

### Staff page 1

B4 identity, C4 civil record, D4:G4 four name parts, H4 birthplace, I4 birth date, J4 religion, K4 nationality, L4 gender, M5:Q5 residence governorate/district/subdistrict/city/neighborhood.

### Staff page 2

Repeated S4 identity and T4 name use the same employee record. V4 marital status, W4 mother name, X4 children count, Y4 job, Z5 appointment status, AA5 contract/appointment letter date, AB5 full/part-time status.

### Staff page 3

Repeated AD4 identity and AE4 name use the same employee record. AF4 current status, AG4 interruption years, AH5 total experience, AI5 education/administration experience, AJ5 gross salary, AK5 cadre category, AL5 refugee-card status, AM5 phone. Total experience must be at least education experience when both are supplied (per AH5 comment). Salary remains a decimal amount without an invented currency rule. AN5 teacher signature and committee/director signatures are paper approval fields; electronic signatures/approval workflow are not implemented.

### Qualifications

B4 identity and C5:D5 name are read from the linked staff record. E4 degree specialization, F4 bachelor's specialization for postgraduate staff, G4 degree, H4 full graduation date, I4 institution, J4 country, K3 evaluation. L3 reported periods belong to the annual assignment; M3 languages belong to the staff profile.

### Extensions

Street, disability/special needs, Ministry file number, ICDL and teacher-training flags are marked as additional fields. Subject/grade/section workload rows extend the worksheet's weekly total. No numeric designation of a degree is assumed. The short prompts do not define the official appointment categories; users can enter the current Ministry terminology.

## Validation and concurrent saves

- School must exist in the Core school list; academic years must be consecutive. No Core or Oracle staff tables are changed.
- Identity, first name and family name are needed to create a record. Other fields can be saved as draft values until the final mandatory rules are supplied.
- Dates use `YYYY-MM-DD`; invalid dates and future birth dates are rejected. Counts and periods are nonnegative integers. Salary and experience accept nonnegative decimals to two places.
- Every personal/assignment edit compares revisions under row locks. Qualification edits lock the staff record, verify ownership and bump its revision. Workload edits lock the same staff/assignment and compare the assignment revision. Failed writes roll back.
- Duplicate subject/grade/section rows are rejected. Up to 100 distributions are saved as one transaction; total periods are summed server-side and displayed live.
- Reported weekly periods are reconciled to subject totals. Internal warning limits default to 26 and can be changed globally or by exact job title. Loads above those limits are saved with warnings, not rejected as a supposed Ministry rule. Combined active-school totals are also displayed.

## Admin and transfer files

`OLAMA EMIS → بيانات الموظفين` provides Arabic search, 25/50-row server pagination, the personal/annual form, AJAX global staff lookup and identity duplicate checking. `المؤهلات وتوزيع الحصص` provides qualification records and annual teaching distributions. Viewing requires `olama_users_ministry_view`; all saves/imports/settings require `olama_users_ministry_configure`, a nonce and completed schema installation. Viewer form controls are disabled.

Staff import accepts the downloadable CSV or first-sheet XLSX templates, up to 5 MB / 2000 data rows. It maps English keys or exact Arabic labels. Excel templates/export cells use text format; preserve identifiers as text in other workbooks because numeric Excel cells may already have lost leading zeros. Dates should be ISO text; ordinary Excel 1900-system date serials are also accepted. The original multi-page Ministry workbook is a mapping reference, not a directly importable table.

Import is an explicit two-step preview/confirmation. Add mode refuses existing identifiers. Update mode requires an existing assignment in the selected school/year and retains omitted columns. Blank supplied cells clear the matching field. Duplicate file identifiers and invalid rows prevent confirmation. Preview expires after ten minutes, is tied to the current user/context, and captures revisions. Confirmation revalidates each row: each employee+assignment is atomic; successful rows remain saved if another row fails, with saved count and row-specific errors shown. Corrections can be re-previewed; there is no silent update or silent skip.

Excel/CSV export includes personal and annual employment fields for the selected school/year and search filter, including archived assignments, with a 5000-record limit. Qualifications/workload are managed in their relational screen and are not included in the staff import file. XLSX cells are text, Arabic/RTL with a frozen header. CSV escapes spreadsheet formula-leading values; use XLSX for exact text round trips. Current exports are transfer files, not a signed Ministry submission.

## Verification

`php tests/staff-validation-test.php` runs on CI. `php tests/campus-integration-test.php` also includes staff cases against a randomly named, isolated MySQL database and actual WordPress `wpdb`/`dbDelta`. It covers repeat installation, stable IDs, multi-school/year history, stale edits, malformed/duplicate/negative workloads, warning thresholds, add/update previews, preserved omitted fields, XLSX roundtrip, pagination, Arabic lookup, archival, viewer rendering and the table-name migration. No production records are used.
