<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Exports\ProductExport;
use App\Filament\Resources\ProductResource;
use App\Http\Controllers\HelperController;
use App\Models\Cabang;
use App\Models\Product;
use App\Services\ProductService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                CreateAction::make()
                    ->color('primary')
                    ->icon('heroicon-o-plus-circle'),
                Action::make('updateHargaPerProduct')
                    ->label('Update Harga Per Product')
                    ->color('primary')
                    ->icon('heroicon-o-adjustments-vertical')
                    ->form(function () {
                        return [
                            Fieldset::make('Update Harga Per Product')
                                ->schema([
                                    Select::make('cabang_id')
                                        ->label('Cabang')
                                        ->preload()
                                        ->searchable()
                                        ->reactive()
                                        ->columnSpanFull()
                                        ->relationship('cabang', 'nama')
                                        ->afterStateUpdated(function ($set, $get, $state) {
                                            $listProduct = Product::select(['id', 'sku', 'name', 'sell_price', 'cost_price'])->where('cabang_id', $state)->get();
                                            $items = [];
                                            foreach ($listProduct as $item) {
                                                array_push($items, [
                                                    'product_id' => $item->id,
                                                    'cost_price' => $item->cost_price,
                                                    'sell_price' => $item->sell_price
                                                ]);
                                            }

                                            $set('listProduct', $items);
                                        })
                                        ->getOptionLabelFromRecordUsing(function (Cabang $cabang) {
                                            return "({$cabang->kode}) {$cabang->nama}";
                                        }),
                                    Repeater::make('listProduct')
                                        ->defaultItems(0)
                                        ->reactive()
                                        ->columns(3)
                                        ->columnSpanFull()
                                        ->addAction(function (ActionsAction $action) {
                                            return $action->disabled()
                                                ->hidden();
                                        })
                                        ->schema([
                                            Hidden::make('product_id'),
                                            Placeholder::make('placeholder_product')
                                                ->label('Product')
                                                ->reactive()
                                                ->content(function ($get) {
                                                    $product = Product::find($get('product_id'));
                                                    return "({$product->sku}) {$product->name}";
                                                }),
                                            TextInput::make('sell_price')
                                                ->label('Harga')
                                                ->numeric()
                                                ->prefix('Rp')
                                                ->default(0)
                                                ->required(),
                                            TextInput::make('cost_price')
                                                ->label('Cost')
                                                ->numeric()
                                                ->prefix('Rp')
                                                ->default(0)
                                                ->required()
                                        ])
                                ])
                        ];
                    })
                    ->action(function (array $data) {
                        $productService = app(ProductService::class);
                        $productService->updateHargaPerProduct($data);
                        HelperController::sendNotification(isSuccess: true, title: "Information", message: "Harga per product berhasil di update");
                    }),
                Action::make('updateHargaPerKategori')
                    ->label('Update Harga Per Kategori')
                    ->icon('heroicon-o-adjustments-vertical')
                    ->color('primary')
                    ->form(function () {
                        return [
                            Fieldset::make('Form Update Harga Per Kategori')
                                ->columnSpanFull()
                                ->schema([
                                    Select::make('cabang_id')
                                        ->label('Cabang')
                                        ->preload()
                                        ->searchable()
                                        ->required()
                                        ->reactive()
                                        ->relationship('cabang', 'id')
                                        ->getOptionLabelFromRecordUsing(function (Cabang $cabang) {
                                            return "({$cabang->kode}) {$cabang->nama}";
                                        }),
                                    Select::make('product_category_id')
                                        ->label('Kategori')
                                        ->preload()
                                        ->searchable()
                                        ->relationship('productCategory', 'name', function (Builder $query, $get) {
                                            $query->where('cabang_id', $get('cabang_id'));
                                        })->required(),
                                    Radio::make('tipe_perubahan')
                                        ->label('Tipe Perubahan')
                                        ->inlineLabel()
                                        ->options([
                                            'Penambahan' => 'Penambahan',
                                            'Pengurangan' => 'Pengurangan',
                                        ])
                                        ->required()
                                        ->validationMessages([
                                            'required' => 'Tipe Perubahan belum dipilih'
                                        ]),
                                    TextInput::make('persentase_perubahan')
                                        ->label('Persentase Perubahan (%)')
                                        ->numeric()
                                        ->maxValue(100)
                                        ->suffix('%')
                                        ->default(0),
                                ])
                        ];
                    })
                    ->action(function (array $data) {
                        $productService = app(ProductService::class);
                        $productService->updateHargaPerKategori($data);
                        HelperController::sendNotification(isSuccess: true, title: "Information", message: "Update harga per kategori berhasil dilakukan");
                    }),
                Action::make('printDaftarProduct')
                    ->label('Print Daftar Product')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->form(function () {
                        return [
                            Fieldset::make('Print Daftar Product')
                                ->columns(2)
                                ->schema([
                                    Select::make('cabang_id')
                                        ->label('Cabang')
                                        ->preload()
                                        ->searchable()
                                        ->required()
                                        ->reactive()
                                        ->validationMessages([
                                            'required' => 'Cabang belum dipilih'
                                        ])
                                        ->relationship('cabang', 'id')
                                        ->getOptionLabelFromRecordUsing(function (Cabang $cabang) {
                                            return "({$cabang->kode}) {$cabang->nama}";
                                        }),
                                    Radio::make('hasil_cetak')
                                        ->label('Hasil Cetak')
                                        ->required()
                                        ->inline()
                                        ->options([
                                            'Excel' => 'Excel',
                                            'Pdf' => 'Pdf'
                                        ])
                                        ->validationMessages([
                                            'required' => 'Hasil Cetak belum dipilih'
                                        ]),
                                    Select::make('dari_product_id')
                                        ->label('Mulai dari Kode Produk')
                                        ->preload()
                                        ->reactive()
                                        ->searchable(['sku', 'name'])
                                        ->options(function ($get) {
                                            return Product::where('cabang_id', $get('cabang_id'))->get()->pluck('sku', 'id');
                                        })
                                        ->getOptionLabelFromRecordUsing(function (Product $product) {
                                            return "({$product->sku}) {$product->name}";
                                        })->required()
                                        ->validationMessages([
                                            'required' => 'Mulai dari Kode Produk belum dipilih'
                                        ]),
                                    Select::make('sampai_product_id')
                                        ->label('Sampai Kode Produk')
                                        ->preload()
                                        ->reactive()
                                        ->searchable(['sku', 'name'])
                                        ->options(function ($get) {
                                            return Product::where('cabang_id', $get('cabang_id'))->get()->pluck('sku', 'id');
                                        })
                                        ->getOptionLabelFromRecordUsing(function (Product $product) {
                                            return "({$product->sku}) {$product->name}";
                                        })->required()
                                        ->validationMessages([
                                            'required' => 'Sampai Kode Produk belum dipilih'
                                        ]),

                                ])
                        ];
                    })
                    ->modalSubmitActionLabel("Print / Cetak")
                    ->action(function (array $data) {
                        $date = now()->format('Ymd');
                        if ($data['hasil_cetak'] == 'Excel') {
                            return Excel::download(new ProductExport($data), 'Product_' . $date . '.xlsx');
                        } elseif ($data['hasil_cetak'] == 'Pdf') {
                            $listProduct = Product::with(['cabang', 'productCategory'])->where('cabang_id', $data['cabang_id'])
                                ->where('id', '>=', $data['dari_product_id'])
                                ->where('id', '<=', $data['sampai_product_id'])
                                ->get();
                            $pdf = Pdf::loadView('pdf.product', [
                                'listProduct' => $listProduct
                            ])->setPaper('A4', 'landscape');

                            return response()->streamDownload(function () use ($pdf) {
                                echo $pdf->stream();
                            }, 'Product_' . $date . '.pdf');
                        }
                    }),
                Action::make('printBarcode')
                    ->label('Print Barcode')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->modalSubmitActionLabel("Print / Cetak")
                    ->form(function () {
                        return [
                            Fieldset::make('Print Barcode')
                                ->columnSpanFull()
                                ->columns(2)
                                ->schema([
                                    Select::make('dari_product_id')
                                        ->label('Mulai dari Kode Produk')
                                        ->preload()
                                        ->reactive()
                                        ->searchable(['sku', 'name'])
                                        ->options(function ($get) {
                                            return Product::get()->pluck('sku', 'id');
                                        })
                                        ->getOptionLabelFromRecordUsing(function (Product $product) {
                                            return "({$product->sku}) {$product->name}";
                                        })->required()
                                        ->validationMessages([
                                            'required' => 'Mulai dari Kode Produk belum dipilih'
                                        ]),
                                    Select::make('sampai_product_id')
                                        ->label('Sampai Kode Produk')
                                        ->preload()
                                        ->reactive()
                                        ->searchable(['sku', 'name'])
                                        ->options(function ($get) {
                                            return Product::get()->pluck('sku', 'id');
                                        })
                                        ->getOptionLabelFromRecordUsing(function (Product $product) {
                                            return "({$product->sku}) {$product->name}";
                                        })->required()
                                        ->validationMessages([
                                            'required' => 'Sampai Kode Produk belum dipilih'
                                        ]),
                                ])
                        ];
                    })
                    ->action(function (array $data) {
                        $date = now()->format('Ymd');
                        $listProduct = Product::where('id', '>=', $data['dari_product_id'])
                            ->where('id', '<=', $data['sampai_product_id'])
                            ->get();
                        $pdf = Pdf::loadView('pdf.product-barcode', [
                            'listProduct' => $listProduct
                        ])->setPaper('A4', 'landscape');

                        return response()->streamDownload(function () use ($pdf) {
                            echo $pdf->stream();
                        }, 'Product_Barcode' . $date . '.pdf');
                    }),
            ])->button()->label('Action')
        ];
    }
}
