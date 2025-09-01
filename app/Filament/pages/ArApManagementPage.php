<?php

namespace App\Filament\pages;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Artisan;

class ArApManagementPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static string $view = 'filament.pages.ar-ap-management-page';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'AR & AP Management';

    protected static ?int $navigationSort = 20;

    // Add this to make sure it's accessible
    protected static ?string $slug = 'ar-ap-management';
    
    // Make sure the page is always visible (remove any permission restrictions)
    public static function canAccess(): bool
    {
        return true;
    }
    
    // Add this to help with debugging
    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public string $activeTab = 'ar';

    public function mount(): void
    {
        $this->activeTab = request()->get('tab', 'ar');
    }

    public function table(Table $table): Table
    {
        $baseQuery = $this->activeTab === 'ar' 
            ? AccountReceivable::query() 
            : AccountPayable::query();

        return $table
            ->query($baseQuery->with([
                'invoice',
                $this->activeTab === 'ar' ? 'customer' : 'supplier'
            ]))
            ->columns($this->getTableColumns())
            ->defaultSort('created_at', 'desc')
            ->filters($this->getTableFilters())
            ->actions([
                Action::make('view_details')
                    ->label('View')
                    ->icon('heroicon-m-eye')
                    ->url(function ($record) {
                        try {
                            $resource = $this->activeTab === 'ar' 
                                ? 'account-receivables' 
                                : 'account-payables';
                            return route("filament.admin.resources.{$resource}.view", ['record' => $record->id]);
                        } catch (\Exception $e) {
                            return '#';
                        }
                    })
                    ->openUrlInNewTab(),
            ])
            ->striped()
            ->paginated([25, 50, 100]);
    }

    protected function getTableColumns(): array
    {
        $entityColumn = $this->activeTab === 'ar' ? 'customer' : 'supplier';
        $entityLabel = $this->activeTab === 'ar' ? 'Customer' : 'Supplier';

        return [
            TextColumn::make('id')
                ->label('ID')
                ->searchable()
                ->sortable(),
                
            TextColumn::make($entityColumn)
                ->label($entityLabel)
                ->formatStateUsing(function ($state) {
                    if ($state) {
                        return "({$state->code}) {$state->name}";
                    }
                    return '-';
                })
                ->searchable(['code', 'name'])
                ->sortable(),
                
            TextColumn::make('total')
                ->label('Total')
                ->money('idr')
                ->sortable(),
                
            TextColumn::make('paid')
                ->label('Paid')
                ->money('idr')
                ->sortable()
                ->color('success'),
                
            TextColumn::make('remaining')
                ->label('Outstanding')
                ->money('idr')
                ->sortable()
                ->color(fn ($state) => $state > 0 ? 'warning' : 'success')
                ->weight('bold'),
                
            TextColumn::make('status')
                ->badge()
                ->color(function ($state) {
                    return match ($state) {
                        'Belum Lunas' => 'warning',
                        'Lunas' => 'success',
                        default => 'gray'
                    };
                }),
        ];
    }

    protected function getTableFilters(): array
    {
        return [
            Tables\Filters\SelectFilter::make('status')
                ->options([
                    'Belum Lunas' => 'Outstanding',
                    'Lunas' => 'Paid',
                ])
                ->multiple(),
        ];
    }

    public function getTitle(): string
    {
        $type = $this->activeTab === 'ar' ? 'Account Receivable' : 'Account Payable';
        return "{$type} Management";
    }
}
