<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use PDepend\TextUI\Runner;
use PDepend\Util\Cache\CacheFactory;
use PDepend\Engine;
use PDepend\Metrics\AnalyzerFactory;
use PDepend\Util\Configuration;
use PDepend\TextUI\ResultPrinter;
use PDepend\Report\Summary\Xml as SummaryXml;
use PDepend\Report\Dependencies\Xml as DependenciesXml;
use PDepend\Report\Jdepend\Xml as JdependXml;
use PDepend\Report\Jdepend\Chart as JdependChart;
use PDepend\Report\Overview\Pyramid as OverviewPyramid;
use PDepend\Report\ReportGeneratorFactory;
use PDepend\Metrics\Analyzer\ClassLevelAnalyzer;
use PDepend\Metrics\Analyzer\CodeRankAnalyzer;
use PDepend\Metrics\Analyzer\CohesionAnalyzer;
use PDepend\Metrics\Analyzer\CouplingAnalyzer;
use PDepend\Metrics\Analyzer\CrapIndexAnalyzer;
use PDepend\Metrics\Analyzer\CyclomaticComplexityAnalyzer;
use PDepend\Metrics\Analyzer\DependencyAnalyzer;
use PDepend\Metrics\Analyzer\ClassDependencyAnalyzer;
use PDepend\Metrics\Analyzer\HierarchyAnalyzer;
use PDepend\Metrics\Analyzer\InheritanceAnalyzer;
use PDepend\Metrics\Analyzer\NPathComplexityAnalyzer;
use PDepend\Metrics\Analyzer\NodeCountAnalyzer;
use PDepend\Metrics\Analyzer\NodeLocAnalyzer;
use PDepend\Metrics\Analyzer\HalsteadAnalyzer;
use PDepend\Metrics\Analyzer\MaintainabilityIndexAnalyzer;

return function (ContainerConfigurator $configurator) {
    $services = $configurator->services()
        ->defaults()
            ->autowire(false)
            ->autoconfigure(false);

    // Core services
    $services->set('pdepend.configuration', Configuration::class)
        ->public();

    $services->set('pdepend.util.cache_factory', CacheFactory::class)
        ->args([new Reference('pdepend.configuration')]);

    $services->set('pdepend.analyzer_factory', AnalyzerFactory::class)
        ->args([new Reference('service_container')])
        ->public();

    $services->set('pdepend.engine', Engine::class)
        ->args([
            new Reference('pdepend.configuration'),
            new Reference('pdepend.util.cache_factory'),
            new Reference('pdepend.analyzer_factory'),
        ])
        ->public();

    $services->set('pdepend.report_generator_factory', ReportGeneratorFactory::class)
        ->args([new Reference('service_container')])
        ->public();

    $services->set('pdepend.textui.runner', Runner::class)
        ->args([
            new Reference('pdepend.report_generator_factory'),
            new Reference('pdepend.engine')
        ])
        ->public();

    $services->set('pdepend.textui.result_printer', ResultPrinter::class);

    // Reports
    $services->set('pdepend.report.summary.xml', SummaryXml::class)
        ->tag('pdepend.logger', [
            'option' => '--summary-xml',
            'message' => 'Generates a xml log with all metrics.'
        ])
        ->public();

    $services->set('pdepend.report.dependencies.xml', DependenciesXml::class)
        ->tag('pdepend.logger', [
            'option' => '--dependency-xml',
            'message' => 'Generates a xml log with all dependencies.'
        ])
        ->public();

    $services->set('pdepend.report.jdepend.xml', JdependXml::class)
        ->tag('pdepend.logger', [
            'option' => '--jdepend-xml',
            'message' => 'Generates the package dependency log.'
        ])
        ->public();

    $services->set('pdepend.report.jdepend.chart', JdependChart::class)
        ->tag('pdepend.logger', [
            'option' => '--jdepend-chart',
            'message' => 'Generates a diagram of the analyzed packages.'
        ])
        ->public();

    $services->set('pdepend.report.overview.pyramid', OverviewPyramid::class)
        ->tag('pdepend.logger', [
            'option' => '--overview-pyramid',
            'message' => 'Generates a chart with an Overview Pyramid for the analyzed project.'
        ])
        ->public();

    // Analyzers
    $services->set('pdepend.analyzer.class_level', ClassLevelAnalyzer::class)
        ->public()
        ->tag('pdepend.analyzer')
        ->call('addAnalyzer', [new Reference('pdepend.analyzer.cyclomatic_complexity')]);

    $services->set('pdepend.analyzer.code_rank', CodeRankAnalyzer::class)
        ->public()
        ->tag('pdepend.analyzer', [
            'option' => '--coderank-mode',
            'message' => "Used CodeRank strategies. Comma separated list of 'inheritance'(default), 'property' and 'method'.",
            'value' => '*[,...]'
        ]);

    $services->set('pdepend.analyzer.cohesion', CohesionAnalyzer::class)->public()->tag('pdepend.analyzer');
    $services->set('pdepend.analyzer.coupling', CouplingAnalyzer::class)->public()->tag('pdepend.analyzer');

    $services->set('pdepend.analyzer.crap_index', CrapIndexAnalyzer::class)
        ->public()
        ->tag('pdepend.analyzer', [
            'option' => '--coverage-report',
            'message' => "Clover style CodeCoverage report, as produced by PHPUnit's --coverage-clover option."
        ])
        ->call('addAnalyzer', [new Reference('pdepend.analyzer.cyclomatic_complexity')]);

    $services->set('pdepend.analyzer.cyclomatic_complexity', CyclomaticComplexityAnalyzer::class)->public()->tag('pdepend.analyzer');
    $services->set('pdepend.analyzer.dependency', DependencyAnalyzer::class)->public()->tag('pdepend.analyzer');
    $services->set('pdepend.analyzer.class_dependency', ClassDependencyAnalyzer::class)->public()->tag('pdepend.analyzer');
    $services->set('pdepend.analyzer.hierarchy', HierarchyAnalyzer::class)->public()->tag('pdepend.analyzer');
    $services->set('pdepend.analyzer.inheritance', InheritanceAnalyzer::class)->public()->tag('pdepend.analyzer');
    $services->set('pdepend.analyzer.npath_complexity', NPathComplexityAnalyzer::class)->public()->tag('pdepend.analyzer');
    $services->set('pdepend.analyzer.node_count', NodeCountAnalyzer::class)->public()->tag('pdepend.analyzer');
    $services->set('pdepend.analyzer.node_loc', NodeLocAnalyzer::class)->public()->tag('pdepend.analyzer');
    $services->set('pdepend.analyzer.halstead', HalsteadAnalyzer::class)->public()->tag('pdepend.analyzer');
    $services->set('pdepend.analyzer.maintainability', MaintainabilityIndexAnalyzer::class)->public()->tag('pdepend.analyzer');
};
