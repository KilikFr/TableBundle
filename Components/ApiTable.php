<?php

namespace Kilik\TableBundle\Components;

use Kilik\TableBundle\Api\ApiInterface;

class ApiTable extends AbstractTable
{
    /**
     * @var ApiInterface
     */
    private $api;

    /**
     * @param ApiInterface $api
     *
     * @return static
     */
    public function setApi(ApiInterface $api)
    {
        $this->api = $api;

        return $this;
    }

    /**
     * Get API.
     *
     * @return ApiInterface
     */
    public function getApi()
    {
        return $this->api;
    }
}
