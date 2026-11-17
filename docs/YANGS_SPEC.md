# PROJECT: YANGS – Bar Management and Point-of-Sale (POS) System

## 1. Project Overview

Develop a comprehensive, modern, and user-friendly **Point-of-Sale (POS) and Business Management System** for YANGS, a small bar business.

The primary objective is to digitize and organize the entire business operation, from customer ordering and table management to payments, inventory, expenses, employee scheduling, salaries, and financial reporting.

The system should allow the owner to monitor business operations, track every transaction, understand business expenses, calculate profits, manage inventory, and oversee employee activities from a centralized platform.

This should be an integrated management system where the owner can monitor almost everything happening within the business.

## 2. Technology Stack

Use the following technologies:

- **Backend:** Latest stable Laravel
- **Frontend:** Laravel Blade
- **Database:** PostgreSQL
- **CSS Framework:** Tailwind CSS
- **Architecture:** MVC with RESTful API architecture
- **API:** Versioned REST API (e.g., `/api/v1/`)
- **Authentication:** Laravel Authentication
- **Authorization:** Role-Based Access Control (RBAC)

### Technical Requirements

1. Use Laravel Blade for the primary web application interface.
2. Implement RESTful API endpoints for core business operations.
3. Structure the application so a future Android or iOS mobile application can communicate with the same backend through the REST API.
4. Use PostgreSQL for reliable relational data management.
5. Follow Laravel conventions, clean code principles, and maintainable architecture.
6. Implement proper database relationships, migrations, seeders, form validation, and error handling.
7. Ensure the system is responsive and optimized for desktop computers, laptops, and tablets.
8. Prioritize simplicity, usability, and performance.

---

## 3. User Roles and Access Management

The system must support multiple user roles with different permissions.

### A. Owner / Administrator

The owner has full access to the entire system.

Responsibilities and permissions:

- View the overall business dashboard.
- Monitor all sales and transactions.
- View daily, weekly, monthly, and yearly revenue.
- Monitor expenses, costs, and net profit.
- Manage products, categories, and prices.
- Manage inventory and stock movements.
- Manage employees and their information.
- Create and manage employee schedules.
- Manage employee salaries and payroll records.
- View cashier transactions.
- View system activity logs.
- Manage user accounts and permissions.
- Access all financial and operational reports.
- Configure system settings.

### B. Cashier

The cashier handles daily sales and payment operations.

Permissions:

- View available tables and their current status.
- Create customer orders.
- Add, update, and remove items from an active order, subject to permissions.
- View current orders and order totals.
- Process customer payments.
- Generate and print receipts.
- View previous transactions based on assigned permissions.
- View their own cashier activity and transaction history.

Cashiers must not have unrestricted access to sensitive business information such as overall profits, employee salaries, or administrative settings.

### C. Additional Roles

Design the permission system to support future roles, such as:

- Bartender
- Staff
- Inventory Manager
- Supervisor

Permissions should be configurable and should not be hardcoded exclusively for the initial roles.

---

## 4. Table Management and POS Ordering

This is one of the most important features of the system.

YANGS operates as a bar where customers can order drinks and other products while staying at their assigned tables.

The system must support an **Open Tab / Pay Later ordering workflow**.

### A. Table Management

- Display all available tables.
- Each table must have a unique table number or identifier.
- Show the current status of each table.
- Allow the cashier to open a customer session for a table.
- Allow customers to order multiple times during their visit.
- Maintain the complete order history for each table session.

Suggested table statuses:

- Available
- Occupied
- Awaiting Payment
- Reserved
- Cleaning

### B. Ordering Workflow

Customers should NOT be required to pay immediately after placing an order.

The expected workflow is:

1. The cashier selects an available table.
2. The cashier creates an order for that table.
3. The cashier adds the customer's requested products.
4. The order is saved as an open transaction.
5. The customer can continue ordering additional products.
6. New orders are added to the existing table session.
7. The system continuously updates the running bill.
8. When the customer is finished, the cashier opens the table's consolidated bill.
9. The cashier reviews all ordered products, quantities, prices, and the total amount.
10. The customer pays the bill.
11. Once payment is successfully recorded, the transaction is marked as Paid.
12. The table session is closed and the table becomes Available again.

