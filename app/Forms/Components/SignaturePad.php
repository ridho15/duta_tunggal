<?php

namespace App\Forms\Components;

use Filament\Forms\Components\Field;

class SignaturePad extends Field
{
    protected string $view = 'forms.components.signature-pad';

    protected function setUp(): void
    {
        parent::setUp();

        // agar Livewire sinkron
        $this->dehydrated(true);
        $this->dehydrateStateUsing(fn($state) => $state);
    }

    public static function make(string $name): static
    {
        return parent::make($name);
    }
}
