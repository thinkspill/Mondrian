<?php

/*
 * Mondrian
 */

namespace Trismegiste\Mondrian\Analysis;

use Trismegiste\Mondrian\Graph\Algorithm;
use Trismegiste\Mondrian\Graph\Vertex;

/**
 * CodeMetrics analyses a graph and counts number of vertices per type
 * Design Pattern : Decorator
 *
 * Metrics are usefull to fast evaluate what kind of project you have
 * to refactor. But it is not a guide where you have to go. Of course
 * a project with a 50/50 ratio in interfaces/classes can be a good thing
 * but if classes are used in parameters of methods instead of interfaces,
 * interfaces are not really usefull. It's easy to fake good metrics.
 *
 * This analyser also counts where methods are declared first in the
 * inheritance tree. A good point can be that you have low count of
 * method first declared in class. This can mean you can decouple your
 * concrete classes (remember LSP)
 *
 * From my experience, it's better to have dirty code in loosely coupled
 * classes than beautiful code in highly coupled classes, because your beautiful
 * code does not stand a chance against the entropy of changing.
 *
 * Dirty code can be refactored, even in paralell process, if you have loosely
 * coupling.
 *
 * In short : Bad coding practices has bad metrics
 * but good metrics does not means good coding practices.
 * That's why I didn't push too far these statistics.
 *
 * See the others tool to find out where the coupling is.
 */
class CodeMetrics extends Algorithm
{

    /**
     * Extract the class name of a vertex to get a printable result
     *
     * @param Vertex $v
     * @return string
     */
    private function extractShortName(Vertex $v)
    {
        $result = 'Unknown';
        if (preg_match('#([^\\\\]+)Vertex$#', get_class($v), $match)) {
            $result = $match[1];
        }

        return $result;
    }

    /**
     * Makes the statistics on the code
     *
     * @return array hashmap of stat
     */
    public function getCardinal()
    {
        $card = array(
            'Class' => 0,
            'Interface' => 0,
            'Trait' => 0,
            'Impl' => 0,
            'Method' => 0,
            'Param' => 0,
            'MethodDeclaration' => array('Class' => 0, 'Interface' => 0, 'Trait' => 0)
        );
        $vertex = $this->graph->getVertexSet();
        foreach ($vertex as $v) {
            $type = $this->extractShortName($v);
            $card[$type]++;
            if (in_array($type, ['Class', 'Interface', 'Trait'])) {
                foreach ($this->graph->getSuccessor($v) as $succ) {
                    $succType = $this->extractShortName($succ);
                    if ($succType == 'Method') {
                        $card['MethodDeclaration'][$type]++;
                    }
                }
            }
        }

        return $card;
    }

}
