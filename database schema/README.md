DanFam Market – Agribusiness Web Application

Run

1. Start XAMPP (Apache & MySQL)
2. Import schema: MySQL phpMyAdmin -> Create database `danfam_market` -> Exec schema
3. Access: http://127.0.0.1/Agribusines_market/ index.html


 admin: obieromikwa@gmail.com / awendo2017 
 farmer: Ann nafula / ann1234
 buyer: Phaline kemunto / Ph1234
  Set IPv4 127.0.0.1 in config.php for low latency.


Structure

config.php – PDO database connection settings (MySQL / MariaDB)

functions.php – Utility functions, session guards, and input sanitization

process.php – Central backend controller (produce CRUD, cart sync, status management)

catalogue.php / product.php – Public marketplace produce listing and details

cart.php / orders.php – Shopping cart, delivery checkout, and order tracking

form.php – Farmer and Admin produce publishing/editing interface

admin_dashboard.php / admin_receipt.php – Admin moderation panel & printable payment receipt generator

uploads/ – Secure directory for uploaded produce images

ER diagram

erDiagram
  USERS ||--o{ PRODUCE : "farmer lists"
  CATEGORIES ||--o{ PRODUCE : classifies
  USERS ||--o{ ORDERS : "buyer places"
  ORDERS ||--|{ ORDER_ITEMS : contains
  PRODUCE ||--o{ ORDER_ITEMS : "ordered as"


Security

PDO Parameterised SQL (prevents SQL injection), password hashing (password_hash), session role guards (Buyer, Farmer, Administrator), CSRF token validation, XSS escaping (htmlspecialchars), MIME/5MB upload limits, PDO database transaction safety (beginTransaction/commit).

Deploy

Any XAMPP host (Apache + PHP 8.0+ + MySQL). For local simulation, run Apache/MySQL under XAMPP at http://127.0.0.1/Agribusines_market/.