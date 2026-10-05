@props(['attachments' => [], 'galleryId'])

@if(count($attachments))
    <div class="return-attachment-gallery">
        @foreach($attachments as $attachment)
            @php
                $extension = strtolower(pathinfo($attachment, PATHINFO_EXTENSION));
                $isVideo = in_array($extension, ['mp4', 'webm'], true);
                $mediaUrl = Storage::disk('public')->url($attachment);
            @endphp
            <button type="button" class="return-attachment-thumb" data-return-attachment-open data-gallery-id="{{ $galleryId }}" data-media-url="{{ $mediaUrl }}" data-media-type="{{ $isVideo ? 'video' : 'image' }}" aria-label="Open {{ $isVideo ? 'video' : 'photo' }} attachment">
                @if($isVideo)
                    <span class="return-video-thumb" aria-hidden="true">▶</span>
                    <span class="return-attachment-kind">Video</span>
                @else
                    <img src="{{ $mediaUrl }}" alt="Return request photo attachment" loading="lazy">
                @endif
            </button>
        @endforeach
    </div>

    <dialog class="return-attachment-lightbox" id="{{ $galleryId }}" aria-label="Return request attachment">
        <button type="button" class="return-lightbox-close" data-return-lightbox-close aria-label="Close attachment">&times;</button>
        <img data-return-lightbox-image alt="Return request attachment" hidden>
        <video data-return-lightbox-video controls playsinline hidden></video>
    </dialog>
@endif