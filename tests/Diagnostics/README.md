# Diagnostics snapshot tests

Pure Pest tests protect typed/case-insensitive header lookup, detached input references, readonly arrays and rejection of mutable or ambiguous input. Real parser/trust/active-container behavior is in `tests/Cloudflare/DiagnosticsTest.php`; native console and translation behavior is in `DoctorTest.php`. Run through root `composer test` in a supported PHP container.
