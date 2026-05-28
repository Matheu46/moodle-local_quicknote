# Moodle plugin: local_quicknote

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
