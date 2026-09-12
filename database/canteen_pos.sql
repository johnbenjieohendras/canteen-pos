-- ============================================================
-- MINI CANTEEN CASHIERING + BACK OFFICE SYSTEM
-- Database: canteen_pos
-- Compatible with MySQL / MariaDB
-- ============================================================

CREATE DATABASE IF NOT EXISTS canteen_pos
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE canteen_pos;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- DROP EXISTING TABLES
-- ============================================================

DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS receipt_settings;
DROP TABLE IF EXISTS expenses;
DROP TABLE IF EXISTS inventory_movements;
DROP TABLE IF EXISTS sale_items;
DROP TABLE IF EXISTS sales;
DROP TABLE IF EXISTS stock_in_items;
DROP TABLE IF EXISTS stock_ins;
DROP TABLE IF EXISTS cashier_sessions;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

-- ============================================================
-- 1. USERS
-- ============================================================

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    username VARCHAR(50) NOT NULL UNIQUE,

    password VARCHAR(255) NOT NULL,

    full_name VARCHAR(100) NOT NULL,

    role ENUM(
        'admin',
        'cashier',
        'inventory'
    ) NOT NULL DEFAULT 'cashier',

    status ENUM(
        'active',
        'inactive'
    ) NOT NULL DEFAULT 'active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP

) ENGINE=InnoDB;

-- ============================================================
-- 2. CATEGORIES
-- ============================================================

CREATE TABLE categories (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    category_name VARCHAR(100) NOT NULL UNIQUE,

    description VARCHAR(255) DEFAULT NULL,

    status ENUM(
        'active',
        'inactive'
    ) NOT NULL DEFAULT 'active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP

) ENGINE=InnoDB;

-- ============================================================
-- 3. SUPPLIERS
-- ============================================================

CREATE TABLE suppliers (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    supplier_code VARCHAR(30) NOT NULL UNIQUE,

    supplier_name VARCHAR(150) NOT NULL,

    contact_person VARCHAR(100) DEFAULT NULL,

    contact_number VARCHAR(50) DEFAULT NULL,

    email VARCHAR(100) DEFAULT NULL,

    address VARCHAR(255) DEFAULT NULL,

    status ENUM(
        'active',
        'inactive'
    ) NOT NULL DEFAULT 'active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP

) ENGINE=InnoDB;

-- ============================================================
-- 4. PRODUCTS
-- ============================================================

