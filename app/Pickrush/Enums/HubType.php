<?php

namespace App\Pickrush\Enums;

enum HubType: string
{
    case Province = 'province';   // sorting center for a province (or a Metro Manila district)
    case Station = 'station';     // first and last mile for one city or municipality
}
