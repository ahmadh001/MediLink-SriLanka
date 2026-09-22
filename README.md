# MediLink Sri Lanka

MediLink Sri Lanka is a database-driven healthcare appointment management web application designed for the Sri Lankan context. The system connects clients with doctors and healthcare centres through a structured platform for provider discovery, appointment booking, schedule management, subscriptions, and administrative oversight.

## Project Purpose

The project demonstrates the design and implementation of a complete relational database application with multiple user roles and practical CRUD operations. It combines a responsive web interface with a PHP backend and a MySQL/MariaDB database while maintaining clear relationships between users, providers, doctors, appointments, schedules, subscriptions, plans, cities, and medical specializations.

## User Roles

### Client
Clients can create and manage an account, search for healthcare providers and doctors, filter available services, view doctor and provider information, select available appointment slots, book appointments, review booking confirmations, manage appointments, maintain profile and location information, and manage a client subscription plan.

### Healthcare Provider
Healthcare providers can manage their organization or professional profile, affiliated doctors, appointment slots and schedules, patient appointments, provider subscription information, and availability. Schedule management includes calendar and table views for easier day-to-day operation.

### Administrator
Administrators have a central management area for users, provider verification, appointments, subscriptions, plans, specializations, cities, database structure, and reports. Administrative pages provide search, filtering, status management, CRUD operations, and summary information for system monitoring.

## Main Features

- Multi-role authentication and authorization
- Client, provider, and administrator dashboards
- Doctor and healthcare provider discovery
- Location and search-radius based filtering
- Medical specialization management
- Appointment slot and schedule management
- Appointment booking and cancellation workflow
- Booking confirmation and printable receipt
- Client and provider subscription management
- Provider verification workflow
- User and account status management
- City and GPS location management
- Administrative reports and analytics
- Database schema and relationship overview
- Responsive Bootstrap-based user interface
- CSRF protection and password hashing
- Transaction-aware appointment booking logic

## Database Design

The application uses a relational MySQL/MariaDB database. Core entities include users, clients, providers, doctors, healthcare centres, cities, specializations, appointment slots, appointments, plans, subscriptions, and provider-doctor relationships.

Primary keys uniquely identify records, while foreign keys maintain relationships between related entities. The database design supports one-to-one, one-to-many, and many-to-many relationships where required. Referential integrity and application-level validation are used to keep appointment, provider, subscription, and user data consistent.

## CRUD Coverage

The project demonstrates Create, Read, Update, and Delete or status-management operations across the main functional areas. Examples include user registration and profile updates, provider and doctor management, slot creation and schedule updates, appointment booking and cancellation, plan management, specialization management, city management, and administrative account controls.

## Technology Stack

- PHP 8.x
- MySQL / MariaDB
- PDO
- HTML5
- CSS3
- JavaScript
- Bootstrap 5.3
- Bootstrap Icons
- Apache / XAMPP compatible environment

## Project Structure

- `admin/` — administrator dashboards and management pages
- `client/` — client dashboard, search, booking, appointments, profile, and subscription pages
- `provider/` — provider dashboard, doctors, slots, appointments, profile, and subscription pages
- `public/` — public pages, authentication, plans, and information pages
- `includes/` — shared authentication, navigation, security, subscription, and helper components
- `config/` — application and database configuration
- `database/` — database schema and stored database logic
- `public/css/` — application styling
- `public/js/` — shared frontend behaviour

## Security and Data Integrity

The application uses role-based access checks, prepared PDO queries, password hashing and verification, CSRF protection for state-changing forms, session handling, server-side validation, and transactional booking logic. Appointment slot updates are coordinated with booking operations to reduce inconsistent or duplicate reservations.

## Academic Scope

MediLink Sri Lanka is an academic database application intended to demonstrate database modelling, relational mapping, SQL operations, CRUD functionality, user-role separation, backend integration, and a complete web-based workflow. Subscription activation and other project-specific workflows are demonstration features and should not be interpreted as production payment processing or medical advice.


## Appointment approval workflow
Clients submit appointment requests as PENDING. The selected slot is locked to prevent double booking. The doctor or healthcare centre can Accept (CONFIRMED) or Reject (REJECTED); rejection releases the slot for booking again.
