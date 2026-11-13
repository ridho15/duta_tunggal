<?php

namespace Tests\Feature\UI;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ConfirmationModalComponentTest extends TestCase
{
    public function test_renders_danger_variant_by_default(): void
    {
        $rendered = Blade::render('<x-confirmation-modal name="delete-item" title="Hapus Data">Apakah yakin?</x-confirmation-modal>');

        $this->assertStringContainsString('bg-red-100', $rendered);
        $this->assertStringContainsString('text-red-600', $rendered);
        $this->assertStringContainsString('bg-red-600 hover:bg-red-700', $rendered);
        $this->assertStringContainsString('x-on:open-modal-delete-item.window', $rendered);
        $this->assertStringContainsString('Hapus Data', $rendered);
    }

    public function test_renders_success_variant(): void
    {
        $rendered = Blade::render('<x-confirmation-modal name="approve-item" variant="success" title="Setujui">Yakin setuju?</x-confirmation-modal>');

        $this->assertStringContainsString('bg-[#dcfce7]', $rendered);
        $this->assertStringContainsString('text-[#15803d]', $rendered);
        $this->assertStringContainsString('bg-[#15803d] hover:bg-[#14532d]', $rendered);
        $this->assertStringContainsString('x-on:open-modal-approve-item.window', $rendered);
    }

    public function test_renders_warning_variant(): void
    {
        $rendered = Blade::render('<x-confirmation-modal name="recount-item" variant="warning" title="Hitung Ulang">Yakin recount?</x-confirmation-modal>');

        $this->assertStringContainsString('bg-amber-100', $rendered);
        $this->assertStringContainsString('text-amber-600', $rendered);
        $this->assertStringContainsString('bg-amber-600 hover:bg-amber-700', $rendered);
    }

    public function test_renders_info_variant(): void
    {
        $rendered = Blade::render('<x-confirmation-modal name="start-item" variant="info" title="Mulai Pengiriman">Mulai armada?</x-confirmation-modal>');

        $this->assertStringContainsString('bg-blue-100', $rendered);
        $this->assertStringContainsString('text-blue-600', $rendered);
        $this->assertStringContainsString('bg-blue-600 hover:bg-blue-700', $rendered);
    }

    public function test_supports_type_attribute_as_fallback_alias(): void
    {
        $rendered = Blade::render('<x-confirmation-modal name="compat-item" type="warning" title="Peringatan">Alias type test</x-confirmation-modal>');

        $this->assertStringContainsString('bg-amber-100', $rendered);
        $this->assertStringContainsString('text-amber-600', $rendered);
        $this->assertStringContainsString('bg-amber-600 hover:bg-amber-700', $rendered);
    }

    public function test_modal_without_form_when_has_form_is_false(): void
    {
        $rendered = Blade::render('
            <x-confirmation-modal name="custom-modal" :has-form="false" title="No Form">
                Konten modal tanpa form
                <x-slot:footer>
                    <button type="button" id="custom-btn">Custom Button</button>
                </x-slot:footer>
            </x-confirmation-modal>
        ');

        $this->assertStringNotContainsString('<form', $rendered);
        $this->assertStringContainsString('id="custom-btn"', $rendered);
    }
}

