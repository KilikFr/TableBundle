<?php

namespace Kilik\TableBundle\Services;

use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
trait RequestParameterTrait
{
    /**
     * Gets a request parameter the way Request::get() did before its removal in Symfony 8.0.
     *
     * The request attributes, then the query string, then the body are searched: the first one containing the key
     * wins, even with a null value, and arrays are returned as is.
     */
    private function getRequestParameter(Request $request, string $key, $default = null)
    {
        foreach ([$request->attributes, $request->query, $request->request] as $parameters) {
            if ($parameters->has($key)) {
                return $parameters->all()[$key];
            }
        }

        return $default;
    }
}
