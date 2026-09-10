<?php

declare(strict_types=1);

namespace Facturino\Model;

/** Code values published by the API; never infer them from the raw reason. */
enum PaRejectionSource: string
{
    case Platform = 'platform';
    case Buyer = 'buyer';
    case Facturino = 'facturino';
}
