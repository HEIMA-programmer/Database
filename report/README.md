# DI31003 Assignment 2 Report Files

This directory contains all report components for the Retro Echo Records Database Implementation assignment.

## Report Structure

The report is organized into the following files:

| File | Content | Pages (Est.) |
|------|---------|--------------|
| `00_cover_page.md` | Cover page with team information | 1 |
| `01_connection_info.md` | System access, login credentials, architecture | 1 |
| `02_executive_summary.md` | Project overview, objectives, development approach | 1.5 |
| `03_user_guide.md` | User guides for Customer, Staff, Manager, Admin | 3 |
| `04_technical_annex.md` | Schema, views, indexes, procedures, triggers | 2.5 |
| `05_advanced_queries.md` | 5+ advanced SQL queries with explanations | 1.5 |
| `06_additional_features.md` | Extra features and design highlights | 1.5 |
| `FULL_REPORT.md` | **Complete combined report** | ~10 |

## How to Use

### Option 1: Complete Report
Use `FULL_REPORT.md` which contains all sections combined into a single document.

### Option 2: Individual Sections
Use the numbered files (00-06) to work on individual sections.

## Converting to PDF

### Using Pandoc (Recommended)
```bash
pandoc FULL_REPORT.md -o DI31003_Assignment2_TeamX.pdf \
  --pdf-engine=xelatex \
  -V geometry:margin=1in \
  -V mainfont="Times New Roman"
```

### Using VS Code
1. Install "Markdown PDF" extension
2. Open FULL_REPORT.md
3. Press Ctrl+Shift+P → "Markdown PDF: Export (pdf)"

### Using Online Tools
1. Copy content to https://dillinger.io/
2. Export as PDF

## Before Submission

1. **Replace placeholders:**
   - `[YOUR-AWS-IP]` → Your actual AWS instance IP
   - `[YOUR-DB-PASSWORD]` → Your database root password
   - `[Your Name]` → Team member names
   - `[Student ID]` → Student IDs
   - `[Your Team Number]` → Team number

2. **Verify page count:** Maximum 10 pages (excluding cover and references)

3. **Check all screenshots** are clear and readable

4. **Ensure system is live** on AWS until end of February 2025

## Key Highlights for Markers

### Database Features
- 17 core tables with proper normalization
- 109 views implementing View-Based Access Control
- 29 stored procedures for business logic
- 15 triggers for data integrity
- 40+ indexes including 8 covering indexes
- 5+ advanced SQL queries with window functions

### Additional Features Beyond Requirements
- Manager Request Approval Workflow
- Polymorphic Foreign Key Pattern
- MySQL Event Scheduler for auto-cleanup
- Birthday Bonus Point System
- Multi-channel fulfillment (Shipping + BOPIS)
- Walk-in customer support

### Frontend Features
- Role-based dashboards (Admin, Manager, Staff, Customer)
- Real-time inventory lookup
- Point-of-Sale system
- Shopping cart with reservation timeout
- Responsive Bootstrap design

## File Size Reference

| Component | Count |
|-----------|-------|
| Tables | 17 |
| Views | 109 |
| Stored Procedures | 29 |
| Triggers | 15 |
| Indexes | 40+ |
| PHP Pages | 45+ |
| API Endpoints | 10+ |

---

*Report prepared for DI31003 Database Systems - Assessment 2*
