<x-filament-panels::page>
    <div class="dt-hp" style="max-width:860px">
        @forelse ($groups as $group => $videos)
            <div style="font-size:12px;font-weight:700;color:#6B7280;letter-spacing:.6px;text-transform:uppercase">{{ $group }}</div>
            @foreach ($videos as $video)
                <div class="dt-hp-card" wire:key="video-{{ $video->id }}">
                    <div class="dt-hp-row">
                        <span class="dt-hp-name">{{ $video->title }}</span>
                        @if ($video->duration_minutes)
                            <span class="dt-hp-code">{{ $video->duration_minutes }} menit</span>
                        @endif
                    </div>
                    @if ($video->description)
                        <div class="dt-hp-sub">{{ $video->description }}</div>
                    @endif
                    @if ($embed = $video->youtubeEmbedUrl())
                        <div style="position:relative;padding-top:56.25%;border-radius:12px;overflow:hidden;background:#111827">
                            <iframe src="{{ $embed }}" title="{{ $video->title }}" loading="lazy" allowfullscreen
                                    allow="accelerometer; encrypted-media; gyroscope; picture-in-picture"
                                    style="position:absolute;inset:0;width:100%;height:100%;border:0"></iframe>
                        </div>
                    @elseif ($video->isVideoFile())
                        <video src="{{ $video->url }}" controls preload="metadata" style="width:100%;border-radius:12px;background:#111827"></video>
                    @else
                        <a href="{{ $video->url }}" target="_blank" rel="noopener" class="dt-hp-link">Buka video ›</a>
                    @endif
                </div>
            @endforeach
        @empty
            <div class="dt-hp-empty">
                Video tutorial untuk peran Anda belum tersedia.
                @if ($manageUrl)
                    <div style="margin-top:8px"><a href="{{ $manageUrl }}" class="dt-hp-link">+ Tambah video tutorial</a></div>
                @endif
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
