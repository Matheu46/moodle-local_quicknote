# QuickNote #

Designed for the native Boost experience in Moodle **4.5 LTS** and **5.0+**, QuickNote helps students capture important excerpts while reading course materials, connect those excerpts to personal reflections, and return to the exact place where learning happened. For teachers and administrators, it provides granular controls, privacy-first usage analytics, and flexible data retention policies.

Instead of forcing learners to copy text into external apps, QuickNote keeps the study workflow inside Moodle. Students can highlight a passage, save it instantly as a quote, dictate or write their own interpretation, attach screenshots, and revisit the original context later through browser text-fragment navigation. The result is a cleaner, more focused note-taking experience that supports active reading, revision, and deeper engagement with course content.

<div align="center">
<a href="https://ateeducacion.github.io/moodle-playground/?blueprint-url=https://gist.githubusercontent.com/Matheu46/ab4a85fb82d52fee6c1b40c21203d5c5/raw/blueprint.json" target="_blank" rel="noopener"><img src=".github/assets/playground-preview-button.svg" alt="Preview in Moodle Playground" width="200"></a>
</div>
<img width="1155" height="949" alt="demo-quicknote" src="https://github.com/user-attachments/assets/07d02bb9-0a81-49fe-837e-10d1b4672b72" />

## ✨ Features

- **Highlight to Note & Text Fragments**: Select text anywhere inside a course page (including same-origin H5P iframes) to save it as a quote and return later to the exact passage via browser text-fragment navigation (`#:~:text=`).
- **Native Boost Drawer & Navbar Integration**: Manage notes inside a minimalist, distraction-free drawer (right or left), triggered via a floating action button, a top navigation bar toggle, or both, with a global keyboard shortcut (`Alt + Q`) and responsive mobile backdrop.
- **Voice Dictation (Speech-to-Text)**: Dictate notes hands-free using the browser's Web Speech API, complete with recording indicators and accessible `aria-live` status announcements.
- **Screenshot Attachments & Lightbox Gallery**: Paste screenshots directly from the clipboard (`Ctrl+V` / `Cmd+V`) with client-side compression, an accessible lightbox gallery, per-note limits, and per-user storage quotas.
- **General Notes (Dashboard & Site Home)**: Optional personal notebook on the Dashboard (`/my/`) and Site home so learners can take notes outside courses.
- **Notes Center (`view.php`) & Bulk Actions**: Full-page dashboard with global search, course filtering, pagination, PDF and Markdown export, and multi-select bulk actions (including `Shift + Click` range selection for bulk delete and export).
- **Teacher Usage Report & Report Builder**:
  - Built-in anonymous course usage report (`QuickNote usage`) displaying aggregated note counts and unique learners per page, automatically resolving activity titles, forum discussions, book chapters, lesson pages, and wiki pages.
  - Optional Moodle Report Builder datasource for institutional engagement analytics. **Strictly privacy-first**: private note contents and quoted text are never exposed in reports.
- **Granular Cached Controls & Assessment Integrity**: MUC-cached course and per-activity settings (Default / Enabled / Disabled), automatic exclusion from secure exam window layouts (`secure`) and configurable page type patterns (`mod-quiz-attempt` by default).
- **Data Retention Policies**: Choose whether notes are removed or preserved when a course is deleted (migrated to General notes with broken links cleared) or when a user is unenrolled.
- **Auto-save, Privacy API, RTL & Accessibility**: Automatic AJAX saving with lazy loading, full GDPR Privacy API support, Backup & Restore of settings (excluding student notes), full RTL layout support, and keyboard accessibility.

## ✅ Prerequisites

- **Moodle `5.0+`** (`main` branch — Moodle 5.0 to 5.3)
- **Moodle `4.5 LTS`** (`MOODLE_405_STABLE` branch)
- Boost theme (or Boost child themes)

QuickNote is designed natively for the Moodle Boost interface. Compatibility with non-Boost themes is not the primary target.

