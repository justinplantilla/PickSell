# PLAN-02 Backend Evidence

**Project:** PickSell Marketplace and Logistics  
**Prepared for:** Justin Paul M. Plantilla  
**Evidence date:** September 25, 2026

This page contains backend proof that can be captured as screenshots for the PLAN-02 submission. The commands were executed from the PickSell Laravel project.

## 1. Role-Scoped Notification Routes

Command:

```powershell
C:\php\php.exe artisan route:list --path=notifications
```

Result:

```text
GET|HEAD admin/notifications          admin.notifications       AdminController@notifications
POST      admin/notifications/read    admin.notifications.read AdminController@markNotificationsRead
GET|HEAD buyer/notifications          buyer.notifications       BuyerController@notifications
POST      buyer/notifications/read    buyer.notifications.read BuyerController@markNotificationsRead
GET|HEAD seller/notifications         seller.notifications      SellerController@notifications
POST      seller/notifications/read   seller.notifications.read SellerController@markNotificationsRead

Showing [6] routes
```

**What this proves:** Notification data is role-scoped and the Mark all as read action uses authenticated POST routes rather than a data-changing GET request.

## 2. Automated Feature Test

Command:

```powershell
C:\php\php.exe artisan test tests\Feature\BuyerNotificationsTest.php
```

Result:

```text
PHPUnit result: passed
Tests: 1 passed
Assertions: 3
```

**What this proves:** The buyer notification endpoint returns a successful response and the expected notification payload.

## 3. Database Migration Status

Command:

```powershell
C:\php\php.exe artisan migrate:status
```

Result summary:

```text
Core users, cache, jobs, complaints, orders, messages, notifications,
products, buyer, announcements, settings, featured products, delivery,
logistics, and network migrations: Ran

2026_09_24_000001_add_deactivated_status_to_users_table: Pending
2026_09_24_000002_add_confirmed_by_seller_at_to_orders_table: Pending
```

**Important:** The two pending migrations are documented as evidence and were not executed automatically. Run them only after confirming the target database and backup procedure.

## 4. Blade and Frontend Build Evidence

Commands:

```powershell
C:\php\php.exe artisan view:cache
npm.cmd run build
```

Expected result:

```text
Blade templates cached successfully.
Vite production build completed successfully.
```

**What this proves:** Server-rendered Blade templates compile and extracted CSS/JavaScript assets are production-buildable.

## 5. Screenshot Captures for Submission

Capture the terminal windows showing each result above and save them as:

- `docs/screenshots/plan02-route-list.png`
- `docs/screenshots/plan02-notification-test.png`
- `docs/screenshots/plan02-migration-status.png`
- `docs/screenshots/plan02-build-output.png`

Recommended captions:

- **Figure A.** Role-scoped notification routes and explicit read-state endpoint.
- **Figure B.** Passing buyer notification feature test.
- **Figure C.** Database migration status for the PickSell backend.
- **Figure D.** Successful Blade compilation and Vite production build.

Before submission, crop screenshots to the terminal output and hide database passwords, mail credentials, tokens, and unrelated personal information.
