-- Nondon ERP - Test Database Initialization
-- This script runs automatically when PostgreSQL starts for the first time
-- (when the data volume is empty). It creates a dedicated test database
-- separate from the development database.
--
-- Development database: nondon (created by POSTGRES_DB env var)
-- Test database:        nondon_test (created by this script)

CREATE DATABASE nondon_test;
GRANT ALL PRIVILEGES ON DATABASE nondon_test TO nondon_app;
