<?php

namespace Tests\Unit\Data\Nmbs;

use DateTime;
use Irail\Data\Nmbs\LiveboardDatasource;
use Irail\Data\Nmbs\Repositories\RawDataRepository;
use Irail\Data\Nmbs\Repositories\StationsRepository;
use Irail\Models\CachedData;
use Irail\Models\DepartureArrivalMode;
use Irail\Models\Requests\LiveboardRequestImpl;
use Tests\TestCase;

class LiveboardDataSourceTest extends TestCase
{
    public function testGetLiveboard_departuresBrusselsSouth_shouldParseAndReturnData()
    {
        $rawDataRepo = \Mockery::mock(RawDataRepository::class);
        $rawDataRepo->shouldReceive("getLiveboardData")->andReturn(
            new CachedData("dummy-cache-key", file_get_contents(__DIR__ . "/../../../Fixtures/departures-brussels-2021114.json"))
        );
        $stationsRepo = new StationsRepository();
        $datasource = new LiveboardDatasource($stationsRepo, $rawDataRepo);
        $liveboardData = $datasource->getLiveboard(
            new LiveboardRequestImpl("nl",
                DepartureArrivalMode::MODE_DEPARTURE,
                new DateTime(),
                '008814001'));
        self::assertNotEmpty($liveboardData);
        self::assertCount(51, $liveboardData->getStops());

        $queriedStation = $liveboardData->getStation();
        foreach ($liveboardData->getStops() as $stop) {
            self::assertEquals($queriedStation, $stop->getStation());
        }

        $stop1 = $liveboardData->getStops()[0];
        self::assertEquals("2021-11-14 13:03:00", $stop1->getScheduledDepartureTime()->format("Y-m-d H:i:s"));
        self::assertEquals(8 * 60, $stop1->getDepartureDelay());
        self::assertEquals("Dinant", $stop1->getHeadsign());
        self::assertCount(1, $stop1->getDirection());
        self::assertEquals("008863503", $stop1->getDirection()[0]->getId());
        self::assertEquals("Dinant", $stop1->getDirection()[0]->getStationName());
        self::assertEquals("IC", $stop1->getVehicle()->getType());
        self::assertEquals(2513, $stop1->getVehicle()->getNumber());
    }
}