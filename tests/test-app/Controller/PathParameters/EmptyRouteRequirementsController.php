<?php

namespace TestApp\Controller\PathParameters;

use Symfony\Component\Routing\Annotation\Route;
use RestApiBundle\Mapping\OpenApi as Docs;

class EmptyRouteRequirementsController
{
    /**
     * @Docs\Endpoint(title="Title", tags={"tag"})
     *
     * @Route("/{unknown_parameter}", methods="GET")
     *
     * @return null
     */
    public function testAction()
    {
        return null;
    }
}
