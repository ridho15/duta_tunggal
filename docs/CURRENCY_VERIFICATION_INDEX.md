# 🗂️ Currency Verification Plan - Documentation Index

**Version:** 1.0  
**Last Updated:** 13 May 2026  
**Total Documents:** 6 files

---

## 📚 Document Structure

```
Project Root (Duta-Tunggal-ERP)
│
├── 📌 CURRENCY_VERIFICATION_EXECUTIVE_SUMMARY.md
│   └─ START HERE! 2-3 page overview
│      - What's this plan?
│      - Quick facts
│      - Execution timeline
│      - Sign-off section
│
├── 📋 CURRENCY_VERIFICATION_PLAN.md
│   └─ Complete detailed plan (60+ pages)
│      - 7 phases with 38+ test cases
│      - Test case descriptions
│      - Code references
│      - Success criteria
│      - Template for first test file
│
├── ✅ CURRENCY_VERIFICATION_CHECKLIST.md
│   └─ Week-by-week tracking (30+ pages)
│      - Actionable checklist items
│      - Checkboxes for progress
│      - Phase breakdowns
│      - Success metrics
│
├── /memories/session/currency_verification_plan.md
│   └─ Session notes (stored in copilot memory)
│      - Accessible during conversations
│      - Can be referenced later
│
├── Diagrams (3 visual diagrams)
│   ├─ Currency Amount Lifecycle Verification Plan
│   │  └─ 7-phase flow with test results
│   ├─ Currency Amount Data Flow & Verification Points
│   │  └─ Sequence diagram showing input→storage→display
│   └─ Currency Verification: Risk Matrix & Testing Priority
│      └─ HIGH/MEDIUM/LOW risk mapping
│
└── Code References (in comments)
    ├─ app/Support/CurrencyConversionResolver.php
    ├─ app/Filament/Resources/SaleOrderResource.php
    ├─ app/Filament/Resources/OrderRequestResource.php
    ├─ app/Models/OrderRequestItem.php
    └─ etc. (see CURRENCY_VERIFICATION_PLAN.md for full list)
```

---

## 🎯 How to Use These Documents

### **For Quick Orientation (5 minutes)**

👉 Read: **CURRENCY_VERIFICATION_EXECUTIVE_SUMMARY.md**

**Contains:**
- What problem we're solving
- Quick facts (38+ tests, 4 weeks)
- Test breakdown by risk
- Execution timeline
- Sign-off section

**Outcome:** You understand the plan and can approve it

---

### **For Planning & Details (1-2 hours)**

👉 Read: **CURRENCY_VERIFICATION_PLAN.md**

**Contains:**
- All 7 phases explained
- 38+ test cases with:
  - Test objective
  - Steps
  - Expected result
  - Risk level
  - Code file references
  - Line numbers
- Success criteria
- Key assertions & validation rules
- Test file template (ready to copy)

**Outcome:** You can assign work and create tests

---

### **For Week-by-Week Tracking (Throughout 4 weeks)**

👉 Use: **CURRENCY_VERIFICATION_CHECKLIST.md**

**Contains:**
- 5 phases with checkboxes
- Test file names
- Run commands (copy-paste ready)
- Status tracking
- Success metrics

**Outcome:** You can track progress and know what to do each day

---

### **For Discussion/Approval**

👉 Share: **CURRENCY_VERIFICATION_EXECUTIVE_SUMMARY.md** + 3 Diagrams

**Start Conversation:**
- "Here's our 4-week plan to verify currency amounts are consistent"
- "We have 38+ test cases across 7 phases"
- "HIGH risk items (Week 1) are critical to verify the SO fix we deployed"
- "Do you approve? Any questions?"

**Expected Response:**
- [ ] Approved
- [ ] Approved with changes (specify)
- [ ] Needs clarification (which part?)

---

## 📖 Document Reading Order

### **Recommended Path for Different Roles**

#### **Project Manager**
1. Read: Executive Summary (5 min)
2. Review: Risk Matrix diagram (2 min)
3. Action: Approve timeline, assign resources
4. Track: Use Checklist for status updates

#### **QA Lead / Test Coordinator**
1. Read: Executive Summary (5 min)
2. Deep dive: Full Plan (2 hours)
3. Create: Test files Week 1 (see template)
4. Track: Checklist + progress updates
5. Lead: Team execution across 4 weeks

