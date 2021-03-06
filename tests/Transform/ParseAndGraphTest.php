<?php

/*
 * Mondrian
 */

namespace Trismegiste\Mondrian\Tests\Transform;

use Trismegiste\Mondrian\Graph\Digraph;
use Trismegiste\Mondrian\Graph\Graph;
use Trismegiste\Mondrian\Builder\Linking;
use Trismegiste\Mondrian\Transform\GraphBuilder;
use Trismegiste\Mondrian\Builder\Statement\Builder;
use Trismegiste\Mondrian\Transform\Logger\NullLogger;
use Trismegiste\Mondrian\Tests\Fixtures\MockSplFileInfo;

/**
 * ParseAndGraphTest tests for Grapher
 */
class ParseAndGraphTest extends \PHPUnit_Framework_TestCase
{

    protected $compiler;
    protected $graph;

    protected function setUp()
    {
        $conf = array('calling' => array());

        $this->graph = new Digraph();
        $this->compiler = new Linking(
                new Builder(), new GraphBuilder($conf, $this->graph, new NullLogger()));
    }

    protected function callParse()
    {
        $iter = array();
        foreach (func_get_args() as $name) {
            $mockedFile = new MockSplFileInfo($name, file_get_contents(__DIR__ . '/../Fixtures/Project/' . $name));
            $iter[] = $mockedFile;
        }

        $this->compiler->run(new \ArrayIterator($iter));

        return $this->graph;
    }

    public function testOneClass()
    {
        $result = $this->callParse('OneClass.php');
        $v = $result->getVertexSet();
        $this->assertCount(1, $v);
        $this->assertEquals('Project\OneClass', $v[0]->getName());
        $this->assertCount(0, $result->getEdgeSet());
    }

    public function getSimpleGraph()
    {
        return array(
            array('Inheritance.php', 4, 3),
            array('Interface.php', 4, 3),
            array('Concrete.php', 3, 3),
            array('OutsideEdge.php', 4, 5),
            array('OutsideSignature.php', 2, 2),
            array('StaticCalling.php', 6, 7),
            ['SimpleTrait.php', 2, 2]
        );
    }

    /**
     * @dataProvider getSimpleGraph
     */
    public function testSimpleGraph($oneFile, $vCard, $eCard)
    {
        $result = $this->callParse($oneFile);
        $this->assertCount($vCard, $result->getVertexSet());
        $this->assertCount($eCard, $result->getEdgeSet());

        return $result;
    }

    public function testDecoupleMethod()
    {
        $result = $this->callParse('NotConcrete.php', 'Contract.php');
        $this->assertCount(4, $result->getVertexSet());
        $this->assertCount(4, $result->getEdgeSet());
    }

    public function testDecoupleMethodParam()
    {
        $fqcnClass = 'Project\NotConcreteParam';
        $fqcnInterface = 'Project\ContractParam';
        $result = $this->callParse('NotConcreteParam.php', 'ContractParam.php');
        $this->assertCount(5, $result->getVertexSet());
        $this->assertEdges(array(
            array(
                array('Class', $fqcnClass),
                array('Interface', $fqcnInterface)
            ),
            array(
                array('Class', $fqcnClass),
                array('Impl', "$fqcnClass::setter")
            ),
            array(
                array('Impl', "$fqcnClass::setter"),
                array('Class', $fqcnClass)
            ),
            array(
                array('Interface', $fqcnInterface),
                array('Method', "$fqcnInterface::setter")
            ),
            array(
                array('Method', "$fqcnInterface::setter"),
                array('Param', "$fqcnInterface::setter/0")
            ),
            array(
                array('Impl', "$fqcnClass::setter"),
                array('Param', "$fqcnInterface::setter/0")
            )
                )
                , $result);
    }

