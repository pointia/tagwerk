<?php

declare(strict_types=1);

namespace App\Filament\Resources\Photos\Schemas;

use App\Exceptions\InvalidImage;
use App\Services\PhotoImage;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class PhotoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('path')
                    ->label(__('Picture'))
                    ->image()
                    // After image(): it would otherwise set image/* and let SVG with scripts through.
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(20480)
                    ->required()
                    /*
                     * On the private disk, not under `public`: a family
                     * photo belongs to nobody who guesses the URL. A freshly
                     * redrawn image is stored — that drops EXIF including the
                     * location and everything else that can hang on an image
                     * file.
                     */
                    ->saveUploadedFileUsing(function (FileUpload $component, TemporaryUploadedFile $file): string {
                        try {
                            return app(PhotoImage::class)->store($file->get());
                        } catch (InvalidImage $error) {
                            throw ValidationException::withMessages([$component->getStatePath() => $error->getMessage()]);
                        }
                    }),

                TextInput::make('caption')
                    ->label(__('Caption'))
                    ->helperText(__('Shown small in the photo frame. May stay empty.'))
                    ->maxLength(255),

                DatePicker::make('taken_on')
                    ->label(__('Taken on'))
                    ->native(false),
            ]);
    }
}
