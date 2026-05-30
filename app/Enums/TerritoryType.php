<?php

namespace App\Enums;

enum TerritoryType: string
{
    case Ward = 'ward';
    case Community = 'community';
    case Market = 'market';
    case Estate = 'estate';
    case Cluster = 'cluster';
    case Zone = 'zone';
}
