````md
# Dynamic RBAC Implementation Plan

## Project : OData V4 Acumatica

Version : 1.0
Author : ChatGPT
Status : Design Proposal

---

# 1. Objective

Membangun sistem Role Based Access Control (RBAC) yang bersifat **Dynamic, Resource-Based, dan Extensible**, sehingga:

-   Tidak perlu membuat permission secara manual.
-   Penambahan Entity otomatis menghasilkan permission.
-   Menu, Route, API, dan UI menggunakan permission yang sama.
-   Mendukung Multi Module.
-   Mudah dipelihara ketika jumlah Entity mencapai ratusan.

---

# 2. Current Architecture

Saat ini aplikasi memiliki struktur:

```

User
│
├── Role
│
├── Permission
│
└── Middleware
↓
Route
↓
Controller
↓
Service
↓
Acumatica API

```

Permission masih bersifat static seperti:

```

sync.manage
entity.manage
report.view
settings.manage

```

Semakin banyak Entity maka permission akan semakin sulit dipelihara.

---

# 3. Target Architecture

```

User
│
▼
Role
│
▼
Permission
│
▼
Resource
│
▼
Action

```

Setiap Resource memiliki Action.

Contoh:

```

Customer

View
Create
Update
Delete
Export
Import
Sync

```

akan menghasilkan

```

customer.view
customer.create
customer.update
customer.delete
customer.export
customer.import
customer.sync

```

---

# 4. Database Design

## 4.1 Modules

```sql
id
code
name
icon
sort_order
is_active
created_at
updated_at
```

Example

| Code        | Name            |
| ----------- | --------------- |
| dashboard   | Dashboard       |
| master-data | Master Data     |
| transaction | Transaction     |
| sync        | Synchronization |
| report      | Report          |
| settings    | Settings        |
| security    | Security        |

---

## 4.2 Resources

Merepresentasikan seluruh Entity.

```sql
id
module_id
name
slug
description
is_active
created_at
updated_at
```

Example

```

Customer
Vendor
Inventory
Warehouse
Sales Order
Purchase Order

```

---

## 4.3 Actions

Master Action.

```sql
id
code
name
sort_order
```

Default

| Code |
| ---- |

view
create
update
delete
approve
cancel
release
export
import
print
sync
api
execute

Action tidak berubah selama aplikasi hidup.

---

## 4.4 Permissions

```sql
id
resource_id
action_id
permission_key
description
created_at
updated_at
```

Generated otomatis.

Example

```

customer.view
customer.create
customer.update
customer.delete
customer.export

```

---

## 4.5 Roles

```sql
id
name
description
is_active
created_at
updated_at
```

Example

```

Administrator
Supervisor
Warehouse
Finance
Purchasing
Sales

```

---

## 4.6 Role Permissions

```sql
role_id
permission_id
```

Many To Many.

---

## 4.7 User Roles

```sql
user_id
role_id
```

Many To Many.

---

# 5. Menu Management

Tambahkan tabel:

## menus

```sql
id
parent_id
module_id
title
icon
route_name
sort_order
```

Example

```

Dashboard

Master Data

Transaction

Reports

Settings

Users

```

---

## menu_permissions

```sql
menu_id
permission_id
```

Menu hanya muncul jika user memiliki permission tersebut.

---

# 6. Dynamic Permission Generator

Service baru

```

PermissionGeneratorService

```

Flow

```

Entity dibuat

↓

PermissionGeneratorService

↓

Generate seluruh Action

↓

Insert Permission

↓

Permission tersedia pada Role Management

```

Pseudo

```

Customer

↓

customer.view
customer.create
customer.update
customer.delete
customer.export
customer.import
customer.sync

```

Tidak ada coding manual.

---

# 7. Permission Naming Standard

```

resource.action

```

Example

```

customer.view

customer.create

inventory.export

warehouse.sync

report.execute

api.release

```

---

# 8. Route Protection

Saat ini

```php
Route::middleware('permission:customer.view');
```

Diubah menjadi

```php
Route::resource('customer', CustomerController::class);
```

Middleware membaca

```

Controller

Action

```

kemudian otomatis mapping

| Controller Action | Permission      |
| ----------------- | --------------- |
| index             | customer.view   |
| show              | customer.view   |
| store             | customer.create |
| update            | customer.update |
| destroy           | customer.delete |

Developer tidak lagi menulis permission satu per satu.

---

# 9. Controller Authorization

Controller cukup

```php
class CustomerController
{
}
```

Permission dilakukan oleh middleware.

---

# 10. Blade Authorization

