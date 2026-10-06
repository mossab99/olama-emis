# Building, floor and room data contract

Reference: `lsjl_lhsyy_lnzm_lmlwmt_ltrbwy_jyl_2025-2026_3.xlsx`, sheets `البيانات الأساسية للبناء `, `بيانات الغرف الصفية`, and `غرف غير صفية `. Coordinates refer to header/label cells, not export destinations. The latest workbook must confirm mandatory rules and categories before Ministry export.

## Identity and annual records

- `emis_buildings`: a stable internal ID linked to Core `school_id`, and a unique building number within that school. The building number is distinct from its national/commercial registry ID. Building numbers are immutable in this UI to protect previous records.
- `emis_building_years`: one profile per building and academic year, national registry ID, annual archive status, revision, last editor and time. Its revision serializes concurrent building, floor and room edits.
- `emis_floors`: one stable ID per building and signed floor number. Ground = 0, first = 1, first basement = -1. `emis_floor_years` stores its annual name and measured area.
- `emis_rooms`: one physical room ID and immutable room number per building, unique across classroom/facility use and all floors. Room numbers may be text; the workbook's facility-room note describes a floor digit plus two room digits (e.g. 102). No pattern is enforced for basements until the Ministry confirms its convention.
- `emis_room_years`: one annual floor, use (`classroom`/`facility`), common profile and archive status per physical room.
- `emis_classrooms` and `emis_facility_rooms`: annual module-specific details linked to the common room/year record. Changing use preserves the source details, but only the selected annual use contributes to lists and totals.

Application validation checks all relationships before writing. Physical identifiers are not linked by national-number text. No Core/Oracle records are updated. Existing assets can be registered in a new year; annual profiles and rooms are explicitly saved rather than copied automatically.

## Worksheet fields

| Building field | Label cell |
| --- | --- |
| Building number; national registry ID | K2; A4 |
| Area; construction year; construction type; start date | A6; B6; C6; D6 |
| Infrastructure condition; ownership; acquisition/construction year; model | E6; F6; G6; H6 |
| Expansion; basin; parcel; parcel area; landlord | I6; J6; K6; L6; M6 |
| Annual rent; electricity; heating/cooling; accessible passages | A9; B9; C9; D9 |
| Water; sewage; internal/external maintenance; drinking facilities | E9; F9; G9; H9; I9 |
| Accessible drinking facilities; water quality; network; floors | J9; K9; L9; M9 |

Building solar panels, emergency exits, civil-defense expiry and notes are optional extensions. Construction/ownership/status categories stay free text pending the official choice list.

| Shared floor header | Classroom sheet | Facility sheet |
| --- | --- | --- |
| Floor name / signed number | D5 / I5 (values D6 / I6) | C5 / H5 (values C6 / H6) |
| Measured floor area | A7 | A7 |

| Room field | Classroom sheet | Facility sheet |
| --- | --- | --- |
| Room number | B10 | B10 |
| Class type, grade, section, gender | C10–F10 | — |
| Use type and lab/workshop subtype | — | C10, E10 |
| Ownership | G10 | G10 |
| Measured area | H10 | I10 |
| Infrastructure condition / maintenance | I10 / J10 | K10 / L10 |
| Basic/added room | K10 | M10 |
| Notes | M10 | N10 |

The classroom M10 comment also identifies combined-class student counts and secondary stream. Length/width, ventilation, heating, operational status, capacity, workstations and sanitary units are optional extensions. Facility use offers suggestions and permits custom text. A separate explicit summary category provides consistent totals without guessing categories from arbitrary text.

## Saving and summaries

Room batches (up to 100 rows, paginated for larger buildings) are posted as JSON to avoid PHP input-variable truncation. Server validation is authoritative. The entire batch commits or rolls back under an InnoDB transaction; duplicate numbers, unrelated floors and stale revisions reject the batch. Omitted rows are preserved. Existing rows are archived explicitly; no hard delete exists. School read permission and configure permission are enforced separately, with nonces on writes. Invalid form values are retained in a temporary notice for the same logged-in user to correct.

Decimals are retained; the length × width button only supplies an editable suggestion. No export is implemented here; eventual export should round areas as specified by the template.

Summary cards cover active annual rooms in active annual buildings. Library and lab totals use the explicit category. Teacher capacity and sanitary unit totals show incomplete when a relevant room lacks its count. Unclassified facility rooms are flagged. Totals are compared to the saved Stage 1 library/lab fields and floor totals to building floor counts; mismatches are displayed without overwriting manual values. Class roster/student linkage and Ministry workbook export remain subsequent work.
