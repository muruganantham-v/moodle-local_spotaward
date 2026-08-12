### 5. lib.php is Excessively Large — 2013 Lines / 88KB
**File**: lib.php
This single file contains Navigation hooks, Inline CSS, Inline JavaScript, All rendering functions, The entire nomination form widget factory, Student/course report renderers.
**Recommendation**: Extract rendering functions into the output/renderer.php class. Move JavaScript to AMD modules in md/src/. Use Mustache templates for HTML generation.

### 6. pi.php is 272KB — Extraordinarily Large
**File**: classes/local/api.php
This single class file is one of the largest single PHP files. It likely contains all business logic, database queries, email sending, certificate generation, draft management, and file handling in one monolithic class.

### 7. defined('MOODLE_INTERNAL') Check Missing in Multiple Files
**Files**: index.php, submission.php, jax.php, download_details.php
lib.php has the use statement on line 4 before the defined('MOODLE_INTERNAL') check on line 6. It violates Moodle coding standards which require the guard to be the first executable statement.

### 8. Functions Defined in index.php Instead of lib.php
**File**: index.php
Four functions are defined directly in index.php. These should live in lib.php or a dedicated class.

### 9. unction_exists() Guard in settings.php — Anti-Pattern
**File**: settings.php
This pattern hides potential bugs (double-include issues) and prevents proper autoloading.

### 10. Inconsistent Indentation in settings.php
**File**: settings.php
Settings additions switch between 4-space indentation and zero indentation within the same if () block.

### 11. Hardcoded English Strings in Multiple Files
**Files**: Various
Several places have hardcoded English text instead of using get_string(). This makes the plugin non-translatable.

### 12. Inline CSS Injected via JavaScript in lib.php
**File**: lib.php
The success overlay injects ~30 CSS rules via JavaScript string concatenation. The nomination form widget injects another ~30+ CSS rules. Move all CSS to styles.css.

### 13. iew_certificate.php Uses equire_once('../../config.php') — Inconsistent Path Style
**File**: iew_certificate.php
All other files use absolute paths (__DIR__). The relative path can break if PHP's working directory changes.

### 14. iew_certificate.php — Manual API Class Include
**File**: iew_certificate.php
Bypasses Moodle's autoloader by manually including the api class.
### 15. No Unit Tests
There is no 	ests/ directory in the plugin. For a plugin this complex, the absence of automated tests is a significant risk.
### 16. No Privacy Provider (privacy/provider.php)
The plugin stores personal data but has no Moodle Privacy API implementation. This is **required** for GDPR compliance.
### 17. No Uninstall Script (db/uninstall.php)
There's no cleanup script to remove plugin data on uninstallation.
