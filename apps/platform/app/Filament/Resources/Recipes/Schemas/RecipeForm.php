<?php

declare(strict_types=1);

namespace App\Filament\Resources\Recipes\Schemas;

use App\Exceptions\InvalidImage;
use App\Models\Recipe;
use App\Services\PhotoImage;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** Without `household_id` — same reasoning as in `TaskForm`. */
class RecipeForm
{
    /**
     * The same few buttons as the device's editor: what the renderer there
     * draws, and nothing it would show as raw text.
     */
    public const TOOLBAR = [['heading', 'bold', 'italic'], ['bulletList', 'orderedList', 'link'], ['undo', 'redo']];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label(__('Title'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                MarkdownEditor::make('intro')
                    ->label(__('Introduction'))
                    ->toolbarButtons(self::TOOLBAR)
                    ->maxLength(5000)
                    ->columnSpanFull(),

                FileUpload::make('photo_path')
                    ->label(__('Photo'))
                    ->image()
                    // After image(): it would otherwise set image/* and let SVG with scripts through.
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(20480)
                    // Redrawn and private, like the photo frame's pictures.
                    ->saveUploadedFileUsing(function (FileUpload $component, TemporaryUploadedFile $file): string {
                        try {
                            return app(PhotoImage::class)->store($file->get(), Recipe::PHOTO_DIRECTORY);
                        } catch (InvalidImage $error) {
                            throw ValidationException::withMessages([$component->getStatePath() => $error->getMessage()]);
                        }
                    })
                    ->columnSpanFull(),

                Grid::make(4)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('servings')
                            ->label(__('Servings'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50)
                            ->default(4)
                            ->required(),

                        TextInput::make('minutes')
                            ->label(__('Minutes'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(2880),

                        Select::make('difficulty')
                            ->label(__('Difficulty'))
                            ->options(self::difficulties()),

                        Toggle::make('vegetarian')
                            ->label(__('Vegetarian'))
                            ->inline(false),
                    ]),

                TagsInput::make('categories')
                    ->label(__('Categories'))
                    ->placeholder(__('e.g. Soup, Quick, Austrian'))
                    ->columnSpanFull(),

                Repeater::make('ingredients')
                    ->label(__('Ingredients'))
                    ->columnSpanFull()
                    ->addActionLabel(__('Add ingredient'))
                    ->reorderable()
                    ->columns(5)
                    ->schema([
                        // Amount and unit apart, so that portions scale and a week's shopping sums up.
                        TextInput::make('amount')
                            ->label(__('Amount'))
                            ->numeric()
                            ->minValue(0),

                        TextInput::make('unit')
                            ->label(__('Unit'))
                            ->maxLength(32)
                            ->placeholder(__('g, tbsp, piece')),

                        TextInput::make('name')
                            ->label(__('Ingredient'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('note')
                            ->label(__('Note'))
                            ->maxLength(255)
                            ->placeholder(__('to taste, finely chopped')),

                        TextInput::make('group')
                            ->label(__('Group'))
                            ->maxLength(100)
                            ->placeholder(__('For the dough')),

                        // A range ("2–3") from an import stays when the row is edited.
                        Hidden::make('amount_max'),
                    ])
                    ->helperText(__('On the wall, one tap puts these ingredients on the shopping list.')),

                MarkdownEditor::make('steps')
                    ->label(__('Preparation'))
                    ->toolbarButtons(self::TOOLBAR)
                    ->helperText(__('A numbered list becomes steps to tick off while cooking.'))
                    ->maxLength(30000)
                    ->columnSpanFull(),
            ]);
    }

    /** @return array<string, string> */
    public static function difficulties(): array
    {
        return [
            'easy' => __('Easy'),
            'medium' => __('Medium'),
            'hard' => __('Demanding'),
        ];
    }
}
