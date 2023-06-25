<?php

/* Copyright (C) 2011 by iRail vzw/asbl */

namespace Irail\Legacy\Output;

/**
 * Prints the Xml style output.
 */
class Xml extends Printer
{
    private $ATTRIBUTES = [
        'id',
        '@id',
        'locationX',
        'locationY',
        'standardname',
        'left',
        'arrived',
        'delay',
        'canceled',
        'partiallyCanceled',
        'normal',
        'shortname',
        'walking',
        'isExtraStop',
        'isExtra',
        'hafasId',
        'type', // Vehicle type
        'number' // Vehicle number
    ];
    private $rootname;

    // make a stack of array information, always work on the last one
    // for nested array support
    private $stack = [];
    private $arrayindices = [];
    private $currentarrayindex = -1;

    public function getHeaders(): array
    {
        return ['Access-Control-Allow-Origin: *', 'Content-Type: application/xml;charset=UTF-8'];
    }

    /**
     * @param $ec
     * @param $msg
     * @return mixed|void
     */
    public function getError($ec, $msg): string
    {
        return "<error code=\"$ec\">$msg</error>";
    }

    /**
     * @param $name
     * @param $version
     * @param $timestamp
     * @return mixed|void
     */
    public function startRootElement($name, $version, $timestamp): string
    {
        $this->rootname = $name;
        echo "<$name version=\"$version\" timestamp=\"$timestamp\">";
    }

    public function startArray($name, $number, $root = false): string
    {
        if (!$root || $this->rootname == 'liveboard' || $this->rootname == 'vehicleinformation') {
            $result = '<' . $name . "s number=\"$number\">";
        }

        $this->currentarrayindex++;
        $this->arrayindices[$this->currentarrayindex] = 0;
        $this->stack[$this->currentarrayindex] = $name;
        return $result;
    }

    public function nextArrayElement(): string
    {
        $this->arrayindices[$this->currentarrayindex]++;
        return "";
    }

    /**
     * @param $name
     * @param $object
     * @return mixed|void
     */
    public function startObject($name, $object): string
    {
        $result = "<$name";

        // Test whether this object is a first-level array object
        if ($this->currentarrayindex > -1 && $this->stack[$this->currentarrayindex] == $name && $name != 'station') {
            $result .= ' id="' . $this->arrayindices[$this->currentarrayindex] . '"';
        }

        // fallback for attributes and name tag
        $hash = get_object_vars($object);
        $named = '';

        foreach ($hash as $elementkey => $elementval) {
            if (in_array($elementkey, $this->ATTRIBUTES)) {
                if ($elementkey == '@id') {
                    $elementkey = 'URI';
                }
                if ($elementkey == 'normal' || $elementkey == 'canceled') {
                    $elementval = intval($elementval);
                }
                $result .= " $elementkey=\"$elementval\"";
            } else if ($elementkey == 'name') {
                $named = $elementval;
            }
        }

        $result .= '>';

        if ($named != '') {
            $result .= $named;
        }
        return $result;
    }

    /**
     * @param $key
     * @param $val
     * @return mixed|void
     */
    public function startKeyVal($key, $val): string
    {
        $result = "";
        if ($key == 'time' || $key == 'startTime' || $key == 'endTime' || $key == 'departureTime' || $key == 'arrivalTime' || $key == 'scheduledDepartureTime' || $key == 'scheduledArrivalTime') {
            $form = $this->iso8601($val);
            $result .= "<$key formatted=\"$form\">$val";
        } else if ($key != 'name' && !in_array($key, $this->ATTRIBUTES)) {
            $result .= "<$key>";
            if ($key == 'header' || $key == 'title' || $key == 'description' || $key == 'richtext' || $key == 'link') {
                echo "<![CDATA[";
            }
            $result .= $val;
        }
        return $result;
    }

    /**
     * @param $key
     * @return mixed|void
     */
    public function endElement($key): string
    {
        $result = "";
        if ($key == 'header' || $key == 'title' || $key == 'description' || $key == 'richtext' || $key == 'link') {
            $result .= ']]>';
        }

        if (!in_array($key, $this->ATTRIBUTES) && $key != 'name') {
            $result .= "</$key>";
        }
        return $result;
    }

    /**
     * @param      $name
     * @param bool $root
     * @return mixed|void
     */
    public function endArray($name, $root = false): string
    {
        if (!$root || $this->rootname == 'liveboard' || $this->rootname == 'vehicleinformation') {
            $result = '</' . $name . 's>';
        }
        $this->stack[$this->currentarrayindex] = '';
        $this->arrayindices[$this->currentarrayindex] = 0;
        $this->currentarrayindex--;
        return $result;
    }

    /**
     * @param $name
     * @return mixed|void
     */
    public function endRootElement($name): string
    {
        return "</$name>";
    }

    /**
     * @param $unixtime
     * @return bool|string
     */
    public function iso8601($unixtime)
    {
        return date("Y-m-d\TH:i:s", $unixtime);
    }
}

;