## ⚙️ Configuration

QuickNote includes controls for both site administrators and course teachers.

### Administrator settings

Go to `Site administration > Plugins > Local plugins > QuickNote` to configure site-wide policies:

- **Availability & Scope**:
  - **Enabled by default**: Defines whether the QuickNote drawer is enabled by default in courses that have not yet been manually configured.
  - **Enable on Dashboard** / **Enable on Site home**: Display the QuickNote drawer as a personal notebook on `/my/` and the site home page.
  - **Position**: Choose where the QuickNote toggle and drawer appear (`Right`, `Left`, `Top navigation bar only`, or `Both`).
  - **Disabled page types**: Line-separated page type patterns where QuickNote is disabled by default using `*` wildcards (defaults to `mod-quiz-attempt`). Teachers can override this per activity.
- **Voice & Screenshots**:
  - **Enable voice dictation**: Globally enable or disable browser speech-to-text dictation (enabled by default).
  - **Enable screenshots**: Globally enable or disable clipboard screenshot attachments (disabled by default).
  - **Maximum screenshot size**: Maximum file size per screenshot (defaults to `2 MB`).
  - **Maximum screenshots per note**: Maximum images per note (defaults to `3`).
  - **User storage quota**: Total screenshot storage quota per user across all courses (`10 MB` to `200 MB`, or `Unlimited`; defaults to `20 MB`).
- **Notes Center & Reporting**:
  - **Notes per page**: Pagination size in the Notes center (`12`, `24`, `48`, or `All`; defaults to `12`).
  - **Enable Report Builder datasource**: Controls whether site administrators and managers can build custom reports using the QuickNote datasource in Moodle Report Builder (enabled by default).
- **Data Retention**:
  - **Preserve notes when course is deleted**: When enabled, notes from a deleted course are migrated to General notes (with course links cleared) instead of being deleted.
  - **Preserve notes when unenrolled**: When enabled, users keep access to their personal notes in the Notes center after unenrolment.

### Teacher course & activity settings

Teachers can control QuickNote per course and per activity:

1. **Course level**: Open the course settings form, expand the `QuickNote` section, and enable or disable QuickNote for that course.
2. **Activity level**: Open any activity's settings form, locate `QuickNote on this activity`, and choose `Default`, `Enabled`, or `Disabled`.
3. **Course usage report**: Navigate to `Reports > QuickNote usage` inside the course to view anonymous, aggregated engagement metrics per page.

### Advanced Permissions (Capabilities)

QuickNote integrates natively with Moodle's Role and Permission system through three capabilities:

- **`local/quicknote:use` (`CONTEXT_SYSTEM`)**: Allows authenticated users (`user` archetype) to create and manage notes on the Dashboard, Site home, and enrolled courses. Anonymous guest accounts (`isguestuser()`) are strictly blocked across all web services, page views, and file downloads.
- **`local/quicknote:uploadscreenshot` (`CONTEXT_SYSTEM`)**: Governs who can paste and attach screenshots to notes. Administrators can revoke this capability for specific roles while still allowing text notes.
- **`local/quicknote:viewreport` (`CONTEXT_COURSE`)**: Allows Teachers, Editing Teachers, and Managers to view the anonymous course usage report (`Reports > QuickNote usage`).
- **Restricting Roles in Specific Courses (e.g., Students Only)**: Because authenticated users have `local/quicknote:use` at the system level for personal Dashboard notes, if you want a course where **only students can use QuickNote**, go to the course's **Participants > Permissions**, locate `local/quicknote:use`, and set the excluded role (e.g., *Teacher*) to **Prohibit** (`CAP_PROHIBIT`). This blocks QuickNote inside that course while preserving Dashboard access.
- **Individual Student Restrictions**: Create a custom role with `local/quicknote:use` set to **Prohibit** and assign it globally or within a single course to revoke access for a specific user.

