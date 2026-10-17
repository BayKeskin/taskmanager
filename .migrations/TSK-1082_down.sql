-- Rollback Migration for TSK-1082
-- Task: [Deploy-2] Barındırma Paneli API Adaptörleri (cPanel UAPI, Plesk, CyberPanel & Fallback)
-- Note: TSK-1082 introduced hosting panel adaptors under System/HostingAdapters/ and API/AJAX routing endpoints.
-- No persistent database table structure (DDL) was altered.
-- Clean up any test databases or test events if remaining:
DROP DATABASE IF EXISTS tm_test_autodb_test;
