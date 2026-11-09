<?php

namespace App\Import\Enum;

enum ImportStatus: string
{
    case Pending = 'pending';
    case Done = 'done';
    case Duplicate = 'duplicate';
    case Failed = 'failed';
}