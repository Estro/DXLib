<?php
/**
 * This file is part of the Data Extraction library.
 *
 * @author     Quetzy Garcia <quetzyg@altek.org>
 * @copyright  2014-2015
 *
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed
 * with this source code.
 */

namespace Huaztli\DXLib;

use DOMDocument;
use DOMException;
use DOMXPath;
use Exception;
use LibXMLError;
use SplFileObject;
use XMLReader;

class XMLExtractor extends AbstractExtractor
{
    /**
     * XML Reader object
     *
     * @access  private
     */
    private $reader = null;

    /**
     * Registered Element data
     *
     * @access  private
     */
    private $data = array();

    /**
     * Current Element path stack
     *
     * @access  private
     */
    private $stack = array();

    /**
     * Current Element XPath
     *
     * @access  private
     */
    private $current = null;

    /**
     * Skip to Element
     *
     * @access  private
     */
    private $skip = null;

    /**
     * XMLExtractor constructor
     *
     * @access  public
     * @param   array  $mappers Element mappers
     * @throws  DXException
     * @return  XMLExtractor
     */
    public function __construct(array $mappers)
    {
        foreach ($mappers as $element => $mapper) {
            $this->mapper[$element] = $this->validate($mapper);
        }

        $this->reader = new XMLReader();

        if (! $this->reader instanceof XMLReader) {
            throw new DXException('Unable to create XMLReader instance');
        }

        // manually handle libXML errors
        libxml_use_internal_errors(true);
        libxml_clear_errors();
    }

    /**
     * Free resources
     *
     * @access  public
     * @return  void
     */
    public function __destruct()
    {
        $this->reader->close();
    }

    /**
     * Convert libXML error to Exception
     *
     * @access  private
     * @throws  Exception
     * @return  void
     */
    private function libXMLErrorToException()
    {
        $error = libxml_get_last_error();

        if ($error instanceof LibXMLError) {
            libxml_clear_errors();

            if ($error->level !== LIBXML_ERR_WARNING) {
                throw new DXException(sprintf('"%s" @ line #%d %s', $this->current, $error->line, $error->message), $error->code);
            }
        }
    }

    /**
     * Read the next Element + handle skipping
     *
     * @access  private
     * @return  bool
     */
    private function nextElement()
    {
        do {
            if (! $this->reader->read()) {
                return false;
            }

            // pop levels above the current Element
            $this->stack = array_slice($this->stack, 0, $this->reader->depth, true);

            // push current Element to the stack
            $this->stack[] = $this->reader->name;

            // update the current Element path
            $this->current = implode('/', $this->stack);

            // skip to Element
            $this->skip = ($this->skip == $this->current) ? null : $this->skip;

        } while ($this->skip !== null);

        return true;
    }

    /**
     * Get the data of an Element
     *
     * @access  private
     * @param   string  $xpath Element XPath
     * @throws  DXException
     * @return  mixed
     */
    private function getData($xpath = null)
    {
        if (isset($this->data[$xpath])) {
           return $this->data[$xpath];
        }

        throw new DXException('Unknown Element: "'.$xpath.'"');
    }

    /**
     * {@inheritdoc}
     */
    public function run($input, array $config = array(), $data = null)
    {
        $config = array_merge(array(
            'encoding'   => 'UTF-8',
            'options'    => LIBXML_PARSEHUGE,
            'namespaces' => array()
        ), $config);

        switch (true) {
            case $input instanceof SplFileObject:
                if (! $this->reader->open($input->getPathname(), $config['encoding'], $config['options'])) {
                    throw new DXException('Could not open "'.$input->getRealPath().'" for parsing');
                }
                break;

            case is_string($input):
                if (! $this->reader->XML($input, $config['encoding'], $config['options'])) {
                    throw new DXException('Could not set the XML input string for parsing');
                }
                break;

            default:
                throw new DXException('Invalid input type: '.gettype($input));
        }

        $doc = new DOMDocument();
        $element = new DOMXPath($doc);

        // namespace registration
        foreach ($config['namespaces'] as $prefix => $ns_uri) {
            $element->registerNamespace($prefix, $ns_uri);
        }

        // extract data
        while ($this->nextElement()) {
            if (! $this->reader->isEmptyElement && $this->reader->nodeType === XMLReader::ELEMENT && isset($this->mapper[$this->current])) {

                $dom_node = $this->reader->expand();

                $this->libXMLErrorToException();

                try {
                    // node for XPath evaluation
                    $node = $doc->importNode($dom_node, true);

                } catch (DOMException $e) {
                    throw new DXException('Node import failed', 0, $e);
                }

                $argument = array(
                    'element'    => $this->current,
                    'properties' => array(),
                    'data'       => $data
                );

                foreach($this->mapper[$this->current]['properties'] as $key => $xpath) {

                    $xpath = trim($xpath);

                    // get registered Element data
                    if (strpos($xpath, '#') === 0) {
                        $argument['properties'][$key] = $this->getData(substr($xpath, 1));

                    // get evaluated XPath data
                    } else {
                        $argument['properties'][$key] = $element->evaluate($xpath, $node);

                        if ($argument['properties'][$key] === false) {
                            throw new DXException('Invalid XPath expression: "'.$xpath.'"');
                        }
                    }
                }

                try {
                    $result = call_user_func($this->mapper[$this->current]['callback'], $argument);

                    if ($result) {
                        // skip to Element
                        if (isset($this->data[$result])) {
                            $this->skip = $result;

                        // store Element data
                        } else {
                            $this->data[$this->current] = $result;
                        }
                    }

                } catch (Exception $e) {
                    throw new DXException('An error occurred while executing the callback function: '.$e->getMessage());
                }
            }
        }

        return true;
    }
}
