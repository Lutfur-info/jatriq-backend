<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\enum\DocumentStatus;
use App\enum\DocumentType;
use App\Models\User;
use App\Models\UserDocument;
use App\Services\VerificationService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The review itself: read a document, then approve or reject it.
 *
 * Every decision goes through `VerificationService::review()`. That is not
 * incidental - the service records the decision and recomputes the badge from
 * the whole required set, so writing `status` here directly would leave the
 * badge stale (see .ai/rules/services.md).
 *
 * A reviewer cannot upload, attach or delete a document. Documents arrive
 * from the mobile app, and a rejection is answered by the applicant
 * re-uploading, which puts the replacement back into review on its own.
 */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Submitted documents';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('original_name')
            ->columns([
                TextColumn::make('type')
                    ->label('Document')
                    ->formatStateUsing(fn (DocumentType $state): string => $state->label()),

                TextColumn::make('original_name')
                    ->label('File')
                    ->description(fn (UserDocument $record): string => $record->mime_type)
                    ->searchable(),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('rejection_reason')
                    ->label('Reason')
                    ->wrap()
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('reviewer.full_name')
                    ->label('Reviewed by')
                    ->placeholder('Not yet reviewed'),
            ])
            ->defaultSort('created_at', 'asc')
            ->recordActions([
                Action::make('open')
                    ->label('Open file')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    // The private disk has no public URL: this is the same
                    // authenticated reader the mobile app uses, and it
                    // re-checks owner-or-admin on every request.
                    ->url(fn (UserDocument $record): string => route('api.documents.show', ['document' => $record->getKey()]))
                    ->openUrlInNewTab(),

                Action::make('approve')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve this document?')
                    ->modalDescription('The badge turns green once every required document is approved.')
                    ->hidden(fn (UserDocument $record): bool => $record->status === DocumentStatus::Approved)
                    ->action(fn (UserDocument $record) => $this->record(
                        $record,
                        DocumentStatus::Approved,
                    )),

                Action::make('reject')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->schema([
                        Textarea::make('rejection_reason')
                            ->label('What is wrong with it?')
                            ->helperText('The applicant sees this, so say what they need to send instead.')
                            ->required()
                            ->maxLength(500)
                            ->rows(3),
                    ])
                    ->modalHeading('Reject this document?')
                    ->modalSubmitActionLabel('Reject')
                    ->hidden(fn (UserDocument $record): bool => $record->status === DocumentStatus::Rejected)
                    ->action(fn (UserDocument $record, array $data) => $this->record(
                        $record,
                        DocumentStatus::Rejected,
                        $data['rejection_reason'],
                    )),
            ]);
    }

    /**
     * Hand the decision to the service and report the badge it produced.
     */
    private function record(UserDocument $document, DocumentStatus $status, ?string $reason = null): void
    {
        /** @var User $reviewer */
        $reviewer = Filament::auth()->user();

        app(VerificationService::class)->review($document, $reviewer, $status, $reason);

        /** @var User $applicant */
        $applicant = $this->getOwnerRecord();

        Notification::make()
            ->success()
            ->title("{$document->type->label()} ".mb_strtolower($status->getLabel()))
            ->body("Badge is now: {$applicant->refresh()->verification_status->getLabel()}.")
            ->send();
    }
}
