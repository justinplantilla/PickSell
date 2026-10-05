document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-return-attachment-open]');
    if (trigger) {
        const dialog = document.getElementById(trigger.dataset.galleryId);
        if (!dialog) return;

        const image = dialog.querySelector('[data-return-lightbox-image]');
        const video = dialog.querySelector('[data-return-lightbox-video]');
        if (trigger.dataset.mediaType === 'video') {
            image.hidden = true;
            video.src = trigger.dataset.mediaUrl;
            video.hidden = false;
        } else {
            video.pause();
            video.removeAttribute('src');
            video.hidden = true;
            image.src = trigger.dataset.mediaUrl;
            image.hidden = false;
        }

        dialog.showModal();
        return;
    }

    const closeButton = event.target.closest('[data-return-lightbox-close]');
    if (closeButton) {
        closeButton.closest('dialog')?.close();
        return;
    }

    if (event.target.matches('.return-attachment-lightbox[open]')) {
        event.target.close();
    }
});

document.querySelectorAll('.return-attachment-lightbox').forEach(dialog => {
    dialog.addEventListener('close', () => {
        const video = dialog.querySelector('[data-return-lightbox-video]');
        video.pause();
        video.removeAttribute('src');
        video.hidden = true;

        const image = dialog.querySelector('[data-return-lightbox-image]');
        image.removeAttribute('src');
        image.hidden = true;
    });
});