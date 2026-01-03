# 1. System Access Information

This section provides all necessary connection details and login credentials for accessing the Retro Echo Records Management System.

---

## 1.1 Web Application Access

**Application URL:** `http://[YOUR-AWS-IP]/public/`

| User Type | Username | Password | Description |
|-----------|----------|----------|-------------|
| **Admin** | `admin` | `password123` | Global system administrator with full access |
| **Manager (Changsha)** | `manager_cs` | `password123` | Changsha retail store manager |
| **Manager (Shanghai)** | `manager_sh` | `password123` | Shanghai retail store manager |
| **Manager (Warehouse)** | `manager_wh` | `password123` | Central warehouse manager |
| **Staff (Changsha)** | `staff_cs` | `password123` | Changsha retail store staff |
| **Staff (Shanghai)** | `staff_sh` | `password123` | Shanghai retail store staff |
| **Staff (Warehouse)** | `staff_wh` | `password123` | Central warehouse staff |

### Customer Accounts

| Customer Name | Email (Username) | Password | Membership Tier |
|---------------|------------------|----------|-----------------|
| Alice Fan | `alice@test.com` | `password123` | VIP |
| Bob Collector | `bob@test.com` | `password123` | Gold |
| Charlie New | `charlie@test.com` | `password123` | Standard |
| Diana Vinyl | `diana@test.com` | `password123` | VIP |
| Edward Rock | `edward@test.com` | `password123` | Standard |

---

## 1.2 Direct Database Access (MySQL Workbench)

For IT department and marking purposes, direct database access is available:

| Parameter | Value |
|-----------|-------|
| **Host** | `[YOUR-AWS-IP]` |
| **Port** | `3306` |
| **Database Name** | `retro_echo` |
| **Username** | `root` |
| **Password** | `[YOUR-DB-PASSWORD]` |
| **Character Set** | `utf8mb4` |

---

## 1.3 System Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                     AWS Cloud Infrastructure                      │
├─────────────────────────────────────────────────────────────────┤
│  ┌─────────────────┐    ┌─────────────────┐                     │
│  │   Web Server    │    │  MySQL Server   │                     │
│  │   (Apache)      │◄──►│  (MariaDB)      │                     │
│  │   PHP 8.2+      │    │                 │                     │
│  └─────────────────┘    └─────────────────┘                     │
│           ▲                                                      │
│           │ HTTPS                                                │
│           ▼                                                      │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │                    Internet (Users)                          ││
│  │  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐        ││
│  │  │ Admin   │  │ Manager │  │  Staff  │  │Customer │        ││
│  │  └─────────┘  └─────────┘  └─────────┘  └─────────┘        ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
```

---

## 1.4 Store Locations

| Shop ID | Shop Name | Type | Location |
|---------|-----------|------|----------|
| 1 | Retro Echo Changsha | Retail | Changsha, Hunan Province |
| 2 | Retro Echo Shanghai | Retail | Shanghai |
| 3 | Central Warehouse | Warehouse | Distribution Center |

---

**Note:** All systems must remain accessible on AWS servers until the end of February 2025 for marking purposes.
