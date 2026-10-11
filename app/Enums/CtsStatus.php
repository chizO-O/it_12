<?php

namespace App\Enums;

enum CtsStatus: string
{
    case Requested = 'requested';
    case PropertyVerified = 'property_verified';
    case ForBuyerReview = 'for_buyer_review';
    case RevisionRequested = 'revision_requested';
    case ForSigning = 'for_signing';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Property verification pending',
            self::PropertyVerified => 'Verified — awaiting draft',
            self::ForBuyerReview => 'For buyer review',
            self::RevisionRequested => 'Buyer requested revisions',
            self::ForSigning => 'Reviewed — awaiting signing and notarization',
            self::Completed => 'Executed CTS recorded',
        };
    }
}