### C. Order Management

Each order must record:

- Order reference number.
- Table number.
- Cashier who created the order.
- Date and time.
- Ordered products.
- Product quantities.
- Unit prices at the time of ordering.
- Subtotals.
- Discounts, if applicable.
- Total amount.
- Order status.
- Payment status.

The system must preserve historical transaction information even when product prices are changed in the future.

### D. Payment Management

Support:

- Cash
- GCash
- Other configurable payment methods

Additional requirements:

- Calculate the amount paid.
- Calculate change for cash payments.
- Support split payments and partial payments.
- Maintain payment history.
- Prevent an order from being marked fully paid unless the recorded payments cover the total amount.
- Generate receipts.
- Support authorized voids, refunds, and transaction corrections with audit records.

**Important:** Never automatically mark an order as Paid just because an order was created or saved. Payment status must only change when an actual payment is successfully recorded.

---

## 5. Product and Menu Management

Create a centralized product management module.

Features:

- Create, update, and archive products.
- Organize products into categories.
- Set product names, descriptions, prices, and images.
- Define product availability.
- Track product costs.
- Set selling prices.
- Calculate gross margins.
- Support different product types.
- Support configurable product options or variations where necessary.

Examples of categories:

- Beer
- Liquor
- Cocktails
- Wine
- Soft Drinks
- Water
- Food and Snacks
- Other Products

The system should allow the owner to easily add new products and categories without modifying the source code.

---

## 6. Inventory Management

The system must track the movement of all products and supplies.

### A. Stock-In

Record incoming inventory whenever the business purchases or receives new stock.

Information should include:

- Product or item.
- Quantity received.
- Purchase cost.
- Supplier.
- Date received.
- Invoice or reference number.
- Person responsible for receiving the stock.

### B. Stock-Out

Automatically deduct inventory when applicable products are sold.

The system must also support manual stock-out transactions for:

- Damaged products.
- Expired products.
- Missing inventory.
- Internal consumption.
- Other authorized adjustments.

Every stock-out operation must have a recorded reason.

### C. Inventory Monitoring

- Current stock levels.
- Stock movement history.
- Low-stock notifications.
- Out-of-stock notifications.
- Inventory valuation.
- Stock adjustment history.
- Inventory reports.

### D. Inventory Accuracy

Use database transactions to ensure that orders and their corresponding inventory deductions remain consistent.

For products sold individually from larger packages, support configurable inventory conversion units when applicable.

For example, a case of beer may contain multiple bottles, and selling one bottle should deduct the correct quantity from inventory.

---

## 7. Financial Management

The owner must have a clear picture of the business's financial performance.

### A. Sales Monitoring

Track:

- Total sales.
- Daily sales.
- Weekly sales.
- Monthly sales.
- Yearly sales.
- Sales by product.
- Sales by category.
- Sales by cashier.
- Sales by payment method.

### B. Expense Management

Record all business expenses.

Examples:

- Product purchases.
- Electricity.
- Water.
- Rent.
- Employee-related expenses.
- Maintenance.
- Transportation.
- Other operational expenses.

Each expense should contain:

- Expense category.
- Description.
- Amount.
- Date.
- Recorded by.
- Optional receipt or attachment.

### C. Cost and Profit Monitoring

The system should calculate:

- Cost of Goods Sold (COGS).
- Gross Profit.
- Operating Expenses.
- Net Profit.
- Product Profit Margins.

Use the following basic calculations:

Gross Profit = Net Sales - Cost of Goods Sold

Net Profit = Gross Profit - Operating Expenses

The system must distinguish between sales revenue, inventory purchases, actual cost of goods sold, and operating expenses to avoid incorrectly calculating profit or double-counting costs.

Provide financial summaries with date filters.

---

## 8. Employee Management and Payroll

Create an employee management module to organize the people working at YANGS.

### A. Employee Records

Store:

