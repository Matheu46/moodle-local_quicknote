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

/**
 * @module      local_quicknote/lightbox
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    var lightbox = null;

    var closeLightbox = function() {
        if (lightbox) {
            lightbox.classList.remove('is-open');
        }
    };

    return {
        show: function(src, alt) {
            if (!lightbox) {
                lightbox = document.createElement('div');
                lightbox.className = 'local-quicknote__lightbox';
                lightbox.innerHTML = '<div class="local-quicknote__lightbox-content">' +
                    '<button type="button" class="local-quicknote__lightbox-close" aria-label="Close">&times;</button>' +
                    '<img class="local-quicknote__lightbox-img" src="" alt="">' +
                    '</div>';
                document.body.appendChild(lightbox);

                lightbox.addEventListener('click', function(e) {
                    if (e.target === lightbox || e.target.closest('.local-quicknote__lightbox-close')) {
                        closeLightbox();
                    }
                });
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && lightbox.classList.contains('is-open')) {
                        closeLightbox();
                    }
                });
            }
            var img = lightbox.querySelector('img');
            img.src = src;
            img.alt = alt || '';

            // Force reflow for CSS transition
            void lightbox.offsetWidth;
            lightbox.classList.add('is-open');
        }
    };
});
