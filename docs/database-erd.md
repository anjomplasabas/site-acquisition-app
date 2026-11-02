# Database ERD

This portfolio-safe overview focuses on the principal domain relationships. Laravel framework tables and lower-level document pivots are omitted for readability.

```mermaid
erDiagram
    USERS }o--|| ROLES : has
    USERS }o--|| DEPARTMENTS : belongs_to
    ROLES ||--o{ ROLE_MODULE_PERMISSIONS : grants
    ROLES ||--o{ ROLE_ACTION_PERMISSIONS : grants
    ROLES ||--o{ ROLE_PERMISSIONS : sees_status

    SITE_PROFILES }o--|| ADDRESSES : located_at
    SITE_PROFILES }o--o| SITE_CONTACTS : contacts
    SITE_PROFILES ||--o{ PIPELINES : has_attempts
    PIPELINES }o--|| USERS : registered_by
    PIPELINES }o--o| USERS : assigned_saq
    PIPELINES ||--o| SITE_SURVEYS : surveys
    SITE_SURVEYS }o--o| USERS : assigned_surveyor

    PIPELINES ||--o{ PIPELINE_STATUS_LOGS : records
    PIPELINES ||--o{ PIPELINE_REMARK_LOGS : records
    PIPELINES ||--o{ PIPELINE_APPROVALS : requires
    PIPELINE_APPROVALS }o--|| ROLES : assigned_role
    APPROVAL_CONFIGS }o--|| ROLES : configures

    PIPELINES ||--o| SITE_PLANNING_ASSIGNMENTS : plans
    SITE_PLANNING_ASSIGNMENTS ||--o{ SITE_PLANNING_TASKS : contains
    SITE_SURVEYS ||--o{ NETWORK_FEASIBILITIES : evaluates
    NETWORK_FEASIBILITIES ||--o{ NETWORK_FEASIBILITY_TASKS : contains

    PIPELINES ||--o| PROJECTS : initializes
    PROJECTS ||--o{ PROJECT_STAGES : contains
    PROJECT_STAGES ||--o{ PROJECT_STAGE_ISPS : tracks
    PROJECT_STAGES ||--o{ STAGE_TASKS : contains

    DOCUMENTS }o--|| USERS : uploaded_by
    PIPELINES }o--o{ DOCUMENTS : commercial_files
    SITE_SURVEYS }o--o{ DOCUMENTS : survey_files
    PROJECT_STAGES }o--o{ DOCUMENTS : delivery_files
    SITE_PLANNING_TASKS }o--o{ DOCUMENTS : task_files
    NETWORK_FEASIBILITIES }o--o{ DOCUMENTS : feasibility_files
```

## Design notes

- A site profile is the durable location; a pipeline represents an acquisition attempt.
- Approval configuration is role-based so staff changes do not rewrite the workflow.
- Project stages are fixed and sequential, while task and document records carry execution detail.
- Documents can be referenced by more than one workflow context. Orphan checks prevent deletion while another domain record still uses a file.
- Several legacy relationships are enforced by application policies and Eloquent rather than database foreign keys; boundary tests cover those ownership rules.