> [!IMPORTANT]
> **Data Access vs UI Visibility**: Disabling QuickNote at the course level blocks creating or editing notes in that course (while still allowing owners to view and delete their own existing notes from the Notes center). Per-activity overrides control UI visibility on individual activity pages, and secure exam layouts (`secure`) and quiz attempts (`mod-quiz-attempt`) are excluded automatically.

### Backup and Restore

When backing up a course, QuickNote preserves your course and activity-level settings. **Student notes and reflections are intentionally excluded** from course backups to protect student privacy and ensure personal data is not leaked when courses are restored or shared.

## 📦 Installation

QuickNote can be installed like any standard Moodle local plugin.

### Option 1: Install from ZIP

1. Download the plugin ZIP package matching your Moodle version (`main` for Moodle 5.0+, `MOODLE_405_STABLE` for Moodle 4.5 LTS).
2. Log in to Moodle as an administrator.
3. Go to `Site administration > Plugins > Install plugins`.
4. Upload the ZIP file.
5. Follow the Moodle installation steps and complete the upgrade process when prompted.

### Option 2: Install from Git

Clone or copy the plugin into your Moodle local plugins directory:

```bash
# For Moodle 5.0+ (public/local/quicknote on Moodle 5.1+)
git clone https://github.com/Matheu46/moodle-local_quicknote.git /path/to/moodle/local/quicknote

# For Moodle 4.5 LTS
git clone -b MOODLE_405_STABLE https://github.com/Matheu46/moodle-local_quicknote.git /path/to/moodle/local/quicknote
```

Then complete the Moodle upgrade:

1. Log in as an administrator and go to `Site administration > Notifications`, or run:

```bash
php admin/cli/upgrade.php
```

## 🧭 Usage

QuickNote is designed to be simple for students from the first interaction.

1. Open any supported course page (or press `Alt + Q` to open the drawer and start a note immediately).
2. Select a meaningful excerpt from the learning material and click the floating button that appears near the selection.
3. QuickNote saves the selected passage as a quote and opens the drawer.
4. Type or **dictate** (using the microphone button) a personal reflection, interpretation, or summary.
5. **Attach Screenshots**: Paste images directly into a note with `Ctrl+V` / `Cmd+V`. Click any thumbnail to view it in full screen via the built-in lightbox gallery or download it.
6. Continue reading while notes are saved automatically in the background.
7. Use the drawer search field or open the **Notes center** to filter by course, search globally, select multiple notes (`Shift + Click`) for bulk deletion, or export notes to PDF / Markdown.
8. Click `View in text` to return to the original page location and highlight the saved passage.

## 💡 Design Principles & Contributing

QuickNote was built with clear pedagogical and architectural priorities. If you are planning to contribute or propose new features, please keep these core principles in mind:

1. **Simplicity First, Especially in the Drawer:**
   - The primary goal of the drawer is frictionless active reading. It must remain minimalist, intuitive, and distraction-free.
   - Rich management workflows belong in the full-page **Notes center**, not inside the reading companion drawer.

2. **Performance Is a Feature:**
   - Any proposed feature that impacts page load times, DOM complexity, API payload size, database query efficiency, or browser memory usage must be thoroughly evaluated and benchmarked.

3. **Prudence with Interface Additions:**
   - Every additional button, toolbar, or setting inside the drawer competes for student attention.
   - New features that add UI complexity to the drawer or risk introducing performance overhead will require careful evaluation before acceptance.

## 🧩 Plugin Details

- **Plugin name**: QuickNote
- **Component**: `local_quicknote`
- **Plugin type**: Local plugin
- **Primary interface target**: Moodle Boost drawer workflow

## 🐛 Bug Reports & Support

If you find a bug or have a feature request, please open an issue on our tracker:
[https://github.com/Matheu46/moodle-local_quicknote/issues](https://github.com/Matheu46/moodle-local_quicknote/issues)

Pull requests are welcome!

## 📄 License ##

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program. If not, see <https://www.gnu.org/licenses/>.
