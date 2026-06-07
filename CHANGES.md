# Moodle plugin: local_quicknote

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