#### **Developer**
1. Read: Executive Summary (5 min)
2. Reference: Code references in Plan
3. Create: Test files for assigned phase
4. Fix: Code based on test results
5. Update: Checklist as tests pass

#### **Tech Lead / Architect**
1. Read: Executive Summary (5 min)
2. Review: Full Plan design
3. Validate: Scope, assumptions, risks
4. Approve: Plan execution
5. Monitor: Weekly progress

---

## 🔍 Key Sections by Document

### CURRENCY_VERIFICATION_EXECUTIVE_SUMMARY.md

| Section | Purpose | Time |
|---------|---------|------|
| What Is This Plan? | Understand the goal | 1 min |
| Quick Facts | See scope at a glance | 1 min |
| Test Breakdown by Risk | Know what's critical | 2 min |
| Execution Plan (Weeks 1-4) | See timeline | 3 min |
| Success Criteria | Know when done | 2 min |
| Sign-Off | Get approval | 1 min |

**Total Time:** 10 minutes

---

### CURRENCY_VERIFICATION_PLAN.md

| Section | Purpose | Time |
|---------|---------|------|
| Executive Summary | Understand objectives | 5 min |
| Phase 1-7 Descriptions | See all test cases | 30 min |
| Key Code References | Where to look in code | 10 min |
| Test Files to Create | Copy-paste names | 5 min |
| Template for First Test | Start coding | 10 min |
| Related Documentation | Find references | 5 min |

**Total Time:** 60 minutes

---

### CURRENCY_VERIFICATION_CHECKLIST.md

| Section | Purpose | Time |
|---------|---------|------|
| Phase 1-5 Checkboxes | Track daily progress | 2 min |
| Test Files & Commands | Copy-paste ready | 3 min |
| Final Validation Section | Know when complete | 3 min |
| Success Metrics | Measure progress | 2 min |
| Known Issues / Questions | Clarify assumptions | 5 min |

**Total Time:** 15 minutes (daily updates)

---

## 🎯 Quick Command Reference

### Run All Currency Tests
```bash
# Backend tests
php artisan test tests/Feature/Currency*.php

# Browser tests
npx playwright test tests/playwright/currency-*.spec.mjs

# Combined
php artisan test tests/Feature/Currency*.php && \
npx playwright test tests/playwright/currency-*.spec.mjs
```

### Run by Phase
```bash
# Week 1: HIGH priority
php artisan test tests/Feature/CurrencyAmountInputValidationTest.php
php artisan test tests/Feature/SaleOrderCurrencyLifecycleTest.php
php artisan test tests/Feature/CurrencyAmountPersistenceTest.php

# Week 2: MEDIUM priority
npx playwright test tests/playwright/sale-order-currency-display.spec.mjs

# Week 3: Edge cases
php artisan test tests/Feature/CurrencyEdgeCasesTest.php

# Week 4: Integration
php artisan test tests/Feature/OrderRequestEndToEndWorkflowTest.php
```

---

## 📊 Test Coverage Map

### Phase-by-Phase Distribution

```
Phase 1: Input & Conversion
├─ OrderRequest Multi-Currency (5 cases)
├─ SaleOrder Livewire Reactivity (4 cases)
└─ PurchaseOrder Mixed Currency (4 cases)
   Total: 13 cases | Risk: HIGH | Week: 1

Phase 2: Persistence & Computed
├─ Data Integrity (4 cases)
├─ Calculated Fields (4 cases)
├─ Reload Consistency (4 cases)
└─ SO→PO Conversion (4 cases)
   Total: 16 cases | Risk: HIGH | Week: 1-2

Phase 3: Display & Formatting
├─ Form Fields Display (4 cases)
└─ Infolist Display (4 cases)
   Total: 8 cases | Risk: MEDIUM | Week: 2-3

Phase 4: Edge Cases
└─ Edge Cases & Validation (5 cases)
   Total: 5 cases | Risk: MEDIUM | Week: 3

Phase 5: Reload Consistency
(Already covered in Phase 2)

Phase 6: Integration
└─ End-to-End Workflows (6 cases)
   Total: 6 cases | Risk: MEDIUM | Week: 4

Phase 7: Playwright UI
└─ Browser Tests (2 specs)
   Total: 2 specs | Risk: MEDIUM | Week: 2-4

GRAND TOTAL: 38+ test cases | 12 files | 140+ assertions
```

