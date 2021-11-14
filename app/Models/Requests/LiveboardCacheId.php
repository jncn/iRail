<?php

namespace Irail\Models\Requests;

use DateTime;

trait LiveboardCacheId
{
    public function getCacheId(): string
    {
        return '|Liveboard|' . join('|', [
                $this->getStationId(),
                $this->getDateTime()->getTimestamp(),
                $this->getDepartureArrivalMode(),
                $this->getLanguage()
            ]);
    }

    abstract function getStationId(): string;

    abstract function getDateTime(): DateTime;

    abstract function getDepartureArrivalMode(): int;

    abstract function getLanguage(): string;

}