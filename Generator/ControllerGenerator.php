<?php

namespace Kilik\TableBundle\Generator;

use Doctrine\Common\Persistence\ObjectManager;
use Sensio\Bundle\GeneratorBundle\Generator\Generator;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * Generates a Controller in a specific bundle, with default KilikTable actions.
 */
class ControllerGenerator extends Generator
{
    /**
     * @param BundleInterface $bundle
     * @param string          $controller
     * @param string          $routeFormat
     * @param string          $templateFormat
     * @param string          $entity
     * @param ObjectManager   $manager
     * @param string          $route
     * @param string          $uri
     */
    public function generate(BundleInterface $bundle, $controller, $routeFormat, $templateFormat, $entity, $manager, $route, $uri)
    {

        // @todo WIP generate each column for each field
        $fields = [];
        foreach ($manager->getClassMetadata($entity)->getFieldNames() as $field) {
            $type = $manager->getClassMetadata($entity)->getTypeOfField($field);
            $fields[$field] = $type;
        }

        // fix slashes
        $controller = str_replace('\\', '/', $controller);
        $dir = $bundle->getPath();
        $controllerFile = $dir.'/Controller/'.$controller.'Controller.php';
        if (file_exists($controllerFile)) {
            throw new \RuntimeException(sprintf('Controller "%s" already exists', $controller));
        }
        $templateList = $dir.'/Resources/views/'.$controller.'/list.html.twig';
        $templateListAjax = $dir.'/Resources/views/'.$controller.'/_list.html.twig';

        $parameters = array(
            'namespace' => $bundle->getNamespace(),
            'bundle' => $bundle->getName(),
            'format' => array(
                'routing' => $routeFormat,
                'templating' => $templateFormat,
            ),
            'controller' => $controller,
            'entity' => $entity,
            'fields' => $fields,
            'route' => $route,
            'uri' => $uri,
        );

        $this->renderFile('controller.php.twig', $controllerFile, $parameters);
        $this->renderFile('list.html.twig.twig', $templateList, $parameters);
        $this->renderFile('_list.html.twig.twig', $templateListAjax, $parameters);
    }

    protected function parseTemplatePath($template)
    {
        $data = $this->parseLogicalTemplateName($template);

        return $data['controller'].'/'.$data['template'];
    }

    protected function parseLogicalTemplateName($logicalName, $part = '')
    {
        if (2 !== substr_count($logicalName, ':')) {
            throw new \RuntimeException(sprintf('The given template name ("%s") is not correct (it must contain two colons).', $logicalName));
        }

        $data = array();

        list($data['bundle'], $data['controller'], $data['template']) = explode(':', $logicalName);

        return $part ? $data[$part] : $data;
    }
}
