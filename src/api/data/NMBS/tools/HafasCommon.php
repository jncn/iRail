<?php

namespace Irail\api\data\NMBS\tools;

use Exception;
use Irail\api\data\models\Alert;
use Irail\api\data\models\hafas\HafasIntermediateStop;
use Irail\api\data\models\hafas\HafasVehicle;
use Irail\api\data\NMBS\StationsDatasource;
use stdClass;

/**
 * This class offers utility methods to work with HaCon/HAFAS data, which is used by the NMBS/SNCB.
 */
class HafasCommon
{

    /**
     * Throw an exception if the JSON API response contains an error instead of a result.
     *
     * @param array $json The JSON response as an associative array.
     *
     * @throws Exception An Exception containing an error message in case the JSON response contains an error message.
     */
    public static function throwExceptionOnLegacyInvalidResponse(array $json): void
    {
        if ($json['svcResL'][0]['err'] == "H9360") {
            throw new Exception("Date outside of the timetable period.", 404);
        }
        if ($json['errorCode'] == 'SVC_NO_RESULT' || $json['svcResL'][0]['err'] == "H890") {
            throw new Exception('No results found', 404);
        }
        if ($json['svcResL'][0]['err'] == 'PROBLEMS') {
            throw new Exception("Date likely outside of the timetable period. Check your query.", 400);
        }
        if ($json['svcResL'][0]['err'] != 'OK') {
            throw new Exception("This request failed. Please check your query. Error code " . $json['svcResL'][0]['err'], 500);
        }
        if ($json['svcResL'][0]['err'] != 'OK') {
            throw new Exception("We're sorry, this data is not available from our sources at this moment. Error code " . $json['svcResL'][0]['err'], 500);
        }
    }

    /**
     * Throw an exception if the JSON API response contains an error instead of a result.
     *
     * @param array|null $json The JSON response as an associative array.
     *
     * @throws Exception An Exception containing an error message in case the JSON response contains an error message.
     */
    public static function throwExceptionOnInvalidResponse(?array $json): void
    {
        if ($json == null) {
            throw new Exception("This request failed due to internal errors.", 500);
        }

        if (!key_exists('errorCode', $json)) {
            // all ok!
            return;
        }

        if ($json['errorCode'] == 'INT_ERR'
            || $json['errorCode'] == 'INT_GATEWAY'
            || $json['errorCode'] == 'INT_TIMEOUT') {
            throw new Exception("NMBS data is temporarily unavailable.", 504);
        }
        if ($json['errorCode'] == 'SVC_NO_RESULT') {
            throw new Exception('No results found', 404);
        }
        if ($json['errorCode'] == 'SVC_DATATIME_PERIOD') {
            throw new Exception("Date  outside of the timetable period. Check your query.", 400);
        }
        throw new Exception("This request failed. Please check your query. Error code " . $json['errorCode'], 500);

    }