CREATE TABLE products (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    product_code VARCHAR(50) NOT NULL UNIQUE,

    barcode VARCHAR(100) DEFAULT NULL UNIQUE,

    product_name VARCHAR(150) NOT NULL,

    category_id INT UNSIGNED DEFAULT NULL,

    supplier_id INT UNSIGNED DEFAULT NULL,

    unit VARCHAR(30) NOT NULL DEFAULT 'pcs',

    cost_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,

    selling_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,

    stock DECIMAL(12,3) NOT NULL DEFAULT 0.000,

    reorder_level DECIMAL(12,3) NOT NULL DEFAULT 5.000,

    status ENUM(
        'active',
        'inactive'
    ) NOT NULL DEFAULT 'active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_products_category
        FOREIGN KEY (category_id)
        REFERENCES categories(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT fk_products_supplier
        FOREIGN KEY (supplier_id)
        REFERENCES suppliers(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    INDEX idx_product_name (product_name),

    INDEX idx_product_barcode (barcode),

    INDEX idx_product_category (category_id)

) ENGINE=InnoDB;

-- ============================================================
-- 5. CASHIER SESSIONS
-- ============================================================

CREATE TABLE cashier_sessions (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED NOT NULL,

    opening_cash DECIMAL(12,2)
        NOT NULL DEFAULT 0.00,

    closing_cash DECIMAL(12,2)
        DEFAULT NULL,

    expected_cash DECIMAL(12,2)
        DEFAULT NULL,

    cash_difference DECIMAL(12,2)
        DEFAULT NULL,

    opened_at DATETIME
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    closed_at DATETIME
        DEFAULT NULL,

    status ENUM(
        'open',
        'closed'
    ) NOT NULL DEFAULT 'open',

    notes VARCHAR(255)
        DEFAULT NULL,

    CONSTRAINT fk_sessions_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    INDEX idx_session_user (user_id),

    INDEX idx_session_status (status),

    INDEX idx_session_opened (opened_at)

) ENGINE=InnoDB;

-- ============================================================
-- 6. STOCK IN
-- ============================================================

CREATE TABLE stock_ins (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    stock_in_no VARCHAR(50) NOT NULL UNIQUE,

    supplier_id INT UNSIGNED DEFAULT NULL,

    user_id INT UNSIGNED NOT NULL,

    total_amount DECIMAL(12,2)
        NOT NULL DEFAULT 0.00,

    reference_no VARCHAR(100)
        DEFAULT NULL,

    notes VARCHAR(255)
        DEFAULT NULL,

    stock_in_date DATETIME
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    created_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_stockins_supplier
        FOREIGN KEY (supplier_id)
        REFERENCES suppliers(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT fk_stockins_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    INDEX idx_stockin_date (stock_in_date)

) ENGINE=InnoDB;

-- ============================================================
-- 7. STOCK IN ITEMS
-- ============================================================

CREATE TABLE stock_in_items (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    stock_in_id BIGINT UNSIGNED NOT NULL,

    product_id INT UNSIGNED NOT NULL,

    quantity DECIMAL(12,3)
        NOT NULL DEFAULT 0.000,

    cost_price DECIMAL(12,2)
        NOT NULL DEFAULT 0.00,

    subtotal DECIMAL(12,2)
        NOT NULL DEFAULT 0.00,

    CONSTRAINT fk_stockinitems_stockin
        FOREIGN KEY (stock_in_id)
        REFERENCES stock_ins(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_stockinitems_product
        FOREIGN KEY (product_id)
        REFERENCES products(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    INDEX idx_stockinitems_stockin (stock_in_id),

    INDEX idx_stockinitems_product (product_id)

) ENGINE=InnoDB;

-- ============================================================
-- 8. INVENTORY MOVEMENTS
-- ============================================================

CREATE TABLE inventory_movements (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    product_id INT UNSIGNED NOT NULL,

    user_id INT UNSIGNED DEFAULT NULL,

    movement_type ENUM(
        'STOCK_IN',
        'SALE',
        'ADJUSTMENT_ADD',
        'ADJUSTMENT_SUBTRACT',
        'VOID',
        'RETURN'
    ) NOT NULL,

    reference_type VARCHAR(50)
        DEFAULT NULL,

    reference_id BIGINT UNSIGNED
        DEFAULT NULL,

    quantity DECIMAL(12,3)
        NOT NULL DEFAULT 0.000,

    stock_before DECIMAL(12,3)
        NOT NULL DEFAULT 0.000,

    stock_after DECIMAL(12,3)
        NOT NULL DEFAULT 0.000,

    remarks VARCHAR(255)
        DEFAULT NULL,

    created_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_inventory_product
        FOREIGN KEY (product_id)
        REFERENCES products(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_inventory_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    INDEX idx_inventory_product (product_id),

    INDEX idx_inventory_type (movement_type),

    INDEX idx_inventory_date (created_at)

) ENGINE=InnoDB;

-- ============================================================
-- 9. SALES
-- ============================================================

CREATE TABLE sales (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    transaction_no VARCHAR(50)
        NOT NULL UNIQUE,

    cashier_session_id BIGINT UNSIGNED
        DEFAULT NULL,

    cashier_id INT UNSIGNED NOT NULL,

    subtotal DECIMAL(12,2)
        NOT NULL DEFAULT 0.00,

    discount DECIMAL(12,2)
        NOT NULL DEFAULT 0.00,

    tax DECIMAL(12,2)
        NOT NULL DEFAULT 0.00,

    total_amount DECIMAL(12,2)
        NOT NULL DEFAULT 0.00,

    amount_tendered DECIMAL(12,2)
        NOT NULL DEFAULT 0.00,

    change_amount DECIMAL(12,2)
        NOT NULL DEFAULT 0.00,

    payment_method ENUM(
        'CASH',
        'GCASH',
        'CARD',
        'OTHER'
    ) NOT NULL DEFAULT 'CASH',

    status ENUM(
        'COMPLETED',
        'VOID',
        'REFUNDED'
    ) NOT NULL DEFAULT 'COMPLETED',

    void_reason VARCHAR(255)
        DEFAULT NULL,

    transaction_date DATETIME
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    created_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_sales_session
        FOREIGN KEY (cashier_session_id)
        REFERENCES cashier_sessions(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT fk_sales_cashier
        FOREIGN KEY (cashier_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    INDEX idx_sales_transaction_date
        (transaction_date),

    INDEX idx_sales_cashier
        (cashier_id),

    INDEX idx_sales_status
        (status),

    INDEX idx_sales_payment
        (payment_method)

) ENGINE=InnoDB;

-- ============================================================
-- 10. SALE ITEMS
-- ============================================================

CREATE TABLE sale_items (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    sale_id BIGINT UNSIGNED NOT NULL,

    product_id INT UNSIGNED NOT NULL,

    product_name VARCHAR(150) NOT NULL,

    quantity DECIMAL(12,3)
        NOT NULL DEFAULT 0.000,

    cost_price DECIMAL(12,2)
        NOT NULL DEFAULT 0.00,

    selling_price DECIMAL(12,2)
        NOT NULL DEFAULT 0.00,

    discount DECIMAL(12,2)
        NOT NULL DEFAULT 0.00,

    subtotal DECIMAL(12,2)
        NOT NULL DEFAULT 0.00,

    CONSTRAINT fk_saleitems_sale
        FOREIGN KEY (sale_id)
        REFERENCES sales(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_saleitems_product
        FOREIGN KEY (product_id)
        REFERENCES products(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    INDEX idx_saleitems_sale
        (sale_id),

    INDEX idx_saleitems_product
        (product_id)

) ENGINE=InnoDB;

-- ============================================================
-- 11. EXPENSES
-- ============================================================

CREATE TABLE expenses (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    expense_no VARCHAR(50)
        NOT NULL UNIQUE,

    user_id INT UNSIGNED NOT NULL,

    expense_category VARCHAR(100)
        NOT NULL,

    description VARCHAR(255)
        DEFAULT NULL,

    amount DECIMAL(12,2)
        NOT NULL DEFAULT 0.00,

    expense_date DATETIME
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    created_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_expenses_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    INDEX idx_expense_date
        (expense_date),

    INDEX idx_expense_category
        (expense_category)

) ENGINE=InnoDB;

-- ============================================================
-- 12. AUDIT LOGS
-- ============================================================

CREATE TABLE audit_logs (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED DEFAULT NULL,

    action VARCHAR(100) NOT NULL,

    module VARCHAR(100)
        DEFAULT NULL,

    reference_id BIGINT UNSIGNED
        DEFAULT NULL,

    description VARCHAR(500)
        DEFAULT NULL,

    ip_address VARCHAR(45)
        DEFAULT NULL,

    created_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_audit_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    INDEX idx_audit_user
        (user_id),

    INDEX idx_audit_action
        (action),

    INDEX idx_audit_date
        (created_at)

) ENGINE=InnoDB;

-- ============================================================
-- 13. RECEIPT SETTINGS
-- ============================================================

CREATE TABLE receipt_settings (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    company_name VARCHAR(150)
        NOT NULL DEFAULT 'CANTEEN POS',

    address VARCHAR(255)
        DEFAULT NULL,

    contact_number VARCHAR(100)
        DEFAULT NULL,

    email VARCHAR(150)
        DEFAULT NULL,

    receipt_title VARCHAR(100)
        NOT NULL DEFAULT 'SALES RECEIPT',

    footer_message VARCHAR(255)
        DEFAULT 'Thank you for your purchase!',

    show_address TINYINT(1)
        NOT NULL DEFAULT 1,

    show_contact TINYINT(1)
        NOT NULL DEFAULT 1,

    show_email TINYINT(1)
        NOT NULL DEFAULT 0,

    updated_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP

) ENGINE=InnoDB;

-- ============================================================
-- SAMPLE USERS
-- ============================================================

INSERT INTO users
(
    username,
    password,
    full_name,
    role,
    status
)
VALUES
(
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC1uBqKz3mWqfZfWJ4u',
    'System Administrator',
    'admin',
    'active'
),
(
    'cashier',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC1uBqKz3mWqfZfWJ4u',
    'Canteen Cashier',
    'cashier',
    'active'
),
(
    'inventory',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC1uBqKz3mWqfZfWJ4u',
    'Inventory Staff',
    'inventory',
    'active'
);

-- ============================================================
-- DEFAULT RECEIPT SETTINGS
-- ============================================================

INSERT INTO receipt_settings
(
    company_name,
    address,
    contact_number,
    email,
    receipt_title,
    footer_message,
    show_address,
    show_contact,
    show_email
)
VALUES
(
    'CANTEEN POS',
    'Cebu City, Philippines',
    '',
    '',
    'SALES RECEIPT',
    'Thank you for your purchase!',
    1,
    1,
    0
);

-- ============================================================
-- SAMPLE CATEGORIES
-- ============================================================

INSERT INTO categories
(
    category_name,
    description
)
VALUES
(
    'Beverages',
    'Soft drinks, juice, water and other drinks'
),
(
    'Snacks',
    'Chips, biscuits and packed snacks'
),
(
    'Meals',
    'Rice meals and cooked food'
),
(
    'School Supplies',
    'Pens, pencils, paper and other supplies'
),
(
    'Desserts',
    'Desserts and sweet products'
),
(
    'Others',
    'Other canteen products'
);

-- ============================================================
-- SAMPLE SUPPLIERS
-- ============================================================

INSERT INTO suppliers
(
    supplier_code,
    supplier_name,
    contact_person,
    contact_number,
    address
)
VALUES
(
    'SUP-001',
    'Sample Beverage Supplier',
    'Juan Supplier',
    '09170000001',
    'Cebu City'
),
(
    'SUP-002',
    'Sample Food Supplier',
    'Maria Supplier',
    '09170000002',
    'Cebu City'
);

-- ============================================================
-- SAMPLE PRODUCTS
-- ============================================================

INSERT INTO products
(
    product_code,
    barcode,
    product_name,
    category_id,
    supplier_id,
    unit,
    cost_price,
    selling_price,
    stock,
    reorder_level
)
VALUES
(
    'BEV-001',
    '480000000001',
    'Coke 1.5L',
    1,
    1,
    'bottle',
    45.00,
    55.00,
    20,
    5
),
(
    'BEV-002',
    '480000000002',
    'Mineral Water 500ml',
    1,
    1,
    'bottle',
    10.00,
    15.00,
    30,
    5
),
(
    'BEV-003',
    '480000000003',
    'C2 Green Tea',
    1,
    1,
    'bottle',
    12.00,
    18.00,
    25,
    5
),
(
    'SNK-001',
    '480000000004',
    'Piattos Cheese',
    2,
    1,
    'pack',
    15.00,
    20.00,
    25,
    5
),
(
    'SNK-002',
    '480000000005',
    'Nova',
    2,
    1,
    'pack',
    15.00,
    20.00,
    25,
    5
),
(
    'SNK-003',
    '480000000006',
    'Chippy',
    2,
    1,
    'pack',
    10.00,
    15.00,
    30,
    5
),
(
    'MEL-001',
    '480000000007',
    'Siomai',
    3,
    2,
    'piece',
    5.00,
    8.00,
    50,
    10
),
(
    'MEL-002',
    '480000000008',
    'Fried Chicken',
    3,
    2,
    'piece',
    35.00,
    50.00,
    30,
    5
),
(
    'MEL-003',
    '480000000009',
    'Rice',
    3,
    2,
    'cup',
    8.00,
    12.00,
    50,
    10
),
(
    'SUP-001',
    '480000000010',
    'Ballpen',
    4,
    NULL,
    'piece',
    8.00,
    12.00,
    20,
    5
);

-- ============================================================
-- INITIAL INVENTORY MOVEMENTS
-- ============================================================

INSERT INTO inventory_movements
(
    product_id,
    user_id,
    movement_type,
    reference_type,
    quantity,
    stock_before,
    stock_after,
    remarks
)
SELECT
    id,
    1,
    'STOCK_IN',
    'INITIAL_STOCK',
    stock,
    0,
    stock,
    'Initial system stock'
FROM products;

-- ============================================================
-- VIEW: LOW STOCK PRODUCTS
-- ============================================================

DROP VIEW IF EXISTS v_low_stock_products;

CREATE VIEW v_low_stock_products AS

SELECT

    p.id,

    p.product_code,

    p.barcode,

    p.product_name,

    c.category_name,

    p.stock,

    p.reorder_level,

    p.unit,

    p.selling_price

FROM products p

LEFT JOIN categories c
    ON c.id = p.category_id

WHERE p.status = 'active'

AND p.stock <= p.reorder_level;

-- ============================================================
-- VIEW: SALES SUMMARY
-- ============================================================

DROP VIEW IF EXISTS v_sales_summary;

CREATE VIEW v_sales_summary AS

SELECT

    DATE(s.transaction_date)
        AS sale_date,

    COUNT(s.id)
        AS total_transactions,

    SUM(s.subtotal)
        AS subtotal,

    SUM(s.discount)
        AS discount,

    SUM(s.tax)
        AS tax,

    SUM(s.total_amount)
        AS total_sales

FROM sales s

WHERE s.status = 'COMPLETED'

GROUP BY
    DATE(s.transaction_date);

-- ============================================================
-- VIEW: PRODUCT SALES
-- ============================================================

DROP VIEW IF EXISTS v_product_sales;

CREATE VIEW v_product_sales AS

SELECT

    si.product_id,

    si.product_name,

    SUM(si.quantity)
        AS total_quantity_sold,

    SUM(si.subtotal)
        AS total_sales,

    SUM(
        (
            si.selling_price
            - si.cost_price
        ) * si.quantity
    )
        AS estimated_profit

FROM sale_items si

INNER JOIN sales s
    ON s.id = si.sale_id

WHERE s.status = 'COMPLETED'

GROUP BY

    si.product_id,

    si.product_name;

-- ============================================================
-- RE-ENABLE FOREIGN KEYS
-- ============================================================

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- CHECK DATABASE
-- ============================================================

SELECT
    'Database setup completed successfully!'
    AS message;

SELECT
    TABLE_NAME

FROM information_schema.TABLES

WHERE TABLE_SCHEMA = 'canteen_pos'

ORDER BY TABLE_NAME;