    protected function findVertex(Graph $g, $type, $name)
    {
        $nsVertex = 'Trismegiste\Mondrian\Transform\Vertex\\';
        foreach ($g->getVertexSet() as $vertex) {
            if ((get_class($vertex) == $nsVertex . $type) && ($vertex->getName() == $name)) {
                return $vertex;
            }
        }
        return null;
    }

    protected function assertEdges(array $search, Graph $g)
    {
        $edge = $g->getEdgeSet();
        $this->assertCount(count($search), $edge);
        foreach ($search as $item) {
            $src = $this->findVertex($g, $item[0][0] . 'Vertex', $item[0][1]);
            $this->assertNotNull($src, $item[0][0]);
            $dst = $this->findVertex($g, $item[1][0] . 'Vertex', $item[1][1]);
            $this->assertNotNull($dst, $item[1][0]);
            $e = $g->searchEdge($src, $dst);
            $this->assertNotNull($e, "{$item[0][1]} -> {$item[1][1]}");
        }
    }

    public function testExternalInterfaceInheritance()
    {
        $result = $this->testSimpleGraph('InheritExtra.php', 2, 2);
        $this->assertNotNull(
                $this->findVertex(
                        $result, "ClassVertex", 'Project\InheritExtra'));
        $this->assertNotNull(
                $this->findVertex(
                        $result, "ImplVertex", 'Project\InheritExtra::getIterator'));
    }

    public function testDecoupledMethodWithTypedParam()
    {
        $fqcnClass = 'Project\NotConcreteTypedParam';
        $fqcnInterface = 'Project\ContractTypedParam';
        $fqcnOtherInterface = 'Project\Contract';
        $result = $this->callParse('NotConcreteTypedParam.php', 'ContractTypedParam.php', 'Contract.php');
        $this->assertCount(7, $result->getVertexSet());
        $this->assertEdges(array(
            array(
                array('Class', $fqcnClass),
                array('Interface', $fqcnInterface)
            ),
            array(
                array('Class', $fqcnClass),
                array('Impl', "$fqcnClass::setter")
            ),
            array(
                array('Impl', "$fqcnClass::setter"),
                array('Class', $fqcnClass)
            ),
            array(
                array('Interface', $fqcnInterface),
                array('Method', "$fqcnInterface::setter")
            ),
            array(
                array('Method', "$fqcnInterface::setter"),
                array('Param', "$fqcnInterface::setter/0")
            ),
            array(
                array('Impl', "$fqcnClass::setter"),
                array('Param', "$fqcnInterface::setter/0")
            ),
            array(
                array('Param', "$fqcnInterface::setter/0"),
                array('Interface', $fqcnOtherInterface)
            ),
            array(
                array('Interface', $fqcnOtherInterface),
                array('Method', "$fqcnOtherInterface::simple")
            )
                )
                , $result);
    }

    public function testCalling()
    {
        $result = $this->callParse('Calling.php', 'Concrete.php');
        $this->assertCount(8, $result->getVertexSet());
        $this->assertCount(10, $result->getEdgeSet());
        $impl = $this->findVertex($result, 'ImplVertex', 'Project\Calling::simpleCall');
        $this->assertNotNull($impl);
        $calledMethod = $this->findVertex($result, 'MethodVertex', 'Project\Concrete::simple');
        $this->assertNotNull($calledMethod);
        $link = $result->searchEdge($impl, $calledMethod);
        $this->assertNotNull($link);
    }

    public function testNewInstance()
    {
        $result = $this->callParse('NewInstance.php', 'Concrete.php');
        $this->assertCount(8, $result->getVertexSet());
        $this->assertCount(10, $result->getEdgeSet());
        $impl = $this->findVertex($result, 'ImplVertex', 'Project\NewInstance::simpleNew');
        $this->assertNotNull($impl);
        $classVertex = $this->findVertex($result, 'ClassVertex', 'Project\Concrete');
        $this->assertNotNull($classVertex);
        $link = $result->searchEdge($impl, $classVertex);
        $this->assertNotNull($link);
    }