Saat ini

```php
@if(auth()->user()->can('customer.update'))
```

Tetap dapat digunakan.

Namun dapat dibuat helper

```php
@permission('customer.update')
```

atau

```php
<x-can permission="customer.update">

```

agar lebih bersih.

---

# 11. Dynamic Menu Rendering

Flow

```

Login

↓

Role

↓

Permission

↓

Menu

↓

Sidebar

```

Sidebar tidak memiliki if statement.

Sidebar dihasilkan dari database.

---

# 12. UI Role Management

Role Management

```

Warehouse

```

```

Customer

☑ View

☐ Create

☐ Delete

☑ Export

☑ Sync

```

```

Inventory

☑ View

☑ Update

☑ Export

☑ Import

```

Grouping berdasarkan Module.

```

Master Data

Customer

Vendor

Inventory

```

```

Transaction

Sales Order

Purchase Order

```

Jauh lebih mudah dibanding checklist ribuan permission.

---

# 13. Entity Integration

Karena aplikasi memiliki Entity Configuration.

Saat Entity baru dibuat.

```

Supplier

```

System otomatis menjalankan

```

PermissionGeneratorService

```

menghasilkan

```

supplier.view

supplier.create

supplier.update

supplier.delete

supplier.export

supplier.sync

```

Role Management langsung menampilkan Resource baru.

Tidak perlu migration.

Tidak perlu Seeder.

Tidak perlu coding.

---

# 14. API Authorization

Semua Endpoint API mengikuti permission yang sama.

Example

```

GET /customer

↓

customer.view

```

```

POST /customer

↓

customer.create

```

```

DELETE /customer

↓

customer.delete

```

Tidak ada permission khusus API.

---

# 15. Report Authorization

Report Builder juga menggunakan Resource.

Misal

```

Sales Report

```

Permission

```

report.sales.view

report.sales.export

```

---

# 16. Sync Authorization

Sinkronisasi Entity

```

inventory.sync

warehouse.sync

customer.sync

```

Administrator dapat memberi hak Sync tanpa memberi hak Delete.

---

# 17. Seeder

Seeder hanya mengisi

Modules

Actions

Administrator Role

Administrator mendapatkan seluruh Permission.

Permission lainnya akan dihasilkan otomatis.

---

# 18. Migration Sequence

```

001_create_modules

002_create_resources

003_create_actions

004_create_permissions

005_create_roles

006_create_role_permissions

007_create_user_roles

008_create_menus

009_create_menu_permissions

```

---

# 19. Service Layer

Service baru

```

PermissionGeneratorService

PermissionCacheService

RoleService

MenuService

AuthorizationService

ResourceService

ActionService

```

---

# 20. Cache Strategy

Gunakan Cache.

```

User Permission

Role Permission

Menu

```

Cache dibersihkan ketika

-   Permission berubah
-   Role berubah
-   Menu berubah

---

# 21. Audit Log

Semua perubahan Role dan Permission dicatat.

```

User A

Menambah

Inventory.Update

ke Role Warehouse

```

---

# 22. Future Ready Features

Tahap berikutnya dapat ditambahkan:

-   Multi Company Permission
-   Branch Level Permission
-   Row Level Security
-   Field Level Security
-   Workflow Permission
-   Approval Permission
-   API Token Permission
-   Temporary Permission
-   Expired Permission
-   Permission Delegation

Tanpa mengubah struktur database.

---

# 23. Implementation Roadmap

## Phase 1

-   Modules
-   Resources
-   Actions
-   Permissions

---

## Phase 2

-   Role
-   User Role
-   Permission Generator

---

## Phase 3

-   Middleware
-   Dynamic Route Authorization
-   Blade Authorization

---

## Phase 4

-   Dynamic Sidebar
-   Menu Management
-   Permission Cache

---

## Phase 5

-   Entity Integration
-   Report Integration
-   Sync Integration

---

## Phase 6

-   Audit Log
-   Branch Permission
-   Company Permission
-   Advanced Authorization

---

# 24. Expected Benefits

✅ Permission tidak dibuat manual

✅ Penambahan Entity otomatis memiliki Permission

✅ Menu otomatis mengikuti Permission

✅ Route otomatis terlindungi

✅ API mengikuti Permission yang sama

✅ Sangat mudah di-maintain

✅ Mendukung ratusan hingga ribuan Resource

✅ Mudah dikembangkan untuk kebutuhan Enterprise ERP

✅ Konsisten dengan konsep Entity Configuration yang telah dimiliki aplikasi OData V4 Acumatica
````
