<?php

namespace Irail\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Irail\Database\HistoricCompositionDao;
use Irail\Database\LogDao;
use Irail\Http\Requests\DatedVehicleJourneyV2Request;
use Irail\Models\Dto\v2\DatedVehicleJourneyV2Converter;
use Irail\Models\Vehicle;
use Irail\Repositories\VehicleCompositionRepository;
use Irail\Repositories\VehicleJourneyRepository;
use Spatie\Async\Pool;

class DatedVehicleJourneyV2Controller extends BaseIrailController
{
    private VehicleJourneyRepository $vehicleJourneyRepository;
    private VehicleCompositionRepository $vehicleCompositionRepository;
    private HistoricCompositionDao $historicCompositionRepository;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        VehicleJourneyRepository $vehicleJourneyRepository,
        VehicleCompositionRepository $vehicleCompositionRepository,
        HistoricCompositionDao $historicCompositionRepository
    ) {
        //
        $this->vehicleJourneyRepository = $vehicleJourneyRepository;
        $this->vehicleCompositionRepository = $vehicleCompositionRepository;
        $this->historicCompositionRepository = $historicCompositionRepository;
    }

    public function getDatedVehicleJourney(DatedVehicleJourneyV2Request $request): JsonResponse
    {
        $pool = new Pool();
        $compositionTask = $pool
            ->add(function () use ($request) {
                // The type may not be determined successfully, but only the number is needed anyway
                $vehicle = Vehicle::fromName($request->getVehicleId(), $request->getDateTime());
                $composition = $this->vehicleCompositionRepository->getComposition($vehicle);
                // Store this in the database, in case it's new.
                $this->historicCompositionRepository->recordComposition($composition);
            });

        $vehicleJourneySearchResult = $this->vehicleJourneyRepository->getDatedVehicleJourney($request);
        $statistics = $this->historicCompositionRepository->getHistoricCompositionStatistics(
            $vehicleJourneySearchResult->getVehicle()->getType(),
            $vehicleJourneySearchResult->getVehicle()->getNumber()
        );
        $pool->wait();
        $composition = $compositionTask->getOutput();
        $dto = DatedVehicleJourneyV2Converter::convert($request, $vehicleJourneySearchResult, $composition->getSegments(), $statistics);
        $this->logRequest($request);
        return $this->outputJson($request, $dto);
    }

    /**
     * @param DatedVehicleJourneyV2Request $request
     * @return void
     */
    public function logRequest(DatedVehicleJourneyV2Request $request): void
    {
        $query = [
            'vehicle'  => $request->getVehicleId(),
            'language' => $request->getLanguage(),
            'version'  => 2
        ];
        app(LogDao::class)->log('VehicleInformation', $query, $request->getUserAgent());
    }
}