- Full name.
- Contact information.
- Address.
- Position.
- Employment status.
- Date hired.
- Salary information.
- Emergency contact, if necessary.

### B. Employee Scheduling

The owner or authorized administrator should be able to create and manage employee work schedules.

Features:

- Assign employees to specific shifts.
- Set duty dates.
- Set start and end times.
- View daily and weekly schedules.
- Track assigned employees.
- Detect overlapping schedules.
- Record schedule changes.
- Allow employees to have different work schedules.

Example shifts:

- Morning Shift
- Afternoon Shift
- Evening Shift
- Custom Shift

The system should provide an easy-to-understand weekly scheduling interface.

### C. Attendance Monitoring

Support:

- Time In.
- Time Out.
- Attendance history.
- Late attendance records.
- Absences.
- Overtime records, if applicable.

### D. Salary and Payroll Management

The system should support different salary arrangements, such as:

- Daily wage.
- Weekly salary.
- Monthly salary.
- Custom salary arrangements.

Track:

- Basic salary.
- Total working days or hours.
- Overtime.
- Advances.
- Deductions.
- Bonuses.
- Net salary.
- Payment date.
- Payroll status.

Maintain historical payroll records so previous salary payments are never overwritten.

---

## 9. System Activity Logs and Audit Trail

The system must record important activities performed by every user.

The owner should be able to see who performed an action, what action was performed, and when it happened.

Examples:

- User login and logout.
- Orders created.
- Products added to orders.
- Orders modified.
- Orders cancelled.
- Payments recorded.
- Refunds processed.
- Inventory added.
- Inventory deducted.
- Product prices changed.
- Expenses recorded.
- Employee schedules modified.
- Salary payments recorded.
- User accounts created or modified.

Each activity log should contain:

- User ID.
- Username or employee name.
- Action performed.
- Affected record.
- Previous value, when applicable.
- New value, when applicable.
- Date and time.

Critical financial and inventory records must not be silently deleted or modified.

---

## 10. Dashboard and Reports

Create different dashboards based on user roles.

### Owner Dashboard

Display important business information:

- Today's total sales.
- Today's total expenses.
- Estimated gross profit.
- Estimated net profit.
- Number of active table sessions.
- Number of completed transactions.
- Best-selling products.
- Low-stock products.
- Current inventory value.
- Upcoming employee shifts.
- Recent business activities.

Include charts and visual summaries where useful.

### Cashier Dashboard

Display:

- Available tables.
- Occupied tables.
- Active customer sessions.
- Pending payments.
- Recent transactions.
- Current cashier shift information.

### Reports

Generate reports for:

1. Sales Reports.
2. Daily Cashier Reports.
3. Product Sales Reports.
4. Inventory Reports.
5. Stock Movement Reports.
6. Expense Reports.
7. Profit and Loss Reports.
8. Employee Attendance Reports.
9. Employee Payroll Reports.
10. Transaction and Payment Reports.

Reports should support date filters and export options such as PDF and Excel.

---

## 11. Notifications and Alerts

Implement notifications for important business events.

Examples:

- Low inventory.
- Out-of-stock products.
- Pending table payments.
- Upcoming employee shifts.
- Employee attendance issues.
- Unusual inventory adjustments.
- Important transaction activities.

Notifications should be relevant to the user's role and permissions.

---

## 12. UI/UX Design Requirements

The interface should be modern, clean, professional, and easy to understand.

YANGS is a small bar, so the application should prioritize usability rather than unnecessary complexity.

### Design Guidelines

- Modern POS interface.
- Clean dashboard.
- Intuitive navigation.
- Responsive layouts.
- Easy-to-read tables and transaction lists.
- Clear status indicators.
- Simple forms.
- Consistent spacing and typography.
- Minimal unnecessary interactions.
- Fast access to frequently used features.

### Tablet Compatibility

The application MUST be fully responsive and optimized for tablet devices.

Important considerations:

- Touch-friendly buttons.
- Large clickable areas.
- Easy product selection.
- Convenient table switching.
- Readable transaction summaries.
- Responsive navigation.
- POS interface optimized for landscape and portrait orientations.

