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
define(['core/str'], function(Str) {
    var lightbox = null;
    var currentGallery = [];
    var currentIndex = 0;
    var stringsCache = {
        download: 'Download',
        close: 'Close',
        previous: 'Previous',
        next: 'Next',
        screenshot: 'Screenshot'
    };

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

    var downloadImage = function() {
        if (!lightbox || currentGallery.length === 0) {
            return;
        }
        var item = currentGallery[currentIndex];
        var link = document.createElement('a');
        link.href = item.src;
        link.download = item.alt || stringsCache.screenshot;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
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
                Str.get_strings([
                    {key: 'download', component: 'core'},
                    {key: 'closebuttontitle', component: 'core'},
                    {key: 'previous', component: 'core'},
                    {key: 'next', component: 'core'},
                    {key: 'screenshot:attachment', component: 'local_quicknote'}
                ]).done(function(strings) {
                    stringsCache.download = strings[0];
                    stringsCache.close = strings[1];
                    stringsCache.previous = strings[2];
                    stringsCache.next = strings[3];
                    stringsCache.screenshot = strings[4];

                    lightbox = document.createElement('div');
                    lightbox.className = 'local-quicknote__lightbox';
                    lightbox.innerHTML = '<div class="local-quicknote__lightbox-content">' +
                        '<div class="local-quicknote__lightbox-actions">' +
                            '<button type="button" class="local-quicknote__lightbox-download" aria-label="' +
                                stringsCache.download + '" title="' + stringsCache.download + '">' +
                                '<i class="fa fa-download" aria-hidden="true"></i>' +
                            '</button>' +
                            '<button type="button" class="local-quicknote__lightbox-close" aria-label="' +
                                stringsCache.close + '" title="' + stringsCache.close + '">' +
                                '<i class="fa-solid fa-xmark" aria-hidden="true"></i>' +
                            '</button>' +
                        '</div>' +
                        '<button type="button" class="local-quicknote__lightbox-prev" aria-label="' +
                            stringsCache.previous + '">' +
                            '<i class="fa fa-chevron-left" aria-hidden="true"></i>' +
                        '</button>' +
                        '<button type="button" class="local-quicknote__lightbox-next" aria-label="' +
                            stringsCache.next + '">' +
                            '<i class="fa fa-chevron-right" aria-hidden="true"></i>' +
                        '</button>' +
                        '<img class="local-quicknote__lightbox-img" src="" alt="">' +
                        '</div>';
                    document.body.appendChild(lightbox);

                    lightbox.addEventListener('click', function(e) {
                        if (e.target.closest('.local-quicknote__lightbox-close')) {
                            closeLightbox();
                        } else if (e.target.closest('.local-quicknote__lightbox-download')) {
                            downloadImage();
                        } else if (e.target.closest('.local-quicknote__lightbox-prev')) {
                            navigate(-1);
                        } else if (e.target.closest('.local-quicknote__lightbox-next')) {
                            navigate(1);
                        } else if (e.target === lightbox) {
                            closeLightbox();
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

                    updateImage();
                    void lightbox.offsetWidth; // Force reflow
                    lightbox.classList.add('is-open');
                }).fail(function() {
                    // Fallback to default strings if Moodle Str fails
                });
            } else {
                updateImage();
                void lightbox.offsetWidth; // Force reflow
                lightbox.classList.add('is-open');
            }
        }
    };
});