    public function testFilteringObviousMethodCall()
    {
        $result = $this->testSimpleGraph('FilterCalling.php', 13, 17);
        $impl = $this->findVertex($result, 'ImplVertex', 'Project\FilterCalling::decorate');
        $this->assertNotNull($impl);
        $succ = $result->getSuccessor($impl);
        $this->assertCount(3, $succ); // the class, the param and one call (not two)
    }

    public function testFilteringMethodCallSuper()
    {
        $result = $this->testSimpleGraph('FilterCallingSuper.php', 13, 17);
        $impl = $this->findVertex($result, 'ImplVertex', 'Project\FilterCalling::decorate');
        $this->assertNotNull($impl);
        $succ = $result->getSuccessor($impl);
        $this->assertCount(3, $succ); // the class, the param and one call (not two)
    }

    public function testNotFilteringOnBadMethodCall()
    {
        $result = $this->testSimpleGraph('FilterCallingBad.php', 11, 15);
        $impl = $this->findVertex($result, 'ImplVertex', 'Project\FilterCallingBad::decorate');
        $this->assertNotNull($impl);
        $succ = $result->getSuccessor($impl);
        $this->assertCount(4, $succ); // the class, the param and two call (not one)
    }

    public function testTypeNotFoundFilteringOnCall()
    {
        $result = $this->testSimpleGraph('FilterCallingUnknown.php', 7, 9);
        $impl = $this->findVertex($result, 'ImplVertex', 'Project\FilterCallingUnknown::decorate');
        $this->assertNotNull($impl);
        $succ = $result->getSuccessor($impl);
        $this->assertCount(3, $succ); // the class, the param and one call (fallback)
    }

    public function testNoFilteringMethodCallOnOuterClass()
    {
        $result = $this->testSimpleGraph('FilterOuterCalling.php', 10, 13);
        $impl = $this->findVertex($result, 'ImplVertex', 'Project\FilterCalling::decorate');
        $this->assertNotNull($impl);
        $succ = $result->getSuccessor($impl);
        $this->assertCount(2, $succ); // the class, the param and no call
        // (there is no signature to call since it's an outer class)
    }

    public function testFilteringCallWithFineTuning()
    {
        $conf = array(
            'calling' => array(
                'Project\FilterCalling::decorate2' => array(
                    'ignore' => array(
                        'Project\OtherClass::getTitle'
                    )
                )
            )
        );

        $this->compiler = new Linking(
                new Builder(), new GraphBuilder($conf, $this->graph, new NullLogger()));

        $result = $this->testSimpleGraph('FilterIgnoreCallTo.php', 11, 15);
        $impl = $this->findVertex($result, 'ImplVertex', 'Project\FilterCalling::decorate3');
        $this->assertNotNull($impl);
        $succ = $result->getSuccessor($impl);
        $this->assertCount(3, $succ); // the class and two calls
        $impl = $this->findVertex($result, 'ImplVertex', 'Project\FilterCalling::decorate2');
        $this->assertNotNull($impl);
        $succ = $result->getSuccessor($impl);
        $this->assertCount(2, $succ); // the class and one call (not two)
    }

    public function testTraitWithoutInterface()
    {
        $fqcnClass = 'Project\ServiceWrong';
        $fqcnTrait = 'Project\ServiceTrait';
        $result = $this->callParse('ServiceTrait.php', 'ServiceWrong.php');
        $this->assertCount(4, $result->getVertexSet());
        $this->assertEdges(array(
            array(
                array('Class', $fqcnClass),
                array('Trait', $fqcnTrait)
            ),
            array(
                array('Class', $fqcnClass),
                array('Method', "$fqcnClass::someService")
            ),
            array(
                array('Impl', "$fqcnTrait::someService"),
                array('Trait', $fqcnTrait)
            ),
            array(
                array('Trait', $fqcnTrait),
                array('Impl', "$fqcnTrait::someService")
            )
                )
                , $result);
    }

