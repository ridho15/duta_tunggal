# Dynamic COA Selection System

## Overview
The Material Issue system now supports dynamic COA (Chart of Account) selection with a hierarchical fallback system. This allows for flexible accounting configurations while maintaining backward compatibility.

## COA Hierarchy
COA selection follows this priority order:

1. **MaterialIssueItem.inventoryCoa** - COA set at individual item level
2. **MaterialIssue.inventoryCoa** - COA set at issue level
3. **Product.inventoryCoa** - COA set at product level
4. **Fallback COA codes** - Predefined default COA codes

For WIP (Work in Progress):
1. **MaterialIssue.wipCoa** - COA set at issue level
2. **Fallback COA codes** - Predefined default COA codes

## Database Changes
- Added `wip_coa_id` and `inventory_coa_id` fields to `material_issues` table
- Added `inventory_coa_id` field to `material_issue_items` table
- All fields are nullable with foreign key constraints to `chart_of_accounts` table

## Model Updates
- **MaterialIssue**: Added `wipCoa` and `inventoryCoa` relationships
- **MaterialIssueItem**: Added `inventoryCoa` relationship
- Added fillable fields for COA IDs

## Service Updates
- **ManufacturingJournalService**: Implemented `resolveCoaByCodes()` method
- Updated all journal generation methods to use dynamic COA hierarchy
- Maintains backward compatibility with existing hardcoded COA codes

## Form Updates
- **MaterialIssueResource**: Added COA selection fields in header and items repeater
- COA fields are searchable and preloaded for better UX
- Helper text explains the hierarchy fallback system

## Usage
1. Create/Edit Material Issue
2. Select COA at issue level (optional) for WIP and Inventory
3. For each item, select COA (optional) to override issue-level COA
4. If no COA selected, system will use product COA or fallback defaults
5. Journal entries will be generated using the resolved COA hierarchy

## Backward Compatibility
- Existing Material Issues without COA selections will use fallback codes
- No breaking changes to existing functionality
- Migration is safe to run on production data