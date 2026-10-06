# School profile field map (provisional 2025/2026)

Source workbook: `lsjl_lhsyy_lnzm_lmlwmt_ltrbwy_jyl_2025-2026_3.xlsx`, sheet `البيانات الأساسية للمدرسة `. Coordinates point to label cells. Reconfirm required marks and choices against the latest Ministry form before building export or a completion gate.

| Group | Field keys | Label cells |
| --- | --- | --- |
| Identity | `school_name`, `school_national_id` | A2, A3 (repeated A21, A22) |
| Administration | `authority`, `directorate` | A5, C5 |
| Geography | `governorate`, `district`, `subdistrict`, `locality` | E5, G5, I5, K5 |
| Address/coordinates | `neighborhood`, `street`, `longitude`, `latitude` | A8, C8, G11, H11 |
| Operations | `founded_year`, `education_system`, `school_gender`, `stages`, `highest_grade`, `lowest_grade`, `period`, `property_type` | E8, E11, F11, I11, K11, L11, C24, A24 |
| Contact/leadership | `phone`, `website`, `founder_name`, `general_manager`, `principal_name` | G8, I8, K8, A11, C11 |
| Site | `infrastructure_condition`, `land_area`, `basin_number`, `plot_number` | D24, E24, G24, H24 |
| Yards/playgrounds | `yard_count`, `yard_area`, `playground_count`, `playground_area` | I24, J24, K24, L24 |
| Parking/garden/access | `parking_count`, `parking_area`, `future_expansion`, `garden_area`, `accessible_passages`, `construction_additions` | A27, B27, C27, E27, F27, G27 |
| Classes/services | `combined_class_count`, `mobile_class_count`, `wall_condition`, `wall_maintenance`, `water_available`, `electricity_available` | I27, K27, A30, B30, C30, D30 |
| Facilities/staff totals | `library_count`, `science_labs`, `computer_labs`, `language_labs`, `service_staff_male`, `service_staff_female` | E30, G30, H30, I30, J30, K30 |

`school_id` is the Core link and `study_year` selects the annual snapshot. `school_number` is the Ministry school number. None has a dedicated cell on this sheet. `postal_code` and `email` are optional extensions with no current sheet cell. The school national ID is not assumed to have a specific length; only digits are validated.

The building sheet contains school land national ID (A3), building national ID (A4), and building number (K2). Those belong to Stage 2 and are separate from the school identifiers. The school sheet's page-two campus totals may later be derived from Building, Classroom, Facilities, or Staff modules. Reconciliation rules must be set before export. This module saves drafts only.
