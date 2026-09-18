<?php

declare(strict_types=1);

namespace App\Infrastructure;

interface CatalogImporter
{
    /** @param array{systemsUrl:string,stationsUrl:string,maxSystems:?int,maxStations:?int,dryRun:bool} $options @return array{systems:int,stations:int} */
    public function import(array $options): array;
}
