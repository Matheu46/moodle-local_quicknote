# Moodle plugin: local_quicknote

## 0.10.0 (2026-08-30)
*Major contributions and features in this release were ported from a downstream fork by Andreas Giesen (@108design).*

- **Permissions & Security**:
  - Implemented the `local/quicknote:use` system capability for granular access control, replacing hardcoded enrollment checks.
- **UI/UX & Accessibility**:
  - Implemented SPA-like asynchronous live search with debounce in the Notes Center for a faster, smoother experience.
  - Added auto-growing textareas in the sidebar that dynamically adjust their height based on content.
  - Redesigned Notes Center cards by replacing title badges with Bootstrap card headers and footers for clearer visual separation.
  - Enabled direct note deletion from within the Notes Center.
  - Significantly improved screen reader accessibility (A11y) with reliable `aria-live` announcements for live search results and native CSS visibility handling for the sidebar drawer.
- **Core & Architecture**:
  - Extracted AJAX operations into a dedicated `repository.js` pattern for improved JS maintainability.
  - Refactored PDF and Markdown export functionality into a dedicated `exporter` class with improved memory efficiency using `get_recordset_sql`.
  - Added comprehensive unit tests for the new exporter class.

## 0.9.2 (2026-08-15)
- **UI/UX**:
  - Implemented a "clear search" button in the search input (Sidebar and Notes Center).

## 0.9.1 (2026-08-11)
- **Core & Architecture**:
  - Refactored course settings storage to use a dedicated database table for improved data management.
  - Implemented access checks in external API methods (`get_notes`, `save_note`, `delete_note`) to ensure QuickNote is not only disable in the UI.
  - Removed the default value for the `url` field in `install.xml` to enforce strict required input.
  - Improved data cleanup by removing associated notes of non-enrolled users.
- **Bug Fixes**:
  - Fixed class existence check for the `course_updated` event to use the correct namespace.
- **Testing & CI**:
  - Added unit tests for external library API, privacy provider, and disabled course scenarios.
  - Expanded CI matrix to support PHP 8.1/8.3, updated PostgreSQL and MariaDB versions, and added new Moodle branch targets.
- **Documentation**:
  - Updated README to clarify data access, UI visibility, and provide backup/restore information.

## 0.9.0 (2026-08-05)
- **Security & Vulnerability Fixes**:
  - Fixed HTML escaping in the sidebar to prevent Cross-Site Scripting (XSS) vulnerabilities.
  - Added strict origin checks for cross-window iframe highlight messages.
  - Sanitized URLs and fixed HTML output during PDF and Markdown note exports.
  - Limited the maximum length of note content and quotes to prevent excessive data storage (DoS prevention).
  - Enhanced URL handling across notes functionality.
- **Modernization & Compatibility**:
  - Dropped support for Moodle 4.1 and older. The plugin now requires Moodle 4.2+.
  - Refactored external API classes to use the modern `core_external` namespace structure.
  - Added compatibility checks for Moodle 4.4+ Hooks API in the `course_updated` event handler.
- **Stability & Cleanup**:
  - Added `course_deleted` event handler to properly clean up orphaned notes when a course is removed.
  - Ensured complete data cleanup upon plugin uninstallation.
  - Improved error handling during note deletion.
