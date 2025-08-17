<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource\RelationManagers;
use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Employee Management'; 

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Relationships')
                         ->schema([
                            Forms\Components\Select::make('country_id')
                                ->relationship(name: 'country', titleAttribute: 'name')
                                ->searchable()
                                ->multiple()
                                ->preload()
                                ->live()
                                ->afterStateUpdated(function (Set $set) {
                                    $set('state_id', null);
                                    $set('city_id', null);
                                })
                                ->required(),
                            Forms\Components\Select::make('state_id')
                                ->options(fn(Get $get): Collection => State::query()
                                    ->where('country_id', $get('country_id'))
                                    ->pluck('name', 'id'))
                                ->searchable()
                                ->multiple()
                                ->preload()
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('city_id', null))
                                ->required(),
                            Forms\Components\Select::make('city_id')
                                ->options(fn(Get $get): Collection => City::query()
                                    ->where('state_id', $get('state_id'))
                                    ->pluck('name', 'id'))
                                ->searchable()
                                ->multiple()
                                ->preload()
                                ->live()
                                ->required(),
                            Forms\Components\Select::make('department_id')
                                ->relationship(name: 'department', titleAttribute: 'name')
                                ->searchable()
                                ->multiple()
                                ->preload()
                                ->required(),
                         ])->columns(2),
                   Forms\Components\Section::make('User Name')
                         ->description('Put the user name details in')
                         ->schema([
                            Forms\Components\TextInput::make('first_name')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('last_name')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('middle_name')
                                ->required()
                                ->maxLength(255),
                         ])->columns(3),
                   Forms\Components\Section::make('User address')
                       ->description('Put the user address details in')
                       ->schema([
                           Forms\Components\TextInput::make('address')
                               ->required()
                               ->maxLength(255),
                           Forms\Components\TextInput::make('zip_code')
                               ->required()
                               ->maxLength(255),
                       ])->columns(2),
                   Forms\Components\Section::make('Dates')
                       ->description('Put the user employment details in')
                       ->schema([
                           Forms\Components\DatePicker::make('date_of_birth')
                               ->native(false)
                               ->displayFormat('d/m/Y')
                               ->required(),
                           Forms\Components\DatePicker::make('date_hired')
                               ->native(false)
                               ->displayFormat('d/m/Y')
                               ->required()                               
                       ])->columns(2),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('country.name')
                    ->sortable()
                    ->searchable(isIndividual: true, isGlobal: false),
                Tables\Columns\TextColumn::make('first_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('middle_name')
                    ->searchable()
                    ->toggleable(isToggleHiddenByDefault: true),
                Tables\Columns\TextColumn::make('address')
                    ->searchable()
                    ->toggleable(isToggleHiddenByDefault: true),
                Tables\Columns\TextColumn::make('zip_code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('date_of_birth')
                    ->searchable()
                    ->toggleable(isToggleHiddenByDefault: true),
                Tables\Columns\TextColumn::make('date_hired')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggleHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggleHiddenByDefault: true),
            ])
            ->filters([
                // İlişki tabanlı select filtre: departments.name
                SelectFilter::make('department_id')
                    ->relationship('department', 'name') // modelde: public function department(){ return $this->belongsTo(Department::class); }
                    ->searchable()
                    ->preload()
                    ->label('Filter by Department')
                    ->indicator('Department'),

                // Tarih aralığı filtresi (created_at aralığı)
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')->label('Created from'),
                        DatePicker::make('created_until')->label('Created until'),
                    ])
                    ->indicateUsing(function (array $data): ?string {
                        if (! empty($data['created_from']) && ! empty($data['created_until'])) {
                            return "Created: {$data['created_from']} → {$data['created_until']}";
                        }

                        if (! empty($data['created_from'])) {
                            return "Created ≥ {$data['created_from']}";
                        }

                        if (! empty($data['created_until'])) {
                            return "Created ≤ {$data['created_until']}";
                        }

                        return null;
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'] ?? null,
                                fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date)
                            )
                            ->when(
                                $data['created_until'] ?? null,
                                fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date)
                            );
                    })
                ->indicateUsing(function (array $data): array {
                    $indicators = [];
                    if ($data['created_from'] ?? null) {
                        $indicators['created_from'] = 'Created from' . Carbon::parse($data['created_from'])->format('d/m/Y');
                    }
                    if ($data['created_until'] ?? null) {
                        $indicators['created_until'] = 'Created until' . Carbon::parse($data['created_until'])->format('d/m/Y');
                    }
                    return $indicators;
                }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }


     public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                    Section::make('Relationships')
                        ->schema([
                            TextEntry::make('country.name')->label('Country Name'),
                            TextEntry::make('state.name')->label('State Name'),
                            TextEntry::make('city.name')->label('City Name'),
                    ])->columns(2),
                    Section::make('Name')
                        ->schema([
                            TextEntry::make('first_name')->label('First Name'),
                            TextEntry::make('last_name')->label('Last Name'),
                            TextEntry::make('middle_name')->label('Middle Name'),
                    ])->columns(3),
                    Section::make('Address')
                        ->schema([
                            TextEntry::make('address')->label('Address'),
                            TextEntry::make('zip_code')->label('Zip Code'),
                    ])->columns(2)
            ]);
    }


    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            // 'view' => Pages\ViewEmployee::route('/{record}'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
