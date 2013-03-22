<?php

/*
 * Mondrian
 */

namespace Trismegiste\Mondrian\Visitor;

use Trismegiste\Mondrian\Graph;
use Trismegiste\Mondrian\Utils\ReflectionTree;
use Trismegiste\Mondrian\Transform\Vertex;

/**
 * VertexCollector is a visitor to transform code into graph vertices
 */
class VertexCollector extends \PHPParser_NodeVisitor_NameResolver
{

    protected $currentClass = false;
    protected $currentMethod = false;
    protected $graph;
    protected $vertex;

    public function __construct(Graph\Graph $g, array &$v)
    {
        $this->graph = $g;
        $this->vertex = &$v;
    }

    public function enterNode(\PHPParser_Node $node)
    {
        parent::enterNode($node);

        switch ($node->getType()) {

            case 'Stmt_Class' :
                $this->currentClass = (string) $node->namespacedName;
                $this->pushClass($node);
                break;

            case 'Stmt_Interface' :
                $this->currentClass = (string) $node->namespacedName;
                $this->pushInterface($node);
                break;

            case 'Stmt_ClassMethod' :
                if ($node->isPublic()) {
                    $this->currentMethod = $node->name;
                    // only if this method is declared in this class
                    $refl = new ReflectionTree($this->currentClass);
                    $declaringClass = $refl->findFirstDeclaration($this->currentMethod);
                    // we add the vertex. If not, it will be a higher class/interface
                    // in the inheritance hierarchy which add it.
                    if ($this->currentClass == $declaringClass->name) {
                        $this->pushMethod($node);
                    }
                    // if not abstract we add the vertex containing the implementation
                    if (!$refl->isInterface() && !$node->isAbstract()) {
                        $this->pushImplementation($node);
                    }
                }
                break;
        }
    }

    public function leaveNode(\PHPParser_Node $node)
    {
        if ($node->getType() == 'Stmt_Class') {
            $this->currentClass = false;
        }
        if ($node->getType() == 'Stmt_ClassMethod') {
            $this->currentMethod = false;
        }
    }

    protected function pushClass(\PHPParser_Node_Stmt_Class $node)
    {
        $index = (string) $node->namespacedName;
        if (!array_key_exists($index, $this->vertex['class'])) {
            $v = new Vertex\ClassVertex($index);
            $this->graph->addVertex($v);
            $this->vertex['class'][$index] = $v;
        }
    }

    protected function pushInterface(\PHPParser_Node_Stmt_Interface $node)
    {
        $index = (string) $node->namespacedName;
        if (!array_key_exists($index, $this->vertex['interface'])) {
            $v = new Vertex\InterfaceVertex($index);
            $this->graph->addVertex($v);
            $this->vertex['interface'][$index] = $v;
        }
    }

    protected function pushMethod(\PHPParser_Node_Stmt_ClassMethod $node)
    {
        $index = $this->getCurrentMethodIndex();
        if (!array_key_exists($index, $this->vertex['method'])) {
            $v = new Vertex\MethodVertex($index);
            $this->graph->addVertex($v);
            $this->vertex['method'][$index] = $v;
            foreach ($node->params as $order => $aParam) {
                $this->pushParameter($index, $order);
            }
        }
    }

    protected function pushImplementation(\PHPParser_Node_Stmt_ClassMethod $node)
    {
        $index = $this->getCurrentMethodIndex();
        if (!array_key_exists($index, $this->vertex['impl'])) {
            $v = new Vertex\ImplVertex($index);
            $this->graph->addVertex($v);
            $this->vertex['impl'][$index] = $v;
        }
    }

    protected function getCurrentMethodIndex()
    {
        return $this->currentClass . '::' . $this->currentMethod;
    }

    protected function pushParameter($methodName, $order)
    {
        $index = $methodName . '/' . $order;
        if (!array_key_exists($index, $this->vertex['param'])) {
            $v = new Vertex\ParamVertex($index);
            $this->graph->addVertex($v);
            $this->vertex['param'][$index] = $v;
        }
    }

}