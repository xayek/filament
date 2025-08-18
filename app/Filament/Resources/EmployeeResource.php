<?php

namespace App\Filament\Resources;

use Illuminate\Database\Eloquent\Model;
use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\City;
use App\Models\Country;
use App\Models\Department;
use App\Models\Employee;
use App\Models\State;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon  = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Employee Management';

    protected static ?string $recordTitleAttribute = 'first_name';

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->last_name;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'last_name', 'middle_name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Country' => $record->country->name,
            'State' => $record->state->name,
            'City' => $record->city->name,
            'Department' => $record->department->name,
        ];
    }

    public static function getGloballySearchEloquentQuery(): Builder //Bu metod Filament’in Global Search (küresel arama) özelliğini özelleştirmek için yazılır.
                                                                    //Normalde parent::getGlobalSearchEloquentQuery() sadece ana model üzerinden sorgu yapar. Senin örneğinde ise with([...]) eklenerek ilişkiler de eager load edilir.
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['country', 'state', 'city', 'department']);
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::count() > 10 ? 'warning' : 'success';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Relationships')
                ->schema([
                    // Country (belongsTo) - tekil
                    Forms\Components\Select::make('country_id')
                        ->label('Country')
                        ->relationship(name: 'country', titleAttribute: 'name')
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function (Set $set) {
                            $set('state_id', null);
                            $set('city_id', null);
                        })
                        ->required(),

                    // State options -> country_id'ye bağlı
                    Forms\Components\Select::make('state_id')
                        ->label('State')
                        ->options(fn (Get $get): Collection => State::query()
                            ->when($get('country_id'), fn ($q, $countryId) => $q->where('country_id', $countryId))
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('city_id', null))
                        ->required(),

                    // City options -> state_id'ye bağlı
                    Forms\Components\Select::make('city_id')
                        ->label('City')
                        ->options(fn (Get $get): Collection => City::query()
                            ->when($get('state_id'), fn ($q, $stateId) => $q->where('state_id', $stateId))
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required(),

                    // Department (belongsTo) - tekil
                    Forms\Components\Select::make('department_id')
                        ->label('Department')
                        ->relationship(name: 'department', titleAttribute: 'name')
                        ->searchable()
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
                        ->required(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('country.name')
                    ->label('Country')
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
                    ->date()
                    ->toggleable(isToggleHiddenByDefault: true),
                Tables\Columns\TextColumn::make('date_hired')
                    ->date(),
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
                // Department select filter
                SelectFilter::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload()
                    ->indicator('Department'),

                // created_at aralığı filtresi
                Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')->label('Created from'),
                        Forms\Components\DatePicker::make('created_until')->label('Created until'),
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
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                ->successNotification(Notification::make()
                        ->title('Employee Deleted')
                        ->body('The employee has been deleted successfully.')
                        ->success())
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfoSection::make('Relationships')
                ->schema([
                    TextEntry::make('country.name')->label('Country Name'),
                    TextEntry::make('state.name')->label('State Name'),
                    TextEntry::make('city.name')->label('City Name'),
                ])->columns(2),

            InfoSection::make('Name')
                ->schema([
                    TextEntry::make('first_name')->label('First Name'),
                    TextEntry::make('last_name')->label('Last Name'),
                    TextEntry::make('middle_name')->label('Middle Name'),
                ])->columns(3),

            InfoSection::make('Address')
                ->schema([
                    TextEntry::make('address')->label('Address'),
                    TextEntry::make('zip_code')->label('Zip Code'),
                ])->columns(2),
        ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit'   => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
