<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_quicknote\local;

use context_system;
use invalid_parameter_exception;

/**
 * Private screenshot storage for QuickNote notes.
 *
 * @package     local_quicknote
 * @copyright   2026 Andreas Giesen
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class screenshot_manager {
    /** @var string File area for note screenshots. */
    public const FILEAREA = 'screenshot';

    /** @var int Maximum file size in bytes (5 MB). */
    public const MAX_BYTES = 5242880;

    /** @var int Maximum number of screenshots allowed per note. */
    public const MAX_FILES_PER_NOTE = 10;

    /** @var array<string, string> Supported MIME types and extensions. */
    private const TYPES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    /**
     * Store an image pasted into a note.
     *
     * @param \stdClass $note Owned note record.
     * @param string $filename Client-side filename.
     * @param string $mimetype Client-side MIME type.
     * @param string $base64 Base64 image payload.
     * @return array External screenshot representation.
     */
    public static function create(\stdClass $note, string $filename, string $mimetype, string $base64): array {
        $mimetype = strtolower(trim($mimetype));
        if (!isset(self::TYPES[$mimetype])) {
            throw new invalid_parameter_exception('Only PNG, JPEG, WebP and GIF screenshots are supported.');
        }

        $content = base64_decode($base64, true);
        if ($content === false || $content === '') {
            throw new invalid_parameter_exception('The screenshot data is invalid.');
        }
        if (strlen($content) > self::MAX_BYTES) {
            throw new invalid_parameter_exception('The screenshot exceeds the 5 MB limit.');
        }

        $imageinfo = @getimagesizefromstring($content);
        $detectedtype = is_array($imageinfo) ? strtolower((string) ($imageinfo['mime'] ?? '')) : '';
        if (!isset(self::TYPES[$detectedtype]) || $detectedtype !== $mimetype) {
            throw new invalid_parameter_exception('The uploaded data is not a supported image of the declared type.');
        }
        if (
            (int) $imageinfo[0] <= 0 || (int) $imageinfo[1] <= 0
                || ((int) $imageinfo[0] * (int) $imageinfo[1]) > 40000000
        ) {
            throw new invalid_parameter_exception('The screenshot dimensions are invalid or too large.');
        }

        $fs = get_file_storage();
        $contextid = context_system::instance()->id;
        $files = $fs->get_area_files($contextid, 'local_quicknote', self::FILEAREA, $note->id, 'id', false);
        if (count($files) >= self::MAX_FILES_PER_NOTE) {
            throw new invalid_parameter_exception('A note can contain at most 10 screenshots.');
        }

        $basename = clean_param(pathinfo($filename, PATHINFO_FILENAME), PARAM_FILE);
        if ($basename === '' || $basename === '.') {
            $basename = 'screenshot';
        }
        $storedname = $basename . '-' . gmdate('Ymd-His') . '-' . random_string(6) . '.' . self::TYPES[$mimetype];
        $file = $fs->create_file_from_string([
            'contextid' => $contextid,
            'component' => 'local_quicknote',
            'filearea' => self::FILEAREA,
            'itemid' => (int) $note->id,
            'filepath' => '/',
            'filename' => $storedname,
            'userid' => (int) $note->userid,
            'source' => $filename,
        ], $content);

        return self::export_file($file);
    }

    /**
     * List screenshots attached to a note.
     *
     * @param int $noteid Note id.
     * @return array
     */
    public static function get_for_note(int $noteid): array {
        $files = get_file_storage()->get_area_files(
            context_system::instance()->id,
            'local_quicknote',
            self::FILEAREA,
            $noteid,
            'timecreated ASC, id ASC',
            false
        );

        return array_map([self::class, 'export_file'], array_values($files));
    }

    /**
     * Delete one owned screenshot.
     *
     * @param int $fileid Stored file id.
     * @param int $noteid Owning note id.
     * @return bool
     */
    public static function delete_file(int $fileid, int $noteid): bool {
        $file = get_file_storage()->get_file_by_id($fileid);
        if (
            !$file || (int) $file->get_contextid() !== (int) context_system::instance()->id
                || $file->get_component() !== 'local_quicknote'
                || $file->get_filearea() !== self::FILEAREA
                || (int) $file->get_itemid() !== $noteid
                || $file->is_directory()
        ) {
            throw new invalid_parameter_exception('Screenshot not found.');
        }
        return $file->delete();
    }

    /**
     * Delete every screenshot attached to one note.
     *
     * @param int $noteid Note id.
     * @return void
     */
    public static function delete_for_note(int $noteid): void {
        get_file_storage()->delete_area_files(
            context_system::instance()->id,
            'local_quicknote',
            self::FILEAREA,
            $noteid
        );
    }

    /**
     * Convert a stored file into the AJAX structure.
     *
     * @param \stored_file $file Stored file instance.
     * @return array
     */
    public static function export_file(\stored_file $file): array {
        $url = \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename(),
            false
        );
        return [
            'id' => (int) $file->get_id(),
            'filename' => $file->get_filename(),
            'mimetype' => $file->get_mimetype(),
            'url' => $url->out(false),
        ];
    }

    /**
     * External API structure shared by note and upload responses.
     *
     * @return \core_external\external_single_structure
     */
    public static function external_structure(): \core_external\external_single_structure {
        return new \core_external\external_single_structure([
            'id' => new \core_external\external_value(PARAM_INT, 'Stored file id.'),
            'filename' => new \core_external\external_value(PARAM_FILE, 'Stored filename.'),
            'mimetype' => new \core_external\external_value(PARAM_RAW, 'Image MIME type.'),
            'url' => new \core_external\external_value(PARAM_URL, 'Private pluginfile URL.'),
        ]);
    }
}
