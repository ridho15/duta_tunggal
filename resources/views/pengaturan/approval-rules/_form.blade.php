@php
    // Nilai desimal dari database ("10000000.00") ditampilkan sebagai angka bulat agar topeng rupiah membacanya benar.
    $num = fn ($v) => ($v === null || $v === '') ? '' : (string) (float) $v;
    $inputClass = 'w-full px-4 py-2.5 text-xs sm:text-sm border rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] outline-none transition';
    $selectedRoles = (array) old('roles', $rule->roles ?? []);
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
    <div>
        <label for="document_type" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Dokumen <span class="text-rose-500">*</span></label>
        <select id="document_type" name="document_type" required class="{{ $inputClass }} @error('document_type') border-rose-300 bg-rose-50/30 @else border-gray-200 @enderror">
            @foreach (\App\Models\ApprovalRule::TYPES as $value => $text)
                <option value="{{ $value }}" @selected(old('document_type', $rule->document_type) === $value)>{{ $text }}</option>
            @endforeach
        </select>
        @error('document_type') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="label" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Nama aturan <span class="text-rose-500">*</span></label>
        <input id="label" type="text" name="label" value="{{ old('label', $rule->label) }}" required maxlength="120"
               placeholder="Contoh: Sales Order di atas Rp 50.000.000"
               class="{{ $inputClass }} @error('label') border-rose-300 bg-rose-50/30 @else border-gray-200 @enderror">
        @error('label') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="above_amount" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Berlaku bila nilai di atas</label>
        <input id="above_amount" type="text" inputmode="numeric" data-currency name="above_amount" value="{{ old('above_amount', $num($rule->above_amount)) }}"
               placeholder="Kosong berarti dari Rp 0"
               class="{{ $inputClass }} @error('above_amount') border-rose-300 bg-rose-50/30 @else border-gray-200 @enderror">
        @error('above_amount') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="up_to_amount" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Dan sampai</label>
        <input id="up_to_amount" type="text" inputmode="numeric" data-currency name="up_to_amount" value="{{ old('up_to_amount', $num($rule->up_to_amount)) }}"
               placeholder="Kosong berarti tanpa batas atas"
               class="{{ $inputClass }} @error('up_to_amount') border-rose-300 bg-rose-50/30 @else border-gray-200 @enderror">
        @error('up_to_amount') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
    </div>
</div>

<fieldset>
    <legend class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Peran yang boleh menyetujui <span class="text-rose-500">*</span></legend>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 p-3 rounded-xl border @error('roles') border-rose-300 bg-rose-50/30 @else border-gray-200 @enderror">
        @foreach ($roles as $role)
            <label class="flex items-center gap-2 text-xs sm:text-sm text-gray-700 py-1 cursor-pointer">
                <input type="checkbox" name="roles[]" value="{{ $role }}" @checked(in_array($role, $selectedRoles, true))
                       class="w-4 h-4 rounded border-gray-300 text-[#15803d] focus:ring-[#15803d]">
                <span>{{ $role }}</span>
            </label>
        @endforeach
    </div>
    @error('roles') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
</fieldset>

<div>
    <label for="approver_label" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Teks penyetuju (tampil di pesan penolakan) <span class="text-rose-500">*</span></label>
    <input id="approver_label" type="text" name="approver_label" value="{{ old('approver_label', $rule->approver_label) }}" required maxlength="255"
           placeholder="Contoh: Sales Manager / Direktur"
           class="{{ $inputClass }} @error('approver_label') border-rose-300 bg-rose-50/30 @else border-gray-200 @enderror">
    @error('approver_label') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
</div>

<div>
    <label for="notes" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Catatan</label>
    <textarea id="notes" name="notes" rows="2" class="{{ $inputClass }} border-gray-200">{{ old('notes', $rule->notes) }}</textarea>
</div>

<label class="inline-flex items-center gap-2 text-xs sm:text-sm font-semibold text-gray-700 cursor-pointer">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $rule->is_active))
           class="w-4 h-4 rounded border-gray-300 text-[#15803d] focus:ring-[#15803d]">
    Aturan aktif
</label>
