<?php

/*
 * Mondrian
 */

namespace Trismegiste\Mondrian\Transform\Format;

/**
 * SvgExporter is an exporter to svg format
 *
 * Use "dot" from GraphViz as a renderer from dot format to svg format
 */
class Svg extends Graphviz
{

    private function checkGraphviz()
    {
        $output = shell_exec('dot -V 2>&1'); // for all platforms
        if (!preg_match('#graphviz version#', $output)) {
            throw new \RuntimeException('Graphviz is not installed on this computer');
        }
    }

    public function export()
    {
        $this->checkGraphviz();

        $descriptorspec = array(
            0 => array("pipe", "r"), // stdin is a pipe
            1 => array("pipe", "w"), // stdout is a pipe
            2 => array("pipe", "a") // stderr is also a pipe
        );

        $process = proc_open('dot -Tsvg', $descriptorspec, $pipes);

        if (is_resource($process)) {

            fwrite($pipes[0], parent::export());
            fclose($pipes[0]);

            $newFile = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            return $newFile;
        }
    }

}