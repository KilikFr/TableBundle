<?php

namespace Kilik\TableBundle\Command;

use Kilik\TableBundle\Generator\ControllerGenerator;
use Sensio\Bundle\GeneratorBundle\Command\GeneratorCommand;
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class GenerateCommand extends GeneratorCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('kilik:table:generate')
            ->setDescription('Generate a generic controller from an entity')
            ->setDefinition(
                array(
                    new InputOption('controller', '', InputOption::VALUE_REQUIRED, 'The name of the controller to create'),
                    new InputOption('entity', '', InputOption::VALUE_REQUIRED, 'Entity name (ex: MyBundle/Entity/MyEntity'),
                    new InputOption('route', '', InputOption::VALUE_REQUIRED, 'Route name (ex: my_list)'),
                    new InputOption('uri', '', InputOption::VALUE_REQUIRED, 'Uri (ex: /list)'),
                )
            );
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();

        if ($input->isInteractive()) {
            $question = new ConfirmationQuestion($questionHelper->getQuestion('Do you confirm generation', 'yes', '?'), true);
            if (!$questionHelper->ask($input, $output, $question)) {
                $output->writeln('<error>Command aborted</error>');

                return 1;
            }
        }

        if (null === $input->getOption('controller')) {
            throw new \RuntimeException('The controller option must be provided.');
        }

        list($bundle, $controller) = $this->parseShortcutNotation($input->getOption('controller'));
        if (is_string($bundle)) {
            $bundle = Validators::validateBundleName($bundle);

            try {
                $bundle = $this->getContainer()->get('kernel')->getBundle($bundle);
            } catch (\Exception $e) {
                $output->writeln(sprintf('<bg=red>Bundle "%s" does not exist.</>', $bundle));
            }
        }

        $questionHelper->writeSection($output, 'Controller generation');

        //$routingFormat = $input->getOption('route-format');
        /** @var ControllerGenerator $generator */
        $generator = $this->getGenerator($bundle);
        $generator->setSkeletonDirs([__DIR__.'/../Resources/skeleton']);

        //$repository = $this->getContainer()->get('doctrine')->getRepository(str_replace('/', '\\', $input->getOption('entity')));

        $generator->generate(
            $bundle,
            $controller,
            'annotation',
            'twig',
            str_replace('/', '\\', $input->getOption('entity')),
            $this->getContainer()->get('doctrine')->getManager(),
            $input->getOption('route'),
            $input->getOption('uri')
        );

        $output->writeln('Generating the bundle code: <info>OK</info>');

        $questionHelper->writeGeneratorSummary($output, array());
    }

    /**
     * {@inheritdoc}
     */
    public function interact(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();
        $questionHelper->writeSection($output, 'Welcome to the KilikTable controller generator');

        // namespace
        $output->writeln(
            array(
                '',
                'A table handling is done with a Controller with 2 actions, and 2 views.',
                'This command helps you generate them easily.',
                '',
            )
        );

        $bundleNames = array_keys($this->getContainer()->get('kernel')->getBundles());

        while (true) {
            $question = new Question($questionHelper->getQuestion('Controller name', $input->getOption('controller')), $input->getOption('controller'));
            $question->setAutocompleterValues($bundleNames);
            $question->setValidator(array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateControllerName'));
            $controller = $questionHelper->ask($input, $output, $question);
            list($bundle, $controller) = $this->parseShortcutNotation($controller);

            try {
                $b = $this->getContainer()->get('kernel')->getBundle($bundle);

                $controllerPath = str_replace('\\', '/', $controller);
                if (!file_exists($b->getPath().'/Controller/'.$controllerPath.'Controller.php')) {
                    break;
                }

                $output->writeln(sprintf('<bg=red>Controller "%s:%s" already exists.</>', $bundle, $controller));
            } catch (\Exception $e) {
                $output->writeln(sprintf('<bg=red>Bundle "%s" does not exist.</>', $bundle));
            }
        }
        $input->setOption('controller', $bundle.':'.$controller);

        // entity
        $entity = (null !== $input->getOption('entity') ? $input->getOption('entity') : 'AppBundle:MyEntity');
        $output->writeln(
            array(
                '',
                'Determine the entity name.',
                'ex: AppBundle:MyEntity',
                '',
            )
        );

        $question = new Question($questionHelper->getQuestion('Entity name', $entity), $entity);
        $question->setValidator(array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateEntityName'));
        $entity = $questionHelper->ask($input, $output, $question);
        $input->setOption('entity', $entity);

        // route
        $route = (null !== $input->getOption('route') ? $input->getOption('route') : 'my_list');
        $output->writeln(
            array(
                '',
                'Determine the route name.',
                '@Route(\'....\',name="route_name")',
                '',
            )
        );

        $question = new Question($questionHelper->getQuestion('Route name (ex: my_list)', $route), $route);
        //$question->setValidator(array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateFormat'));
        $route = $questionHelper->ask($input, $output, $question);
        $input->setOption('route', $route);

        // uri
        $uri = (null !== $input->getOption('uri') ? $input->getOption('uri') : '/list');
        $output->writeln(
            array(
                '',
                'Determine the route path.',
                '@Route(\'/path\',name="...")',
                '',
            )
        );

        $question = new Question($questionHelper->getQuestion('Route path (ex: /list)', $uri), $uri);
        //$question->setValidator(array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateFormat'));
        $uri = $questionHelper->ask($input, $output, $question);
        $input->setOption('uri', $uri);
    }

    public function parseShortcutNotation($shortcut)
    {
        $entity = str_replace('/', '\\', $shortcut);

        if (false === $pos = strpos($entity, ':')) {
            throw new \InvalidArgumentException(sprintf('The controller name must contain a : ("%s" given, expecting something like AcmeBlogBundle:Post)', $entity));
        }

        return array(substr($entity, 0, $pos), substr($entity, $pos + 1));
    }

    /**
     * {@inheritdoc}
     */
    protected function createGenerator()
    {
        return new ControllerGenerator($this->getContainer()->get('filesystem'));
    }
}
