# Moodle plugin: local_quicknote

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
