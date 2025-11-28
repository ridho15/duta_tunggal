<div class="flex flex-col space-y-1">
    @if($getState()['total_materials'] > 0)
        <div class="text-xs">
            <span class="font-medium">{{ $getState()['fully_issued'] }}/{{ $getState()['total_materials'] }}</span>
            <span class="text-gray-500">diambil</span>
        </div>
        <div class="flex space-x-1">
            @if($getState()['fully_issued'] == $getState()['total_materials'])
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    100%
                </span>
            @elseif($getState()['fully_issued'] > 0)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                    Sebagian
                </span>
            @else
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                    Belum
                </span>
            @endif
        </div>
    @else
        <span class="text-xs text-gray-500">Tidak ada bahan</span>
    @endif
</div>