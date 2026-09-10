<?php

declare(strict_types=1);

namespace Facturino\Model;

/** Code values published by the API; never infer them from the raw reason. */
enum PaRejectionCategory: string
{
    case BuyerNotInDirectory = 'buyer_not_in_directory';
    case AddressingError = 'addressing_error';
    case Other = 'other';
    case FormatInvalid = 'format_invalid';
    case SemanticError = 'semantic_error';
    case Duplicate = 'duplicate';
    case PlatformAuth = 'platform_auth';
    case PlatformUnavailable = 'platform_unavailable';
    case RefusedByBuyer = 'refused_by_buyer';
    case Suspended = 'suspended';
    case Unknown = 'unknown';
}
