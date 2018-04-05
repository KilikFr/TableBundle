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
     * @param string          $controller (ex: Admin\User\Agency for My\AppBundle\Controller\Admin\User\AgencyController.php)
     * @param string          $routeFormat
     * @param string          $templateFormat
     * @param string          $entity
     * @param ObjectManager   $manager
     * @param string          $route
     * @param string          $uri
     */
    public function generate(BundleInterface $bundle, $controller, $routeFormat, $templateFormat, $entity, $manager, $route, $uri)
    {
        $bundlePath=$bundle->getPath();
        // full controller file path (ex src/My/AppBundle/Controller/Admin/User/AgencyController.php)
        $controllerFilePath=$bundlePath.'/Controller/'.str_replace('\\', '/', $controller).'Controller.php';
        // relative folder name (ex: Admin/User/Agency
        $controllerRelativeDir=str_replace('\\', '/', $controller);
        // controller name (ex: Agency)
        $controllerName = basename($controllerRelativeDir);
        // controller relative namespace (ex: Admin\User)
        $controllerNamespace = str_replace('/', '\\', dirname($controllerRelativeDir));

        // @todo WIP generate each column for each field
        $fields = [];
        foreach ($manager->getClassMetadata($entity)->getFieldNames() as $field) {
            $type = $manager->getClassMetadata($entity)->getTypeOfField($field);
            $fields[$field] = $type;
        }

        if (file_exists($controllerFilePath)) {
            throw new \RuntimeException(sprintf('Controller "%s" already exists', $controller));
        }
        $templateList = $bundlePath.'/Resources/views/'.$controllerRelativeDir.'/list.html.twig';
        $templateListAjax = $bundlePath.'/Resources/views/'.$controllerRelativeDir.'/_list.html.twig';

        $controllerNamespace = str_replace('/', '\\', dirname(str_replace('\\', '/', $controller)));

        $parameters = array(
            'namespace' => $bundle->getNamespace(),
            'namespace_path' => $controllerNamespace,
            'bundle' => $bundle->getName(),
            'format' => array(
                'routing' => $routeFormat,
                'templating' => $templateFormat,
            ),
            'controller' => $controllerName,
            'controller_dir' => $controllerRelativeDir,
            'entity' => $entity,
            'fields' => $fields,
            'route' => $route,
            'uri' => $uri,
        );

        $this->renderFile('controller.php.twig', $controllerFilePath, $parameters);
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