    /**
     * Check whether or not the status of the arrival equals cancelled.
     *
     * @param string $status The status to check.
     *
     * @return bool True if the arrival is cancelled, or if the status has an unrecognized value.
     */
    public static function isArrivalCanceledBasedOnState(string $status): bool
    {
        if ($status == "SCHEDULED" ||
            $status == "REPORTED" ||
            $status == "PROGNOSED" ||
            $status == "CALCULATED" ||
            $status == "CORRECTED" ||
            $status == "PARTIAL_FAILURE_AT_DEP") {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Check whether or not the status of the departure equals cancelled.
     *
     * @param string $status The status to check.
     *
     * @return bool True if the departure is cancelled, or if the status has an unrecognized value.
     */
    public static function isDepartureCanceledBasedOnState(string $status): bool
    {
        if ($status == "SCHEDULED" ||
            $status == "REPORTED" ||
            $status == "PROGNOSED" ||
            $status == "CALCULATED" ||
            $status == "CORRECTED" ||
            $status == "PARTIAL_FAILURE_AT_ARR") {
            return false;
        } else {
            return true;
        }
    }

    /**
     * @param $json
     *
     * @return array
     */
    public static function parseRemarkDefinitions($json): array
    {
        if (!key_exists('remL', $json['svcResL'][0]['res']['common'])) {
            return [];
        }

        $remarkDefinitions = [];
        foreach ($json['svcResL'][0]['res']['common']['remL'] as $rawRemark) {
            /**
             *  "type": "I",
             * "code": "VIA",
             * "icoX": 5,
             * "txtN": "Opgelet: voor deze reis heb je 2 biljetten nodig.
             *          <a href=\"http:\/\/www.belgianrail.be\/nl\/klantendienst\/faq\/biljetten.aspx?cat=reisweg\">Meer info.<\/a>"
             */

            $remark = new StdClass();
            $remark->code = $rawRemark['code'];
            $remark->description = strip_tags(preg_replace(
                "/<a href=\".*?\">.*?<\/a>/",
                '',
                $rawRemark['txtN']
            ));

            $matches = [];
            preg_match_all("/<a href=\"(.*?)\">.*?<\/a>/", urldecode($rawRemark['txtN']), $matches);

            if (count($matches[1]) > 0) {
                $remark->link = urlencode($matches[1][0]);
            }

            $remarkDefinitions[] = $remark;
        }

        return $remarkDefinitions;
    }

    /**
     * Parse the list which contains information about all the alerts which are used in this API response.
     * Alerts warn about service interruptions etc.
     *
     * @param $json
     *
     * @return Alert[]
     */
    public static function parseAlertDefinitions($json): array
    {
        if (!key_exists('himL', $json['svcResL'][0]['res']['common'])) {
            return [];
        }

        $alertDefinitions = [];
        foreach ($json['svcResL'][0]['res']['common']['himL'] as $rawAlert) {
            /*
                "hid": "23499",
                "type": "LOC",
                "act": true,
                "head": "S Gravenbrakel: Wisselstoring.",
                "lead": "Wisselstoring.",
                "text": "Vertraagd verkeer.<br \/><br \/> Vertragingen tussen 5 en 10 minuten zijn mogelijk.<br \/><br \/> Dienst op enkel spoor tussen Tubeke en S Gravenbrakel.",
                "icoX": 3,
                "prio": 25,
                "prod": 1893,
                "pubChL": [
                  {
                      "name": "timetable",
                    "fDate": "20171016",
                    "fTime": "082000",
                    "tDate": "20171018",
                    "tTime": "235900"
                  }
                ]
              }*/

            $alert = new Alert();
            $alert->header = strip_tags($rawAlert['head']);
            $alert->description = strip_tags(preg_replace("/<a href=\".*?\">.*?<\/a>/", '', $rawAlert['text']));
            $alert->lead = strip_tags($rawAlert['lead']);

            preg_match_all("/<a href=\"(.*?)\">.*?<\/a>/", urldecode($rawAlert['text']), $matches);
            if (count($matches[1]) > 1) {
                $alert->link = urlencode($matches[1][0]);
            }

            if (key_exists('pubChL', $rawAlert)) {
                $alert->startTime = Tools::transformTime(
                    $rawAlert['pubChL'][0]['fTime'],
                    $rawAlert['pubChL'][0]['fDate']
                );
                $alert->endTime = Tools::transformTime(
                    $rawAlert['pubChL'][0]['tTime'],
                    $rawAlert['pubChL'][0]['tDate']
                );
            }

            $alertDefinitions[] = $alert;
        }
        return $alertDefinitions;
    }

    /**
     * Parse the list which contains information about all the alerts which are used in this API response.
     * Alerts warn about service interruptions etc.
     *
     * @param $json
     *
     * @return Alert[]
     */
    public static function parseAlerts($json): array
    {
        if (!key_exists('Messages', $json)) {
            return [];
        }

        $alertDefinitions = [];
        foreach ($json['Messages']['Message'] as $rawAlert) {
            /*
                              {
                  "affectedStops": {
                    "StopLocation": [
                      ...
                    ]
                  },
                  "validFromStop": {
                    "name": "Gent-Sint-Pieters",
                    "id": "A=1@O=Gent-Sint-Pieters@X=3710675@Y=51035897@U=80@L=8892007@",
                    "extId": "8892007",
                    "lon": 3.710675,
                    "lat": 51.035897
                  },
                  "validToStop": {
                    "name": "Antwerpen-Centraal",
                    "id": "A=1@O=Antwerpen-Centraal@X=4421102@Y=51217200@U=80@L=8821006@",
                    "extId": "8821006",
                    "lon": 4.421102,
                    "lat": 51.2172
                  },
                  "channel": [
                    ...
                  ],
                  "id": "66738",
                  "act": true,
                  "head": "Kortrijk - Deinze",
                  "lead": "We are conducting work for you between Kortrijk and Deinze.",
                  "text": "We are conducting work for you between Kortrijk and Deinze. Detailed information only available in French (FR) and in Dutch (NL).",
                  "company": "SNCB",
                  "category": "1",
                  "priority": 50,
                  "products": 57348,
                  "modTime": "11:45:57",
                  "modDate": "2022-10-17",
                  "icon": "HIM1",
                  "routeIdxFrom": 0,
                  "routeIdxTo": 14,
                  "sTime": "03:00:00",
                  "sDate": "2022-10-29",
                  "eTime": "23:59:00",
                  "eDate": "2022-11-06"
                }
              }*/

            $alert = new Alert();
            $alert->header = strip_tags($rawAlert['head']);
            $alert->description = strip_tags(preg_replace("/<a href=\".*?\">.*?<\/a>/", '', $rawAlert['text']));
            $alert->lead = strip_tags($rawAlert['lead']);

            preg_match_all("/<a href=\"(.*?)\">.*?<\/a>/", urldecode($rawAlert['text']), $matches);
            if (count($matches[1]) > 1) {
                $alert->link = urlencode($matches[1][0]);
            }
            $alert->startTime = Tools::transformTime(
                $rawAlert['sTime'],
                $rawAlert['sDate']
            );
            $alert->endTime = Tools::transformTime(
                $rawAlert['eTime'],
                $rawAlert['eDate']
            );
            $alertDefinitions[] = $alert;
        }
        return $alertDefinitions;
    }


    /**
     * @param $product
     * @return HafasVehicle
     */
    public static function parseProduct($product): HafasVehicle
    {
        $vehicle = new HafasVehicle();
        $vehicle->name = str_replace(" ", '', $product['name']);
        $vehicle->num = trim($product['num']);
        $vehicle->category = trim($product['catOutL']);
        return $vehicle;
    }
    /**
     * Parse an intermediate stop for a train on a connection. For example, if a traveller travels from
     * Brussels South to Brussels north, Brussels central would be an intermediate stop (the train stops but
     * the traveller stays on)
     * @param $lang
     * @param $rawIntermediateStop
     * @param $leg
     * @return HafasIntermediateStop The parsed intermediate stop.
     * @throws Exception
     */
    public static function parseHafasIntermediateStop($lang, $rawIntermediateStop, $leg)
    {
        $intermediateStop = new HafasIntermediateStop();
        $intermediateStop->station = StationsDatasource::getStationFromID(
            $rawIntermediateStop['extId'],
            $lang
        );

        if (key_exists('arrTime', $rawIntermediateStop)) {
            $intermediateStop->scheduledArrivalTime = Tools::transformTime(
                $rawIntermediateStop['arrTime'],
                $rawIntermediateStop['arrDate']
            );
        } else {
            $intermediateStop->scheduledArrivalTime = null;
        }

        if (key_exists('arrPrognosisType', $rawIntermediateStop)) {
            $intermediateStop->arrivalCanceled = HafasCommon::isArrivalCanceledBasedOnState($rawIntermediateStop['arrPrognosisType']);

            if ($rawIntermediateStop['arrPrognosisType'] == "REPORTED") {
                $intermediateStop->arrived = 1;
            } else {
                $intermediateStop->arrived = 0;
            }
        } else {
            $intermediateStop->arrivalCanceled = false;
            $intermediateStop->arrived = 0;
        }

        if (key_exists('rtArrTime', $rawIntermediateStop)) {
            $intermediateStop->arrivalDelay = Tools::calculateSecondsHHMMSS(
                $rawIntermediateStop['rtArrTime'],
                $rawIntermediateStop['rtArrDate'],
                $rawIntermediateStop['arrTime'],
                $rawIntermediateStop['arrDate']
            );
        } else {
            $intermediateStop->arrivalDelay = 0;
        }


        if (key_exists('depTime', $rawIntermediateStop)) {
            $intermediateStop->scheduledDepartureTime = Tools::transformTime(
                $rawIntermediateStop['depTime'],
                $rawIntermediateStop['depDate']
            );
        } else {
            $intermediateStop->scheduledDepartureTime = null;
        }

        if (key_exists('rtDepTime', $rawIntermediateStop)) {
            $intermediateStop->departureDelay = Tools::calculateSecondsHHMMSS(
                $rawIntermediateStop['rtDepTime'],
                $rawIntermediateStop['rtDepDate'],
                $rawIntermediateStop['depTime'],
                $rawIntermediateStop['depDate']
            );
        } else {
            $intermediateStop->departureDelay = 0;
        }

        if (key_exists('depPrognosisType', $rawIntermediateStop)) {
            $intermediateStop->departureCanceled =
                HafasCommon::isDepartureCanceledBasedOnState($rawIntermediateStop['depPrognosisType']);

            if ($rawIntermediateStop['depPrognosisType'] == "REPORTED") {
                $intermediateStop->left = 1;
                // A train can only leave a stop if he arrived first
                $intermediateStop->arrived = 1;
            } else {
                $intermediateStop->left = 0;
            }
        } else {
            $intermediateStop->departureCanceled = false;
            $intermediateStop->left = 0;
        }

        // Prevent null values in edge cases. If one of both values is unknown, copy the non-null value. In case both
        // are null, hope for the best
        if ($intermediateStop->scheduledDepartureTime == null) {
            $intermediateStop->scheduledDepartureTime = $intermediateStop->scheduledArrivalTime;
        }
        if ($intermediateStop->scheduledArrivalTime == null) {
            $intermediateStop->scheduledArrivalTime = $intermediateStop->scheduledDepartureTime;
        }

        // Some boolean about scheduled departure? First seen on an added stop
        // dInS, dInR, aOutS, aOutR are not processed at this moment
        if (key_exists('cancelledDeparture', $rawIntermediateStop)) {
            $intermediateStop->departureCanceled = 1;
        }

        if (key_exists('cancelledArrival', $rawIntermediateStop)) {
            $intermediateStop->arrivalCanceled = 1;
        }

        if (key_exists('additional', $rawIntermediateStop)) {
            $intermediateStop->isExtraStop = 1;
        } else {
            $intermediateStop->isExtraStop = 0;
        }

        // The last stop does not have a departure, and therefore we cannot construct a departure URI.
        if (key_exists('dProdX', $rawIntermediateStop)) {
            $intermediateStop->departureConnection = 'http://irail.be/connections/' .
                $rawIntermediateStop['extId'] . '/' .
                date('Ymd', $intermediateStop->scheduledDepartureTime) . '/' .
                str_replace(' ', '', $leg['Product']['Name']);
        }
        return $intermediateStop;
    }
}
