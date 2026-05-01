# AGENTS.md
# Laravel Backend Development Instruction Set (Antigravity AI Optimized)

## Project Identity
This project is a Laravel Backend system focused on scalable enterprise/business operations such as:
- Multi-tenant business systems
- Financial transaction records
- Import/export workflows
- Operational dashboards
- User management
- Attendance / HR modules
- API integrations

---

# Core Stack
- Laravel (Latest stable unless project specifies otherwise)
- PHP ^8.2
- MySQL
- RESTful API
- Laravel Resource Controllers
- FormRequest Validation
- Service Layer Pattern
- Repository Pattern (for large modules)
- Queue / Jobs where relevant
- Dockerized environment when available
- Linux Ubuntu deployment target

---

# Primary AI Behavior Rules
## Always:
- Prioritize backend safety
- Preserve existing architecture
- Analyze dependencies before editing
- Avoid breaking changes
- Follow existing naming conventions
- Respect project structure before creating new files
- Prefer maintainable enterprise-grade code
- Keep backward compatibility unless explicitly told otherwise
- Explain risks for destructive actions

---

# Forbidden Behavior
## Never:
- Delete production-critical logic without warning
- Hardcode secrets, tokens, or credentials
- Modify `.env` directly
- Bypass validation
- Put business logic inside controllers unless project pattern already does so
- Introduce large architectural shifts without reason
- Rename database columns casually
- Change public API responses unexpectedly
- Ignore rollback safety in migrations

---

# Architecture Rules

## Controllers
### Must:
- Stay thin
- Handle request/response only
- Use FormRequest
- Delegate business logic to Services

### Avoid:
- Heavy query logic
- Validation inline
- Complex calculations

---

## Services
### Use for:
- Business logic
- Transactions
- Cross-module operations
- File processing
- Import systems
- External integrations

---

## Repositories
### Use when:
- Query complexity increases
- Filtering/searching becomes large
- Reusable query logic is needed

---

# Database Rules

## Migrations:
- Always include rollback-safe `down()`
- Preserve existing data when possible
- Use nullable only when logically valid
- Add indexes where needed
- Avoid destructive schema edits without fallback

## Seeder:
- Dummy data must be realistic
- Prefer Indonesian business context when relevant

---

# Validation Standards
## Always:
- Use FormRequest
- Use localized field aliases when useful
- Validate:
  - Required
  - Type
  - Length
  - File mime
  - Size
  - Foreign key existence
  - Enum constraints

## Import Features:
- Validate row-by-row
- Fail gracefully
- Return detailed error logs
- Preserve successful rows when applicable
- Use transactions where full consistency is required

---

# API Standards

## Response Format:
```json
{
  "success": true,
  "message": "Descriptive message",
  "data": {}
}