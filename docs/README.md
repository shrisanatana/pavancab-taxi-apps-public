# ðŸš• PAVANCAB GOA - Project Memory & Knowledge Base

> **Master Architecture, Features, Bug Resolutions & Roadmap Documentation**  
> *Permanent context memory for developers & AI assistants.*

---

## ðŸ“ Knowledge Base Index

```
docs/
â”œâ”€â”€ 01_project_overview/
â”‚   â”œâ”€â”€ architecture.md          # Tech stack, backend flow, directory structure
â”‚   â”œâ”€â”€ database_schema.md       # MySQL tables, fields, indexes, auto-migrations
â”‚   â””â”€â”€ deployment_and_server.md # Hostinger server, WinSCP sync, FTP scripts
â”œâ”€â”€ 02_features/
â”‚   â”œâ”€â”€ booking_engine.md        # 4-step booking flow, One-way, Hourly & Tours
â”‚   â”œâ”€â”€ authentication.md        # WhatsApp OTP system, session & role management
â”‚   â”œâ”€â”€ dispatch_tower.md        # Admin dashboard, live sync, driver assignment
â”‚   â”œâ”€â”€ passenger_radar.md       # My Rides radar, direct ride page spotlight view
â”‚   â”œâ”€â”€ notifications_fcm_whatsapp.md # Meta WhatsApp Cloud API & FCM Web Push
â”‚   â””â”€â”€ ratings_and_reports.md   # Star ratings, driver averages & ride report desk
â”œâ”€â”€ 03_bugs_and_solutions/
â”‚   â”œâ”€â”€ database_pdo_migration_bug.md # PDO vs mysqli refactoring & missing helpers
â”‚   â”œâ”€â”€ filter_tab_mixing_bug.md      # Dispatch filter tab locking & status mapping
â”‚   â”œâ”€â”€ session_state_mismatch_bug.md # Global window.isUserLoggedIn synchronization
â”‚   â”œâ”€â”€ sos_feature_removal.md        # Permanent removal of SOS features per client
â”‚   â””â”€â”€ script_syntax_error.md        # Missing tags & data-hash sync reset
â””â”€â”€ 04_plan_and_roadmap/
    â””â”€â”€ future_roadmap.md        # Payment gateway, driver PWA & GPS live tracking
```

---

## ðŸš€ Key Project Metadata

| Attribute | Details |
|---|---|
| **App Name** | PAVANCAB (Goa Taxi & Airport Dispatch) |
| **Theme / Meaning** | *Pavan* = Air / Father of Lord Hanuman (Speed, Trust & Safety) |
| **Live App URL** | [https://pavancab.com/app/](https://pavancab.com/app/) |
| **Dispatch Tower** | [https://pavancab.com/app/dashboard.php](https://pavancab.com/app/dashboard.php) |
| **My Rides Page** | [https://pavancab.com/app/rides.php](https://pavancab.com/app/rides.php) |
| **Super Admin Phone** | `919000000000` |
| **Super Admin Email** | `admin@pavancab-demo.local` |
| **Hosting Platform** | Hostinger VPS / Cloud |
| **Backend Engine** | Pure PHP 8.x + MySQLi (Zero heavy node dependencies) |
| **Styling** | Mobile-First Tailwind CSS + Glassmorphic Dark UI |
| **Deploy Tool** | WinSCP PowerShell Automation (`deploy.ps1`) |
