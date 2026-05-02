# Quick Note #

Designed for the native Boost experience in Moodle 4.4 and 4.5+, QuickNote helps students capture important excerpts while reading course materials, connect those excerpts to personal reflections, and return to the exact place where learning happened. For teachers and administrators, it provides simple controls to decide when and where the tool is available.

Instead of forcing learners to copy text into external apps, QuickNote keeps the study workflow inside Moodle. Students can highlight a passage, save it instantly as a quote, add their own interpretation, and revisit the original context later through browser text-fragment navigation. The result is a cleaner, more focused note-taking experience that supports active reading, revision, and deeper engagement with course content.

## ✨ Features

- **Highlight to Note**: Students can select text anywhere inside a course page and use a floating action button to save the selection instantly as a quote.
- **Scroll to Text Fragments**: Quote references use browser text fragments (`#:~:text=`), allowing QuickNote to return users to the exact original passage and highlight it visually.
- **Quote and Reflection Separation**: The interface clearly separates the quoted course text from the student's own annotation or reflection.
- **Native Sidebar Drawer**: Notes are managed inside a right-hand sidebar drawer integrated with the Boost user experience, accessible from a floating action button or navigation entry point.
- **Auto-save**: Notes and reflections are saved automatically in the background via Moodle AJAX services, reducing the risk of lost work.
- **Search and Management**: Students can filter notes in real time and delete notes they no longer need.
- **Course-Level Control**: Teachers can enable or disable QuickNote for individual courses.
- **Default Site Policy**: Administrators can define whether the feature should be enabled or disabled by default for newly configured courses.
- **Student-Centered Study Workflow**: Supports close reading, reflective writing, and quick review without leaving Moodle.

## ✅ Prerequisites

- Moodle `4.4+`
- Boost theme
- Boost child themes are also supported

QuickNote is designed natively for the Moodle Boost interface. Compatibility with non-Boost themes is not the primary target.

## ⚙️ Configuration

QuickNote includes controls for both site administrators and course teachers.

### Administrator settings

Administrators can define the default behavior for new courses:

1. Go to `Site administration > Plugins > Local plugins > QuickNote`.
2. Configure the default state for the plugin:
   `Enabled` or `Disabled` for courses that have not yet been individually configured.

This allows institutions to decide whether QuickNote should be available broadly by default or enabled selectively.

### Teacher course settings

Teachers can control QuickNote per course:

1. Open the target course.
2. Go to the course settings form.
3. Find the `Quick Notes` section.
4. Enable or disable QuickNote for that course.

This makes it easy to align the tool with the pedagogical design of each course.

## 📦 Installation

QuickNote can be installed like any standard Moodle local plugin.

### Option 1: Install from ZIP

1. Download the plugin ZIP package.
2. Log in to Moodle as an administrator.
3. Go to `Site administration > Plugins > Install plugins`.
4. Upload the ZIP file.
5. Follow the Moodle installation steps.
6. Complete the upgrade process when prompted.

### Option 2: Install from Git

Clone or copy the plugin into your Moodle local plugins directory:

```bash
git clone https://github.com/Matheu46/moodle-local_quicknote.git /path/to/moodle/local/quicknote
```

Then complete the Moodle upgrade:

1. Log in as an administrator.
2. Go to `Site administration > Notifications`.

Or run the CLI upgrade:

```bash
php admin/cli/upgrade.php
```

## 🧭 Usage

QuickNote is designed to be simple for students from the first interaction.

1. Open any supported course page.
2. Select a meaningful excerpt from the learning material.
3. Click the floating button that appears near the selection.
4. QuickNote saves the selected passage as a quote and opens the sidebar drawer.
5. Add a personal reflection, interpretation, summary, or study note in the annotation field.
6. Continue reading while notes are saved automatically in the background.
7. Use the search field later to find notes quickly.
8. Click `View in text` to return to the original page location and highlight the saved passage.

## 🧩 Plugin Details

- **Plugin name**: QuickNote
- **Component**: `local_quicknote`
- **Plugin type**: Local plugin
- **Primary interface target**: Moodle Boost right-hand drawer workflow

## 🐛 Bug Reports & Support

If you find a bug or have a feature request, please open an issue on our tracker:
[(https://github.com/Matheu46/moodle-local_quicknote/issues)](#)

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