The cashier should be able to operate the entire ordering and payment workflow comfortably using a tablet without needing a desktop computer.

---

## 13. Security and Data Integrity

Implement appropriate security measures:

- Secure authentication.
- Role-based authorization.
- Server-side validation.
- CSRF protection.
- API authentication.
- Rate limiting.
- Secure password hashing.
- Protection against SQL injection and XSS.
- Database transaction management.
- Audit logging.
- Proper session management.

Financial transactions, inventory movements, and payroll records must maintain data consistency.

Use soft deletes or archival where appropriate instead of permanently deleting important business records.

---

## 14. Suggested Core Modules

Organize the application into the following modules:

1. Dashboard
2. POS / Cashier
3. Table Management
4. Order Management
5. Payment Management
6. Product Management
7. Category Management
8. Inventory Management
9. Supplier Management
10. Expense Management
11. Financial Management
12. Employee Management
13. Employee Scheduling
14. Attendance Management
15. Payroll Management
16. Reports and Analytics
17. Notifications
18. User and Role Management
19. Activity Logs
20. System Settings

---

## 15. Development Guidelines for the AI Coding Agent

Before implementing the system, analyze the complete requirements and establish a proper development plan.

### Development Rules

1. Do not build the entire application in one large implementation.
2. Develop the system module by module.
3. Establish the database architecture and entity relationships before implementing business logic.
4. Implement the core POS workflow before secondary management features.
5. Follow clean architecture principles while respecting Laravel's conventions.
6. Keep business logic separate from controllers where appropriate.
7. Use reusable Blade components and layouts.
8. Create reusable services for complex business operations.
9. Implement REST API endpoints alongside the relevant business functionality.
10. Maintain consistent validation and error handling.
11. Write database seeders for initial roles, permissions, and sample data.
12. Test important business workflows before proceeding to the next module.
13. Do not use fake or hardcoded dashboard statistics in the completed system.
14. Never invent business records or silently modify historical transactions.
15. Do not introduce unnecessary packages or overengineer simple features.

### Recommended Development Phases

**Phase 1 – Foundation**
- Laravel setup.
- PostgreSQL configuration.
- Authentication.
- User roles and permissions.
- Database architecture.
- Base UI layout.

**Phase 2 – Core POS**
- Table management.
- Product and category management.
- Customer table sessions.
- Order creation.
- Open tabs.
- Consolidated billing.
- Payment processing.
- Receipt generation.

**Phase 3 – Inventory**
- Suppliers.
- Stock-in.
- Stock-out.
- Inventory movements.
- Low-stock monitoring.
- Product cost tracking.

**Phase 4 – Financial Management**
- Sales monitoring.
- Expense tracking.
- Cost calculations.
- Profit and loss reporting.

**Phase 5 – Employee Management**
- Employee records.
- Scheduling.
- Attendance.
- Salary and payroll.

**Phase 6 – Dashboard and Reporting**
- Owner dashboard.
- Cashier dashboard.
- Business analytics.
- Reports.
- Export functionality.

**Phase 7 – API and Integration**
- Complete REST API.
- API authentication.
- API documentation.
- API testing.
- Prepare the backend for future mobile application integration.

**Phase 8 – Testing and Deployment**
- End-to-end testing.
- POS transaction testing.
- Inventory consistency testing.
- Permission testing.
- Tablet responsiveness testing.
- Performance optimization.
- Production deployment preparation.

---

## 16. Expected Final Result

The final output should be a fully functional, integrated YANGS Bar Management and POS System.

The owner should be able to manage and monitor the entire business through one application, while cashiers can efficiently handle customer orders and payments.

The most important requirement is that the system must accurately track every transaction, maintain inventory records, calculate business costs and profits, manage employees, and preserve a complete history of activities.

**Build the application with simplicity, accuracy, maintainability, and future scalability as the primary priorities.**

Remember: This is intended for an actual small bar business, not merely a demonstration or portfolio project. All major business workflows must function correctly.