---

## ❓ FAQ

### Q: Where should I start?
**A:** Read CURRENCY_VERIFICATION_EXECUTIVE_SUMMARY.md (10 min), then decide your role and follow the "Reading Order" section above.

---

### Q: How long will this take?
**A:** 
- Reading all docs: 2-3 hours
- Approval/clarification: 1-2 hours
- Execution: 4 weeks
- **Total: 5-6 weeks** from now

---

### Q: Do I need to create all test files myself?
**A:** 
No! Template provided in CURRENCY_VERIFICATION_PLAN.md. You can:
- Copy template
- Update for your test case
- Takes ~30 min per test file

---

### Q: What if tests fail?
**A:** 
That's expected! Tests find bugs. When they fail:
1. Read test output carefully
2. Identify root cause
3. Fix code (not test)
4. Re-run test
5. Move to next

Checklist includes section for "Known Issues" to track failures.

---

### Q: Can I run tests in parallel?
**A:** 
**Backend tests:** Yes, use `--parallel`
```bash
php artisan test tests/Feature/Currency*.php --parallel
```

**Playwright tests:** Yes, configure in playwright.config.js
```javascript
use: { workers: 4 }
```

**Be careful:** Parallel tests might have flakes if they share DB resources. Start serial, then optimize.

---

### Q: What if a test doesn't apply to my code?
**A:** 
- Skip it (note in checklist)
- OR modify test to fit your actual code
- OR document why it's not needed
- Main goal: Verify amounts stay consistent, however you implement it

---

### Q: How often should I update the checklist?
**A:** 
- Daily (check off completed items)
- Weekly (summarize progress)
- At end of each phase (review results)

---

## 🚀 Getting Started Now

### Step 1: Right Now (5 minutes)
- [ ] Read CURRENCY_VERIFICATION_EXECUTIVE_SUMMARY.md

### Step 2: Today (1 hour)
- [ ] Share plan with team
- [ ] Get sign-off from PM/Tech Lead
- [ ] Clarify any questions

### Step 3: This Week (2 hours)
- [ ] Read full CURRENCY_VERIFICATION_PLAN.md
- [ ] Review code references
- [ ] Assign test creation to team

### Step 4: Next Week (Start execution)
- [ ] Create Week 1 test files
- [ ] Run tests
- [ ] Fix code based on results
- [ ] Update checklist

---

## 📞 Support & Questions

### If you don't understand:
- **Section:** Re-read it or ask someone
- **Test case:** Refer to code reference + example
- **Timeline:** Adjust based on team capacity
- **Scope:** Trim to MVP if needed

### If tests don't work:
1. Check test command (copy-paste correct one)
2. Ensure test database is ready
3. Check syntax errors in test file
4. Run with `-v` flag for verbose output: `php artisan test -v tests/Feature/CurrencyAmountInputValidationTest.php`

### If test fails:
- This is GOOD! Tests find bugs
- Read error message carefully
- Fix code or clarify test
- Re-run

---

## 📈 Success Tracking

### Weekly Status Template

```
Week: ___
Completed: ___ / 38 test cases
Passed: ___ / ___ 
Failed: ___ (detail in Known Issues)
Blockers: (none / list)
On Track: YES / NO
Notes:
```

Use this in your status updates or checklist.

---

## 📎 File Locations

All documents in project root:

```
Duta-Tunggal-ERP/
├── CURRENCY_VERIFICATION_EXECUTIVE_SUMMARY.md ← START HERE
├── CURRENCY_VERIFICATION_PLAN.md ← Detailed plan
├── CURRENCY_VERIFICATION_CHECKLIST.md ← Week-by-week tracking
└── This index file (optional, for navigation)
```

Plus memory storage:
```
/memories/session/currency_verification_plan.md
```

---

## ✅ Checklist for This Index

- [ ] I understand where each document is
- [ ] I know which document to read first
- [ ] I know where to find test commands
- [ ] I know what to do if a test fails
- [ ] I know how to track progress
- [ ] I'm ready to start

---

**Next Action:** 👉 Read CURRENCY_VERIFICATION_EXECUTIVE_SUMMARY.md

Good luck! 🚀
