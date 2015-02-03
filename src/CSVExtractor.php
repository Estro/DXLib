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

use Exception;
use RuntimeException;
use SplFileObject;

class CSVExtractor extends AbstractExtractor
{
    /**
     * CSVExtractor constructor
     *
     * @access  public
     * @param   array  $mapper Element mapper
     * @throws  ExtractorException
     * @return  CSVExtractor
     */
    public function __construct(array $mapper)
    {
        $this->mapper = $this->validate($mapper);
    }

    /**
     * {@inheritdoc}
     */
    public function run($input, array $config = array())
    {
        $config = array_merge(array(
            'delimiter'  => ',',
            'enclosure'  => '"',
            'escape'     => '\\',
            'start_line' => 0,
            'exceptions' => true, // throw exception on invalid indexes
            'auto_eol'   => false // end of line auto detection
        ), $config);

        switch (true) {
            case $input instanceof SplFileObject:
                try {
                    ini_set('auto_detect_line_endings', $config['auto_eol']);

                    $input->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
                    $input->setCsvControl($config['delimiter'], $config['enclosure'], $config['escape']);
                    $input->rewind();

                } catch (RuntimeException $e) {
                    throw new ExtractorException('Could not open "'.$input->getRealPath().'" for parsing.', 0 ,$e);
                }
                break;

            case is_string($input):
                $lines = preg_split('/\R/', $input, null, PREG_SPLIT_NO_EMPTY);

                $input = array();

                foreach ($lines as $line) {
                    $input[] = str_getcsv($line, $config['delimiter'], $config['enclosure'], $config['escape']);
                }
                break;

            default:
                throw new ExtractorException('Invalid input type: '.gettype($input));
        }

        // extract data
        foreach ($input as $line => $element) {

            // skip until we reach the starting line
            if ($line < $config['start_line']) {
                continue;
            }

            $data = array(
                'properties' => array()
            );

            foreach ($this->mapper['properties'] as $key => $column) {

                if (isset($element[$column])) {
                    $data['properties'][$key] = $element[$column];

                    continue;
                }

                // halt extraction on invalid index
                if ($config['exceptions']) {
                    throw new ExtractorException('Invalid column '.$column.' @ line '.$line.' for property "'.$key.'"');
                }
            }

            try {
                call_user_func($this->mapper['callback'], $data);

            } catch (Exception $e) {
                throw new ExtractorException('An error occurred while executing the callback function', 0, $e);
            }
        }

        return true;
    }
}
