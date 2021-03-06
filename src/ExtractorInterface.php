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

interface ExtractorInterface
{
    /**
     * Validate an Element mapper
     *
     * @access  public
     * @param   array  $mapper Element mapper
     * @throws  DXException
     * @return  array
     */
    public function validate(array $mapper);

    /**
     * Execute the data extraction
     *
     * @access  public
     * @param   string $input  Input data
     * @param   array  $config Configuration settings
     * @param   mixed  $data   Optional data to pass with the callback argument
     * @throws  DXException
     * @return  bool
     */
    public function run($input, array $config = array(), $data = null);
}
