<?php

namespace Kilik\TableBundle\Services;

use Kilik\TableBundle\Components\AbstractTable;
use Kilik\TableBundle\Components\TableInterface;
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

    /**
     * Number of rows per page of the request, limited to the maximum of the table if any.
     */
    private function getRequestedRowsPerPage(TableInterface $table, Request $request): int
    {
        $rowsPerPage = (int) $this->getRequestParameter($request, 'rowsPerPage', 10);
        $maxRowsPerPage = $table instanceof AbstractTable ? $table->getMaxRowsPerPage() : null;

        return null === $maxRowsPerPage ? $rowsPerPage : max(1, min($rowsPerPage, $maxRowsPerPage));
    }
}
