# OLAMA EMIS

The standalone Ministry statistics plugin owns year-specific EMIS modules. Stage 7, **البيانات الأساسية للطالب**, is the first implemented module. Its admin page is **OLAMA EMIS → البيانات الأساسية للطالب**.

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

The 2025–2026 workbook visible in Excel has additional page 1/2 columns (address hierarchy, parent education, family size, income, religion and others) beyond the shortened stage 7 prompt. Do not map its `الرقم التعريفي للطالب` to a national number. When the latest form is provided, version the field registry, add those columns with their exact categories, and write an explicit mapping/compatibility migration. The other six modules can then use the same plugin shell and their own tables and permissions.
