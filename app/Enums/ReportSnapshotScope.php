<?php

namespace App\Enums;

enum ReportSnapshotScope: string
{
    case Global = 'global';
    case State = 'state';
    case LocalGovernment = 'local_government';
    case Territory = 'territory';
    case AreaAgent = 'area_agent';
}
