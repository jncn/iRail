<?php


namespace Irail\Data\Nmbs\Models\hafas;

class HafasVehicle
{
    /**
     * @var string
     */
    public $name;
    /**
     * @var string
     */
    public $num;
    /**
     * @var string
     */
    public $category;

    public function getUri(): string
    {
        return "http://irail.be/vehicle/{$this->name}";
    }
}
