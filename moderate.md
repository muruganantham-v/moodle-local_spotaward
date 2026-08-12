### 18. Massive Inline JavaScript — ~800+ Lines in lib.php
**File**: lib.php
The local_spotaward_nomination_form_js() function generates 755 lines of JavaScript via a PHP heredoc. This should be a proper AMD module in md/src/.

### 19. Success Overlay JavaScript — 96 Lines of Inline JS
**File**: lib.php
Another massive inline JS block containing animation logic, DOM creation, and event handling.

### 20. Checkbox Select-All JS in index.php — Inline Instead of AMD
**File**: index.php
Admin dashboard select-all/download JS is inline instead of using an AMD module.

### 21. Non-Standard File: compare_plugin.html and compare_template.html
**Files**: compare_plugin.html, compare_template.html
These HTML files don't appear to be part of the plugin's runtime. They seem like development comparison/diff files and should be removed from distribution.

### 22. Database Field Type Mismatch — wardcategory
**File**: db/install.xml
The parent table uses TEXT (unlimited) while the child uses CHAR(255). The upgrade step explicitly changes this field to TEXT, but the install.xml should match what the upgrade produces for new installs.

### 23. $plugin->maturity = MATURITY_BETA — Not Production Ready
**File**: ersion.php
The plugin self-declares as BETA maturity. This should be updated to MATURITY_STABLE before production deployment.

### 24. Missing manager_role Setting Usage — Capability Gap
**File**: db/access.php
The 'local/spotaward:nominate' capability has **empty archetypes**. This means no role gets this capability by default. Users need manual capability assignment.

### 25. Mixed Line Endings (CRLF / LF)
Most files show mixed \r\n and \n line endings. This suggests different editors/OSes were used without .editorconfig enforcement.
