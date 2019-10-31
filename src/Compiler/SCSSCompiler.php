<?php
/**
 * BSD 3-Clause License
 *
 * Copyright (c) 2019, TASoft Applications
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *  Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 *
 *  Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 *  Neither the name of the copyright holder nor the names of its
 *   contributors may be used to endorse or promote products derived from
 *   this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace Skyline\Component\SCSS\Compiler;

use Leafo\ScssPhp\Compiler;
use Leafo\ScssPhp\Exception\CompilerException;
use Skyline\Compiler\AbstractCompiler;
use Skyline\Compiler\CompilerConfiguration;
use Skyline\Compiler\CompilerContext;
use Skyline\Component\SCSS\SCSSComponent;

class SCSSCompiler extends AbstractCompiler
{
    private $dirname;
    private $cacheDirectory;
    /** @var Compiler  */
    private $scssCompiler;

    /**
     * SCSSCompiler constructor.
     * @param string $compilerID
     * @param $cacheDirectoryName
     */
    public function __construct(string $compilerID, $cacheDirectoryName)
    {
        parent::__construct($compilerID);
        $this->dirname = $cacheDirectoryName;

        $this->scssCompiler = new Compiler();
    }

    public function compile(CompilerContext $context)
    {
        $this->cacheDirectory = realpath($context->getSkylineAppDataDirectory() . DIRECTORY_SEPARATOR . $this->dirname);



        foreach($context->getSourceCodeManager()->yieldSourceFiles('/^components\.cfg\.php/i') as $component) {
            $this->analyzeContents(require $component);
        }

        $dir = $context->getSkylineAppDirectory( CompilerConfiguration::SKYLINE_DIR_CONFIG );
        if(is_file( $f = $dir . DIRECTORY_SEPARATOR . "components.config.php" ))
            $this->analyzeContents( require $f );
    }

    private function analyzeContents($content) {
        foreach($content as $componentName => $elements) {
            foreach($elements as $elementName => $element) {
                if($element instanceof SCSSComponent) {
                    $this->compileComponent($componentName, $elementName, $element);
                }
            }
        }
    }

    private function compileComponent($componentName, $elementName, SCSSComponent $component) {
        $options = $component->getOptions();
        $file = $options[ SCSSComponent::OPTION_INPUT_FILE ] ?? NULL;
        if(!$file) {
            trigger_error("SCSS compiler: no input file for component $componentName", E_USER_WARNING);
        }

        $content = file_get_contents($file);

        $this->scssCompiler->setFormatter( $options[ SCSSComponent::OPTION_OUTPUT_FORMAT ] );
        try {
            $this->scssCompiler->setImportPaths([]);
            $this->scssCompiler->addImportPath( dirname($file) );

            $namespaces = [];
            foreach(($options[ SCSSComponent::OPTION_LIBRARY_MAPPING ] ?? []) as $mapping => $directory) {
                if(is_numeric($mapping))
                    $this->scssCompiler->addImportPath( $directory );
                else
                    $namespaces[$mapping] = $directory;
            }

            $this->scssCompiler->addImportPath(function($path) use ($namespaces, $file) {
                $mx = explode(":", $path);
                $filename = array_pop($mx);
                $namespace = array_pop($mx);

                if($namespace) {
                    if($lib = $namespaces[ $namespace ] ?? NULL) {
                        $file = realpath($lib . DIRECTORY_SEPARATOR . $filename . ".scss");
                        if(!$file)
                            $file = realpath($lib . DIRECTORY_SEPARATOR . $filename . ".css");

                        if(!$file)
                            trigger_error("SCSS: @import '$path': Library component $filename not found", E_USER_WARNING);

                        return $file;
                    } else
                        trigger_error("SCSS: @import '$path': Library $namespace not found", E_USER_WARNING);
                } else {
                    trigger_error("SCSS: @import '$path' could not be resolved", E_USER_WARNING);
                }
                return NULL;
            });

            $result = $this->scssCompiler->compile($content, $file);

            if(!is_dir($cacheDir = "$this->cacheDirectory/scss_cache/"))
                @mkdir($cacheDir, 0777, true);
            error_clear_last();

            $f = md5($component->getConfig()['l'] ?? "$componentName:$elementName");
            file_put_contents($cacheDir . DIRECTORY_SEPARATOR . $f . ".css", $result);

            echo "Compiled: $file\n";

        } catch (CompilerException $exception) {
            trigger_error($exception->getMessage(), E_USER_ERROR);
        }
    }
}