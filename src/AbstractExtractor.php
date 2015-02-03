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

use Closure;

abstract class AbstractExtractor implements ExtractorInterface
{
    /**
     * Element mapper
     *
     * @access  protected
     */
    protected $mapper = array();

    /**
     * {@inheritdoc}
     */
    public function validate(array $mapper)
    {
        if (empty($mapper['properties'])) {
            throw new ExtractorException('Mapper properties empty/not set');
        }

        if (! is_array($mapper['properties'])) {
            throw new ExtractorException('Mapper properties must be an array');
        }

        // if unavailable, set a default callback
        if (! isset($mapper['callback'])) {
            $mapper['callback'] = function (array $data) {
                var_dump($data);
            };
        }

        if (! $mapper['callback'] instanceof Closure) {
            throw new ExtractorException('Callbacks must be instances of Closure');
        }

        return $mapper;
    }
}
