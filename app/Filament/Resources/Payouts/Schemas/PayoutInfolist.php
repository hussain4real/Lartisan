<?php

namespace App\Filament\Resources\Payouts\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PayoutInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('artisanProfile.business_name')->label('Artisan'),
                TextEntry::make('status')->badge(),
                TextEntry::make('provider_status')->label('Provider status')->badge()->placeholder('-'),
                TextEntry::make('amount')->money('NGN', divideBy: 100),
                TextEntry::make('payoutAccount.bank_name')->label('Bank'),
                TextEntry::make('payoutAccount.account_name')->label('Account name'),
                TextEntry::make('batch.id')->label('Batch')->placeholder('-'),
                TextEntry::make('provider_reference')->label('Reference')->placeholder('-'),
                TextEntry::make('provider_transfer_code')->label('Transfer code')->placeholder('-'),
                TextEntry::make('requestedBy.name')->label('Requested by'),
                TextEntry::make('approvedBy.name')->label('Approved by')->placeholder('-'),
                TextEntry::make('processedBy.name')->label('Processed by')->placeholder('-'),
                TextEntry::make('requested_at')->dateTime(),
                TextEntry::make('processing_at')->dateTime()->placeholder('-'),
                TextEntry::make('paid_at')->dateTime()->placeholder('-'),
                TextEntry::make('reconciled_at')->dateTime()->placeholder('-'),
                TextEntry::make('next_retry_at')->dateTime()->placeholder('-'),
                TextEntry::make('failure_reason')->placeholder('-')->columnSpanFull(),
            ]);
    }
}