- **UI/UX**:
  - Replaced the sidebar toggle icon (the previous one clashed with Moodle's native edit action icon).

## 0.8.4 (2026-07-29)
- **Refactoring & Performance**:
  - Removed jQuery dependency from `view.js` and `notes.js` modules, replacing it with native standard JavaScript methods.
  - Extracted click handlers for note actions (toggle, close, add, copy, delete, quote) into separate functions for improved maintainability.
- **UI/UX & Styling Sidebar**:
  - Integrated the `core/user_date` module for proper localized date formatting of note timestamps.
  - Added new CSS styles for note actions to enhance layout and spacing.
  - Improved note status updates to effectively reflect the latest modifications.
- **Accessibility Improvements (A11y)**:
  - Fix the search input label to use the correct one.

## 0.8.3 (2026-07-13)
- **Backward Compatibility (Moodle < 4.4)**:
  - Re-introduced the legacy callback `before_standard_top_of_body_html` in `lib.php` to render the QuickNote toggle/sidebar on older Moodle versions that do not support the Hooks API.
  - Replaced `cloneNode` with `document.importNode` in `notes.js` when cloning template content, preventing a jQuery 3.6.x TypeError (`Cannot read properties of null (reading 'contains')`) on older Moodle versions.

## 0.8.2 (2026-07-07)
- **Style Fix**: Updated primary color CSS variables to use `--bs-primary` with a fallback to `--primary` for compatibility with Moodle 5.x, where `--primary` is no longer supported.

## 0.8.1 (2026-07-01)
- **Accessibility Improvements (A11y)**: 
  - Added support to close the sidebar using the `Esc` key.
  - Implemented logical focus management in the sidebar (focus moves into the panel when opened, and returns to the toggle button or action elements when closed/deleted).
  - Fixed an issue in the Notes Center where the course filter dropdown would automatically submit for keyboard users navigating with arrow keys.
- **UX & Architecture Enhancements**: 
  - The course filter dropdown in the Notes Center now lists only courses where the user has created at least one note, rather than all enrolled courses.

## 0.8.0 (2026-06-28)
- **Copy Note to Clipboard**: Added a copy button inside the note's textarea in the sidebar, visible only when the note has content.
- **Visual Feedback**: Implemented visual feedback that changes the copy icon to a checkmark and colors it green for 2 seconds when clicked.

## 0.7.0 (2026-06-21)
- **Mobile Support**: Implemented visualization and management of notes in the official Moodle mobile app.
- **Export to Markdown**: Added a new option in the Notes Center to export notes to `.md` format with proper text block formatting and decoded HTML entities.
- **UI Improvements**: 
  - Preserved whitespace and line breaks in both the quoted text and the note content within the Notes Center.
  - Added a "View in text" link to notes even when they do not have a specific text quote attached.

## 0.6.0 (2026-06-15)
- **Pagination**: Implemented pagination in the Notes Center to limit the number of notes fetched and displayed at once.
- **Admin Configuration**: Added a new global setting (`perpage`) to customize the number of notes displayed per page (options: 12, 24, 48, or no pagination).
- **Sorting**: Changed the default sorting in the Notes Center to order strictly by the last modified date (newest first).

## 0.5.0 (2026-06-07)
- **New Feature**: Added an administration setting allowing administrators to choose whether the QuickNote toggle icon and sidebar is displayed on the left or right side of the screen.
- **UI/UX**: Adjusted the `z-index` logic to dynamically increase only when the sidebar is open, preventing it from being hidden behind its own sidebar while still remaining below the Moodle message drawer when closed.

## 0.4.0 (2026-06-04)
- Replaced some hardcoded CSS colors with Moodle's native Bootstrap variables. The QuickNote toggle button now have the same color of your Moodle theme's primary color.
- Lowered the `z-index` of the QuickNote toggle button to 120, ensuring it remains below the Moodle message drawer (z-index 121) to prevent interaction conflicts.
- **Fix H5P**: Resolved the issue where the QuickNote toggle button was incorrectly rendered inside `mod_h5pactivity` iframes, preventing duplicate icons.
- Enabled QuickNote highlight capture support for native Moodle H5P activities (`mod_h5pactivity`) through cross-window communication.

## 0.3.1 (2026-05-30)
- Fix backup and restore.
- Restrict quicknote rendering to course and module contexts.

## 0.3.0 (2026-05-28)
- **Core Improvements**:
    - Implement Notes Center page (`view.php`).
    - Added **PDF Export** functionality (`view.php?export=pdf`).
    - **Search & Filters**:
        - **Global Search**: Search across all notes from all courses.
        - **Course Filter**: Filter notes by specific course.
        - **Empty State**: Clean UI when no notes are found.
- **UI/UX**:
    - Animated toggle icon transition in the quicknote sidebar.

## 0.2.0 (2026-05-25)
*Contributions by @mattgig:*

- Added backup functionality.
- Added administration settings to exclude specific pages (e.g., mod-quiz-*).
- Added teacher-level settings for Quicknotes visibility per page.
- Added automatic deletion of quicknotes upon user unenrollment.
- Fixed a bug breaking message functionality in Moodle 5.0.

*Maintainer updates:*

- Hide search bar in sidebar when the user has no quicknotes.

## 0.1.1 (2026-05-22)
- Fixed a dmlreadexception caused by a mismatch between the database table name in install.xml and the classes.

## 0.1.0 (2026-05-19)
- Initial release.
