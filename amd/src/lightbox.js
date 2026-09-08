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
    var currentGallery = [];
    var currentIndex = 0;

    var updateImage = function() {
        if (!lightbox || currentGallery.length === 0) {
            return;
        }
        var item = currentGallery[currentIndex];
        var img = lightbox.querySelector('img');
        img.src = item.src;
        img.alt = item.alt || '';

        var prevBtn = lightbox.querySelector('.local-quicknote__lightbox-prev');
        var nextBtn = lightbox.querySelector('.local-quicknote__lightbox-next');

        if (currentGallery.length > 1) {
            if (currentIndex > 0) {
                prevBtn.removeAttribute('hidden');
            } else {
                prevBtn.setAttribute('hidden', 'true');
            }
            if (currentIndex < currentGallery.length - 1) {
                nextBtn.removeAttribute('hidden');
            } else {
                nextBtn.setAttribute('hidden', 'true');
            }
        } else {
            prevBtn.setAttribute('hidden', 'true');
            nextBtn.setAttribute('hidden', 'true');
        }
    };

    var navigate = function(step) {
        if (currentGallery.length <= 1) {
            return;
        }
        var nextIndex = currentIndex + step;
        if (nextIndex >= 0 && nextIndex < currentGallery.length) {
            currentIndex = nextIndex;
            updateImage();
        }
    };

    var closeLightbox = function() {
        if (lightbox) {
            lightbox.classList.remove('is-open');
        }
    };

    return {
        show: function(gallery, startIndex) {
            if (!Array.isArray(gallery)) {
                gallery = [{src: gallery, alt: startIndex}];
                startIndex = 0;
            }

            currentGallery = gallery;
            currentIndex = startIndex || 0;

            if (!lightbox) {
                lightbox = document.createElement('div');
                lightbox.className = 'local-quicknote__lightbox';
                lightbox.innerHTML = '<div class="local-quicknote__lightbox-content">' +
                    '<button type="button" class="local-quicknote__lightbox-close" aria-label="Close">&times;</button>' +
                    '<button type="button" class="local-quicknote__lightbox-prev" aria-label="Previous">' +
                        '<i class="fa fa-chevron-left" aria-hidden="true"></i>' +
                    '</button>' +
                    '<button type="button" class="local-quicknote__lightbox-next" aria-label="Next">' +
                        '<i class="fa fa-chevron-right" aria-hidden="true"></i>' +
                    '</button>' +
                    '<img class="local-quicknote__lightbox-img" src="" alt="">' +
                    '</div>';
                document.body.appendChild(lightbox);

                lightbox.addEventListener('click', function(e) {
                    if (e.target === lightbox || e.target.closest('.local-quicknote__lightbox-close')) {
                        closeLightbox();
                    } else if (e.target.closest('.local-quicknote__lightbox-prev')) {
                        navigate(-1);
                    } else if (e.target.closest('.local-quicknote__lightbox-next')) {
                        navigate(1);
                    }
                });

                document.addEventListener('keydown', function(e) {
                    if (!lightbox.classList.contains('is-open')) {
                        return;
                    }
                    if (e.key === 'Escape') {
                        closeLightbox();
                    } else if (e.key === 'ArrowLeft') {
                        navigate(-1);
                    } else if (e.key === 'ArrowRight') {
                        navigate(1);
                    }
                });
            }

            updateImage();
            void lightbox.offsetWidth; // Force reflow
            lightbox.classList.add('is-open');
        }
    };
});
