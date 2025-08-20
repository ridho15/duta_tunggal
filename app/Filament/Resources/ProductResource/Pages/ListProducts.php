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
                                    Hidden::make('is_searching')
                                        ->default(false),
                                    Hidden::make('product_info_message')
                                        ->default(''),
                                    Select::make('cabang_id')
                                        ->label('Cabang')
                                        ->preload()
                                        ->searchable()
                                        ->reactive()
                                        ->columnSpanFull()
                                        ->relationship('cabang', 'nama')
                                        ->afterStateUpdated(function ($set, $get, $state) {
                                            // Set loading state for initial load
                                            $set('is_searching', true);
                                            $set('product_info_message', 'Memuat produk...');

                                            $listProduct = Product::select(['id', 'sku', 'name', 'sell_price', 'cost_price'])
                                                ->where('cabang_id', $state)
                                                ->orderBy('sku')
                                                ->get();
                                            $items = [];
                                            foreach ($listProduct as $item) {
                                                array_push($items, [
                                                    'product_id' => $item->id,
                                                    'cost_price' => $item->cost_price,
                                                    'sell_price' => $item->sell_price
                                                ]);
                                            }

                                            $set('listProduct', $items);
                                            $set('search_product', ''); // Reset search when cabang changes
                                            $set('product_count', $listProduct->count()); // Set initial count

                                            // Clear loading state and set initial message
                                            $set('product_info_message', "Menampilkan {$listProduct->count()} produk");
                                            $set('is_searching', false);
                                        })
                                        ->getOptionLabelFromRecordUsing(function (Cabang $cabang) {
                                            return "({$cabang->kode}) {$cabang->nama}";
                                        }),
                                    TextInput::make('search_product')
                                        ->label('Cari Product (SKU/Nama)')
                                        ->placeholder('Ketik untuk mencari product...')
                                        ->reactive()
                                        ->debounce(500)
                                        ->afterStateUpdated(function ($set, $get, $state) {
                                            // Set loading state
                                            $set('is_searching', true);
                                            $set('product_info_message', 'Mencari produk...');

                                            $cabangId = $get('cabang_id');
                                            if (!$cabangId) {
                                                $set('is_searching', false);
                                                return;
                                            }

                                            $query = Product::select(['id', 'sku', 'name', 'sell_price', 'cost_price'])
                                                ->where('cabang_id', $cabangId);

                                            if ($state) {
                                                $query->where(function ($q) use ($state) {
                                                    $q->where('sku', 'LIKE', '%' . $state . '%')
                                                        ->orWhere('name', 'LIKE', '%' . $state . '%');
                                                });
                                            }

                                            $listProduct = $query->orderBy('sku')->get();
                                            $items = [];
                                            foreach ($listProduct as $item) {
                                                array_push($items, [
                                                    'product_id' => $item->id,
                                                    'cost_price' => $item->cost_price,
                                                    'sell_price' => $item->sell_price
                                                ]);
                                            }

                                            $set('listProduct', $items);
                                            $set('product_count', $listProduct->count());

                                            // Update info message and clear loading state
                                            if ($state) {
                                                $set('product_info_message', "Ditemukan {$listProduct->count()} produk dengan kata kunci: '{$state}'");
                                            } else {
                                                $set('product_info_message', "Menampilkan {$listProduct->count()} produk");
                                            }
                                            $set('is_searching', false);
                                        })
                                        ->columnSpanFull()
                                        ->visible(fn($get) => $get('cabang_id'))
                                        ->suffixIcon(fn($get) => $get('is_searching') ? 'heroicon-m-arrow-path' : 'heroicon-m-magnifying-glass')
                                        ->suffixIconColor(fn($get) => $get('is_searching') ? 'warning' : 'gray')
                                        ->helperText(fn($get) => $get('is_searching') ? 'Sedang mencari produk...' : 'Ketik untuk mencari berdasarkan SKU atau nama produk'),
                                    Placeholder::make('product_info')
                                        ->label('')
                                        ->content(function ($get) {
                                            $isSearching = $get('is_searching');
                                            $message = $get('product_info_message');

                                            if ($isSearching) {
                                                return $message;
                                            }

                                            return $message ?? 'Silakan pilih cabang terlebih dahulu';
                                        })
                                        ->visible(fn($get) => $get('cabang_id'))
                                        ->columnSpanFull(),
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
                                    Select::make('ukuran_kertas')
                                        ->label('Ukuran Kertas')
                                        ->required()
                                        ->default('A4')
                                        ->options([
                                            'A4' => 'A4 (210 x 297 mm)',
                                            'A5' => 'A5 (148 x 210 mm)',
                                            'Letter' => 'Letter (216 x 279 mm)',
                                            'Legal' => 'Legal (216 x 356 mm)',
                                            'Sticker' => 'Sticker Label (100 x 150 mm)',
                                        ])
                                        ->validationMessages([
                                            'required' => 'Ukuran kertas belum dipilih'
                                        ]),
                                    Select::make('orientasi')
                                        ->label('Orientasi Kertas')
                                        ->required()
                                        ->default('portrait')
                                        ->options([
                                            'portrait' => 'Portrait (Tegak)',
                                            'landscape' => 'Landscape (Mendatar)',
                                        ])
                                        ->validationMessages([
                                            'required' => 'Orientasi kertas belum dipilih'
                                        ]),
                                    Select::make('ukuran_barcode')
                                        ->label('Ukuran Barcode')
                                        ->required()
                                        ->default('medium')
                                        ->options([
                                            'small' => 'Kecil (30mm x 15mm)',
                                            'medium' => 'Sedang (40mm x 20mm)', 
                                            'large' => 'Besar (50mm x 25mm)',
                                            'extra_large' => 'Sangat Besar (60mm x 30mm)',
                                        ])
                                        ->validationMessages([
                                            'required' => 'Ukuran barcode belum dipilih'
                                        ]),
                                    Select::make('barcode_per_baris')
                                        ->label('Barcode per Baris')
                                        ->required()
                                        ->default('3')
                                        ->reactive()
                                        ->options(function ($get) {
                                            $ukuranKertas = $get('ukuran_kertas');
                                            $orientasi = $get('orientasi');
                                            
                                            // Adjust options based on paper size and orientation
                                            if ($ukuranKertas === 'A4') {
                                                return $orientasi === 'landscape' 
                                                    ? ['2' => '2', '3' => '3', '4' => '4', '5' => '5']
                                                    : ['2' => '2', '3' => '3', '4' => '4'];
                                            } elseif ($ukuranKertas === 'A5') {
                                                return ['1' => '1', '2' => '2', '3' => '3'];
                                            } elseif ($ukuranKertas === 'Sticker') {
                                                return ['1' => '1', '2' => '2'];
                                            }
                                            
                                            return ['2' => '2', '3' => '3', '4' => '4'];
                                        })
                                        ->validationMessages([
                                            'required' => 'Jumlah barcode per baris belum dipilih'
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
                            'listProduct' => $listProduct,
                            'ukuran_barcode' => $data['ukuran_barcode'],
                            'barcode_per_baris' => $data['barcode_per_baris'],
                            'ukuran_kertas' => $data['ukuran_kertas'],
                            'orientasi' => $data['orientasi']
                        ])->setPaper($data['ukuran_kertas'], $data['orientasi']);

                        return response()->streamDownload(function () use ($pdf) {
                            echo $pdf->stream();
                        }, 'Product_Barcode_' . $date . '.pdf');
                    }),
            ])->button()->label('Action')
        ];
    }
}
