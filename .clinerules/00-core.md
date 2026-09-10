# Core Module Blueprint

## Overview

This document outlines the core module structure and key services for the application.

## Services

### OrgService (`App\Services\OrgService`)

- **Purpose**: Manages organizational data including branches, locations, departments, verticals, segments, and related entities.
- **Key Methods**:
  - `userQuery()` - Retrieves user-related data
  - `branches()` - Fetches branch information
  - `locations()` - Gets location details
  - `divisions()` - Handles division management
  - `verticals()` - Manages vertical categories
  - `segments()` - Processes segment data
  - `subSegments()` - Handles sub-segment logic
  - `models()` - Model-related operations
  - `variants()` - Variant handling
  - `colors()` - Color management
  - `usersByPost()` - User queries by post
  - `usersByDesignation()` - Users by designation
  - `usersByDepartment()` - Users by department
  - `usersByDivision()` - Users by division
  - `salesConsultants()` - Sales consultant retrieval
  - `salesTeamUsers()` - Team user management
  - `getKeyValuesByCode()` - Key-value extraction
  - `getKeyValueById()` - ID-based key lookup
  - `getKeyValueByCode()` - Code-based key lookup
  - `users()` - General user listing
  - `getUserNameByCode()` - Username by code
  - `checkReceiptX()` - Receipt validation
  - `getReferenceUsers()` - Reference user fetching
  - `getPostOfficesByPincode()` - Post office lookup by pincode
  - `getLocationByPincode()` - Location by pincode
  - `getUpline()` - Upline relationship

### PersonService (`App\Services\PersonService`)

- **Purpose**: Handles person-related operations including contact, address, banking, and profile management.
- **Key Methods**:
  - `find()` - Generic find operation
  - `search()` - Search functionality
  - `get()` - Single entity retrieval
  - `upsert()` - Insert/update operations
  - `upsertContact()` - Contact-specific upsert
  - `upsertAddress()` - Address upsert
  - `upsertBanking()` - Banking information upsert
  - `setPrimary()` - Setting primary records
  - `buildQuery()` - Dynamic query building
  - `detectCriteria()` - Criteria detection
  - `preparePersonAttributes()` - Attribute preparation
  - `formatProfile()` - Profile formatting
  - `setPrimaryContact()` - Primary contact assignment
  - `setPrimaryAddress()` - Primary address setting
  - `setPrimaryBanking()` - Primary banking assignment
  - `cleanPhone()` - Phone number cleaning
  - `parseDate()` - Date parsing
  - `splitName()` - Name splitting

## Architecture Notes

- Both services leverage Eloquent ORM for database interactions
- Caching strategies are implemented via `Cache::remember()`
- Query optimization through selective field loading and filtering
- Consistent naming conventions across service interfaces
