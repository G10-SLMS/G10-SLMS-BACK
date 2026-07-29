# Sprint 1 — Project Setup & Authentication

**Week 28:** July 7–13

## Goal

Establish the project foundation by setting up the backend and frontend applications, implementing user authentication, role-based authorization, and creating the basic dashboard structure.

---

## Backend Tasks

- [ ] Set up Laravel 12 project

    - [ ] This repository contains the backend API for the **Student Leave Management System (SLMS)**, built with **Laravel 12**. The project follows a clean and maintainable architecture using the MVC pattern and RESTful API principles.

---

## Technology Stack

- Laravel 12
- PHP 8.3+
- MySQL
- Laravel Sanctum (Authentication)
- RESTful API

---

## Project Structure

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── LeaveRequestController.php
│   │   ├── LeaveTypeController.php
│   │   ├── CommentController.php
│   │   ├── DashboardController.php
│   │   └── NotificationController.php
│   │
│   ├── Middleware/
│   │   └── RoleMiddleware.php
│   │
│   └── Requests/
│       ├── StoreLeaveRequest.php
│       └── UpdateLeaveRequest.php
│
├── Models/
│   ├── User.php
│   ├── LeaveRequest.php
│   ├── LeaveType.php
│   ├── Comment.php
│   ├── Attachment.php
│   └── Notification.php
│
├── Services/
│   ├── LeaveService.php
│   └── NotificationService.php
│
routes/
└── api.php

database/
├── migrations/
└── seeders/
```

---

## Directory Overview

### Controllers

Contains all API controllers responsible for handling incoming requests and returning responses.

- **AuthController** – User authentication.
- **LeaveRequestController** – Student leave request operations.
- **LeaveTypeController** – Manage leave types.
- **CommentController** – Manage comments on leave requests.
- **DashboardController** – Dashboard statistics and summary.
- **NotificationController** – Notification management.

---

### Models

Represents the application's database entities.

- User
- LeaveRequest
- LeaveType
- Comment
- Attachment
- Notification

---

### Form Requests

Contains request validation classes.

- StoreLeaveRequest
- UpdateLeaveRequest

---

### Middleware

Contains custom middleware used to protect routes.

- RoleMiddleware

---

### Services

Contains business logic to keep controllers clean.

- LeaveService
- NotificationService

---

### Routes

API endpoints are defined in:

```
routes/api.php
```

---

### Database

Contains database-related files.

- Migrations
- Seeders

---

## Installation

Clone the repository.

```bash
git clone <repository-url>
```

Navigate into the project.

```bash
cd G10-SLMS-BACK
```

Install dependencies.

```bash
composer install
```

Copy the environment file.

```bash
cp .env.example .env
```

Generate the application key.

```bash
php artisan key:generate
```

Configure the database in the `.env` file.

```

Run database migrations.

```bash
php artisan migrate
```

(Optional) Seed the database.

```bash
php artisan db:seed
```

Start the development server.

```bash
php artisan serve
```

The API will be available at:

```
http://127.0.0.1:8000
```

---

- [ ] Configure MySQL database
    - [ ] `DB_CONNECTION=mysql`
    - [ ] `DB_HOST=127.0.0.1`
    - [ ] `DB_PORT=3306`
    - [ ] `DB_DATABASE=g10_slms_db`
    - [ ] `DB_USERNAME=root`
    - [ ] `DB_PASSWORD=`

- [ ] Implement Authentication API using Laravel Sanctum
- [ ] Create Role Middleware (Admin, Trainer, Student)
- [ ] Create User model and database migration
- [ ] Develop Profile Update API

## Current Progress

- ✅ Laravel 12 project initialized.
- ✅ Project directory structure established.
- ✅ MVC architecture prepared.
- ✅ API routing configured.
- ✅ Ready for Authentication (Laravel Sanctum).

---

## Frontend Tasks

- [ ] Set up Vue 3 project with Vite
- [ ] Configure Vue Router
- [ ] Create Login and Register pages
- [ ] Implement Authentication Store using Pinia
- [ ] Build Dashboard Layout Shell
- [ ] Configure API service (Axios)

---

## Deliverables

### Backend
- Laravel 12 project initialized
- MySQL connected successfully
- Authentication APIs completed
- Role-based middleware implemented
- User management ready
- Profile update endpoint functional

### Frontend
- Vue 3 application initialized
- Routing configured
- Authentication pages completed
- Pinia authentication store integrated
- Dashboard layout created
- API service connected to backend

---