    public function testTraitWithInterface()
    {
        $fqcnClass = 'Project\ServiceRight';
        $fqcnTrait = 'Project\ServiceTrait';
        $fqcnInterface = 'Project\ServiceInterface';

        $result = $this->callParse('ServiceTrait.php', 'ServiceRight.php', 'ServiceInterface.php');
        $this->assertCount(5, $result->getVertexSet());
        $this->assertEdges(array(
            array(
                array('Class', $fqcnClass),
                array('Trait', $fqcnTrait)
            ),
            array(
                array('Class', $fqcnClass),
                array('Interface', $fqcnInterface)
            ),
            array(
                array('Interface', $fqcnInterface),
                array('Method', "$fqcnInterface::someService")
            ),
            array(
                array('Impl', "$fqcnTrait::someService"),
                array('Trait', $fqcnTrait)
            ),
            array(
                array('Trait', $fqcnTrait),
                array('Impl', "$fqcnTrait::someService")
            )
                )
                , $result);
    }

    /**
     * Test edge between 2 traits
     */
    public function testTraitUsingTrait()
    {
        $fqcnUser = 'Project\ServiceUsingTrait';
        $fqcnTrait = 'Project\ServiceTrait';
        $result = $this->callParse('ServiceTrait.php', 'ServiceUsingTrait.php');
        $this->assertCount(3, $result->getVertexSet());
        $this->assertEdges(array(
            array(
                array('Trait', $fqcnUser),
                array('Trait', $fqcnTrait)
            ),
            array(
                array('Impl', "$fqcnTrait::someService"),
                array('Trait', $fqcnTrait)
            ),
            array(
                array('Trait', $fqcnTrait),
                array('Impl', "$fqcnTrait::someService")
            )
                )
                , $result);
    }

    public function testInternalForTrait()
    {
        $result = $this->callParse('TraitInternals.php');
        $this->assertCount(5 + 2 * 3 + 1, $result->getVertexSet());

        $fqcn = 'Project\TraitInternals';
        $call = 'Project\TraitConfig';
        $helper = 'Project\TraitHelper';
        $instance = 'Project\TraitDocument';
        $this->assertEdges([
            // the trait
            [['Trait', $fqcn], ['Impl', $fqcn . '::nonDynCall']],
            [['Impl', $fqcn . '::nonDynCall'], ['Trait', $fqcn]],
            [['Trait', $fqcn], ['Impl', $fqcn . '::staticCall']],
            [['Impl', $fqcn . '::staticCall'], ['Trait', $fqcn]],
            [['Trait', $fqcn], ['Impl', $fqcn . '::newInstance']],
            [['Impl', $fqcn . '::newInstance'], ['Trait', $fqcn]],
            // the param in the trait
            [['Impl', $fqcn . '::nonDynCall'], ['Param', $fqcn . '::nonDynCall/0']],
            [['Param', $fqcn . '::nonDynCall/0'], ['Class', $call]],
            // the called class
            [['Class', $call], ['Method', "$call::calling"]],
            [['Method', "$call::calling"], ['Impl', "$call::calling"]],
            [['Impl', "$call::calling"], ['Class', $call]],
            // the helper class
            [['Class', $helper], ['Method', "$helper::simple"]],
            [['Method', "$helper::simple"], ['Impl', "$helper::simple"]],
            [['Impl', "$helper::simple"], ['Class', $helper]],
            // the edge between for the non-static method call
            [['Impl', $fqcn . '::nonDynCall'], ['Method', "$call::calling"]],
            // the edge for static call
            [['Impl', $fqcn . '::staticCall'], ['Method', "$helper::simple"]],
            // the edge for instantiation
            [['Impl', $fqcn . '::newInstance'], ['Class', $instance]]
                ], $result);
    }

}
