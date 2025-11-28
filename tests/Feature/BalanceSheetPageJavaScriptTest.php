<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class BalanceSheetPageJavaScriptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create and authenticate a test user with super admin permissions
        /** @var \App\Models\User $user */
        $user = \App\Models\User::factory()->create();

        // Bypass Filament policy authorization for tests
        $this->actingAs($user);

        // Mock authorization to always return true for test environment
        Gate::before(function () {
            return true;
        });

        // Create test branch
        \App\Models\Cabang::create([
            'kode' => 'TEST',
            'nama' => 'Test Branch',
            'alamat' => 'Test Address',
            'telepon' => '0123456789',
        ]);

        // Create basic Chart of Accounts for Balance Sheet
        \App\Models\ChartOfAccount::create([
            'code' => '1-1001',
            'name' => 'Kas',
            'type' => 'Asset',
            'is_current' => true,
            'is_active' => true,
        ]);

        \App\Models\ChartOfAccount::create([
            'code' => '2-2001',
            'name' => 'Hutang Usaha',
            'type' => 'Liability',
            'is_current' => true,
            'is_active' => true,
        ]);

        \App\Models\ChartOfAccount::create([
            'code' => '3-3001',
            'name' => 'Modal',
            'type' => 'Equity',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function renders_the_page_with_modern_css_classes()
    {
        // Test that the page renders with modern card styling
        $response = $this->get('/admin/balance-sheet-page');

        $response->assertSee('modern-card')
                ->assertSee('summary-card')
                ->assertSee('filter-section')
                ->assertSee('section-header assets')
                ->assertSee('section-header liabilities')
                ->assertSee('section-header equity');
    }

    /** @test */
    public function includes_proper_css_for_modern_design()
    {
        $response = $this->get('/admin/balance-sheet-page');

        // Check for modern CSS classes
        $response->assertSee('.modern-card')
                ->assertSee('.summary-card')
                ->assertSee('.filter-section')
                ->assertSee('.section-header')
                ->assertSee('.account-row')
                ->assertSee('.account-code')
                ->assertSee('.account-balance');
    }

    /** @test */
    public function displays_icons_in_section_headers()
    {
        $response = $this->get('/admin/balance-sheet-page');

        $response->assertSee('🏦 ASET (ASSETS)')
                ->assertSee('📋 KEWAJIBAN (LIABILITIES)')
                ->assertSee('🏛️ EKUITAS (EQUITY)');
    }

    /** @test */
    public function shows_icons_in_summary_cards()
    {
        $response = $this->get('/admin/balance-sheet-page');

        $response->assertSee('💰') // Assets icon
                ->assertSee('📊') // Liabilities icon
                ->assertSee('🏛️') // Equity icon
                ->assertSee('📈'); // Ratio icon
    }

    /** @test */
    public function displays_icons_in_subsection_headers()
    {
        $response = $this->get('/admin/balance-sheet-page');

        $response->assertSee('💵 Aset Lancar')
                ->assertSee('💳 Kewajiban Lancar')
                ->assertSee('🏦 TOTAL ASET')
                ->assertSee('📊 TOTAL KEWAJIBAN')
                ->assertSee('⚖️ TOTAL KEWAJIBAN & EKUITAS', false);
    }

    /** @test */
    public function includes_emoji_icons_in_filter_section()
    {
        $response = $this->get('/admin/balance-sheet-page');

        $response->assertSee('📅 Tanggal Neraca')
                ->assertSee('🏢 Cabang')
                ->assertSee('🌐 Semua Cabang')
                ->assertSee('⚙️ Opsi');
    }

    /** @test */
    public function shows_export_button_icons()
    {
        $response = $this->get('/admin/balance-sheet-page');

        $response->assertSee('📄 Export PDF')
                ->assertSee('📊 Export Excel')
                ->assertSee('🖨️ Print');
    }

    /** @test */
    public function displays_balance_check_with_emoji()
    {
        $response = $this->get('/admin/balance-sheet-page');

        $response->assertSee('✅') // Balanced indicator
                ->assertSee('Neraca Seimbang');
    }

    /** @test */
    public function includes_proper_css_animations_and_transitions()
    {
        $response = $this->get('/admin/balance-sheet-page');

        // Check for CSS animations
        $response->assertSee('transition:')
                ->assertSee('transform:')
                ->assertSee('hover:')
                ->assertSee('keyframes');
    }

    /** @test */
    public function has_responsive_design_css()
    {
        $response = $this->get('/admin/balance-sheet-page');

        $response->assertSee('@media (max-width: 768px)')
                ->assertSee('grid-template-columns: repeat(auto-fit')
                ->assertSee('flex-direction: column');
    }

    /** @test */
    public function includes_gradient_backgrounds()
    {
        $response = $this->get('/admin/balance-sheet-page');

        $response->assertSee('linear-gradient')
                ->assertSee('135deg');
    }

    /** @test */
    public function has_proper_box_shadows_for_modern_look()
    {
        $response = $this->get('/admin/balance-sheet-page');

        $response->assertSee('box-shadow:')
                ->assertSee('rgba(');
    }

    /** @test */
    public function includes_monospace_font_for_account_codes()
    {
        $response = $this->get('/admin/balance-sheet-page');

        $response->assertSee('font-family:')
                ->assertSee('Monaco')
                ->assertSee('monospace');
    }

    /** @test */
    public function displays_account_codes_with_proper_styling()
    {
        $response = $this->get('/admin/balance-sheet-page');

        $response->assertSee('account-code')
                ->assertSee('font-weight: 600');
    }

    /** @test */
    public function includes_hover_effects_for_interactive_elements()
    {
        $response = $this->get('/admin/balance-sheet-page');

        $response->assertSee('cursor: pointer')
                ->assertSee('hover:')
                ->assertSee('scale(');
    }

    /** @test */
    public function has_proper_color_scheme_for_different_sections()
    {
        $response = $this->get('/admin/balance-sheet-page');

        // Assets colors (green)
        $response->assertSee('#10b981')
                ->assertSee('#059669')
                // Liabilities colors (orange)
                ->assertSee('#f59e0b')
                ->assertSee('#d97706')
                // Equity colors (blue)
                ->assertSee('#3b82f6')
                ->assertSee('#2563eb');
    }

    /** @test */
    public function includes_shimmer_animation_for_section_headers()
    {
        $response = $this->get('/admin/balance-sheet-page');

        $response->assertSee('shimmer')
                ->assertSee('keyframes shimmer')
                ->assertSee('translateX(-100%)')
                ->assertSee('translateX(100%)');
    }

    /** @test */
    public function has_loading_shimmer_effect_css()
    {
        $response = $this->get('/admin/balance-sheet-page');

        $response->assertSee('loading-shimmer')
                ->assertSee('background-size: 200% 100%')
                ->assertSee('animation: loading');
    }

    /** @test */
    public function includes_proper_focus_states_for_form_inputs()
    {
        $response = $this->get('/admin/balance-sheet-page');

        $response->assertSee('focus:')
                ->assertSee('border-color:')
                ->assertSee('ring');
    }

    /** @test */
    public function displays_retained_earnings_with_re_code()
    {
        $response = $this->get('/admin/balance-sheet-page');

        $response->assertSee('RE')
                ->assertSee('Laba Ditahan');
    }

    /** @test */
    public function includes_proper_print_styles()
    {
        $response = $this->get('/admin/balance-sheet-page');

        $response->assertSee('@media print')
                ->assertSee('display: none !important')
                ->assertSee('page-break-inside: avoid');
    }
}