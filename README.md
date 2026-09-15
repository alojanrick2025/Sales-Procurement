# Sales and Procurement Management System - Admin Panel

A comprehensive web-based **Sales and Procurement Management System** built with PHP, MySQL, and Bootstrap 5 for **JUSTLY ELECTRICAL SUPPLIES AND SERVICES**.

## Features

- **User Authentication & Role Management**:
  - Secure session-based authentication with bcrypt password hashing
  - Role-based permissions for **Administrator**
  - Profile management with customizable avatars and profile pictures

- **Sales and Procurement Dashboard**:
  - Real-time KPI summaries: Total Quotations, Pending Quotations, Customer POs, Supplier POs, Completed Orders, Cancelled Orders
  - Total Sales Value & Procurement Value metrics
  - Recent transactions activity table

- **Business Partners Management**:
  - **Clients**: Manage customer companies, contact persons, phone numbers, and addresses
  - **Suppliers**: Manage vendor profiles, contact details, payment terms, and notes

- **Quotations Management**:
  - Create and manage formal quotations with dynamic line items linked to inventory
  - Tax calculation (VAT), subtotal, and grand total auto-computation
  - Quotation statuses: Draft, Sent, Accepted, Rejected, Expired
  - Printable and exportable quotation views

- **Order Management**:
  - **Customer Purchase Orders (Sales POs)**: Create and track sales orders from clients
  - **Supplier Purchase Orders (Procurement POs)**: Create and manage purchasing orders sent to suppliers
  - Order workflows: Pending, Approved, Processing, Completed, Cancelled

- **Inventory Management**:
  - Product catalog (`item_list`) with item codes, descriptions, units, unit prices, and stock levels
  - Stock addition, editing, and low-stock indicators

- **Comprehensive Reports**:
  - Sales Reports with date range filtering and revenue metrics
  - Procurement Reports with supplier breakdown and expense tracking
  - Complete Transaction History

- **System Administration**:
  - Company profile settings (logo, contact info, business location)
  - User management (Create, Edit, Deactivate user accounts)

## Technology Stack

- **Backend**: PHP 7.4+ (Compatible with PHP 8.x)
- **Database**: MariaDB / MySQL
- **Frontend**: HTML5, Vanilla CSS / Bootstrap 5, Phosphor Icons
- **Web Server**: Apache (via XAMPP) or PHP Built-in Server

## Getting Started

### 1. Database Setup
- Start MySQL via XAMPP Control Panel.
- Import `database.sql` into MySQL (database name: `sales_procurement` or your configured database):
  ```bash
  mysql -u root < database.sql
  ```

### 2. Configure Database Connection
Edit [config.php](config.php) if your MySQL credentials differ:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sales_procurement');
```

### 3. Running the Server

#### Option A: One-Click Launcher (Recommended for Development)
Double click `start-server.bat` in the project root. It will:
1. Automatically start MySQL if it's not already running.
2. Launch the PHP development server on `http://localhost:8000`.
3. Open your default web browser to the login page.

#### Option B: Terminal
```bash
php -S localhost:8000 router.php
```
Then visit `http://localhost:8000` in your browser.

#### Option C: XAMPP Apache
See [SETUP-XAMPP.md](SETUP-XAMPP.md) for full Apache virtual host / alias configuration.

## Default Credentials

| Role | Username | Password |
| :--- | :--- | :--- |
| **Administrator** | `admin` | `admin123` |

> [!IMPORTANT]
> Change the default passwords immediately after your initial login via **My Profile** or **User Management**.

## Project Structure

```
├── admin/                  # Admin dashboard, user management, profile, system info
├── auth/                   # Login and logout handlers
├── clients/                # Client / customer management
├── includes/               # Common header, navbar, sidebar, and footer templates
├── items/                  # Inventory items and product catalog
├── migrations/             # Database migration scripts
├── orders/                 # Customer and Supplier Purchase Order modules
├── quotation/              # Quotation generation and management
├── reports/                # Sales, Procurement, and Transaction reporting
├── stocks/                 # Stock movement and management
├── suppliers/              # Supplier vendor management
├── uploads/                # User avatars and company logos
├── config.php              # Global configuration and database connection
├── database.sql            # Master database schema and seed data
├── router.php              # Routing handler for PHP server
└── start-server.bat        # Windows launcher script
```
